<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;

/**
 * The provider's answer to a transaction creation request.
 *
 * It only describes how the customer can pay. It never states that the payment
 * succeeded: settlement always arrives later through a verified confirmation.
 */
final readonly class GatewayTransaction
{
    /**
     * @param  string  $reference  Provider transaction id, echoed back on every notification.
     * @param  PaymentMethod  $method  Channel the transaction was created for.
     * @param  string  $statusText  Raw provider status, kept for auditing.
     * @param  string|null  $qrImage  QRIS image (https URL or data URI) exactly as sent by the provider.
     * @param  string|null  $qrPayload  QRIS payload string, used when no image is returned.
     * @param  string|null  $virtualAccountNumber  Virtual account the customer transfers to.
     * @param  string|null  $virtualAccountBank  Bank owning the virtual account.
     * @param  string|null  $expiresAt  Provider expiry timestamp, when reported.
     */
    public function __construct(
        public string $reference,
        public PaymentMethod $method,
        public string $statusText,
        public ?string $qrImage = null,
        public ?string $qrPayload = null,
        public ?string $virtualAccountNumber = null,
        public ?string $virtualAccountBank = null,
        public ?string $expiresAt = null,
    ) {}

    public function virtualAccount(): ?string
    {
        return $this->virtualAccountNumber;
    }
}
