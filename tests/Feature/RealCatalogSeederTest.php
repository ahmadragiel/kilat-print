<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PriceRule;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RealCatalogSeeder;
use Tests\TestCase;

/**
 * The real catalog seeder must be safe to run repeatedly. It creates the four
 * real products, retires the demo rows without deleting them, and never
 * overwrites fields an admin has already edited.
 */
class RealCatalogSeederTest extends TestCase
{
    public function test_seeder_creates_exactly_four_products_and_is_idempotent(): void
    {
        $this->seed(RealCatalogSeeder::class);

        $counts = [
            'products' => Product::withTrashed()->count(),
            'price_rules' => PriceRule::withTrashed()->count(),
            'categories' => Category::withTrashed()->count(),
        ];

        $this->assertSame(4, $counts['products']);
        $this->assertSame(8, $counts['price_rules']);
        $this->assertSame(2, $counts['categories']);

        $this->seed(RealCatalogSeeder::class);

        $this->assertSame($counts['products'], Product::withTrashed()->count());
        $this->assertSame($counts['price_rules'], PriceRule::withTrashed()->count());
        $this->assertSame($counts['categories'], Category::withTrashed()->count());

        $this->assertSame(
            ['Gelas Mug Custom', 'Kertas Kado Custom', 'Paper Bag Custom', 'Topi Ultah Custom'],
            Product::active()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_seeder_retires_demo_products_without_deleting_their_rows(): void
    {
        $demoMug = $this->makeProduct(attributes: [
            'name' => 'Mug Custom',
            'slug' => 'mug-custom',
            'is_featured' => true,
        ]);
        $demoPaperBag = $this->makeProduct(attributes: [
            'name' => 'Paper Bag Custom',
            'slug' => 'paper-bag-custom',
            'is_featured' => true,
        ]);
        $demoBando = $this->makeProduct(attributes: [
            'name' => 'Bando Tuning Custom',
            'slug' => 'bando-tuning-custom',
            'is_featured' => true,
        ]);

        $this->seed(RealCatalogSeeder::class);

        foreach ([$demoMug, $demoBando] as $demo) {
            $demo->refresh();

            $this->assertSame('inactive', $demo->status);
            $this->assertFalse($demo->is_featured);
            $this->assertSame(0, $demo->priceRules()->where('active', true)->count());
            $this->assertNotNull(Product::withTrashed()->find($demo->id));
        }

        $demoPaperBag->refresh();

        $this->assertSame('demo-paper-bag-custom', $demoPaperBag->slug);
        $this->assertSame('inactive', $demoPaperBag->status);
        $this->assertFalse($demoPaperBag->is_featured);
        $this->assertSame(0, $demoPaperBag->priceRules()->where('active', true)->count());
        $this->assertNotNull(Product::withTrashed()->find($demoPaperBag->id));

        $realPaperBag = Product::where('slug', 'paper-bag-custom')->firstOrFail();

        $this->assertSame('paper-bag-custom', $realPaperBag->catalog_key);
        $this->assertSame('active', $realPaperBag->status);
        $this->assertNotSame($demoPaperBag->id, $realPaperBag->id);
    }

    public function test_seeder_replaces_legacy_demo_category_descriptions_without_touching_admin_edits(): void
    {
        Category::create([
            'name' => 'Produk Custom',
            'slug' => 'produk-custom',
            'description' => 'Produk custom Kilat Print: Mug Custom, Bando Tuning Custom, Topper Cake, dan Topeng Muka.',
            'status' => 'active',
        ]);
        Category::create([
            'name' => 'Printing Custom',
            'slug' => 'printing-custom',
            'description' => 'Printing custom Kilat Print untuk kebutuhan cetak dan kemasan: Paper Bag Custom, Kertas Kado, dan Apotek Mini.',
            'status' => 'active',
        ]);

        $this->seed(RealCatalogSeeder::class);

        $this->assertSame(
            'Produk custom Kilat Print untuk hadiah dan acara.',
            Category::where('slug', 'produk-custom')->value('description'),
        );
        $this->assertSame(
            'Printing custom untuk kebutuhan cetak dan kemasan.',
            Category::where('slug', 'printing-custom')->value('description'),
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Bando Tuning Custom')
            ->assertDontSee('Apotek Mini')
            ->assertDontSee('Topper Cake')
            ->assertDontSee('Topeng Muka');
    }

    public function test_seeder_does_not_overwrite_admin_edits(): void
    {
        $this->seed(RealCatalogSeeder::class);

        $product = Product::where('catalog_key', 'gelas-mug-custom')->firstOrFail();
        $rule = PriceRule::where('catalog_key', 'gelas-mug-custom-standard')->firstOrFail();

        $product->update([
            'name' => 'Mug Custom Edisi Admin',
            'description' => 'Deskripsi yang diubah admin.',
            'base_price' => 31000,
            'status' => 'inactive',
        ]);
        $rule->update(['price' => 31000]);

        $this->seed(RealCatalogSeeder::class);

        $product->refresh();
        $rule->refresh();

        $this->assertSame('Mug Custom Edisi Admin', $product->name);
        $this->assertSame('Deskripsi yang diubah admin.', $product->description);
        $this->assertSame(31000, (int) $product->base_price);
        $this->assertSame('inactive', $product->status);
        $this->assertSame(31000, (int) $rule->price);
    }

    public function test_seeder_does_not_resurrect_a_soft_deleted_real_product(): void
    {
        $this->seed(RealCatalogSeeder::class);

        $product = Product::where('catalog_key', 'gelas-mug-custom')->firstOrFail();
        $product->delete();

        $this->seed(RealCatalogSeeder::class);

        $trashed = Product::withTrashed()->where('catalog_key', 'gelas-mug-custom')->get();

        $this->assertCount(1, $trashed);
        $this->assertTrue($trashed->first()->trashed());
        $this->assertNull(Product::where('catalog_key', 'gelas-mug-custom')->first());
        $this->assertSame(3, Product::active()->count());
    }

    public function test_database_seeder_keeps_demo_products_inactive(): void
    {
        $demo = $this->makeProduct(attributes: [
            'name' => 'Mug Custom',
            'slug' => 'mug-custom',
            'is_featured' => true,
        ]);

        $this->seed(DatabaseSeeder::class);

        $demo->refresh();

        $this->assertSame('inactive', $demo->status);
        $this->assertFalse($demo->is_featured);
        $this->assertSame(4, Product::active()->count());
        $this->assertSame(
            ['Gelas Mug Custom', 'Kertas Kado Custom', 'Paper Bag Custom', 'Topi Ultah Custom'],
            Product::active()->orderBy('name')->pluck('name')->all(),
        );
    }
}
