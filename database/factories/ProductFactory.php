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

    /** Nama produk mengikuti katalog Kilat Print pada laporan Kerja Praktek. */
    public const REPORT_PRODUCTS = [
        'Mug Custom',
        'Bando Tuning Custom',
        'Paper Bag Custom',
        'Kertas Kado',
        'Apotek Mini',
        'Topper Cake',
        'Topeng Muka',
    ];

    public function definition(): array
    {
        $name = fake()->randomElement(self::REPORT_PRODUCTS);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => "Produk {$name} yang dapat disesuaikan dengan desain dan kebutuhan pelanggan. Data uji untuk pengujian fitur.",
            'specifications' => [
                'demo' => true,
                'size' => 'Sesuai kebutuhan pelanggan',
            ],
            'thumbnail' => null,
            'base_price' => fake()->randomFloat(2, 3000, 50000),
            'status' => 'active',
            'is_featured' => false,
            'minimum_order' => 1,
            'production_days' => fake()->numberBetween(1, 5),
            'popularity_count' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}