<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentVerificationService
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    public function upload(Order $order, User $customer, UploadedFile $proof): Payment
    {
        abort_unless($order->customer?->user_id === $customer->id, 403);
        $currentStatus = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from($order->status);
        abort_unless(in_array($currentStatus, [OrderStatus::PendingPayment, OrderStatus::PaymentReview], true), 422, 'Pesanan tidak sedang menunggu pembayaran.');

        $newPath = null;
        $oldPath = null;
        try {
            $payment = DB::transaction(function () use ($order, $customer, $proof, $currentStatus, &$newPath, &$oldPath) {
                $payment = Payment::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
                $paymentStatus = $payment->status instanceof PaymentStatus ? $payment->status : PaymentStatus::from($payment->status);
                abort_if(in_array($paymentStatus, [PaymentStatus::WaitingVerification, PaymentStatus::Paid], true), 422, 'Bukti pembayaran sudah sedang diproses atau telah disetujui.');

                $oldPath = $payment->proof_path;
                $newPath = $proof->store("payment-proofs/{$order->id}", 'local');
                $payment->update([
                    'status' => PaymentStatus::WaitingVerification,
                    'proof_path' => $newPath,
                    'proof_original_filename' => $proof->getClientOriginalName(),
                    'proof_extension' => strtolower($proof->extension()),
                    'proof_mime_type' => $proof->getMimeType(),
                    'proof_size' => $proof->getSize(),
                    'proof_version' => $payment->proof_path ? ((int) $payment->proof_version) + 1 : 1,
                    'submitted_at' => now(),
                    'verified_at' => null,
                    'verified_by' => null,
                    'rejection_reason' => null,
                ]);

                if ($currentStatus === OrderStatus::PendingPayment) {
                    $this->statuses->transition($order, OrderStatus::PaymentReview, $customer, 'Bukti pembayaran diunggah.');
                }

                return $payment->fresh();
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $payment;
    }

    public function approve(Order $order, User $admin): Payment
    {
        return DB::transaction(function () use ($order, $admin) {
            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $paymentStatus = $payment->status instanceof PaymentStatus ? $payment->status : PaymentStatus::from($payment->status);
            abort_unless($paymentStatus === PaymentStatus::WaitingVerification, 422, 'Pembayaran tidak sedang menunggu verifikasi.');

            $payment->update([
                'status' => PaymentStatus::Paid,
                'verified_at' => now(),
                'verified_by' => $admin->id,
                'rejection_reason' => null,
            ]);
            $order->update(['paid_at' => now()]);
            $order = $this->statuses->transition($order, OrderStatus::PaymentConfirmed, $admin, 'Pembayaran transfer bank terverifikasi.');
            if ($order->designFiles()->exists()) {
                $order = $this->statuses->transition($order, OrderStatus::DesignReview, $admin, 'Desain dari konfigurasi cart siap ditinjau.');
            }
            $order->customer?->user?->notify(new OrderNotification(
                'Pembayaran dikonfirmasi',
                "Pembayaran pesanan {$order->number} telah kami terima.",
                route('customer.orders.show', $order),
                'success',
            ));

            return $payment->fresh();
        });
    }

    public function reject(Order $order, User $admin, string $reason): Payment
    {
        return DB::transaction(function () use ($order, $admin, $reason) {
            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $paymentStatus = $payment->status instanceof PaymentStatus ? $payment->status : PaymentStatus::from($payment->status);
            abort_unless($paymentStatus === PaymentStatus::WaitingVerification, 422, 'Pembayaran tidak sedang menunggu verifikasi.');

            $payment->update([
                'status' => PaymentStatus::Rejected,
                'verified_at' => now(),
                'verified_by' => $admin->id,
                'rejection_reason' => $reason,
            ]);
            $this->statuses->transition($order, OrderStatus::PendingPayment, $admin, "Pembayaran ditolak: {$reason}");
            $order->customer?->user?->notify(new OrderNotification(
                'Pembayaran perlu diunggah ulang',
                "Alasan: {$reason}",
                route('customer.orders.show', $order),
                'warning',
            ));

            return $payment->fresh();
        });
    }
}
