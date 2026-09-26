@php
    $user = auth()->user();
    $cartItemsForCount = data_get($cart, 'items', []);
    $cartCount = $cartCount ?? data_get($cart, 'total_items', is_countable($cartItemsForCount) ? count($cartItemsForCount) : 0);
    $unreadNotifications = $unreadNotifications ?? data_get($user, 'unread_notifications_count', 0);
@endphp

<header class="sticky top-0 z-40 border-b border-ink-200/90 bg-white/95 backdrop-blur">
    <div class="page-shell flex h-16 items-center justify-between gap-5">
        <x-brand />

        <nav class="hidden items-center gap-7 lg:flex" aria-label="Navigasi utama">
            <a href="{{ route('products.index') }}" class="text-sm font-semibold text-ink-600 transition hover:text-ink-950 {{ request()->routeIs('products.*') ? 'text-ink-950' : '' }}">Katalog</a>
            @auth
                <a href="{{ route('customer.orders.index') }}" class="text-sm font-semibold text-ink-600 transition hover:text-ink-950 {{ request()->routeIs('customer.orders.*') ? 'text-ink-950' : '' }}">Pesanan</a>
                <a href="{{ route('customer.dashboard') }}" class="text-sm font-semibold text-ink-600 transition hover:text-ink-950 {{ request()->routeIs('customer.dashboard') ? 'text-ink-950' : '' }}">Dasbor</a>
            @endauth
        </nav>

        <div class="hidden items-center gap-2 lg:flex">
            @auth
                <a href="{{ \Illuminate\Support\Facades\Route::has('customer.notifications') ? route('customer.notifications') : route('customer.notifications.index') }}" class="relative grid h-10 w-10 place-items-center rounded-md text-ink-600 transition hover:bg-ink-100 hover:text-ink-950" aria-label="Notifikasi">
                    <x-icon name="bell" class="h-5 w-5" />
                    @if ((int) $unreadNotifications > 0)
                        <span class="absolute right-1.5 top-1.5 grid min-h-4 min-w-4 place-items-center rounded-full bg-flame-500 px-1 text-[10px] font-bold text-ink-950">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    @endif
                </a>
                <a href="{{ route('cart.index') }}" class="relative grid h-10 w-10 place-items-center rounded-md text-ink-600 transition hover:bg-ink-100 hover:text-ink-950" aria-label="Keranjang">
                    <x-icon name="cart" class="h-5 w-5" />
                    @if ((int) $cartCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 grid min-h-5 min-w-5 place-items-center rounded-full bg-flame-500 px-1 text-[10px] font-bold text-ink-950">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
                    @endif
                </a>
                <a href="{{ \Illuminate\Support\Facades\Route::has('customer.profile') ? route('customer.profile') : route('customer.profile.edit') }}" class="ml-1 inline-flex items-center gap-2 rounded-md border border-ink-200 py-1.5 pl-2 pr-3 text-sm font-semibold text-ink-800 transition hover:bg-ink-50">
                    <span class="grid h-7 w-7 place-items-center rounded bg-ink-950 text-xs font-bold text-white">{{ strtoupper(substr($user->name ?? 'P', 0, 1)) }}</span>
                    <span class="max-w-32 truncate">{{ $user->name ?? 'Pelanggan' }}</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md px-3 py-2 text-sm font-semibold text-ink-600 transition hover:bg-ink-100 hover:text-ink-950">Keluar</button>
                </form>
            @else
                <x-button :href="\Illuminate\Support\Facades\Route::has('auth.login') ? route('auth.login') : route('login')" variant="ghost" size="sm">Masuk</x-button>
                <x-button :href="\Illuminate\Support\Facades\Route::has('auth.register') ? route('auth.register') : route('register')" size="sm">Daftar</x-button>
            @endauth
        </div>

        <button type="button" class="grid h-10 w-10 place-items-center rounded-md border border-ink-200 text-ink-800 lg:hidden" x-on:click="mobileMenu = !mobileMenu" :aria-expanded="mobileMenu.toString()" aria-controls="mobile-navigation" aria-label="Buka navigasi">
            <x-icon name="menu" class="h-5 w-5" x-show="!mobileMenu" />
            <x-icon name="x" class="h-5 w-5" x-show="mobileMenu" x-cloak />
        </button>
    </div>

    <div id="mobile-navigation" x-show="mobileMenu" x-cloak x-transition class="border-t border-ink-200 bg-white lg:hidden" @click.outside="mobileMenu = false">
        <nav class="page-shell space-y-1 py-4" aria-label="Navigasi seluler">
            <a href="{{ route('products.index') }}" class="flex items-center justify-between rounded-md px-3 py-2.5 text-sm font-semibold text-ink-700 hover:bg-ink-50">Katalog <x-icon name="chevron-right" class="h-4 w-4" /></a>
            @auth
                <a href="{{ route('customer.dashboard') }}" class="flex items-center justify-between rounded-md px-3 py-2.5 text-sm font-semibold text-ink-700 hover:bg-ink-50">Dasbor <x-icon name="chevron-right" class="h-4 w-4" /></a>
                <a href="{{ route('customer.orders.index') }}" class="flex items-center justify-between rounded-md px-3 py-2.5 text-sm font-semibold text-ink-700 hover:bg-ink-50">Pesanan <x-icon name="chevron-right" class="h-4 w-4" /></a>
                <a href="{{ route('cart.index') }}" class="flex items-center justify-between rounded-md px-3 py-2.5 text-sm font-semibold text-ink-700 hover:bg-ink-50">Keranjang @if((int) $cartCount > 0)<x-badge color="orange">{{ $cartCount }}</x-badge>@else<x-icon name="chevron-right" class="h-4 w-4" />@endif</a>
                <a href="{{ \Illuminate\Support\Facades\Route::has('customer.notifications') ? route('customer.notifications') : route('customer.notifications.index') }}" class="flex items-center justify-between rounded-md px-3 py-2.5 text-sm font-semibold text-ink-700 hover:bg-ink-50">Notifikasi <x-icon name="chevron-right" class="h-4 w-4" /></a>
                <a href="{{ \Illuminate\Support\Facades\Route::has('customer.profile') ? route('customer.profile') : route('customer.profile.edit') }}" class="flex items-center justify-between rounded-md px-3 py-2.5 text-sm font-semibold text-ink-700 hover:bg-ink-50">Profil <x-icon name="chevron-right" class="h-4 w-4" /></a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-between rounded-md px-3 py-2.5 text-left text-sm font-semibold text-ink-700 hover:bg-ink-50">Keluar <x-icon name="logout" class="h-4 w-4" /></button>
                </form>
            @else
                <div class="grid grid-cols-2 gap-2 pt-3">
                    <x-button :href="\Illuminate\Support\Facades\Route::has('auth.login') ? route('auth.login') : route('login')" variant="secondary">Masuk</x-button>
                    <x-button :href="\Illuminate\Support\Facades\Route::has('auth.register') ? route('auth.register') : route('register')">Daftar</x-button>
                </div>
            @endauth
        </nav>
    </div>
</header>
