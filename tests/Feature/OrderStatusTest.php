<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Services\OrderStatusService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_payment_provider_transition_records_history_without_an_actor(): void
    {
        $order = $this->makeOrder($this->makeCustomer());

        $updated = app(OrderStatusService::class)->systemTransition(
            $order,
            OrderStatus::PaymentConfirmed,
            'Pembayaran dikonfirmasi otomatis oleh penyedia pembayaran.',
        );

        $this->assertSame(OrderStatus::PaymentConfirmed, $updated->status);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PaymentConfirmed->value,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'changed_by' => null,
            'old_status' => OrderStatus::PENDING_PAYMENT->value,
            'new_status' => OrderStatus::PaymentConfirmed->value,
        ]);
    }

    public function test_admin_cannot_confirm_payment_without_the_provider(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());

        $this->expectException(HttpException::class);
        app(OrderStatusService::class)->transition($order, OrderStatus::PAYMENT_CONFIRMED, $admin);
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
        $order = $this->makePaidOrder($this->makeOrder($customer));

        $this->expectException(HttpException::class);
        app(OrderStatusService::class)->transition($order, OrderStatus::InProduction, $customer->user);
    }

    public function test_order_cannot_enter_production_while_the_payment_is_unpaid(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::PaymentConfirmed]);
        $production = $this->makeProduction($order, ['operator_id' => $this->makeOperator()->id]);
        $this->makePayment($order, ['status' => PaymentStatus::Pending]);

        $this->expectException(HttpException::class);
        app(OrderStatusService::class)->transition($order, OrderStatus::InProduction, $admin);
    }

    public function test_same_status_transition_is_idempotent_and_does_not_create_history(): void
    {
        $order = $this->makePaidOrder($this->makeOrder($this->makeCustomer()));

        $result = app(OrderStatusService::class)->transition($order, OrderStatus::PaymentConfirmed, $this->makeAdmin());

        $this->assertSame(OrderStatus::PaymentConfirmed, $result->status);
        $this->assertDatabaseCount('order_status_histories', 0);
    }
}
