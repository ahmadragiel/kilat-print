@extends('layouts.app')

@section('title', 'Kilat Print')
@section('meta_description', 'Katalog produk percetakan Kilat Print dengan kustomisasi desain dan pemesanan daring.')

@section('content')
@php
    $popular = collect($popularProducts ?? []);
    $latest = collect($latestProducts ?? []);
    $featuredProduct = $popular->first() ?? $latest->first();
    $featuredImage = data_get($featuredProduct, 'image_url') ?? data_get($featuredProduct, 'image');
@endphp

<section class="border-b border-ink-200 bg-white">
    <div class="page-shell grid min-h-[560px] items-center gap-10 py-12 lg:grid-cols-[1.02fr_.98fr] lg:py-16">
        <div class="max-w-2xl">
            <div class="mb-6 inline-flex items-center gap-2 rounded-full bg-flame-50 px-3 py-1.5 text-xs font-bold text-flame-800 ring-1 ring-inset ring-flame-200">
                <x-icon name="star" class="h-3.5 w-3.5" />
                Pilihan cetak Kilat Print
            </div>
            <h1 class="text-4xl font-black tracking-[-0.035em] text-ink-950 sm:text-5xl lg:text-6xl lg:leading-[1.05]">
                Kilat Print.<br>
                <span class="text-flame-600">Ide Anda, dicetak presisi.</span>
            </h1>
            <p class="mt-6 max-w-xl text-base leading-8 text-ink-600 sm:text-lg">Pilih produk, sesuaikan spesifikasi, lalu pantau pesanan dari desain hingga produksi dalam satu alur kerja.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <x-button :href="route('products.index')" size="lg">
                    Jelajahi katalog <x-icon name="arrow-right" class="h-4 w-4" />
                </x-button>
                @auth
                    <x-button :href="route('customer.orders.index')" variant="secondary" size="lg">Lacak pesanan</x-button>
                @else
                    <x-button :href="\Illuminate\Support\Facades\Route::has('auth.register') ? route('auth.register') : route('register')" variant="secondary" size="lg">Buat akun</x-button>
                @endauth
            </div>
        </div>

        <div class="relative mx-auto w-full max-w-xl lg:mx-0 lg:ml-auto">
            <div class="absolute -inset-4 -z-10 rounded-lg bg-flame-100/60"></div>
            <div class="panel relative overflow-hidden border-ink-200 bg-ink-50 p-2 shadow-xl shadow-ink-950/10">
                <x-product-image
                    :src="$featuredImage"
                    :alt="$featuredProduct ? data_get($featuredProduct, 'name', 'Produk Pilihan') : 'Ilustrasi produk Kilat Print'"
                    class="aspect-[5/4]"
                />
                @if ($featuredProduct)
                    <div class="flex items-center justify-between gap-4 border-t border-ink-200 bg-white px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-ink-900">{{ data_get($featuredProduct, 'name', 'Produk Pilihan') }}</p>
                            <p class="mt-0.5 text-xs text-ink-500">Produk pilihan</p>
                        </div>
                        <a href="{{ route('products.show', $featuredProduct) }}" class="action-link shrink-0">Lihat <x-icon name="arrow-right" class="h-4 w-4" /></a>
                    </div>
                @endif
            </div>
            <div class="absolute -bottom-4 -left-4 hidden h-24 w-24 rounded-lg border border-flame-200 bg-flame-400/90 p-4 text-ink-950 sm:block">
                <svg viewBox="0 0 24 24" class="h-full w-full" fill="currentColor" aria-hidden="true"><path d="M13.2 2 5 13h6l-.8 9L19 10h-6l.2-8Z"/></svg>
            </div>
        </div>
    </div>
</section>

<section class="page-shell py-14 sm:py-16">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="section-kicker">Kategori</p>
            <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">Mulai dari kebutuhan Anda</h2>
        </div>
        <a href="{{ route('products.index') }}" class="action-link">Lihat semua produk <x-icon name="arrow-right" class="h-4 w-4" /></a>
    </div>

    @forelse (($categories ?? []) as $category)
        <a href="{{ route('products.index', ['category' => data_get($category, 'slug', data_get($category, 'id'))]) }}" class="group mt-7 flex items-center justify-between gap-5 border-b border-ink-200 py-5 transition hover:border-flame-300">
            <div class="flex min-w-0 items-center gap-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-ink-100 text-ink-700 transition group-hover:bg-flame-100 group-hover:text-flame-700">
                    <x-icon :name="data_get($category, 'icon', 'category')" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h3 class="truncate font-bold text-ink-900 group-hover:text-flame-700">{{ data_get($category, 'name', 'Kategori') }}</h3>
                    @if (data_get($category, 'description'))
                        <p class="mt-1 line-clamp-1 text-sm text-ink-500">{{ data_get($category, 'description') }}</p>
                    @endif
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-3 text-sm text-ink-500">
                @if (! is_null(data_get($category, 'products_count')))
                    <span>{{ data_get($category, 'products_count') }} produk</span>
                @endif
                <x-icon name="chevron-right" class="h-4 w-4 transition group-hover:translate-x-0.5" />
            </div>
        </a>
    @empty
        <div class="panel mt-7">
            <x-empty-state title="Kategori belum tersedia" description="Katalog akan menampilkan kategori setelah data produk ditambahkan." icon="category" />
        </div>
    @endforelse
</section>

<section class="border-y border-ink-200 bg-ink-50 py-14 sm:py-16">
    <div class="page-shell">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Pilihan pelanggan</p>
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">Produk populer</h2>
            </div>
            <a href="{{ route('products.index', ['sort' => 'popular']) }}" class="action-link">Katalog lengkap <x-icon name="arrow-right" class="h-4 w-4" /></a>
        </div>

        @if ($popular->isNotEmpty())
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($popular->take(4) as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        @else
            <div class="panel mt-8">
                <x-empty-state title="Belum ada produk populer" description="Produk yang sudah tersedia akan tampil di sini." icon="star">
                    <x-button :href="route('products.index')" variant="secondary" size="sm">Lihat katalog</x-button>
                </x-empty-state>
            </div>
        @endif
    </div>
</section>

<section class="page-shell py-14 sm:py-16">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="section-kicker">Koleksi terbaru</p>
            <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">Baru hadir</h2>
        </div>
        <a href="{{ route('products.index', ['sort' => 'latest']) }}" class="action-link">Lihat semua <x-icon name="arrow-right" class="h-4 w-4" /></a>
    </div>

    @if ($latest->isNotEmpty())
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($latest->take(8) as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    @else
        <div class="panel mt-8">
            <x-empty-state title="Belum ada produk terbaru" description="Katalog akan diperbarui saat produk baru tersedia." icon="box" />
        </div>
    @endif
</section>
@endsection
