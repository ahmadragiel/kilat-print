<?php

namespace App\Enums;

enum DesignStatus: string
{
    case Pending = 'Pending';
    case AwaitingCustomerApproval = 'Awaiting Customer Approval';
    case Approved = 'Approved';
    case RevisionRequired = 'Revision Required';

    public const PENDING = self::Pending;

    public const AWAITING_CUSTOMER_APPROVAL = self::AwaitingCustomerApproval;

    public const APPROVED = self::Approved;

    public const REVISION_REQUIRED = self::RevisionRequired;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingCustomerApproval => 'Menunggu Persetujuan Customer',
            self::Approved => 'Approved',
            self::RevisionRequired => 'Revision Required',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
