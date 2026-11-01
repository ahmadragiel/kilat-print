@extends('layouts.app')

@section('title', 'Kilat Print')
@section('meta_description', 'Kilat Print melayani cetak dan produksi kustom: pilih produk, atur ukuran dan jumlah, lihat estimasi harga, lalu pantau pesanan dari satu dashboard.')

@section('content')
@php
    $popular = collect($popularProducts ?? []);
    $latest = collect($latestProducts ?? []);
    $featured = collect($featuredProducts ?? []);
    $showcaseProduct = $featured->first() ?? $popular->first() ?? $latest->first();
    $showcaseImage = data_get($showcaseProduct, 'thumbnail_url')
        ?? data_get($showcaseProduct, 'image_url')
        ?? data_get($showcaseProduct, 'thumbnail')
        ?? data_get($showcaseProduct, 'image');
    $showcasePriceRules = collect(data_get($showcaseProduct, 'priceRules', []))
        ->filter(fn ($rule) => data_get($rule, 'active', true) && ! data_get($rule, 'is_wholesale', false))
        ->sortBy(fn ($rule) => data_get($rule, 'discounted_price', data_get($rule, 'price', 0)));
    $showcasePriceLabel = is_object($showcaseProduct) && method_exists($showcaseProduct, 'priceDisplayLabel')
        ? $showcaseProduct->priceDisplayLabel()
        : 'Mulai dari';
    $showcasePrice = data_get($showcasePriceRules->first(), 'discounted_price')
        ?? data_get($showcaseProduct, 'starting_price')
        ?? data_get($showcaseProduct, 'min_price')
        ?? data_get($showcaseProduct, 'base_price')
        ?? data_get($showcaseProduct, 'price');
    $catalogHighlights = $popular
        ->concat($latest)
        ->unique(fn ($product) => data_get($product, 'id') ?? data_get($product, 'slug'))
        ->take(8);
    $categoryList = collect($categories ?? []);
    $previewCategories = $categoryList->take(4);
@endphp

{{-- ============================= HERO ============================= --}}
<section class="page-shell pt-6 sm:pt-8">
    <div class="relative overflow-hidden rounded-[2rem] border border-ink-200 bg-white shadow-panel-lg">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(225,29,46,0.06),_transparent_28%),radial-gradient(circle_at_bottom_right,_rgba(251,191,36,0.12),_transparent_30%)]"></div>

        <div class="relative grid gap-8 p-6 sm:p-9 lg:grid-cols-[1.05fr_.95fr] lg:items-center lg:gap-12 lg:p-12">
            <div>
                <p class="section-kicker">Percetakan online</p>
                <h1 class="mt-3 max-w-xl text-3xl font-black leading-[1.08] tracking-[-0.04em] text-ink-950 sm:text-4xl lg:text-[3.25rem]">
                    Cetak kebutuhan usaha dan hadiah, dari desain sampai jadi.
                </h1>
                <p class="mt-5 max-w-xl text-base leading-7 text-ink-600">
                    Pilih produk, atur ukuran dan jumlah, lalu lihat estimasi harga sebelum masuk keranjang.
                    Setelah dibayar, status produksi dan pengiriman bisa dipantau dari dashboard.
                </p>
                <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                    <x-button :href="route('products.index')" size="lg">
                        Lihat katalog <x-icon name="arrow-right" class="h-4 w-4" />
                    </x-button>
                    <x-button :href="route('tracking.index')" variant="outline" size="lg">
                        <x-icon name="search" class="h-4 w-4" /> Lacak pesanan
                    </x-button>
                </div>
                <dl class="mt-8 grid gap-4 border-t border-ink-100 pt-6 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-ink-500">Katalog</dt>
                        <dd class="mt-1 font-bold text-ink-900">{{ $categoryList->count() }} kategori produk</dd>
                    </div>
                    <div>
                        <dt class="text-ink-500">Harga</dt>
                        <dd class="mt-1 font-bold text-ink-900">Estimasi sebelum bayar</dd>
                    </div>
                    <div>
                        <dt class="text-ink-500">Pesanan</dt>
                        <dd class="mt-1 font-bold text-ink-900">Status bisa dipantau</dd>
                    </div>
                </dl>
            </div>

            @if ($showcaseProduct)
                <a href="{{ route('products.show', $showcaseProduct) }}" class="group block overflow-hidden rounded-2xl border border-ink-200 bg-ink-50 transition hover:border-brand-300 hover:shadow-panel-lg">
                    <div class="overflow-hidden">
                        <x-product-image :src="$showcaseImage" :alt="data_get($showcaseProduct, 'name', 'Produk Kilat Print')" fit="contain" class="aspect-[4/3] transition duration-300 group-hover:scale-[1.02]" />
                    </div>
                    <div class="border-t border-ink-200 bg-white p-5">
                        @if (data_get($showcaseProduct, 'category.name'))
                            <p class="text-2xs font-extrabold tracking-[0.14em] text-brand-700 uppercase">{{ data_get($showcaseProduct, 'category.name') }}</p>
                        @endif
                        <h2 class="mt-1 text-xl font-extrabold tracking-tight text-ink-950">{{ data_get($showcaseProduct, 'name', 'Produk Kilat Print') }}</h2>
                        <p class="mt-2 text-sm text-ink-500">
                            {{ $showcasePriceLabel }}
                            <span class="ml-1 font-extrabold text-brand-700"><x-money :value="$showcasePrice" /></span>
                        </p>
                        <span class="action-link mt-3 text-brand-700">Lihat detail produk <x-icon name="arrow-right" class="h-4 w-4" /></span>
                    </div>
                </a>
            @endif
        </div>
    </div>
</section>

{{-- ========================== CARA PESAN ========================== --}}
<section class="page-shell mt-6" aria-labelledby="how-to-order-title">
    <div class="panel p-5 sm:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Alur pesan</p>
                <h2 id="how-to-order-title" class="mt-1 text-xl font-extrabold text-ink-950">Tiga langkah sebelum produksi</h2>
            </div>
            <a href="{{ route('products.index') }}" class="action-link">Mulai dari katalog <x-icon name="arrow-right" class="h-4 w-4" /></a>
        </div>
        <ol class="mt-5 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['title' => 'Pilih dan atur', 'text' => 'Tentukan produk, ukuran, jumlah, dan metode produksi.'],
                ['title' => 'Periksa estimasi', 'text' => 'Lihat harga per item dan total sebelum menambahkan ke keranjang.'],
                ['title' => 'Bayar dan pantau', 'text' => 'Selesaikan pembayaran, lalu ikuti status produksi sampai selesai.'],
            ] as $step)
                <li class="rounded-xl border border-ink-200 bg-ink-50 p-4">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 text-sm font-black text-white">{{ $loop->iteration }}</span>
                    <p class="mt-3 font-bold text-ink-950">{{ $step['title'] }}</p>
                    <p class="mt-1 text-sm leading-6 text-ink-500">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ========================= TRUST STRIP ========================== --}}
<section class="border-y border-ink-200 bg-white mt-8">
    <div class="page-shell grid gap-5 py-6 sm:grid-cols-3">
        @foreach ([
            ['icon' => 'calculator', 'title' => 'Harga terlihat di awal', 'text' => 'Estimasi dihitung dari aturan harga produk, bukan tebakan.'],
            ['icon' => 'clock', 'title' => 'Status pesanan jelas', 'text' => 'Pembayaran, produksi, dan pengiriman tercatat berurutan.'],
            ['icon' => 'palette', 'title' => 'Dua cara menyiapkan desain', 'text' => 'Unggah file sendiri atau rancang langsung di editor.'],
        ] as $feature)
            <div class="flex items-start gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700"><x-icon :name="$feature['icon']" class="h-5 w-5" /></span>
                <div>
                    <p class="text-sm font-bold text-ink-950">{{ $feature['title'] }}</p>
                    <p class="mt-0.5 text-sm leading-6 text-ink-500">{{ $feature['text'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- ============================ CATEGORIES ========================= --}}
<section class="page-shell py-12 sm:py-16" x-data="{ categoriesOpen: false }">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="section-kicker">Kategori</p>
            <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">Mulai dari kebutuhan Anda</h2>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            @if ($categoryList->count() > $previewCategories->count())
                <button
                    type="button"
                    @click="categoriesOpen = !categoriesOpen"
                    :aria-expanded="categoriesOpen.toString()"
                    aria-controls="home-categories"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-bold text-ink-700 transition hover:bg-ink-100 hover:text-brand-700"
                >
                    <span x-text="categoriesOpen ? 'Sembunyikan kategori' : 'Tampilkan semua kategori'"></span>
                    <x-icon name="chevron-down" class="h-4 w-4 transition-transform duration-300" x-bind:class="categoriesOpen ? 'rotate-180' : ''" />
                </button>
            @endif
            <a href="{{ route('products.index') }}" class="action-link">Lihat semua produk <x-icon name="arrow-right" class="h-4 w-4" /></a>
        </div>
    </div>

    @if ($categoryList->isNotEmpty())
        <div class="mt-2">
            @foreach ($previewCategories as $category)
                <a href="{{ route('products.index', ['category' => data_get($category, 'slug', data_get($category, 'id'))]) }}" class="group mt-6 flex min-h-11 items-center justify-between gap-5 border-b border-ink-200 py-4 transition hover:border-brand-300">
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
            @endforeach

            @if ($categoryList->count() > $previewCategories->count())
                <div
                    id="home-categories"
                    x-show="categoriesOpen"
                    x-cloak
                    class="mt-2"
                >
                    @foreach ($categoryList->skip($previewCategories->count()) as $category)
                        <a href="{{ route('products.index', ['category' => data_get($category, 'slug', data_get($category, 'id'))]) }}" class="group mt-6 flex min-h-11 items-center justify-between gap-5 border-b border-ink-200 py-4 transition hover:border-brand-300">
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
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <div class="panel mt-7">
            <x-empty-state title="Kategori belum tersedia" description="Katalog akan menampilkan kategori setelah data produk ditambahkan." icon="category" />
        </div>
    @endif
</section>

{{-- ======================= PRODUCT HIGHLIGHTS ==================== --}}
<section class="border-y border-ink-200 bg-ink-50 py-12 sm:py-16">
    <div class="page-shell">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Katalog</p>
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">Produk pilihan</h2>
                <p class="mt-2 text-sm text-ink-500">Produk populer dan terbaru, ditampilkan tanpa duplikasi.</p>
            </div>
            <a href="{{ route('products.index', ['sort' => 'popular']) }}" class="action-link">Katalog lengkap <x-icon name="arrow-right" class="h-4 w-4" /></a>
        </div>

        @if ($catalogHighlights->isNotEmpty())
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($catalogHighlights as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        @else
            <div class="panel mt-8">
                <x-empty-state title="Belum ada produk" description="Produk yang sudah aktif akan tampil di sini." icon="box">
                    <x-button :href="route('products.index')" variant="outline" size="sm">Lihat katalog</x-button>
                </x-empty-state>
            </div>
        @endif
    </div>
</section>

{{-- ============================== CTA ============================= --}}
<section class="page-shell py-14 sm:py-20">
    <div class="brand-surface brand-stripes overflow-hidden rounded-2xl px-6 py-10 sm:px-12 sm:py-14">
        <div class="pointer-events-none absolute inset-y-0 right-0 hidden w-1/3 bg-linear-to-l from-accent-400/15 to-transparent lg:block"></div>
        <div class="relative max-w-2xl">
            <p class="inline-flex items-center gap-2 rounded-full bg-accent-400 px-3 py-1 text-2xs font-black tracking-[0.16em] text-ink-950 uppercase">
                <x-icon name="zap" class="h-3 w-3" /> Mulai dari satu produk
            </p>
            <h2 class="mt-5 text-3xl font-black tracking-tight text-white sm:text-4xl">Siapkan desain, kami bantu sampai pesanan selesai.</h2>
            <p class="mt-4 text-base leading-7 text-brand-100">Pilih produk di katalog, gunakan editor desain bila perlu, lalu selesaikan pembayaran dan pantau produksinya.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <x-button :href="route('products.index')" variant="accent" size="lg">Lihat katalog <x-icon name="arrow-right" class="h-5 w-5" /></x-button>
                <x-button :href="route('tracking.index')" variant="outline" size="lg" class="!border-white/40 !bg-white/10 !text-white hover:!bg-white/20">Lacak pesanan</x-button>
            </div>
        </div>
    </div>
</section>
@endsection
