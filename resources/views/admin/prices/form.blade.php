@extends('layouts.admin')

@php
    $priceRule = $priceRule ?? $price ?? null;
    $editing = (bool) $priceRule;
    $currentPricingType = data_get($priceRule, 'pricing_type');
    $currentPricingTypeValue = $currentPricingType instanceof \BackedEnum ? $currentPricingType->value : $currentPricingType;
    $formAction = $editing
        ? (\Illuminate\Support\Facades\Route::has('admin.prices.update') ? route('admin.prices.update', $priceRule) : route('admin.prices.update', $priceRule))
        : (\Illuminate\Support\Facades\Route::has('admin.prices.store') ? route('admin.prices.store') : route('admin.prices.store'));
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
        <div class="grid gap-5 sm:grid-cols-2">
            <x-select name="product_id" label="Produk" :value="data_get($priceRule, 'product_id')" placeholder="Pilih produk" required>
                @foreach (($products ?? []) as $product)
                    <option value="{{ data_get($product, 'id') }}" @selected((string) old('product_id', data_get($priceRule, 'product_id')) === (string) data_get($product, 'id'))>{{ data_get($product, 'name', 'Produk') }}</option>
                @endforeach
            </x-select>
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
            <div class="sm:col-span-2">
                <label class="flex items-center gap-2.5 text-sm font-medium text-ink-700"><input type="checkbox" name="active" value="1" class="form-check rounded" @checked(!$editing || data_get($priceRule, 'active', true))> Aktif digunakan</label>
            </div>
        </div>
        <div class="mt-6 flex justify-end border-t border-ink-100 pt-5"><x-button type="submit">Simpan harga</x-button></div>
    </form>
</div>
@endsection
