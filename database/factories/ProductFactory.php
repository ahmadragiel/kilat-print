<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->paragraph(),
            'specifications' => [
                'material' => 'Demo material',
                'finish' => 'Demo finish',
            ],
            'thumbnail' => null,
            'base_price' => fake()->randomFloat(2, 10000, 500000),
            'status' => 'active',
            'minimum_order' => fake()->numberBetween(1, 10),
            'production_days' => fake()->numberBetween(1, 7),
            'popularity_count' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
