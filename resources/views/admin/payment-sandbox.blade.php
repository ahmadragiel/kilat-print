@extends('layouts.admin')

@section('title', 'Simulasi Sandbox')

@section('content')
<div class="space-y-7">
    <x-page-header
        title="Simulasi pembayaran sandbox"
        description="Bayar transaksi uji dengan simulator Midtrans, lalu pastikan aplikasi menerima hasil sukses maupun gagal dari penyedia."
        eyebrow="Operasional"
    />

    <x-alert type="warning" title="Hanya untuk sandbox">
        Halaman ini menolak berjalan bila gateway tidak mengarah ke lingkungan uji Midtrans
        (<code>{{ $baseUrl }}</code>). Tidak ada status pembayaran yang diubah langsung dari sini: setiap
        hasil tetap dibaca dari penyedia dan diproses oleh alur settlement yang sama seperti produksi.
    </x-alert>

    <section class="panel p-5 sm:p-6" aria-labelledby="how-title">
        <h2 id="how-title" class="font-extrabold text-ink-950">Cara menguji pembayaran berhasil</h2>
        <ol class="mt-4 space-y-3 text-sm leading-6 text-ink-700">
            <li><strong class="text-ink-900">1.</strong> Buka halaman pembayaran pesanan sebagai customer, lalu salin tautan gambar QRIS.</li>
            <li><strong class="text-ink-900">2.</strong> Buka <a href="{{ $simulatorUrl }}" target="_blank" rel="noopener" class="action-link inline-flex">Midtrans QRIS Simulator <x-icon name="external" class="h-4 w-4" /></a>, tempel tautan tersebut, lalu klik <em>Scan QR</em>.</li>
            <li><strong class="text-ink-900">3.</strong> Pilih e-wallet dan selesaikan pembayaran di simulator.</li>
            <li><strong class="text-ink-900">4.</strong> Kembali ke tabel di bawah lalu klik <strong>Cek ke penyedia</strong> untuk membaca hasil dari Midtrans.</li>
        </ol>
        <p class="mt-4 text-xs leading-5 text-ink-500">
            Di production, langkah 4 tidak perlu manual: Midtrans mengirim webhook ke
            <code>{{ route('payments.webhook.midtrans', absolute: false) }}</code> dan halaman customer menampilkan
            hasilnya secara otomatis.
        </p>
    </section>

    <section class="panel p-5 sm:p-6" aria-labelledby="fail-title">
        <h2 id="fail-title" class="font-extrabold text-ink-950">Cara menguji pembayaran gagal</h2>
        <p class="mt-3 text-sm leading-6 text-ink-700">
            Klik <strong>Paksa kedaluwarsa</strong> pada transaksi uji. Aplikasi memanggil endpoint
            <code>POST /v2/{order_id}/expire</code> milik Midtrans, jadi status berubah di sisi penyedia dan
            penyedia mengirim notifikasi seperti pada kasus nyata.
        </p>
    </section>

    <div class="panel overflow-hidden">
        <div class="panel-head">
            <h2 class="font-extrabold text-ink-950">Transaksi uji yang menunggu</h2>
        </div>

        @if ($pending->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pesanan</th>
                            <th>Metode</th>
                            <th>Nominal</th>
                            <th>Instruksi</th>
                            <th>Status</th>
                            <th><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pending as $payment)
                            @php
                                $qr = $payment->safeQrImage();
                                $isQris = $payment->method === \App\Enums\PaymentMethod::Qris->value;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.orders.show', $payment->order) }}" class="font-semibold text-ink-950 hover:text-brand-700">
                                        #{{ data_get($payment, 'order.number') }}
                                    </a>
                                    <p class="mt-1 font-mono text-xs break-all text-ink-500">{{ $payment->provider_reference }}</p>
                                </td>
                                <td>{{ $payment->paymentMethod()?->label() ?? $payment->method }}</td>
                                <td class="font-extrabold text-brand-700"><x-money :value="$payment->amount" /></td>
                                <td>
                                    @if ($isQris && $qr)
                                        <a href="{{ $qr }}" target="_blank" rel="noopener" class="action-link">Buka QR</a>
                                    @else
                                        <span class="font-mono text-xs text-ink-700">{{ $payment->virtual_account_number ?? '—' }}</span>
                                    @endif
                                </td>
                                <td><x-status-badge :status="$payment->status" /></td>
                                <td class="whitespace-nowrap text-right">
                                    <form method="POST" action="{{ route('admin.payment-sandbox.check', $payment) }}" class="inline">
                                        @csrf
                                        <x-button type="submit" size="sm">Cek ke penyedia</x-button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.payment-sandbox.expire', $payment) }}" class="ml-2 inline"
                                          x-data="confirmAction('Paksa transaksi ini kedaluwarsa di Midtrans?')" x-on:submit="confirm">
                                        @csrf
                                        <x-button type="submit" size="sm" variant="secondary">Paksa kedaluwarsa</x-button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state
                title="Tidak ada transaksi menunggu"
                description="Buat transaksi uji dengan `php artisan payments:sandbox-transaction` atau lewat checkout customer."
                icon="credit-card"
            />
        @endif
    </div>
</div>
@endsection