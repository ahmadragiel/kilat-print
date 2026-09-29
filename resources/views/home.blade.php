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
<section class="page-shell pt-6 sm:pt-8">
    <div class="relative overflow-hidden rounded-[2rem] border border-[#f4a5a5] bg-[#f4f2f1] px-5 pb-5 pt-4 shadow-panel-lg sm:px-7 sm:pb-7 lg:px-9 lg:pb-8">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(244,63,80,0.06),_transparent_26%),radial-gradient(circle_at_bottom_right,_rgba(251,191,36,0.08),_transparent_28%)]"></div>

        <div class="relative pb-5 sm:pb-6"></div>

        <div class="relative grid items-end gap-7 lg:grid-cols-[0.9fr_1.1fr]">
            <div class="max-w-lg pt-2 sm:pt-4">
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-ink-900 bg-ink-950 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.18em] text-white">
                    <span class="h-2 w-2 rounded-full bg-brand-400"></span> KILAT PRINT READY
                </p>
                <h1 class="max-w-md text-3xl font-black leading-none tracking-[-0.06em] text-ink-900 sm:text-4xl lg:text-[3rem]">
                    CETAK CEPAT.<br>
                    <span class="block text-ink-900">TAMPIL KONSISTEN.</span>
                </h1>
            </div>

            <div class="flex justify-end">
                <div class="w-full max-w-xl">
                    <div class="mb-4 text-right text-[10px] font-bold uppercase tracking-[0.2em] text-ink-500">Best Seller</div>
                </div>
            </div>
        </div>

        @php
            $showcaseProducts = collect($featuredProducts ?? [])
                ->concat($latest)
                ->unique(fn ($product) => data_get($product, 'id') ?? data_get($product, 'slug'))
                ->take(3)
                ->map(function ($product) {
                $image = data_get($product, 'thumbnail_url')
                    ?? data_get($product, 'thumbnail')
                    ?? data_get($product, 'front_mockup')
                    ?? data_get($product, 'back_mockup')
                    ?? data_get($product, 'image_url')
                    ?? data_get($product, 'image')
                    ?? 'images/mockups/product-front.svg';
                $imageUrl = filled($image) && ! preg_match('#^(https?:|//|/|data:)#i', (string) $image)
                    ? (str_starts_with($image, 'images/mockups/') ? asset($image) : \Illuminate\Support\Facades\Storage::disk('public')->url($image))
                    : $image;

                return [
                    'name' => data_get($product, 'name', 'Featured Product'),
                    'image' => $imageUrl,
                    'url' => route('products.show', $product),
                ];
            })->values();
        @endphp

        @if ($showcaseProducts->isNotEmpty())
            <div
                class="relative mt-8"
                x-data='{
                    active: 0,
                    products: @json($showcaseProducts, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP),
                    slideStyle(index) {
                        const offset = (index - this.active + this.products.length) % this.products.length;
                        const base = { left: "50%", top: 0, pointerEvents: "auto" };

                        if (offset === 0) return { ...base, transform: "translate(-50%, 0) scale(1)", opacity: 1, zIndex: 20 };
                        if (offset === 1) return { ...base, transform: "translate(25%, 5%) scale(.82)", opacity: .62, zIndex: 10 };
                        if (offset === this.products.length - 1) return { ...base, transform: "translate(-125%, 5%) scale(.82)", opacity: .62, zIndex: 10 };

                        return { ...base, transform: "translate(50%, 5%) scale(.72)", opacity: 0, zIndex: 0, pointerEvents: "none" };
                    }
                }'
                x-init="setInterval(() => { active = (active + 1) % products.length; }, 6000)"
            >
                <div class="relative mx-auto h-[420px] w-full max-w-6xl overflow-hidden sm:h-[460px]">
                    <template x-for="(product, index) in products" :key="index">
                        <div
                            :style="slideStyle(index)"
                            class="absolute inset-y-0 flex w-[84%] flex-col overflow-hidden rounded-[1.8rem] border border-[#f4a5a5] bg-[#f7f1ea] p-3 shadow-lg transition-all duration-[6000ms] ease-in-out md:w-[62%]"
                        >
                            <div class="min-h-0 flex-1 overflow-hidden rounded-[1.5rem] bg-[#efe2d6] p-3 sm:p-5">
                                <img :src="product.image || '/images/placeholder-product.svg'" :alt="product.name" class="h-full w-full object-contain" />
                            </div>

                            <div
                                :class="active === index ? 'h-28 opacity-100' : 'pointer-events-none h-0 opacity-0'"
                                class="flex shrink-0 flex-col items-center justify-center overflow-hidden text-center transition-all duration-300"
                            >
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-brand-600">Featured</p>
                                <h3 x-text="product.name" class="mt-2 line-clamp-1 text-xl font-black tracking-tight text-ink-900"></h3>
                                <a :href="product.url" class="mt-3 inline-flex items-center justify-center rounded-full bg-ink-950 px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-white transition hover:bg-brand-600">
                                    Lihat produk
                                </a>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-4 flex items-center justify-center gap-2">
                    <template x-for="(product, index) in products" :key="index">
                        <button
                            type="button"
                            @click="active = index"
                            :class="active === index ? 'w-8 bg-brand-600' : 'w-3 bg-ink-300'"
                            class="h-3 rounded-full transition-all duration-300"
                            :aria-label="'Show product ' + (index + 1)"
                        ></button>
                    </template>
                </div>
            </div>
        @endif
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
<section class="page-shell py-14 sm:py-16" x-data="{ categoriesOpen: false }">
    @php
        $categoryList = collect($categories ?? []);
        $previewCategories = $categoryList->take(2);
        $remainingCategories = $categoryList->skip(2);
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="section-kicker">Kategori</p>
            <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">Mulai dari kebutuhan Anda</h2>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            @if ($remainingCategories->isNotEmpty())
                <button
                    type="button"
                    @click="categoriesOpen = !categoriesOpen"
                    :aria-expanded="categoriesOpen.toString()"
                    aria-controls="home-categories"
                    class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm font-bold text-ink-700 transition hover:bg-ink-100 hover:text-brand-700"
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
            @endforeach

            @if ($remainingCategories->isNotEmpty())
                <div
                    id="home-categories"
                    x-show="categoriesOpen"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="-translate-y-3 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-3 opacity-0"
                    class="mt-2"
                >
                    @foreach ($remainingCategories as $category)
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
