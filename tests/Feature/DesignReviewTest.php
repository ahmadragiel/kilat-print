<?php

namespace Tests\Feature;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Notifications\OrderNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DesignReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_customer_uploads_a_private_design_version_and_moves_order_to_review(): void
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
        $this->assertSame(DesignStatus::PENDING, $design->status);
        $this->assertSame($item->id, $design->order_item_id);
        $this->assertSame('First proof.', $design->notes);
        $this->assertSame(OrderStatus::DESIGN_REVIEW, $order->fresh()->status);
        Storage::disk('local')->assertExists($design->path);
        Notification::assertSentTo($customer->user, OrderNotification::class);
    }

    public function test_admin_approves_latest_design_and_creates_waiting_production_record(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::DESIGN_REVIEW]);
        $design = $this->makeDesign($order);

        $response = $this->actingAs($admin)->post(route('admin.designs.approve', $design));

        $response->assertSessionHas('success');
        $this->assertSame(DesignStatus::APPROVED, $design->fresh()->status);
        $this->assertSame($admin->id, $design->fresh()->reviewed_by);
        $this->assertSame(OrderStatus::DESIGN_APPROVED, $order->fresh()->status);
        $this->assertDatabaseHas('production_orders', [
            'order_id' => $order->id,
            'status' => ProductionStatus::WAITING_PRODUCTION->value,
        ]);
    }

    public function test_revision_reason_is_required_and_only_the_newest_version_can_be_approved(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::DESIGN_REVIEW]);
        $oldDesign = $this->makeDesign($order, [
            'version' => 1,
            'status' => DesignStatus::PENDING,
        ]);
        Storage::disk('local')->put($oldDesign->path, 'old design');

        $this->actingAs($admin)
            ->post(route('admin.designs.revision', $oldDesign), [])
            ->assertSessionHasErrors('reason');
        $this->assertSame(DesignStatus::PENDING, $oldDesign->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.designs.revision', $oldDesign), ['reason' => 'Please increase the logo size.'])
            ->assertSessionHas('success');
        $this->assertSame(DesignStatus::REVISION_REQUIRED, $oldDesign->fresh()->status);
        $this->assertSame('Please increase the logo size.', $oldDesign->fresh()->review_note);
        $this->assertSame(OrderStatus::DESIGN_REVISION, $order->fresh()->status);

        $this->actingAs($customer->user)
            ->post(route('customer.orders.design', $order), [
                'design' => UploadedFile::fake()->create('layout-v2.pdf', 50, 'application/pdf'),
                'notes' => 'Logo enlarged.',
            ])
            ->assertSessionHas('success');
        $newDesign = $order->designFiles()->orderByDesc('version')->firstOrFail();
        $this->assertSame(2, $newDesign->version);
        $this->assertSame(OrderStatus::DESIGN_REVIEW, $order->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.designs.approve', $oldDesign))
            ->assertSessionHas('operation');
        $this->actingAs($admin)
            ->post(route('admin.designs.approve', $newDesign))
            ->assertSessionHas('success');
        $this->assertSame(DesignStatus::APPROVED, $newDesign->fresh()->status);
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
