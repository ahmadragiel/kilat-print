@extends('layouts.admin')

@php
    $orderItems = $items ?? data_get($order, 'items', []);
    $orderItems = collect($orderItems);
    $orderPayment = $payment ?? data_get($order, 'payment');
    $designFiles = $designFiles ?? data_get($order, 'designFiles', data_get($order, 'design', []));
    $designFiles = collect($designFiles instanceof \Illuminate\Support\Collection ? $designFiles : [$designFiles]);
    $orderDesign = $design ?? $designFiles->sortByDesc('created_at')->first();
    $production = $production ?? data_get($order, 'production');
    $statusOptions = $statuses ?? (class_exists(\App\Enums\OrderStatus::class) ? \App\Enums\OrderStatus::cases() : []);
    $currentOrderStatus = data_get($order, 'status');
    $currentOrderStatusValue = $currentOrderStatus instanceof \BackedEnum ? $currentOrderStatus->value : $currentOrderStatus;
    $statusUrl = \Illuminate\Support\Facades\Route::has('orders.status') ? route('orders.status', $order) : route('admin.orders.status', $order);
    $paymentVerifyUrl = \Illuminate\Support\Facades\Route::has('payment.verify')
        ? route('payment.verify', $orderPayment)
        : route('admin.payments.verify', $order);
    $paymentRejectUrl = \Illuminate\Support\Facades\Route::has('payment.reject')
        ? route('payment.reject', $orderPayment)
        : route('admin.payments.reject', $order);
    $paymentProofUrl = data_get($orderPayment, 'proof_url') ?? (\Illuminate\Support\Facades\Route::has('admin.payments.proof') && data_get($orderPayment, 'proof_path') ? route('admin.payments.proof', $order) : null);
    $designApproveUrl = $orderDesign ? (\Illuminate\Support\Facades\Route::has('design.approve') ? route('design.approve', $orderDesign) : route('admin.designs.approve', $orderDesign)) : null;
    $designRevisionUrl = $orderDesign ? (\Illuminate\Support\Facades\Route::has('design.requestRevision') ? route('design.requestRevision', $orderDesign) : route('admin.designs.revision', $orderDesign)) : null;
    $designDownloadUrl = $orderDesign ? (\Illuminate\Support\Facades\Route::has('design.download') && data_get($orderDesign, 'path') ? route('design.download', $orderDesign) : (\Illuminate\Support\Facades\Route::has('admin.designs.download') && data_get($orderDesign, 'path') ? route('admin.designs.download', $orderDesign) : data_get($orderDesign, 'file_url'))) : null;
    $productionAssignUrl = $production ? (\Illuminate\Support\Facades\Route::has('production.assign') ? route('production.assign', $production) : route('admin.production.assign', $production)) : (\Illuminate\Support\Facades\Route::has('production.assign') ? route('production.assign', $order) : null);
@endphp

@section('title', 'Pesanan #'.data_get($order, 'number', data_get($order, 'order_number', data_get($order, 'id'))))

@section('content')
<div class="space-y-7">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2"><p class="section-kicker">Detail pesanan</p><x-status-badge :status="data_get($order, 'status')" /></div>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">#{{ data_get($order, 'number', data_get($order, 'order_number', data_get($order, 'id'))) }}</h1>
            <p class="mt-1 text-sm text-ink-500">Dibuat {{ data_get($order, 'created_at')?->format('d F Y, H:i') ?? 'belum tersedia' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-button :href="\Illuminate\Support\Facades\Route::has('admin.invoice') ? route('admin.invoice', $order) : route('admin.invoices.show', $order)" variant="secondary"><x-icon name="receipt" class="h-4 w-4" /> Invoice</x-button>
            <x-button :href="route('admin.orders.index')" variant="ghost">Kembali</x-button>
        </div>
    </div>

    <div class="grid items-start gap-7 xl:grid-cols-[1fr_350px]">
        <div class="space-y-7">
            <section class="panel overflow-hidden">
                <div class="panel-head"><h2 class="font-extrabold text-ink-950">Item pesanan</h2></div>
                @if($orderItems->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Spesifikasi</th>
                                    <th>Jumlah</th>
                                    <th class="text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orderItems as $item)
                                    <tr>
                                        <td class="font-semibold text-ink-950">
                                            {{ data_get($item, 'product_name', data_get($item, 'product.name', 'Produk')) }}
                                        </td>
                                        <td>
                                            {{ data_get($item, 'specification', data_get($item, 'material.name', data_get($item, 'size', 'Tidak ada'))) }}
                                            @if (data_get($item, 'customDesignDraft'))
                                                @php
                                                    $draftElements = collect(data_get($item, 'customDesignDraft.design', []))
                                                        ->flatMap(fn ($side) => data_get($side, 'elements', []) ?? [])
                                                        ->count();
                                                @endphp
                                                <span class="mt-1.5 block">
                                                    <x-badge color="brand">Editor v{{ data_get($item, 'customDesignDraft.version') }} &middot; {{ $draftElements }} elemen</x-badge>
                                                </span>
                                            @endif
                                        </td>
                                        <td>{{ data_get($item, 'quantity', 1) }}</td>
                                        <td class="text-right font-extrabold text-brand-600">
                                            <x-money :value="data_get($item, 'line_total', data_get($item, 'subtotal', data_get($item, 'total')))" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else<x-empty-state compact title="Item belum tersedia" icon="package" />@endif
                <div class="space-y-3 border-t border-ink-200 bg-ink-50/60 p-5 text-sm">
                    <div class="flex justify-between text-ink-600"><span>Subtotal</span><span class="font-semibold text-ink-900"><x-money :value="data_get($order, 'subtotal')" /></span></div>
                    <div class="flex justify-between text-ink-600"><span>Ongkos kirim</span><span class="font-semibold text-ink-900"><x-money :value="data_get($order, 'shipping_fee', data_get($order, 'shipping_cost'))" /></span></div>
                    <div class="flex items-end justify-between gap-4 rounded-lg bg-white px-4 py-3 ring-1 ring-ink-200">
                        <span class="text-2xs font-extrabold tracking-[0.14em] text-brand-600 uppercase">Total</span>
                        <span class="text-xl font-black text-brand-600"><x-money :value="data_get($order, 'grand_total', data_get($order, 'total'))" /></span>
                    </div>
                </div>
            </section>

            <section class="panel p-5 sm:p-6">
                <h2 class="font-extrabold text-ink-950">Alamat pengiriman</h2>
                <address class="mt-4 text-sm not-italic leading-6 text-ink-600"><strong class="block text-ink-950">{{ data_get($order, 'address.recipient', data_get($order, 'shipping_address.recipient_name', 'Belum tersedia')) }}</strong>{{ data_get($order, 'address.phone', data_get($order, 'phone')) }}<br>{{ data_get($order, 'address.address', data_get($order, 'shipping_address.address_line1')) }}<br>@if(data_get($order, 'address.district')){{ data_get($order, 'address.district') }}<br>@endif{{ data_get($order, 'address.city', data_get($order, 'city')) }}, {{ data_get($order, 'address.province', data_get($order, 'province')) }} {{ data_get($order, 'address.postal_code', data_get($order, 'postal_code')) }}</address>
            </section>

            <section class="panel p-5 sm:p-6">
                <h2 class="font-extrabold text-ink-950">Catatan pesanan</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-ink-600">{{ data_get($order, 'internal_notes', data_get($order, 'notes', 'Tidak ada catatan.')) }}</p>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="panel p-5">
                <h2 class="font-extrabold text-ink-950">Ubah status</h2>
                <form method="POST" action="{{ $statusUrl }}" class="mt-4 space-y-3">
                    @csrf
                    @if(\Illuminate\Support\Facades\Route::has('orders.status')) @method('PATCH') @endif
                    <x-select name="status" label="Status baru" :value="data_get($order, 'status')" required>
                        @forelse($statusOptions as $status)
                            @php $statusValue = $status instanceof \BackedEnum ? $status->value : (is_array($status) ? data_get($status, 'value', data_get($status, 'key')) : $status); $statusText = $status instanceof \UnitEnum ? $status->name : (is_array($status) ? data_get($status, 'label', data_get($status, 'name')) : $status); @endphp
                            <option value="{{ $statusValue }}" @selected((string) $currentOrderStatusValue === (string) $statusValue)>{{ $statusText }}</option>
                        @empty
                            <option value="{{ $currentOrderStatusValue }}" selected>{{ $currentOrderStatusValue }}</option>
                        @endforelse
                    </x-select>
                    <x-textarea name="note" label="Catatan perubahan" :rows="2" />
                    <x-button type="submit" class="w-full">Perbarui status</x-button>
                </form>
            </section>

            <section class="panel p-5">
                <div class="flex items-center justify-between gap-3"><h2 class="font-extrabold text-ink-950">Pembayaran</h2><x-status-badge :status="data_get($orderPayment, 'status')" /></div>
                @if($orderPayment)
                    <p class="mt-3 text-sm text-ink-500">{{ data_get($orderPayment, 'method', 'Metode belum tersedia') }}</p>
                    @if($paymentProofUrl)<a href="{{ $paymentProofUrl }}" target="_blank" rel="noopener" class="action-link mt-3">Bukti pembayaran <x-icon name="external" class="h-4 w-4" /></a>@endif
                    <div class="mt-4 grid grid-cols-2 gap-2 border-t border-ink-100 pt-4"><form method="POST" action="{{ $paymentVerifyUrl }}">@csrf @if(\Illuminate\Support\Facades\Route::has('payment.verify')) @method('PATCH') @endif<x-button type="submit" variant="success" size="sm" class="w-full">Verifikasi</x-button></form><form method="POST" action="{{ $paymentRejectUrl }}">@csrf @if(\Illuminate\Support\Facades\Route::has('payment.reject')) @method('PATCH') @endif<input type="hidden" name="reason" value="Bukti pembayaran tidak dapat diverifikasi."><x-button type="submit" variant="danger" size="sm" class="w-full">Tolak</x-button></form></div>
                @else<x-empty-state compact title="Data pembayaran belum tersedia" icon="credit-card" />@endif
            </section>

            <section class="panel p-5">
                <div class="flex items-center justify-between gap-3"><h2 class="font-extrabold text-ink-950">Desain</h2><x-status-badge :status="data_get($orderDesign, 'status')" /></div>
                @if($orderDesign)
                    @if($designDownloadUrl)<a href="{{ $designDownloadUrl }}" target="_blank" rel="noopener" class="action-link mt-3">Buka desain <x-icon name="external" class="h-4 w-4" /></a>@else<p class="mt-3 text-sm text-ink-500">{{ data_get($orderDesign, 'original_filename', 'File desain tersimpan') }}</p>@endif
                    <div class="mt-4 grid grid-cols-2 gap-2 border-t border-ink-100 pt-4"><form method="POST" action="{{ $designApproveUrl }}">@csrf @if(\Illuminate\Support\Facades\Route::has('design.approve')) @method('PATCH') @endif<x-button type="submit" variant="success" size="sm" class="w-full">Setujui</x-button></form><form method="POST" action="{{ $designRevisionUrl }}">@csrf @if(\Illuminate\Support\Facades\Route::has('design.requestRevision')) @method('PATCH') @endif<input type="hidden" name="reason" value="Mohon periksa kembali desain sebelum produksi."><x-button type="submit" variant="secondary" size="sm" class="w-full">Minta revisi</x-button></form></div>
                @else<x-empty-state compact title="Desain belum diunggah" icon="palette" />@endif
            </section>

            @if($productionAssignUrl)
                <section class="panel p-5">
                    <h2 class="font-extrabold text-ink-950">Tugas produksi</h2>
                    <form method="POST" action="{{ $productionAssignUrl }}" class="mt-4 space-y-3">
                        @csrf
                        @if(\Illuminate\Support\Facades\Route::has('production.assign')) @method('PATCH') @endif
                        <x-select name="operator_id" label="Operator" :value="data_get($production, 'operator_id')" placeholder="Pilih operator" required>
                            @foreach(($operators ?? []) as $operator)<option value="{{ data_get($operator, 'id') }}">{{ data_get($operator, 'user.name', data_get($operator, 'name', 'Operator')) }}</option>@endforeach
                        </x-select>
                        <x-input name="deadline" label="Deadline" type="date" :value="data_get($production, 'deadline')" />
                        <x-button type="submit" variant="secondary" class="w-full"><x-icon name="user-cog" class="h-4 w-4" /> Tugaskan operator</x-button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
