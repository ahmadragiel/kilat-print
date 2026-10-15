<?php

namespace Tests\Feature;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Notifications\OrderNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards the removal of the manual admin design review.
 *
 * The customer can still produce and attach designs; there is simply no review
 * stage between the upload and production.
 */
class DesignUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_customer_upload_is_immediately_print_ready(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::PAYMENT_CONFIRMED]);
        $item = $this->makeOrderItem($order, $product);

        $response = $this->actingAs($customer->user)->post(route('customer.orders.design', $order), [
            'design' => UploadedFile::fake()->create('layout.pdf', 50, 'application/pdf'),
            'order_item_id' => $item->id,
            'notes' => 'First proof.',
        ]);

        $response->assertSessionHas('success');
        $design = $order->designFiles()->sole();
        $this->assertSame(1, $design->version);
        // No admin review step: the design is stored as print-ready.
        $this->assertSame(DesignStatus::APPROVED, $design->status);
        $this->assertNull($design->review_note);
        $this->assertNull($design->reviewed_by);
        $this->assertSame($item->id, $design->order_item_id);
        $this->assertSame('First proof.', $design->notes);
        Storage::disk('local')->assertExists($design->path);
        $this->assertDatabaseCount('design_approvals', 0);
        Notification::assertSentTo($customer->user, OrderNotification::class);
    }

    public function test_design_upload_does_not_change_the_order_status(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::IN_PRODUCTION]);

        $this->actingAs($customer->user)->post(route('customer.orders.design', $order), [
            'design' => UploadedFile::fake()->create('revised.pdf', 50, 'application/pdf'),
        ])->assertSessionHas('success');

        $this->assertSame(OrderStatus::IN_PRODUCTION, $order->fresh()->status);
        $this->assertSame(DesignStatus::APPROVED, $order->designFiles()->sole()->status);
    }

    public function test_each_upload_bumps_the_design_version(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::PAYMENT_CONFIRMED]);
        $this->makeDesign($order, ['version' => 1, 'status' => DesignStatus::APPROVED]);

        $this->actingAs($customer->user)->post(route('customer.orders.design', $order), [
            'design' => UploadedFile::fake()->create('layout-v2.pdf', 50, 'application/pdf'),
            'notes' => 'Logo enlarged.',
        ])->assertSessionHas('success');

        $newest = $order->designFiles()->orderByDesc('version')->firstOrFail();
        $this->assertSame(2, $newest->version);
        $this->assertSame(DesignStatus::APPROVED, $newest->status);
    }

    public function test_manual_design_approval_routes_no_longer_exist(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertNull($routes->getByName('admin.designs.approve'));
        $this->assertNull($routes->getByName('admin.designs.revision'));
        $this->assertNull($routes->getByName('admin.designs.send-review'));
        $this->assertNull($routes->getByName('customer.orders.design.approve'));
        $this->assertNull($routes->getByName('customer.orders.design.revision'));
    }

    public function test_admin_design_listing_still_renders_without_approval_actions(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makePaidOrder($this->makeOrder($customer, ['status' => OrderStatus::PaymentConfirmed]));
        $this->makeDesign($order, ['status' => DesignStatus::APPROVED]);

        $response = $this->actingAs($admin)->get(route('admin.designs.index'));

        $response->assertOk();
        $response->assertDontSee('Minta revisi', false);
    }

    public function test_customer_cannot_upload_for_another_customers_order(): void
    {
        $owner = $this->makeCustomer();
        $other = $this->makeCustomer();
        $order = $this->makeOrder($owner, ['status' => OrderStatus::PAYMENT_CONFIRMED]);

        $this->actingAs($other->user)
            ->post(route('customer.orders.design', $order), [
                'design' => UploadedFile::fake()->create('intruder.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
        $this->assertDatabaseCount('design_files', 0);
    }
}
