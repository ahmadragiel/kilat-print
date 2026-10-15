@extends('layouts.admin')

@section('title', 'Desain')

@section('content')
<div class="space-y-7">
    <x-page-header title="Desain" description="Daftar file desain yang diunggah pelanggan dan siap diproses produksi." eyebrow="Operasional" />

    <div class="panel overflow-hidden">
        @if (collect($designs ?? [])->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Pesanan</th><th>Pelanggan</th><th>File desain</th><th>Status</th><th>Operator</th></tr></thead>
                    <tbody>
                        @foreach($designs as $design)
                            @php
                                $downloadUrl = data_get($design, 'file_url') ?? (data_get($design, 'path') && \Illuminate\Support\Facades\Route::has('admin.designs.download') ? route('admin.designs.download', $design) : null);
                            @endphp
                            <tr>
                                <td class="font-semibold text-ink-900">#{{ data_get($design, 'order.number', data_get($design, 'order_number', data_get($design, 'order_id'))) }}</td>
                                <td>{{ data_get($design, 'order.customer.user.name', data_get($design, 'customer_name', 'Pelanggan')) }}</td>
                                <td>@if($downloadUrl)<a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="action-link">Buka file <x-icon name="external" class="h-4 w-4" /></a>@else<span class="text-xs text-ink-500">{{ data_get($design, 'original_filename', 'Belum ada file') }}</span>@endif</td>
                                <td><x-status-badge :status="data_get($design, 'status')" /></td>
                                <td>{{ data_get($design, 'operator.name', data_get($design, 'operator_name', 'Belum ditugaskan')) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($designs ?? [], 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $designs->links() }}</div>@endif
        @else
            <x-empty-state title="Belum ada desain" description="File desain yang diunggah pelanggan akan tampil di sini." icon="palette" />
        @endif
    </div>
</div>
@endsection
