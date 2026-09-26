@php
    $user = auth()->user();
    $resources = [
        ['label' => 'Produk', 'type' => 'products', 'icon' => 'box'],
        ['label' => 'Kategori', 'type' => 'categories', 'icon' => 'category'],
        ['label' => 'Material', 'type' => 'materials', 'icon' => 'layers'],
        ['label' => 'Finishing', 'type' => 'finishings', 'icon' => 'palette'],
    ];
    $active = fn (array $patterns) => collect($patterns)->contains(fn ($pattern) => request()->routeIs($pattern));
@endphp

<aside class="flex h-full flex-col bg-ink-950 text-ink-200">
    <div class="flex h-16 shrink-0 items-center border-b border-white/10 px-5">
        <x-brand inverse />
        <span class="ml-2 rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-ink-300 uppercase">Admin</span>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Navigasi admin">
        <div>
            <p class="mb-2 px-3 text-[10px] font-bold tracking-[0.16em] text-ink-500 uppercase">Ringkasan</p>
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ $active(['admin.dashboard']) ? 'bg-flame-500 text-ink-950' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                <x-icon name="home" class="h-5 w-5" /> Dasbor
            </a>
            <a href="{{ route('admin.orders.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ $active(['admin.orders.*']) ? 'bg-flame-500 text-ink-950' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                <x-icon name="shopping-bag" class="h-5 w-5" /> Pesanan
            </a>
        </div>

        <div>
            <p class="mb-2 px-3 text-[10px] font-bold tracking-[0.16em] text-ink-500 uppercase">Data master</p>
            @foreach ($resources as $resource)
                <a href="{{ \Illuminate\Support\Facades\Route::has('admin.resources.index') ? route('admin.resources.index', ['resourceType' => $resource['type']]) : route('admin.'.$resource['type'].'.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ request()->route('resourceType') === $resource['type'] ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                    <x-icon :name="$resource['icon']" class="h-5 w-5" /> {{ $resource['label'] }}
                </a>
            @endforeach
            <a href="{{ route('admin.prices.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ $active(['admin.prices.*']) ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                <x-icon name="calculator" class="h-5 w-5" /> Harga
            </a>
        </div>

        <div>
            <p class="mb-2 px-3 text-[10px] font-bold tracking-[0.16em] text-ink-500 uppercase">Operasional</p>
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.payments') ? route('admin.payments') : route('admin.payments.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('admin.payments*') ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                <x-icon name="credit-card" class="h-5 w-5" /> Pembayaran
            </a>
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.designs') ? route('admin.designs') : route('admin.designs.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('admin.designs*') ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                <x-icon name="palette" class="h-5 w-5" /> Desain
            </a>
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.production') ? route('admin.production') : route('admin.production.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('admin.production*') ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                <x-icon name="factory" class="h-5 w-5" /> Produksi
            </a>
        </div>

        <div>
            <p class="mb-2 px-3 text-[10px] font-bold tracking-[0.16em] text-ink-500 uppercase">Laporan</p>
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.customers') ? route('admin.customers') : route('admin.customers.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('admin.customers*') ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                <x-icon name="users" class="h-5 w-5" /> Pelanggan
            </a>
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.operators') ? route('admin.operators') : route('admin.operators.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('admin.operators*') ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                <x-icon name="user-cog" class="h-5 w-5" /> Operator
            </a>
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.reports') ? route('admin.reports') : route('admin.reports.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('admin.reports*') ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                <x-icon name="chart" class="h-5 w-5" /> Laporan
            </a>
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.invoices') ? route('admin.invoices') : route('admin.invoices.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('admin.invoices*') ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}">
                <x-icon name="receipt" class="h-5 w-5" /> Faktur
            </a>
        </div>
    </nav>

    <div class="shrink-0 border-t border-white/10 p-3">
        <div class="flex items-center gap-3 rounded-md px-3 py-2">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-white/10 text-sm font-bold text-white">{{ strtoupper(substr($user->name ?? 'A', 0, 1)) }}</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-white">{{ $user->name ?? 'Administrator' }}</p>
                <p class="truncate text-xs text-ink-400">{{ $user->email ?? '' }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mt-1 flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold text-ink-400 transition hover:bg-white/5 hover:text-white">
                <x-icon name="logout" class="h-5 w-5" /> Keluar
            </button>
        </form>
    </div>
</aside>
