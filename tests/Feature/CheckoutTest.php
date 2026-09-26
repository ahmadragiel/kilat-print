<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Notifications\OrderNotification;
use App\Services\OrderService;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_checkout_creates_numbered_order_snapshots_payment_history_notification_and_clears_cart(): void
    {
        config(['printing.delivery_fee' => 15000]);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 12500);
        $material = $this->attachMaterial($product, PricingType::PER_ITEM, 1000);
        $finishing = $this->attachFinishing($product, PricingType::PER_ITEM, 500);
        $cart = $this->makeCart($customer);
        $item = $this->makeCartItem($cart, $product, 2, [
            'size' => 'Large',
            'material_id' => $material->id,
            'finishing_id' => $finishing->id,
            'color' => 'Navy',
            'price_at_addition' => 1,
            'custom_parameters' => ['source' => 'cart-test'],
        ]);

        $response = $this->actingAs($customer->user)->post(route('checkout.store'), [
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'recipient' => 'Budi Snapshot',
            'address_phone' => '081299999999',
            'address' => 'Jalan Snapshot No. 9',
            'district' => 'Gubeng',
            'city' => 'Jakarta Timur',
            'province' => 'DKI Jakarta',
            'postal_code' => '14210',
        ]);

        $order = $customer->orders()->latest('id')->firstOrFail();
        $response->assertRedirect(route('customer.orders.show', $order));
        $this->assertMatchesRegularExpression('/^KP-\d{8}-\d{4}$/', $order->number);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame($customer->user->name, $order->customer_name);
        $this->assertSame('081200000001', $order->customer_phone);
        $this->assertSame($customer->user->email, $order->customer_email);
        $this->assertSame(25000 + 1000 * 2 + 500 * 2, (int) $order->subtotal);
        $this->assertSame(15000, (int) $order->shipping_fee);
        $this->assertSame((int) $order->subtotal + 15000, (int) $order->grand_total);

        $snapshot = $order->items()->sole();
        $this->assertSame($product->id, $snapshot->product_id);
        $this->assertSame($product->name, $snapshot->product_name);
        $this->assertSame(2, $snapshot->quantity);
        $this->assertSame($material->name, $snapshot->material_name);
        $this->assertSame($finishing->name, $snapshot->finishing_name);
        $this->assertSame('Navy', $snapshot->color);
        $this->assertSame(['source' => 'cart-test'], $snapshot->custom_parameters);
        $this->assertSame(28000, (int) $snapshot->line_total);

        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'recipient' => 'Budi Snapshot',
            'address' => 'Jalan Snapshot No. 9',
            'city' => 'Jakarta Timur',
            'postal_code' => '14210',
            'shipping_method' => 'delivery',
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::UNPAID->value,
            'amount' => $order->grand_total,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'old_status' => null,
            'new_status' => OrderStatus::PENDING_PAYMENT->value,
            'changed_by' => $customer->user_id,
        ]);
        Notification::assertSentTo($customer->user, OrderNotification::class);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_recalculates_stored_cart_prices_before_snapshotting_the_order(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_SQM, 20000);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product, 2, [
            'length_cm' => 100,
            'width_cm' => 50,
            'price_at_addition' => 999999,
        ]);

        $this->actingAs($customer->user)->post(route('checkout.store'), [
            'name' => 'Customer Test',
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'pickup',
            'recipient' => 'Recipient',
            'address_phone' => '081200000001',
            'address' => 'Address',
            'district' => 'District',
            'city' => 'City',
            'province' => 'Province',
            'postal_code' => '12345',
        ])->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $this->assertSame(20000, (int) $order->subtotal);
        $this->assertSame(20000, (int) $order->items()->sole()->line_total);
    }

    public function test_checkout_rolls_back_when_recalculation_fails(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $cart = $this->makeCart($customer);
        $item = $this->makeCartItem($cart, $product);
        $product->update(['status' => 'inactive']);

        $this->expectException(HttpException::class);
        try {
            app(OrderService::class)->checkout($customer, [
                'shipping_method' => 'pickup',
                'recipient' => 'Recipient',
                'address_phone' => '081200000001',
                'address' => 'Address',
                'district' => 'District',
                'city' => 'City',
                'province' => 'Province',
                'postal_code' => '12345',
            ]);
        } finally {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseHas('cart_items', ['id' => $item->id]);
            $this->assertSame(1, $cart->items()->count());
        }
    }
}
