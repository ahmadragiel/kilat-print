<?php

namespace Database\Seeders;

use App\Enums\PricingType;
use App\Models\Category;
use App\Models\PriceRule;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the four products that are actually sold: Kertas Kado Custom, Paper Bag
 * Custom, Gelas Mug Custom, and Topi Ultah Custom.
 *
 * The seeder is idempotent and conservative: it creates missing records keyed by
 * `catalog_key`, but it never overwrites fields an admin may have edited. Demo
 * products are deactivated, never deleted, so historical order snapshots stay
 * intact.
 */
class RealCatalogSeeder extends Seeder
{
    /** @var list<string> */
    private const DEMO_SLUGS = [
        'mug-custom',
        'bando-tuning-custom',
        'paper-bag-custom',
        'kertas-kado',
        'apotek-mini',
        'topper-cake',
        'topeng-muka',
    ];

    /**
     * Category descriptions written by the retired demo catalog. They are
     * replaced only when they still match these exact strings, so an admin edit
     * is never overwritten.
     *
     * @var array<string, string>
     */
    private const LEGACY_DEMO_CATEGORY_DESCRIPTIONS = [
        'produk-custom' => 'Produk custom Kilat Print: Mug Custom, Bando Tuning Custom, Topper Cake, dan Topeng Muka.',
        'printing-custom' => 'Printing custom Kilat Print untuk kebutuhan cetak dan kemasan: Paper Bag Custom, Kertas Kado, dan Apotek Mini.',
    ];

    public function run(): void
    {
        $this->retireDemoProducts();

        $printing = $this->category('Printing Custom', 'Printing custom untuk kebutuhan cetak dan kemasan.');
        $custom = $this->category('Produk Custom', 'Produk custom Kilat Print untuk hadiah dan acara.');

        $this->seedKertasKado($printing);
        $this->seedPaperBag($printing);
        $this->seedMug($custom);
        $this->seedTopi($custom);
    }

    private function retireDemoProducts(): void
    {
        $demoProducts = Product::query()
            ->whereNull('catalog_key')
            ->whereIn('slug', self::DEMO_SLUGS)
            ->get();

        foreach ($demoProducts as $product) {
            if ($product->slug === 'paper-bag-custom') {
                $product->slug = $this->uniqueSlug('demo-paper-bag-custom', $product->id);
            }

            $product->forceFill([
                'status' => 'inactive',
                'is_featured' => false,
                'slug' => $product->slug,
            ])->save();

            $product->priceRules()->update(['active' => false]);
        }
    }

    private function category(string $name, string $description): Category
    {
        $category = Category::firstOrCreate(
            ['slug' => Str::slug($name)],
            [
                'name' => $name,
                'description' => $description,
                'status' => 'active',
            ],
        );

        $legacyDescription = self::LEGACY_DEMO_CATEGORY_DESCRIPTIONS[$category->slug] ?? null;

        if ($legacyDescription !== null && $category->description === $legacyDescription) {
            $category->forceFill(['description' => $description])->save();
        }

        return $category;
    }

    private function seedKertasKado(Category $category): void
    {
        $product = $this->product('kertas-kado-custom', [
            'category_id' => $category->id,
            'name' => 'Kertas Kado Custom',
            'slug' => 'kertas-kado-custom',
            'description' => 'Kertas kado custom dengan desain sesuai kebutuhan Anda. Tersedia bahan HVS B dan ArtPaper, cocok untuk membungkus hadiah dengan tampilan yang lebih personal.',
            'specifications' => [
                'ukuran' => 'A3',
                'bahan' => 'HVS B atau ArtPaper',
            ],
            'variations' => $this->variation('material', 'Bahan', [
                'hvs-b' => 'HVS B',
                'artpaper' => 'ArtPaper',
            ]),
            'thumbnail' => 'images/products/kertas-kado-custom.jpeg',
            'front_mockup' => 'images/products/kertas-kado-custom.jpeg',
            'back_mockup' => null,
            'base_price' => 15000,
            'price_display' => 'starting',
            'minimum_order' => 1,
            'production_days' => 3,
            'is_featured' => true,
        ]);

        if ($product->trashed()) {
            return;
        }

        $this->priceRule($product, 'kertas-kado-custom-standard', [
            'name' => 'Harga standar',
            'pricing_type' => PricingType::PER_ITEM,
            'price' => 15000,
            'min_quantity' => 1,
            'active' => true,
            'is_wholesale' => false,
            'description' => 'Harga mulai dari Rp15.000. Masih estimasi sampai tarif final ditetapkan.',
        ]);
    }

    private function seedPaperBag(Category $category): void
    {
        $product = $this->product('paper-bag-custom', [
            'category_id' => $category->id,
            'name' => 'Paper Bag Custom',
            'slug' => 'paper-bag-custom',
            'description' => 'Paper bag custom dengan cetak desain sesuai kebutuhan. Pilih ukuran yang paling sesuai untuk kemasan produk atau hadiah.',
            'specifications' => [
                'ukuran' => 'Small, Medium, Large, Large X2',
                'catatan' => 'Harga final per ukuran: Small Rp19.500, Medium Rp24.500, Large Rp32.000, Large X2 Rp39.500.',
            ],
            'variations' => $this->variation('size', 'Ukuran', [
                'small' => 'Small',
                'medium' => 'Medium',
                'large' => 'Large',
                'large-x2' => 'Large X2',
            ]),
            'thumbnail' => 'images/products/paper-bag-custom.jpeg',
            'front_mockup' => 'images/products/paper-bag-custom.jpeg',
            'back_mockup' => null,
            'base_price' => 19500,
            'price_display' => 'starting',
            'minimum_order' => 1,
            'production_days' => 4,
            'is_featured' => true,
        ]);

        if ($product->trashed()) {
            return;
        }

        $tiers = [
            'small' => ['Small', 19500],
            'medium' => ['Medium', 24500],
            'large' => ['Large', 32000],
            'large-x2' => ['Large X2', 39500],
        ];

        foreach ($tiers as $key => [$label, $price]) {
            $this->priceRule($product, 'paper-bag-custom-size-'.$key, [
                'name' => 'Harga '.$label,
                'pricing_type' => PricingType::PER_ITEM,
                'variant_key' => $key,
                'price' => $price,
                'min_quantity' => 1,
                'active' => true,
                'is_wholesale' => false,
                'description' => 'Harga final ukuran '.$label.'.',
            ]);
        }
    }

    private function seedMug(Category $category): void
    {
        $product = $this->product('gelas-mug-custom', [
            'category_id' => $category->id,
            'name' => 'Gelas Mug Custom',
            'slug' => 'gelas-mug-custom',
            'description' => 'Mug custom 11 oz (325 ml) dengan permukaan glossy. Area cetak 9,5 × 20 cm. Gratis box dan bubble wrap.',
            'specifications' => [
                'kapasitas' => '11 oz / 325 ml',
                'area_cetak' => '9,5 × 20 cm',
                'finishing' => 'Glossy',
                'packaging' => 'Gratis box dan bubble wrap',
            ],
            'thumbnail' => 'images/products/gelas-mug-custom.jpeg',
            'front_mockup' => 'images/products/gelas-mug-custom.jpeg',
            'back_mockup' => null,
            'base_price' => 29500,
            'price_display' => 'per_unit',
            'minimum_order' => 1,
            'production_days' => 3,
            'is_featured' => true,
        ]);

        if ($product->trashed()) {
            return;
        }

        $this->priceRule($product, 'gelas-mug-custom-standard', [
            'name' => 'Harga satuan',
            'pricing_type' => PricingType::PER_ITEM,
            'price' => 29500,
            'min_quantity' => 1,
            'active' => true,
            'is_wholesale' => false,
            'description' => 'Harga per unit mug 11 oz (325 ml).',
        ]);
    }

    private function seedTopi(Category $category): void
    {
        $product = $this->product('topi-ultah-custom', [
            'category_id' => $category->id,
            'name' => 'Topi Ultah Custom',
            'slug' => 'topi-ultah-custom',
            'description' => 'Topi ulang tahun custom dengan pilihan model Love, Polkadot, Muka Polos, dan Bintang. Bisa custom muka dan teks, serta ganti warna.',
            'specifications' => [
                'model' => 'Love, Polkadot, Muka Polos, Bintang',
                'custom' => 'Muka dan teks',
                'warna' => 'Bisa diganti',
            ],
            'variations' => $this->variation('model', 'Model', [
                'love' => 'Love',
                'polkadot' => 'Polkadot',
                'muka-polos' => 'Muka Polos',
                'bintang' => 'Bintang',
            ]),
            'thumbnail' => 'images/products/topi-ultah-custom.jpeg',
            'front_mockup' => 'images/products/topi-ultah-custom.jpeg',
            'back_mockup' => null,
            'base_price' => 12900,
            'price_display' => 'per_unit',
            'minimum_order' => 1,
            'production_days' => 3,
            'is_featured' => true,
        ]);

        if ($product->trashed()) {
            return;
        }

        $this->priceRule($product, 'topi-ultah-custom-retail', [
            'name' => 'Harga satuan',
            'pricing_type' => PricingType::PER_ITEM,
            'price' => 12900,
            'min_quantity' => 1,
            'active' => true,
            'is_wholesale' => false,
            'description' => 'Harga satuan untuk semua model topi.',
        ]);

        $this->priceRule($product, 'topi-ultah-custom-wholesale', [
            'name' => 'Harga grosir',
            'pricing_type' => PricingType::PER_ITEM,
            'price' => 10900,
            'min_quantity' => 2,
            'active' => false,
            'is_wholesale' => true,
            'description' => 'Harga grosir Rp10.900 nonaktif sampai ambang jumlah minimum ditentukan.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $catalogKey, array $attributes): Product
    {
        $product = Product::withTrashed()->where('catalog_key', $catalogKey)->first();

        if ($product) {
            return $product;
        }

        $product = Product::withTrashed()
            ->whereNull('catalog_key')
            ->where('slug', $attributes['slug'])
            ->first();

        if ($product) {
            $product->forceFill(['catalog_key' => $catalogKey])->save();

            return $product;
        }

        return Product::create($attributes + [
            'catalog_key' => $catalogKey,
            'status' => 'active',
            'popularity_count' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function priceRule(Product $product, string $catalogKey, array $attributes): PriceRule
    {
        $rule = PriceRule::withTrashed()->where('catalog_key', $catalogKey)->first();

        if ($rule) {
            return $rule;
        }

        $rule = PriceRule::withTrashed()
            ->where('product_id', $product->id)
            ->whereNull('catalog_key')
            ->where('name', $attributes['name'])
            ->first();

        if ($rule) {
            $rule->forceFill(['catalog_key' => $catalogKey])->save();

            return $rule;
        }

        return PriceRule::create($attributes + [
            'product_id' => $product->id,
            'catalog_key' => $catalogKey,
        ]);
    }

    /**
     * @param  array<string, string>  $options  value => label
     * @return array{key:string,label:string,required:bool,options:list<array{value:string,label:string}>}
     */
    private function variation(string $key, string $label, array $options): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'required' => true,
            'options' => collect($options)
                ->map(fn (string $optionLabel, string $value) => ['value' => $value, 'label' => $optionLabel])
                ->values()
                ->all(),
        ];
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        $suffix = 2;

        while (Product::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
