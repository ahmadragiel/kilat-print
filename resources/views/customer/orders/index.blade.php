@extends('layouts.app')

@section('title', 'Pesanan Saya')

@section('content')
<div class="page-shell py-8 sm:py-10">
    <x-page-header title="Pesanan saya" description="Lihat status dan akses detail setiap pesanan." eyebrow="Akun pelanggan" />

    <form method="GET" action="{{ route('customer.orders.index') }}" class="panel mt-7 grid gap-3 p-4 sm:grid-cols-[1fr_200px_auto]" aria-label="Filter pesanan">
        <div class="relative">
            <label for="search" class="sr-only">Cari nomor pesanan</label>
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
            <input id="search" name="search" value="{{ request('search') }}" class="form-control pl-10" placeholder="Nomor pesanan">
        </div>
        <div>
            <label for="status" class="sr-only">Status pesanan</label>
            <select id="status" name="status" class="form-control">
                <option value="">Semua status</option>
                @foreach (($statuses ?? [
                    'PENDING_PAYMENT' => 'Menunggu pembayaran',
                    'PAYMENT_REVIEW' => 'Verifikasi pembayaran',
                    'PAYMENT_CONFIRMED' => 'Pembayaran dikonfirmasi',
                    'DESIGN_REVIEW' => 'Review desain',
                    'DESIGN_REVISION' => 'Revisi desain',
                    'DESIGN_APPROVED' => 'Desain disetujui',
                    'WAITING_PRODUCTION' => 'Menunggu produksi',
                    'IN_PRODUCTION' => 'Dalam produksi',
                    'FINISHING' => 'Finishing',
                    'QUALITY_CHECK' => 'Pemeriksaan kualitas',
                    'READY' => 'Siap kirim',
                    'SHIPPED' => 'Dikirim',
                    'COMPLETED' => 'Selesai',
                    'CANCELLED' => 'Dibatalkan',
                ]) as $statusValue => $statusLabel)
                    <option value="{{ is_int($statusValue) ? $statusLabel : $statusValue }}" @selected(request('status') === (string) $statusValue)>{{ is_int($statusValue) ? str_replace('_', ' ', $statusLabel) : $statusLabel }}</option>
                @endforeach
            </select>
        </div>
        <x-button type="submit"><x-icon name="filter" class="h-4 w-4" /> Filter</x-button>
    </form>

    <div class="panel mt-6 overflow-hidden">
        @if (collect($orders ?? [])->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Nomor pesanan</th><th>Tanggal</th><th>Item</th><th>Total</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('customer.orders.show', $order) }}" class="font-bold text-ink-900 hover:text-flame-700">#{{ data_get($order, 'number', data_get($order, 'order_number', data_get($order, 'id'))) }}</a>
                                    @if (data_get($order, 'payment_status'))<p class="mt-1 text-xs text-ink-500">Pembayaran: <x-status-badge :status="data_get($order, 'payment_status')" /></p>@endif
                                </td>
                                <td class="whitespace-nowrap">{{ data_get($order, 'created_at')?->format('d M Y, H:i') ?? 'Belum tersedia' }}</td>
                                <td>
                                    <p class="max-w-xs truncate font-medium text-ink-800">{{ data_get($order, 'items.0.product_name', data_get($order, 'product.name', data_get($order, 'product_name', 'Produk'))) }}</p>
                                    @if (data_get($order, 'items_count'))<p class="mt-0.5 text-xs text-ink-500">{{ data_get($order, 'items_count') }} item</p>@endif
                                </td>
                                <td class="font-semibold text-ink-900"><x-money :value="data_get($order, 'grand_total', data_get($order, 'total'))" /></td>
                                <td><x-status-badge :status="data_get($order, 'status')" /></td>
                                <td class="text-right"><a href="{{ route('customer.orders.show', $order) }}" class="action-link">Detail <x-icon name="chevron-right" class="h-4 w-4" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (method_exists($orders ?? [], 'links'))
                <div class="border-t border-ink-200 px-4 py-4">{{ $orders->withQueryString()->links() }}</div>
            @endif
        @else
            <x-empty-state title="Belum ada pesanan" description="Pesanan yang dibuat akan tampil di daftar ini." icon="shopping-bag">
                <x-button :href="route('products.index')">Mulai pesan</x-button>
            </x-empty-state>
        @endif
    </div>
</div>
@endsection
