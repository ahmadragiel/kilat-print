<?php

namespace App\Enums;

enum ProductionStatus: string
{
    case WaitingProduction = 'WAITING_PRODUCTION';
    case InProduction = 'IN_PRODUCTION';
    case Finishing = 'FINISHING';
    case QualityCheck = 'QUALITY_CHECK';
    case Ready = 'READY';
    case Shipped = 'SHIPPED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    // Upper-case aliases make persisted values convenient in queries and migrations.
    public const WAITING_PRODUCTION = self::WaitingProduction;

    public const IN_PRODUCTION = self::InProduction;

    public const FINISHING = self::Finishing;

    public const QUALITY_CHECK = self::QualityCheck;

    public const READY = self::Ready;

    public const SHIPPED = self::Shipped;

    public const COMPLETED = self::Completed;

    public const CANCELLED = self::Cancelled;

    public function label(): string
    {
        return match ($this) {
            self::WaitingProduction => 'Waiting Production',
            self::InProduction => 'In Production',
            self::Finishing => 'Finishing',
            self::QualityCheck => 'Quality Check',
            self::Ready => 'Ready',
            self::Shipped => 'Shipped',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
