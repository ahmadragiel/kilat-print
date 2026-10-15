<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\PaymentSettlementService;
use App\Services\Payment\PaymentTransactionService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * Regressions for defects found while wiring the Midtrans gateway.
 *
 * Each test fails against the code as it was before the fix.
 */
class PaymentGatewayRegressionTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();
        // Notifications are deliberately NOT faked: one regression below depends on
        // a real database write failing during settlement.
        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install();
    }

    /**
     * A payment whose window elapsed is no longer payable. The page used to keep
     * rendering because the guard only looked at the status, so the customer saw a
     * QR code or virtual account that could never settle.
     */
    public function test_payment_page_is_closed_once_the_pay_window_has_elapsed(): void
    {
        $order = $this->makePendingOrder();
        $order->payment->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($order->customer->user)
            ->get(route('customer.orders.payment', $order->fresh()))
            ->assertRedirect(route('customer.orders.show', $order));
    }

    public function test_payment_page_is_open_while_the_window_is_running(): void
    {
        $order = $this->makePendingOrder();

        $this->actingAs($order->customer->user)
            ->get(route('customer.orders.payment', $order))
            ->assertOk();
    }

    /**
     * `isSettled()` used to return true for failed/expired transactions, which is
     * the exact opposite of what the name promises.
     */
    public function test_final_states_are_not_reported_as_settled(): void
    {
        foreach ([PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Cancelled] as $status) {
            $payment = $this->makePayment($this->makeOrder($this->makeCustomer()), ['status' => $status]);

            $this->assertTrue($payment->isFinal(), "{$status->value} must be final.");
            $this->assertFalse($payment->isPaid());
            $this->assertFalse($payment->isAwaitingPayment());
        }

        $pending = $this->makePayment($this->makeOrder($this->makeCustomer()));
        $this->assertFalse($pending->isFinal());
        $this->assertTrue($pending->isAwaitingPayment());
    }

    /** A closed transaction must not be reset to PENDING by a retry. */
    public function test_a_final_transaction_cannot_be_reopened(): void
    {
        $order = $this->makePendingOrder();
        $order->payment->forceFill([
            'status' => PaymentStatus::Expired,
            'failure_reason' => 'Waktu pembayaran habis.',
        ])->save();

        $this->expectException(HttpException::class);

        try {
            app(PaymentTransactionService::class)->initiate($order->fresh(), PaymentMethod::Qris);
        } finally {
            $payment = $order->fresh()->payment;
            $this->assertSame(PaymentStatus::Expired, $payment->status);
            $this->assertSame('Waktu pembayaran habis.', $payment->failure_reason);
        }
    }

    /** The stored instructions must not be handed back once they expired. */
    public function test_an_expired_reference_is_not_reused(): void
    {
        $order = $this->makePendingOrder();
        $order->payment->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->expectException(HttpException::class);

        try {
            app(PaymentTransactionService::class)->initiate($order->fresh(), PaymentMethod::Qris);
        } finally {
            // No second charge may have been sent to the provider.
            $this->assertCount(0, $this->midtrans->charges);
        }
    }

    /** The QR image must only ever load from Midtrans over HTTPS. */
    public function test_qr_image_is_pinned_to_the_provider_host(): void
    {
        $order = $this->makePendingOrder();

        $accepted = [
            'https://api.midtrans.com/v2/qris/abc/qr-code',
            'https://api.sandbox.midtrans.com/v2/qris/abc/qr-code',
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==',
        ];

        $rejected = [
            'https://evil.example.com/qr.png',
            'http://api.midtrans.com/v2/qris/abc/qr-code',   // not https
            'https://midtrans.com.evil.example/qr.png',      // lookalike host
            'javascript:alert(1)',
            '',
        ];

        foreach ($accepted as $image) {
            $order->payment->forceFill(['qr_image' => $image])->save();
            $this->assertSame($image, $order->fresh()->payment->safeQrImage(), "{$image} should be allowed");
        }

        foreach ($rejected as $image) {
            $order->payment->forceFill(['qr_image' => $image])->save();
            $this->assertNull($order->fresh()->payment->safeQrImage(), "{$image} should be rejected");
        }
    }

    /**
     * A notification failure must not undo a confirmed payment. The database
     * channel used to run inside the settlement transaction.
     */
    public function test_settlement_survives_a_failing_notification(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        // Force the notifications table write to fail.
        DB::statement('DROP TABLE notifications');

        $result = app(PaymentSettlementService::class)->apply(
            $order->payment,
            app(MidtransGateway::class)->toGatewayStatus(
                $this->midtrans->notification($order->number)
            ),
        );

        $this->assertTrue($result['settled']);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment->status);
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
    }

    /**
     * An empty cart must send the customer back to the cart page. The checkout
     * index declared a `View` return type while returning a redirect, so the
     * empty-cart branch produced a 500 instead.
     */
    public function test_checkout_redirects_when_the_cart_is_empty(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)
            ->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('warning');
    }

    private function makePendingOrder(PaymentMethod $method = PaymentMethod::Qris): Order
    {
        $order = $this->makeOrder($this->makeCustomer());
        $this->makePayment($order, [
            'status' => PaymentStatus::Pending,
            'method' => $method->value,
            'amount' => $order->grand_total,
            'expires_at' => now()->addDay(),
        ])->forceFill([
            'provider' => 'midtrans',
            'provider_reference' => $order->number,
            'provider_status' => 'pending',
        ])->save();

        $this->midtrans->grossAmount = number_format((float) $order->grand_total, 2, '.', '');

        return $order->fresh(['payment', 'customer']);
    }
}
