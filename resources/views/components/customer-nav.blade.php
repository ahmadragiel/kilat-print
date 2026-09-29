@php
    $user = auth()->user();
    $isAdmin = $user?->isRole(\App\Enums\UserRole::Admin) ?? false;
    $cartItemsForCount = data_get($cart, 'items', []);
    $cartCount = $cartCount ?? data_get($cart, 'total_items', is_countable($cartItemsForCount) ? count($cartItemsForCount) : 0);
    $unreadNotifications = $unreadNotifications ?? data_get($user, 'unread_notifications_count', 0);
    $dashboardRoute = match (data_get($user, 'role.value', data_get($user, 'role'))) {
        'admin' => 'admin.dashboard',
        'operator' => 'operator.dashboard',
        default => 'customer.dashboard',
    };
@endphp

<header class="sticky top-0 z-40 border-b border-ink-200/90 bg-white/95 backdrop-blur">
    {{-- Brand hairline: the only saturated red in the navbar chrome --}}
    <div class="h-1 bg-linear-to-r from-brand-700 via-brand-500 to-accent-400"></div>
    <div class="page-shell flex h-16 items-center justify-between gap-5">
        <x-brand />

        <nav class="hidden items-center gap-7 lg:flex" aria-label="Navigasi utama">
            <div
                class="relative"
                x-data="{ productMenuOpen: false }"
                @mouseenter="productMenuOpen = true"
                @mouseleave="productMenuOpen = false"
                @focusin="productMenuOpen = true"
                @focusout="$nextTick(() => { if (!$el.contains(document.activeElement)) productMenuOpen = false })"
                @keydown.escape="productMenuOpen = false"
            >
                <button
                    type="button"
                    @click="productMenuOpen = !productMenuOpen"
                    :aria-expanded="productMenuOpen.toString()"
                    aria-haspopup="true"
                    aria-controls="product-dropdown"
                    class="inline-flex items-center gap-1.5 rounded-lg px-1 py-2 text-sm font-semibold transition {{ request()->routeIs('products.*') ? 'text-brand-700' : 'text-ink-600 hover:text-ink-950' }}"
                >
                    Produk
                    <x-icon name="chevron-down" class="h-4 w-4 transition-transform" x-bind:class="productMenuOpen ? 'rotate-180' : ''" />
                    @if (request()->routeIs('products.*'))<span class="absolute inset-x-1 -bottom-0.5 h-0.5 rounded-full bg-brand-600"></span>@endif
                </button>
                <div id="product-dropdown" x-show="productMenuOpen" x-cloak x-transition class="absolute left-0 top-full z-50 w-56 pt-2">
                    <div class="max-h-80 overflow-y-auto rounded-xl border border-ink-200 bg-white p-1.5 shadow-panel-lg" aria-label="Kategori produk">
                        @forelse ($navCategories as $category)
                            <a href="{{ route('products.index', ['category' => $category->slug]) }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold transition {{ request('category') === $category->slug ? 'bg-brand-50 text-brand-700' : 'text-ink-700 hover:bg-ink-50 hover:text-ink-950' }}">
                                {{ $category->name }}
                            </a>
                        @empty
                            <p class="px-3 py-2.5 text-sm text-ink-500">Belum ada kategori.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            @auth
                @unless ($isAdmin)
                <a href="{{ route('customer.orders.index') }}" class="relative rounded-lg px-1 py-2 text-sm font-semibold transition {{ request()->routeIs('customer.orders.*') ? 'text-brand-700' : 'text-ink-600 hover:text-ink-950' }}">
                    Pesanan
                    @if (request()->routeIs('customer.orders.*'))<span class="absolute inset-x-1 -bottom-0.5 h-0.5 rounded-full bg-brand-600"></span>@endif
                </a>
                @endunless
                <a href="{{ route($dashboardRoute) }}" class="relative rounded-lg px-1 py-2 text-sm font-semibold transition {{ request()->routeIs($dashboardRoute) ? 'text-brand-700' : 'text-ink-600 hover:text-ink-950' }}">
                    Dashboard
                    @if (request()->routeIs($dashboardRoute))<span class="absolute inset-x-1 -bottom-0.5 h-0.5 rounded-full bg-brand-600"></span>@endif
                </a>
            @endauth
        </nav>

        <div class="hidden items-center gap-2 lg:flex">
            {{-- Catalog search: plain GET to the existing catalog route --}}
            @unless ($isAdmin)
            <form method="GET" action="{{ route('products.index') }}" role="search" class="relative mr-1 hidden xl:block">
                <label for="nav-search" class="sr-only">Cari produk</label>
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" />
                <input id="nav-search" type="search" name="search" value="{{ request('search', request('q')) }}" placeholder="Cari produk…" class="h-10 w-48 rounded-lg border border-ink-200 bg-ink-50 pl-9 pr-3 text-sm text-ink-900 transition placeholder:text-ink-500 focus:border-brand-500 focus:bg-white focus:ring-3 focus:ring-brand-100 focus:outline-none">
            </form>
            @endunless
            @auth
                @unless ($isAdmin)
                <a href="{{ \Illuminate\Support\Facades\Route::has('customer.notifications') ? route('customer.notifications') : route('customer.notifications.index') }}" class="relative grid h-10 w-10 place-items-center rounded-lg text-ink-600 transition hover:bg-brand-50 hover:text-brand-700" aria-label="Notifikasi">
                    <x-icon name="bell" class="h-5 w-5" />
                    @if ((int) $unreadNotifications > 0)
                        <span class="absolute right-1 top-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-accent-400 px-1 text-[10px] font-black text-ink-950 ring-2 ring-white">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    @endif
                </a>
                <a href="{{ route('cart.index') }}" class="relative grid h-10 w-10 place-items-center rounded-lg text-ink-600 transition hover:bg-brand-50 hover:text-brand-700" aria-label="Keranjang">
                    <x-icon name="cart" class="h-5 w-5" />
                    @if ((int) $cartCount > 0)
                        <span class="absolute right-0 top-0 grid min-h-5 min-w-5 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-black text-white ring-2 ring-white">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
                    @endif
                </a>
                @endunless
                <div
                    class="relative ml-1"
                    x-data="{ accountMenuOpen: false }"
                    @mouseenter="accountMenuOpen = true"
                    @mouseleave="accountMenuOpen = false"
                    @focusin="accountMenuOpen = true"
                    @focusout="$nextTick(() => { if (!$el.contains(document.activeElement)) accountMenuOpen = false })"
                    @keydown.escape="accountMenuOpen = false"
                >
                    <button
                        type="button"
                        @click="accountMenuOpen = !accountMenuOpen"
                        :aria-expanded="accountMenuOpen.toString()"
                        aria-haspopup="true"
                        aria-controls="account-dropdown"
                        class="inline-flex items-center gap-2 rounded-lg border border-ink-200 py-1.5 pl-1.5 pr-3 text-sm font-semibold text-ink-800 transition hover:border-brand-300 hover:bg-brand-50"
                    >
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-brand-600 text-xs font-bold text-white">{{ strtoupper(substr($user->name ?? 'P', 0, 1)) }}</span>
                        <span class="max-w-32 truncate">{{ $user->name ?? 'Pelanggan' }}</span>
                        <x-icon name="chevron-down" class="h-4 w-4 transition-transform" x-bind:class="accountMenuOpen ? 'rotate-180' : ''" />
                    </button>

                    <div id="account-dropdown" x-show="accountMenuOpen" x-cloak x-transition class="absolute right-0 top-full z-50 w-56 pt-2">
                        <div class="overflow-hidden rounded-xl border border-ink-200 bg-white p-1.5 shadow-panel-lg" role="menu" aria-label="Menu akun">
                            <div class="border-b border-ink-100 px-3 py-2.5">
                                <p class="truncate text-sm font-bold text-ink-900">{{ $user->name ?? 'Pelanggan' }}</p>
                                <p class="truncate text-xs text-ink-500">{{ $user->email ?? '' }}</p>
                            </div>
                            @unless ($isAdmin)
                            <a href="{{ route('customer.profile.edit') }}" role="menuitem" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-ink-700 transition hover:bg-brand-50 hover:text-brand-700">Profil</a>
                            @endunless
                            <a href="{{ route($dashboardRoute) }}" role="menuitem" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-ink-700 transition hover:bg-brand-50 hover:text-brand-700">Dashboard</a>
                            @unless ($isAdmin)
                            <a href="{{ route('customer.orders.index') }}" role="menuitem" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-ink-700 transition hover:bg-brand-50 hover:text-brand-700">Pesanan</a>
                            <a href="{{ route('customer.addresses.index') }}" role="menuitem" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-ink-700 transition hover:bg-brand-50 hover:text-brand-700">Alamat</a>
                            <a href="{{ route('customer.notifications.index') }}" role="menuitem" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold text-ink-700 transition hover:bg-brand-50 hover:text-brand-700">
                                Notifikasi
                                @if ((int) $unreadNotifications > 0)
                                    <span class="grid min-h-5 min-w-5 place-items-center rounded-full bg-accent-400 px-1 text-[10px] font-black text-ink-950">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                                @endif
                            </a>
                            <div class="my-1 border-t border-ink-100"></div>
                            @endunless
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" role="menuitem" class="w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-600 transition hover:bg-red-50 hover:text-red-700">Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <x-button :href="\Illuminate\Support\Facades\Route::has('auth.login') ? route('auth.login') : route('login')" variant="ghost" size="sm">Masuk</x-button>
                <x-button :href="\Illuminate\Support\Facades\Route::has('auth.register') ? route('auth.register') : route('register')" size="sm">Daftar</x-button>
            @endauth
        </div>

        <button type="button" class="grid h-10 w-10 place-items-center rounded-lg border border-ink-200 text-ink-800 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 lg:hidden" x-on:click="mobileMenu = !mobileMenu" :aria-expanded="mobileMenu.toString()" aria-controls="mobile-navigation" aria-label="Buka navigasi">
            <x-icon name="menu" class="h-5 w-5" x-show="!mobileMenu" />
            <x-icon name="x" class="h-5 w-5" x-show="mobileMenu" x-cloak />
        </button>
    </div>

    <div id="mobile-navigation" x-show="mobileMenu" x-cloak x-transition class="border-t border-ink-200 bg-white lg:hidden" @click.outside="mobileMenu = false">
        <nav class="page-shell space-y-1 py-4" aria-label="Navigasi seluler">
            @unless ($isAdmin)
            <form method="GET" action="{{ route('products.index') }}" role="search" class="relative pb-2">
                <label for="nav-search-mobile" class="sr-only">Cari produk</label>
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 mt-1 h-4 w-4 -translate-y-1/2 text-ink-500" />
                <input id="nav-search-mobile" type="search" name="search" value="{{ request('search', request('q')) }}" placeholder="Cari produk…" class="form-control h-11 pl-10">
            </form>
            @endunless
            <div x-data="{ productCategoriesOpen: false }">
                <button type="button" @click="productCategoriesOpen = !productCategoriesOpen" :aria-expanded="productCategoriesOpen.toString()" aria-controls="mobile-product-categories" class="flex w-full items-center justify-between rounded-lg px-3 py-3 text-left text-sm font-semibold {{ request()->routeIs('products.*') ? 'bg-brand-50 text-brand-700' : 'text-ink-700 hover:bg-ink-50' }}">
                    Produk
                    <x-icon name="chevron-down" class="h-4 w-4 transition-transform" x-bind:class="productCategoriesOpen ? 'rotate-180' : ''" />
                </button>
                <div id="mobile-product-categories" x-show="productCategoriesOpen" x-cloak x-transition class="space-y-1 py-1 pl-3">
                    @forelse ($navCategories as $category)
                        <a href="{{ route('products.index', ['category' => $category->slug]) }}" @click="mobileMenu = false" class="block rounded-lg px-3 py-2.5 text-sm font-medium {{ request('category') === $category->slug ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-ink-50 hover:text-ink-950' }}">{{ $category->name }}</a>
                    @empty
                        <p class="px-3 py-2.5 text-sm text-ink-500">Belum ada kategori.</p>
                    @endforelse
                </div>
            </div>
            @auth
                <a href="{{ route($dashboardRoute) }}" class="flex items-center justify-between rounded-lg px-3 py-3 text-sm font-semibold {{ request()->routeIs($dashboardRoute) ? 'bg-brand-50 text-brand-700' : 'text-ink-700 hover:bg-ink-50' }}">Dashboard <x-icon name="chevron-right" class="h-4 w-4" /></a>
                @unless ($isAdmin)
                <a href="{{ route('customer.orders.index') }}" class="flex items-center justify-between rounded-lg px-3 py-3 text-sm font-semibold {{ request()->routeIs('customer.orders.*') ? 'bg-brand-50 text-brand-700' : 'text-ink-700 hover:bg-ink-50' }}">Pesanan <x-icon name="chevron-right" class="h-4 w-4" /></a>
                <a href="{{ route('cart.index') }}" class="flex items-center justify-between rounded-lg px-3 py-3 text-sm font-semibold text-ink-700 hover:bg-ink-50">Keranjang @if((int) $cartCount > 0)<x-badge color="brand">{{ $cartCount }}</x-badge>@else<x-icon name="chevron-right" class="h-4 w-4" />@endif</a>
                <a href="{{ \Illuminate\Support\Facades\Route::has('customer.notifications') ? route('customer.notifications') : route('customer.notifications.index') }}" class="flex items-center justify-between rounded-lg px-3 py-3 text-sm font-semibold text-ink-700 hover:bg-ink-50">Notifikasi @if((int) $unreadNotifications > 0)<x-badge color="accent">{{ $unreadNotifications }}</x-badge>@else<x-icon name="chevron-right" class="h-4 w-4" />@endif</a>
                <a href="{{ \Illuminate\Support\Facades\Route::has('customer.profile') ? route('customer.profile') : route('customer.profile.edit') }}" class="flex items-center justify-between rounded-lg px-3 py-3 text-sm font-semibold text-ink-700 hover:bg-ink-50">Profil <x-icon name="chevron-right" class="h-4 w-4" /></a>
                @endunless
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-between rounded-lg px-3 py-3 text-left text-sm font-semibold text-ink-700 hover:bg-ink-50">Keluar <x-icon name="logout" class="h-4 w-4" /></button>
                </form>
            @else
                <div class="grid grid-cols-2 gap-2 pt-3">
                    <x-button :href="\Illuminate\Support\Facades\Route::has('auth.login') ? route('auth.login') : route('login')" variant="outline">Masuk</x-button>
                    <x-button :href="\Illuminate\Support\Facades\Route::has('auth.register') ? route('auth.register') : route('register')">Daftar</x-button>
                </div>
            @endauth
        </nav>
    </div>
</header>
