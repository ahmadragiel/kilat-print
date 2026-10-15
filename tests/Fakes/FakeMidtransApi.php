<?php

namespace Tests\Fakes;

use App\Services\Payment\Drivers\MidtransGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Fakes the Midtrans HTTP endpoints so the real {@see MidtransGateway}
 * driver can be exercised end to end.
 *
 * Responses mirror the documented Midtrans Core API shapes: `va_numbers` for bank
 * transfer, a `generate-qr-code` action for QRIS, and `signature_key` inside the
 * notification body.
 *
 * This is an HTTP stub, not a Midtrans sandbox account. It proves the application
 * builds the documented requests and reacts correctly to the documented responses;
 * it does not prove a live or sandbox payment succeeded.
 */
class FakeMidtransApi
{
    public const SERVER_KEY = 'test-server-key-midtrans';

    public const CHARGE_URL = 'https://api.sandbox.midtrans.com/v2/charge';

    public const PRODUCTION_CHARGE_URL = 'https://api.midtrans.com/v2/charge';

    /** Raw transaction status reported by the stubbed provider. */
    public string $transactionStatus = 'pending';

    public string $statusCode = '200';

    public string $grossAmount = '0';

    public string $currency = 'IDR';

    public ?string $settlementTime = null;

    public ?string $fraudStatus = 'accept';

    public bool $failCharge = false;

    /** Every charge request the driver sent, for assertions on the payload. */
    public array $charges = [];

    /** References the stub was asked to expire or cancel. */
    public array $expired = [];

    public function install(bool $sandbox = true): void
    {
        config([
            'payments.driver' => 'midtrans',
            'payments.server_key' => self::SERVER_KEY,
            'payments.sandbox' => $sandbox,
            'payments.currency' => 'IDR',
        ]);

        Http::fake(function (Request $request) {
            $url = (string) $request->url();

            if (str_ends_with($url, '/v2/charge')) {
                return $this->chargeResponse($request);
            }

            // Midtrans exposes POST /v2/{order_id}/expire and /cancel. Both move the
            // transaction on the provider side, which is what the sandbox helper uses
            // to produce a genuine failure without faking a local status write.
            if (preg_match('#/v2/([^/]+)/(expire|cancel)$#', $url, $matches)) {
                $this->expired[] = $matches[1];

                if ($matches[2] === 'expire') {
                    $this->transactionStatus = 'expire';
                    $this->statusCode = '202';
                } else {
                    $this->transactionStatus = 'cancel';
                    $this->statusCode = '202';
                }

                return Http::response([
                    'transaction_status' => $this->transactionStatus,
                    'status_code' => $this->statusCode,
                ], 200);
            }

            if (str_contains($url, '/status')) {
                // Midtrans answers an unknown order id with 404 once the
                // credentials have authenticated.
                if (str_contains($url, 'probe')) {
                    return Http::response([
                        'error_messages' => ['Transaction not found.'],
                        'status_code' => '404',
                    ], 404);
                }

                return Http::response($this->statusBody(), 200);
            }

            return Http::response([], 404);
        });
    }

    public function settle(): void
    {
        $this->transactionStatus = 'settlement';
        $this->statusCode = '200';
        $this->settlementTime = now()->toIso8601String();
    }

    public function expire(): void
    {
        $this->transactionStatus = 'expire';
        $this->statusCode = '202';
    }

    public function deny(): void
    {
        $this->transactionStatus = 'deny';
        $this->statusCode = '201';
    }

    /** Report a successful transaction, but for a different amount. */
    public function settleWithAmount(string $amount): void
    {
        $this->grossAmount = $amount;
        $this->settle();
    }

    /**
     * A Midtrans transaction notification body, including the `signature_key`
     * that the real provider places in the body.
     *
     * @return array<string, mixed>
     */
    public function notification(string $reference, ?string $statusCode = null): array
    {
        $payload = [
            'transaction_time' => now()->subMinute()->format('Y-m-d H:i:s'),
            'transaction_status' => $this->transactionStatus,
            'transaction_id' => 'mid-'.$reference,
            'status_message' => 'midtrans payment notification',
            'status_code' => $statusCode ?? $this->statusCode,
            'order_id' => $reference,
            'gross_amount' => $this->grossAmount,
            'currency' => $this->currency,
            'payment_type' => 'qris',
            'fraud_status' => $this->fraudStatus,
            'settlement_time' => $this->settlementTime,
        ];

        // Drop nulls the way a real payload would omit them.
        $payload = array_filter($payload, fn ($value) => $value !== null);
        $payload['signature_key'] = $this->signature($payload);

        return $payload;
    }

    /**
     * `signature_key` = SHA512(order_id + status_code + gross_amount + server_key).
     *
     * @param  array<string, mixed>  $payload
     */
    public function signature(array $payload, ?string $serverKey = null): string
    {
        return hash(
            'sha512',
            ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').($serverKey ?? self::SERVER_KEY),
        );
    }

    public function chargePayloadFor(string $reference): ?array
    {
        foreach ($this->charges as $charge) {
            if ($charge['order_id'] === (string) $reference) {
                return $charge['payload'];
            }
        }

        return null;
    }

    private function chargeResponse(Request $request)
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->data();
        $orderId = (string) ($payload['transaction_details']['order_id'] ?? '');
        $this->charges[] = ['order_id' => $orderId, 'payload' => $payload];
        $this->grossAmount = number_format((float) ($payload['transaction_details']['gross_amount'] ?? 0), 2, '.', '');

        if ($this->failCharge) {
            return Http::response(['error_messages' => ['API key is invalid']], 401);
        }

        $body = [
            'status_code' => '201',
            'status_message' => 'Transaction is created',
            'transaction_id' => 'mid-'.$orderId,
            'order_id' => $orderId,
            'merchant_id' => 'M000000',
            'gross_amount' => $this->grossAmount,
            'currency' => $this->currency,
            'payment_type' => $payload['payment_type'] ?? '',
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'transaction_status' => 'pending',
            'fraud_status' => 'accept',
            'expiry_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ];

        if (($payload['payment_type'] ?? '') === 'qris') {
            $body['acquirer'] = 'gopay';
            $body['actions'] = [[
                'name' => 'generate-qr-code',
                'method' => 'GET',
                'url' => 'https://api.sandbox.midtrans.com/v2/qris/mid-'.$orderId.'/qr-code',
            ]];
        } else {
            // Midtrans returns the virtual account inside a `va_numbers` array.
            $body['va_numbers'] = [[
                'bank' => (string) ($payload['bank_transfer']['bank'] ?? 'bca'),
                'va_number' => '91019021579',
            ]];
        }

        return Http::response($body, 201);
    }

    /** @return array<string, mixed> */
    private function statusBody(): array
    {
        return array_filter([
            'order_id' => 'ref',
            'transaction_id' => 'mid-ref',
            'transaction_status' => $this->transactionStatus,
            'status_code' => $this->statusCode,
            'fraud_status' => $this->fraudStatus,
            'gross_amount' => $this->grossAmount,
            'currency' => $this->currency,
            'payment_type' => 'qris',
            'settlement_time' => $this->settlementTime,
        ], fn ($value) => $value !== null);
    }
}
