@extends('layouts.app')

@section('title', 'Katalog Produk')

@section('content')
<div class="border-b border-ink-200 bg-white">
    <div class="page-shell py-10 sm:py-12">
        <p class="section-kicker">Katalog</p>
        <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-ink-950 sm:text-4xl">Produk percetakan</h1>
                <p class="mt-2 text-sm text-ink-500">Temukan produk sesuai kategori dan filter kebutuhan Anda.</p>
            </div>
            @if (isset($filters['search']) || request('search') || request('q'))
                <p class="text-sm font-semibold text-ink-600">Pencarian: {{ $filters['search'] ?? request('search', request('q')) }}</p>
            @endif
        </div>
    </div>
</div>

<div class="page-shell py-8 sm:py-10">
    @php
        $activeFilters = $filters ?? request()->query();
        $hasFilters = collect(['search', 'category', 'min_price', 'max_price', 'sort'])
            ->contains(fn (string $key) => request()->filled($key));
        $sortLabels = ['latest' => 'Terbaru', 'newest' => 'Terbaru', 'popular' => 'Populer', 'name' => 'Nama A-Z', 'price_low' => 'Harga terendah', 'price_high' => 'Harga tertinggi'];
        $activeCategory = collect($categories ?? [])->firstWhere('slug', request('category'));
        $activeCategoryName = data_get($activeCategory, 'name', request('category'));
        $activeSortLabel = $sortLabels[request('sort')] ?? null;
    @endphp
    <form method="GET" action="{{ route('products.index') }}" class="panel grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-12" aria-label="Filter produk">
        <div class="lg:col-span-4">
            <label for="search" class="form-label">Cari produk</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" />
                <input id="search" name="search" value="{{ $activeFilters['search'] ?? request('q') }}" class="form-control pl-10" placeholder="Nama produk">
            </div>
        </div>
        <div class="lg:col-span-2">
            <label for="min_price" class="form-label">Harga minimum</label>
            <input id="min_price" name="min_price" type="number" inputmode="numeric" min="0" step="1000" value="{{ $activeFilters['min_price'] ?? '' }}" class="form-control" placeholder="Tanpa batas">
            <p class="mt-1 text-xs text-ink-500">Dalam rupiah.</p>
        </div>
        <div class="lg:col-span-2">
            <label for="max_price" class="form-label">Harga maksimum</label>
            <input id="max_price" name="max_price" type="number" inputmode="numeric" min="0" step="1000" value="{{ $activeFilters['max_price'] ?? '' }}" class="form-control" placeholder="Tanpa batas">
            <p class="mt-1 text-xs text-ink-500">Dalam rupiah.</p>
        </div>
        <div class="lg:col-span-2">
            <label for="category" class="form-label">Kategori</label>
            <select id="category" name="category" class="form-control">
                <option value="">Semua kategori</option>
                @foreach (($categories ?? []) as $category)
                    <option value="{{ data_get($category, 'slug', data_get($category, 'id')) }}" @selected((string) request('category') === (string) data_get($category, 'slug', data_get($category, 'id')))>{{ data_get($category, 'name', 'Kategori') }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-2">
            <label for="sort" class="form-label">Urutkan</label>
            <select id="sort" name="sort" class="form-control">
                @foreach ($sortLabels as $value => $label)
                    @continue($value === 'newest')
                    <option value="{{ $value }}" @selected(request('sort', 'latest') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2 lg:col-span-2">
            <x-button type="submit" class="flex-1"><x-icon name="filter" class="h-4 w-4" /> Terapkan</x-button>
            @if ($hasFilters)
                <x-button :href="route('products.index')" variant="secondary" size="icon" aria-label="Reset filter"><x-icon name="refresh" class="h-4 w-4" /></x-button>
            @endif
        </div>
    </form>

    <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-600" aria-live="polite">
            @if (method_exists($products ?? null, 'total'))
                {{ $products->total() }} produk ditemukan
            @else
                Hasil sesuai filter yang dipilih
            @endif
        </p>
        @if ($hasFilters)
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold text-ink-500">Filter aktif:</span>
                @if(request('search'))
                    <x-badge color="gray">Pencarian: {{ request('search') }}</x-badge>
                @endif
                @if(request('category'))
                    <x-badge color="gray">Kategori: {{ $activeCategoryName }}</x-badge>
                @endif
                @if(request()->filled('min_price'))
                    <x-badge color="gray">Minimal Rp {{ number_format((float) request('min_price'), 0, ',', '.') }}</x-badge>
                @endif
                @if(request()->filled('max_price'))
                    <x-badge color="gray">Maksimal Rp {{ number_format((float) request('max_price'), 0, ',', '.') }}</x-badge>
                @endif
                @if($activeSortLabel && request('sort') !== 'latest')
                    <x-badge color="gray">Urutan: {{ $activeSortLabel }}</x-badge>
                @endif
                <a href="{{ route('products.index') }}" class="inline-flex min-h-11 items-center px-1 text-xs font-bold text-brand-700 underline underline-offset-4 hover:text-brand-800">Hapus semua</a>
            </div>
        @endif
    </div>

    @if (($products ?? collect())->count())
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>

        @if (method_exists($products ?? [], 'links'))
            <div class="mt-10">{{ $products->withQueryString()->links() }}</div>
        @endif
    @else
        <div class="panel mt-6">
            <x-empty-state title="Produk tidak ditemukan" description="Coba kata kunci atau kategori yang berbeda." icon="search">
                <x-button :href="route('products.index')" variant="secondary" size="sm">Reset filter</x-button>
            </x-empty-state>
        </div>
    @endif
</div>
@endsection
