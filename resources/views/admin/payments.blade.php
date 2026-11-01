@extends('layouts.admin')

@section('title', 'Pembayaran')

@section('content')
@php $paymentItems = $payments ?? $orders ?? []; @endphp
<div class="space-y-7">
    <x-page-header
        title="Pembayaran"
        description="Rekap transaksi pembayaran pelanggan. Status diperbarui otomatis oleh penyedia pembayaran, admin tidak melakukan verifikasi manual."
        eyebrow="Operasional"
    />

    <form method="GET" action="{{ route('admin.payments.index') }}" class="panel grid gap-3 p-4 sm:grid-cols-[220px_auto]" aria-label="Filter pembayaran">
        <div>
            <label for="payment-status" class="sr-only">Status pembayaran</label>
            <select id="payment-status" name="status" class="form-control">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <x-button type="submit"><x-icon name="filter" class="h-4 w-4" /> Filter</x-button>
    </form>

    <div class="panel overflow-hidden">
        @if (collect($paymentItems)->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Metode</th>
                            <th>Nominal</th>
                            <th>Referensi</th>
                            <th>Waktu pembayaran</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($paymentItems as $paymentItem)
                            @php
                                $paymentOrder = data_get($paymentItem, 'order', $paymentItem);
                                $payment = data_get($paymentItem, 'payment', $paymentItem);
                                $proofUrl = data_get($payment, 'proof_path') && \Illuminate\Support\Facades\Route::has('admin.payments.proof')
                                    ? route('admin.payments.proof', $paymentOrder)
                                    : null;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.orders.show', $paymentOrder) }}" class="font-semibold text-ink-950 hover:text-brand-700">#{{ data_get($paymentOrder, 'number', data_get($paymentOrder, 'id')) }}</a>
                                    {{-- Legacy proofs stay reachable so old transactions remain auditable. --}}
                                    @if ($proofUrl)<a href="{{ $proofUrl }}" class="action-link mt-1 block text-xs">Bukti tersimpan</a>@endif
                                </td>
                                <td>{{ data_get($paymentOrder, 'customer.user.name', data_get($paymentOrder, 'customer_name', 'Pelanggan')) }}</td>
                                <td>{{ $payment->paymentMethod()?->label() ?? data_get($payment, 'method', 'Belum tersedia') }}</td>
                                <td class="font-extrabold text-brand-700"><x-money :value="data_get($payment, 'amount')" /></td>
                                <td class="font-mono text-xs break-all text-ink-600">{{ data_get($payment, 'provider_reference', 'Belum dibuat') }}</td>
                                <td class="whitespace-nowrap text-ink-600">{{ data_get($payment, 'paid_at')?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                                <td><x-status-badge :status="data_get($payment, 'status')" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (method_exists($paymentItems, 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $paymentItems->links() }}</div>@endif
        @else
            <x-empty-state title="Belum ada pembayaran" description="Transaksi pembayaran pelanggan akan tampil di sini." icon="credit-card" />
        @endif
    </div>
</div>
@endsection