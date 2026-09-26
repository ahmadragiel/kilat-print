@php $errors = $errors ?? new \Illuminate\Support\ViewErrorBag; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Admin') · Kilat Print</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false" class="bg-ink-50">
    <div class="min-h-screen lg:grid lg:grid-cols-[264px_1fr]">
        <div class="fixed inset-0 z-40 hidden bg-ink-950/60 lg:hidden" x-show="sidebar" x-cloak x-transition.opacity x-on:click="sidebar = false" aria-hidden="true"></div>
        <aside class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full transition-transform duration-200 lg:static lg:translate-x-0" x-show="sidebar || window.innerWidth >= 1024" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" x-cloak>
            <x-admin-sidebar />
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-30 flex h-16 items-center border-b border-ink-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                <button type="button" class="mr-3 grid h-10 w-10 place-items-center rounded-md border border-ink-200 text-ink-700 lg:hidden" x-on:click="sidebar = !sidebar" aria-label="Buka sidebar">
                    <x-icon name="menu" class="h-5 w-5" />
                </button>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-ink-900">@yield('title', 'Dasbor Admin')</p>
                    <p class="hidden text-xs text-ink-500 sm:block">Kelola operasional Kilat Print</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ \Illuminate\Support\Facades\Route::has('admin.reports') ? route('admin.reports') : route('admin.reports.index') }}" class="hidden items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold text-ink-600 transition hover:bg-ink-100 hover:text-ink-950 sm:flex">
                        <x-icon name="chart" class="h-4 w-4" /> Laporan
                    </a>
                    <span class="grid h-9 w-9 place-items-center rounded-md bg-ink-950 text-xs font-bold text-white">{{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}</span>
                </div>
            </header>

            @if (session('success') || session('status') || session('error') || $errors->any())
                <div x-data="flash" x-show="visible" x-transition class="px-4 pt-5 sm:px-6 lg:px-8">
                    <x-alert :type="session('error') || $errors->any() ? 'error' : 'success'">
                        {{ session('success') ?? session('status') ?? ($errors->any() ? $errors->first() : '') }}
                    </x-alert>
                </div>
            @endif

            <main class="p-4 sm:p-6 lg:p-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
