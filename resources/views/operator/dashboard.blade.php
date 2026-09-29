@extends('layouts.operator')

@section('title', 'Dashboard Operator')

@section('content')
<div class="space-y-7">
    <x-page-header title="Dashboard produksi" description="Pilih pekerjaan yang ditugaskan dan pantau tahapnya." eyebrow="Operator">
        <x-slot:actions><x-button :href="route('operator.jobs.index')"><x-icon name="factory" class="h-4 w-4" /> Semua pekerjaan</x-button></x-slot:actions>
    </x-page-header>

    @php
        $operatorStats = $stats ?? array_values(array_filter([
            ['label' => 'Total pekerjaan', 'value' => $totalJobs ?? null, 'icon' => 'factory'],
            ['label' => 'Diterima hari ini', 'value' => $todayJobs ?? null, 'icon' => 'clipboard'],
            ['label' => 'Dalam produksi', 'value' => $inProduction ?? null, 'icon' => 'printer', 'tone' => 'brand'],
            ['label' => 'Selesai', 'value' => $completed ?? null, 'icon' => 'check-circle', 'tone' => 'neutral'],
        ], fn ($stat) => $stat['value'] !== null));
        $operatorJobs = $jobs ?? $recentJobs ?? [];
    @endphp
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Statistik operator">
        @forelse($operatorStats as $statKey => $stat)
            @php $statIsObject = is_array($stat) || is_object($stat); $statLabel = $statIsObject ? data_get($stat, 'label', (string) $statKey) : (string) $statKey; $statValue = $statIsObject ? data_get($stat, 'value', $stat) : $stat; $statIcon = $statIsObject ? data_get($stat, 'icon', 'factory') : 'factory'; $statTone = $statIsObject ? data_get($stat, 'tone', 'brand') : 'brand'; @endphp
            <x-stat-card :label="$statLabel" :value="$statValue ?? 'Belum tersedia'" :icon="$statIcon" :tone="$statTone" />
        @empty
            <div class="panel sm:col-span-2 xl:col-span-4"><x-empty-state compact title="Statistik belum tersedia" description="Ringkasan pekerjaan akan muncul dari basis data." icon="chart" /></div>
        @endforelse
    </section>

    <section class="panel overflow-hidden">
        <div class="panel-head"><div><p class="section-kicker">Antrean</p><h2 class="mt-1 font-extrabold text-ink-950">Pekerjaan terbaru</h2></div><a href="{{ route('operator.jobs.index') }}" class="action-link text-xs">Lihat semua <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a></div>
        @if(collect($operatorJobs)->isNotEmpty())
            <div class="divide-y divide-ink-100">@foreach($operatorJobs as $job)<a href="{{ route('operator.jobs.show', $job) }}" class="flex flex-wrap items-center gap-4 px-5 py-4 transition hover:bg-ink-50"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-icon name="printer" class="h-5 w-5" /></span><span class="min-w-0 flex-1"><span class="block font-bold text-ink-900">#{{ data_get($job, 'order.number', data_get($job, 'order_number', data_get($job, 'id'))) }}</span><span class="mt-1 block truncate text-sm text-ink-500">{{ data_get($job, 'order.items.0.product_name', data_get($job, 'product.name', data_get($job, 'product_name', 'Produk'))) }}</span></span><x-status-badge :status="data_get($job, 'status', data_get($job, 'stage'))" /></a>@endforeach</div>
        @else<x-empty-state title="Belum ada pekerjaan" description="Pekerjaan yang ditugaskan akan tampil di sini." icon="factory" />@endif
    </section>
</div>
@endsection
