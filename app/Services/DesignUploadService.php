<?php

namespace App\Services;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Models\DesignFile;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Stores the design a customer attaches to an order.
 *
 * The manual admin design review and approval stages were removed, so an
 * uploaded design is registered as print-ready straight away and production can
 * use it once the order is paid.
 */
class DesignUploadService
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    /**
     * Store a customer design upload.
     *
     * The manual design review and approval stages were removed, so every uploaded
     * design is registered as print-ready straight away.
     */
    public function upload(Order $order, User $customer, UploadedFile $file, ?int $orderItemId, ?string $notes): DesignFile
    {
        abort_unless($order->customer?->user_id === $customer->id, 403);
        $currentStatus = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from($order->status);
        abort_if($currentStatus->isTerminal(), 422, 'Pesanan sudah selesai atau dibatalkan.');

        if ($orderItemId) {
            abort_unless($order->items()->whereKey($orderItemId)->exists(), 422, 'Item pesanan tidak valid.');
        }

        $path = null;
        try {
            return DB::transaction(function () use ($order, $customer, $file, $orderItemId, $notes, &$path) {
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
                    'status' => DesignStatus::Approved,
                    'notes' => $notes,
                    'uploaded_by' => $customer->id,
                    'uploaded_at' => now(),
                ]);

                $order->customer?->user?->notify(new OrderNotification(
                    'Desain diterima',
                    "Versi {$version} pesanan {$order->number} telah disimpan dan siap dicetak.",
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
}
