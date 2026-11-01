@extends('layouts.admin')

@section('title', 'Pesanan')

@section('content')
@php
    $statusOptions = $statuses ?? [];
    $paymentStatusOptions = $paymentStatuses ?? [];
    $statusValue = fn ($status) => $status instanceof \BackedEnum ? $status->value : (is_array($status) ? data_get($status, 'value', data_get($status, 'key')) : $status);
    $statusLabel = fn ($status) => $status instanceof \UnitEnum && method_exists($status, 'label') ? $status->label() : (is_array($status) ? data_get($status, 'label', data_get($status, 'name')) : $status);
    $hasFilters = request()->filled('search') || request()->filled('status') || request()->filled('payment');
@endphp
<div class="space-y-7">
    <x-page-header title="Pesanan" description="Kelola status dan detail pesanan pelanggan." eyebrow="Operasional" />

    <form method="GET" action="{{ route('admin.orders.index') }}" class="panel grid gap-3 p-4 sm:grid-cols-[1fr_200px_200px_auto]" aria-label="Filter pesanan admin">
        <div class="relative">
            <label for="search" class="sr-only">Cari pesanan</label>
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" />
            <input id="search" name="search" value="{{ request('search') }}" class="form-control pl-10" placeholder="Nomor pesanan atau pelanggan">
        </div>
        <div>
            <label for="status" class="sr-only">Status pesanan</label>
            <select id="status" name="status" class="form-control">
                <option value="">Semua status</option>
                @foreach($statusOptions as $status)
                    <option value="{{ $statusValue($status) }}" @selected(request('status') === (string) $statusValue($status))>{{ $statusLabel($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="payment" class="sr-only">Status pembayaran</label>
            <select id="payment" name="payment" class="form-control">
                <option value="">Semua pembayaran</option>
                @foreach($paymentStatusOptions as $paymentStatus)
                    <option value="{{ $statusValue($paymentStatus) }}" @selected(request('payment') === (string) $statusValue($paymentStatus))>{{ $statusLabel($paymentStatus) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <x-button type="submit" class="flex-1"><x-icon name="filter" class="h-4 w-4" /> Filter</x-button>
            @if($hasFilters)
                <x-button :href="route('admin.orders.index')" variant="secondary" size="icon" aria-label="Reset filter"><x-icon name="refresh" class="h-4 w-4" /></x-button>
            @endif
        </div>
    </form>

    <div class="panel overflow-hidden">
        @if (collect($orders ?? [])->isNotEmpty())
            <div class="divide-y divide-ink-100 lg:hidden">
                @foreach ($orders as $order)
                    <article class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.orders.show', $order) }}" class="inline-flex min-h-11 items-center font-bold text-ink-950 transition hover:text-brand-700">#{{ data_get($order, 'number', data_get($order, 'order_number', data_get($order, 'id'))) }}</a>
                                <p class="mt-1 truncate text-xs text-ink-500">{{ data_get($order, 'customer.user.name', data_get($order, 'customer.name', data_get($order, 'customer_name', 'Pelanggan'))) }}</p>
                            </div>
                            <x-status-badge :status="data_get($order, 'status')" class="shrink-0" />
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-500">
                            <span>{{ data_get($order, 'created_at')?->translatedFormat('d M Y, H:i') ?? 'Belum tersedia' }}</span>
                            <span>{{ data_get($order, 'items_count', count(data_get($order, 'items', []))) }} item</span>
                        </div>
                        <div class="mt-3 flex items-end justify-between gap-3">
                            <div>
                                <p class="text-xs text-ink-500">Total</p>
                                <p class="font-extrabold text-brand-700"><x-money :value="data_get($order, 'grand_total', data_get($order, 'total'))" /></p>
                            </div>
                            <a href="{{ route('admin.orders.show', $order) }}" class="action-link">Detail <x-icon name="chevron-right" class="h-4 w-4" /></a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <table class="data-table">
                    <thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th>Pembayaran</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}" class="font-bold text-ink-900 hover:text-brand-700">#{{ data_get($order, 'number', data_get($order, 'order_number', data_get($order, 'id'))) }}</a><p class="mt-1 text-xs text-ink-500">{{ data_get($order, 'items_count', count(data_get($order, 'items', []))) }} item</p></td>
                                <td><p class="font-semibold text-ink-800">{{ data_get($order, 'customer.user.name', data_get($order, 'customer.name', data_get($order, 'customer_name', 'Pelanggan'))) }}</p><p class="mt-1 text-xs text-ink-500">{{ data_get($order, 'customer.user.email', data_get($order, 'customer_email')) }}</p></td>
                                <td class="whitespace-nowrap">{{ data_get($order, 'created_at')?->translatedFormat('d M Y, H:i') ?? 'Belum tersedia' }}</td>
                                <td class="font-bold text-ink-900"><x-money :value="data_get($order, 'grand_total', data_get($order, 'total'))" /></td>
                                <td><x-status-badge :status="data_get($order, 'payment.status')" /></td>
                                <td><x-status-badge :status="data_get($order, 'status')" /></td>
                                <td class="text-right"><a href="{{ route('admin.orders.show', $order) }}" class="action-link">Detail <x-icon name="chevron-right" class="h-4 w-4" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (method_exists($orders ?? [], 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $orders->withQueryString()->links() }}</div>@endif
        @else
            <x-empty-state title="Pesanan tidak ditemukan" description="Belum ada pesanan yang sesuai filter." icon="shopping-bag">
                @if($hasFilters)<x-button :href="route('admin.orders.index')" variant="secondary" size="sm">Reset filter</x-button>@endif
            </x-empty-state>
        @endif
    </div>
</div>
@endsection
