@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
@php
    $cart = $cart ?? null;
    $estimate = $estimate ?? null;
    $checkoutCartItems = $items ?? data_get($cart, 'items', []);
    $checkoutCartItems = collect($checkoutCartItems);
    $checkoutAddresses = $addresses ?? (isset($address) ? (is_array($address) ? $address : [$address]) : []);
    $addressRows = collect($checkoutAddresses)->map(fn ($address) => [
        'id' => data_get($address, 'id'),
        'label' => data_get($address, 'label', 'Alamat'),
        'recipient_name' => data_get($address, 'recipient_name', data_get($address, 'recipient', data_get($address, 'name'))),
        'phone' => data_get($address, 'phone'),
        'address_line1' => data_get($address, 'address_line1', data_get($address, 'address', data_get($address, 'street'))),
        'address_line2' => data_get($address, 'address_line2'),
        'district' => data_get($address, 'district'),
        'city' => data_get($address, 'city'),
        'province' => data_get($address, 'province'),
        'postal_code' => data_get($address, 'postal_code', data_get($address, 'zip_code')),
        'is_primary' => (bool) data_get($address, 'is_primary'),
    ])->values();
    $selectedAddress = collect($addressRows)->firstWhere('is_primary') ?? $addressRows->first() ?? [
        'id' => null, 'label' => '', 'recipient_name' => old('recipient_name'),
        'phone' => old('phone'), 'address_line1' => old('address_line1'), 'address_line2' => old('address_line2'),
        'city' => old('city'), 'province' => old('province'), 'postal_code' => old('postal_code'), 'is_primary' => false,
    ];
    $deliveryFee = $deliveryFee ?? null;
    $shippingMethods = $shippingMethods ?? [
        ['value' => 'pickup', 'label' => 'Ambil di lokasi', 'cost' => 0],
        ['value' => 'delivery', 'label' => 'Kirim ke alamat', 'cost' => $deliveryFee],
    ];
    $checkoutSubtotal = $subtotal ?? (is_object($estimate) && method_exists($estimate, 'sum') ? $estimate->sum('total') : data_get($estimate, 'subtotal'));
    $checkoutShipping = $estimateShipping ?? data_get($estimate, 'shipping_cost', $deliveryFee);
    $checkoutTotal = $grandTotal ?? data_get($estimate, 'grand_total');
@endphp

<div class="page-shell py-8 sm:py-10">
    {{-- Checkout step rail: red for reached steps, yellow for the current one --}}
    <nav class="mb-6 flex flex-wrap items-center gap-2 text-xs font-bold sm:gap-3" aria-label="Tahapan checkout">
        @foreach ([
            ['label' => 'Keranjang', 'state' => 'done'],
            ['label' => 'Alamat & pengiriman', 'state' => 'current'],
            ['label' => 'Pembayaran', 'state' => 'todo'],
            ['label' => 'Selesai', 'state' => 'todo'],
        ] as $stepIndex => $step)
            <span @class([
                'inline-flex items-center gap-2 rounded-full px-3 py-1.5',
                'bg-brand-600 text-white' => $step['state'] === 'done',
                'bg-accent-400 text-ink-950 ring-1 ring-inset ring-accent-500' => $step['state'] === 'current',
                'bg-ink-100 text-ink-500' => $step['state'] === 'todo',
            ])>
                <span @class([
                    'grid h-4 w-4 place-items-center rounded-full text-[10px] font-black',
                    'bg-white/20' => $step['state'] === 'done',
                    'bg-ink-950' => $step['state'] === 'current',
                    'bg-ink-300 text-ink-600' => $step['state'] === 'todo',
                ])>{{ $stepIndex + 1 }}</span>
                {{ $step['label'] }}
            </span>
            @if (! $loop->last)<x-icon name="chevron-right" class="hidden h-3.5 w-3.5 text-ink-300 sm:block" />@endif
        @endforeach
    </nav>

    <x-page-header title="Checkout" description="Pilih alamat pengiriman dan metode pengiriman." eyebrow="Pesanan" />

    @if ($checkoutCartItems->isEmpty())
        <div class="panel mt-8">
            <x-empty-state title="Checkout tidak dapat dilanjutkan" description="Keranjang harus berisi minimal satu item." icon="cart">
                <x-button :href="route('cart.index')" variant="secondary">Kembali ke keranjang</x-button>
            </x-empty-state>
        </div>
    @else
        <form
            method="POST"
            action="{{ \Illuminate\Support\Facades\Route::has('checkout.store') ? route('checkout.store') : route('checkout.store') }}"
            x-data='{ addresses: @json($addressRows, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), selected: @json($selectedAddress, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), chooseAddress(id) { this.selected = { ...(this.addresses.find(address => String(address.id) === String(id)) || {}) }; } }'
            class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_380px]"
        >
            @csrf
            <div class="space-y-6">
                <section class="panel p-5 sm:p-6" aria-labelledby="checkout-address-title">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="section-kicker">Pengiriman</p>
                            <h2 id="checkout-address-title" class="mt-1 text-lg font-extrabold text-ink-950">Alamat tujuan</h2>
                        </div>
                        <a href="{{ \Illuminate\Support\Facades\Route::has('customer.addresses') ? route('customer.addresses') : route('customer.addresses.index') }}" class="action-link shrink-0"><x-icon name="plus" class="h-4 w-4" /> Alamat baru</a>
                    </div>

                    @if ($addressRows->isNotEmpty())
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            @foreach ($addressRows as $address)
                                <label class="relative flex cursor-pointer gap-3 rounded-lg border p-4 transition" :class="String(selected.id) === String({{ json_encode($address['id']) }}) ? 'border-brand-500 bg-brand-50' : 'border-ink-200 hover:border-brand-300 hover:bg-ink-50'">
                                    <input type="radio" name="address_id" value="{{ $address['id'] }}" x-model="selected.id" x-on:change="chooseAddress({{ json_encode($address['id']) }})" class="form-check mt-1" required>
                                    <span class="min-w-0 text-sm">
                                        <span class="flex flex-wrap items-center gap-2 font-bold text-ink-950">{{ $address['label'] }} @if($address['is_primary'])<x-badge color="accent">Utama</x-badge>@endif</span>
                                        <span class="mt-1 block font-medium text-ink-700">{{ $address['recipient_name'] }}</span>
                                        <span class="mt-1 block leading-5 text-ink-500">{{ $address['address_line1'] }}@if($address['district']), {{ $address['district'] }}@endif @if($address['address_line2']), {{ $address['address_line2'] }}@endif<br>{{ $address['city'] }}, {{ $address['province'] }} {{ $address['postal_code'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <x-alert type="warning" title="Alamat belum tersedia" class="mt-5">Tambahkan alamat pengiriman sebelum melanjutkan.</x-alert>
                        <x-button :href="\Illuminate\Support\Facades\Route::has('customer.addresses') ? route('customer.addresses') : route('customer.addresses.index')" size="sm" class="mt-4"><x-icon name="plus" class="h-4 w-4" /> Tambah alamat</x-button>
                    @endif

                    <input type="hidden" name="name" value="{{ auth()->user()->name }}">
                    <input type="hidden" name="email" value="{{ auth()->user()->email }}">
                    <input type="hidden" name="recipient" x-model="selected.recipient_name">
                    <input type="hidden" name="address_phone" x-model="selected.phone">
                    <input type="hidden" name="address" x-model="selected.address_line1">
                    <input type="hidden" name="district" x-model="selected.district">
                    <input type="hidden" name="recipient_name" x-model="selected.recipient_name">
                    <input type="hidden" name="phone" x-model="selected.phone">
                    <input type="hidden" name="address_line1" x-model="selected.address_line1">
                    <input type="hidden" name="address_line2" x-model="selected.address_line2">
                    <input type="hidden" name="city" x-model="selected.city">
                    <input type="hidden" name="province" x-model="selected.province">
                    <input type="hidden" name="postal_code" x-model="selected.postal_code">
                </section>

                <section class="panel p-5 sm:p-6" aria-labelledby="shipping-method-title">
                    <p class="section-kicker">Ongkos kirim</p>
                    <h2 id="shipping-method-title" class="mt-1 text-lg font-extrabold text-ink-950">Metode pengiriman</h2>
                    <div class="mt-5 space-y-3">
                        @forelse ($shippingMethods as $method)
                            @php
                                $methodValue = data_get($method, 'value', data_get($method, 'code', is_string($method) ? $method : null));
                                $methodLabel = data_get($method, 'label', data_get($method, 'name', $methodValue));
                                $methodCost = data_get($method, 'cost', data_get($method, 'price'));
                            @endphp
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-ink-200 p-4 transition hover:border-brand-300 hover:bg-ink-50 has-checked:border-brand-500 has-checked:bg-brand-50">
                                <input type="radio" name="shipping_method" value="{{ $methodValue }}" class="form-check" @checked(old('shipping_method') === (string) $methodValue) required>
                                <span class="min-w-0 flex-1 text-sm font-semibold text-ink-800">{{ $methodLabel }}</span>
                                <span class="shrink-0 text-sm font-bold text-ink-900"><x-money :value="$methodCost" /></span>
                            </label>
                        @empty
                            <x-alert type="warning">Pilihan metode pengiriman belum tersedia dari sistem.</x-alert>
                        @endforelse
                    </div>
                    <x-textarea name="shipping_notes" label="Catatan pengiriman" class="mt-5" placeholder="Informasi tambahan untuk kurir" />
                </section>
            </div>

            <aside class="panel overflow-hidden lg:sticky lg:top-24" aria-labelledby="checkout-summary-title">
                <div class="panel-head">
                    <h2 id="checkout-summary-title" class="font-extrabold text-ink-950">Ringkasan pesanan</h2>
                </div>
                <div class="max-h-72 divide-y divide-ink-100 overflow-y-auto px-5">
                    @foreach ($checkoutCartItems as $item)
                        <div class="flex items-center justify-between gap-4 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink-800">{{ data_get($item, 'product_name', data_get($item, 'product.name', 'Produk')) }}</p>
                                <p class="mt-0.5 text-xs text-ink-500">{{ data_get($item, 'quantity', 1) }} item</p>
                            </div>
                            <span class="shrink-0 font-semibold text-ink-900"><x-money :value="data_get($item, 'price_at_addition', data_get($item, 'line_total', data_get($item, 'subtotal', data_get($item, 'total'))))" /></span>
                        </div>
                    @endforeach
                </div>
                <div class="space-y-3 border-t border-ink-200 p-5 text-sm">
                    <div class="flex justify-between gap-4 text-ink-600"><span>Subtotal</span><span class="font-semibold text-ink-900"><x-money :value="$checkoutSubtotal ?? $checkoutCartItems->sum('price_at_addition')" /></span></div>
                    <div class="flex justify-between gap-4 text-ink-600"><span>Ongkos kirim</span><span class="font-semibold text-ink-900"><x-money :value="$checkoutShipping" /></span></div>
                    <div class="flex items-end justify-between gap-4 rounded-lg bg-accent-100 px-4 py-3">
                        <span class="text-xs font-extrabold tracking-[0.14em] text-accent-800 uppercase">Total</span>
                        <span class="text-xl font-black text-brand-600"><x-money :value="$checkoutTotal" /></span>
                    </div>
                </div>
                <div class="border-t border-ink-200 p-5">
                    <x-button type="submit" size="lg" class="w-full" x-bind:disabled="addresses.length === 0">Buat pesanan <x-icon name="arrow-right" class="h-4 w-4" /></x-button>
                    <p class="mt-3 flex items-start gap-2 text-xs leading-5 text-accent-900"><x-icon name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-accent-600" /> Periksa kembali alamat dan metode pengiriman sebelum melanjutkan.</p>
                </div>
            </aside>
        </form>
    @endif
</div>
@endsection
