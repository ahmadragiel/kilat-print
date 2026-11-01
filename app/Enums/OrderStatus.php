<?php

namespace App\Enums;

/**
 * The order lifecycle after the manual design review stages were removed.
 *
 * An order is created in {@see self::PendingPayment} and is only promoted to
 * {@see self::PaymentConfirmed} by the payment settlement service once the payment
 * provider has confirmed the transaction. Production then follows the existing
 * production rules unchanged.
 */
enum OrderStatus: string
{
    case PendingPayment = 'PENDING_PAYMENT';
    case PaymentConfirmed = 'PAYMENT_CONFIRMED';
    case InProduction = 'IN_PRODUCTION';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    // Upper-case aliases keep the persisted values convenient in queries and migrations.
    public const PENDING_PAYMENT = self::PendingPayment;

    public const PAYMENT_CONFIRMED = self::PaymentConfirmed;

    public const IN_PRODUCTION = self::InProduction;

    public const COMPLETED = self::Completed;

    public const CANCELLED = self::Cancelled;

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu pembayaran',
            self::PaymentConfirmed => 'Pembayaran dikonfirmasi',
            self::InProduction => 'Sedang diproduksi',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
