@extends('layouts.admin')

@php
    $priceRule = $priceRule ?? $price ?? null;
    $editing = (bool) $priceRule;
    $currentPricingType = data_get($priceRule, 'pricing_type');
    $currentPricingTypeValue = $currentPricingType instanceof \BackedEnum ? $currentPricingType->value : $currentPricingType;
    $selectedProductId = (string) old('product_id', data_get($priceRule, 'product_id'));
    $selectedVariant = (string) old('variant_key', data_get($priceRule, 'variant_key'));
    $variantOptions = $variantOptions ?? [];
    $formAction = $editing
        ? route('admin.prices.update', $priceRule)
        : route('admin.prices.store');
@endphp

@section('title', $editing ? 'Edit Harga' : 'Tambah Harga')

@section('content')
<div class="mx-auto max-w-3xl space-y-7">
    <x-page-header :title="$editing ? 'Edit harga' : 'Tambah harga'" description="Harga akan digunakan oleh sistem untuk estimasi." eyebrow="Harga">
        <x-slot:actions><x-button :href="route('admin.prices.index')" variant="secondary">Kembali</x-button></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ $formAction }}" class="panel p-5 sm:p-7" aria-label="Form harga produk">
        @csrf
        @if($editing) @method('PUT') @endif

        <div
            class="grid gap-5 sm:grid-cols-2"
            x-data="{ productId: @js($selectedProductId), variant: @js($selectedVariant), variants: @js($variantOptions) }"
        >
            <div>
                <label for="product_id" class="form-label">Produk <span class="text-danger-600" aria-hidden="true">*</span></label>
                <select id="product_id" name="product_id" x-model="productId" class="form-control" required>
                    <option value="">Pilih produk</option>
                    @foreach (($products ?? []) as $product)
                        <option value="{{ data_get($product, 'id') }}" @selected($selectedProductId === (string) data_get($product, 'id'))>{{ data_get($product, 'name', 'Produk') }}</option>
                    @endforeach
                </select>
                @error('product_id')<p class="mt-1.5 text-xs font-medium text-danger-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="variant_key" class="form-label">Varian</label>
                <select id="variant_key" name="variant_key" x-model="variant" class="form-control">
                    <option value="">Semua varian</option>
                    <template x-for="option in variants.filter(item => String(item.product_id) === String(productId))" :key="option.value">
                        <option :value="option.value" x-text="option.label"></option>
                    </template>
                </select>
                <p class="mt-1.5 text-xs text-ink-500">Kosongkan bila aturan berlaku untuk semua varian produk.</p>
                @error('variant_key')<p class="mt-1.5 text-xs font-medium text-danger-600">{{ $message }}</p>@enderror
            </div>

            <x-input name="name" label="Nama aturan" :value="data_get($priceRule, 'name')" placeholder="Contoh: Harga standar" required />

            <x-select name="pricing_type" label="Jenis harga" :value="data_get($priceRule, 'pricing_type')" placeholder="Pilih jenis harga" required>
                @foreach (($pricingTypes ?? []) as $pricingType)
                    @php $pricingValue = $pricingType instanceof \BackedEnum ? $pricingType->value : data_get($pricingType, 'value', $pricingType); $pricingLabel = $pricingType instanceof \UnitEnum ? $pricingType->name : data_get($pricingType, 'label', $pricingValue); @endphp
                    <option value="{{ $pricingValue }}" @selected((string) $currentPricingTypeValue === (string) $pricingValue)>{{ $pricingLabel }}</option>
                @endforeach
            </x-select>

            <x-input name="min_quantity" label="Jumlah minimum" type="number" min="1" :value="data_get($priceRule, 'min_quantity', 1)" required />
            <x-input name="price" label="Harga" type="number" step="0.01" min="0" :value="data_get($priceRule, 'price', data_get($priceRule, 'amount'))" required />
            <x-input name="discount_percent" label="Diskon (%)" type="number" step="0.01" min="0" max="100" :value="data_get($priceRule, 'discount_percent', 0) ?? 0" />

            <label class="sm:col-span-2 flex items-start gap-2.5 rounded-lg border border-ink-200 bg-ink-50 p-3 text-sm text-ink-700">
                <input type="checkbox" name="is_wholesale" value="1" class="form-check mt-0.5 rounded" @checked((bool) data_get($priceRule, 'is_wholesale', false))>
                <span>
                    <span class="font-semibold text-ink-900">Harga grosir</span>
                    <span class="mt-0.5 block text-xs leading-5 text-ink-500">Hanya boleh aktif mulai jumlah minimal 2. Harga grosir tidak dipakai untuk harga mulai di katalog.</span>
                </span>
            </label>

            <label class="sm:col-span-2 flex items-center gap-2.5 text-sm font-medium text-ink-700"><input type="checkbox" name="active" value="1" class="form-check rounded" @checked(!$editing || data_get($priceRule, 'active', true))> Aktif digunakan</label>
        </div>

        <div class="mt-6 flex justify-end border-t border-ink-100 pt-5"><x-button type="submit">Simpan harga</x-button></div>
    </form>
</div>
@endsection
