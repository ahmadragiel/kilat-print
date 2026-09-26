@extends('layouts.app')

@section('title', 'Keranjang')

@section('content')
<div class="page-shell py-8 sm:py-10">
    <x-page-header title="Keranjang" description="Tinjau item dan jumlah sebelum melanjutkan ke pembayaran." eyebrow="Pesanan" />

    @php
        $cart = $cart ?? null;
        $cartItems = $items ?? data_get($cart, 'items', []);
        $cartItems = collect($cartItems);
        $grandTotal = $grandTotal ?? $cartItems->sum('price_at_addition');
    @endphp

    @if ($cartItems->isNotEmpty())
        <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_360px]">
            <section class="panel overflow-hidden" aria-label="Item keranjang">
                <div class="hidden grid-cols-[1fr_120px_150px_44px] gap-4 border-b border-ink-200 bg-ink-50 px-5 py-3 text-xs font-bold tracking-wide text-ink-500 uppercase sm:grid">
                    <span>Produk</span><span>Jumlah</span><span class="text-right">Subtotal</span><span></span>
                </div>
                <div class="divide-y divide-ink-200">
                    @foreach ($cartItems as $item)
                        @php
                            $itemProduct = data_get($item, 'product');
                            $productName = data_get($item, 'product_name', data_get($itemProduct, 'name', 'Produk'));
                            $image = data_get($item, 'image_url') ?? data_get($itemProduct, 'thumbnail_url') ?? data_get($itemProduct, 'image_url') ?? data_get($itemProduct, 'thumbnail') ?? data_get($itemProduct, 'image');
                            $lineTotal = data_get($item, 'price_at_addition', data_get($item, 'line_total', data_get($item, 'subtotal', data_get($item, 'total'))));
                        @endphp
                        <div class="grid gap-4 p-4 sm:grid-cols-[1fr_120px_150px_44px] sm:items-center sm:p-5">
                            <div class="flex min-w-0 gap-4">
                                <div class="w-20 shrink-0 overflow-hidden rounded-md border border-ink-200 sm:w-24">
                                    <x-product-image :src="$image" :alt="''" class="aspect-square" />
                                </div>
                                <div class="min-w-0 py-0.5">
                                    <h2 class="font-bold text-ink-900">{{ $productName }}</h2>
                                    @if(data_get($item, 'custom_design_draft_id'))
                                        <span class="mt-1.5 inline-flex"><x-badge color="purple">Desain editor</x-badge></span>
                                    @endif
                                    @if (data_get($item, 'material.name'))
                                        <p class="mt-1 text-xs text-ink-500">Material: {{ data_get($item, 'material.name') }}</p>
                                    @endif
                                    @if (data_get($item, 'finishing.name'))
                                        <p class="mt-0.5 text-xs text-ink-500">Finishing: {{ data_get($item, 'finishing.name') }}</p>
                                    @endif
                                    <p class="mt-2 text-sm text-ink-600">Harga konfigurasi: <x-money :value="$lineTotal" /></p>
                                    @if(data_get($item, 'product.status') === 'active')
                                        <a href="{{ route('cart.customize', $item) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-flame-700 hover:text-flame-800"><x-icon name="edit" class="h-3.5 w-3.5" /> Edit konfigurasi & desain</a>
                                    @endif
                                </div>
                            </div>

                            <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('customer.cart.update') ? route('customer.cart.update', $item) : route('cart.update', $item) }}" class="flex items-center gap-2 sm:block">
                                @csrf
                                @method('PATCH')
                                <label for="quantity-{{ data_get($item, 'id') }}" class="mb-1 block text-xs font-semibold text-ink-500 sm:hidden">Jumlah</label>
                                <input id="quantity-{{ data_get($item, 'id') }}" name="quantity" type="number" min="1" value="{{ data_get($item, 'quantity', 1) }}" class="form-control min-h-10 py-2" aria-label="Jumlah {{ $productName }}">
                                <input type="hidden" name="size" value="{{ data_get($item, 'size') }}">
                                <input type="hidden" name="length_cm" value="{{ data_get($item, 'length_cm') }}">
                                <input type="hidden" name="width_cm" value="{{ data_get($item, 'width_cm') }}">
                                <input type="hidden" name="material_id" value="{{ data_get($item, 'material_id') }}">
                                <input type="hidden" name="finishing_id" value="{{ data_get($item, 'finishing_id') }}">
                                <input type="hidden" name="color" value="{{ data_get($item, 'color') }}">
                                <input type="hidden" name="production_method" value="{{ data_get($item, 'production_method') }}">
                                <input type="hidden" name="notes" value="{{ data_get($item, 'notes') }}">
                                <button type="submit" class="mt-1.5 text-xs font-semibold text-flame-700 hover:text-flame-800">Perbarui</button>
                            </form>

                            <div class="sm:text-right">
                                <p class="text-xs text-ink-500 sm:hidden">Subtotal</p>
                                <p class="font-extrabold text-ink-950"><x-money :value="$lineTotal" /></p>
                            </div>

                            <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('customer.cart.remove') ? route('customer.cart.remove', $item) : route('cart.destroy', $item) }}" class="flex justify-end">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="grid h-10 w-10 place-items-center rounded-md text-ink-400 transition hover:bg-red-50 hover:text-red-600" aria-label="Hapus {{ $productName }}"><x-icon name="trash" class="h-4 w-4" /></button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>

            <aside class="panel overflow-hidden lg:sticky lg:top-24" aria-labelledby="cart-summary-title">
                <div class="border-b border-ink-200 bg-ink-50 px-5 py-4">
                    <h2 id="cart-summary-title" class="font-extrabold text-ink-950">Ringkasan</h2>
                </div>
                <div class="p-5">
                    <div class="flex items-center justify-between text-sm text-ink-600">
                        <span>Subtotal</span>
                        <span class="font-semibold text-ink-900"><x-money :value="$grandTotal" /></span>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-ink-500">Ongkos kirim dan total akhir dihitung pada tahap checkout.</p>
                    <x-button :href="route('checkout.index')" class="mt-5 w-full">Lanjut checkout <x-icon name="arrow-right" class="h-4 w-4" /></x-button>
                    <a href="{{ route('products.index') }}" class="mt-3 block text-center text-sm font-semibold text-ink-600 hover:text-flame-700">Tambah produk lain</a>
                </div>
            </aside>
        </div>
    @else
        <div class="panel mt-8">
            <x-empty-state title="Keranjang masih kosong" description="Pilih produk dari katalog untuk mulai memesan." icon="cart">
                <x-button :href="route('products.index')">Jelajahi katalog</x-button>
            </x-empty-state>
        </div>
    @endif
</div>
@endsection
