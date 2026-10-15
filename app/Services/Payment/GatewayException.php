<?php

namespace App\Services\Payment;

use RuntimeException;

/**
 * Raised when the payment provider cannot be used.
 *
 * Controllers translate this into a user facing message; it never results in a
 * payment being marked as paid.
 */
class GatewayException extends RuntimeException
{
    public static function notConfigured(string $driver): self
    {
        return new self("Payment gateway [{$driver}] is not configured. Set PAYMENT_PROVIDER, PAYMENT_SERVER_KEY and PAYMENT_SANDBOX before enabling online payment.");
    }

    public static function unreachable(string $driver, string $reason): self
    {
        return new self("Payment gateway [{$driver}] could not be reached: {$reason}");
    }

    public static function rejected(string $driver, string $reason): self
    {
        return new self("Payment gateway [{$driver}] rejected the request: {$reason}");
    }

    public static function unknownTransaction(string $driver, string $reference): self
    {
        return new self("Payment gateway [{$driver}] does not know transaction [{$reference}].");
    }
}
