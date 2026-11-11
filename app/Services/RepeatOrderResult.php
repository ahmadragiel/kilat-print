<?php

namespace App\Services;

use App\Models\Cart;

/**
 * Outcome of rebuilding a cart from a historical order.
 *
 * The cart already contains the new items. Missing designs are reported by
 * product name so the controller can warn the customer without inventing a
 * file reference for artwork that is no longer stored.
 */
final readonly class RepeatOrderResult
{
    /** @param list<string> $missingDesigns */
    public function __construct(
        public Cart $cart,
        public int $reusedDesigns,
        public array $missingDesigns,
    ) {}
}
