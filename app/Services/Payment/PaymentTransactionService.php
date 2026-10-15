<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates the payable transaction for an order.
 *
 * Checkout only ever produces a PENDING payment. Turning that into PAID is the
 * exclusive job of {@see PaymentSettlementService}, which needs a verified
 * provider confirmation.
 */
class PaymentTransactionService
{
    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly PaymentSettlementService $settlement,
    ) {}

    /**
     * Create (or reuse) the pending transaction for an order and register it with
     * the payment provider.
     *
     * @return array{payment: Payment, transaction: GatewayTransaction}
     */
    public function initiate(Order $order, PaymentMethod $method): array
    {
        $payment = $this->ensurePendingPayment($order, $method);
        $gateway = $this->gateways->gateway();

        // Re-use the existing reference so a customer revisiting the payment page
        // never ends up with several live transactions for one order. A window
        // that already elapsed is closed instead: handing back its QR or virtual
        // account would ask the customer to pay for a transaction the provider
        // will refuse.
        if (filled($payment->provider_reference)) {
            abort_if($payment->expires_at?->isPast() === true, 422, 'Waktu pembayaran untuk pesanan ini sudah habis. Silakan buat pesanan baru.');

            $transaction = new GatewayTransaction(
                reference: $payment->provider_reference,
                method: PaymentMethod::from($payment->method),
                statusText: (string) ($payment->provider_status ?? 'pending'),
                qrImage: $payment->qr_image,
                qrPayload: $payment->qr_payload,
                virtualAccountNumber: $payment->virtual_account_number,
                virtualAccountBank: $payment->virtual_account_bank,
                expiresAt: $payment->expires_at?->toIso8601String(),
            );

            return ['payment' => $payment, 'transaction' => $transaction];
        }

        $transaction = $gateway->createTransaction($order, $method);

        $payment->forceFill([
            'provider' => $gateway->name(),
            'provider_reference' => $transaction->reference,
            'provider_status' => $transaction->statusText,
            'channel' => $transaction->method->value,
            'qr_image' => $transaction->qrImage,
            'qr_payload' => $transaction->qrPayload,
            'virtual_account_number' => $transaction->virtualAccountNumber,
            'virtual_account_bank' => $transaction->virtualAccountBank,
            'expires_at' => $this->expiresAt($transaction->expiresAt),
        ])->save();

        return ['payment' => $payment->fresh(), 'transaction' => $transaction];
    }

    /**
     * Ask the provider for the current state of a transaction and apply it.
     *
     * The customer triggers this from the payment page, but the answer always
     * comes from the provider, never from the request itself.
     *
     * @return array{payment: Payment, settled: bool, ignored_reason: string|null}
     */
    public function refresh(Payment $payment): array
    {
        abort_if(blank($payment->provider_reference), 422, 'Transaksi pembayaran belum dibuat di penyedia pembayaran.');

        $status = $this->gateways->gateway()->fetchStatus($payment->provider_reference);

        return $this->settlement->apply($payment, $status);
    }

    /** The order must still be waiting for payment for a transaction to exist. */
    private function ensurePendingPayment(Order $order, PaymentMethod $method): Payment
    {
        return DB::transaction(function () use ($order, $method) {
            $status = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from((string) $order->status);
            abort_unless($status === OrderStatus::PendingPayment, 422, 'Pesanan ini tidak sedang menunggu pembayaran.');

            $payment = Payment::query()->lockForUpdate()->firstOrNew(['order_id' => $order->id]);

            // A failed, expired or cancelled transaction must never be reopened.
            // The provider side is already closed, so resetting the row would hand
            // the customer payment instructions that can no longer settle.
            abort_if($payment->exists && $payment->isFinal(), 422, 'Transaksi pembayaran ini sudah berakhir. Silakan buat pesanan baru.');

            $payment->fill([
                'status' => PaymentStatus::Pending->value,
                'method' => $method->value,
                'amount' => $order->grand_total,
                'expires_at' => $payment->expires_at ?? $this->defaultExpiry(),
                'failure_reason' => null,
            ])->save();

            return $payment->fresh();
        }, 3);
    }

    private function expiresAt(?string $providerExpiry): Carbon
    {
        if (filled($providerExpiry)) {
            try {
                return Carbon::parse($providerExpiry);
            } catch (\Throwable) {
                // Fall through to the locally configured pay window.
            }
        }

        return $this->defaultExpiry();
    }

    private function defaultExpiry(): Carbon
    {
        return now()->addHours(max(1, (int) config('payments.expiry_hours', 24)));
    }
}
