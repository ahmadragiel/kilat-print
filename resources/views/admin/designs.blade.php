@extends('layouts.admin')

@section('title', 'Desain')

@section('content')
<div class="space-y-7">
    <x-page-header title="Persetujuan desain" description="Periksa file desain dan teruskan ke produksi." eyebrow="Operasional" />

    <div class="panel overflow-hidden">
        @if (collect($designs ?? [])->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Pesanan</th><th>Pelanggan</th><th>File desain</th><th>Status</th><th>Operator</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach($designs as $design)
                            @php
                                $approveUrl = \Illuminate\Support\Facades\Route::has('design.approve') ? route('design.approve', $design) : route('admin.designs.approve', $design);
                                $revisionUrl = \Illuminate\Support\Facades\Route::has('design.requestRevision') ? route('design.requestRevision', $design) : route('admin.designs.revision', $design);
                                $downloadUrl = data_get($design, 'file_url') ?? (data_get($design, 'path') && \Illuminate\Support\Facades\Route::has('admin.designs.download') ? route('admin.designs.download', $design) : null);
                            @endphp
                            <tr>
                                <td class="font-semibold text-ink-900">#{{ data_get($design, 'order.number', data_get($design, 'order_number', data_get($design, 'order_id'))) }}</td>
                                <td>{{ data_get($design, 'order.customer.user.name', data_get($design, 'customer_name', 'Pelanggan')) }}</td>
                                <td>@if($downloadUrl)<a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="action-link">Buka file <x-icon name="external" class="h-4 w-4" /></a>@else<span class="text-xs text-ink-500">{{ data_get($design, 'original_filename', 'Belum ada file') }}</span>@endif</td>
                                <td><x-status-badge :status="data_get($design, 'status')" /></td>
                                <td>{{ data_get($design, 'operator.name', data_get($design, 'operator_name', 'Belum ditugaskan')) }}</td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <form method="POST" action="{{ $approveUrl }}">@csrf @if(\Illuminate\Support\Facades\Route::has('design.approve')) @method('PATCH') @endif<x-button type="submit" size="sm" variant="success">Setujui</x-button></form>
                                        <form method="POST" action="{{ $revisionUrl }}">@csrf @if(\Illuminate\Support\Facades\Route::has('design.requestRevision')) @method('PATCH') @endif<input type="hidden" name="reason" value="Mohon periksa kembali desain sebelum produksi."><x-button type="submit" size="sm" variant="secondary">Minta revisi</x-button></form>
                                    </div>
                                </td>
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
