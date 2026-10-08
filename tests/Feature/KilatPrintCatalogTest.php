<?php

namespace Tests\Feature;

use App\Enums\PricingType;
use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Services\PriceCalculationService;
use Database\Seeders\DemoDataSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Katalog Kilat Print harus mengikuti produk yang disebut pada laporan Kerja Praktek.
 * Test ini memverifikasi master data seed, integrasi kalkulator harga, Design Editor,
 * dan alur pemesanan untuk produk katalog laporan.
 */
class KilatPrintCatalogTest extends TestCase
{
    /** Produk katalog Kilat Print beserta kategori menurut laporan KP. */
    private const REPORT_PRODUCTS = [
        'mug-custom' => ['Mug Custom', 'Produk Custom'],
        'bando-tuning-custom' => ['Bando Tuning Custom', 'Produk Custom'],
        'paper-bag-custom' => ['Paper Bag Custom', 'Printing Custom'],
        'kertas-kado' => ['Kertas Kado', 'Printing Custom'],
        'apotek-mini' => ['Apotek Mini', 'Printing Custom'],
        'topper-cake' => ['Topper Cake', 'Produk Custom'],
        'topeng-muka' => ['Topeng Muka', 'Produk Custom'],
    ];

    /** Produk generik percetakan lama yang tidak ada di laporan KP. */
    private const LEGACY_PRODUCT_SLUGS = [
        'banner',
        'spanduk',
        'brosur',
        'flyer',
        'poster',
        'stiker',
        'kartu-nama',
        'undangan',
        'nota',
        'kop-surat',
    ];

    private const LEGACY_CATEGORY_SLUGS = [
        'banner',
        'brochure-flyer',
        'poster-sticker',
        'stationery',
        'invitation-event',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoDataSeeder::class);
    }

    // -----------------------------------------------------------------
    // Master data katalog
    // -----------------------------------------------------------------

    public function test_catalog_contains_every_product_named_in_the_kp_report(): void
    {
        foreach (self::REPORT_PRODUCTS as $slug => [$name, $categoryName]) {
            $product = Product::with('category')->where('slug', $slug)->first();

            $this->assertNotNull($product, "Produk {$name} ({$slug}) tidak ada di katalog.");
            $this->assertSame($name, $product->name);
            $this->assertSame($categoryName, $product->category->name);
            $this->assertSame('active', $product->status);
            $this->assertGreaterThan(0, (float) $product->base_price);
            $this->assertGreaterThanOrEqual(1, (int) $product->minimum_order);
            $this->assertGreaterThanOrEqual(1, (int) $product->production_days);
            $this->assertNotEmpty($product->description);
        }
    }

    public function test_catalog_hides_legacy_generic_printing_products_and_categories(): void
    {
        $this->assertSame(0, Product::withTrashed()->whereIn('slug', self::LEGACY_PRODUCT_SLUGS)->count());
        $this->assertSame(0, Category::withTrashed()->whereIn('slug', self::LEGACY_CATEGORY_SLUGS)->count());

        $activeSlugs = Product::active()->pluck('slug')->all();
        foreach (self::LEGACY_PRODUCT_SLUGS as $legacy) {
            $this->assertNotContains($legacy, $activeSlugs);
        }

        $this->assertSame(
            ['printing-custom', 'produk-custom'],
            Category::active()->orderBy('slug')->pluck('slug')->all(),
        );
    }

    public function test_each_product_uses_its_own_illustration_instead_of_the_shared_mockup(): void
    {
        foreach (self::REPORT_PRODUCTS as $slug => [$name]) {
            $product = Product::where('slug', $slug)->firstOrFail();

            $this->assertSame('images/products/'.$slug.'.svg', $product->thumbnail, $name.' memakai thumbnail generik.');
            $this->assertSame('images/products/'.$slug.'.svg', $product->front_mockup);
            $this->assertFileExists(public_path($product->thumbnail));
            $this->assertFileExists(public_path($product->front_mockup));
        }

        $this->assertNotSame(
            Product::where('slug', 'mug-custom')->value('thumbnail'),
            Product::where('slug', 'paper-bag-custom')->value('thumbnail'),
        );
    }

    // -----------------------------------------------------------------
    // Material, finishing, dan harga
    // -----------------------------------------------------------------

    public function test_materials_and_finishings_are_relevant_and_free_of_legacy_banner_data(): void
    {
        $this->assertSame(
            ['Bahan Bando', 'Bahan Mug', 'Kertas Cetak', 'Kertas Kraft'],
            Material::active()->orderBy('name')->pluck('name')->all(),
        );

        $this->assertSame(
            ['Glossy', 'Laminasi', 'Matte'],
            \App\Models\Finishing::active()->orderBy('name')->pluck('name')->all(),
        );

        $mug = Product::with(['materials', 'finishings'])->where('slug', 'mug-custom')->firstOrFail();

        $this->assertTrue($mug->materials->contains('name', 'Bahan Mug'));
        $this->assertTrue($mug->finishings->contains('name', 'Matte'));
        $this->assertFalse($mug->finishings->contains('name', 'Mata Ayam'));
        $this->assertFalse($mug->materials->contains('name', 'Flexi 280gr'));
    }

    public function test_every_product_has_a_per_item_price_rule_and_a_calculable_estimate(): void
    {
        $calculator = app(PriceCalculationService::class);

        foreach (Product::active()->with('priceRules')->get() as $product) {
            $rules = $product->priceRules()->where('active', true)->get();

            $this->assertNotEmpty($rules, "Produk {$product->slug} tidak memiliki aturan harga aktif.");

            foreach ($rules as $rule) {
                $this->assertSame(
                    PricingType::PerItem,
                    $rule->pricing_type,
                    "Produk {$product->slug} memakai rumus harga {$rule->pricing_type->value}.",
                );
                $this->assertGreaterThan(0, (float) $rule->price);
            }

            $calculation = $calculator->calculate($product, [
                'quantity' => max(1, (int) $product->minimum_order),
                'material_id' => $product->materials()->value('materials.id'),
                'finishing_id' => $product->finishings()->value('finishings.id'),
            ]);

            $this->assertGreaterThan(0, $calculation['total']);
            $this->assertSame(0.0, $calculation['dimensions']['area_sqm']);
        }
    }

    public function test_price_endpoint_returns_an_estimate_for_a_report_product(): void
    {
        $customer = $this->makeCustomer();
        $mug = Product::where('slug', 'mug-custom')->firstOrFail();

        $response = $this->actingAs($customer->user)->getJson(route('products.price', [
            'product' => $mug->slug,
            'quantity' => 3,
        ]));

        $response->assertOk()
            ->assertJsonPath('quantity', 3)
            ->assertJsonPath('unit_price', 25000)
            ->assertJsonPath('total', 75000);
    }

    // -----------------------------------------------------------------
    // Katalog publik, pencarian, dan homepage
    // -----------------------------------------------------------------

    public function test_public_catalog_lists_the_report_products_and_no_legacy_product(): void
    {
        $response = $this->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('Mug Custom');
        $response->assertSee('Bando Tuning Custom');
        $response->assertSee('Paper Bag Custom');
        $response->assertSee('Kertas Kado');
        $response->assertSee('Apotek Mini');
        $response->assertSee('Topper Cake');
        $response->assertSee('Topeng Muka');

        foreach (self::LEGACY_PRODUCT_SLUGS as $legacy) {
            $this->assertStringNotContainsString(
                '/products/'.$legacy.'"',
                $response->getContent(),
                "Katalog masih menautkan produk lama {$legacy}.",
            );
        }
    }

    public function test_homepage_highlights_the_three_main_report_products(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Mug Custom');
        $response->assertSee('Bando Tuning Custom');
        $response->assertSee('Paper Bag Custom');
        $response->assertSee('Produk Custom');
        $response->assertSee('Printing Custom');
    }

    public static function searchProvider(): array
    {
        return [
            ['mug', 'mug-custom'],
            ['bando', 'bando-tuning-custom'],
            ['paper bag', 'paper-bag-custom'],
            ['kertas kado', 'kertas-kado'],
            ['apotek', 'apotek-mini'],
            ['topper', 'topper-cake'],
            ['topeng', 'topeng-muka'],
        ];
    }

    #[DataProvider('searchProvider')]
    public function test_catalog_search_finds_every_report_product(string $term, string $slug): void
    {
        $response = $this->get(route('products.index', ['search' => $term]));

        $response->assertOk();
        $results = $response->viewData('products');

        $this->assertTrue(
            $results->contains('slug', $slug),
            "Pencarian '{$term}' tidak menemukan {$slug}.",
        );
    }

    public function test_product_detail_never_shows_legacy_banner_specifications(): void
    {
        $mug = Product::where('slug', 'mug-custom')->firstOrFail();

        $response = $this->get(route('products.show', $mug));

        $response->assertOk();
        $response->assertSee('Mug Custom');
        $response->assertSee('images/products/mug-custom.svg');
        $response->assertDontSee('Flexi 280gr');
        $response->assertDontSee('Flexi 340gr');
        $response->assertDontSee('Mata Ayam');
    }

    // -----------------------------------------------------------------
    // Master data katalog
    // -----------------------------------------------------------------

    public function test_admin_sees_the_same_kilat_print_catalog_and_master_data(): void
    {
        $admin = $this->makeAdmin();
        $mug = Product::where('slug', 'mug-custom')->firstOrFail();

        $products = $this->actingAs($admin)->get(route('admin.products.index'));
        $products->assertOk();
        foreach (self::REPORT_PRODUCTS as [$name]) {
            $products->assertSee($name);
        }
        $products->assertDontSee('Flexi 280gr');
        $products->assertDontSee('Mata Ayam');

        $this->actingAs($admin)->get(route('admin.products.edit', $mug))
            ->assertOk()
            ->assertSee('images/products/mug-custom.svg', false);

        $this->actingAs($admin)->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Produk Custom')
            ->assertSee('Printing Custom')
            ->assertDontSee('Stationery');

        $this->actingAs($admin)->get(route('admin.materials.index'))
            ->assertOk()
            ->assertSee('Bahan Mug')
            ->assertSee('Bahan Bando')
            ->assertDontSee('Flexi');

        $this->actingAs($admin)->get(route('admin.finishings.index'))
            ->assertOk()
            ->assertSee('Matte')
            ->assertSee('Laminasi')
            ->assertDontSee('Mata Ayam');

        $this->actingAs($admin)->get(route('admin.prices.index'))
            ->assertOk()
            ->assertSee('Harga demo per item');
    }

    // -----------------------------------------------------------------
    // Design Editor + alur pemesanan
    // -----------------------------------------------------------------

    public function test_customer_can_customize_and_order_mug_custom_end_to_end(): void
    {
        $customer = $this->makeCustomer();
        $mug = Product::where('slug', 'mug-custom')->firstOrFail();

        $draft = $this->actingAs($customer->user)->postJson(route('custom-designs.store'), [
            'product_id' => $mug->id,
            'specification' => [
                'quantity' => 2,
                'production_method' => 'digital',
            ],
            'design' => [
                'front' => ['elements' => [[
                    'id' => 'el_text_1',
                    'type' => 'text',
                    'text' => 'Kilat Print',
                    'x' => 120.0,
                    'y' => 90.0,
                    'width' => 220.0,
                    'height' => 60.0,
                    'side' => 'front',
                    'layer' => 0,
                    'visible' => true,
                    'opacity' => 1.0,
                    'font_size' => 32.0,
                    'font_family' => 'Arial',
                    'font_weight' => 'bold',
                    'font_style' => 'normal',
                    'text_align' => 'center',
                    'fill' => '#111827',
                ]]],
                'back' => ['elements' => []],
            ],
        ]);

        $draft->assertOk()->assertJsonStructure(['id', 'version', 'design']);
        $draftId = $draft->json('id');

        $editor = $this->actingAs($customer->user)->get(route('products.customize', $mug));
        $editor->assertOk();
        $editor->assertViewHas('hasBackMockup', true);
        $this->assertStringEndsWith(
            'images/products/mug-custom.svg',
            (string) $editor->viewData('mockups')['front'],
        );

        $glossy = $mug->finishings()->where('name', 'Glossy')->value('finishings.id');

        $this->actingAs($customer->user)->post(route('cart.store'), [
            'product_id' => $mug->id,
            'quantity' => 2,
            'production_method' => 'digital',
            'material_id' => $mug->materials()->value('materials.id'),
            'finishing_id' => $glossy,
            'design_draft_id' => $draftId,
        ])->assertRedirect(route('cart.index'));

        $cartItem = $customer->cart->items()->sole();

        $this->assertSame($mug->id, $cartItem->product_id);
        $this->assertSame($draftId, $cartItem->custom_design_draft_id);
        $this->assertSame(2 * 25000 + 2 * 3000, (int) $cartItem->price_at_addition);

        $this->actingAs($customer->user)->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Mug Custom');

        $this->actingAs($customer->user)->post(route('checkout.store'), [
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'pickup',
            'recipient' => $customer->user->name,
            'address_phone' => '081200000001',
            'address' => 'Jalan Kilat No. 1',
            'district' => 'Nginden',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'postal_code' => '60111',
        ])->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $item = $order->items()->sole();

        $this->assertSame('mug-custom', $item->product_slug);
        $this->assertSame('Mug Custom', $item->product_name);
        $this->assertSame($draftId, $item->custom_design_draft_id);
        $this->assertSame(2 * 25000 + 2 * 3000, (int) $item->line_total);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'UNPAID']);
    }
}