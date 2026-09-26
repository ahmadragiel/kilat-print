@props([
    'product',
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
@endphp

<article {{ $attributes->class(['group panel overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:border-ink-300 hover:shadow-md']) }}>
    <a href="{{ route('products.show', $product) }}" class="block overflow-hidden" tabindex="-1" aria-hidden="true">
        <x-product-image :src="$image" :alt="''" class="aspect-[4/3] border-b border-ink-100 transition duration-300 group-hover:scale-[1.02]" />
    </a>
    <div class="p-4 sm:p-5">
        <div class="mb-3 flex items-start justify-between gap-3">
            <div class="min-w-0">
                @if ($category)
                    <p class="mb-1 truncate text-xs font-semibold text-flame-700">{{ $category }}</p>
                @endif
                <h3 class="line-clamp-2 font-bold leading-6 text-ink-950">
                    <a href="{{ route('products.show', $product) }}" class="hover:text-flame-700">{{ $productName }}</a>
                </h3>
            </div>
            @if (data_get($product, 'is_active') === false || data_get($product, 'status') === 'inactive')
                <x-badge color="gray">Nonaktif</x-badge>
            @endif
        </div>
        <div class="flex items-end justify-between gap-3 border-t border-ink-100 pt-4">
            <div>
                <p class="text-[11px] font-medium text-ink-500">Harga</p>
                <p class="mt-0.5 text-sm font-extrabold text-ink-900"><x-money :value="$price" /></p>
            </div>
            <x-button :href="route('products.show', $product)" variant="ghost" size="icon" aria-label="Lihat {{ $productName }}">
                <x-icon name="arrow-right" class="h-4 w-4" />
            </x-button>
        </div>
    </div>
</article>
