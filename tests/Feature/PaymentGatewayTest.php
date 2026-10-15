<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\GatewayException;
use App\Services\Payment\PaymentSettlementService;
use App\Services\Payment\PaymentTransactionService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * Covers the gateway-backed payment rules: a transaction only becomes paid when
 * the provider confirms it, and nothing a customer or admin submits can skip
 * that step.
 *
 * The Midtrans HTTP endpoints are stubbed with the documented payload shapes
 * (`va_numbers`, `generate-qr-code`, `signature_key` in the notification body).
 * These tests do not prove a sandbox or live payment succeeded.
 */
class PaymentGatewayTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install();
    }

    public function test_order_is_not_paid_when_the_customer_only_picks_a_payment_method(): void
    {
        $order = $this->makePendingOrder();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment->status);
        $this->assertNull($order->payment->paid_at);
        $this->assertNull($order->paid_at);
    }

    public function test_customer_cannot_post_a_paid_status_on_the_payment_routes(): void
    {
        $order = $this->makePendingOrder();
        $customer = $order->customer;

        $this->actingAs($customer->user)
            ->post(route('customer.orders.payment-refresh', $order), ['status' => 'PAID'])
            ->assertRedirect();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);

        $this->actingAs($customer->user)
            ->post(route('customer.orders.show', $order), ['status' => 'PAID'])
            ->assertStatus(405);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_valid_provider_confirmation_marks_the_payment_as_paid(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        $this->actingAs($order->customer->user)
            ->post(route('customer.orders.payment-refresh', $order))
            ->assertRedirect();

        $payment = $order->fresh()->payment;

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('settlement', $payment->provider_status);
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_signed_webhook_marks_the_payment_as_paid(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        $this->notify($order->number)->assertOk()->assertJson(['status' => PaymentStatus::Paid->value]);

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment->status);
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'changed_by' => null,
            'new_status' => OrderStatus::PaymentConfirmed->value,
        ]);
    }

    /** Midtrans authenticates a notification with signature_key inside the body. */
    public function test_notification_is_accepted_when_the_body_signature_is_valid(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        $payload = $this->midtrans->notification($order->number);
        $this->assertArrayHasKey('signature_key', $payload);

        $this->postJson(route('payments.webhook.midtrans'), $payload)
            ->assertOk()
            ->assertJson(['status' => PaymentStatus::Paid->value]);
    }

    public function test_webhook_without_a_signature_is_rejected(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        $payload = $this->midtrans->notification($order->number);
        unset($payload['signature_key']);

        $this->postJson(route('payments.webhook.midtrans'), $payload)->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_webhook_with_a_signature_built_from_another_key_is_rejected(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        $payload = $this->midtrans->notification($order->number);
        $payload['signature_key'] = $this->midtrans->signature($payload, 'not-the-server-key');

        $this->postJson(route('payments.webhook.midtrans'), $payload)->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
    }

    public function test_tampered_payload_keeps_the_original_signature_and_is_rejected(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        $payload = $this->midtrans->notification($order->number);
        // Amount is changed after signing, exactly like a forged callback.
        $payload['gross_amount'] = '1.00';

        $this->postJson(route('payments.webhook.midtrans'), $payload)->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
    }

    public function test_webhook_for_an_unknown_transaction_is_not_settled(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        $this->notify('KP-UNKNOWN-0001')->assertNotFound();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
    }

    public function test_confirmation_with_a_different_amount_is_rejected(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settleWithAmount('1.00');

        $this->notify($order->number)->assertOk()->assertJson(['message' => 'amount_mismatch']);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_confirmation_in_a_foreign_currency_is_rejected(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();
        $this->midtrans->currency = 'USD';

        $this->notify($order->number)->assertOk()->assertJson(['message' => 'currency_mismatch']);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
    }

    public function test_confirmation_rejected_by_the_fraud_check_is_not_settled(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();
        $this->midtrans->fraudStatus = 'challenge';

        $this->notify($order->number)->assertOk()->assertJson(['message' => 'fraud_rejected']);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
    }

    /** Midtrans documents status_code 200 as the only success code. */
    public function test_confirmation_with_a_non_success_status_code_is_rejected(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        // status_code 202 is what Midtrans reports for an expired transaction.
        $this->notify($order->number, '202')->assertOk()->assertJson(['message' => 'status_code_rejected']);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
    }

    public function test_duplicate_webhook_does_not_settle_twice(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        $this->notify($order->number)
            ->assertOk()
            ->assertJson(['status' => PaymentStatus::Paid->value]);

        $this->notify($order->number)
            ->assertOk()
            ->assertJson([
                'message' => 'already_paid',
                'status' => PaymentStatus::Paid->value,
            ]);

        $this->assertSame(1, $order->statusHistories()->where('new_status', OrderStatus::PaymentConfirmed->value)->count());
        $this->assertSame(1, $order->production()->count());
    }

    public function test_failed_and_expired_transactions_are_not_shown_as_successful(): void
    {
        $failed = $this->makePendingOrder();
        $this->midtrans->deny();
        $this->notify($failed->number)->assertOk();
        $this->assertSame(PaymentStatus::Failed, $failed->fresh()->payment->status);
        $this->assertSame(OrderStatus::PendingPayment, $failed->fresh()->status);

        $expired = $this->makePendingOrder();
        $this->midtrans->expire();
        $this->notify($expired->number)->assertOk();
        $this->assertSame(PaymentStatus::Expired, $expired->fresh()->payment->status);

        $this->actingAs($expired->customer->user)
            ->get(route('customer.orders.payment', $expired))
            ->assertRedirect(route('customer.orders.show', $expired));
    }

    public function test_a_settled_transaction_is_not_revived_by_a_later_failure_notice(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();
        $this->notify($order->number)->assertOk();

        $this->midtrans->deny();
        $this->notify($order->number)->assertOk();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment->status);
    }

    public function test_a_failed_transaction_is_not_revived_by_a_later_confirmation(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->deny();
        $this->notify($order->number)->assertOk();
        $this->assertSame(PaymentStatus::Failed, $order->fresh()->payment->status);

        $this->midtrans->settle();
        $this->notify($order->number)->assertOk();

        $this->assertSame(PaymentStatus::Failed, $order->fresh()->payment->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_payment_page_shows_the_qr_and_never_a_success_tick_before_confirmation(): void
    {
        $order = $this->makePendingOrder(PaymentMethod::Qris);

        $this->actingAs($order->customer->user)
            ->get(route('customer.orders.payment', $order))
            ->assertOk()
            ->assertSee('https://api.sandbox.midtrans.com/qr/'.$order->number.'.png', false)
            ->assertSee('Menunggu pembayaran')
            ->assertSee('Referensi transaksi')
            ->assertDontSee('Pembayaran Berhasil', false);

        $this->midtrans->settle();
        $this->notify($order->number)->assertOk();

        // Once settled the payment page hands over to the order, which shows the tick.
        $this->actingAs($order->customer->user)
            ->get(route('customer.orders.payment', $order))
            ->assertRedirect(route('customer.orders.show', $order));

        $this->actingAs($order->customer->user)
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertSee('Pembayaran Berhasil', false);
    }

    public function test_payment_page_shows_the_virtual_account_for_bank_transfer(): void
    {
        $order = $this->makePendingOrder(PaymentMethod::BankTransfer);
        $order->payment->forceFill([
            'virtual_account_number' => '91019021579',
            'virtual_account_bank' => 'bca',
        ])->save();

        $this->actingAs($order->customer->user)
            ->get(route('customer.orders.payment', $order))
            ->assertOk()
            ->assertSee('91019021579')
            ->assertSee('Nomor virtual account')
            ->assertSee('Transfer Bank');
    }

    public function test_customer_cannot_open_another_customers_payment_page(): void
    {
        $order = $this->makePendingOrder();
        $intruder = $this->makeCustomer();

        $this->actingAs($intruder->user)
            ->get(route('customer.orders.payment', $order))
            ->assertForbidden();

        $this->actingAs($intruder->user)
            ->post(route('customer.orders.payment-refresh', $order))
            ->assertForbidden();
    }

    public function test_admin_has_no_endpoint_to_verify_or_force_a_payment(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makePendingOrder();
        $routes = app('router')->getRoutes();

        foreach (['admin.payments.verify', 'admin.payments.reject', 'admin.payments.update', 'admin.payments.confirm'] as $name) {
            $this->assertNull($routes->getByName($name), "Route [{$name}] must not exist.");
        }

        $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk();
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
    }

    public function test_admin_can_read_the_transaction_reference_and_time(): void
    {
        $order = $this->makePendingOrder();
        $order->payment->forceFill([
            'provider_reference' => $order->number,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ])->save();
        $order->forceFill(['status' => OrderStatus::PaymentConfirmed, 'paid_at' => now()])->save();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Transfer Bank')
            ->assertSee('Pembayaran berhasil');
    }

    public function test_customer_sees_own_transaction_history_only(): void
    {
        $mine = $this->makePendingOrder();
        $someoneElse = $this->makePendingOrder();

        $this->actingAs($mine->customer->user)
            ->get(route('customer.orders.index'))
            ->assertOk()
            ->assertSee($mine->number)
            ->assertDontSee($someoneElse->number);
    }

    public function test_payment_never_completes_the_order_or_its_production(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();
        $this->notify($order->number)->assertOk();

        $production = $order->fresh()->production;

        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
        $this->assertNotNull($production);
        // Production still starts at the first stage and is not completed.
        $this->assertSame('IN_DESIGN', $production->status->value);
        $this->assertSame(0, (int) $production->progress);
        $this->assertNull($order->fresh()->completed_at);
    }

    public function test_expired_transactions_are_marked_locally_without_being_paid(): void
    {
        $order = $this->makePendingOrder();
        $order->payment->forceFill(['expires_at' => now()->subMinute()])->save();

        $expired = app(PaymentSettlementService::class)->expireOverduePayments();

        $this->assertSame(1, $expired);
        $this->assertSame(PaymentStatus::Expired, $order->fresh()->payment->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_unconfigured_provider_refuses_to_create_a_transaction(): void
    {
        config([
            'payments.driver' => 'null',
            'payments.server_key' => null,
        ]);

        $order = $this->makePendingOrder();
        $order->payment->forceFill(['provider' => 'null', 'provider_reference' => null])->save();

        $this->expectException(GatewayException::class);
        app(PaymentTransactionService::class)->initiate($order, PaymentMethod::Qris);
    }

    /** The charge payload must match the documented Midtrans Core API shape. */
    public function test_qris_charge_uses_the_documented_request_shape(): void
    {
        $order = $this->makePendingOrder(PaymentMethod::Qris);
        $order->payment->forceFill(['provider_reference' => null])->save();

        app(PaymentTransactionService::class)->initiate($order->fresh(), PaymentMethod::Qris);

        $payload = $this->midtrans->chargePayloadFor($order->number);

        $this->assertSame('qris', $payload['payment_type']);
        $this->assertSame($order->number, $payload['transaction_details']['order_id']);
        $this->assertSame((int) $order->grand_total, $payload['transaction_details']['gross_amount']);
        $this->assertArrayNotHasKey('bank_transfer', $payload);
        $this->assertArrayHasKey('customer_details', $payload);
        $this->assertSame('hour', $payload['custom_expiry']['unit']);
    }

    /** Midtrans requires the acquiring bank nested inside `bank_transfer`. */
    public function test_bank_transfer_charge_nests_the_bank_and_reads_va_numbers(): void
    {
        config(['payments.bank_transfer_bank' => 'bni']);

        $order = $this->makePendingOrder(PaymentMethod::BankTransfer);
        $order->payment->forceFill(['provider_reference' => null])->save();

        app(PaymentTransactionService::class)->initiate($order->fresh(), PaymentMethod::BankTransfer);

        $payload = $this->midtrans->chargePayloadFor($order->number);
        $this->assertSame('bank_transfer', $payload['payment_type']);
        $this->assertSame(['bank' => 'bni'], $payload['bank_transfer']);
        $this->assertArrayNotHasKey('bank', $payload);

        $payment = $order->fresh()->payment;
        $this->assertSame('91019021579', $payment->virtual_account_number);
        $this->assertSame('bni', $payment->virtual_account_bank);
    }

    public function test_unsupported_acquiring_bank_is_refused_before_calling_the_provider(): void
    {
        config(['payments.bank_transfer_bank' => 'mandiri']);

        $order = $this->makePendingOrder(PaymentMethod::BankTransfer);
        $order->payment->forceFill(['provider_reference' => null])->save();

        $this->expectException(GatewayException::class);
        app(PaymentTransactionService::class)->initiate($order->fresh(), PaymentMethod::BankTransfer);
    }

    public function test_probe_reports_an_authentication_problem_without_creating_a_transaction(): void
    {
        $probe = app(MidtransGateway::class)->probe();

        // The stub answers 404 for the unknown probe order, which proves the
        // server key authenticated.
        $this->assertTrue($probe['reachable']);
        $this->assertTrue($probe['authenticated']);
        $this->assertSame(404, $probe['status']);
    }

    /**
     * Post a correctly signed provider notification.
     */
    private function notify(string $reference, ?string $statusCode = null): TestResponse
    {
        return $this->postJson(
            route('payments.webhook.midtrans'),
            $this->midtrans->notification($reference, $statusCode),
        );
    }

    /** Create an order with a gateway transaction that is still pending. */
    private function makePendingOrder(PaymentMethod $method = PaymentMethod::BankTransfer): Order
    {
        $order = $this->makeOrder($this->makeCustomer());
        $payment = $this->makePayment($order, [
            'status' => PaymentStatus::Pending,
            'method' => $method->value,
            'amount' => $order->grand_total,
            'expires_at' => now()->addDay(),
        ]);
        $payment->forceFill([
            'provider' => 'midtrans',
            'provider_reference' => $order->number,
            'provider_status' => 'pending',
            'qr_image' => 'https://api.sandbox.midtrans.com/qr/'.$order->number.'.png',
        ])->save();

        $this->midtrans->grossAmount = number_format((float) $order->grand_total, 2, '.', '');

        return $order->fresh(['payment', 'customer']);
    }
}
