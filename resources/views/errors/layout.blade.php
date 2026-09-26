<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="theme-color" content="#e11d2e">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <title>@yield('title', 'Terjadi kesalahan') · Kilat Print</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-50">
    <main class="grid min-h-screen place-items-center px-5 py-12">
        <section class="w-full max-w-lg overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-panel-lg">
            <div class="h-1.5 bg-linear-to-r from-brand-600 via-brand-500 to-accent-400"></div>
            <div class="p-8 text-center sm:p-10">
                <x-brand class="justify-center" />
                <p class="mt-8 text-sm font-black tracking-[0.2em] text-brand-600 uppercase">@yield('code', 'ERROR')</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-ink-950">@yield('heading', 'Permintaan tidak dapat diproses')</h1>
                <p class="mt-3 text-sm leading-7 text-ink-600">@yield('message', 'Terjadi kendala yang tidak terduga. Silakan coba kembali.')</p>
                <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                    <x-button :href="route('home')">Kembali ke beranda</x-button>
                    @auth
                        <x-button :href="auth()->user()->isRole(\App\Enums\UserRole::Admin) ? route('admin.dashboard') : (auth()->user()->isRole(\App\Enums\UserRole::Operator) ? route('operator.dashboard') : route('customer.dashboard'))" variant="outline">Buka dashboard</x-button>
                    @endauth
                </div>
            </div>
        </section>
    </main>
</body>
</html>
