<?php

namespace App\Support;

use App\Exceptions\DeliveryFeeNotConfiguredException;

/**
 * Resolves the delivery tariff from server configuration.
 *
 * A missing or zero tariff is a configuration error, not an implicit free
 * delivery policy. Free delivery must be declared with DELIVERY_FREE=true so a
 * forgotten environment variable can never turn a paid shipment into Rp0.
 */
final class DeliveryFee
{
    public static function amount(): int
    {
        if ((bool) config('printing.delivery_free', false)) {
            return 0;
        }

        $configured = config('printing.delivery_fee');
        $fee = filter_var($configured, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($fee === false) {
            throw DeliveryFeeNotConfiguredException::missing();
        }

        return $fee;
    }
}
