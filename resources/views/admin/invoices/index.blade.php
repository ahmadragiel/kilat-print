@extends('layouts.admin')

@section('title', 'Faktur')

@section('content')
<div class="space-y-7">
    <x-page-header title="Faktur" description="Daftar faktur untuk pesanan yang telah dibayar." eyebrow="Dokumen" />
    <div class="panel overflow-hidden">
        @if (collect($orders ?? [])->isNotEmpty())
            <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead><tbody>
                @foreach($orders as $order)
                    <tr>
                        <td class="font-semibold text-ink-900">#{{ data_get($order, 'number', data_get($order, 'order_number', data_get($order, 'id'))) }}</td>
                        <td>{{ data_get($order, 'customer.user.name', data_get($order, 'customer_name', 'Pelanggan')) }}</td>
                        <td>{{ data_get($order, 'created_at')?->format('d M Y') ?? 'Belum tersedia' }}</td>
                        <td class="font-bold"><x-money :value="data_get($order, 'grand_total')" /></td>
                        <td><x-status-badge :status="data_get($order, 'status')" /></td>
                        <td class="text-right"><a href="{{ \Illuminate\Support\Facades\Route::has('admin.invoice') ? route('admin.invoice', $order) : route('admin.invoices.show', $order) }}" class="action-link">Buka <x-icon name="external" class="h-4 w-4" /></a></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            @if(method_exists($orders, 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $orders->links() }}</div>@endif
        @else
            <x-empty-state title="Belum ada faktur" description="Faktur akan tersedia setelah pesanan memenuhi syarat pencetakan." icon="receipt" />
        @endif
    </div>
</div>
@endsection
