<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Enums\UserRole;
use App\Models\Operator;
use App\Models\ProductionOrder;
use App\Models\QualityCheck;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductionService
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    public function assign(ProductionOrder $production, Operator $operator, User $admin, ?string $deadline = null): ProductionOrder
    {
        return DB::transaction(function () use ($production, $operator, $admin, $deadline) {
            $order = $production->order;
            $orderStatus = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from($order->status);
            abort_unless(in_array($orderStatus, [OrderStatus::DesignApproved, OrderStatus::WaitingProduction], true), 422, 'Desain harus disetujui sebelum operator ditugaskan.');
            abort_unless($operator->is_active && $operator->user?->is_active, 422, 'Operator tidak aktif.');

            $production->assignments()->where('is_current', true)->update(['is_current' => false, 'unassigned_at' => now()]);
            $production->update([
                'operator_id' => $operator->id,
                'deadline' => $deadline ?: $production->deadline,
                'assigned_at' => now(),
            ]);
            $production->assignments()->create([
                'operator_id' => $operator->id,
                'assigned_by' => $admin->id,
                'assigned_at' => now(),
                'is_current' => true,
                'notes' => $orderStatus === OrderStatus::WaitingProduction ? 'Reassignment job produksi.' : 'Penugasan awal produksi.',
            ]);
            if ($orderStatus === OrderStatus::DesignApproved) {
                $order = $this->statuses->transition($order, OrderStatus::WaitingProduction, $admin, "Ditugaskan ke {$operator->user->name}.");
            }
            $operator->user?->notify(new OrderNotification(
                'Job produksi baru',
                "Anda menerima job {$order->number}.",
                route('operator.jobs.show', $production),
            ));
            $order->customer?->user?->notify(new OrderNotification(
                'Pesanan masuk antrean produksi',
                "Operator telah ditugaskan untuk pesanan {$order->number}.",
                route('customer.orders.show', $order),
            ));

            return $production->fresh();
        });
    }

    public function transition(ProductionOrder $production, ProductionStatus $target, User $actor, int $progress, ?string $note = null): ProductionOrder
    {
        return DB::transaction(function () use ($production, $target, $actor, $progress, $note) {
            $old = $production->status instanceof ProductionStatus ? $production->status : ProductionStatus::from($production->status);
            abort_unless($actor->isRole(UserRole::Admin) || $production->operator?->user_id === $actor->id, 403);
            abort_if($old === $target, 422, 'Status produksi sudah berada pada status tersebut.');

            $orderTarget = match ($target) {
                ProductionStatus::InProduction => OrderStatus::InProduction,
                ProductionStatus::Finishing => OrderStatus::Finishing,
                ProductionStatus::QualityCheck => OrderStatus::QualityCheck,
                ProductionStatus::Ready => OrderStatus::Ready,
                default => null,
            };

            $updates = ['status' => $target, 'progress' => max(0, min(100, $progress))];
            if ($target === ProductionStatus::InProduction && ! $production->started_at) {
                $updates['started_at'] = now();
            }
            if (in_array($target, [ProductionStatus::Ready, ProductionStatus::Completed], true)) {
                $updates['finished_at'] = now();
            }
            $production->update($updates);
            $production->statusHistories()->create([
                'changed_by' => $actor->id,
                'old_status' => $old,
                'new_status' => $target,
                'progress' => $updates['progress'],
                'note' => $note,
            ]);

            if ($orderTarget) {
                $this->statuses->transition($production->order, $orderTarget, $actor, $note);
            }

            if ($target === ProductionStatus::InProduction) {
                $production->order->customer?->user?->notify(new OrderNotification('Produksi dimulai', "Pesanan {$production->order->number} sedang diproduksi.", route('customer.orders.show', $production->order)));
            }

            if ($target === ProductionStatus::Ready) {
                $production->order->customer?->user?->notify(new OrderNotification('Pesanan siap', "Pesanan {$production->order->number} telah lolos quality check.", route('customer.orders.show', $production->order), 'success'));
            }

            return $production->fresh();
        });
    }

    public function qualityCheck(ProductionOrder $production, User $checker, string $result, ?string $notes): QualityCheck
    {
        return DB::transaction(function () use ($production, $checker, $result, $notes) {
            $currentStatus = $production->status instanceof ProductionStatus ? $production->status : ProductionStatus::from($production->status);
            abort_unless($currentStatus === ProductionStatus::QualityCheck, 422, 'Job belum siap diperiksa.');
            $check = $production->qualityChecks()->create([
                'checker_id' => $checker->id,
                'result' => strtoupper($result),
                'notes' => $notes,
                'checked_at' => now(),
            ]);
            $target = $result === 'PASS' ? ProductionStatus::Ready : ProductionStatus::InProduction;
            $this->transition($production->fresh(), $target, $checker, $result === 'PASS' ? 100 : max(50, $production->progress - 10), $notes);

            return $check->fresh();
        });
    }

    public function addPhoto(ProductionOrder $production, User $actor, UploadedFile $photo): void
    {
        abort_unless($actor->isRole(UserRole::Admin) || $production->operator?->user_id === $actor->id, 403);
        $path = $photo->store("production-photos/{$production->id}", 'local');
        try {
            $production->photos()->create([
                'path' => $path,
                'original_filename' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getMimeType(),
                'size' => $photo->getSize(),
                'uploaded_by' => $actor->id,
                'uploaded_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }
}
