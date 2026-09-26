@props([
    'status' => null,
])

@php
    $key = strtolower(str_replace([' ', '-'], '_', (string) ($status instanceof \BackedEnum ? $status->value : ($status instanceof \UnitEnum ? $status->name : $status))));
    $statusMap = [
        'pending' => ['Menunggu', 'orange'],
        'waiting' => ['Menunggu', 'orange'],
        'waiting_payment' => ['Menunggu pembayaran', 'orange'],
        'unpaid' => ['Belum dibayar', 'orange'],
        'waiting_verification' => ['Menunggu verifikasi', 'orange'],
        'payment_review' => ['Verifikasi pembayaran', 'orange'],
        'payment_confirmed' => ['Pembayaran dikonfirmasi', 'green'],
        'design_review' => ['Review desain', 'purple'],
        'design_revision' => ['Revisi desain', 'red'],
        'design_approved' => ['Desain disetujui', 'green'],
        'waiting_production' => ['Menunggu produksi', 'orange'],
        'revision_required' => ['Revisi diminta', 'orange'],
        'paid' => ['Sudah dibayar', 'blue'],
        'verified' => ['Terverifikasi', 'green'],
        'processing' => ['Diproses', 'blue'],
        'in_production' => ['Dalam produksi', 'purple'],
        'finishing' => ['Finishing', 'purple'],
        'quality_check' => ['Pemeriksaan', 'purple'],
        'rework' => ['Perbaikan', 'orange'],
        'pass' => ['Lulus', 'green'],
        'fail' => ['Gagal', 'red'],
        'ready' => ['Siap kirim', 'green'],
        'shipped' => ['Dikirim', 'blue'],
        'delivered' => ['Selesai', 'green'],
        'completed' => ['Selesai', 'green'],
        'cancelled' => ['Dibatalkan', 'red'],
        'canceled' => ['Dibatalkan', 'red'],
        'rejected' => ['Ditolak', 'red'],
        'failed' => ['Gagal', 'red'],
        'revision_requested' => ['Revisi diminta', 'orange'],
        'approved' => ['Disetujui', 'green'],
        'draft' => ['Draf', 'gray'],
        'active' => ['Aktif', 'green'],
        'inactive' => ['Nonaktif', 'gray'],
    ];
    [$label, $color] = $statusMap[$key] ?? [ucwords(str_replace('_', ' ', $key ?: 'Tidak diketahui')), 'gray'];
@endphp

<x-badge :color="$color" {{ $attributes }}>
    <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
    {{ $label }}
</x-badge>
