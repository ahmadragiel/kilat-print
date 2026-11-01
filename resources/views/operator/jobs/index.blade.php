@extends('layouts.operator')

@section('title', 'Pekerjaan Produksi')

@section('content')
@php
    $stageLabel = fn ($status) => $status instanceof \UnitEnum && method_exists($status, 'label') ? $status->label() : (string) $status;
    $hasFilters = request()->filled('search') || request()->filled('status') || request()->filled('deadline');
    $today = now()->startOfDay();
@endphp
<div class="space-y-7">
    <x-page-header title="Pekerjaan produksi" description="Kerjakan dan perbarui tahap pekerjaan yang ditugaskan." eyebrow="Operator" />

    <form method="GET" action="{{ route('operator.jobs.index') }}" class="panel grid gap-3 p-4 sm:grid-cols-[1fr_200px_180px_auto]" aria-label="Filter pekerjaan">
        <div class="relative">
            <label for="search" class="sr-only">Cari pekerjaan</label>
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" />
            <input id="search" name="search" value="{{ request('search') }}" class="form-control pl-10" placeholder="Nomor pesanan atau pelanggan">
        </div>
        <div>
            <label for="status" class="sr-only">Tahap pekerjaan</label>
            <select id="status" name="status" class="form-control">
                <option value="">Semua tahap</option>
                @foreach(($statuses ?? []) as $status)
                    <option value="{{ $status instanceof \BackedEnum ? $status->value : $status }}" @selected(request('status') === (string) ($status instanceof \BackedEnum ? $status->value : $status))>{{ $stageLabel($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="deadline" class="sr-only">Batas waktu</label>
            <input id="deadline" name="deadline" type="date" value="{{ request('deadline') }}" class="form-control">
        </div>
        <div class="flex items-end gap-2">
            <x-button type="submit" class="flex-1"><x-icon name="filter" class="h-4 w-4" /> Filter</x-button>
            @if($hasFilters)
                <x-button :href="route('operator.jobs.index')" variant="secondary" size="icon" aria-label="Reset filter"><x-icon name="refresh" class="h-4 w-4" /></x-button>
            @endif
        </div>
    </form>

    <div class="panel overflow-hidden">
        @if(collect($jobs ?? [])->isNotEmpty())
            <div class="divide-y divide-ink-100 lg:hidden">
                @foreach($jobs as $job)
                    @php
                        $deadline = data_get($job, 'deadline');
                        $deadlineDay = $deadline?->copy()->startOfDay();
                        $isOverdue = $deadlineDay ? $deadlineDay->lt($today) : false;
                        $isUrgent = $deadlineDay ? ($deadlineDay->greaterThanOrEqualTo($today) && $deadlineDay->lte($today->copy()->addDays(2))) : false;
                    @endphp
                    <article class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('operator.jobs.show', $job) }}" class="inline-flex min-h-11 items-center font-bold text-ink-950 transition hover:text-brand-700">#{{ data_get($job, 'order.number', data_get($job, 'order_number', data_get($job, 'id'))) }}</a>
                                <p class="mt-1 truncate text-xs text-ink-500">{{ data_get($job, 'order.customer.user.name', data_get($job, 'customer_name', 'Pelanggan')) }}</p>
                            </div>
                            <x-status-badge :status="data_get($job, 'status')" class="shrink-0" />
                        </div>
                        <p class="mt-3 truncate text-sm font-medium text-ink-800">{{ data_get($job, 'order.items.0.product_name', data_get($job, 'order.items.0.product.name', data_get($job, 'product_name', 'Produk'))) }}</p>
                        <div class="mt-3 flex items-end justify-between gap-3">
                            @if ($deadline)
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset',
                                    'bg-danger-50 text-danger-700 ring-danger-200' => $isOverdue,
                                    'bg-accent-100 text-accent-800 ring-accent-300' => $isUrgent,
                                    'bg-ink-100 text-ink-700 ring-ink-200' => ! $isOverdue && ! $isUrgent,
                                ])>
                                    <x-icon name="clock" class="h-3.5 w-3.5" />
                                    {{ $deadline->translatedFormat('d M Y') }}
                                    @if ($isOverdue)<span class="sr-only">terlambat</span>@endif
                                </span>
                            @else
                                <span class="text-xs text-ink-500">Batas waktu belum ditentukan</span>
                            @endif
                            <a href="{{ route('operator.jobs.show', $job) }}" class="action-link">Buka <x-icon name="chevron-right" class="h-4 w-4" /></a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <table class="data-table">
                    <thead><tr><th>Pesanan</th><th>Produk</th><th>Pelanggan</th><th>Tahap</th><th>Batas waktu</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @foreach($jobs as $job)
                            @php
                                $deadline = data_get($job, 'deadline');
                                $deadlineDay = $deadline?->copy()->startOfDay();
                                $isOverdue = $deadlineDay ? $deadlineDay->lt($today) : false;
                                $isUrgent = $deadlineDay ? ($deadlineDay->greaterThanOrEqualTo($today) && $deadlineDay->lte($today->copy()->addDays(2))) : false;
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
                                            {{ $deadline->translatedFormat('d M Y') }}
                                            @if ($isOverdue)<span class="sr-only">terlambat</span>@endif
                                        </span>
                                    @else
                                        <span class="text-ink-500">Belum ditentukan</span>
                                    @endif
                                </td>
                                <td class="text-right"><a href="{{ route('operator.jobs.show', $job) }}" class="action-link">Buka <x-icon name="chevron-right" class="h-4 w-4" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($jobs, 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $jobs->withQueryString()->links() }}</div>@endif
        @else
            <x-empty-state title="Pekerjaan tidak ditemukan" description="Belum ada pekerjaan yang sesuai filter." icon="factory">
                @if($hasFilters)<x-button :href="route('operator.jobs.index')" variant="secondary" size="sm">Reset filter</x-button>@endif
            </x-empty-state>
        @endif
    </div>
</div>
@endsection
