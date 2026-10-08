<?php

namespace App\Services;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    /** @var array<string, array<string, list<string>>> */
    private const TRANSITIONS = [
        'customer' => [
            'PENDING_PAYMENT' => ['PAYMENT_REVIEW', 'CANCELLED'],
            'PAYMENT_REVIEW' => ['CANCELLED'],
            'PAYMENT_CONFIRMED' => ['DESIGN_REVIEW', 'CANCELLED'],
            'DESIGN_REVIEW' => ['DESIGN_APPROVED', 'DESIGN_REVISION', 'CANCELLED'],
            'DESIGN_REVISION' => ['DESIGN_REVIEW', 'CANCELLED'],
            'DESIGN_APPROVED' => ['CANCELLED'],
        ],
        'operator' => [
            'IN_PRODUCTION' => ['COMPLETED'],
        ],
        'admin' => [
            'PENDING_PAYMENT' => ['PAYMENT_REVIEW', 'CANCELLED'],
            'PAYMENT_REVIEW' => ['PENDING_PAYMENT', 'PAYMENT_CONFIRMED', 'CANCELLED'],
            'PAYMENT_CONFIRMED' => ['DESIGN_REVIEW', 'CANCELLED'],
            'DESIGN_REVIEW' => ['DESIGN_REVISION', 'CANCELLED'],
            'DESIGN_REVISION' => ['DESIGN_REVIEW', 'CANCELLED'],
            'DESIGN_APPROVED' => ['IN_PRODUCTION', 'CANCELLED'],
            'IN_PRODUCTION' => ['COMPLETED', 'CANCELLED'],
        ],
    ];

    public function transition(Order $order, OrderStatus|string $newStatus, User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $newStatus, $actor, $note) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $oldValue = $locked->status instanceof OrderStatus ? $locked->status->value : (string) $locked->status;
            $newValue = $newStatus instanceof OrderStatus ? $newStatus->value : (string) $newStatus;

            if ($oldValue === $newValue) {
                return $locked;
            }

            $role = $actor->role instanceof UserRole ? $actor->role->value : (string) $actor->role;
            $allowed = self::TRANSITIONS[$role] ?? [];
            abort_unless(isset($allowed[$oldValue]) && in_array($newValue, $allowed[$oldValue], true), 422, "Transisi $oldValue ke $newValue tidak diizinkan untuk peran $role.");

            if ($newValue === OrderStatus::DesignReview->value) {
                abort_unless($locked->designFiles()->exists(), 422, 'Pesanan harus memiliki file desain sebelum masuk review.');
            }
            if (in_array($newValue, [OrderStatus::DesignApproved->value], true)) {
                abort_unless($locked->designFiles()->where('status', DesignStatus::Approved->value)->exists(), 422, 'Desain harus disetujui customer sebelum pesanan dapat diproses.');
            }
            if ($newValue === OrderStatus::InProduction->value) {
                abort_unless($locked->production()->whereNotNull('operator_id')->exists(), 422, 'Operator produksi harus ditugaskan sebelum status produksi diubah.');
            }
            if ($newValue === OrderStatus::Completed->value) {
                $locked->update(['completed_at' => now()]);
            }

            $updates = ['status' => $newValue];
            if ($newValue === OrderStatus::Cancelled->value) {
                $updates['cancelled_at'] = now();
            }
            $locked->update($updates);
            if ($newValue === OrderStatus::Cancelled->value && $locked->production()->exists()) {
                $production = $locked->production;
                $oldProductionStatus = $production->status;
                $production->update(['status' => ProductionStatus::Cancelled, 'cancelled_at' => now()]);
                $production->statusHistories()->create([
                    'changed_by' => $actor->id,
                    'old_status' => $oldProductionStatus,
                    'new_status' => ProductionStatus::Cancelled,
                    'progress' => $production->progress,
                    'note' => $note ?? 'Order dibatalkan.',
                ]);
            }
            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'changed_by' => $actor->id,
                'old_status' => $oldValue,
                'new_status' => $newValue,
                'note' => $note,
            ]);

            return $locked->fresh();
        });
    }
}
