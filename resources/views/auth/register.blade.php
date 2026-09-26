@extends('layouts.app')

@section('title', 'Daftar Akun')

@section('content')
<div class="page-shell flex min-h-[calc(100vh-4rem)] items-center justify-center py-12">
    <div class="grid w-full max-w-5xl overflow-hidden border border-ink-200 bg-white lg:grid-cols-[.9fr_1.1fr]">
        <div class="hidden bg-ink-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <x-brand inverse />
            <div>
                <p class="text-xs font-bold tracking-[0.16em] text-flame-400 uppercase">Akun Kilat Print</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight">Kelola setiap pesanan dengan lebih ringkas.</h1>
            </div>
            <p class="text-sm leading-6 text-ink-400">Simpan alamat, pantau progres, dan gunakan kembali pesanan sebelumnya.</p>
        </div>
        <div class="p-6 sm:p-10 lg:p-12">
            <div class="mx-auto max-w-md">
                <p class="section-kicker">Daftar</p>
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">Buat akun pelanggan</h1>
                <p class="mt-2 text-sm text-ink-500">Lengkapi data berikut untuk membuat akun.</p>

                <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('auth.register') ? route('auth.register') : route('register') }}" class="mt-8 space-y-5" aria-label="Formulir pendaftaran">
                    @csrf
                    <x-input name="name" label="Nama lengkap" autocomplete="name" placeholder="Nama sesuai identitas" required autofocus />
                    <x-input name="email" label="Email" type="email" autocomplete="email" placeholder="nama@email.com" required />
                    <x-input name="phone" label="Nomor telepon" type="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" required />
                    <x-input name="password" label="Kata sandi" type="password" autocomplete="new-password" placeholder="Minimal 8 karakter" required />
                    <x-input name="password_confirmation" label="Konfirmasi kata sandi" type="password" autocomplete="new-password" placeholder="Ulangi kata sandi" required />
                    <label class="flex items-start gap-2.5 text-sm leading-5 text-ink-600">
                        <input type="checkbox" name="terms" value="1" class="mt-0.5 h-4 w-4 rounded border-ink-300 text-flame-500 focus:ring-flame-300" required>
                        <span>Saya menyetujui ketentuan layanan Kilat Print.</span>
                    </label>
                    <x-button type="submit" class="w-full">Buat akun</x-button>
                </form>

                <p class="mt-6 text-center text-sm text-ink-600">
                    Sudah memiliki akun?
                    <a href="{{ \Illuminate\Support\Facades\Route::has('auth.login') ? route('auth.login') : route('login') }}" class="font-bold text-flame-700 hover:text-flame-800">Masuk</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
