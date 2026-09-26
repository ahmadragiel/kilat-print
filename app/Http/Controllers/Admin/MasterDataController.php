<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterDataRequest;
use App\Models\Category;
use App\Models\Finishing;
use App\Models\Material;
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
        return view('admin.resources.form', [
            'resourceType' => $resourceType,
            'title' => $this->meta($resourceType)['label'],
            'record' => null,
            'categories' => $resourceType === 'products' ? Category::where('status', 'active')->orderBy('name')->get() : collect(),
            'materials' => $resourceType === 'products' ? Material::where('status', 'active')->orderBy('name')->get() : collect(),
            'finishings' => $resourceType === 'products' ? Finishing::where('status', 'active')->orderBy('name')->get() : collect(),
            'pricingTypes' => PricingType::cases(),
        ]);
    }

    public function store(MasterDataRequest $request, string $resourceType): RedirectResponse
    {
        $meta = $this->meta($resourceType);
        $data = $request->validated();
        unset($data['resourceType']);

        if (in_array($resourceType, ['products', 'categories', 'materials', 'finishings'], true)) {
            $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        }
        $this->storeImages($request, $data, $resourceType, null);
        if ($resourceType === 'products') {
            $data['specifications'] = array_filter($data['specifications'] ?? [], fn ($value) => $value !== null && $value !== '');
        }

        $record = $meta['model']::create($data);
        $this->syncOptions($record, $resourceType, $data);

        return redirect()->route("admin.{$resourceType}.index")->with('success', "{$meta['label']} berhasil ditambahkan.");
    }

    public function edit(string|int $record, string $resourceType): View
    {
        $meta = $this->meta($resourceType);
        $model = $this->findRecord($resourceType, $record, true);
        if ($resourceType === 'products') {
            $model->load(['materials', 'finishings']);
        }

        return view('admin.resources.form', [
            'resourceType' => $resourceType,
            'title' => $meta['label'],
            'record' => $model,
            'categories' => $resourceType === 'products' ? Category::where('status', 'active')->orderBy('name')->get() : collect(),
            'materials' => $resourceType === 'products' ? Material::where('status', 'active')->orderBy('name')->get() : collect(),
            'finishings' => $resourceType === 'products' ? Finishing::where('status', 'active')->orderBy('name')->get() : collect(),
            'pricingTypes' => PricingType::cases(),
        ]);
    }

    public function update(MasterDataRequest $request, string|int $record, string $resourceType): RedirectResponse
    {
        $meta = $this->meta($resourceType);
        $model = $this->findRecord($resourceType, $record);
        $data = $request->validated();
        if (in_array($resourceType, ['products', 'categories', 'materials', 'finishings'], true)) {
            $data['slug'] = ($data['slug'] ?? null) ?: $model->slug;
        }
        $this->storeImages($request, $data, $resourceType, $model);
        $model->update($data);
        $this->syncOptions($model, $resourceType, $data);

        return redirect()->route("admin.{$resourceType}.index")->with('success', "{$meta['label']} berhasil diperbarui.");
    }

    public function destroy(string|int $record, string $resourceType): RedirectResponse
    {
        $meta = $this->meta($resourceType);
        $model = $this->findRecord($resourceType, $record);
        $model->delete();

        return back()->with('success', "{$meta['label']} dinonaktifkan. Data transaksi historis tetap aman.");
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

    private function storeImages(Request $request, array &$data, string $type, ?Model $record): void
    {
        foreach (['thumbnail' => 'thumbnails', 'image' => 'categories', 'front_mockup' => 'product-mockups', 'back_mockup' => 'product-mockups'] as $field => $directory) {
            if (! $request->hasFile($field)) {
                continue;
            }
            if ($record?->{$field}) {
                Storage::disk('public')->delete($record->{$field});
            }
            $data[$field] = $request->file($field)->store($directory, 'public');
        }
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
