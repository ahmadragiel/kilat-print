<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentSetting;
use App\Services\Payment\PaymentSettings;
use App\Services\Payment\PaymentSettlementService;
use App\Services\Payment\PaymentTransactionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * Regressions for retrying a failed charge and for settlement edge cases where a
 * provider confirmation arrives after a local final state.
 */
class PaymentRetryAndSettlementTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install();
    }

    public function test_customer_can_retry_after_the_initial_charge_fails(): void
    {
        $customer = $this->makeCustomer();
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $this->makeProduct(PricingType::PER_ITEM, 20000), 1);
        $this->midtrans->failCharge = true;

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer))
            ->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $this->assertNull($order->payment->provider_reference);

        $this->midtrans->failCharge = false;

        $this->actingAs($customer->user)
            ->post(route('customer.orders.payment-retry', $order))
            ->assertRedirect(route('customer.orders.payment', $order))
            ->assertSessionHas('success');

        $payment = $order->fresh()->payment;
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame($order->number, $payment->provider_reference);
    }

    public function test_retry_does_not_reopen_a_final_payment(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $payment = $this->makeGatewayPayment($order, [
            'status' => PaymentStatus::Expired,
            'expires_at' => now()->subHour(),
        ]);

        $this->actingAs($customer->user)
            ->post(route('customer.orders.payment-retry', $order))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);
        $this->assertCount(0, $this->midtrans->charges);
    }

    public function test_payment_page_offers_a_retry_when_no_provider_reference_exists(): void
    {
        $order = $this->makeOrder($this->makeCustomer());
        $this->makePayment($order, [
            'status' => PaymentStatus::Pending,
            'method' => PaymentMethod::Qris->value,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($order->customer->user)
            ->get(route('customer.orders.payment', $order))
            ->assertOk()
            ->assertSee('/payment/retry', false)
            ->assertSee('Buat transaksi pembayaran')
            ->assertDontSee('Pindai QRIS');
    }

    public function test_order_page_does_not_offer_payment_for_a_final_transaction(): void
    {
        $order = $this->makeOrder($this->makeCustomer());
        $this->makePayment($order, [
            'status' => PaymentStatus::Expired,
            'expires_at' => now()->subHour(),
        ]);

        $this->actingAs($order->customer->user)
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Bayar sekarang');
    }

    public function test_a_locally_expired_payment_can_still_settle_from_a_late_provider_confirmation(): void
    {
        $order = $this->makePendingOrder();
        $order->payment->forceFill(['expires_at' => now()->subMinute()])->save();

        $expired = app(PaymentSettlementService::class)->expireOverduePayments();
        $this->assertSame(1, $expired);
        $this->assertSame(PaymentStatus::Expired, $order->fresh()->payment->status);

        $this->midtrans->settle();

        $result = app(PaymentTransactionService::class)->refresh($order->fresh()->payment);

        $this->assertTrue($result['settled']);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment->status);
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
        $this->assertDatabaseHas('production_orders', ['order_id' => $order->id]);
    }

    public function test_a_provider_expired_payment_stays_final_even_if_a_paid_status_arrives_later(): void
    {
        $order = $this->makePendingOrder();

        $this->midtrans->expire();
        app(PaymentTransactionService::class)->refresh($order->payment);

        $payment = $order->fresh()->payment;
        $this->assertSame(PaymentStatus::Expired, $payment->status);
        $this->assertFalse((bool) $payment->expired_locally);

        $this->midtrans->settle();
        $result = app(PaymentTransactionService::class)->refresh($payment);

        $this->assertFalse($result['settled']);
        $this->assertSame(PaymentStatus::Expired, $order->fresh()->payment->status);
    }

    public function test_a_paid_webhook_does_not_create_production_for_a_cancelled_order(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::Cancelled]);
        $payment = $this->makeGatewayPayment($order, [
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);

        $this->midtrans->settle();
        $this->midtrans->grossAmount = number_format((float) $order->grand_total, 2, '.', '');

        $this->postJson(route('payments.webhook.midtrans'), $this->midtrans->notification($order->number))
            ->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertDatabaseCount('production_orders', 0);
    }

    public function test_sandbox_expire_uses_the_dashboard_server_key(): void
    {
        config([
            'payments.server_key' => 'env-server-key',
            'payments.sandbox' => true,
        ]);

        PaymentSetting::create([
            'driver' => 'midtrans',
            'server_key' => 'dashboard-server-key',
            'sandbox' => true,
        ]);
        app(PaymentSettings::class)->flush();

        $order = $this->makeOrder($this->makeCustomer());
        $payment = $this->makeGatewayPayment($order, [
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.payment-sandbox.expire', $payment))
            ->assertRedirect();

        $expireRequest = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->first(fn ($request) => str_contains((string) $request->url(), '/expire'));

        $this->assertNotNull($expireRequest, 'The sandbox helper did not call the provider expire endpoint.');

        $headers = collect($expireRequest->headers())
            ->mapWithKeys(fn (array $values, string $name) => [strtolower($name) => $values]);

        $this->assertContains(
            'Basic '.base64_encode('dashboard-server-key:'),
            $headers->get('authorization', []),
            'The sandbox expire request did not authenticate with the dashboard server key.',
        );
    }

    private function makePendingOrder(): Order
    {
        $order = $this->makeOrder($this->makeCustomer());

        $payment = $this->makePayment($order, [
            'status' => PaymentStatus::Pending,
            'method' => PaymentMethod::Qris->value,
            'amount' => $order->grand_total,
            'expires_at' => now()->addDay(),
        ]);

        $payment->forceFill([
            'provider' => 'midtrans',
            'provider_reference' => $order->number,
            'provider_status' => 'pending',
        ])->save();

        $this->midtrans->grossAmount = number_format((float) $order->grand_total, 2, '.', '');

        return $order->fresh(['payment', 'customer']);
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(Customer $customer): array
    {
        $address = $customer->addresses()->orderByDesc('is_primary')->first()
            ?? $this->makeAddress($customer);

        return [
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::Qris->value,
        ];
    }
}
