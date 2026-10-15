<?php

namespace App\Enums;

/**
 * Payment channels offered at checkout.
 *
 * Both channels are settled by the payment provider, so neither one produces a
 * customer uploaded proof and neither one is confirmed by an admin.
 */
enum PaymentMethod: string
{
    case Qris = 'QRIS';
    case BankTransfer = 'BANK_TRANSFER';

    public const QRIS = self::Qris;

    public const BANK_TRANSFER = self::BankTransfer;

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            self::BankTransfer => 'Transfer Bank',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Qris => 'Bayar dengan memindai QRIS dari aplikasi e-wallet atau mobile banking Anda.',
            self::BankTransfer => 'Transfer ke nomor virtual account yang dibuat otomatis untuk pesanan ini.',
        };
    }

    /** The payment type key sent to the provider for this channel. */
    public function providerPaymentType(): string
    {
        return match ($this) {
            self::Qris => 'qris',
            self::BankTransfer => 'bank_transfer',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $method): string => $method->value, self::cases());
    }
}
