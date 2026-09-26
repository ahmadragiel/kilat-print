<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Services\OrderStatusService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_central_service_records_valid_transition_and_actor_history(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());

        $updated = app(OrderStatusService::class)->transition(
            $order,
            OrderStatus::PAYMENT_REVIEW,
            $admin,
            'Proof submitted for review.',
        );

        $this->assertSame(OrderStatus::PAYMENT_REVIEW, $updated->status);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PAYMENT_REVIEW->value,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'changed_by' => $admin->id,
            'old_status' => OrderStatus::PENDING_PAYMENT->value,
            'new_status' => OrderStatus::PAYMENT_REVIEW->value,
            'note' => 'Proof submitted for review.',
        ]);
    }

    public function test_illegal_transition_is_rejected_without_mutating_status_or_history(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());

        try {
            app(OrderStatusService::class)->transition($order, OrderStatus::COMPLETED, $admin);
            $this->fail('Expected an illegal transition to fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_customer_cannot_use_admin_only_transition_even_when_status_is_adjacent(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->expectException(HttpException::class);
        app(OrderStatusService::class)->transition($order, OrderStatus::PAYMENT_CONFIRMED, $customer->user);
    }

    public function test_same_status_transition_is_idempotent_and_does_not_create_history(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), ['status' => OrderStatus::PAYMENT_REVIEW]);

        $result = app(OrderStatusService::class)->transition($order, OrderStatus::PAYMENT_REVIEW, $admin);

        $this->assertSame(OrderStatus::PAYMENT_REVIEW, $result->status);
        $this->assertDatabaseCount('order_status_histories', 0);
    }
}
