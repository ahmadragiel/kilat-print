<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\OrderNotification;
use App\Services\OrderStatusService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only component allowed to mark a payment as paid.
 *
 * Every call must supply a {@see GatewayStatus} produced from the payment
 * provider, either through a signature-verified webhook or through a
 * server-to-server status request. Nothing a customer submits can reach this
 * service, and no admin action can force a payment to succeed.
 *
 * The write is idempotent: replaying the same notification settles the order
 * once, and a transaction that already failed, expired or was cancelled never
 * flips back to paid.
 */
class PaymentSettlementService
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    /**
     * Apply a provider status to a stored payment.
     *
     * @return array{payment: Payment, settled: bool, ignored_reason: string|null}
     */
    public function apply(Payment $payment, GatewayStatus $status): array
    {
        return DB::transaction(function () use ($payment, $status) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            $current = $locked->status instanceof PaymentStatus ? $locked->status : PaymentStatus::from((string) $locked->status);

            $ignored = $this->ignoreReason($locked, $current, $status);
            if ($ignored !== null) {
                return ['payment' => $locked->fresh(), 'settled' => false, 'ignored_reason' => $ignored];
            }

            $attributes = [
                'provider_status' => $status->providerStatus,
                'status' => $status->status->value,
            ];

            if ($status->status === PaymentStatus::Paid) {
                $attributes['paid_at'] = $this->paidAt($status);
                $attributes['failure_reason'] = null;
            } elseif ($status->status === PaymentStatus::Failed || $status->status === PaymentStatus::Expired) {
                $attributes['failure_reason'] = match ($status->status) {
                    PaymentStatus::Failed => 'Penyedia pembayaran melaporkan transaksi gagal.',
                    default => 'Waktu pembayaran habis sebelum transaksi dikonfirmasi.',
                };
            }

            $locked->forceFill($attributes)->save();

            if ($status->status !== PaymentStatus::Paid) {
                return ['payment' => $locked->fresh(), 'settled' => false, 'ignored_reason' => null];
            }

            $this->markOrderPaid($order, $locked->fresh());

            return ['payment' => $locked->fresh(), 'settled' => true, 'ignored_reason' => null];
        }, 3);
    }

    /**
     * Reason to skip this update, or null when it must be applied.
     *
     * A non-null return means "leave the stored transaction alone".
     */
    private function ignoreReason(Payment $payment, PaymentStatus $current, GatewayStatus $status): ?string
    {
        // Replayed notification for a transaction that is already settled.
        if ($current === PaymentStatus::Paid) {
            return 'already_paid';
        }

        // A failed, expired or cancelled transaction is never revived.
        if ($current->isFinal()) {
            return 'final_state';
        }

        if ($status->status !== PaymentStatus::Paid) {
            return null;
        }

        if (! $status->passesFraudCheck()) {
            return 'fraud_rejected';
        }

        // Midtrans documents status_code 200 as the only code for a successful
        // transaction. A created-but-unpaid one reports 201 and an expired one
        // reports 202, so neither may settle even with a settlement-like status.
        if (! $status->isSuccessfulResponse()) {
            return 'status_code_rejected';
        }

        if (! $status->grossAmountMatches((string) $payment->amount)) {
            return 'amount_mismatch';
        }

        if (! $status->currencyMatches((string) config('payments.currency', 'IDR'))) {
            return 'currency_mismatch';
        }

        return null;
    }

    /**
     * Promote the order and open its production record.
     *
     * A confirmed payment only moves the order to "payment confirmed". It never
     * completes production or the order itself; that stays with the production
     * workflow.
     */
    private function markOrderPaid(Order $order, Payment $payment): void
    {
        $current = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from((string) $order->status);

        if ($current === OrderStatus::PendingPayment) {
            $order->forceFill(['paid_at' => $payment->paid_at ?? now()])->save();
            $this->statuses->systemTransition(
                $order,
                OrderStatus::PaymentConfirmed,
                'Pembayaran dikonfirmasi otomatis oleh penyedia pembayaran.',
            );
        }

        if (! $order->production()->exists()) {
            $order->production()->create([
                'status' => ProductionStatus::InDesign,
                'progress' => 0,
                'deadline' => $order->deadline,
            ]);
        }

        // The money is already confirmed at this point, so the notification must never
        // be able to undo the settlement. It is deferred until the surrounding
        // transaction commits; otherwise a failed insert would roll the payment
        // back to PENDING even though the provider already took the money.
        $recipient = $order->customer?->user;
        $orderNumber = $order->number;

        DB::afterCommit(function () use ($recipient, $orderNumber): void {
            // The payment is already committed here, so a notification failure must
            // not surface as an error response: Midtrans would keep retrying a
            // notification that has in fact already been applied.
            try {
                $recipient?->notify(new OrderNotification(
                    'Pembayaran berhasil',
                    "Pembayaran pesanan {$orderNumber} telah dikonfirmasi. Pesanan masuk antrean produksi.",
                    route('customer.orders.show', ['order' => $orderNumber]),
                    'success',
                ));
            } catch (Throwable $exception) {
                Log::error('Payment settled but the customer notification failed.', [
                    'order' => $orderNumber,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function paidAt(GatewayStatus $status): Carbon
    {
        if (filled($status->paidAt)) {
            try {
                return Carbon::parse($status->paidAt);
            } catch (Throwable) {
                Log::warning('Unparsable provider settlement time, falling back to now.', [
                    'reference' => $status->reference,
                ]);
            }
        }

        return now();
    }

    /**
     * Mark pending transactions as expired once the pay window has passed.
     *
     * This is local housekeeping, not a settlement: it never sets PAID.
     */
    public function expireOverduePayments(): int
    {
        $expired = 0;

        Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->each(function (Payment $payment) use (&$expired) {
                $payment->forceFill([
                    'status' => PaymentStatus::Expired,
                    'failure_reason' => 'Waktu pembayaran habis sebelum transaksi dikonfirmasi.',
                ])->save();
                $expired++;
            });

        return $expired;
    }
}
