@extends('layouts.app')

@section('title', 'Kilat Print')
@section('meta_description', 'Percetakan online Kilat Print. Pesan lebih cepat, cetak lebih mudah, dari desain hingga produksi.')

@section('content')
@php
    $popular = collect($popularProducts ?? []);
    $latest = collect($latestProducts ?? []);
    $featuredProduct = $popular->first() ?? $latest->first();
    $featuredImage = data_get($featuredProduct, 'image_url') ?? data_get($featuredProduct, 'image');
@endphp

{{-- ============================= HERO ============================= --}}
<section class="brand-surface brand-stripes">
    <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-accent-400/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-brand-950/40 blur-3xl"></div>

    <div class="page-shell relative grid items-center gap-12 py-16 lg:grid-cols-[1.05fr_.95fr] lg:py-24">
        <div class="max-w-2xl">
            <div class="mb-6 inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-xs font-bold text-white ring-1 ring-inset ring-white/25">
                <span class="grid h-4 w-4 place-items-center rounded-full bg-accent-400 text-ink-950"><x-icon name="sparkles" class="h-2.5 w-2.5" /></span>
                Percetakan online Kilat Print
            </div>

            <h1 class="text-4xl font-black tracking-[-0.04em] text-white sm:text-5xl lg:text-6xl lg:leading-[1.02]">
                Kilat Print<br>
                <span class="text-accent-300">Pesan Lebih Cepat, Cetak Lebih Mudah.</span>
            </h1>

            <p class="mt-6 max-w-xl text-base leading-8 text-brand-100 sm:text-lg">
                Pilih produk, unggah desain, atur spesifikasi, lalu pantau pesanan dari desain hingga produksi dalam satu alur kerja.
            </p>

            <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                <x-button :href="route('products.index')" variant="accent" size="lg">
                    <x-icon name="zap" class="h-5 w-5" /> Mulai Pesan
                </x-button>
                @auth
                    <a href="{{ route('customer.orders.index') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-white/40 bg-white/10 px-6 py-3 text-base font-bold text-white transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Lacak pesanan</a>
                @else
                    <a href="{{ \Illuminate\Support\Facades\Route::has('auth.register') ? route('auth.register') : route('register') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-white/40 bg-white/10 px-6 py-3 text-base font-bold text-white transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Buat akun gratis</a>
                @endauth
            </div>

            <dl class="mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-white/15 pt-7">
                @foreach ([
                    ['value' => 'Ribuan', 'label' => 'produk tercetak'],
                    ['value' => '24 jam', 'label' => 'konfirmasi desain'],
                    ['value' => '100%', 'label' => 'QC sebelum kirim'],
                ] as $heroStat)
                    <div>
                        <dt class="text-2xl font-black text-accent-300">{{ $heroStat['value'] }}</dt>
                        <dd class="mt-1 text-xs font-medium text-brand-100">{{ $heroStat['label'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="relative mx-auto w-full max-w-lg lg:mx-0 lg:ml-auto">
            <div class="relative overflow-hidden rounded-2xl border border-white/20 bg-white p-2.5 shadow-panel-lg">
                <x-product-image
                    :src="$featuredImage"
                    :alt="$featuredProduct ? data_get($featuredProduct, 'name', 'Produk Pilihan') : 'Ilustrasi produk Kilat Print'"
                    class="aspect-[5/4] rounded-xl"
                />
                @if ($featuredProduct)
                    <div class="flex items-center justify-between gap-4 px-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-ink-950">{{ data_get($featuredProduct, 'name', 'Produk Pilihan') }}</p>
                            <p class="mt-0.5 text-xs text-ink-500">Produk pilihan</p>
                        </div>
                        <a href="{{ route('products.show', $featuredProduct) }}" class="action-link shrink-0">Lihat <x-icon name="arrow-right" class="h-4 w-4" /></a>
                    </div>
                @endif
            </div>

            {{-- Yellow accent card — small, decorative, attention grabbing --}}
            <div class="absolute -bottom-5 -left-4 hidden w-40 rounded-2xl border border-accent-300 bg-accent-400 p-4 text-ink-950 shadow-panel-lg sm:block">
                <svg viewBox="0 0 24 24" class="h-7 w-7" fill="currentColor" aria-hidden="true"><path d="M13.2 2 5 13h6l-.8 9L19 10h-6l.2-8Z"/></svg>
                <p class="mt-2 text-xs font-black tracking-tight">Kilat Productions</p>
            </div>
        </div>
    </div>
</section>

{{-- ========================= TRUST STRIP ========================== --}}
<section class="border-b border-ink-200 bg-white">
    <div class="page-shell grid gap-4 py-6 sm:grid-cols-3">
        @foreach ([
            ['icon' => 'truck', 'title' => 'Kirim tepat waktu', 'text' => 'Status produksi transparan sampai barang dikirim.'],
            ['icon' => 'shield', 'title' => 'QC berlapis', 'text' => 'Setiap unit diperiksa sebelum packing.'],
            ['icon' => 'palette', 'title' => 'Bebas desain', 'text' => 'Upload file atau rancang langsung di editor kami.'],
        ] as $feature)
            <div class="flex items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-icon :name="$feature['icon']" class="h-5 w-5" /></span>
                <div>
                    <p class="text-sm font-bold text-ink-950">{{ $feature['title'] }}</p>
                    <p class="mt-0.5 text-sm leading-6 text-ink-500">{{ $feature['text'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- ============================ CATEGORIES ========================= --}}
<section class="page-shell py-14 sm:py-16">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="section-kicker">Kategori</p>
            <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">Mulai dari kebutuhan Anda</h2>
        </div>
        <a href="{{ route('products.index') }}" class="action-link">Lihat semua produk <x-icon name="arrow-right" class="h-4 w-4" /></a>
    </div>

    @forelse (($categories ?? []) as $category)
        <a href="{{ route('products.index', ['category' => data_get($category, 'slug', data_get($category, 'id'))]) }}" class="group mt-7 flex items-center justify-between gap-5 border-b border-ink-200 py-5 transition hover:border-brand-300">
            <div class="flex min-w-0 items-center gap-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-ink-100 text-ink-700 transition group-hover:bg-brand-600 group-hover:text-white">
                    <x-icon :name="data_get($category, 'icon', 'category')" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h3 class="truncate font-bold text-ink-900 transition group-hover:text-brand-700">{{ data_get($category, 'name', 'Kategori') }}</h3>
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

{{-- ======================= POPULAR PRODUCTS ====================== --}}
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
                    <x-product-card :product="$product" :badge="$loop->first ? 'Best Seller' : null" />
                @endforeach
            </div>
        @else
            <div class="panel mt-8">
                <x-empty-state title="Belum ada produk populer" description="Produk yang sudah tersedia akan tampil di sini." icon="star">
                    <x-button :href="route('products.index')" variant="outline" size="sm">Lihat katalog</x-button>
                </x-empty-state>
            </div>
        @endif
    </div>
</section>

{{-- ============================ LATEST ============================ --}}
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

{{-- ============================== CTA ============================= --}}
<section class="page-shell pb-16 sm:pb-24">
    <div class="brand-surface brand-stripes overflow-hidden rounded-2xl px-6 py-12 sm:px-12 sm:py-16">
        <div class="pointer-events-none absolute inset-y-0 right-0 hidden w-1/3 bg-linear-to-l from-accent-400/15 to-transparent lg:block"></div>
        <div class="relative max-w-2xl">
            <p class="inline-flex items-center gap-2 rounded-full bg-accent-400 px-3 py-1 text-2xs font-black tracking-[0.16em] text-ink-950 uppercase">
                <x-icon name="zap" class="h-3 w-3" /> Siap cetak hari ini
            </p>
            <h2 class="mt-5 text-3xl font-black tracking-tight text-white sm:text-4xl">Siapkan desain Anda, kami tangani sisanya.</h2>
            <p class="mt-4 text-base leading-7 text-brand-100">Mulai dari katalog, gunakan editor desain Kilat Print, lalu selesaikan pembayaran dalam beberapa langkah.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <x-button :href="route('products.index')" variant="accent" size="lg">Mulai Pesan <x-icon name="arrow-right" class="h-5 w-5" /></x-button>
                <a href="{{ route('cart.index') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-white/40 bg-white/10 px-6 py-3 text-base font-bold text-white transition hover:bg-white/20">Lihat keranjang</a>
            </div>
        </div>
    </div>
</section>
@endsection
