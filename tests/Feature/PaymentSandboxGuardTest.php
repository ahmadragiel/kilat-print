<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\PaymentStatusPoller;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * The sandbox helper must be unreachable on a production merchant.
 *
 * Every assertion here is a guard: if any of them fail, a live Midtrans account
 * could be driven by the admin UI.
 */
class PaymentSandboxGuardTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install(sandbox: true);
    }

    public function test_sandbox_pages_are_refused_when_the_gateway_is_not_sandbox(): void
    {
        $this->midtrans->install(sandbox: false);

        $this->assertSame('https://api.midtrans.com', app(MidtransGateway::class)->baseUrl());

        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.payment-sandbox.index'))->assertForbidden();
    }

    public function test_expire_is_refused_outside_sandbox_and_never_calls_the_provider(): void
    {
        $this->midtrans->install(sandbox: false);

        $payment = $this->makePendingPayment();
        Http::preventStrayRequests();

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.payment-sandbox.expire', $payment))
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertCount(0, Http::recorded());
    }

    public function test_check_is_refused_outside_sandbox(): void
    {
        $this->midtrans->install(sandbox: false);

        $payment = $this->makePendingPayment();

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.payment-sandbox.check', $payment))
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_non_admin_roles_cannot_reach_the_sandbox_pages(): void
    {
        $payment = $this->makePendingPayment();

        $this->actingAs($this->makeCustomer()->user)
            ->get(route('admin.payment-sandbox.index'))
            ->assertForbidden();

        $this->actingAs($this->makeOperator()->user)
            ->post(route('admin.payment-sandbox.expire', $payment))
            ->assertForbidden();
    }

    public function test_the_expire_button_moves_the_state_through_the_provider(): void
    {
        $payment = $this->makePendingPayment();

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.payment-sandbox.expire', $payment))
            ->assertRedirect()
            ->assertSessionHas('success');

        // The provider was actually asked to expire the transaction.
        $this->assertContains($payment->provider_reference, $this->midtrans->expired);

        // And the resulting state went through the normal settlement path.
        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $payment->order->fresh()->status);
    }

    public function test_check_reports_a_successful_settlement(): void
    {
        $this->midtrans->settle();

        $payment = $this->makePendingPayment();

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.payment-sandbox.check', $payment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_index_lists_waiting_transactions(): void
    {
        $payment = $this->makePendingPayment();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.payment-sandbox.index'))
            ->assertOk()
            ->assertSee($payment->provider_reference)
            ->assertSee('Cek ke penyedia')
            ->assertSee('Paksa kedaluwarsa')
            ->assertSee(PaymentStatusPoller::simulatorUrl());
    }

    public function test_the_dashboard_sandbox_toggle_also_guards_the_sandbox_pages(): void
    {
        $this->midtrans->install(sandbox: false);

        // Flip the stored flag back to the test environment through the dashboard.
        $this->actingAs($this->makeAdmin())->put(route('admin.payment-settings.update'), [
            'sandbox' => '1',
        ]);

        $this->assertTrue(app(PaymentStatusPoller::class)->isSandbox());

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.payment-sandbox.index'))
            ->assertOk();
    }

    private function makePendingPayment(): Payment
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
            'qr_image' => 'https://api.sandbox.midtrans.com/v2/qris/'.$order->number.'/qr-code',
        ])->save();

        // The stub provider must quote the real total or the amount check
        // (correctly) refuses to settle the transaction.
        $this->midtrans->grossAmount = number_format((float) $order->grand_total, 2, '.', '');

        return $payment->fresh();
    }
}
