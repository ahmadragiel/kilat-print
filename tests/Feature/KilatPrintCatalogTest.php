<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\RealCatalogSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The public catalog must contain the four real Kilat Print products only. The
 * prices are server-side estimates, the photos are the supplied product photos,
 * and the demo catalog must never leak back into public listings.
 */
class KilatPrintCatalogTest extends TestCase
{
    /** @var array<string, array{name:string,category:string,base_price:int,price_display:string}> */
    private const PRODUCTS = [
        'kertas-kado-custom' => [
            'name' => 'Kertas Kado Custom',
            'category' => 'Printing Custom',
            'base_price' => 15000,
            'price_display' => 'starting',
        ],
        'paper-bag-custom' => [
            'name' => 'Paper Bag Custom',
            'category' => 'Printing Custom',
            'base_price' => 19500,
            'price_display' => 'starting',
        ],
        'gelas-mug-custom' => [
            'name' => 'Gelas Mug Custom',
            'category' => 'Produk Custom',
            'base_price' => 29500,
            'price_display' => 'per_unit',
        ],
        'topi-ultah-custom' => [
            'name' => 'Topi Ultah Custom',
            'category' => 'Produk Custom',
            'base_price' => 12900,
            'price_display' => 'per_unit',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RealCatalogSeeder::class);
    }

    public function test_only_the_four_real_products_are_active(): void
    {
        $this->assertSame(
            ['Gelas Mug Custom', 'Kertas Kado Custom', 'Paper Bag Custom', 'Topi Ultah Custom'],
            Product::active()->orderBy('name')->pluck('name')->all(),
        );

        $this->assertSame(
            ['printing-custom', 'produk-custom'],
            Category::active()->orderBy('slug')->pluck('slug')->all(),
        );
    }

    public function test_products_have_the_expected_category_price_and_label(): void
    {
        foreach (self::PRODUCTS as $slug => $expected) {
            $product = Product::with('category')->where('slug', $slug)->first();

            $this->assertNotNull($product, "Produk {$expected['name']} tidak ditemukan.");
            $this->assertSame($expected['name'], $product->name);
            $this->assertSame($expected['category'], $product->category->name);
            $this->assertSame('active', $product->status);
            $this->assertSame($expected['base_price'], (int) $product->base_price);
            $this->assertSame($expected['price_display'], $product->price_display);
            $this->assertSame(1, (int) $product->minimum_order);
            $this->assertNotEmpty($product->description);
            $this->assertNotEmpty($product->specifications);
        }
    }

    public function test_each_product_uses_its_supplied_photo(): void
    {
        foreach (self::PRODUCTS as $slug => $expected) {
            $product = Product::where('slug', $slug)->firstOrFail();

            $this->assertSame('images/products/'.$slug.'.jpeg', $product->thumbnail);
            $this->assertSame('images/products/'.$slug.'.jpeg', $product->front_mockup);
            $this->assertFileExists(public_path($product->thumbnail), "Foto {$expected['name']} tidak ada.");
        }

        $this->assertCount(
            4,
            collect(self::PRODUCTS)->keys()->map(fn (string $slug) => Product::where('slug', $slug)->value('thumbnail'))->unique(),
        );
    }

    public function test_paper_bag_and_topi_variations_are_complete_and_required(): void
    {
        $paperBag = Product::where('slug', 'paper-bag-custom')->firstOrFail();
        $paperBagVariation = $paperBag->variationDefinition();

        $this->assertNotNull($paperBagVariation);
        $this->assertSame('size', $paperBagVariation['key']);
        $this->assertSame('Ukuran', $paperBagVariation['label']);
        $this->assertTrue($paperBagVariation['required']);
        $this->assertSame(
            ['small', 'medium', 'large', 'large-x2'],
            array_column($paperBagVariation['options'], 'value'),
        );
        $this->assertSame('Large X2', $paperBag->variantLabel('large-x2'));

        $topi = Product::where('slug', 'topi-ultah-custom')->firstOrFail();
        $topiVariation = $topi->variationDefinition();

        $this->assertNotNull($topiVariation);
        $this->assertSame('model', $topiVariation['key']);
        $this->assertSame('Model', $topiVariation['label']);
        $this->assertSame(
            ['love', 'polkadot', 'muka-polos', 'bintang'],
            array_column($topiVariation['options'], 'value'),
        );
        $this->assertSame('Muka Polos', $topi->variantLabel('muka-polos'));
    }

    public function test_paper_bag_tiers_mug_price_and_topi_wholesale_match_business_decisions(): void
    {
        $paperBag = Product::where('slug', 'paper-bag-custom')->firstOrFail();
        $tiers = $paperBag->priceRules()
            ->where('active', true)
            ->where('is_wholesale', false)
            ->pluck('price', 'variant_key')
            ->map(fn ($price) => (int) $price)
            ->all();

        $this->assertSame([
            'small' => 19500,
            'medium' => 24500,
            'large' => 32000,
            'large-x2' => 39500,
        ], $tiers);

        $this->assertTrue(
            $paperBag->priceRules()
                ->where('is_wholesale', false)
                ->get()
                ->every(fn ($rule) => str_contains((string) $rule->description, 'Harga final')),
            'Semua harga Paper Bag harus ditandai final, bukan estimasi.',
        );

        $mug = Product::where('slug', 'gelas-mug-custom')->firstOrFail();
        $mugRule = $mug->priceRules()->where('catalog_key', 'gelas-mug-custom-standard')->firstOrFail();

        $this->assertSame(29500, (int) $mugRule->price);
        $this->assertTrue($mugRule->active);
        $this->assertFalse($mugRule->is_wholesale);

        $topi = Product::where('slug', 'topi-ultah-custom')->firstOrFail();
        $retail = $topi->priceRules()->where('catalog_key', 'topi-ultah-custom-retail')->firstOrFail();
        $wholesale = $topi->priceRules()->where('catalog_key', 'topi-ultah-custom-wholesale')->firstOrFail();

        $this->assertSame(12900, (int) $retail->price);
        $this->assertTrue($retail->active);
        $this->assertFalse($retail->is_wholesale);

        $this->assertSame(10900, (int) $wholesale->price);
        $this->assertFalse($wholesale->active);
        $this->assertTrue($wholesale->is_wholesale);
        $this->assertSame(2, (int) $wholesale->min_quantity);
    }

    public function test_public_catalog_lists_only_the_real_products(): void
    {
        $response = $this->get(route('products.index'));

        $response->assertOk();

        foreach (self::PRODUCTS as $expected) {
            $response->assertSee($expected['name']);
        }

        foreach (['Bando Tuning Custom', 'Apotek Mini', 'Topper Cake', 'Topeng Muka'] as $demoName) {
            $response->assertDontSee($demoName);
        }
    }

    public static function searchProvider(): array
    {
        return [
            ['kertas', 'kertas-kado-custom'],
            ['paper bag', 'paper-bag-custom'],
            ['mug', 'gelas-mug-custom'],
            ['topi', 'topi-ultah-custom'],
        ];
    }

    #[DataProvider('searchProvider')]
    public function test_catalog_search_finds_every_real_product(string $term, string $slug): void
    {
        $response = $this->get(route('products.index', ['search' => $term]));

        $response->assertOk();

        $results = $response->viewData('products');
        $this->assertTrue($results->contains('slug', $slug), "Pencarian '{$term}' tidak menemukan {$slug}.");
    }

    public function test_catalog_search_does_not_find_retired_demo_products(): void
    {
        foreach (['bando', 'apotek', 'topper', 'topeng'] as $term) {
            $response = $this->get(route('products.index', ['search' => $term]));

            $response->assertOk();
            $this->assertCount(0, $response->viewData('products'), "Pencarian '{$term}' masih menemukan produk demo.");
        }
    }

    public function test_homepage_highlights_the_four_real_products(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();

        foreach (self::PRODUCTS as $expected) {
            $response->assertSee($expected['name']);
        }

        $response->assertSee('Printing Custom');
        $response->assertSee('Produk Custom');
        $response->assertDontSee('Bando Tuning Custom');
        $response->assertDontSee('Apotek Mini');
    }

    public function test_product_detail_shows_confirmed_mug_specifications_and_price_label(): void
    {
        $mug = Product::where('slug', 'gelas-mug-custom')->firstOrFail();

        $response = $this->get(route('products.show', $mug));

        $response->assertOk()
            ->assertSee('Gelas Mug Custom')
            ->assertSee('Harga per unit')
            ->assertSee('11 oz / 325 ml')
            ->assertSee('9,5 × 20 cm')
            ->assertSee('Gratis box dan bubble wrap')
            ->assertSee('gelas-mug-custom.jpeg');
    }

    public function test_kertas_kado_offers_hvs_b_and_artpaper_at_a_starting_price(): void
    {
        $kertasKado = Product::where('slug', 'kertas-kado-custom')->firstOrFail();
        $variation = $kertasKado->variationDefinition();

        $this->assertNotNull($variation);
        $this->assertSame('material', $variation['key']);
        $this->assertSame('Bahan', $variation['label']);
        $this->assertTrue($variation['required']);
        $this->assertSame(['hvs-b', 'artpaper'], array_column($variation['options'], 'value'));
        $this->assertSame('HVS B', $kertasKado->variantLabel('hvs-b'));
        $this->assertSame('ArtPaper', $kertasKado->variantLabel('artpaper'));

        $response = $this->get(route('products.show', $kertasKado));

        $response->assertOk()
            ->assertSee('Mulai dari')
            ->assertSee('Kertas Kado Custom')
            ->assertSee('HVS B')
            ->assertSee('ArtPaper');
    }

    public function test_detail_pages_render_variation_controls_for_paper_bag_and_topi(): void
    {
        $paperBag = Product::where('slug', 'paper-bag-custom')->firstOrFail();
        $this->get(route('products.show', $paperBag))
            ->assertOk()
            ->assertSee('name="variant"', false)
            ->assertSee('Large X2')
            ->assertSee('Mulai dari');

        $topi = Product::where('slug', 'topi-ultah-custom')->firstOrFail();
        $this->get(route('products.show', $topi))
            ->assertOk()
            ->assertSee('name="variant"', false)
            ->assertSee('Polkadot')
            ->assertSee('Muka Polos')
            ->assertSee('Bintang')
            ->assertSee('Harga per unit');
    }

    public function test_admin_product_form_exposes_variation_and_price_fields(): void
    {
        $admin = $this->makeAdmin();
        $paperBag = Product::where('slug', 'paper-bag-custom')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.products.edit', $paperBag))
            ->assertOk()
            ->assertSee('Varian produk')
            ->assertSee('small|Small')
            ->assertSee('price_display')
            ->assertSee('base_price');
    }
}
