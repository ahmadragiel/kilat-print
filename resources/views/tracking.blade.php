@extends('layouts.app')

@section('title', 'Lacak Pesanan')

@section('content')
<section class="page-shell py-10">
    <x-page-header title="Lacak Pesanan" description="Masukkan nomor pesanan untuk melihat progres produksi." eyebrow="Tracking" />

    <form method="GET" action="{{ route('tracking.index') }}" class="panel mt-6 flex flex-col gap-3 p-5 sm:flex-row sm:items-end" aria-label="Lacak pesanan">
        <div class="flex-1">
            <x-input name="number" label="Nomor ID Pesanan" :value="$number ?? ''" placeholder="Contoh: KP-20260101-0001" required />
        </div>
        <x-button type="submit">Lacak Pesanan</x-button>
    </form>

    @if ($number !== '' && ! $order)
        <x-alert type="warning" class="mt-6">Pesanan dengan nomor {{ $number }} tidak ditemukan.</x-alert>
    @endif

    @if ($order)
        @php
            $currentStatus = data_get($order->production, 'status');
            $currentIndex = -1;
            foreach ($steps as $i => $step) {
                if ($step === $currentStatus || (is_object($currentStatus) && $step->value === $currentStatus->value)) {
                    $currentIndex = $i;
                }
            }
            if ($currentStatus === null) {
                $currentIndex = -1;
            } elseif ($order->status === \App\Enums\OrderStatus::Completed) {
                $currentIndex = count($steps) - 1;
            }
        @endphp
        <div class="panel mt-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-extrabold text-ink-950">Pesanan #{{ $order->number }}</h2>
                <x-status-badge :status="$order->production?->status ?? $order->status" />
            </div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                <div><dt class="text-xs font-semibold text-ink-500">Produk</dt><dd class="mt-1 font-semibold text-ink-800">{{ $order->items->first()?->product_name ?? '-' }}</dd></div>
                <div><dt class="text-xs font-semibold text-ink-500">Jumlah</dt><dd class="mt-1 font-semibold text-ink-800">{{ $order->items->first()?->quantity ?? '-' }}</dd></div>
                <div><dt class="text-xs font-semibold text-ink-500">Tanggal</dt><dd class="mt-1 font-semibold text-ink-800">{{ $order->created_at?->format('d M Y') }}</dd></div>
            </dl>
            <ol class="mt-6 space-y-3">
                @foreach ($steps as $i => $step)
                    <li class="flex items-center gap-3">
                        @if ($i <= $currentIndex)
                            <span class="text-emerald-600">✓</span>
                        @else
                            <span class="text-ink-300">○</span>
                        @endif
                        <span class="{{ $i <= $currentIndex ? 'font-semibold text-ink-900' : 'text-ink-400' }}">{{ $step->label() }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</section>
@endsection
