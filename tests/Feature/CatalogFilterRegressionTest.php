<?php

namespace Tests\Feature;

use App\Enums\PricingType;
use Tests\TestCase;

/**
 * Regressions for the public catalog filter contract.
 *
 * The Blade filter form and the server-side validation used different values,
 * so several options offered to the customer were rejected or silently ignored.
 */
class CatalogFilterRegressionTest extends TestCase
{
    public function test_catalog_accepts_latest_and_name_sort_options(): void
    {
        $older = $this->makeProduct(attributes: [
            'name' => 'Zebra Product',
            'slug' => 'zebra-product',
        ]);
        $newer = $this->makeProduct(attributes: [
            'name' => 'Alpha Product',
            'slug' => 'alpha-product',
        ]);

        $older->forceFill(['created_at' => now()->subMinute()])->save();
        $newer->forceFill(['created_at' => now()])->save();

        $latest = $this->get(route('products.index', ['sort' => 'latest']));
        $latest->assertOk()->assertSessionHasNoErrors();
        $this->assertSame($newer->id, $latest->viewData('products')->first()?->id);

        $byName = $this->get(route('products.index', ['sort' => 'name']));
        $byName->assertOk()->assertSessionHasNoErrors();
        $this->assertSame($newer->id, $byName->viewData('products')->first()?->id);
    }

    public function test_catalog_price_filters_accept_decimals_and_a_maximum_without_a_minimum(): void
    {
        $product = $this->makeProduct(PricingType::PER_ITEM, 15000, [
            'name' => 'Produk Rentang Harga',
            'slug' => 'produk-rentang-harga',
        ]);

        $withBoth = $this->get(route('products.index', [
            'min_price' => '10000.50',
            'max_price' => '20000.50',
        ]));
        $withBoth->assertOk()->assertSessionHasNoErrors();
        $this->assertTrue($withBoth->viewData('products')->pluck('id')->contains($product->id));

        $maxOnly = $this->get(route('products.index', ['max_price' => '20000.50']));
        $maxOnly->assertOk()->assertSessionHasNoErrors();
        $this->assertTrue($maxOnly->viewData('products')->pluck('id')->contains($product->id));
    }

    public function test_catalog_reset_and_active_badges_react_to_search_and_price_filters(): void
    {
        $product = $this->makeProduct(PricingType::PER_ITEM, 15000, [
            'name' => 'Produk Pencarian Khusus',
            'slug' => 'produk-pencarian-khusus',
        ]);

        $response = $this->get(route('products.index', [
            'search' => $product->name,
            'min_price' => '1',
            'max_price' => '999999',
        ]));

        $response->assertOk()->assertSessionHasNoErrors();
        $response->assertSee('aria-label="Reset filter"', false);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($response->getContent(), 'Pencarian: '.$product->name),
            'The search term should appear in both the result heading and the active filter badge.',
        );
    }
}
