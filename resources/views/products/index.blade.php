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
    @php $activeFilters = $filters ?? request()->query(); @endphp
    <form method="GET" action="{{ route('products.index') }}" class="panel grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-12" aria-label="Filter produk">
        <div class="lg:col-span-4">
            <label for="search" class="form-label">Cari produk</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                <input id="search" name="search" value="{{ $activeFilters['search'] ?? request('q') }}" class="form-control pl-10" placeholder="Nama produk">
            </div>
        </div>
        <div class="lg:col-span-2">
            <label for="min_price" class="form-label">Harga minimum</label>
            <input id="min_price" name="min_price" type="number" min="0" step="0.01" value="{{ $activeFilters['min_price'] ?? '' }}" class="form-control" placeholder="Tanpa batas">
        </div>
        <div class="lg:col-span-2">
            <label for="max_price" class="form-label">Harga maksimum</label>
            <input id="max_price" name="max_price" type="number" min="0" step="0.01" value="{{ $activeFilters['max_price'] ?? '' }}" class="form-control" placeholder="Tanpa batas">
        </div>
        <div class="lg:col-span-3">
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
                @foreach (['latest' => 'Terbaru', 'popular' => 'Populer', 'name' => 'Nama A-Z', 'price_low' => 'Harga terendah', 'price_high' => 'Harga tertinggi'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', 'latest') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2 lg:col-span-3">
            <x-button type="submit" class="flex-1"><x-icon name="filter" class="h-4 w-4" /> Terapkan</x-button>
            @if (request()->hasAny(['q', 'category', 'sort']))
                <x-button :href="route('products.index')" variant="secondary" size="icon" aria-label="Reset filter"><x-icon name="refresh" class="h-4 w-4" /></x-button>
            @endif
        </div>
    </form>

    <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-500">Menampilkan hasil sesuai filter yang dipilih.</p>
        @if (request()->hasAny(['q', 'category', 'sort']))
            <div class="flex flex-wrap gap-2">
                @if(request('q'))<x-badge color="gray">Pencarian: {{ request('q') }}</x-badge>@endif
                @if(request('category'))<x-badge color="gray">Kategori: {{ request('category') }}</x-badge>@endif
                @if(request('sort'))<x-badge color="orange">Urut: {{ request('sort') }}</x-badge>@endif
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
