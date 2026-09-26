@extends('layouts.admin')

@section('title', 'Laporan')

@section('content')
@php
    $reportStats = $stats ?? [];
    if (empty($reportStats) && isset($kpi)) {
        $reportStats = [
            ['label' => 'Pendapatan', 'value' => data_get($kpi, 'revenue'), 'icon' => 'wallet', 'tone' => 'brand'],
            ['label' => 'Pesanan', 'value' => data_get($kpi, 'orders'), 'icon' => 'shopping-bag', 'tone' => 'brand'],
            ['label' => 'Rata-rata pesanan', 'value' => data_get($kpi, 'average'), 'icon' => 'calculator', 'tone' => 'neutral'],
            ['label' => 'Pelanggan baru', 'value' => data_get($kpi, 'newCustomers'), 'icon' => 'users', 'tone' => 'neutral'],
        ];
    }
    $reportUrl = \Illuminate\Support\Facades\Route::has('admin.reports') ? route('admin.reports') : route('admin.reports.index');
@endphp
<div class="space-y-7">
    <x-page-header title="Laporan" description="Ringkasan laporan berdasarkan data yang tersedia di basis data." eyebrow="Laporan">
        <x-slot:actions>
            @if(!empty($exportUrl))<x-button :href="$exportUrl" variant="outline"><x-icon name="download" class="h-4 w-4" /> Unduh laporan</x-button>@endif
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ $reportUrl }}" class="panel grid gap-4 p-4 sm:grid-cols-3" aria-label="Filter laporan">
        <x-input name="from" label="Dari tanggal" type="date" :value="$from ?? request('from')" />
        <x-input name="to" label="Sampai tanggal" type="date" :value="$to ?? request('to')" />
        <div class="flex items-end"><x-button type="submit" class="w-full"><x-icon name="filter" class="h-4 w-4" /> Terapkan</x-button></div>
    </form>

    @if (collect($reportStats)->isNotEmpty())
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan laporan">
            @foreach($reportStats as $statKey => $stat)
                @php $statIsObject = is_array($stat) || is_object($stat); $statLabel = $statIsObject ? data_get($stat, 'label', (string) $statKey) : (string) $statKey; $statValue = $statIsObject ? data_get($stat, 'value', $stat) : $stat; $statIcon = $statIsObject ? data_get($stat, 'icon', 'chart') : 'chart'; $statTone = $statIsObject ? data_get($stat, 'tone', 'brand') : 'brand'; @endphp
                <x-stat-card :label="$statLabel" :value="$statValue ?? 'Belum tersedia'" :icon="$statIcon" :tone="$statTone" />
            @endforeach
        </section>
    @else
        <div class="panel"><x-empty-state title="Laporan belum tersedia" description="Pilih rentang tanggal untuk mengambil data dari basis data." icon="chart" /></div>
    @endif

    <div class="grid gap-7 lg:grid-cols-2">
        <section class="panel overflow-hidden"><div class="panel-head"><h2 class="font-extrabold text-ink-950">Penjualan harian</h2></div>@if(collect($dailyLabels ?? [])->isNotEmpty())<div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Tanggal</th><th class="text-right">Nilai</th></tr></thead><tbody>@foreach($dailyLabels as $index => $label)<tr><td class="font-semibold">{{ $label }}</td><td class="text-right font-extrabold text-brand-600"><x-money :value="$dailyRevenue[$index] ?? null" /></td></tr>@endforeach</tbody></table></div>@else<x-empty-state compact title="Data penjualan belum tersedia" icon="chart" />@endif</section>
        <section class="panel overflow-hidden"><div class="panel-head"><h2 class="font-extrabold text-ink-950">Produk teratas</h2></div>@if(collect($topProducts ?? [])->isNotEmpty())<div class="divide-y divide-ink-100">@foreach($topProducts as $product)<div class="flex items-center justify-between gap-4 px-5 py-4"><div class="min-w-0"><p class="truncate font-semibold text-ink-950">{{ data_get($product, 'product_name', data_get($product, 'name', 'Produk')) }}</p><p class="mt-1 text-xs text-ink-500">{{ data_get($product, 'quantity_sold', data_get($product, 'quantity', 'Belum tersedia')) }} unit</p></div><span class="shrink-0 font-extrabold text-brand-600"><x-money :value="data_get($product, 'revenue', data_get($product, 'total'))" /></span></div>@endforeach</div>@else<x-empty-state compact title="Data produk belum tersedia" icon="box" />@endif</section>
    </div>
</div>
@endsection
