<?php

namespace App\Services;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Models\DesignFile;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DesignReviewService
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    public function upload(Order $order, User $customer, UploadedFile $file, ?int $orderItemId, ?string $notes): DesignFile
    {
        abort_unless($order->customer?->user_id === $customer->id, 403);
        $currentStatus = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from($order->status);
        abort_unless(in_array($currentStatus, [OrderStatus::PaymentConfirmed, OrderStatus::DesignReview, OrderStatus::DesignRevision], true), 422, 'Pesanan tidak dalam tahap upload desain.');

        if ($orderItemId) {
            abort_unless($order->items()->whereKey($orderItemId)->exists(), 422, 'Item pesanan tidak valid.');
        }

        $path = null;
        try {
            return DB::transaction(function () use ($order, $customer, $file, $orderItemId, $notes, $currentStatus, &$path) {
                $version = ((int) $order->designFiles()->max('version')) + 1;
                $path = $file->store("designs/{$order->id}", 'local');
                $design = $order->designFiles()->create([
                    'order_item_id' => $orderItemId,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_filename' => $path,
                    'path' => $path,
                    'extension' => strtolower($file->extension()),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'version' => $version,
                    'status' => DesignStatus::Pending,
                    'notes' => $notes,
                    'uploaded_by' => $customer->id,
                    'uploaded_at' => now(),
                ]);

                if ($currentStatus !== OrderStatus::DesignReview) {
                    $order = $this->statuses->transition($order, OrderStatus::DesignReview, $customer, "Desain versi {$version} diunggah untuk ditinjau.");
                }

                $order->customer?->user?->notify(new OrderNotification(
                    'Desain menunggu peninjauan',
                    "Versi {$version} pesanan {$order->number} telah diterima.",
                    route('customer.orders.show', $order),
                ));

                return $design->fresh();
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function approve(DesignFile $design, User $admin): DesignFile
    {
        return DB::transaction(function () use ($design, $admin) {
            abort_if($design->order->designFiles()->where('version', '>', $design->version)->exists(), 422, 'Hanya versi desain terbaru yang dapat disetujui.');
            $design->update([
                'status' => DesignStatus::Approved,
                'review_note' => null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
            $order = $design->order;
            $orderStatus = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from($order->status);
            abort_unless($orderStatus === OrderStatus::DesignReview, 422, 'Pesanan tidak dalam tahap review desain.');

            $order = $this->statuses->transition($order, OrderStatus::DesignApproved, $admin, "Desain versi {$design->version} disetujui.");
            $order->production()->firstOrCreate([
                'order_id' => $order->id,
            ], [
                'status' => ProductionStatus::WaitingProduction,
                'deadline' => $order->deadline,
            ]);
            $order->customer?->user?->notify(new OrderNotification(
                'Desain disetujui',
                "Desain pesanan {$order->number} siap dijadwalkan untuk produksi.",
                route('customer.orders.show', $order),
                'success',
            ));

            return $design->fresh();
        });
    }

    public function requestRevision(DesignFile $design, User $admin, string $reason): DesignFile
    {
        return DB::transaction(function () use ($design, $admin, $reason) {
            abort_if($design->order->designFiles()->where('version', '>', $design->version)->exists(), 422, 'Hanya versi desain terbaru yang dapat ditinjau.');
            $design->update([
                'status' => DesignStatus::RevisionRequired,
                'review_note' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
            $order = $this->statuses->transition($design->order, OrderStatus::DesignRevision, $admin, $reason);
            $order->customer?->user?->notify(new OrderNotification(
                'Desain perlu direvisi',
                $reason,
                route('customer.orders.show', $order),
                'warning',
            ));

            return $design->fresh();
        });
    }
}
