<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\PaymentTransactionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * Pins the wire format of the Midtrans driver.
 *
 * The Midtrans endpoints are stubbed, so these tests prove the application
 * sends the documented request to the documented host. They do not prove a
 * sandbox or live payment succeeded.
 */
class MidtransGatewayRequestTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install();
    }

    public function test_sandbox_mode_targets_the_sandbox_host(): void
    {
        config(['payments.sandbox' => true]);

        $this->assertSame('https://api.sandbox.midtrans.com', app(MidtransGateway::class)->baseUrl());
    }

    public function test_production_mode_targets_the_production_host(): void
    {
        config(['payments.sandbox' => false]);

        $this->assertSame('https://api.midtrans.com', app(MidtransGateway::class)->baseUrl());
    }

    public function test_charge_is_sent_to_the_sandbox_endpoint_with_basic_auth(): void
    {
        $order = $this->makePendingOrder(PaymentMethod::Qris);

        app(PaymentTransactionService::class)->initiate($order->fresh(), PaymentMethod::Qris);

        Http::assertSent(function (Request $request) {
            if (! str_ends_with((string) $request->url(), '/v2/charge')) {
                return false;
            }

            $this->assertSame('api.sandbox.midtrans.com', $request->toPsrRequest()->getUri()->getHost());
            $this->assertSame('POST', $request->method());

            // Midtrans authenticates with the server key as the basic auth user.
            $expected = 'Basic '.base64_encode(FakeMidtransApi::SERVER_KEY.':');
            $this->assertSame($expected, $request->header('Authorization')[0]);

            // The key must never appear in the JSON body or any other header.
            $this->assertStringNotContainsString(
                FakeMidtransApi::SERVER_KEY,
                $request->body(),
                'The server key must never be sent in the request body.',
            );

            return true;
        });
    }

    public function test_bank_transfer_charge_nests_the_bank_code(): void
    {
        config(['payments.bank_transfer_bank' => 'bri']);

        $order = $this->makePendingOrder(PaymentMethod::BankTransfer);

        app(PaymentTransactionService::class)->initiate($order->fresh(), PaymentMethod::BankTransfer);

        Http::assertSent(function (Request $request) {
            if (! str_ends_with((string) $request->url(), '/v2/charge')) {
                return false;
            }

            $payload = $request->data();

            $this->assertSame('bank_transfer', $payload['payment_type']);
            // The bank must be nested; a top level `bank` is ignored by Midtrans.
            $this->assertSame(['bank' => 'bri'], $payload['bank_transfer']);
            $this->assertArrayNotHasKey('bank', $payload);

            return true;
        });
    }

    public function test_charge_amount_is_an_integer_in_idr(): void
    {
        $order = $this->makePendingOrder(PaymentMethod::Qris);

        app(PaymentTransactionService::class)->initiate($order->fresh(), PaymentMethod::Qris);

        Http::assertSent(function (Request $request) use ($order) {
            if (! str_ends_with((string) $request->url(), '/v2/charge')) {
                return false;
            }

            $this->assertSame(
                (int) round((float) $order->grand_total),
                $request->data()['transaction_details']['gross_amount'],
            );

            return true;
        });
    }

    /**
     * Verified against the Midtrans Sandbox: sending `custom_expiry.duration`
     * fails with 400 "custom_expiry.expiry_duration is required".
     */
    public function test_charge_sends_the_documented_custom_expiry_field(): void
    {
        config(['payments.expiry_hours' => 24]);

        $order = $this->makePendingOrder(PaymentMethod::Qris);

        app(PaymentTransactionService::class)->initiate($order->fresh(), PaymentMethod::Qris);

        Http::assertSent(function (Request $request) {
            if (! str_ends_with((string) $request->url(), '/v2/charge')) {
                return false;
            }

            $expiry = $request->data()['custom_expiry'];

            $this->assertSame(['unit' => 'hour', 'expiry_duration' => 24], $expiry);
            $this->assertArrayNotHasKey('duration', $expiry);

            return true;
        });
    }

    public function test_the_webhook_endpoint_is_reachable_without_a_session(): void
    {
        $this->postJson(route('payments.webhook.midtrans'), ['order_id' => 'x'])
            ->assertForbidden();
    }

    public function test_supported_banks_match_the_midtrans_core_api_list(): void
    {
        $this->assertSame(['bca', 'bni', 'bri', 'permata', 'cimb'], MidtransGateway::SUPPORTED_BANKS);
    }

    private function makePendingOrder(PaymentMethod $method): Order
    {
        $order = $this->makeOrder($this->makeCustomer());
        $this->makePayment($order, [
            'status' => PaymentStatus::Pending,
            'method' => $method->value,
            'amount' => $order->grand_total,
        ])->forceFill([
            'provider' => 'midtrans',
            'provider_reference' => null,
            'expires_at' => now()->addDay(),
        ])->save();

        return $order->fresh();
    }
}
