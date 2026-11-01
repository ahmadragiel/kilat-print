@extends('layouts.admin')

@php
    $invoice = $invoice ?? null;
    $businessAddress = trim((string) config('app.address', ''));
    $businessPhone = trim((string) config('app.phone', ''));
    $businessEmail = trim((string) config('app.email', ''));
    $invoiceAddress = collect([
        data_get($order, 'address.address', data_get($order, 'shipping_address.address_line1')),
        data_get($order, 'address.district', data_get($order, 'shipping_address.address_line2')),
        trim(collect([data_get($order, 'address.city', data_get($order, 'shipping_address.city')), data_get($order, 'address.province', data_get($order, 'shipping_address.province'))])->filter()->implode(', ').' '.data_get($order, 'address.postal_code', data_get($order, 'shipping_address.postal_code'))),
    ])->filter(fn ($line) => filled($line));
    $shippingMethod = (string) data_get($order, 'shipping_method', '');
    $shippingMethodLabel = match ($shippingMethod) {
        'pickup' => 'Ambil di lokasi (pesanan lama)',
        'delivery' => 'Kirim ke alamat',
        default => 'Belum ditentukan',
    };
@endphp

@section('title', 'Faktur')

@section('content')
<div class="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
    <x-page-header title="Faktur pesanan" description="Pratinjau dokumen faktur untuk pesanan yang dipilih." eyebrow="Dokumen" />
    <x-button type="button" onclick="window.print()" variant="secondary"><x-icon name="printer" class="h-4 w-4" /> Cetak faktur</x-button>
</div>

<article class="print-sheet mx-auto max-w-4xl rounded-xl border border-ink-200 bg-white p-6 shadow-panel sm:p-10" aria-label="Faktur Kilat Print">
    <header class="flex flex-col gap-6 border-b border-ink-200 pb-7 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <x-brand />
            <div class="mt-4 space-y-0.5 text-sm leading-6 text-ink-500">
                <p class="font-semibold text-ink-800">{{ config('app.name', 'Kilat Print') }}</p>
                @if ($businessAddress)<p>{{ $businessAddress }}</p>@endif
                @if ($businessPhone)<p>Telepon: {{ $businessPhone }}</p>@endif
                @if ($businessEmail)<p>{{ $businessEmail }}</p>@endif
            </div>
        </div>
        <div class="sm:text-right">
            <p class="inline-flex items-center gap-2 rounded-full bg-accent-100 px-3 py-1 text-2xs font-black tracking-[0.14em] text-accent-800 uppercase ring-1 ring-inset ring-accent-300">Faktur</p>
            <p class="mt-3 text-2xl font-black text-brand-700">#{{ data_get($invoice, 'number', data_get($order, 'invoice_number', data_get($order, 'number', data_get($order, 'order_number', data_get($order, 'id'))))) }}</p>
            <p class="mt-1 text-sm text-ink-500">{{ data_get($invoice, 'issued_at', data_get($order, 'created_at'))?->translatedFormat('d F Y') ?? 'Tanggal belum tersedia' }}</p>
            <p class="mt-2 inline-flex"><x-status-badge :status="data_get($order, 'payment.status', data_get($order, 'payment_status'))" /></p>
        </div>
    </header>

    <section class="grid gap-6 border-b border-ink-200 py-7 sm:grid-cols-2">
        <div>
            <p class="text-2xs font-bold tracking-wide text-ink-500 uppercase">Ditagihkan kepada</p>
            <p class="mt-2 font-bold text-ink-950">{{ data_get($invoice, 'customer_name', data_get($order, 'customer.user.name', data_get($order, 'customer.name', data_get($order, 'customer_name', 'Pelanggan')))) }}</p>
            <p class="mt-1 text-sm leading-6 text-ink-500">
                @if (data_get($invoice, 'customer_email', data_get($order, 'customer.user.email', data_get($order, 'customer.email')))){{ data_get($invoice, 'customer_email', data_get($order, 'customer.user.email', data_get($order, 'customer.email'))) }}<br>@endif
                {{ data_get($invoice, 'customer_phone', data_get($order, 'customer.user.phone', data_get($order, 'customer.phone'))) }}
            </p>
        </div>
        <div class="sm:text-right">
            <p class="text-2xs font-bold tracking-wide text-ink-500 uppercase">Alamat</p>
            <address class="mt-2 text-sm not-italic leading-6 text-ink-600">
                @forelse ($invoiceAddress as $line)
                    {{ $line }}@if (! $loop->last)<br>@endif
                @empty
                    <span class="text-ink-500">Belum tersedia</span>
                @endforelse
            </address>
            <p class="mt-2 text-xs text-ink-500">Metode pengiriman: <span class="font-semibold text-ink-700">{{ $shippingMethodLabel }}</span></p>
        </div>
    </section>

    <section class="py-7">
        <div class="overflow-x-auto">
            <table class="data-table data-table-brand">
                <thead><tr><th>Deskripsi</th><th>Qty</th><th class="text-right">Harga</th><th class="text-right">Jumlah</th></tr></thead>
                <tbody>
                    @forelse ($order->items as $item)
                        <tr>
                            <td class="font-semibold text-ink-950">
                                {{ $item->product_name }}
                                <p class="mt-1 text-xs font-normal text-ink-500">{{ collect([$item->variant_label, $item->size, $item->color, $item->production_method])->filter()->implode(' • ') }}</p>
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td class="text-right"><x-money :value="$item->unit_price" /></td>
                            <td class="text-right font-bold text-ink-950"><x-money :value="$item->line_total" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-ink-500">Detail item invoice belum tersedia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ml-auto mt-6 max-w-xs space-y-3 text-sm">
            <div class="flex justify-between gap-5 text-ink-600"><span>Subtotal</span><span class="font-semibold text-ink-900"><x-money :value="$order->subtotal" /></span></div>
            <div class="flex justify-between gap-5 text-ink-600"><span>Ongkos kirim</span><span class="font-semibold text-ink-900"><x-money :value="$order->shipping_fee" /></span></div>
            <div class="flex items-end justify-between gap-5 border-t-2 border-brand-600 pt-3">
                <span class="text-xs font-extrabold tracking-[0.14em] text-brand-700 uppercase">Total</span>
                <span class="text-xl font-black text-brand-700"><x-money :value="$order->grand_total" /></span>
            </div>
        </div>
    </section>

    <footer class="border-t border-ink-200 pt-6 text-xs leading-5 text-ink-500">
        <p>Terima kasih atas kepercayaan Anda menggunakan Kilat Print.</p>
        @if(data_get($invoice, 'notes'))<p class="mt-2">{{ data_get($invoice, 'notes') }}</p>@endif
    </footer>
</article>
@endsection
