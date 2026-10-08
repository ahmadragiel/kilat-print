<?php

namespace App\Enums;

/** The complete order lifecycle used by the customer, payment, design and production workflows. */
enum OrderStatus: string
{
    case PendingPayment = 'PENDING_PAYMENT';
    case PaymentReview = 'PAYMENT_REVIEW';
    case PaymentConfirmed = 'PAYMENT_CONFIRMED';
    case DesignReview = 'DESIGN_REVIEW';
    case DesignRevision = 'DESIGN_REVISION';
    case DesignApproved = 'DESIGN_APPROVED';
    case InProduction = 'IN_PRODUCTION';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    // Upper-case aliases keep the persisted values convenient in queries and migrations.
    public const PENDING_PAYMENT = self::PendingPayment;

    public const PAYMENT_REVIEW = self::PaymentReview;

    public const PAYMENT_CONFIRMED = self::PaymentConfirmed;

    public const DESIGN_REVIEW = self::DesignReview;

    public const DESIGN_REVISION = self::DesignRevision;

    public const DESIGN_APPROVED = self::DesignApproved;

    public const IN_PRODUCTION = self::InProduction;

    public const COMPLETED = self::Completed;

    public const CANCELLED = self::Cancelled;

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::PaymentReview => 'Payment Review',
            self::PaymentConfirmed => 'Payment Confirmed',
            self::DesignReview => 'Design Review',
            self::DesignRevision => 'Design Revision',
            self::DesignApproved => 'Design Approved',
            self::InProduction => 'In Production',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
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
