<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Services\OrderStatusService;
use App\Services\ProductionService;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderCompletionWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_admin_cannot_complete_an_order_while_production_is_still_in_qc(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $operator = $this->makeOperator();
        $order = $this->makePaidOrder($this->makeOrder($customer));
        $order->forceFill(['status' => OrderStatus::IN_PRODUCTION])->save();
        $production = $this->makeProduction($order, [
            'operator_id' => $operator->id,
            'status' => ProductionStatus::QUALITY_CONTROL,
            'progress' => 90,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.status', $order), [
                'status' => OrderStatus::COMPLETED->value,
                'note' => 'Mencoba menyelesaikan tanpa QC.',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('operation');

        $this->assertSame(OrderStatus::IN_PRODUCTION, $order->fresh()->status);
        $this->assertNull($order->fresh()->completed_at);
        $this->assertSame(ProductionStatus::QUALITY_CONTROL, $production->fresh()->status);
        $this->assertNull($production->fresh()->completed_at);
        $this->assertDatabaseCount('quality_checks', 0);
        $this->assertDatabaseMissing('production_status_histories', [
            'production_order_id' => $production->id,
            'new_status' => ProductionStatus::COMPLETED->value,
        ]);
    }

    public function test_admin_order_page_does_not_offer_completion_without_qc(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $operator = $this->makeOperator();
        $order = $this->makePaidOrder($this->makeOrder($customer));
        $order->forceFill(['status' => OrderStatus::IN_PRODUCTION])->save();
        $this->makeProduction($order, [
            'operator_id' => $operator->id,
            'status' => ProductionStatus::QUALITY_CONTROL,
            'progress' => 90,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('value="COMPLETED"', false)
            ->assertSee('value="CANCELLED"', false);
    }

    public function test_admin_can_still_cancel_an_order_that_is_in_production(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $operator = $this->makeOperator();
        $order = $this->makePaidOrder($this->makeOrder($customer));
        $order->forceFill(['status' => OrderStatus::IN_PRODUCTION])->save();
        $production = $this->makeProduction($order, [
            'operator_id' => $operator->id,
            'status' => ProductionStatus::PRINTING,
            'progress' => 25,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.status', $order), [
                'status' => OrderStatus::CANCELLED->value,
                'note' => 'Dibatalkan oleh admin.',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(ProductionStatus::CANCELLED, $production->fresh()->status);
    }

    public function test_operator_cannot_complete_an_order_directly_without_a_quality_check(): void
    {
        $customer = $this->makeCustomer();
        $operator = $this->makeOperator();
        $order = $this->makePaidOrder($this->makeOrder($customer));
        $order->forceFill(['status' => OrderStatus::IN_PRODUCTION])->save();
        $this->makeProduction($order, [
            'operator_id' => $operator->id,
            'status' => ProductionStatus::PRINTING,
            'progress' => 25,
        ]);

        $this->expectException(HttpException::class);
        app(OrderStatusService::class)->transition($order, OrderStatus::COMPLETED, $operator->user);
    }

    public function test_operator_qc_pass_is_the_path_that_completes_the_order(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $operator = $this->makeOperator();
        $order = $this->makePaidOrder($this->makeOrder($customer, [
            'status' => OrderStatus::PAYMENT_CONFIRMED,
        ]));
        $production = $this->makeProduction($order);
        app(ProductionService::class)->assign($production, $operator, $admin);

        foreach (['PRINTING', 'FINISHING', 'PACKING', 'QUALITY_CONTROL'] as $status) {
            $this->actingAs($operator->user)
                ->post(route('operator.jobs.status', $production), ['status' => $status])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($operator->user)
            ->post(route('operator.jobs.quality-check', $production), [
                'result' => 'PASS',
                'notes' => 'Lolos QC.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProductionStatus::COMPLETED, $production->fresh()->status);
        $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status);
        $this->assertDatabaseHas('quality_checks', [
            'production_order_id' => $production->id,
            'result' => 'PASS',
        ]);
    }
}
