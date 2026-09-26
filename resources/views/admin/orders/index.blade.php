@extends('layouts.admin')

@section('title', 'Pesanan')

@section('content')
@php
    $statusOptions = $statuses ?? [];
    $paymentStatusOptions = $paymentStatuses ?? [];
@endphp
<div class="space-y-7">
    <x-page-header title="Pesanan" description="Kelola status dan detail pesanan pelanggan." eyebrow="Operasional" />

    <form method="GET" action="{{ route('admin.orders.index') }}" class="panel grid gap-3 p-4 sm:grid-cols-[1fr_200px_200px_auto]" aria-label="Filter pesanan admin">
        <div class="relative"><label for="search" class="sr-only">Cari pesanan</label><x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" /><input id="search" name="search" value="{{ request('search') }}" class="form-control pl-10" placeholder="Nomor pesanan atau pelanggan"></div>
        <div><label for="status" class="sr-only">Status</label><select id="status" name="status" class="form-control"><option value="">Semua status</option>@foreach($statusOptions as $status)<option value="{{ $status instanceof \BackedEnum ? $status->value : (is_array($status) ? data_get($status, 'value', data_get($status, 'key')) : $status) }}" @selected(request('status') === (string) ($status instanceof \BackedEnum ? $status->value : (is_array($status) ? data_get($status, 'value', data_get($status, 'key')) : $status)))>{{ $status instanceof \UnitEnum ? $status->name : (is_array($status) ? data_get($status, 'label', data_get($status, 'name')) : $status) }}</option>@endforeach</select></div>
        <div><label for="payment" class="sr-only">Pembayaran</label><select id="payment" name="payment" class="form-control"><option value="">Semua pembayaran</option>@foreach($paymentStatusOptions as $paymentStatus)<option value="{{ $paymentStatus instanceof \BackedEnum ? $paymentStatus->value : (is_array($paymentStatus) ? data_get($paymentStatus, 'value') : $paymentStatus) }}" @selected(request('payment') === (string) ($paymentStatus instanceof \BackedEnum ? $paymentStatus->value : (is_array($paymentStatus) ? data_get($paymentStatus, 'value') : $paymentStatus)))>{{ $paymentStatus instanceof \UnitEnum ? $paymentStatus->name : (is_array($paymentStatus) ? data_get($paymentStatus, 'label', data_get($paymentStatus, 'name')) : $paymentStatus) }}</option>@endforeach</select></div>
        <x-button type="submit"><x-icon name="filter" class="h-4 w-4" /> Filter</x-button>
    </form>

    <div class="panel overflow-hidden">
        @if (collect($orders ?? [])->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th>Pembayaran</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}" class="font-bold text-ink-900 hover:text-brand-700">#{{ data_get($order, 'number', data_get($order, 'order_number', data_get($order, 'id'))) }}</a><p class="mt-1 text-xs text-ink-500">{{ data_get($order, 'items_count', count(data_get($order, 'items', []))) }} item</p></td>
                                <td><p class="font-semibold text-ink-800">{{ data_get($order, 'customer.user.name', data_get($order, 'customer.name', data_get($order, 'customer_name', 'Pelanggan'))) }}</p><p class="mt-1 text-xs text-ink-500">{{ data_get($order, 'customer.user.email', data_get($order, 'customer_email')) }}</p></td>
                                <td class="whitespace-nowrap">{{ data_get($order, 'created_at')?->format('d M Y, H:i') ?? 'Belum tersedia' }}</td>
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
            <x-empty-state title="Pesanan tidak ditemukan" description="Belum ada pesanan yang sesuai filter." icon="shopping-bag" />
        @endif
    </div>
</div>
@endsection
