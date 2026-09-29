@extends('layouts.admin')

@section('title', 'Dashboard Admin')

@section('content')
<div class="space-y-7">
    <x-page-header title="Dashboard operasional" description="Ringkasan data dari Kilat Print." eyebrow="Admin">
        <x-slot:actions>
            <x-button :href="route('admin.orders.index')" variant="secondary"><x-icon name="shopping-bag" class="h-4 w-4" /> Pesanan</x-button>
            <x-button :href="\Illuminate\Support\Facades\Route::has('admin.resources.form') ? route('admin.resources.form', ['resourceType' => 'products']) : route('admin.products.create')"><x-icon name="plus" class="h-4 w-4" /> Produk baru</x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $dashboardStats = $stats ?? $dashboardStats ?? array_values(array_filter([
            ['label' => 'Total pesanan', 'value' => $totalOrders ?? null, 'icon' => 'shopping-bag'],
            ['label' => 'Pesanan hari ini', 'value' => $ordersToday ?? null, 'icon' => 'clock'],
            ['label' => 'Pendapatan', 'value' => $revenue ?? null, 'icon' => 'wallet'],
            ['label' => 'Pelanggan aktif', 'value' => $activeCustomers ?? null, 'icon' => 'users'],
        ], fn ($stat) => $stat['value'] !== null));
        $dashboardOrders = $latestOrders ?? $recentOrders ?? $orders ?? [];
        $charts = $charts ?? [];
        $chartLabels = data_get($charts, 'labels', []);
        $chartDatasets = [];
        foreach ([
            'revenue' => 'Pendapatan',
            'orders' => 'Pesanan',
            'customerGrowth' => 'Pelanggan baru',
        ] as $key => $label) {
            $values = data_get($charts, $key);
            if ($values !== null) {
                $chartDatasets[] = [
                    'label' => $label,
                    'data' => is_object($values) && method_exists($values, 'all') ? $values->values()->all() : $values,
                    'borderColor' => $key === 'revenue' ? '#e11d2e' : ($key === 'orders' ? '#10151c' : '#059669'),
                    'backgroundColor' => $key === 'revenue' ? 'rgba(225, 29, 46, .12)' : ($key === 'orders' ? 'rgba(16, 21, 28, .08)' : 'rgba(5, 150, 105, .10)'),
                    'tension' => 0.35,
                ];
            }
        }
        $chartData = $chartData ?? (! empty($chartLabels) && ! empty($chartDatasets) ? [
            'type' => 'line',
            'data' => ['labels' => $chartLabels, 'datasets' => $chartDatasets],
        ] : null);
    @endphp
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Statistik operasional">
        @forelse ($dashboardStats as $statKey => $stat)
            @php
                $statIsObject = is_array($stat) || is_object($stat);
                $statLabel = $statIsObject ? data_get($stat, 'label', (string) $statKey) : (string) $statKey;
                $statValue = $statIsObject ? data_get($stat, 'value', data_get($stat, 'total')) : $stat;
                $statHint = $statIsObject ? data_get($stat, 'hint') : null;
                $statIcon = $statIsObject ? data_get($stat, 'icon', 'chart') : 'chart';
                $statHref = $statIsObject ? data_get($stat, 'href') : null;
            @endphp
            <x-stat-card :label="$statLabel" :value="$statValue ?? 'Belum tersedia'" :hint="$statHint" :icon="$statIcon" :href="$statHref" />
        @empty
            <div class="panel sm:col-span-2 xl:col-span-4"><x-empty-state compact title="Statistik belum tersedia" description="Dashboard akan menampilkan angka dari basis data." icon="chart" /></div>
        @endforelse
    </section>

    <section class="grid gap-6 xl:grid-cols-[1.35fr_.65fr]">
        <div class="panel p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="section-kicker">Grafik</p>
                    <h2 class="mt-1 text-lg font-extrabold text-ink-950">Tren operasional</h2>
                </div>
                @if(isset($chartTitle))<span class="text-sm font-semibold text-ink-500">{{ $chartTitle }}</span>@endif
            </div>
            @if (!empty($chartData))
                <div class="mt-6 h-72" x-data='kilatChart(@json($chartData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP))' x-init="init()" x-destroy="destroy()">
                    <canvas x-ref="canvas" x-show="hasData()" x-cloak aria-label="Grafik operasional"></canvas>
                    <x-empty-state x-show="!hasData()" compact title="Data grafik belum tersedia" description="Dataset database belum memiliki nilai untuk ditampilkan." icon="chart" />
                </div>
            @else
                <x-empty-state class="mt-4" title="Grafik belum tersedia" description="Dataset database belum dikirim ke dashboard." icon="chart" />
            @endif
        </div>

        <div class="panel overflow-hidden">
            <div class="panel-head">
                <div>
                    <p class="section-kicker">Aktivitas</p>
                    <h2 class="mt-1 font-extrabold text-ink-950">Pesanan terbaru</h2>
                </div>
                <a href="{{ route('admin.orders.index') }}" class="action-link text-xs">Lihat <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a>
            </div>
            @if (collect($dashboardOrders)->isNotEmpty())
                <div class="divide-y divide-ink-100">
                    @foreach ($dashboardOrders as $order)
                        <a href="{{ route('admin.orders.show', $order) }}" class="flex items-center gap-3 px-5 py-4 transition hover:bg-ink-50">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-ink-100 text-ink-600"><x-icon name="shopping-bag" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold text-ink-900">#{{ data_get($order, 'number', data_get($order, 'order_number', data_get($order, 'id'))) }}</span>
                                <span class="mt-0.5 block text-xs text-ink-500">{{ data_get($order, 'customer.user.name', data_get($order, 'customer.name', data_get($order, 'customer_name', 'Pelanggan'))) }}</span>
                            </span>
                            <span class="shrink-0 text-right"><x-status-badge :status="data_get($order, 'status')" /></span>
                        </a>
                    @endforeach
                </div>
            @else
                <x-empty-state compact title="Belum ada pesanan" icon="shopping-bag" />
            @endif
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-3" aria-label="Queue operasional">
        <div class="panel overflow-hidden">
            <div class="panel-head">
                <div>
                    <p class="section-kicker">Pembayaran</p>
                    <h2 class="mt-1 font-extrabold text-ink-950">Menunggu pembayaran</h2>
                </div>
                <a href="{{ route('admin.payments.index') }}" class="action-link text-xs">Kelola</a>
            </div>
            <div class="divide-y divide-ink-100">
                @forelse (($pendingPaymentOrders ?? []) as $order)
                    <a href="{{ route('admin.orders.show', $order) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-ink-50">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-bold text-ink-900">#{{ $order->number }}</span>
                            <span class="mt-1 block truncate text-xs text-ink-500">{{ $order->customer?->user?->name }} · {{ $order->payment?->status?->label() }}</span>
                        </span>
                        <x-money :value="$order->grand_total" class="shrink-0 text-sm font-bold" />
                    </a>
                @empty
                    <x-empty-state compact title="Tidak ada pembayaran tertunda" description="Semua pembayaran sudah ditindaklanjuti." icon="check-circle" />
                @endforelse
            </div>
        </div>

        <div class="panel overflow-hidden">
            <div class="panel-head">
                <div>
                    <p class="section-kicker">Desain</p>
                    <h2 class="mt-1 font-extrabold text-ink-950">Menunggu review</h2>
                </div>
                <a href="{{ route('admin.designs.index') }}" class="action-link text-xs">Review</a>
            </div>
            <div class="divide-y divide-ink-100">
                @forelse (($waitingDesigns ?? []) as $order)
                    @php($design = $order->designFiles->sortByDesc('version')->first())
                    <a href="{{ route('admin.orders.show', $order) }}" class="block px-5 py-4 transition hover:bg-ink-50">
                        <span class="flex items-center justify-between gap-3">
                            <span class="truncate text-sm font-bold text-ink-900">#{{ $order->number }}</span>
                            <x-status-badge :status="$order->status" />
                        </span>
                        <span class="mt-1 block truncate text-xs text-ink-500">{{ $design?->original_filename ?? 'Belum ada file desain' }}</span>
                    </a>
                @empty
                    <x-empty-state compact title="Tidak ada desain tertunda" description="Antrean review desain kosong." icon="image" />
                @endforelse
            </div>
        </div>

        <div class="panel overflow-hidden">
            <div class="panel-head">
                <div>
                    <p class="section-kicker">Produksi</p>
                    <h2 class="mt-1 font-extrabold text-ink-950">Produksi berjalan</h2>
                </div>
                <a href="{{ route('admin.production.index') }}" class="action-link text-xs">Monitor</a>
            </div>
            <div class="divide-y divide-ink-100">
                @forelse (($currentProduction ?? []) as $order)
                    <a href="{{ route('admin.orders.show', $order) }}" class="block px-5 py-4 transition hover:bg-ink-50">
                        <span class="flex items-center justify-between gap-3">
                            <span class="truncate text-sm font-bold text-ink-900">#{{ $order->number }}</span>
                            <x-status-badge :status="$order->production?->status" />
                        </span>
                        <span class="mt-1 block text-xs text-ink-500">Operator: {{ $order->production?->operator?->user?->name ?? 'Belum ditugaskan' }}</span>
                    </a>
                @empty
                    <x-empty-state compact title="Belum ada produksi aktif" description="Job produksi akan muncul setelah design disetujui." icon="factory" />
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
