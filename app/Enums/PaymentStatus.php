<?php

namespace App\Enums;

/**
 * Lifecycle of a gateway payment transaction.
 *
 * Only {@see self::Paid} may be written after the backend has received a verified
 * confirmation from the payment provider. Every other state is written by the
 * application itself when it creates, expires or cancels a transaction.
 */
enum PaymentStatus: string
{
    /** Transaction created at the provider, waiting for the customer to pay. */
    case Pending = 'PENDING';

    /** Confirmed by the provider through a verified webhook or status response. */
    case Paid = 'PAID';

    /** The provider reported the transaction as denied or cancelled. */
    case Failed = 'FAILED';

    /** The provider reported the transaction as expired. */
    case Expired = 'EXPIRED';

    /** Cancelled locally before the provider reported a final state. */
    case Cancelled = 'CANCELLED';

    public const PENDING = self::Pending;

    public const PAID = self::Paid;

    public const FAILED = self::Failed;

    public const EXPIRED = self::Expired;

    public const CANCELLED = self::Cancelled;

    /** States from which a later provider confirmation must never move the payment. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Failed, self::Expired, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Paid => 'Pembayaran Berhasil',
            self::Failed => 'Pembayaran Gagal',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
