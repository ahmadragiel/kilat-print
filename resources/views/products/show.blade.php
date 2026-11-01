@extends('layouts.app')

@section('title', data_get($product, 'name', 'Produk'))
@section('meta_description', data_get($product, 'description', 'Detail produk Kilat Print'))

@section('content')
@php
    $image = data_get($product, 'thumbnail_url') ?? data_get($product, 'image_url') ?? data_get($product, 'thumbnail') ?? data_get($product, 'image');
    $price = data_get($product, 'starting_price') ?? data_get($product, 'min_price') ?? data_get($product, 'base_price') ?? data_get($product, 'price');
    $priceRules = collect(data_get($product, 'priceRules', []))
        ->filter(fn ($rule) => ! data_get($rule, 'is_wholesale', false));
    if ($price === null && $priceRules->isNotEmpty()) {
        $price = $priceRules->min(fn ($rule) => data_get($rule, 'discounted_price', data_get($rule, 'price')));
    }
    $productionMethods = $productionMethods ?? [
        ['value' => 'digital', 'label' => 'Digital'],
        ['value' => 'offset', 'label' => 'Offset'],
        ['value' => 'large_format', 'label' => 'Large format'],
        ['value' => 'sublimation', 'label' => 'Sublimasi'],
    ];
    $canEstimate = auth()->check() && auth()->user()->isRole(\App\Enums\UserRole::Customer);
    $minimumOrder = max(1, (int) data_get($product, 'minimum_order', 1));
    $variation = method_exists($product, 'variationDefinition') ? $product->variationDefinition() : null;
    $defaultVariant = method_exists($product, 'defaultVariant') ? $product->defaultVariant() : null;
    $needsDimensions = collect($priceRules ?? [])->contains(function ($rule) {
        $type = data_get($rule, 'pricing_type');
        $value = $type instanceof \BackedEnum ? $type->value : $type;

        return in_array($value, ['per_sqm', 'per_meter'], true);
    });
    $specificationRows = collect(data_get($product, 'specifications', []))
        ->reject(fn ($value, $key) => in_array($key, ['demo', 'catatan'], true) || is_array($value) || blank($value));
@endphp

<div class="page-shell py-6 sm:py-8">
    <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-ink-500" aria-label="Breadcrumb">
        <a href="{{ route('products.index') }}" class="breadcrumb-link">Katalog</a>
        <x-icon name="chevron-right" class="h-3.5 w-3.5" />
        @if (data_get($product, 'category'))
            <a href="{{ route('products.index', ['category' => data_get($product, 'category.slug', data_get($product, 'category.id'))]) }}" class="breadcrumb-link">{{ data_get($product, 'category.name', 'Kategori') }}</a>
            <x-icon name="chevron-right" class="h-3.5 w-3.5" />
        @endif
        <span class="max-w-56 truncate font-medium text-ink-700">{{ data_get($product, 'name', 'Produk') }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-[1.05fr_.95fr] lg:gap-12">
        <div>
            <x-product-image :src="$image" :alt="data_get($product, 'name', 'Gambar produk')" fit="contain" class="aspect-square border border-ink-200" />
            @if (data_get($product, 'description'))
                <section class="mt-8 border-t border-ink-200 pt-7">
                    <h2 class="text-lg font-extrabold text-ink-950">Tentang produk</h2>
                    <div class="mt-3 whitespace-pre-line text-sm leading-7 text-ink-600">{{ data_get($product, 'description') }}</div>
                </section>
            @endif
            @if ($specificationRows->isNotEmpty())
                <section class="mt-8 border-t border-ink-200 pt-7">
                    <h2 class="text-lg font-extrabold text-ink-950">Spesifikasi</h2>
                    <dl class="mt-3 divide-y divide-ink-100 text-sm">
                        @foreach ($specificationRows as $specificationKey => $specificationValue)
                            <div class="flex items-start justify-between gap-6 py-3">
                                <dt class="text-ink-500">{{ \Illuminate\Support\Str::headline((string) $specificationKey) }}</dt>
                                <dd class="text-right font-semibold text-ink-800">{{ $specificationValue }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>

        <div>
            <div class="lg:sticky lg:top-24">
                <div class="flex flex-wrap items-center gap-2">
                    @if (data_get($product, 'category.name'))
                        <x-badge color="brand">{{ data_get($product, 'category.name') }}</x-badge>
                    @endif
                    @if (data_get($product, 'sku'))
                        <x-badge color="gray">SKU {{ data_get($product, 'sku') }}</x-badge>
                    @endif
                </div>
                <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-ink-950 sm:text-4xl">{{ data_get($product, 'name', 'Produk') }}</h1>
                <p class="mt-3 text-2xs font-extrabold tracking-[0.14em] text-ink-600 uppercase">{{ method_exists($product, 'priceDisplayLabel') ? $product->priceDisplayLabel() : 'Mulai dari' }}</p>
                <p class="mt-1 text-3xl font-black text-brand-700"><x-money :value="$price" /></p>

                <form
                    method="POST"
                    action="{{ \Illuminate\Support\Facades\Route::has('customer.cart.store') ? route('customer.cart.store') : route('cart.store') }}"
                    enctype="multipart/form-data"
                    class="mt-8 space-y-5 border-y border-ink-200 py-6"
                    aria-label="Tambah produk ke keranjang"
                    @if ($canEstimate) x-data="priceEstimator({ endpoint: @js(route('products.price', $product)) })" @endif
                >
                    @csrf
                    <input type="hidden" name="product_id" value="{{ data_get($product, 'id') }}">
                    <p class="text-xs leading-5 text-ink-500">Kolom bertanda <span class="font-bold text-danger-600">*</span> wajib diisi. Estimasi harga dihitung dari aturan harga produk.</p>

                    <div class="grid gap-5 sm:grid-cols-2">
                        @if ($variation)
                            <x-select
                                name="variant"
                                :label="$variation['label']"
                                :value="$variation['required'] ? $defaultVariant : null"
                                :placeholder="$variation['required'] ? null : 'Tidak memilih'"
                                :required="$variation['required']"
                                :help="'Pilih '.strtolower($variation['label']).($variation['required'] ? ' yang diinginkan.' : ' bila diperlukan.')"
                            >
                                @foreach ($variation['options'] as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </x-select>
                        @else
                            <x-input name="size" label="Ukuran / orientasi" :value="data_get($product, 'default_size')" placeholder="Contoh: A4" help="Opsional. Contoh: A4, lanskap." />
                        @endif
                        <x-input name="color" label="Warna" placeholder="Contoh: Merah" help="Opsional. Sebutkan warna utama." />
                        @if ($needsDimensions)
                            <x-input name="length_cm" label="Panjang (cm)" type="number" step="0.01" min="0.1" placeholder="Panjang" help="Isi jika harga dihitung per meter atau luas." />
                            <x-input name="width_cm" label="Lebar (cm)" type="number" step="0.01" min="0.1" placeholder="Lebar" help="Isi jika harga dihitung per meter atau luas." />
                        @endif
                    </div>

                    <x-select name="production_method" label="Metode produksi" :value="data_get($product, 'default_production_method')" required help="Pilih proses yang paling sesuai dengan bahan dan hasil akhir.">
                        @foreach ($productionMethods as $method)
                            <option value="{{ data_get($method, 'value', data_get($method, 'code')) }}">{{ data_get($method, 'label', data_get($method, 'name', data_get($method, 'value'))) }}</option>
                        @endforeach
                    </x-select>
                    <x-input name="quantity" label="Jumlah" type="number" :value="$minimumOrder" :min="$minimumOrder" required :help="'Minimal '.$minimumOrder.' item.'" />
                    <x-textarea name="notes" label="Catatan produk (opsional)" :rows="2" placeholder="Instruksi tambahan untuk produksi" />
                    <x-input name="design_file" label="Referensi desain (opsional)" type="file" accept=".jpg,.jpeg,.png,.pdf" help="Format JPG, PNG, atau PDF." />

                    @if ($canEstimate)
                        <div class="rounded-xl border border-brand-200 bg-brand-50/60 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-extrabold tracking-[0.12em] text-brand-700 uppercase">Estimasi harga</p>
                                    <p class="mt-1 text-sm text-ink-600">Hitung dulu sebelum menambahkan ke keranjang.</p>
                                </div>
                                <x-button type="button" variant="secondary" size="sm" x-on:click="fetchEstimate()" x-bind:disabled="loading">
                                    <x-icon name="calculator" class="h-4 w-4" />
                                    <span x-text="loading ? 'Menghitung…' : 'Hitung estimasi'"></span>
                                </x-button>
                            </div>
                            <p x-show="error" x-cloak x-text="error" class="mt-3 text-sm font-semibold text-danger-700"></p>
                            <div x-show="estimate !== null && ! error" x-cloak class="mt-3 space-y-1 border-t border-brand-200 pt-3 text-sm">
                                <p class="flex items-center justify-between gap-4">
                                    <span class="text-ink-600">Total estimasi</span>
                                    <span class="text-lg font-black text-brand-700" x-text="formatAmount(estimate)"></span>
                                </p>
                                <p x-show="response?.formatted?.unit_price" class="flex items-center justify-between gap-4 text-ink-600">
                                    <span>Harga per item</span>
                                    <span class="font-semibold text-ink-900" x-text="response?.formatted?.unit_price"></span>
                                </p>
                                <p class="text-xs leading-5 text-ink-500">Belum termasuk ongkos kirim. Total akhir dihitung saat checkout.</p>
                            </div>
                        </div>
                    @else
                        <x-alert type="info">
                            <p>Masuk sebagai pelanggan untuk menghitung estimasi harga dan menambahkan produk ke keranjang. <a href="{{ route('login') }}" class="font-bold underline underline-offset-4">Masuk</a></p>
                        </x-alert>
                    @endif

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-button type="submit" size="lg" class="w-full"><x-icon name="cart" class="h-4 w-4" /> Tambah ke keranjang</x-button>
                        <x-button :href="route('products.customize', $product)" variant="outline" size="lg" class="w-full"><x-icon name="edit" class="h-4 w-4" /> Buka editor desain</x-button>
                    </div>
                </form>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['icon' => 'shield', 'text' => 'QC sebelum kirim'],
                        ['icon' => 'truck', 'text' => data_get($product, 'production_days') ? data_get($product, 'production_days').' hari produksi' : 'Produksi cepat'],
                        ['icon' => 'palette', 'text' => 'Bebas desain'],
                    ] as $assurance)
                        <div class="flex items-center gap-2 rounded-lg border border-ink-200 bg-ink-50 px-3 py-2.5 text-xs font-semibold text-ink-700">
                            <x-icon :name="$assurance['icon']" class="h-4 w-4 shrink-0 text-brand-600" />
                            {{ $assurance['text'] }}
                        </div>
                    @endforeach
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
