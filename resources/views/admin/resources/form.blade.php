@extends('layouts.admin')

@php
    $resourceType = $resourceType ?? request()->route('resourceType') ?? 'products';
    $record = $record ?? $resource ?? null;
    $resourceLabels = ['products' => 'Produk', 'categories' => 'Kategori', 'materials' => 'Material', 'finishings' => 'Finishing'];
    $resourceLabel = $title ?? ($resourceLabels[$resourceType] ?? ucfirst($resourceType));
    $editing = (bool) $record;
    $formAction = $editing
        ? (\Illuminate\Support\Facades\Route::has('admin.resources.update')
            ? route('admin.resources.update', ['resourceType' => $resourceType, 'resource' => $record])
            : route('admin.'.$resourceType.'.update', ['record' => $record]))
        : (\Illuminate\Support\Facades\Route::has('admin.resources.store')
            ? route('admin.resources.store', ['resourceType' => $resourceType])
            : route('admin.'.$resourceType.'.store'));
    $selectedMaterials = collect(data_get($record, 'materials', []))->pluck('id')->all();
    $selectedFinishings = collect(data_get($record, 'finishings', []))->pluck('id')->all();
    $currentPricingType = data_get($record, 'pricing_type');
    $currentPricingTypeValue = $currentPricingType instanceof \BackedEnum ? $currentPricingType->value : $currentPricingType;
@endphp

@section('title', ($editing ? 'Edit ' : 'Tambah ') . $resourceLabel)

@section('content')
<div class="mx-auto max-w-3xl space-y-7">
    <x-page-header :title="($editing ? 'Edit ' : 'Tambah ') . $resourceLabel" :description="'Perbarui data ' . strtolower($resourceLabel) . ' dengan lengkap.'" eyebrow="Master data">
        <x-slot:actions>
            <x-button :href="\Illuminate\Support\Facades\Route::has('admin.resources.index') ? route('admin.resources.index', ['resourceType' => $resourceType]) : route('admin.'.$resourceType.'.index')" variant="secondary"><x-icon name="arrow-right" class="h-4 w-4" /> Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="panel p-5 sm:p-7" aria-label="Form {{ strtolower($resourceLabel) }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="resourceType" value="{{ $resourceType }}">

        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="name" label="Nama" :value="data_get($record, 'name')" required />
            <x-select name="status" label="Status" :value="data_get($record, 'status', 'active')" required>
                <option value="active" @selected(data_get($record, 'status', 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(data_get($record, 'status') === 'inactive')>Nonaktif</option>
            </x-select>

            @if($resourceType === 'products')
                <label class="flex items-center gap-3 rounded-xl border border-ink-200 bg-ink-50 px-3 py-3 text-sm font-medium text-ink-800">
                    <input type="checkbox" name="is_featured" value="1" class="form-check rounded" @checked((bool) data_get($record, 'is_featured', false))>
                    <span>Tampilkan sebagai featured di homepage</span>
                </label>
                <x-input name="slug" label="Slug" :value="data_get($record, 'slug')" placeholder="Dibuat otomatis jika kosong" />
                <x-select name="category_id" label="Kategori" :value="data_get($record, 'category_id')" placeholder="Pilih kategori" required>
                    @foreach (($categories ?? []) as $category)
                        <option value="{{ data_get($category, 'id') }}">{{ data_get($category, 'name', 'Kategori') }}</option>
                    @endforeach
                </x-select>
                <x-input name="minimum_order" label="Minimum pesan" type="number" min="1" :value="data_get($record, 'minimum_order', 1)" required />
                <x-input name="production_days" label="Estimasi produksi (hari)" type="number" min="1" :value="data_get($record, 'production_days', 1)" required />
                <div class="sm:col-span-2"><x-input name="thumbnail" label="Thumbnail produk" type="file" accept=".jpg,.jpeg,.png,.webp" /></div>
                <div class="sm:col-span-2 grid gap-4 sm:grid-cols-2">
                    @foreach(['front_mockup' => 'Mockup depan', 'back_mockup' => 'Mockup belakang'] as $field => $label)
                        <div class="rounded-lg border border-ink-200 bg-ink-50 p-3">
                            <x-input :name="$field" :label="$label" type="file" accept=".jpg,.jpeg,.png,.webp" />
                            @if(data_get($record, $field))
                                <img src="{{ \App\Support\MediaPath::url(data_get($record, $field)) }}" alt="{{ $label }}" class="mt-3 h-24 w-full rounded-lg border border-ink-200 bg-white object-contain">
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="sm:col-span-2">
                    <x-select name="materials[]" label="Material tersedia" :value="data_get($record, 'default_material_id')" multiple>
                        @foreach (($materials ?? []) as $material)
                            <option value="{{ data_get($material, 'id') }}" @selected(in_array(data_get($material, 'id'), $selectedMaterials))>{{ data_get($material, 'name', 'Material') }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="sm:col-span-2">
                    <x-select name="finishings[]" label="Finishing tersedia" :value="data_get($record, 'default_finishing_id')" multiple>
                        @foreach (($finishings ?? []) as $finishing)
                            <option value="{{ data_get($finishing, 'id') }}" @selected(in_array(data_get($finishing, 'id'), $selectedFinishings))>{{ data_get($finishing, 'name', 'Finishing') }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="sm:col-span-2 grid gap-5 sm:grid-cols-2">
                    <x-input name="specifications[size]" label="Ukuran" :value="data_get($record, 'specifications.size')" />
                    <x-input name="specifications[material]" label="Catatan material" :value="data_get($record, 'specifications.material')" />
                </div>
            @elseif($resourceType === 'categories')
                <x-input name="slug" label="Slug" :value="data_get($record, 'slug')" placeholder="Dibuat otomatis jika kosong" />
                <div class="sm:col-span-2"><x-input name="image" label="Gambar kategori" type="file" accept=".jpg,.jpeg,.png,.webp" /></div>
            @else
                <x-select name="pricing_type" label="Jenis harga" :value="data_get($record, 'pricing_type')" placeholder="Pilih jenis harga" required>
                    @foreach (($pricingTypes ?? []) as $pricingType)
                        @php $pricingValue = $pricingType instanceof \BackedEnum ? $pricingType->value : data_get($pricingType, 'value', $pricingType); $pricingLabel = $pricingType instanceof \UnitEnum ? $pricingType->name : data_get($pricingType, 'label', $pricingValue); @endphp
                        <option value="{{ $pricingValue }}" @selected((string) $currentPricingTypeValue === (string) $pricingValue)>{{ $pricingLabel }}</option>
                    @endforeach
                </x-select>
                <x-input name="price" label="Harga" type="number" step="0.01" min="0" :value="data_get($record, 'price')" required />
            @endif

            <div class="sm:col-span-2"><x-textarea name="description" label="Deskripsi" :value="data_get($record, 'description')" :rows="5" /></div>
        </div>

        <div class="mt-6 flex justify-end border-t border-ink-100 pt-5">
            <x-button type="submit">{{ $editing ? 'Simpan perubahan' : 'Simpan data' }}</x-button>
        </div>
    </form>
</div>
@endsection
