@php
    $user = auth()->user();
    $resources = [
        ['label' => 'Produk', 'type' => 'products', 'icon' => 'box'],
        ['label' => 'Kategori', 'type' => 'categories', 'icon' => 'category'],
        ['label' => 'Material', 'type' => 'materials', 'icon' => 'layers'],
        ['label' => 'Finishing', 'type' => 'finishings', 'icon' => 'palette'],
    ];
    $active = fn (array $patterns) => collect($patterns)->contains(fn ($pattern) => request()->routeIs($pattern));
    /* Sidebar link contract: dark red base, red-700 active fill, yellow left indicator. */
    $link = 'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition text-brand-100 hover:bg-brand-800/60 hover:text-white';
    $linkActive = 'bg-brand-800 text-white shadow-sm';
@endphp

<aside class="flex h-full flex-col bg-brand-700 text-brand-100">
    <div class="flex h-16 shrink-0 items-center border-b border-white/10 bg-brand-800 px-5">
        <x-brand inverse />
        <span class="ml-2 rounded bg-accent-400 px-1.5 py-0.5 text-[10px] font-black tracking-wide text-ink-950 uppercase">Admin</span>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Navigasi admin">
        <div>
            <p class="mb-2 px-3 text-[10px] font-bold tracking-[0.16em] text-brand-100 uppercase">Ringkasan</p>
            <a href="{{ route('admin.dashboard') }}" class="{{ $link }} {{ $active(['admin.dashboard']) ? $linkActive : '' }} @if($active(['admin.dashboard'])) before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-accent-400 @endif">
                <x-icon name="home" class="h-5 w-5" /> Dasbor
            </a>
            <a href="{{ route('admin.orders.index') }}" class="{{ $link }} {{ $active(['admin.orders.*']) ? $linkActive : '' }} @if($active(['admin.orders.*'])) before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-accent-400 @endif">
                <x-icon name="shopping-bag" class="h-5 w-5" /> Pesanan
            </a>
        </div>

        <div>
            <p class="mb-2 px-3 text-[10px] font-bold tracking-[0.16em] text-brand-100 uppercase">Data master</p>
            @foreach ($resources as $resource)
                @php $isActive = request()->route('resourceType') === $resource['type']; @endphp
                <a href="{{ \Illuminate\Support\Facades\Route::has('admin.resources.index') ? route('admin.resources.index', ['resourceType' => $resource['type']]) : route('admin.'.$resource['type'].'.index') }}" class="{{ $link }} {{ $isActive ? $linkActive : '' }} @if($isActive) before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-accent-400 @endif">
                    <x-icon :name="$resource['icon']" class="h-5 w-5" /> {{ $resource['label'] }}
                </a>
            @endforeach
            <a href="{{ route('admin.prices.index') }}" class="{{ $link }} {{ $active(['admin.prices.*']) ? $linkActive : '' }} @if($active(['admin.prices.*'])) before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-accent-400 @endif">
                <x-icon name="calculator" class="h-5 w-5" /> Harga
            </a>
        </div>

        <div>
            <p class="mb-2 px-3 text-[10px] font-bold tracking-[0.16em] text-brand-100 uppercase">Operasional</p>
            @php $paymentsActive = request()->routeIs('admin.payments*'); @endphp
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.payments') ? route('admin.payments') : route('admin.payments.index') }}" class="{{ $link }} {{ $paymentsActive ? $linkActive : '' }} @if($paymentsActive) before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-accent-400 @endif">
                <x-icon name="credit-card" class="h-5 w-5" /> Pembayaran
            </a>
            @php $designsActive = request()->routeIs('admin.designs*'); @endphp
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.designs') ? route('admin.designs') : route('admin.designs.index') }}" class="{{ $link }} {{ $designsActive ? $linkActive : '' }} @if($designsActive) before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-accent-400 @endif">
                <x-icon name="palette" class="h-5 w-5" /> Desain
            </a>
            @php $productionActive = request()->routeIs('admin.production*'); @endphp
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.production') ? route('admin.production') : route('admin.production.index') }}" class="{{ $link }} {{ $productionActive ? $linkActive : '' }} @if($productionActive) before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-accent-400 @endif">
                <x-icon name="factory" class="h-5 w-5" /> Produksi
            </a>
        </div>

        <div>
            <p class="mb-2 px-3 text-[10px] font-bold tracking-[0.16em] text-brand-100 uppercase">Laporan</p>
            @php
                $reportLinks = [
                    ['label' => 'Pelanggan', 'icon' => 'users', 'active' => request()->routeIs('admin.customers*'), 'href' => \Illuminate\Support\Facades\Route::has('admin.customers') ? route('admin.customers') : route('admin.customers.index')],
                    ['label' => 'Operator', 'icon' => 'user-cog', 'active' => request()->routeIs('admin.operators*'), 'href' => \Illuminate\Support\Facades\Route::has('admin.operators') ? route('admin.operators') : route('admin.operators.index')],
                    ['label' => 'Laporan', 'icon' => 'chart', 'active' => request()->routeIs('admin.reports*'), 'href' => \Illuminate\Support\Facades\Route::has('admin.reports') ? route('admin.reports') : route('admin.reports.index')],
                    ['label' => 'Faktur', 'icon' => 'receipt', 'active' => request()->routeIs('admin.invoices*'), 'href' => \Illuminate\Support\Facades\Route::has('admin.invoices') ? route('admin.invoices') : route('admin.invoices.index')],
                ];
            @endphp
            @foreach ($reportLinks as $reportLink)
                <a href="{{ $reportLink['href'] }}" class="{{ $link }} {{ $reportLink['active'] ? $linkActive : '' }} @if($reportLink['active']) before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-accent-400 @endif">
                    <x-icon :name="$reportLink['icon']" class="h-5 w-5" /> {{ $reportLink['label'] }}
                </a>
            @endforeach
        </div>
    </nav>

    <div class="shrink-0 border-t border-white/10 bg-brand-800 p-3">
        <div class="flex items-center gap-3 rounded-lg px-3 py-2">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-accent-400 text-sm font-black text-ink-950">{{ strtoupper(substr($user->name ?? 'A', 0, 1)) }}</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-white">{{ $user->name ?? 'Administrator' }}</p>
                <p class="truncate text-xs text-brand-100/90">{{ $user->email ?? '' }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold text-brand-100 transition hover:bg-brand-700 hover:text-white">
                <x-icon name="logout" class="h-5 w-5" /> Keluar
            </button>
        </form>
    </div>
</aside>
