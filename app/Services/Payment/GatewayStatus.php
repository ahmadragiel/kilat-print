<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

/**
 * A normalised view of one provider status answer.
 *
 * The amount and currency are always reported by the provider. The settlement
 * service compares them against the stored order total instead of trusting them,
 * so a tampered or mismatched payload cannot mark a payment as paid.
 */
final readonly class GatewayStatus
{
    /**
     * @param  string  $reference  Provider transaction id.
     * @param  string  $providerStatus  Raw provider status string.
     * @param  string  $grossAmount  Total reported by the provider.
     * @param  string  $currency  Currency reported by the provider.
     * @param  string|null  $statusCode  Provider status code; Midtrans uses `200` for a successful transaction.
     * @param  string|null  $paidAt  Provider settlement time, when the transaction settled.
     * @param  string|null  $paymentType  Provider payment type, e.g. `qris` or `bank_transfer`.
     * @param  string|null  $fraudStatus  Provider fraud verdict, when the provider runs one.
     */
    public function __construct(
        public string $reference,
        public string $providerStatus,
        public PaymentStatus $status,
        public string $grossAmount,
        public string $currency,
        public ?string $statusCode = null,
        public ?string $paidAt = null,
        public ?string $paymentType = null,
        public ?string $fraudStatus = null,
    ) {}

    public function grossAmountMatches(string $expected): bool
    {
        return $this->normaliseAmount($this->grossAmount) === $this->normaliseAmount($expected);
    }

    /** Compare the provider currency with the currency the application expects. */
    public function currencyMatches(string $expected): bool
    {
        return strtoupper($this->currency) === strtoupper($expected);
    }

    /**
     * A provider may only confirm a transaction when the fraud verdict is absent or accepted.
     */
    public function passesFraudCheck(): bool
    {
        return in_array(strtolower((string) $this->fraudStatus), ['', 'accept'], true);
    }

    /**
     * Midtrans documents `status_code` 200 as the code for a successful transaction.
     *
     * A transaction created successfully but not yet paid reports `201`, and an
     * expired one reports `202`, so neither may be treated as paid.
     */
    public function isSuccessfulResponse(): bool
    {
        return $this->statusCode === null || $this->statusCode === '200';
    }

    public function method(): ?PaymentMethod
    {
        if ($this->paymentType === null) {
            return null;
        }

        return PaymentMethod::tryFrom(strtoupper($this->paymentType));
    }

    private function normaliseAmount(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
