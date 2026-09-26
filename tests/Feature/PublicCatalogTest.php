<?php

namespace Tests\Feature;

use App\Models\Category;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    public function test_homepage_uses_active_categories_and_products(): void
    {
        $category = Category::factory()->create([
            'name' => 'Banner Test',
            'slug' => 'banner-test',
            'status' => 'active',
        ]);
        $inactiveCategory = Category::factory()->create([
            'name' => 'Inactive Category',
            'slug' => 'inactive-category',
            'status' => 'inactive',
        ]);
        $product = $this->makeProduct(attributes: [
            'name' => 'Banner Homepage',
            'slug' => 'banner-homepage',
            'category_id' => $category->id,
            'popularity_count' => 99,
        ]);
        $inactive = $this->makeProduct(attributes: [
            'name' => 'Inactive Homepage Product',
            'slug' => 'inactive-homepage-product',
            'category_id' => $category->id,
            'status' => 'inactive',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertViewHas('categories', function ($categories) use ($category, $inactiveCategory) {
            return $categories->contains($category) && ! $categories->contains($inactiveCategory);
        });
        $response->assertViewHas('popularProducts', function ($products) use ($product, $inactive) {
            return $products->contains($product) && ! $products->contains($inactive);
        });
        $response->assertSee('Banner Homepage');
    }

    public function test_catalog_searches_and_filters_active_products(): void
    {
        $category = Category::factory()->create([
            'name' => 'Sticker Category',
            'slug' => 'sticker-category',
        ]);
        $otherCategory = Category::factory()->create([
            'name' => 'Other Category',
            'slug' => 'other-category',
        ]);
        $matching = $this->makeProduct(attributes: [
            'name' => 'Premium Sticker Sheet',
            'slug' => 'premium-sticker-sheet',
            'category_id' => $category->id,
        ]);
        $wrongCategory = $this->makeProduct(attributes: [
            'name' => 'Premium Sticker Roll',
            'slug' => 'premium-sticker-roll',
            'category_id' => $otherCategory->id,
        ]);
        $inactive = $this->makeProduct(attributes: [
            'name' => 'Premium Sticker Hidden',
            'slug' => 'premium-sticker-hidden',
            'category_id' => $category->id,
            'status' => 'inactive',
        ]);

        $response = $this->get(route('products.index', [
            'search' => 'Sticker',
            'category' => $category->slug,
            'min_price' => 1,
            'max_price' => 20000,
        ]));

        $response->assertOk();
        $products = $response->viewData('products');
        $this->assertTrue($products->contains($matching));
        $this->assertFalse($products->contains($wrongCategory));
        $this->assertFalse($products->contains($inactive));
        $this->assertSame('Sticker', $response->viewData('filters')['search']);
    }

    public function test_catalog_can_sort_by_price_and_product_detail_loads_options_and_increments_popularity(): void
    {
        $cheaper = $this->makeProduct(price: 2000, attributes: [
            'name' => 'Cheap Print',
            'slug' => 'cheap-print',
        ]);
        $expensive = $this->makeProduct(price: 50000, attributes: [
            'name' => 'Premium Print',
            'slug' => 'premium-print',
        ]);
        $material = $this->attachMaterial($cheaper, price: 250);
        $finishing = $this->attachFinishing($cheaper, price: 500);

        $response = $this->get(route('products.index', ['sort' => 'price_low']));
        $response->assertOk();
        $this->assertSame($cheaper->id, $response->viewData('products')->first()?->id);
        $this->assertTrue($response->viewData('products')->contains($expensive));

        $detail = $this->get(route('products.show', $cheaper));
        $detail->assertOk();
        $detail->assertSee('Cheap Print');
        $this->assertDatabaseHas('products', ['id' => $cheaper->id, 'popularity_count' => 1]);
        $this->assertTrue($cheaper->fresh()->materials->contains($material));
        $this->assertTrue($cheaper->fresh()->finishings->contains($finishing));
    }

    public function test_product_price_endpoint_returns_server_calculation_for_customer(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(price: 12500);

        $response = $this->actingAs($customer->user)->getJson(route('products.price', [
            'product' => $product->slug,
            'quantity' => 3,
        ]));

        $response->assertOk()
            ->assertJsonPath('quantity', 3)
            ->assertJsonPath('unit_price', 12500)
            ->assertJsonPath('total', 37500)
            ->assertJsonPath('breakdown.product', 37500);
    }
}
