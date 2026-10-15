<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maps the removed workflow statuses onto the new lifecycle, then widens both
 * status columns so future statuses do not need a destructive enum rewrite.
 *
 * Legacy values are translated before the constraint is dropped, so historical
 * orders and payments keep a meaningful state. Nothing is deleted here: the
 * order status history rows survive with a translated status.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Widen first: the enum/check constraint must be gone before the new
        // status values can be written on either MySQL or SQLite.
        $this->widenStatusColumns();
        $this->mapLegacyStatuses();
    }

    public function down(): void
    {
        // The legacy statuses are not restored: orders that moved past payment
        // cannot be pushed back into a removed review stage.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 40)->default('PENDING_PAYMENT')->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('status', 40)->default('PENDING')->change();
        });
    }

    private function mapLegacyStatuses(): void
    {
        $orderStatusMap = [
            'PAYMENT_REVIEW' => OrderStatus::PendingPayment->value,
            'DESIGN_REVIEW' => OrderStatus::PaymentConfirmed->value,
            'DESIGN_REVISION' => OrderStatus::PaymentConfirmed->value,
            'DESIGN_APPROVED' => OrderStatus::PaymentConfirmed->value,
        ];

        foreach ($orderStatusMap as $legacy => $replacement) {
            DB::table('orders')->where('status', $legacy)->update(['status' => $replacement]);
        }

        $paymentStatusMap = [
            'UNPAID' => PaymentStatus::Pending->value,
            'WAITING_VERIFICATION' => PaymentStatus::Pending->value,
            'REJECTED' => PaymentStatus::Failed->value,
        ];

        foreach ($paymentStatusMap as $legacy => $replacement) {
            DB::table('payments')->where('status', $legacy)->update([
                'status' => $replacement,
                'failure_reason' => $legacy === 'REJECTED'
                    ? 'Transaksi sebelumnya ditolak saat verifikasi manual masih dipakai.'
                    : null,
            ]);
        }

        // The audit trail keeps its rows, but the enum cast on the model can only
        // read values that still exist, so history entries are translated too.
        foreach ($orderStatusMap as $legacy => $replacement) {
            DB::table('order_status_histories')->where('new_status', $legacy)->update(['new_status' => $replacement]);
            DB::table('order_status_histories')->where('old_status', $legacy)->update(['old_status' => $replacement]);
        }
    }

    private function widenStatusColumns(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 40)->default(OrderStatus::PendingPayment->value)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('status', 40)->default(PaymentStatus::Pending->value)->change();
        });
    }
};
