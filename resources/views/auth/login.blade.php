@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
<div class="page-shell flex min-h-[calc(100vh-4rem)] items-center justify-center py-12">
    <div class="grid w-full max-w-5xl overflow-hidden border border-ink-200 bg-white lg:grid-cols-[.9fr_1.1fr]">
        <div class="hidden bg-ink-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <x-brand inverse />
            <div>
                <p class="text-xs font-bold tracking-[0.16em] text-flame-400 uppercase">Selamat datang kembali</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight">Lanjutkan pesanan Anda di Kilat Print.</h1>
            </div>
            <p class="text-sm leading-6 text-ink-400">Akses katalog, keranjang, pesanan, dan informasi akun dari satu tempat.</p>
        </div>
        <div class="p-6 sm:p-10 lg:p-12">
            <div class="mx-auto max-w-md">
                <p class="section-kicker">Masuk</p>
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">Selamat datang kembali</h1>
                <p class="mt-2 text-sm text-ink-500">Gunakan akun yang terdaftar untuk melanjutkan.</p>

                <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('auth.login') ? route('auth.login') : route('login') }}" class="mt-8 space-y-5" aria-label="Formulir masuk">
                    @csrf
                    <x-input name="email" label="Email" type="email" autocomplete="email" placeholder="nama@email.com" required autofocus />
                    <x-input name="password" label="Kata sandi" type="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required />
                    <label class="flex items-center gap-2.5 text-sm text-ink-600">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-ink-300 text-flame-500 focus:ring-flame-300">
                        Ingat saya di perangkat ini
                    </label>
                    <x-button type="submit" class="w-full">Masuk</x-button>
                </form>

                <p class="mt-6 text-center text-sm text-ink-600">
                    Belum memiliki akun?
                    <a href="{{ \Illuminate\Support\Facades\Route::has('auth.register') ? route('auth.register') : route('register') }}" class="font-bold text-flame-700 hover:text-flame-800">Daftar sekarang</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
