<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterDataRequest;
use App\Models\Category;
use App\Models\Finishing;
use App\Models\Material;
use App\Models\PriceRule;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MasterDataController extends Controller
{
    private const TYPES = [
        'products' => ['label' => 'Produk', 'model' => Product::class, 'icon' => 'P'],
        'categories' => ['label' => 'Kategori', 'model' => Category::class, 'icon' => 'K'],
        'materials' => ['label' => 'Material', 'model' => Material::class, 'icon' => 'M'],
        'finishings' => ['label' => 'Finishing', 'model' => Finishing::class, 'icon' => 'F'],
    ];

    public function index(Request $request, string $resourceType): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
        $meta = $this->meta($resourceType);
        $query = $meta['model']::query();
        if ($resourceType === 'products') {
            $query->with(['category', 'priceRules']);
        }
        if ($search = $request->string('search')->trim()->value()) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return view('admin.resources.index', [
            'resourceType' => $resourceType,
            'title' => $meta['label'],
            'records' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(string $resourceType): View
    {
        return $this->formView($resourceType, null);
    }

    public function store(MasterDataRequest $request, string $resourceType): RedirectResponse
    {
        $meta = $this->meta($resourceType);
        $data = $request->validated();
        unset($data['resourceType']);

        if (in_array($resourceType, ['products', 'categories', 'materials', 'finishings'], true)) {
            $data['slug'] = $this->uniqueSlug($meta['model'], ($data['slug'] ?? null) ?: $data['name']);
        }
        $replacedImages = $this->storeImages($request, $data, $resourceType, null);
        if ($resourceType === 'products') {
            $data['specifications'] = $this->cleanSpecifications($data['specifications'] ?? []);
            $data['variations'] = $this->normalizeVariations($data['variations'] ?? null);
            $data['price_display'] = $data['price_display'] ?? 'starting';
        }

        $record = $meta['model']::create($data);
        $this->deleteReplacedImages($replacedImages, $data);
        $this->syncOptions($record, $resourceType, $data);

        if ($record instanceof Product) {
            $this->syncDefaultPriceRule($record, $data['base_price'] ?? null);
        }

        return redirect()->route("admin.{$resourceType}.index")->with('success', "{$meta['label']} berhasil ditambahkan.");
    }

    public function edit(string|int $record, string $resourceType): View
    {
        $model = $this->findRecord($resourceType, $record, true);
        if ($resourceType === 'products') {
            $model->load(['materials', 'finishings']);
        }

        return $this->formView($resourceType, $model);
    }

    private function formView(string $resourceType, ?Model $record): View
    {
        $isProduct = $resourceType === 'products';

        return view('admin.resources.form', [
            'resourceType' => $resourceType,
            'title' => $this->meta($resourceType)['label'],
            'record' => $record,
            'categories' => $isProduct ? Category::where('status', 'active')->orderBy('name')->get() : collect(),
            'materials' => $isProduct ? Material::where('status', 'active')->orderBy('name')->get() : collect(),
            'finishings' => $isProduct ? Finishing::where('status', 'active')->orderBy('name')->get() : collect(),
            'pricingTypes' => PricingType::cases(),
        ]);
    }

    public function update(MasterDataRequest $request, string|int $record, string $resourceType): RedirectResponse
    {
        $meta = $this->meta($resourceType);
        $model = $this->findRecord($resourceType, $record);
        $data = $request->validated();
        if (in_array($resourceType, ['products', 'categories', 'materials', 'finishings'], true)) {
            $data['slug'] = $this->uniqueSlug($meta['model'], ($data['slug'] ?? null) ?: $model->slug, $model->getKey());
        }
        if ($resourceType === 'products') {
            $data['is_featured'] = $request->boolean('is_featured');
            if (array_key_exists('specifications', $data)) {
                $data['specifications'] = $this->cleanSpecifications($data['specifications']);
            }
            if (array_key_exists('variations', $data)) {
                $data['variations'] = $this->normalizeVariations($data['variations']);
            }
            if (array_key_exists('price_display', $data) && $data['price_display'] === null) {
                $data['price_display'] = 'starting';
            }
        }
        $replacedImages = $this->storeImages($request, $data, $resourceType, $model);
        $model->update($data);
        $this->deleteReplacedImages($replacedImages, $data);
        $this->syncOptions($model, $resourceType, $data);

        if ($model instanceof Product && array_key_exists('base_price', $data)) {
            $this->syncDefaultPriceRule($model, $data['base_price']);
        }

        return redirect()->route("admin.{$resourceType}.index")->with('success', "{$meta['label']} berhasil diperbarui.");
    }

    public function destroy(string|int $record, string $resourceType): RedirectResponse
    {
        $meta = $this->meta($resourceType);
        $model = $this->findRecord($resourceType, $record);

        // A removed product must not leave an active tariff behind. The rules are
        // deactivated, never deleted, so historical snapshots stay readable.
        if ($model instanceof Product) {
            $model->priceRules()->update(['active' => false]);
        }

        $model->delete();

        return back()->with('success', "{$meta['label']} dinonaktifkan. Data transaksi historis tetap aman.");
    }

    /**
     * @param  array<string, mixed>|null  $variations
     * @return array{key:string,label:string,required:bool,options:list<array{value:string,label:string}>}|null
     */
    private function normalizeVariations(?array $variations): ?array
    {
        if (! $variations) {
            return null;
        }

        $key = trim((string) ($variations['key'] ?? ''));
        $options = collect($variations['options'] ?? [])
            ->filter(fn ($option) => is_array($option) && filled($option['value'] ?? null))
            ->map(fn (array $option) => [
                'value' => (string) $option['value'],
                'label' => (string) ($option['label'] ?? $option['value']),
            ])
            ->unique('value')
            ->values()
            ->all();

        if ($key === '' || $options === []) {
            return null;
        }

        return [
            'key' => $key,
            'label' => filled($variations['label'] ?? null) ? (string) $variations['label'] : ucfirst($key),
            'required' => (bool) ($variations['required'] ?? true),
            'options' => $options,
        ];
    }

    /**
     * Keep one generic per-item tariff in sync with the product base price.
     * Variant-priced products keep their own rules untouched.
     */
    private function syncDefaultPriceRule(Product $product, mixed $basePrice): void
    {
        if ($basePrice === null || $basePrice === '') {
            return;
        }

        if ($product->priceRules()->whereNotNull('variant_key')->exists()) {
            return;
        }

        $rule = $product->priceRules()
            ->whereNull('variant_key')
            ->where('is_wholesale', false)
            ->orderBy('id')
            ->first();

        if ($rule) {
            $rule->update(['price' => $basePrice]);

            return;
        }

        PriceRule::create([
            'product_id' => $product->id,
            'name' => 'Harga produk',
            'pricing_type' => PricingType::PER_ITEM,
            'price' => $basePrice,
            'min_quantity' => 1,
            'active' => true,
            'is_wholesale' => false,
            'description' => 'Aturan harga default dari harga dasar produk.',
        ]);
    }

    private function meta(string $type): array
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        return self::TYPES[$type];
    }

    private function findRecord(string $type, string|int $record, bool $withTrashed = false): Model
    {
        $class = self::TYPES[$type]['model'];
        $query = $withTrashed ? $class::withTrashed() : $class::query();

        return ctype_digit((string) $record)
            ? $query->whereKey((int) $record)->firstOrFail()
            : $query->where('slug', $record)->firstOrFail();
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function uniqueSlug(string $modelClass, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while ($modelClass::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Store replacement images and return the old paths. Old files are deleted
     * only after the database write succeeds, so a failed save never leaves a
     * record pointing at a file that no longer exists.
     *
     * @return list<string>
     */
    private function storeImages(Request $request, array &$data, string $type, ?Model $record): array
    {
        $replaced = [];

        foreach (['thumbnail' => 'thumbnails', 'image' => 'categories', 'front_mockup' => 'product-mockups', 'back_mockup' => 'product-mockups'] as $field => $directory) {
            if (! $request->hasFile($field)) {
                continue;
            }
            if ($record?->{$field}) {
                $replaced[] = (string) $record->{$field};
            }
            $data[$field] = $request->file($field)->store($directory, 'public');
        }

        return $replaced;
    }

    /** @param  list<string>  $paths */
    private function deleteReplacedImages(array $paths, array $current): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            if (! in_array($path, $current, true)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /** @return array<string, string> */
    private function cleanSpecifications(mixed $specifications): array
    {
        if (! is_array($specifications)) {
            return [];
        }

        return array_filter(
            $specifications,
            fn ($value) => is_scalar($value) && trim((string) $value) !== '',
        );
    }

    private function syncOptions(Model $record, string $type, array $data): void
    {
        if ($type !== 'products') {
            return;
        }
        $record->materials()->sync($data['materials'] ?? []);
        $record->finishings()->sync($data['finishings'] ?? []);
    }
}
