@extends('layouts.operator')

@section('title', 'Pekerjaan Produksi')

@section('content')
<div class="space-y-7">
    <x-page-header title="Pekerjaan produksi" description="Kerjakan dan perbarui tahap pekerjaan yang ditugaskan." eyebrow="Operator" />

    <form method="GET" action="{{ route('operator.jobs.index') }}" class="panel grid gap-3 p-4 sm:grid-cols-[1fr_200px_180px_auto]" aria-label="Filter pekerjaan">
        <div class="relative"><label for="search" class="sr-only">Cari pekerjaan</label><x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" /><input id="search" name="search" value="{{ request('search') }}" class="form-control pl-10" placeholder="Nomor pesanan atau pelanggan"></div>
        <div><label for="status" class="sr-only">Status pekerjaan</label><select id="status" name="status" class="form-control"><option value="">Semua tahap</option>@foreach(($statuses ?? []) as $status)<option value="{{ $status instanceof \BackedEnum ? $status->value : $status }}" @selected(request('status') === (string) ($status instanceof \BackedEnum ? $status->value : $status))>{{ $status instanceof \UnitEnum ? $status->name : $status }}</option>@endforeach</select></div>
        <div><label for="deadline" class="sr-only">Deadline</label><input id="deadline" name="deadline" type="date" value="{{ request('deadline') }}" class="form-control"></div>
        <x-button type="submit"><x-icon name="filter" class="h-4 w-4" /> Filter</x-button>
    </form>

    <div class="panel overflow-hidden">
        @if(collect($jobs ?? [])->isNotEmpty())
            <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Pesanan</th><th>Produk</th><th>Pelanggan</th><th>Tahap</th><th>Deadline</th><th><span class="sr-only">Aksi</span></th></tr></thead><tbody>
                @php $today = now()->startOfDay(); @endphp
                @foreach($jobs as $job)
                    @php
                        $deadline = data_get($job, 'deadline');
                        $isOverdue = $deadline ? $deadline->startOfDay()->lt($today) : false;
                        $isUrgent = $deadline ? ($deadline->startOfDay()->greaterThanOrEqualTo($today) && $deadline->startOfDay()->lte($today->copy()->addDays(2))) : false;
                    @endphp
                    <tr>
                        <td class="font-semibold text-ink-950">#{{ data_get($job, 'order.number', data_get($job, 'order_number', data_get($job, 'id'))) }}</td>
                        <td>{{ data_get($job, 'order.items.0.product_name', data_get($job, 'order.items.0.product.name', data_get($job, 'product_name', 'Produk'))) }}</td>
                        <td>{{ data_get($job, 'order.customer.user.name', data_get($job, 'customer_name', 'Pelanggan')) }}</td>
                        <td><x-status-badge :status="data_get($job, 'status')" /></td>
                        <td>
                            @if ($deadline)
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset',
                                    'bg-danger-50 text-danger-700 ring-danger-200' => $isOverdue,
                                    'bg-accent-100 text-accent-800 ring-accent-300' => $isUrgent,
                                    'bg-ink-100 text-ink-700 ring-ink-200' => ! $isOverdue && ! $isUrgent,
                                ])>
                                    <x-icon name="clock" class="h-3.5 w-3.5" />
                                    {{ $deadline->format('d M Y') }}
                                    @if ($isOverdue)<span class="sr-only">terlambat</span>@endif
                                </span>
                            @else
                                <span class="text-ink-500">Belum ditentukan</span>
                            @endif
                        </td>
                        <td class="text-right"><a href="{{ route('operator.jobs.show', $job) }}" class="action-link">Buka <x-icon name="chevron-right" class="h-4 w-4" /></a></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            @if(method_exists($jobs, 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $jobs->withQueryString()->links() }}</div>@endif
        @else<x-empty-state title="Pekerjaan tidak ditemukan" description="Belum ada pekerjaan yang sesuai filter." icon="factory" />@endif
    </div>
</div>
@endsection
