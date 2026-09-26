@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
<div class="page-shell py-10 sm:py-14">
    <div class="mx-auto grid w-full max-w-5xl overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-panel-lg lg:grid-cols-[.9fr_1.1fr]">

        {{-- Brand panel: red dominant, yellow accent, white type --}}
        <div class="brand-surface brand-stripes relative hidden p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-accent-400/20 blur-3xl"></div>
            <x-brand inverse class="relative" />
            <div class="relative">
                <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-2xs font-black tracking-[0.16em] text-accent-300 uppercase ring-1 ring-inset ring-white/20">
                    Selamat datang kembali
                </p>
                <h1 class="mt-5 text-3xl font-black leading-tight tracking-tight">Lanjutkan pesanan Anda di Kilat Print.</h1>
                <ul class="mt-7 space-y-3 text-sm text-brand-100">
                    @foreach ([
                        'Katalog dan harga selalu terupdate',
                        'Unggah desain lewat editor bawaan kami',
                        'Pantau status produksi hingga dikirim',
                    ] as $authBenefit)
                        <li class="flex items-start gap-2.5">
                            <span class="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full bg-accent-400 text-ink-950"><x-icon name="check" class="h-2.5 w-2.5" /></span>
                            {{ $authBenefit }}
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="relative text-sm leading-6 text-brand-200/90">Akses katalog, keranjang, pesanan, dan informasi akun dari satu tempat.</p>
        </div>

        <div class="p-6 sm:p-10 lg:p-12">
            <div class="mx-auto max-w-md">
                <div class="lg:hidden"><x-brand class="mb-8" /></div>
                <p class="section-kicker">Masuk</p>
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">Selamat datang kembali</h1>
                <p class="mt-2 text-sm text-ink-500">Gunakan akun yang terdaftar untuk melanjutkan.</p>

                <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('auth.login') ? route('auth.login') : route('login') }}" class="mt-8 space-y-5" aria-label="Formulir masuk">
                    @csrf
                    <x-input name="email" label="Email" type="email" autocomplete="email" placeholder="nama@email.com" required autofocus />
                    <x-input name="password" label="Kata sandi" type="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required />
                    <label class="flex items-center gap-2.5 text-sm text-ink-600">
                        <input type="checkbox" name="remember" value="1" class="form-check rounded">
                        Ingat saya di perangkat ini
                    </label>
                    <x-button type="submit" size="lg" class="w-full">Masuk <x-icon name="arrow-right" class="h-4 w-4" /></x-button>
                </form>

                <p class="mt-6 text-center text-sm text-ink-600">
                    Belum memiliki akun?
                    <a href="{{ \Illuminate\Support\Facades\Route::has('auth.register') ? route('auth.register') : route('register') }}" class="font-bold text-brand-700 transition hover:text-brand-800">Daftar sekarang</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
