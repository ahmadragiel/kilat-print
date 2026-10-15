<?php

namespace App\Support;

use App\Enums\OrderStatus;

class OrderPresenter
{
    public static function label(string|OrderStatus $status): string
    {
        $status = $status instanceof OrderStatus ? $status : OrderStatus::from($status);

        return match ($status) {
            OrderStatus::PendingPayment => 'Menunggu Pembayaran',
            OrderStatus::PaymentConfirmed => 'Pembayaran Dikonfirmasi',
            OrderStatus::InProduction => 'Sedang Diproduksi',
            OrderStatus::Completed => 'Selesai',
            OrderStatus::Cancelled => 'Dibatalkan',
        };
    }

    public static function color(string|OrderStatus $status): string
    {
        return match ($status instanceof OrderStatus ? $status : OrderStatus::from($status)) {
            OrderStatus::Completed => 'emerald',
            OrderStatus::Cancelled => 'rose',
            OrderStatus::PendingPayment => 'amber',
            OrderStatus::InProduction => 'blue',
            default => 'violet',
        };
    }
}
