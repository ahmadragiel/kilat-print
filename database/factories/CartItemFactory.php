<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'size' => fake()->optional()->randomElement(['A5', 'A4', '3x1m']),
            'length_cm' => fake()->optional()->randomFloat(2, 10, 300),
            'width_cm' => fake()->optional()->randomFloat(2, 10, 300),
            'production_method' => fake()->randomElement(['digital', 'offset', 'large_format', 'sublimation']),
            'custom_parameters' => [],
            'price_at_addition' => fake()->randomFloat(2, 10000, 500000),
            'notes' => null,
        ];
    }
}
