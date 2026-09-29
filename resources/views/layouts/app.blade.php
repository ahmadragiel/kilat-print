@php $errors = $errors ?? new \Illuminate\Support\ViewErrorBag; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Kilat Print, layanan cetak dan produksi kustom dalam satu sistem.')">
    <title>@yield('title', 'Kilat Print')</title>
    <meta name="theme-color" content="#e11d2e">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ mobileMenu: false }" @keydown.escape.window="mobileMenu = false">
    <a href="#main-content" class="fixed left-3 top-3 z-[100] -translate-y-20 rounded-lg bg-ink-950 px-4 py-2 text-sm font-bold text-white transition focus:translate-y-0">Lewati ke konten</a>

    <x-customer-nav :cart-count="$cartCount ?? null" :cart="$cart ?? null" :unread-notifications="$unreadNotifications ?? null" />

    @if (session('logout_notice') || session('success') || session('status') || session('error') || $errors->any())
        <div
            x-data="flash"
            @if (session('logout_notice')) x-init="setTimeout(() => visible = false, 2000)" @endif
            x-show="visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="-translate-y-2 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="-translate-y-2 opacity-0"
            class="page-shell pt-4"
            role="region"
            aria-label="Pemberitahuan sistem"
        >
            <x-alert :type="session('error') || $errors->any() ? 'error' : 'success'">
                {{ session('logout_notice') ?? session('success') ?? session('status') ?? ($errors->any() ? $errors->first() : '') }}
            </x-alert>
        </div>
    @endif

    <main id="main-content">
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-brand-800 bg-ink-950 text-ink-300">
        <div class="h-1.5 bg-linear-to-r from-brand-600 via-brand-500 to-accent-400"></div>
        <div class="page-shell grid gap-8 py-12 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <x-brand inverse />
                <p class="mt-4 max-w-sm text-sm leading-6 text-ink-500">Kelola kebutuhan cetak, desain, pembayaran, dan produksi dengan satu alur kerja yang rapi.</p>
                <p class="mt-5 inline-flex items-center gap-2 rounded-full bg-brand-800 px-3 py-1.5 text-2xs font-black tracking-[0.14em] text-accent-300 uppercase">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent-400"></span> Cetak kilat, harga bersahabat
                </p>
            </div>
            <div>
                <p class="text-sm font-bold text-white">Katalog</p>
                <div class="mt-3 space-y-2 text-sm text-ink-500">
                    <a href="{{ route('products.index') }}" class="block transition hover:text-accent-300">Semua produk</a>
                    <a href="{{ route('products.index', ['sort' => 'popular']) }}" class="block transition hover:text-accent-300">Produk populer</a>
                    <a href="{{ route('products.index', ['sort' => 'latest']) }}" class="block transition hover:text-accent-300">Produk terbaru</a>
                </div>
            </div>
            <div>
                <p class="text-sm font-bold text-white">Pelanggan</p>
                <div class="mt-3 space-y-2 text-sm text-ink-500">
                    <a href="{{ route('customer.orders.index') }}" class="block transition hover:text-accent-300">Lacak pesanan</a>
                    <a href="{{ route('cart.index') }}" class="block transition hover:text-accent-300">Keranjang</a>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('customer.profile') ? route('customer.profile') : route('customer.profile.edit') }}" class="block transition hover:text-accent-300">Profil</a>
                </div>
            </div>
            <div>
                <p class="text-sm font-bold text-white">Bantuan</p>
                <div class="mt-3 space-y-2 text-sm text-ink-500">
                    <a href="{{ \Illuminate\Support\Facades\Route::has('customer.addresses') ? route('customer.addresses') : route('customer.addresses.index') }}" class="block transition hover:text-accent-300">Alamat pengiriman</a>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('customer.notifications') ? route('customer.notifications') : route('customer.notifications.index') }}" class="block transition hover:text-accent-300">Notifikasi</a>
                </div>
            </div>
        </div>
        <div class="border-t border-white/10 py-5">
            <div class="page-shell flex flex-col gap-2 text-xs text-ink-500 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} Kilat Print. Seluruh hak dilindungi.</p>
                <p>Cetak lebih jelas, kelola lebih rapi.</p>
            </div>
        </div>
    </footer>
</body>
</html>
