@extends('layouts.app')

@section('title', data_get($product, 'name', 'Produk'))
@section('meta_description', data_get($product, 'description', 'Detail produk Kilat Print'))

@section('content')
@php
    $materials = $materials ?? data_get($product, 'materials', []);
    $finishings = $finishings ?? data_get($product, 'finishings', []);
    $image = data_get($product, 'thumbnail_url') ?? data_get($product, 'image_url') ?? data_get($product, 'thumbnail') ?? data_get($product, 'image');
    $price = data_get($product, 'starting_price') ?? data_get($product, 'min_price') ?? data_get($product, 'base_price') ?? data_get($product, 'price');
    $priceRules = data_get($product, 'priceRules');
    if ($price === null && is_object($priceRules) && method_exists($priceRules, 'min')) {
        $price = $priceRules->min('price');
    }
    $productionMethods = $productionMethods ?? [
        ['value' => 'digital', 'label' => 'Digital'],
        ['value' => 'offset', 'label' => 'Offset'],
        ['value' => 'large_format', 'label' => 'Large format'],
        ['value' => 'sublimation', 'label' => 'Sublimasi'],
    ];
@endphp

<div class="page-shell py-6 sm:py-8">
    <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-ink-500" aria-label="Breadcrumb">
        <a href="{{ route('products.index') }}" class="hover:text-flame-700">Katalog</a>
        <x-icon name="chevron-right" class="h-3.5 w-3.5" />
        @if (data_get($product, 'category'))
            <a href="{{ route('products.index', ['category' => data_get($product, 'category.slug', data_get($product, 'category.id'))]) }}" class="hover:text-flame-700">{{ data_get($product, 'category.name', 'Kategori') }}</a>
            <x-icon name="chevron-right" class="h-3.5 w-3.5" />
        @endif
        <span class="max-w-56 truncate font-medium text-ink-700">{{ data_get($product, 'name', 'Produk') }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-[1.05fr_.95fr] lg:gap-12">
        <div>
            <x-product-image :src="$image" :alt="data_get($product, 'name', 'Gambar produk')" class="aspect-square border border-ink-200" />
            @if (data_get($product, 'description'))
                <section class="mt-8 border-t border-ink-200 pt-7">
                    <h2 class="text-lg font-extrabold text-ink-950">Tentang produk</h2>
                    <div class="mt-3 whitespace-pre-line text-sm leading-7 text-ink-600">{{ data_get($product, 'description') }}</div>
                </section>
            @endif
        </div>

        <div>
            <div class="lg:sticky lg:top-24">
                <div class="flex flex-wrap items-center gap-2">
                    @if (data_get($product, 'category.name'))
                        <x-badge color="orange">{{ data_get($product, 'category.name') }}</x-badge>
                    @endif
                    @if (data_get($product, 'sku'))
                        <x-badge color="gray">SKU {{ data_get($product, 'sku') }}</x-badge>
                    @endif
                </div>
                <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-ink-950 sm:text-4xl">{{ data_get($product, 'name', 'Produk') }}</h1>
                <p class="mt-3 text-sm font-medium text-ink-500">Harga mulai dari</p>
                <p class="mt-1 text-2xl font-black text-ink-950"><x-money :value="$price" /></p>

                <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('customer.cart.store') ? route('customer.cart.store') : route('cart.store') }}" class="mt-8 space-y-5 border-y border-ink-200 py-6" aria-label="Tambah produk ke keranjang">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ data_get($product, 'id') }}">

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-select name="material_id" label="Material" :value="data_get($product, 'default_material_id')" placeholder="Tanpa material">
                            @foreach ($materials as $material)
                                <option value="{{ data_get($material, 'id') }}">{{ data_get($material, 'name', 'Material') }}</option>
                            @endforeach
                        </x-select>
                        <x-select name="finishing_id" label="Finishing" :value="data_get($product, 'default_finishing_id')" placeholder="Tanpa finishing">
                            @foreach ($finishings as $finishing)
                                <option value="{{ data_get($finishing, 'id') }}">{{ data_get($finishing, 'name', 'Finishing') }}</option>
                            @endforeach
                        </x-select>
                        <x-input name="size" label="Ukuran / orientasi" :value="data_get($product, 'default_size')" placeholder="Contoh: A4" />
                        <x-input name="color" label="Warna" placeholder="Contoh: Merah" />
                        <x-input name="length_cm" label="Panjang (cm)" type="number" step="0.01" min="0" placeholder="Panjang" />
                        <x-input name="width_cm" label="Lebar (cm)" type="number" step="0.01" min="0" placeholder="Lebar" />
                    </div>

                    <x-select name="production_method" label="Metode produksi" :value="data_get($product, 'default_production_method')" required>
                        @foreach ($productionMethods as $method)
                            <option value="{{ data_get($method, 'value', data_get($method, 'code')) }}">{{ data_get($method, 'label', data_get($method, 'name', data_get($method, 'value'))) }}</option>
                        @endforeach
                    </x-select>
                    <x-input name="quantity" label="Jumlah" type="number" :value="data_get($product, 'minimum_order', 1)" min="1" required />
                    <x-textarea name="notes" label="Catatan produk" :rows="2" placeholder="Instruksi tambahan untuk produksi" />
                    <x-input name="design_file" label="Referensi desain (opsional)" type="file" accept=".jpg,.jpeg,.png,.pdf" />

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-button type="submit" class="w-full"><x-icon name="cart" class="h-4 w-4" /> Tambah ke keranjang</x-button>
                        <x-button :href="route('products.customize', $product)" variant="secondary" class="w-full"><x-icon name="edit" class="h-4 w-4" /> Buka editor desain</x-button>
                    </div>
                </form>

                <div class="mt-6 flex items-center gap-3 text-sm text-ink-600">
                    <x-icon name="shield" class="h-5 w-5 text-emerald-600" />
                    <span>Spesifikasi produk</span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-x-5 gap-y-3 text-sm">
                    @if (data_get($product, 'minimum_order'))
                        <div><dt class="text-ink-500">Minimum pesan</dt><dd class="mt-0.5 font-semibold text-ink-800">{{ data_get($product, 'minimum_order') }} item</dd></div>
                    @endif
                    @if (data_get($product, 'production_days'))
                        <div><dt class="text-ink-500">Estimasi produksi</dt><dd class="mt-0.5 font-semibold text-ink-800">{{ data_get($product, 'production_days') }} hari</dd></div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
