<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\Drivers\MidtransGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Answers "what is the real payment state right now?" for the payment page.
 *
 * The page polls this endpoint after the customer scans the QR, so a successful or
 * failed payment shows up without them reloading.
 *
 * Two important properties:
 *
 * 1. The answer is never derived from the request. A caller can only ask; the
 *    state comes from our own row, which is only ever written from a verified
 *    provider confirmation.
 * 2. The provider is queried at most once every {@see self::PROVIDER_INTERVAL}
 *    seconds per transaction, so a polling page cannot hammer the gateway or push
 *    a merchant over a provider rate limit.
 */
class PaymentStatusPoller
{
    /** Minimum gap between two provider reads for the same transaction. */
    private const PROVIDER_INTERVAL = 8;

    private const CACHE_PREFIX = 'payment-status:';

    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly PaymentSettlementService $settlement,
    ) {}

    /**
     * @return array{status: string, label: string, paid: bool, failed: bool, pending: bool, message: string|null, checked_provider: bool}
     */
    public function current(Payment $payment): array
    {
        $checkedProvider = $this->refreshFromProvider($payment);

        $status = $payment->fresh()->status ?? PaymentStatus::Pending;

        return [
            'status' => $status->value,
            'label' => $status->label(),
            'paid' => $status === PaymentStatus::Paid,
            'failed' => $status->isFinal(),
            'pending' => ! $status->isFinal() && $status !== PaymentStatus::Paid,
            'message' => $payment->fresh()->failure_reason,
            'checked_provider' => $checkedProvider,
        ];
    }

    /**
     * Read the provider at most once per interval.
     *
     * @return bool Whether a provider call actually happened.
     */
    private function refreshFromProvider(Payment $payment): bool
    {
        if (! filled($payment->provider_reference)) {
            return false;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            return false;
        }

        $key = self::CACHE_PREFIX.$payment->id;

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, self::PROVIDER_INTERVAL);

        try {
            $status = $this->gateways->gateway()->fetchStatus($payment->provider_reference);
            $this->settlement->apply($payment, $status);
        } catch (\Throwable $exception) {
            // A polling failure must never break the page. The state stays PENDING
            // and the customer can retry; the webhook remains the source of truth.
            Log::warning('Payment status poll could not reach the provider.', [
                'payment_id' => $payment->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return true;
    }

    /** The simulator Midtrans provides for paying a sandbox QRIS by hand. */
    public static function simulatorUrl(): string
    {
        return 'https://simulator.sandbox.midtrans.com/v2/qris/index';
    }

    /** True when the gateway points at Midtrans' test environment. */
    public function isSandbox(): bool
    {
        $gateway = $this->gateways->gateway();

        return $gateway instanceof MidtransGateway && $gateway->baseUrl() !== 'https://api.midtrans.com';
    }
}
