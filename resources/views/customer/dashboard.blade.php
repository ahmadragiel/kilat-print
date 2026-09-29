@extends('layouts.app')

@section('title', 'Dashboard Pelanggan')

@section('content')
<div class="page-shell py-8 sm:py-10">
    <x-page-header title="Halo, {{ auth()->user()->name }}" description="Pantau pesanan dan aktivitas akun Anda." eyebrow="Dashboard pelanggan">
        <x-slot:actions>
            <x-button :href="route('products.index')"><x-icon name="plus" class="h-4 w-4" /> Produk baru</x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $dashboardStats = $stats ?? array_values(array_filter([
            ['label' => 'Total pesanan', 'value' => $totalOrders ?? null, 'icon' => 'shopping-bag', 'tone' => 'brand'],
            ['label' => 'Pesanan aktif', 'value' => $activeOrders ?? null, 'icon' => 'clock', 'tone' => 'brand'],
            ['label' => 'Pesanan selesai', 'value' => $completedOrders ?? null, 'icon' => 'check-circle', 'tone' => 'neutral'],
            ['label' => 'Menunggu pembayaran', 'value' => $pendingPayments ?? null, 'icon' => 'wallet', 'tone' => 'accent'],
        ], fn ($stat) => $stat['value'] !== null));
        $dashboardOrders = $recentOrders ?? $orders ?? [];
    @endphp

    <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan akun">
        @forelse ($dashboardStats as $statKey => $stat)
            @php
                $statIsObject = is_array($stat) || is_object($stat);
                $statLabel = $statIsObject ? data_get($stat, 'label', (string) $statKey) : (string) $statKey;
                $statValue = $statIsObject ? data_get($stat, 'value', data_get($stat, 'total')) : $stat;
                $statHint = $statIsObject ? data_get($stat, 'hint') : null;
                $statIcon = $statIsObject ? data_get($stat, 'icon', 'chart') : 'chart';
                $statHref = $statIsObject ? data_get($stat, 'href') : null;
                $statTone = $statIsObject ? data_get($stat, 'tone', 'brand') : 'brand';
            @endphp
            <x-stat-card :label="$statLabel" :value="$statValue ?? 'Belum tersedia'" :hint="$statHint" :icon="$statIcon" :href="$statHref" :tone="$statTone" />
        @empty
            <div class="panel sm:col-span-2 xl:col-span-4">
                <x-empty-state compact title="Ringkasan belum tersedia" description="Statistik pesanan akan muncul setelah backend menyediakan data." icon="chart" />
            </div>
        @endforelse
    </section>

    @if (($pendingPayments ?? null))
        <div class="accent-callout mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="flex items-center gap-2 font-bold"><x-icon name="wallet" class="h-4 w-4 text-accent-600" /> Anda punya {{ $pendingPayments }} pesanan yang menunggu pembayaran.</p>
            <a href="{{ route('customer.orders.index') }}" class="action-link !text-accent-900">Lihat pesanan <x-icon name="arrow-right" class="h-4 w-4" /></a>
        </div>
    @endif>

    <section class="mt-8" aria-labelledby="recent-orders-title">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <p class="section-kicker">Aktivitas</p>
                <h2 id="recent-orders-title" class="mt-1 text-xl font-extrabold text-ink-950">Pesanan terbaru</h2>
            </div>
            <a href="{{ route('customer.orders.index') }}" class="action-link">Lihat semua <x-icon name="arrow-right" class="h-4 w-4" /></a>
        </div>

        <div class="panel overflow-hidden">
            @if (collect($dashboardOrders)->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Nomor</th><th>Produk</th><th>Tanggal</th><th>Total</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                        <tbody>
                            @foreach ($dashboardOrders as $order)
                                <tr>
                                    <td class="font-semibold text-ink-900">#{{ data_get($order, 'number', data_get($order, 'order_number', data_get($order, 'id'))) }}</td>
                                    <td>
                                        <p class="max-w-xs truncate font-medium text-ink-800">{{ data_get($order, 'items.0.product_name', data_get($order, 'product.name', data_get($order, 'product_name', 'Produk'))) }}</p>
                                        @if (data_get($order, 'items_count'))<p class="mt-0.5 text-xs text-ink-500">{{ data_get($order, 'items_count') }} item</p>@endif
                                    </td>
                                    <td class="whitespace-nowrap">{{ data_get($order, 'created_at')?->format('d M Y, H:i') ?? 'Belum tersedia' }}</td>
                                    <td class="font-semibold text-brand-600"><x-money :value="data_get($order, 'grand_total', data_get($order, 'total'))" /></td>
                                    <td><x-status-badge :status="data_get($order, 'status')" /></td>
                                    <td class="text-right"><a href="{{ route('customer.orders.show', $order) }}" class="action-link"><span class="sr-only">Lihat</span><x-icon name="chevron-right" class="h-4 w-4" /></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-empty-state title="Belum ada pesanan" description="Pesanan terbaru akan tampil setelah Anda membuat pesanan pertama." icon="shopping-bag">
                    <x-button :href="route('products.index')" size="sm">Jelajahi produk</x-button>
                </x-empty-state>
            @endif
        </div>
    </section>
</div>
@endsection
