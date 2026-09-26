<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'UNPAID';
    case WaitingVerification = 'WAITING_VERIFICATION';
    case Paid = 'PAID';
    case Rejected = 'REJECTED';

    public const UNPAID = self::Unpaid;

    public const WAITING_VERIFICATION = self::WaitingVerification;

    public const PAID = self::Paid;

    public const REJECTED = self::Rejected;

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::WaitingVerification => 'Waiting Verification',
            self::Paid => 'Paid',
            self::Rejected => 'Rejected',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
