<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single place where order status changes are recorded.
 *
 * The manual payment review and design review stages were removed, so the
 * lifecycle is now: waiting for payment, payment confirmed by the provider, then
 * the unchanged production workflow.
 */
class OrderStatusService
{
    /** @var array<string, array<string, list<string>>> */
    private const TRANSITIONS = [
        'customer' => [
            'PENDING_PAYMENT' => ['CANCELLED'],
            'PAYMENT_CONFIRMED' => ['CANCELLED'],
        ],
        'operator' => [
            'IN_PRODUCTION' => ['COMPLETED'],
        ],
        'admin' => [
            'PENDING_PAYMENT' => ['CANCELLED'],
            'PAYMENT_CONFIRMED' => ['IN_PRODUCTION', 'CANCELLED'],
            // Completion is owned by the production workflow after QC PASS.
            'IN_PRODUCTION' => ['CANCELLED'],
        ],
        // Transitions driven by the payment provider, never by a browser request.
        'system' => [
            'PENDING_PAYMENT' => ['PAYMENT_CONFIRMED', 'CANCELLED'],
            'PAYMENT_CONFIRMED' => ['CANCELLED'],
        ],
    ];

    /**
     * Statuses the given role may move this order to, filtered by the
     * operational preconditions the transition service enforces.
     *
     * @return list<OrderStatus>
     */
    public function allowedTransitions(Order $order, string $role): array
    {
        $current = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        $allowed = self::TRANSITIONS[$role][$current] ?? [];

        $statuses = array_values(array_filter(array_map(
            fn (string $value) => OrderStatus::tryFrom($value),
            $allowed,
        )));

        return array_values(array_filter($statuses, function (OrderStatus $status) use ($order) {
            if ($status !== OrderStatus::InProduction) {
                return true;
            }

            return $order->payment()->where('status', 'PAID')->exists()
                && $order->production()->whereNotNull('operator_id')->exists();
        }));
    }

    public function transition(Order $order, OrderStatus|string $newStatus, User $actor, ?string $note = null): Order
    {
        return $this->apply(
            $order,
            $newStatus,
            $actor->role instanceof UserRole ? $actor->role->value : (string) $actor->role,
            $actor->id,
            $note,
        );
    }

    /**
     * Apply a transition triggered by the payment provider.
     *
     * The history row records no actor, which keeps provider-driven changes
     * distinguishable from admin and customer actions in the audit trail.
     */
    public function systemTransition(Order $order, OrderStatus|string $newStatus, ?string $note = null): Order
    {
        return $this->apply($order, $newStatus, 'system', null, $note);
    }

    private function apply(Order $order, OrderStatus|string $newStatus, string $role, ?int $actorId, ?string $note): Order
    {
        return DB::transaction(function () use ($order, $newStatus, $role, $actorId, $note) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $oldValue = $locked->status instanceof OrderStatus ? $locked->status->value : (string) $locked->status;
            $newValue = $newStatus instanceof OrderStatus ? $newStatus->value : (string) $newStatus;

            if ($oldValue === $newValue) {
                return $locked;
            }

            $allowed = self::TRANSITIONS[$role] ?? [];
            abort_unless(isset($allowed[$oldValue]) && in_array($newValue, $allowed[$oldValue], true), 422, "Transisi $oldValue ke $newValue tidak diizinkan untuk peran $role.");

            if ($newValue === OrderStatus::InProduction->value) {
                abort_unless($locked->payment()->where('status', 'PAID')->exists(), 422, 'Pembayaran belum dikonfirmasi oleh penyedia pembayaran.');
                abort_unless($locked->production()->whereNotNull('operator_id')->exists(), 422, 'Operator produksi harus ditugaskan sebelum status produksi diubah.');
            }
            if ($newValue === OrderStatus::Completed->value) {
                abort_unless(
                    $locked->production()->whereHas('qualityChecks', fn ($query) => $query->where('result', 'PASS'))->exists(),
                    422,
                    'Order hanya dapat diselesaikan setelah quality control PASS.',
                );
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
                    'changed_by' => $actorId,
                    'old_status' => $oldProductionStatus,
                    'new_status' => ProductionStatus::Cancelled,
                    'progress' => $production->progress,
                    'note' => $note ?? 'Order dibatalkan.',
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'changed_by' => $actorId,
                'old_status' => $oldValue,
                'new_status' => $newValue,
                'note' => $note,
            ]);

            return $locked->fresh();
        });
    }
}
