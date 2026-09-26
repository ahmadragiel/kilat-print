<?php

namespace Database\Factories;

use App\Enums\PricingType;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->sentence(),
            'pricing_type' => fake()->randomElement(PricingType::cases()),
            'price' => fake()->randomFloat(2, 0, 50000),
            'status' => 'active',
        ];
    }
}
