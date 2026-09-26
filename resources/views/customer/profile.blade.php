@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')
<div class="page-shell py-8 sm:py-10">
    <x-page-header title="Profil saya" description="Kelola informasi akun dan kata sandi." eyebrow="Akun pelanggan">
        <x-slot:actions>
            <x-button :href="\Illuminate\Support\Facades\Route::has('customer.addresses') ? route('customer.addresses') : route('customer.addresses.index')" variant="secondary"><x-icon name="map-pin" class="h-4 w-4" /> Alamat</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mt-8 grid items-start gap-8 lg:grid-cols-[280px_1fr]">
        <aside class="panel p-5 text-center">
            <span class="mx-auto grid h-20 w-20 place-items-center rounded-2xl bg-brand-600 text-2xl font-black text-white shadow-brand">{{ strtoupper(substr(auth()->user()->name ?? 'P', 0, 1)) }}</span>
            <h2 class="mt-4 font-extrabold text-ink-950">{{ auth()->user()->name ?? 'Pelanggan' }}</h2>
            <p class="mt-1 truncate text-sm text-ink-500">{{ auth()->user()->email ?? '' }}</p>
            <div class="mt-5 border-t border-ink-100 pt-5 text-left">
                <p class="text-2xs font-extrabold tracking-[0.1em] text-ink-600 uppercase">Pelanggan sejak</p>
                <p class="mt-1 text-sm font-bold text-ink-800">{{ auth()->user()->created_at?->format('F Y') ?? 'Belum tersedia' }}</p>
            </div>
        </aside>

        <section class="panel p-5 sm:p-7" aria-labelledby="profile-form-title">
            <h2 id="profile-form-title" class="text-lg font-extrabold text-ink-950">Informasi akun</h2>
            <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('customer.profile.update') ? route('customer.profile.update') : route('customer.profile.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PATCH')
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="name" label="Nama lengkap" :value="auth()->user()->name" autocomplete="name" required />
                    <x-input name="email" label="Email" type="email" :value="auth()->user()->email" autocomplete="email" required />
                    <x-input name="phone" label="Nomor telepon" type="tel" :value="auth()->user()->customer->phone ?? auth()->user()->phone" autocomplete="tel" />
                </div>

                <div class="border-t border-ink-200 pt-6">
                    <h3 class="font-bold text-ink-950">Ubah kata sandi</h3>
                    <p class="mt-1 text-sm text-ink-500">Isi bagian berikut hanya jika ingin mengganti kata sandi.</p>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <x-input name="current_password" label="Kata sandi saat ini" type="password" autocomplete="current-password" />
                        <x-input name="password" label="Kata sandi baru" type="password" autocomplete="new-password" />
                        <x-input name="password_confirmation" label="Konfirmasi kata sandi baru" type="password" autocomplete="new-password" />
                    </div>
                </div>

                <div class="flex justify-end border-t border-ink-100 pt-5">
                    <x-button type="submit">Simpan perubahan</x-button>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection
