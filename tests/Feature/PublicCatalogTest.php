<?php

namespace Tests\Feature;

use App\Models\Category;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    public function test_homepage_uses_active_categories_and_products(): void
    {
        $category = Category::factory()->create([
            'name' => 'Kategori Katalog Uji',
            'slug' => 'kategori-katalog-uji',
            'status' => 'active',
        ]);
        $inactiveCategory = Category::factory()->create([
            'name' => 'Inactive Category',
            'slug' => 'inactive-category',
            'status' => 'inactive',
        ]);
        $product = $this->makeProduct(attributes: [
            'name' => 'Mug Custom Unggulan',
            'slug' => 'mug-custom-unggulan',
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
        $response->assertSee('Mug Custom Unggulan');
    }

    public function test_homepage_product_cards_show_discounted_and_struck_original_prices(): void
    {
        $product = $this->makeProduct(attributes: [
            'name' => 'Mug Custom Diskon',
            'slug' => 'mug-custom-diskon',
        ]);
        $product->priceRules()->update(['discount_percent' => 15]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Mug Custom Diskon')
            ->assertSee('Rp 10.000')
            ->assertSee('Rp 8.500')
            ->assertSee('line-through');
    }

    public function test_catalog_searches_and_filters_active_products(): void
    {
        $category = Category::factory()->create([
            'name' => 'Kategori Utama',
            'slug' => 'kategori-utama',
        ]);
        $otherCategory = Category::factory()->create([
            'name' => 'Kategori Cadangan',
            'slug' => 'kategori-cadangan',
        ]);
        $matching = $this->makeProduct(attributes: [
            'name' => 'Topper Cake Premium',
            'slug' => 'topper-cake-premium',
            'category_id' => $category->id,
        ]);
        $wrongCategory = $this->makeProduct(attributes: [
            'name' => 'Topeng Muka Premium',
            'slug' => 'topeng-muka-premium',
            'category_id' => $otherCategory->id,
        ]);
        $inactive = $this->makeProduct(attributes: [
            'name' => 'Apotek Mini Premium Hidden',
            'slug' => 'apotek-mini-premium-hidden',
            'category_id' => $category->id,
            'status' => 'inactive',
        ]);

        $response = $this->get(route('products.index', [
            'search' => 'Premium',
            'category' => $category->slug,
            'min_price' => 1,
            'max_price' => 20000,
        ]));

        $response->assertOk();
        $products = $response->viewData('products');
        $this->assertTrue($products->contains($matching));
        $this->assertFalse($products->contains($wrongCategory));
        $this->assertFalse($products->contains($inactive));
        $this->assertSame('Premium', $response->viewData('filters')['search']);
    }

    public function test_catalog_can_sort_by_price_and_product_detail_loads_options_and_increments_popularity(): void
    {
        $cheaper = $this->makeProduct(price: 2000, attributes: [
            'name' => 'Kertas Kado Murah',
            'slug' => 'kertas-kado-murah',
        ]);
        $expensive = $this->makeProduct(price: 50000, attributes: [
            'name' => 'Bando Tuning Custom Premium',
            'slug' => 'bando-tuning-custom-premium',
        ]);
        $material = $this->attachMaterial($cheaper, price: 250);
        $finishing = $this->attachFinishing($cheaper, price: 500);

        $response = $this->get(route('products.index', ['sort' => 'price_low']));
        $response->assertOk();
        $this->assertSame($cheaper->id, $response->viewData('products')->first()?->id);
        $this->assertTrue($response->viewData('products')->contains($expensive));

        $detail = $this->get(route('products.show', $cheaper));
        $detail->assertOk();
        $detail->assertSee('Kertas Kado Murah');
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
