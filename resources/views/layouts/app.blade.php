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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ mobileMenu: false }" @keydown.escape.window="mobileMenu = false">
    <a href="#main-content" class="fixed left-3 top-3 z-[100] -translate-y-20 rounded-md bg-ink-950 px-4 py-2 text-sm font-bold text-white transition focus:translate-y-0">Lewati ke konten</a>

    <x-customer-nav :cart-count="$cartCount ?? null" :cart="$cart ?? null" :unread-notifications="$unreadNotifications ?? null" />

    @if (session('success') || session('status') || session('error') || $errors->any())
        <div x-data="flash" x-show="visible" x-transition class="page-shell pt-4" role="region" aria-label="Pemberitahuan sistem">
            <x-alert :type="session('error') || $errors->any() ? 'error' : 'success'">
                {{ session('success') ?? session('status') ?? ($errors->any() ? $errors->first() : '') }}
            </x-alert>
        </div>
    @endif

    <main id="main-content">
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-ink-200 bg-white">
        <div class="page-shell grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <x-brand />
                <p class="mt-4 max-w-sm text-sm leading-6 text-ink-500">Kelola kebutuhan cetak, desain, pembayaran, dan produksi dengan satu alur kerja yang rapi.</p>
            </div>
            <div>
                <p class="text-sm font-bold text-ink-900">Katalog</p>
                <div class="mt-3 space-y-2 text-sm text-ink-500">
                    <a href="{{ route('products.index') }}" class="block hover:text-flame-700">Semua produk</a>
                    <a href="{{ route('products.index', ['sort' => 'popular']) }}" class="block hover:text-flame-700">Produk populer</a>
                    <a href="{{ route('products.index', ['sort' => 'latest']) }}" class="block hover:text-flame-700">Produk terbaru</a>
                </div>
            </div>
            <div>
                <p class="text-sm font-bold text-ink-900">Pelanggan</p>
                <div class="mt-3 space-y-2 text-sm text-ink-500">
                    <a href="{{ route('customer.orders.index') }}" class="block hover:text-flame-700">Lacak pesanan</a>
                    <a href="{{ route('cart.index') }}" class="block hover:text-flame-700">Keranjang</a>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('customer.profile') ? route('customer.profile') : route('customer.profile.edit') }}" class="block hover:text-flame-700">Profil</a>
                </div>
            </div>
            <div>
                <p class="text-sm font-bold text-ink-900">Bantuan</p>
                <div class="mt-3 space-y-2 text-sm text-ink-500">
                    <a href="{{ \Illuminate\Support\Facades\Route::has('customer.addresses') ? route('customer.addresses') : route('customer.addresses.index') }}" class="block hover:text-flame-700">Alamat pengiriman</a>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('customer.notifications') ? route('customer.notifications') : route('customer.notifications.index') }}" class="block hover:text-flame-700">Notifikasi</a>
                </div>
            </div>
        </div>
        <div class="border-t border-ink-100 py-5">
            <div class="page-shell flex flex-col gap-2 text-xs text-ink-500 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} Kilat Print. Seluruh hak dilindungi.</p>
                <p>Cetak lebih jelas, kelola lebih rapi.</p>
            </div>
        </div>
    </footer>
</body>
</html>
