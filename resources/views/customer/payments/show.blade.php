@extends('layouts.app')

@section('title', 'Pembayaran '.(data_get($order, 'number')))

@section('content')
@php
    $isQris = data_get($payment, 'method') === \App\Enums\PaymentMethod::Qris->value;
    $isPaid = data_get($payment, 'status') === \App\Enums\PaymentStatus::Paid;
    $qrImage = $payment->safeQrImage();
    $methodLabel = $payment->paymentMethod()?->label() ?? $payment->method;
    $statusUrl = route('customer.orders.payment-status', $order);
    $isSandbox = app(\App\Services\Payment\PaymentStatusPoller::class)->isSandbox();
    $simulatorUrl = \App\Services\Payment\PaymentStatusPoller::simulatorUrl();
@endphp

{{-- After a scan the provider decides the outcome. This polls our own record,
     which is only ever written from a verified provider confirmation. --}}
<div class="page-shell py-8 sm:py-10"
     x-data="{
        url: @js($statusUrl, JSON_UNESCAPED_SLASHES),
        state: @js([
            'status' => $payment->status->value,
            'label' => $payment->status->label(),
            'paid' => $isPaid,
            'failed' => $payment->status->isFinal(),
            'pending' => ! $isPaid && ! $payment->status->isFinal(),
            'message' => $payment->failure_reason,
        ]),
        timer: null,
        checks: 0,
        get done() { return this.state.paid || this.state.failed },
        check() {
            if (this.done) return
            fetch(this.url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                .then(r => r.ok ? r.json() : null)
                .then(data => { if (data) { this.state = data; this.checks++ } })
                .catch(() => {})
        },
        start() {
            if (this.done) return
            this.timer = setInterval(() => this.check(), 6000)
            this.check()
        },
        stop() { if (this.timer) clearInterval(this.timer); this.timer = null },
     }"
     x-init="start()" x-on:beforeunload="stop()" x-show="true">

    <nav class="mb-6 flex items-center gap-2 text-sm text-ink-500" aria-label="Breadcrumb">
        <a href="{{ route('customer.orders.index') }}" class="hover:text-brand-700">Pesanan</a>
        <x-icon name="chevron-right" class="h-3.5 w-3.5" />
        <a href="{{ route('customer.orders.show', $order) }}" class="hover:text-brand-700">{{ data_get($order, 'number') }}</a>
        <x-icon name="chevron-right" class="h-3.5 w-3.5" />
        <span class="font-medium text-ink-700">Pembayaran</span>
    </nav>

    <div class="grid items-start gap-8 lg:grid-cols-[1fr_360px]">
        <div class="space-y-6">
            <x-page-header
                title="Selesaikan pembayaran"
                :description="'Pesanan '.data_get($order, 'number').' menunggu pembayaran melalui '.$methodLabel.'.'"
                eyebrow="Pembayaran"
            />

            {{-- Live outcome banner: flips to success or failure once the provider confirms. --}}
            <div x-show="state.paid" x-cloak class="panel border-emerald-200 bg-emerald-50 p-6 sm:p-8">
                <div class="flex flex-col items-center gap-3 text-center">
                    <span class="grid h-14 w-14 place-items-center rounded-full bg-emerald-600 text-white">
                        <x-icon name="check" class="h-8 w-8" />
                    </span>
                    <h2 class="text-xl font-extrabold text-emerald-900" x-text="state.label"></h2>
                    <p class="max-w-md text-sm text-emerald-800">Status pembayaran dikonfirmasi oleh penyedia pembayaran. Pesanan Anda sudah diteruskan ke tim produksi.</p>
                    <a href="{{ route('customer.orders.show', $order) }}" class="action-link mt-2">Lihat detail pesanan <x-icon name="arrow-right" class="h-4 w-4" /></a>
                </div>
            </div>

            <div x-show="state.failed" x-cloak class="panel border-rose-200 bg-rose-50 p-6 sm:p-8">
                <div class="flex flex-col items-center gap-3 text-center">
                    <span class="grid h-14 w-14 place-items-center rounded-full bg-rose-600 text-white">
                        <x-icon name="x-circle" class="h-8 w-8" />
                    </span>
                    <h2 class="text-xl font-extrabold text-rose-900" x-text="state.label"></h2>
                    <p class="max-w-md text-sm text-rose-800" x-text="state.message || 'Pembayaran tidak berhasil. Silakan buat pesanan baru.'"></p>
                    <a href="{{ route('customer.orders.index') }}" class="action-link mt-2">Kembali ke daftar pesanan</a>
                </div>
            </div>

            @if (! $gatewayReady && ! $isPaid)
                <section class="panel p-6" aria-labelledby="unavailable-title">
                    <h2 id="unavailable-title" class="font-extrabold text-ink-950">Pembayaran online belum tersedia</h2>
                    <p class="mt-2 text-sm leading-6 text-ink-600">
                        Penyedia pembayaran belum dikonfigurasi pada lingkungan ini, sehingga transaksi belum dapat dibuat.
                        Pesanan Anda tetap tersimpan dan belum berstatus lunas.
                    </p>
                    <x-alert type="warning" class="mt-4">
                        Isi <code>PAYMENT_PROVIDER</code>, <code>PAYMENT_SERVER_KEY</code>, dan <code>PAYMENT_SANDBOX</code> pada file <code>.env</code>, lalu jalankan <code>php artisan optimize:clear</code>.
                    </x-alert>
                    <x-button :href="route('customer.orders.show', $order)" variant="secondary" class="mt-5">Kembali ke pesanan</x-button>
                </section>
            @else
                @unless ($isPaid)
                    <section class="panel p-6" aria-labelledby="instruction-title">
                        <h2 id="instruction-title" class="font-extrabold text-ink-950">
                            {{ $isQris ? 'Pindai QRIS' : 'Nomor virtual account' }}
                        </h2>

                        <div class="mt-6 flex flex-col items-center gap-6">
                            @if ($isQris)
                                @if ($qrImage)
                                    <img src="{{ $qrImage }}" alt="QRIS pembayaran pesanan {{ data_get($order, 'number') }}" class="h-64 w-64 rounded-xl border border-ink-200 bg-white object-contain p-2" />
                                @endif

                                @if ($isSandbox)
                                    <x-alert type="info" title="Mode sandbox" class="w-full">
                                        Pindai memakai <strong>Midtrans Payment Simulator</strong>, bukan aplikasi bank sungguhan.
                                        Salin tautan QR di bawah, buka simulator, tempel, lalu klik <em>Scan QR</em> dan pilih e-wallet.
                                    </x-alert>
                                    <div class="w-full">
                                        <label for="qr-url" class="text-xs font-semibold text-ink-500">Tautan gambar QRIS</label>
                                        <div class="mt-1 flex gap-2">
                                            <input id="qr-url" type="text" readonly value="{{ $qrImage }}" class="form-control font-mono text-xs" x-ref="qrUrl">
                                            <x-button type="button" size="sm" variant="secondary" x-on:click="navigator.clipboard.writeText($refs.qrUrl.value); $el.innerText = 'Tersalin'">Salin</x-button>
                                        </div>
                                        <a href="{{ $simulatorUrl }}" target="_blank" rel="noopener" class="action-link mt-3 inline-flex">
                                            <x-icon name="external" class="h-4 w-4" /> Buka Midtrans QRIS Simulator
                                        </a>
                                    </div>
                                @endif
                            @else
                                <div class="w-full rounded-xl border border-ink-200 bg-ink-50 p-5 text-center">
                                    <p class="text-xs font-semibold tracking-[0.14em] text-ink-500 uppercase">Nomor virtual account</p>
                                    <p class="mt-2 font-mono text-2xl font-extrabold tracking-wider text-ink-950">{{ data_get($payment, 'virtual_account_number', 'Belum tersedia') }}</p>
                                    <p class="mt-1 text-sm text-ink-600">{{ data_get($payment, 'virtual_account_bank') ?: config('printing.bank_name') }}</p>
                                </div>
                            @endif
                        </div>
                    </section>
                @endunless

                <section class="panel p-5 sm:p-6" aria-labelledby="status-title">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="status-title" class="font-extrabold text-ink-950">Status pembayaran</h2>
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500" x-show="state.pending">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent-400 opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-accent-500"></span>
                            </span>
                            <span x-text="state.pending ? 'Menunggu pembayaran' : 'Selesai'"></span>
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold text-ink-500">Nominal</dt>
                            <dd class="mt-1 text-lg font-extrabold text-brand-600"><x-money :value="data_get($payment, 'amount')" /></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-ink-500">Referensi transaksi</dt>
                            <dd class="mt-1 font-mono text-sm font-semibold break-all text-ink-800">{{ data_get($payment, 'provider_reference', 'Belum tersedia') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-ink-500">Status</dt>
                            <dd class="mt-1" x-text="state.label"></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-ink-500">Batas pembayaran</dt>
                            <dd class="mt-1 text-sm font-semibold text-ink-800">{{ data_get($payment, 'expires_at')?->format('d F Y, H:i') ?? '—' }}</dd>
                        </div>
                    </dl>

                    @if (data_get($payment, 'failure_reason'))
                        <x-alert type="warning" class="mt-4">{{ data_get($payment, 'failure_reason') }}</x-alert>
                    @endif

                    <form method="POST" action="{{ route('customer.orders.payment-refresh', $order) }}" class="mt-5 border-t border-ink-100 pt-5">
                        @csrf
                        <x-button type="submit" variant="secondary" x-show="state.pending"><x-icon name="refresh" class="h-4 w-4" /> Periksa sekarang</x-button>
                        <p class="mt-3 text-xs leading-5 text-ink-500">
                            Halaman ini otomatis memeriksa penyedia setiap beberapa detik setelah QR dipindai.
                            Tombol di atas hanya meminta server memeriksa lebih cepat; tidak ada jalur untuk mengubah status secara manual.
                        </p>
                    </form>
                </section>
            @endif
        </div>

        <aside class="panel overflow-hidden lg:sticky lg:top-24">
            <div class="panel-head">
                <h2 class="font-extrabold text-ink-950">Ringkasan pesanan</h2>
            </div>
            <div class="divide-y divide-ink-100">
                @foreach (data_get($order, 'items', []) as $item)
                    <div class="flex items-center justify-between gap-4 px-5 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-ink-800">{{ data_get($item, 'product_name') }}</p>
                            <p class="mt-0.5 text-xs text-ink-500">{{ data_get($item, 'quantity') }} item</p>
                        </div>
                        <span class="shrink-0 font-semibold text-ink-900"><x-money :value="data_get($item, 'line_total')" /></span>
                    </div>
                @endforeach
            </div>
            <div class="space-y-3 border-t border-ink-200 p-5 text-sm">
                <div class="flex justify-between text-ink-600"><span>Subtotal</span><span class="font-semibold text-ink-900"><x-money :value="data_get($order, 'subtotal')" /></span></div>
                <div class="flex justify-between text-ink-600"><span>Ongkos kirim</span><span class="font-semibold text-ink-900"><x-money :value="data_get($order, 'shipping_fee')" /></span></div>
                <div class="flex items-end justify-between gap-4 rounded-lg bg-accent-100 px-4 py-3">
                    <span class="text-xs font-extrabold tracking-[0.14em] text-accent-800 uppercase">Total</span>
                    <span class="text-xl font-black text-brand-600"><x-money :value="data_get($order, 'grand_total')" /></span>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection