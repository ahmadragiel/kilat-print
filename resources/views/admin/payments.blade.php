@extends('layouts.admin')

@section('title', 'Pembayaran')

@section('content')
@php $paymentItems = $payments ?? $orders ?? []; @endphp
<div class="space-y-7">
    <x-page-header title="Verifikasi pembayaran" description="Tinjau bukti pembayaran pelanggan sebelum diteruskan ke produksi." eyebrow="Operasional" />

    <div class="panel overflow-hidden">
        @if (collect($paymentItems)->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Metode</th><th>Nominal</th><th>Bukti</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach($paymentItems as $paymentItem)
                            @php
                                $paymentOrder = data_get($paymentItem, 'order', $paymentItem);
                                $payment = data_get($paymentItem, 'payment', $paymentItem);
                                $verifyUrl = \Illuminate\Support\Facades\Route::has('payment.verify') ? route('payment.verify', $payment) : route('admin.payments.verify', $paymentOrder);
                                $rejectUrl = \Illuminate\Support\Facades\Route::has('payment.reject') ? route('payment.reject', $payment) : route('admin.payments.reject', $paymentOrder);
                                $proofUrl = data_get($payment, 'proof_url') ?? (data_get($payment, 'proof_path') && \Illuminate\Support\Facades\Route::has('admin.payments.proof') ? route('admin.payments.proof', $paymentOrder) : null);
                            @endphp
                            <tr>
                                <td class="font-semibold text-ink-900">#{{ data_get($paymentOrder, 'number', data_get($paymentOrder, 'order_number', data_get($paymentOrder, 'id'))) }}</td>
                                <td>{{ data_get($paymentOrder, 'customer.user.name', data_get($paymentOrder, 'customer.name', data_get($paymentOrder, 'customer_name', 'Pelanggan'))) }}</td>
                                <td>{{ data_get($payment, 'method', 'Belum tersedia') }}</td>
                                <td class="font-semibold"><x-money :value="data_get($payment, 'amount', data_get($payment, 'total'))" /></td>
                                <td>@if($proofUrl)<a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="action-link">Lihat</a>@else<span class="text-xs text-ink-400">Tidak ada</span>@endif</td>
                                <td><x-status-badge :status="data_get($payment, 'status')" /></td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ $verifyUrl }}">@csrf @if(\Illuminate\Support\Facades\Route::has('payment.verify')) @method('PATCH') @endif<x-button type="submit" size="icon" variant="success" aria-label="Verifikasi pembayaran"><x-icon name="check" class="h-4 w-4" /></x-button></form>
                                        <form method="POST" action="{{ $rejectUrl }}">@csrf @if(\Illuminate\Support\Facades\Route::has('payment.reject')) @method('PATCH') @endif<input type="hidden" name="reason" value="Bukti pembayaran tidak dapat diverifikasi."><x-button type="submit" size="icon" variant="danger" aria-label="Tolak pembayaran"><x-icon name="x" class="h-4 w-4" /></x-button></form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($paymentItems, 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $paymentItems->links() }}</div>@endif
        @else
            <x-empty-state title="Belum ada pembayaran menunggu" description="Bukti pembayaran yang dikirim pelanggan akan tampil di sini." icon="credit-card" />
        @endif
    </div>
</div>
@endsection
