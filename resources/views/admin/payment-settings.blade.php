@extends('layouts.admin')

@section('title', 'Konfigurasi Penyedia Pembayaran')

@section('content')
@php
    $sourceLabel = fn (string $source) => $source === 'dashboard'
        ? 'Disimpan di dashboard'
        : 'Dari .env';
@endphp

<div class="space-y-7">
    <x-page-header
        title="Konfigurasi penyedia pembayaran"
        description="Masukkan kredensial Midtrans untuk mengaktifkan QRIS dan transfer bank. Nilai disimpan terenkripsi dan tidak pernah ditampilkan lagi."
        eyebrow="Operasional"
    />

    @if ($sandbox)
        <x-alert type="warning" title="Mode sandbox aktif">
            Semua transaksi diarahkan ke <code>{{ $baseUrl }}</code>. Jangan gunakan kredensial sandbox untuk transaksi production.
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
        <form method="POST" action="{{ route('admin.payment-settings.update') }}" class="space-y-6" autocomplete="off">
            @csrf
            @method('PUT')

            <section class="panel p-5 sm:p-6" aria-labelledby="credentials-title">
                <div class="panel-head -mx-5 -mt-5 mb-5 sm:-mx-6 sm:-mt-6">
                    <h2 id="credentials-title" class="font-extrabold text-ink-950">Kredensial</h2>
                </div>

                <div class="space-y-5">
                    <div>
                        <x-input
                            name="server_key"
                            label="Server Key"
                            type="password"
                            value=""
                            autocomplete="off"
                            spellcheck="false"
                        />
                        <p class="mt-1.5 text-xs text-ink-500">
                            @if ($record?->hasServerKey())
                                <span class="font-semibold text-emerald-700">Tersimpan ({{ $sourceLabel($sources['server_key']) }})</span> — biarkan kosong untuk mempertahankan nilai saat ini.
                            @else
                                <span class="font-semibold text-amber-700">Belum diisi</span> — wajib diisi sebelum pembayaran online dapat dipakai.
                            @endif
                        </p>
                    </div>

                    <div>
                        <x-input
                            name="client_key"
                            label="Client Key"
                            type="password"
                            value=""
                            autocomplete="off"
                            spellcheck="false"
                        />
                        <p class="mt-1.5 text-xs text-ink-500">
                            @if ($record?->hasClientKey())
                                <span class="font-semibold text-emerald-700">Tersimpan ({{ $sourceLabel($sources['client_key']) }})</span> — biarkan kosong untuk mempertahankan nilai saat ini.
                            @else
                                Opsional. Hanya dibutuhkan untuk integrasi frontend Snap.js.
                            @endif
                        </p>
                    </div>

                    <div>
                        <x-input
                            name="merchant_id"
                            label="Merchant ID"
                            type="password"
                            value=""
                            autocomplete="off"
                            spellcheck="false"
                        />
                        <p class="mt-1.5 text-xs text-ink-500">
                            @if ($record?->hasMerchantId())
                                <span class="font-semibold text-emerald-700">Tersimpan ({{ $sourceLabel($sources['merchant_id']) }})</span> — biarkan kosong untuk mempertahankan nilai saat ini.
                            @else
                                Opsional. Dipakai untuk identifikasi akun dan diagnostik.
                            @endif
                        </p>
                    </div>
                </div>

                <label class="mt-6 flex cursor-pointer items-start gap-3 rounded-lg border border-ink-200 p-4 transition hover:border-brand-300 hover:bg-ink-50">
                    <input type="hidden" name="sandbox" value="0">
                    <input type="checkbox" name="sandbox" value="1" class="form-check mt-1" @checked($sandbox)>
                    <span class="min-w-0 flex-1 text-sm">
                        <span class="flex flex-wrap items-center gap-2 font-bold text-ink-900">Mode sandbox</span>
                        <span class="mt-1 block leading-5 text-ink-500">
                            Centang selama pengujian. Nonaktifkan hanya setelah memakai kredensial production dan memastikan domain webhook sudah HTTPS.
                        </span>
                    </span>
                </label>

                <div class="mt-6 flex flex-wrap gap-3 border-t border-ink-100 pt-5">
                    <x-button type="submit">Simpan konfigurasi</x-button>
                    @if ($record?->hasServerKey() || $record?->hasClientKey() || $record?->hasMerchantId())
                        <x-button type="submit" form="clear-payment-credentials" variant="secondary">Hapus kredensial dashboard</x-button>
                    @endif
                </div>
            </section>
        </form>

        <aside class="space-y-6">
            <section class="panel p-5" aria-labelledby="status-title">
                <h2 id="status-title" class="font-extrabold text-ink-950">Status</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Driver</dt>
                        <dd class="font-semibold text-ink-800">{{ $driver }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Endpoint</dt>
                        <dd class="text-right font-mono text-xs break-all text-ink-700">{{ $baseUrl }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Server key</dt>
                        <dd>
                            @if ($isConfigured)
                                <x-badge color="green">Aktif</x-badge>
                            @else
                                <x-badge color="accent">Belum lengkap</x-badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Mata uang</dt>
                        <dd class="font-semibold text-ink-800">{{ $currency }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Masa berlaku</dt>
                        <dd class="font-semibold text-ink-800">{{ $expiryHours }} jam</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Bank transfer</dt>
                        <dd class="font-semibold text-ink-800 uppercase">{{ $bank }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Diperbarui</dt>
                        <dd class="text-ink-700">{{ $record?->updated_at?->format('d M Y, H:i') ?? '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-4 border-t border-ink-100 pt-4">
                    <p class="text-xs leading-5 text-ink-500">Metode pembayaran aktif:</p>
                    <p class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($paymentMethods as $method)
                            <x-badge :color="$isConfigured ? 'brand' : 'gray'">{{ $method->label() }}</x-badge>
                        @endforeach
                    </p>
                </div>
            </section>

            <section class="panel p-5" aria-labelledby="webhook-title">
                <h2 id="webhook-title" class="font-extrabold text-ink-950">Webhook</h2>
                <p class="mt-3 text-xs leading-5 text-ink-500">
                    Daftarkan alamat berikut sebagai <strong>Payment Notification URL</strong> di dashboard Midtrans.
                    Alamat harus dapat dijangkau dari internet; <code>localhost</code> tidak bisa menerima callback.
                </p>
                <p class="mt-3 rounded-lg bg-ink-50 px-3 py-2 font-mono text-xs break-all text-ink-800">{{ config('app.url').$webhookPath }}</p>
            </section>

            <section class="panel p-5" aria-labelledby="security-title">
                <h2 id="security-title" class="font-extrabold text-ink-950">Keamanan</h2>
                <ul class="mt-3 space-y-2 text-xs leading-5 text-ink-600">
                    <li>Kredensial disimpan terenkripsi di database dan tidak pernah ditampilkan lagi.</li>
                    <li>Server key hanya dipakai di header <code>Authorization</code> pada permintaan server-ke-server.</li>
                    <li>Nilai kredensial tidak pernah masuk ke log atau halaman error.</li>
                    <li>Kosongkan sebuah kolom untuk mempertahankan nilai yang tersimpan.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>

@if ($record?->hasServerKey() || $record?->hasClientKey() || $record?->hasMerchantId())
    <form id="clear-payment-credentials" method="POST" action="{{ route('admin.payment-settings.destroy') }}"
        x-data="confirmAction('Hapus kredensial dari dashboard dan kembali memakai konfigurasi .env?')" x-on:submit="confirm">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection