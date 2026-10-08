<?php

namespace App\Support;

use App\Enums\OrderStatus;

class OrderPresenter
{
    public static function label(string|OrderStatus $status): string
    {
        $status = $status instanceof OrderStatus ? $status : OrderStatus::from($status);

        return match ($status) {
            OrderStatus::PENDING_PAYMENT => 'Menunggu Pembayaran',
            OrderStatus::PAYMENT_REVIEW => 'Verifikasi Pembayaran',
            OrderStatus::PAYMENT_CONFIRMED => 'Pembayaran Dikonfirmasi',
            OrderStatus::DESIGN_REVIEW => 'Meninjau Desain',
            OrderStatus::DESIGN_REVISION => 'Revisi Desain',
            OrderStatus::DESIGN_APPROVED => 'Desain Disetujui',
            OrderStatus::IN_PRODUCTION => 'Sedang Diproduksi',
            OrderStatus::COMPLETED => 'Selesai',
            OrderStatus::CANCELLED => 'Dibatalkan',
        };
    }

    public static function color(string|OrderStatus $status): string
    {
        return match ($status instanceof OrderStatus ? $status : OrderStatus::from($status)) {
            OrderStatus::COMPLETED => 'emerald',
            OrderStatus::CANCELLED, OrderStatus::DESIGN_REVISION => 'rose',
            OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_REVIEW => 'amber',
            OrderStatus::IN_PRODUCTION => 'blue',
            default => 'violet',
        };
    }
}
