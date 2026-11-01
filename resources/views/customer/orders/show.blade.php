@extends('layouts.app')

@section('title', 'Pesanan #'.data_get($order, 'number', data_get($order, 'id')))
@section('meta_description', 'Detail pesanan Kilat Print.')

@section('content')
@php
    $orderItems = $items ?? data_get($order, 'items', []);
    $orderItems = collect($orderItems);
    $timelineEvents = $timeline ?? data_get($order, 'statusHistories', data_get($order, 'timeline', []));
    $orderPayment = $payment ?? data_get($order, 'payment');
    $designFiles = $designFiles ?? data_get($order, 'designFiles', data_get($order, 'design', []));
    $designFiles = collect($designFiles instanceof \Illuminate\Support\Collection ? $designFiles : [$designFiles]);
    $orderDesign = $design ?? $designFiles->sortByDesc('created_at')->first();
    $paymentProof = data_get($orderPayment, 'proof_url');
    $designFile = data_get($orderDesign, 'file_url') ?? data_get($orderDesign, 'download_url') ?? (\Illuminate\Support\Facades\Route::has('customer.orders.design-download') && data_get($orderDesign, 'path') ? route('customer.orders.design-download', ['order' => $order, 'design' => $orderDesign]) : null);
    $shippingMethod = (string) data_get($order, 'shipping_method', '');
    $shippingMethodLabel = data_get($order, 'shipping_method_label') ?? match ($shippingMethod) {
        'pickup' => 'Ambil di lokasi (pesanan lama)',
        'delivery' => 'Kirim ke alamat',
        default => 'Belum ditentukan',
    };
    $addressRecipient = data_get($order, 'address.recipient', data_get($order, 'shipping_address.recipient_name', data_get($order, 'recipient_name')));
    $addressPhone = data_get($order, 'address.phone', data_get($order, 'shipping_address.phone', data_get($order, 'phone')));
    $addressStreet = data_get($order, 'address.address', data_get($order, 'shipping_address.address_line1', data_get($order, 'address_line1')));
    $addressDistrict = data_get($order, 'address.district', data_get($order, 'shipping_address.address_line2', data_get($order, 'address_line2')));
    $addressRegion = trim(collect([
        data_get($order, 'address.city', data_get($order, 'shipping_address.city', data_get($order, 'city'))),
        data_get($order, 'address.province', data_get($order, 'shipping_address.province', data_get($order, 'province'))),
    ])->filter()->implode(', ').' '.data_get($order, 'address.postal_code', data_get($order, 'shipping_address.postal_code', data_get($order, 'postal_code'))));
    $hasAddress = filled($addressRecipient) || filled($addressStreet) || filled($addressRegion);
@endphp

<div class="page-shell py-8 sm:py-10">
    <nav class="mb-6 flex items-center gap-2 text-sm text-ink-500" aria-label="Breadcrumb">
        <a href="{{ route('customer.orders.index') }}" class="breadcrumb-link">Pesanan</a>
        <x-icon name="chevron-right" class="h-3.5 w-3.5" />
        <span class="font-medium text-ink-700">#{{ data_get($order, 'number', data_get($order, 'id')) }}</span>
    </nav>

    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <p class="section-kicker">Detail pesanan</p>
                <x-status-badge :status="data_get($order, 'status')" />
            </div>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">#{{ data_get($order, 'number', data_get($order, 'code', data_get($order, 'id'))) }}</h1>
            <p class="mt-1 text-sm text-ink-500">Dibuat {{ data_get($order, 'created_at')?->translatedFormat('d F Y, H:i') ?? 'belum tersedia' }}</p>
        </div>
        <form method="POST" action="{{ route('customer.orders.repeat', $order) }}" x-data="confirmAction('Ulangi pesanan ini dengan data saat ini?')" x-on:submit="confirm">
            @csrf
            <x-button type="submit" variant="secondary"><x-icon name="refresh" class="h-4 w-4" /> Pesan ulang</x-button>
        </form>
    </div>

    <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_360px]">
        <div class="space-y-6">
            <section class="panel overflow-hidden" aria-labelledby="order-items-title">
                <div class="panel-head">
                    <h2 id="order-items-title" class="font-extrabold text-ink-950">Item pesanan</h2>
                </div>
                @if ($orderItems->isNotEmpty())
                    <div class="divide-y divide-ink-100">
                        @foreach ($orderItems as $item)
                            <div class="flex items-center gap-4 p-4 sm:p-5">
                                <div class="w-16 shrink-0 overflow-hidden rounded-lg border border-ink-200 sm:w-20">
                                    <x-product-image :src="data_get($item, 'image_url') ?? data_get($item, 'product.thumbnail_url') ?? data_get($item, 'product.image_url') ?? data_get($item, 'product.thumbnail') ?? data_get($item, 'product.image')" :alt="data_get($item, 'product_name', 'Produk')" fit="contain" class="aspect-square" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-ink-900">{{ data_get($item, 'product_name', data_get($item, 'product.name', 'Produk')) }}</p>
                                    @if (data_get($item, 'variant_label'))
                                        <p class="mt-1 text-xs font-semibold text-ink-600">{{ data_get($item, 'variant_label') }}</p>
                                    @endif
                                    <p class="mt-1 text-xs text-ink-500">{{ data_get($item, 'quantity', 1) }} item
                                        @if (data_get($item, 'unit_price') !== null)
                                            · <x-money :value="data_get($item, 'unit_price')" /> per item
                                        @endif
                                    </p>
                                    @if (data_get($item, 'specification'))<p class="mt-1 text-xs text-ink-500">{{ data_get($item, 'specification') }}</p>@endif
                                    @if(data_get($item, 'customDesignDraft'))<span class="mt-1.5 inline-flex"><x-badge color="brand">Desain editor v{{ data_get($item, 'customDesignDraft.version') }}</x-badge></span>@endif
                                </div>
                                <p class="shrink-0 text-sm font-bold text-ink-900"><x-money :value="data_get($item, 'line_total', data_get($item, 'subtotal', data_get($item, 'total')))" /></p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-empty-state compact title="Detail item tidak tersedia" icon="package" />
                @endif
                <div class="space-y-3 border-t border-ink-200 bg-ink-50/60 p-5 text-sm">
                    <div class="flex justify-between text-ink-600"><span>Subtotal</span><span class="font-semibold text-ink-900"><x-money :value="data_get($order, 'subtotal')" /></span></div>
                    <div class="flex justify-between text-ink-600"><span>Ongkos kirim</span><span class="font-semibold text-ink-900"><x-money :value="data_get($order, 'shipping_fee', data_get($order, 'shipping_cost'))" /></span></div>
                    <div class="flex items-end justify-between gap-4 rounded-lg bg-white px-4 py-3 ring-1 ring-ink-200">
                        <span class="text-xs font-extrabold tracking-[0.14em] text-brand-700 uppercase">Total</span>
                        <span class="text-xl font-black text-brand-700"><x-money :value="data_get($order, 'grand_total', data_get($order, 'total'))" /></span>
                    </div>
                </div>
            </section>

            <section class="panel p-5 sm:p-6" aria-labelledby="order-timeline-title">
                <h2 id="order-timeline-title" class="font-extrabold text-ink-950">Riwayat pesanan</h2>
                @if (collect($timelineEvents)->isNotEmpty())
                    <ol class="mt-5 space-y-0">
                        @foreach ($timelineEvents as $eventIndex => $event)
                            @php
                                $eventActive = data_get($event, 'active', data_get($event, 'completed', true));
                                $eventDate = data_get($event, 'timestamp', data_get($event, 'created_at'));
                                /* The latest reached step is the "current" step: red with white icon. */
                                $eventIsCurrent = $eventActive && $eventIndex === collect($timelineEvents)->search(fn ($item) => data_get($item, 'active', data_get($item, 'completed', true)));
                            @endphp
                            <li class="relative flex gap-4 pb-6 last:pb-0">
                                @if (!$loop->last)<span class="absolute left-[11px] top-6 h-[calc(100%-1.5rem)] w-px bg-ink-200"></span>@endif
                                <span @class([
                                    'relative z-10 mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full',
                                    'bg-brand-600 text-white ring-4 ring-brand-100' => $eventIsCurrent,
                                    'bg-emerald-500 text-white' => $eventActive && ! $eventIsCurrent,
                                    'border-2 border-ink-300 bg-white text-ink-500' => ! $eventActive,
                                ])>
                                    @if($eventActive)<x-icon name="check" class="h-3.5 w-3.5" />@else<span class="h-1.5 w-1.5 rounded-full bg-current"></span>@endif
                                </span>
                                <div class="min-w-0">
                                    <p @class(['text-sm font-bold', $eventIsCurrent ? 'text-brand-700' : 'text-ink-800'])>{{ data_get($event, 'title', data_get($event, 'status_label', data_get($event, 'status', 'Pembaruan pesanan'))) }}</p>
                                    @if (data_get($event, 'description'))<p class="mt-1 text-sm leading-6 text-ink-500">{{ data_get($event, 'description') }}</p>@endif
                                    @if (is_object($eventDate) && method_exists($eventDate, 'format'))<p class="mt-1 text-xs text-ink-500">{{ method_exists($eventDate, 'translatedFormat') ? $eventDate->translatedFormat('d M Y, H:i') : $eventDate->format('d M Y, H:i') }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <x-empty-state compact title="Riwayat belum tersedia" description="Pembaruan status akan tampil di sini." icon="clock" />
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="panel p-5" aria-labelledby="order-shipping-title">
                <h2 id="order-shipping-title" class="font-extrabold text-ink-950">Alamat pesanan</h2>
                <div class="mt-4 rounded-lg bg-ink-50 px-3 py-2.5 text-sm">
                    <p class="text-xs text-ink-500">Metode pengiriman</p>
                    <p class="mt-0.5 font-bold text-ink-900">{{ $shippingMethodLabel }}</p>
                </div>
                @if ($hasAddress)
                    <address class="mt-4 text-sm not-italic leading-6 text-ink-600">
                        @if ($addressRecipient)<strong class="block text-ink-900">{{ $addressRecipient }}</strong>@endif
                        @if ($addressPhone){{ $addressPhone }}<br>@endif
                        @if ($addressStreet){{ $addressStreet }}<br>@endif
                        @if ($addressDistrict){{ $addressDistrict }}<br>@endif
                        @if ($addressRegion){{ $addressRegion }}@endif
                    </address>
                @else
                    <p class="mt-4 text-sm text-ink-500">Alamat belum tersedia.</p>
                @endif
                @if ($shippingMethod === 'pickup')
                    <p class="mt-3 flex items-start gap-2 rounded-lg bg-accent-50 px-3 py-2 text-xs leading-5 text-accent-900 ring-1 ring-inset ring-accent-200">
                        <x-icon name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-accent-600" />
                        Pesanan lama ini menggunakan metode ambil di lokasi. Metode tersebut tidak lagi tersedia untuk pesanan baru; alamat tetap disimpan sebagai data pesanan.
                    </p>
                @endif
            </section>

            <section class="panel p-5" aria-labelledby="order-payment-title">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="order-payment-title" class="font-extrabold text-ink-950">Pembayaran</h2>
                    <x-status-badge :status="data_get($orderPayment, 'status', data_get($order, 'payment_status'))" />
                </div>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold text-ink-500">Metode</dt>
                        <dd class="mt-0.5 font-semibold text-ink-800">{{ $orderPayment?->paymentMethod()?->label() ?? data_get($orderPayment, 'method', 'Belum tersedia') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-ink-500">Nominal</dt>
                        <dd class="mt-0.5 font-semibold text-ink-800"><x-money :value="data_get($orderPayment, 'amount', data_get($order, 'grand_total'))" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-ink-500">Referensi transaksi</dt>
                        <dd class="mt-0.5 font-mono text-xs break-all text-ink-700">{{ data_get($orderPayment, 'provider_reference', 'Belum dibuat') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-ink-500">Waktu pembayaran</dt>
                        <dd class="mt-0.5 text-ink-700">{{ data_get($orderPayment, 'paid_at')?->translatedFormat('d F Y, H:i') ?? 'Belum dikonfirmasi' }}</dd>
                    </div>
                </dl>

                @if ($orderPayment?->status === \App\Enums\PaymentStatus::Paid)
                    <p class="mt-4 flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800 ring-1 ring-inset ring-emerald-200">
                        <x-icon name="check" class="h-4 w-4" /> Pembayaran Berhasil
                    </p>
                @elseif ($order->status === \App\Enums\OrderStatus::PendingPayment && $orderPayment?->isAwaitingPayment())
                    <x-button :href="route('customer.orders.payment', $order)" class="mt-4 w-full">Bayar sekarang <x-icon name="arrow-right" class="h-4 w-4" /></x-button>
                @endif

                @if ($paymentProof)
                    <a href="{{ $paymentProof }}" target="_blank" rel="noopener" class="action-link mt-4">Lihat bukti pembayaran tersimpan <x-icon name="external" class="h-4 w-4" /></a>
                @endif
            </section>

            <section class="panel p-5" aria-labelledby="order-design-title">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="order-design-title" class="font-extrabold text-ink-950">Desain</h2>
                    @if($orderDesign)<x-status-badge :status="data_get($orderDesign, 'status')" />@endif
                </div>
                @if ($designFile)
                    <a href="{{ $designFile }}" target="_blank" rel="noopener" class="action-link mt-4">Lihat file desain <x-icon name="external" class="h-4 w-4" /></a>
                @endif
                @if (data_get($orderDesign, 'review_note'))
                    <x-alert type="warning" class="mt-4">Catatan revisi: {{ data_get($orderDesign, 'review_note') }}</x-alert>
                @endif
                <form method="POST" action="{{ route('customer.orders.design', $order) }}" enctype="multipart/form-data" class="mt-5 space-y-3 border-t border-ink-100 pt-5" aria-label="Unggah desain">
                    @csrf
                    <p class="text-xs leading-5 text-ink-500">Unggah file JPG, PNG, atau PDF. Jika tidak memilih item, file dianggap berlaku untuk seluruh pesanan.</p>
                    <x-select name="order_item_id" label="Item pesanan (opsional)" placeholder="Seluruh pesanan">
                        @foreach ($orderItems as $item)
                            <option value="{{ data_get($item, 'id') }}">{{ data_get($item, 'product_name', data_get($item, 'product.name', 'Produk')) }}</option>
                        @endforeach
                    </x-select>
                    <x-input name="design" label="File desain" type="file" accept=".jpg,.jpeg,.png,.pdf" required help="Maksimal 10 MB." />
                    <x-textarea name="notes" label="Catatan desain (opsional)" :rows="2" placeholder="Keterangan atau instruksi desain" />
                    <x-button type="submit" class="w-full" variant="secondary"><x-icon name="upload" class="h-4 w-4" /> Kirim desain</x-button>
                </form>
            </section>
        </aside>
    </div>
</div>
@endsection
