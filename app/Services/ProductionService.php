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
            abort_unless(in_array($orderStatus, [OrderStatus::DesignApproved, OrderStatus::InProduction], true), 422, 'Desain harus disetujui sebelum operator ditugaskan.');
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
                'notes' => $orderStatus === OrderStatus::InProduction ? 'Reassignment job produksi.' : 'Penugasan awal produksi.',
            ]);
            if ($orderStatus === OrderStatus::DesignApproved) {
                $order = $this->statuses->transition($order, OrderStatus::InProduction, $admin, "Ditugaskan ke {$operator->user->name}.");
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

            $allowedNext = match ($old) {
                ProductionStatus::InDesign => [ProductionStatus::Printing],
                ProductionStatus::Printing => [ProductionStatus::Finishing],
                ProductionStatus::Finishing => [ProductionStatus::Packing],
                ProductionStatus::Packing => [ProductionStatus::QualityControl],
                ProductionStatus::QualityControl => [ProductionStatus::Printing],
                default => [],
            };
            abort_unless(in_array($target, $allowedNext, true), 422, "Transisi {$old->label()} ke {$target->label()} tidak diizinkan.");

            $orderTarget = $target === ProductionStatus::Completed
                ? OrderStatus::Completed
                : (in_array($target, [ProductionStatus::InDesign, ProductionStatus::Printing, ProductionStatus::Finishing, ProductionStatus::Packing, ProductionStatus::QualityControl], true) ? OrderStatus::InProduction : null);

            $updates = ['status' => $target, 'progress' => max(0, min(100, $progress))];
            if ($target === ProductionStatus::Printing && ! $production->started_at) {
                $updates['started_at'] = now();
            }
            if ($target === ProductionStatus::Completed) {
                $updates['finished_at'] = now();
                $updates['completed_at'] = now();
            }
            if ($target === ProductionStatus::QualityControl) {
                $updates['progress'] = max($updates['progress'], 90);
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

            if ($old === ProductionStatus::InDesign && $target === ProductionStatus::Printing) {
                $production->order->customer?->user?->notify(new OrderNotification('Produksi dimulai', "Pesanan {$production->order->number} sedang diproduksi.", route('customer.orders.show', $production->order)));
            }

            if ($target === ProductionStatus::Completed) {
                $production->order->customer?->user?->notify(new OrderNotification('Pesanan selesai', "Pesanan {$production->order->number} telah selesai.", route('customer.orders.show', $production->order), 'success'));
            }

            return $production->fresh();
        });
    }

    public function qualityCheck(ProductionOrder $production, User $checker, string $result, ?string $notes): QualityCheck
    {
        return DB::transaction(function () use ($production, $checker, $result, $notes) {
            $currentStatus = $production->status instanceof ProductionStatus ? $production->status : ProductionStatus::from($production->status);
            abort_unless($currentStatus === ProductionStatus::QualityControl, 422, 'Job belum siap diperiksa.');
            $normalized = strtoupper($result);
            abort_unless(in_array($normalized, ['PASS', 'FAIL', 'REWORK'], true), 422, 'Hasil QC tidak valid.');
            if ($normalized !== 'PASS') {
                abort_if(blank($notes), 422, 'Catatan wajib diisi untuk QC FAIL/REWORK.');
            }
            $check = $production->qualityChecks()->create([
                'checker_id' => $checker->id,
                'result' => $normalized,
                'notes' => $notes,
                'checked_at' => now(),
            ]);

            if ($normalized === 'PASS') {
                $production->update(['status' => ProductionStatus::Completed, 'progress' => 100, 'finished_at' => now(), 'completed_at' => now()]);
                $production->statusHistories()->create([
                    'changed_by' => $checker->id,
                    'old_status' => ProductionStatus::QualityControl,
                    'new_status' => ProductionStatus::Completed,
                    'progress' => 100,
                    'note' => $notes ?? 'QC PASS.',
                ]);
                $this->statuses->transition($production->order, OrderStatus::Completed, $checker, $notes ?? 'QC PASS.');
                $production->order->customer?->user?->notify(new OrderNotification('Pesanan selesai', "Pesanan {$production->order->number} telah lolos quality control.", route('customer.orders.show', $production->order), 'success'));
            } else {
                $production->update(['status' => ProductionStatus::Printing, 'progress' => min(90, max(40, $production->progress - 20))]);
                $production->statusHistories()->create([
                    'changed_by' => $checker->id,
                    'old_status' => ProductionStatus::QualityControl,
                    'new_status' => ProductionStatus::Printing,
                    'progress' => $production->progress,
                    'note' => $notes,
                ]);
                $production->order->customer?->user?->notify(new OrderNotification('Produksi diulang', "Pesanan {$production->order->number} masuk tahap rework/re-print.", route('customer.orders.show', $production->order), 'warning'));
            }

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
