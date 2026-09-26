<?php

namespace App\Enums;

enum PricingType: string
{
    case PerItem = 'per_item';
    case PerSqm = 'per_sqm';
    case PerMeter = 'per_meter';
    case Fixed = 'fixed';
    case AdditionalFee = 'additional_fee';

    public const PER_ITEM = self::PerItem;

    public const PER_SQM = self::PerSqm;

    public const PER_METER = self::PerMeter;

    public const FIXED = self::Fixed;

    public const ADDITIONAL_FEE = self::AdditionalFee;

    public function label(): string
    {
        return match ($this) {
            self::PerItem => 'Per Item',
            self::PerSqm => 'Per Square Meter',
            self::PerMeter => 'Per Meter',
            self::Fixed => 'Fixed',
            self::AdditionalFee => 'Additional Fee',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
