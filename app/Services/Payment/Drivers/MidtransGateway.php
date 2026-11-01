<?php

namespace App\Services\Payment\Drivers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payment\GatewayException;
use App\Services\Payment\GatewayStatus;
use App\Services\Payment\GatewayTransaction;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Midtrans Core API driver.
 *
 * Midtrans is the provider integrated for Indonesian merchants: QRIS is scanned
 * from any e-wallet or mobile banking app, and bank transfer issues a virtual
 * account whose credit Midtrans confirms automatically. Because both channels
 * are settled by Midtrans, the application never asks for a proof upload and
 * never asks an admin to verify a payment.
 *
 * Documentation: https://docs.midtrans.com/
 *   - Charge .................. POST {base}/v2/charge
 *   - Get status .............. GET  {base}/v2/{order_id}/status
 *   - Signature ............... signature_key = SHA512(order_id + status_code + gross_amount + server_key)
 *   - Sandbox base ............ https://api.sandbox.midtrans.com
 *   - Production base ......... https://api.midtrans.com
 *
 * The server key is read from configuration and only ever travels in the
 * `Authorization` header of server-to-server requests. It is never rendered into
 * Blade output, JavaScript, or any browser response.
 */
class MidtransGateway implements PaymentGateway
{
    private const PRODUCTION_BASE = 'https://api.midtrans.com';

    private const SANDBOX_BASE = 'https://api.sandbox.midtrans.com';

    /** Acquiring banks Midtrans accepts for a Core API `bank_transfer` charge. */
    public const SUPPORTED_BANKS = ['bca', 'bni', 'bri', 'permata', 'cimb'];

    public function __construct(private readonly PaymentSettings $settings) {}

    public function name(): string
    {
        return 'midtrans';
    }

    public function isConfigured(): bool
    {
        return filled($this->serverKey());
    }

    public function merchantId(): ?string
    {
        return $this->settings->merchantId();
    }

    public function clientKey(): ?string
    {
        return $this->settings->clientKey();
    }

    /** The base URL every request of this environment is sent to. */
    public function baseUrl(): string
    {
        return $this->settings->sandbox() ? self::SANDBOX_BASE : self::PRODUCTION_BASE;
    }

    public function createTransaction(Order $order, PaymentMethod $method): GatewayTransaction
    {
        $payload = [
            'payment_type' => $method->providerPaymentType(),
            'transaction_details' => [
                'order_id' => $order->number,
                'gross_amount' => (int) round((float) $order->grand_total),
            ],
            'customer_details' => [
                'first_name' => $order->customer_name ?: 'Kilat Print Customer',
                'email' => $order->customer_email ?: 'customer@kilatprint.test',
                'phone' => $order->customer_phone ?: '0800000000',
            ],
            // Midtrans names the duration field `expiry_duration`; sending `duration`
            // is rejected with "custom_expiry.expiry_duration is required".
            'custom_expiry' => [
                'unit' => 'hour',
                'expiry_duration' => max(1, (int) config('payments.expiry_hours', 24)),
            ],
        ];

        // Midtrans requires the acquiring bank inside a `bank_transfer` object;
        // a top level `bank` field is ignored by the charge endpoint.
        if ($method === PaymentMethod::BankTransfer) {
            $payload['bank_transfer'] = ['bank' => $this->acquiringBank()];
        }

        $body = $this->send(
            fn (): Response => $this->request()->post($this->baseUrl().'/v2/charge', $payload),
            'create a transaction',
        ) ?? [];

        $reference = (string) ($body['order_id'] ?? $body['token'] ?? '');
        if ($reference === '') {
            throw GatewayException::rejected($this->name(), 'the provider did not return a transaction reference.');
        }

        $virtualAccount = $this->firstVirtualAccount($body);

        return new GatewayTransaction(
            reference: $reference,
            method: $method,
            statusText: (string) ($body['transaction_status'] ?? 'pending'),
            // QRIS returns the PNG endpoint in `actions`; it has no inline payload.
            qrImage: $this->extractQrImage($body['actions'] ?? []),
            qrPayload: null,
            virtualAccountNumber: $virtualAccount['number'],
            virtualAccountBank: $virtualAccount['bank'],
            expiresAt: $this->extractExpiry($body['expiry_time'] ?? null),
        );
    }

    public function fetchStatus(string $reference): GatewayStatus
    {
        $body = $this->send(
            fn (): Response => $this->request()->get($this->baseUrl().'/v2/'.urlencode($reference).'/status'),
            "read transaction {$reference}",
        ) ?? [];

        if (isset($body['error_messages'][0]) && ! isset($body['transaction_status'])) {
            throw GatewayException::unknownTransaction($this->name(), $reference);
        }

        return $this->toGatewayStatus($body);
    }

    /**
     * Confirm the provider can be reached and the server key is accepted.
     *
     * Midtrans answers an unknown order id with 404 when the credentials are
     * valid and with 401 when they are not, so this checks authentication
     * without creating a transaction.
     *
     * @return array{reachable: bool, authenticated: bool, status: int, message: string}
     */
    public function probe(): array
    {
        $reference = 'kilat-print-probe-'.substr(hash('sha256', (string) getmypid().microtime(true)), 0, 16);

        try {
            $response = $this->request()->get($this->baseUrl().'/v2/'.$reference.'/status');
        } catch (GatewayException $exception) {
            return ['reachable' => false, 'authenticated' => false, 'status' => 0, 'message' => $exception->getMessage()];
        }

        $status = $response->status();

        if ($status === 401 || $status === 403) {
            return [
                'reachable' => true,
                'authenticated' => false,
                'status' => $status,
                'message' => 'Server key ditolak oleh penyedia pembayaran.',
            ];
        }

        // 404 means the credentials authenticated but the probe order does not exist.
        return [
            'reachable' => true,
            'authenticated' => true,
            'status' => $status,
            'message' => $status === 404
                ? 'Kredensial valid; order probe memang tidak ada (diharapkan).'
                : 'Penyedia menjawab HTTP '.$status.'.',
        ];
    }

    /**
     * Run a provider call and turn transport or HTTP errors into a GatewayException.
     *
     * Callers only handle one exception type, and a failed provider call can
     * never be mistaken for a successful payment.
     *
     * @param  callable(): Response  $call
     * @return array<string, mixed>|null
     */
    private function send(callable $call, string $action): ?array
    {
        try {
            $response = $call();
        } catch (ConnectionException $exception) {
            throw GatewayException::unreachable($this->name(), $action.' failed: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw GatewayException::rejected($this->name(), 'could not '.$action.': '.$this->errorMessage($response));
        }

        return $response->json();
    }

    /**
     * Build the gateway status view from a provider payload.
     *
     * The notification body and the status response share the transaction
     * fields, so the same normalisation applies to both sources.
     *
     * @param  array<string, mixed>  $body
     */
    public function toGatewayStatus(array $body): GatewayStatus
    {
        $transactionStatus = strtolower((string) ($body['transaction_status'] ?? ''));

        return new GatewayStatus(
            reference: (string) ($body['order_id'] ?? ''),
            providerStatus: $transactionStatus,
            status: $this->mapStatus($transactionStatus),
            grossAmount: (string) ($body['gross_amount'] ?? '0'),
            currency: (string) ($body['currency'] ?? 'IDR'),
            statusCode: isset($body['status_code']) ? (string) $body['status_code'] : null,
            paidAt: isset($body['settlement_time']) ? (string) $body['settlement_time'] : null,
            paymentType: isset($body['payment_type']) ? (string) $body['payment_type'] : null,
            fraudStatus: isset($body['fraud_status']) ? (string) $body['fraud_status'] : null,
        );
    }

    /**
     * Verify the authenticity of a Midtrans notification.
     *
     * Midtrans ships `signature_key` inside the notification body and documents
     * it as `SHA512(order_id + status_code + gross_amount + server_key)`.
     */
    public function verifyNotificationSignature(array $payload): bool
    {
        $secret = $this->serverKey();
        if ($secret === null) {
            return false;
        }

        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');

        if ($orderId === '' || $statusCode === '' || $grossAmount === '') {
            return false;
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$secret);

        $provided = (string) ($payload['signature_key'] ?? '');
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, strtolower(trim($provided)));
    }

    private function mapStatus(string $transactionStatus): PaymentStatus
    {
        return match ($transactionStatus) {
            'capture', 'settlement' => PaymentStatus::Paid,
            'deny' => PaymentStatus::Failed,
            'cancel', 'expire' => PaymentStatus::Expired,
            'refund', 'reversal', 'partial_refund', 'partial_capture' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    private function acquiringBank(): string
    {
        $bank = strtolower((string) config('payments.bank_transfer_bank', 'bca'));

        if (! in_array($bank, self::SUPPORTED_BANKS, true)) {
            throw GatewayException::rejected(
                $this->name(),
                'the configured acquiring bank ['.$bank.'] is not supported by Midtrans Core API. '
                    .'Supported banks: '.implode(', ', self::SUPPORTED_BANKS).'.',
            );
        }

        return $bank;
    }

    private function serverKey(): ?string
    {
        return $this->settings->serverKey();
    }

    private function request(): PendingRequest
    {
        $serverKey = $this->serverKey();

        if ($serverKey === null) {
            throw GatewayException::notConfigured($this->name());
        }

        return Http::asJson()
            ->withBasicAuth($serverKey, '')
            ->timeout(20)
            ->acceptJson();
    }

    /**
     * Midtrans reports the reason as `error_messages`; fall back to the raw body.
     */
    private function errorMessage(Response $response): string
    {
        $messages = $response->json('error_messages');

        if (is_array($messages) && $messages !== []) {
            return implode('; ', array_map('strval', $messages));
        }

        return $response->status().' '.$response->body();
    }

    /**
     * The QR code PNG endpoint, e.g. `generate-qr-code` in `actions`.
     *
     * @param  array<int, array<string, mixed>>  $actions
     */
    private function extractQrImage(array $actions): ?string
    {
        foreach ($actions as $action) {
            if (! str_contains(strtolower((string) ($action['name'] ?? '')), 'qr')) {
                continue;
            }

            foreach (['image', 'url'] as $key) {
                $value = $action[$key] ?? null;
                if (is_string($value) && $this->isSafeImageSource($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Only provider HTTPS URLs and inline PNG images may reach the browser.
     */
    private function isSafeImageSource(string $image): bool
    {
        if (str_starts_with($image, 'https://')) {
            return true;
        }

        return (bool) preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $image);
    }

    /**
     * Read the virtual account from `va_numbers`, e.g.
     * `[{"bank": "bca", "va_number": "91019021579"}]`.
     *
     * @param  array<string, mixed>  $body
     * @return array{number: string|null, bank: string|null}
     */
    private function firstVirtualAccount(array $body): array
    {
        foreach ((array) ($body['va_numbers'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $number = $this->firstString($entry, ['va_number', 'account_number']);

            if ($number !== null) {
                return ['number' => $number, 'bank' => $this->firstString($entry, ['bank'])];
            }
        }

        return ['number' => null, 'bank' => null];
    }

    private function extractExpiry(mixed $expiryTime): ?string
    {
        if (! is_string($expiryTime) || $expiryTime === '') {
            return null;
        }

        try {
            return Carbon::parse($expiryTime)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<string>  $keys
     */
    private function firstString(array $body, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $body[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
