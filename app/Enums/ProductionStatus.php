<?php

namespace App\Enums;

enum ProductionStatus: string
{
    case InDesign = 'IN_DESIGN';
    case Printing = 'PRINTING';
    case Finishing = 'FINISHING';
    case Packing = 'PACKING';
    case QualityControl = 'QUALITY_CONTROL';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    // Upper-case aliases make persisted values convenient in queries and migrations.
    public const IN_DESIGN = self::InDesign;

    public const PRINTING = self::Printing;

    public const FINISHING = self::Finishing;

    public const PACKING = self::Packing;

    public const QUALITY_CONTROL = self::QualityControl;

    public const COMPLETED = self::Completed;

    public const CANCELLED = self::Cancelled;

    public function label(): string
    {
        return match ($this) {
            self::InDesign => 'In-Design',
            self::Printing => 'Printing',
            self::Finishing => 'Finishing',
            self::Packing => 'Packing',
            self::QualityControl => 'Quality Control',
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
