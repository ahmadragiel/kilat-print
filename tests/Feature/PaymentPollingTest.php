<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payment\PaymentStatusPoller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * The payment page polls this endpoint so a scan resolves to a real outcome.
 *
 * The endpoint reports state; it never decides it. A caller can ask as often as
 * it likes, but only a verified provider confirmation can move a payment.
 */
class PaymentPollingTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install();
    }

    public function test_polling_reports_pending_while_the_provider_has_not_confirmed(): void
    {
        $order = $this->makePendingOrder();

        $this->actingAs($order->customer->user)
            ->getJson(route('customer.orders.payment-status', $order))
            ->assertOk()
            ->assertJson([
                'status' => PaymentStatus::Pending->value,
                'pending' => true,
                'paid' => false,
                'failed' => false,
            ]);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
    }

    /** After the scan the provider settles and the poll reflects it. */
    public function test_polling_reports_success_after_the_provider_settles(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        $this->actingAs($order->customer->user)
            ->getJson(route('customer.orders.payment-status', $order))
            ->assertOk()
            ->assertJson([
                'status' => PaymentStatus::Paid->value,
                'paid' => true,
                'label' => 'Pembayaran Berhasil',
            ]);

        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
    }

    public function test_polling_reports_failure_when_the_provider_denies(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->deny();

        $this->actingAs($order->customer->user)
            ->getJson(route('customer.orders.payment-status', $order))
            ->assertOk()
            ->assertJson([
                'status' => PaymentStatus::Failed->value,
                'failed' => true,
                'paid' => false,
            ]);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_polling_reports_expiry_when_the_provider_expires(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->expire();

        $this->actingAs($order->customer->user)
            ->getJson(route('customer.orders.payment-status', $order))
            ->assertOk()
            ->assertJson(['status' => PaymentStatus::Expired->value, 'failed' => true]);
    }

    /**
     * The endpoint takes no status input. Posting one must change nothing.
     */
    public function test_a_polling_caller_cannot_post_a_status(): void
    {
        $order = $this->makePendingOrder();

        $this->actingAs($order->customer->user)
            ->getJson(route('customer.orders.payment-status', $order).'?status=PAID')
            ->assertOk()
            ->assertJson(['paid' => false]);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
    }

    /** Repeated polling must not hammer the provider. */
    public function test_repeated_polling_reads_the_provider_at_most_once_per_interval(): void
    {
        $order = $this->makePendingOrder();
        $this->midtrans->settle();

        // One poll performs the provider read and settles the payment.
        $this->actingAs($order->customer->user)->getJson(route('customer.orders.payment-status', $order))->assertOk();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment->status);

        $this->assertSame(1, Http::recorded()->count());

        // Later polls are answered from our own record, without calling Midtrans.
        $this->actingAs($order->customer->user)->getJson(route('customer.orders.payment-status', $order))->assertOk();
        $this->actingAs($order->customer->user)->getJson(route('customer.orders.payment-status', $order))->assertOk();

        $this->assertSame(1, Http::recorded()->count());
        $this->assertTrue(Cache::has('payment-status:'.$order->payment->id));
    }

    public function test_polling_survives_an_unreachable_provider(): void
    {
        $order = $this->makePendingOrder();

        Http::fake(fn () => throw new ConnectionException('down'));

        $this->actingAs($order->customer->user)
            ->getJson(route('customer.orders.payment-status', $order))
            ->assertOk()
            ->assertJson(['status' => PaymentStatus::Pending->value]);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment->status);
    }

    public function test_customer_cannot_poll_another_customers_transaction(): void
    {
        $order = $this->makePendingOrder();

        $this->actingAs($this->makeCustomer()->user)
            ->getJson(route('customer.orders.payment-status', $order))
            ->assertForbidden();
    }

    public function test_payment_page_polls_and_offers_the_sandbox_simulator(): void
    {
        $order = $this->makePendingOrder();

        $this->actingAs($order->customer->user)
            ->get(route('customer.orders.payment', $order))
            ->assertOk()
            ->assertSee(route('customer.orders.payment-status', $order), false)
            ->assertSee(PaymentStatusPoller::simulatorUrl(), false)
            ->assertSee('Scan QR', false);
    }

    private function makePendingOrder(): Order
    {
        $order = $this->makeOrder($this->makeCustomer());
        $this->makePayment($order, [
            'status' => PaymentStatus::Pending,
            'method' => PaymentMethod::Qris->value,
            'amount' => $order->grand_total,
            'expires_at' => now()->addDay(),
        ])->forceFill([
            'provider' => 'midtrans',
            'provider_reference' => $order->number,
            'provider_status' => 'pending',
            'qr_image' => 'https://api.sandbox.midtrans.com/v2/qris/'.$order->number.'/qr-code',
        ])->save();

        $this->midtrans->grossAmount = number_format((float) $order->grand_total, 2, '.', '');

        return $order->fresh(['payment', 'customer']);
    }
}
