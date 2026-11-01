<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The delivery tariff is missing or invalid in server configuration.
 *
 * Checkout catches this exception and renders a controlled 503 page instead of
 * exposing a stack trace. It must never be treated as an implicit free tariff.
 */
class DeliveryFeeNotConfiguredException extends RuntimeException
{
    public static function missing(): self
    {
        return new self(
            'DELIVERY_FEE belum diatur ke tarif yang valid. Isi DELIVERY_FEE atau aktifkan DELIVERY_FREE=true untuk pengiriman gratis.'
        );
    }
}
