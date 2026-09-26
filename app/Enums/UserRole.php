<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Customer = 'customer';

    public const ADMIN = self::Admin;

    public const OPERATOR = self::Operator;

    public const CUSTOMER = self::Customer;

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Operator => 'Operator',
            self::Customer => 'Customer',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
