@extends('layouts.admin')

@section('title', 'Laporan')

@section('content')
@php
    $reportStats = $stats ?? [];
    if (empty($reportStats) && isset($kpi)) {
        $reportStats = [
            ['label' => 'Pendapatan', 'value' => 'Rp '.number_format((int) data_get($kpi, 'revenue', 0), 0, ',', '.'), 'icon' => 'wallet', 'tone' => 'brand'],
            ['label' => 'Pesanan', 'value' => number_format((int) data_get($kpi, 'orders', 0), 0, ',', '.'), 'icon' => 'shopping-bag', 'tone' => 'brand'],
            ['label' => 'Rata-rata per pesanan', 'value' => 'Rp '.number_format((int) data_get($kpi, 'average', 0), 0, ',', '.'), 'icon' => 'calculator', 'tone' => 'neutral'],
            ['label' => 'Pelanggan baru', 'value' => number_format((int) data_get($kpi, 'newCustomers', 0), 0, ',', '.'), 'icon' => 'users', 'tone' => 'neutral'],
        ];
    }
    $reportUrl = \Illuminate\Support\Facades\Route::has('admin.reports') ? route('admin.reports') : route('admin.reports.index');
    $fromValue = $from ?? request('from');
    $toValue = $to ?? request('to');
    $fromValue = $fromValue instanceof \DateTimeInterface ? $fromValue->format('Y-m-d') : $fromValue;
    $toValue = $toValue instanceof \DateTimeInterface ? $toValue->format('Y-m-d') : $toValue;
    $dailyTotal = collect($dailyRevenue ?? [])->sum();
@endphp
<div class="space-y-7">
    <x-page-header title="Laporan" description="Ringkasan laporan berdasarkan data yang tersedia di basis data." eyebrow="Laporan">
        <x-slot:actions>
            @if(!empty($exportUrl))<x-button :href="$exportUrl" variant="outline"><x-icon name="download" class="h-4 w-4" /> Unduh laporan</x-button>@endif
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ $reportUrl }}" class="panel grid gap-4 p-4 sm:grid-cols-3" aria-label="Filter laporan">
        <x-input name="from" label="Dari tanggal" type="date" :value="$fromValue" />
        <x-input name="to" label="Sampai tanggal" type="date" :value="$toValue" />
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
        <section class="panel overflow-hidden"><div class="panel-head"><div><h2 class="font-extrabold text-ink-950">Penjualan harian</h2><p class="mt-1 text-xs text-ink-500">Hanya pesanan dengan pembayaran terkonfirmasi.</p></div>@if(collect($dailyLabels ?? [])->isNotEmpty())<span class="text-sm font-bold text-brand-700"><x-money :value="$dailyTotal" /></span>@endif</div>@if(collect($dailyLabels ?? [])->isNotEmpty())<div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Tanggal</th><th class="text-right">Nilai</th></tr></thead><tbody>@foreach($dailyLabels as $index => $label)<tr><td class="font-semibold">{{ \Illuminate\Support\Carbon::parse($label)->translatedFormat('d M Y') }}</td><td class="text-right font-extrabold text-brand-700"><x-money :value="$dailyRevenue[$index] ?? null" /></td></tr>@endforeach</tbody></table></div>@else<x-empty-state compact title="Belum ada penjualan" description="Tidak ada pembayaran terkonfirmasi pada rentang tanggal ini." icon="chart" />@endif</section>
        <section class="panel overflow-hidden"><div class="panel-head"><h2 class="font-extrabold text-ink-950">Produk teratas</h2><span class="text-xs text-ink-500">Berdasarkan jumlah unit</span></div>@if(collect($topProducts ?? [])->isNotEmpty())<div class="divide-y divide-ink-100">@foreach($topProducts as $product)<div class="flex items-center justify-between gap-4 px-5 py-4"><div class="min-w-0"><p class="truncate font-semibold text-ink-950">{{ data_get($product, 'product_name', data_get($product, 'name', 'Produk')) }}</p><p class="mt-1 text-xs text-ink-500">{{ number_format((int) data_get($product, 'quantity_sold', data_get($product, 'quantity', 0)), 0, ',', '.') }} unit terjual</p></div><span class="shrink-0 font-extrabold text-brand-700"><x-money :value="data_get($product, 'revenue', data_get($product, 'total'))" /></span></div>@endforeach</div>@else<x-empty-state compact title="Belum ada produk terjual" description="Produk akan muncul setelah ada pesanan pada rentang ini." icon="box" />@endif</section>
    </div>
</div>
@endsection
