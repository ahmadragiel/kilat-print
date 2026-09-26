<?php

namespace Database\Factories;

use App\Enums\PricingType;
use App\Models\PriceRule;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceRule>
 */
class PriceRuleFactory extends Factory
{
    protected $model = PriceRule::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => 'Demo price rule',
            'pricing_type' => fake()->randomElement(PricingType::cases()),
            'price' => fake()->randomFloat(2, 1000, 100000),
            'min_quantity' => 1,
            'active' => true,
            'description' => 'Demo tariff for local development.',
        ];
    }
}
