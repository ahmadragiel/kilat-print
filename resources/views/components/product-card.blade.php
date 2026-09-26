@props([
    'product',
    'badge' => null,
])

@php
    $productName = data_get($product, 'name', 'Produk Kilat Print');
    $image = data_get($product, 'thumbnail_url') ?? data_get($product, 'image_url') ?? data_get($product, 'thumbnail') ?? data_get($product, 'image');
    $price = data_get($product, 'starting_price') ?? data_get($product, 'min_price') ?? data_get($product, 'base_price') ?? data_get($product, 'price');
    $priceRules = data_get($product, 'priceRules');
    if ($price === null && is_object($priceRules) && method_exists($priceRules, 'min')) {
        $price = $priceRules->min('price');
    }
    $category = data_get($product, 'category.name') ?? data_get($product, 'category_name');
    $isNew = (bool) (data_get($product, 'is_new') || data_get($product, 'is_new_product') || data_get($product, 'is_featured'));
@endphp

<article {{ $attributes->class(['group panel flex flex-col overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-panel-lg']) }}>
    <a href="{{ route('products.show', $product) }}" class="relative block overflow-hidden bg-ink-50" tabindex="-1" aria-hidden="true">
        <x-product-image :src="$image" :alt="''" class="aspect-[4/3] transition duration-300 group-hover:scale-[1.03]" />
        @if ($badge)
            <span class="absolute left-3 top-3"><x-badge color="accent-solid" class="shadow-sm">{{ $badge }}</x-badge></span>
        @elseif ($isNew)
            <span class="absolute left-3 top-3"><x-badge color="accent-solid" class="shadow-sm">Baru</x-badge></span>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-4 sm:p-5">
        <div class="mb-3">
            @if ($category)
                <p class="mb-1.5 inline-flex items-center gap-1.5 text-2xs font-extrabold tracking-[0.14em] text-brand-600 uppercase">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent-400"></span>{{ $category }}
                </p>
            @endif
            <h3 class="line-clamp-2 font-bold leading-6 text-ink-950">
                <a href="{{ route('products.show', $product) }}" class="transition hover:text-brand-700">{{ $productName }}</a>
            </h3>
            @if (data_get($product, 'is_active') === false || data_get($product, 'status') === 'inactive')
                <x-badge color="gray" class="mt-2">Nonaktif</x-badge>
            @endif
        </div>
        <div class="mt-auto flex items-end justify-between gap-3 border-t border-ink-100 pt-4">
            <div>
                <p class="text-2xs font-bold tracking-wide text-ink-500 uppercase">Harga mulai</p>
                <p class="mt-1 text-lg font-black text-brand-600"><x-money :value="$price" /></p>
            </div>
            <x-button :href="route('products.show', $product)" size="icon" class="shrink-0" aria-label="Lihat {{ $productName }}">
                <x-icon name="arrow-right" class="h-4 w-4" />
            </x-button>
        </div>
    </div>
</article>
