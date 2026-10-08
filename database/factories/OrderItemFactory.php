<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 25);
        $unitPrice = fake()->randomFloat(2, 5000, 100000);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fn (array $attributes): ?string => Product::find($attributes['product_id'])?->name,
            'product_reference' => 'REF-'.fake()->unique()->numerify('#####'),
            'product_slug' => fn (array $attributes): ?string => Product::find($attributes['product_id'])?->slug,
            'quantity' => $quantity,
            'production_method' => 'digital',
            'custom_parameters' => [],
            'configuration' => [],
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
        ];
    }
}
