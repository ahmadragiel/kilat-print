<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Enums\ProductionStatus;
use App\Models\Customer;
use App\Notifications\OrderNotification;
use App\Services\OrderService;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install();
    }

    public function test_checkout_creates_a_numbered_order_waiting_for_payment_and_clears_the_cart(): void
    {
        config(['printing.delivery_fee' => 15000]);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 12500);
        $address = $this->makeAddress($customer, [
            'label' => 'Kantor',
            'recipient' => 'Budi Snapshot',
            'phone' => '081299999999',
            'address' => 'Jalan Snapshot No. 9',
            'district' => 'Gubeng',
            'city' => 'Jakarta Timur',
            'province' => 'DKI Jakarta',
            'postal_code' => '14210',
        ]);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product, 2, [
            'size' => 'Large',
            'color' => 'Navy',
            'price_at_addition' => 1,
            'custom_parameters' => ['source' => 'cart-test'],
        ]);

        $response = $this->actingAs($customer->user)->post(route('checkout.store'), [
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::Qris->value,
        ]);

        $order = $customer->orders()->latest('id')->firstOrFail();
        $response->assertRedirect(route('customer.orders.payment', $order));
        $this->assertMatchesRegularExpression('/^KP-\d{8}-\d{4}$/', $order->number);

        // Checkout must never mark the order as paid.
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->production);

        $this->assertSame($customer->user->name, $order->customer_name);
        $this->assertSame('081200000001', $order->customer_phone);
        $this->assertSame($customer->user->email, $order->customer_email);
        $this->assertSame(25000, (int) $order->subtotal);
        $this->assertSame(15000, (int) $order->shipping_fee);
        $this->assertSame((int) $order->subtotal + 15000, (int) $order->grand_total);

        $snapshot = $order->items()->sole();
        $this->assertSame($product->id, $snapshot->product_id);
        $this->assertSame($product->name, $snapshot->product_name);
        $this->assertSame(2, $snapshot->quantity);
        // Neither material nor finishing is chosen or snapshotted by the customer.
        $this->assertNull($snapshot->material_id);
        $this->assertNull($snapshot->finishing_id);
        $this->assertSame('Navy', $snapshot->color);
        $this->assertSame(['source' => 'cart-test'], $snapshot->custom_parameters);
        $this->assertSame(25000, (int) $snapshot->line_total);

        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'label' => 'Kantor',
            'recipient' => 'Budi Snapshot',
            'phone' => '081299999999',
            'address' => 'Jalan Snapshot No. 9',
            'city' => 'Jakarta Timur',
            'postal_code' => '14210',
            'shipping_method' => 'delivery',
        ]);

        // The transaction is created through the provider and starts as PENDING.
        $payment = $order->payment;
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(PaymentMethod::Qris->value, $payment->method);
        $this->assertSame('midtrans', $payment->provider);
        $this->assertSame($order->number, $payment->provider_reference);
        $this->assertNull($payment->paid_at);
        // No proof is uploaded or stored anywhere in the flow.
        $this->assertNull($payment->proof_path);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'old_status' => null,
            'new_status' => OrderStatus::PENDING_PAYMENT->value,
            'changed_by' => $customer->user_id,
        ]);
        // Production only starts after a verified payment.
        $this->assertDatabaseCount('production_orders', 0);
        Notification::assertSentTo($customer->user, OrderNotification::class);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_sends_the_documented_qris_request_to_the_provider(): void
    {
        config(['printing.delivery_fee' => 15000]);
        $customer = $this->makeCustomer();
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $this->makeProduct(PricingType::PER_ITEM, 20000), 3);

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer))
            ->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $payload = $this->midtrans->chargePayloadFor($order->number);

        $this->assertNotNull($payload, 'The driver did not send a charge request to the provider.');
        $this->assertSame('qris', $payload['payment_type']);
        $this->assertSame($order->number, $payload['transaction_details']['order_id']);
        $this->assertSame(75000, $payload['transaction_details']['gross_amount']);
        $this->assertSame('hour', $payload['custom_expiry']['unit']);
        // Midtrans names this field `expiry_duration`; sending `duration` is
        // rejected with "custom_expiry.expiry_duration is required".
        $this->assertSame(24, $payload['custom_expiry']['expiry_duration']);
        $this->assertArrayNotHasKey('duration', $payload['custom_expiry']);
    }

    public function test_checkout_requires_a_supported_payment_method(): void
    {
        $customer = $this->makeCustomer();
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $this->makeProduct(PricingType::PER_ITEM, 15000), 1);

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer, paymentMethod: 'COD'))
            ->assertSessionHasErrors('payment_method');

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer, paymentMethod: null))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_page_offers_qris_and_bank_transfer_without_a_proof_upload_form(): void
    {
        $customer = $this->makeCustomer();
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $this->makeProduct(PricingType::PER_ITEM, 15000), 2, ['price_at_addition' => 30000]);
        $this->makeAddress($customer);

        $response = $this->actingAs($customer->user)->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee('Metode pembayaran');
        $response->assertSee('QRIS');
        $response->assertSee('Transfer Bank');
        $response->assertDontSee('Unggah bukti pembayaran', false);
        $response->assertDontSee('Pilih bahan', false);
        $response->assertDontSee('Pilih finishing', false);
    }

    public function test_bank_transfer_checkout_stores_the_virtual_account_reference(): void
    {
        $customer = $this->makeCustomer();
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $this->makeProduct(PricingType::PER_ITEM, 20000), 1);

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer, paymentMethod: PaymentMethod::BankTransfer->value))
            ->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $payment = $order->payment;

        $this->assertSame(PaymentMethod::BankTransfer->value, $payment->method);
        // Midtrans returns the account inside `va_numbers`.
        $this->assertSame('91019021579', $payment->virtual_account_number);
        $this->assertSame('bca', $payment->virtual_account_bank);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('bank_transfer', $this->midtrans->chargePayloadFor($order->number)['payment_type']);
        $this->assertSame(['bank' => 'bca'], $this->midtrans->chargePayloadFor($order->number)['bank_transfer']);
    }

    public function test_order_enters_the_production_queue_only_after_the_provider_confirms(): void
    {
        $customer = $this->makeCustomer();
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $this->makeProduct(PricingType::PER_ITEM, 20000), 1);

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer))
            ->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();

        // The provider still reports the transaction as pending.
        $this->actingAs($customer->user)
            ->post(route('customer.orders.payment-refresh', $order))
            ->assertRedirect();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertDatabaseCount('production_orders', 0);

        $this->midtrans->settle();

        $this->actingAs($customer->user)
            ->post(route('customer.orders.payment-refresh', $order))
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::Paid, $fresh->payment->status);
        $this->assertSame(OrderStatus::PaymentConfirmed, $fresh->status);
        $this->assertDatabaseHas('production_orders', [
            'order_id' => $order->id,
            'status' => ProductionStatus::IN_DESIGN->value,
        ]);
    }

    public function test_order_is_kept_when_the_provider_rejects_the_charge_request(): void
    {
        $customer = $this->makeCustomer();
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $this->makeProduct(PricingType::PER_ITEM, 20000), 1);
        $this->midtrans->failCharge = true;

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer))
            ->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();

        // The order exists and stays unpaid; no fake success is shown.
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment->status);
        $this->assertNull($order->payment->provider_reference);
        $this->assertDatabaseCount('production_orders', 0);
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

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer))
            ->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $this->assertSame(20000, (int) $order->subtotal);
        $this->assertSame(20000, (int) $order->items()->sole()->line_total);
    }

    public function test_checkout_rolls_back_when_recalculation_fails(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $address = $this->makeAddress($customer);
        $cart = $this->makeCart($customer);
        $item = $this->makeCartItem($cart, $product);
        $product->update(['status' => 'inactive']);

        $this->expectException(HttpException::class);
        try {
            app(OrderService::class)->checkout($customer, [
                'name' => $customer->user->name,
                'phone' => '081200000001',
                'email' => $customer->user->email,
                'shipping_method' => 'delivery',
                'address_id' => $address->id,
                'payment_method' => PaymentMethod::Qris->value,
            ]);
        } finally {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseHas('cart_items', ['id' => $item->id]);
            $this->assertSame(1, $cart->items()->count());
        }
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(Customer $customer, ?string $paymentMethod = PaymentMethod::Qris->value): array
    {
        $address = $customer->addresses()->orderByDesc('is_primary')->first()
            ?? $this->makeAddress($customer);

        return array_filter([
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'address_id' => $address->id,
            'payment_method' => $paymentMethod,
        ], fn ($value) => $value !== null);
    }
}
