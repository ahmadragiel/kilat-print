@props([
    'status' => null,
])

@php
    $key = strtolower(str_replace([' ', '-'], '_', (string) ($status instanceof \BackedEnum ? $status->value : ($status instanceof \UnitEnum ? $status->name : $status))));
    /* Semantic status palette — deliberately independent from the brand red so
       meaning is never lost: yellow = pending/attention, green = done/ok,
       blue = in progress/info, red = failure, purple = review, gray = neutral. */
    $statusMap = [
        'pending' => ['Menunggu', 'accent'],
        'waiting' => ['Menunggu', 'accent'],
        'waiting_payment' => ['Menunggu pembayaran', 'accent'],
        'unpaid' => ['Belum dibayar', 'accent'],
        'pending_payment' => ['Menunggu pembayaran', 'accent'],
        'expired' => ['Kedaluwarsa', 'accent'],
        'payment_confirmed' => ['Pembayaran dikonfirmasi', 'green'],
        'revision_required' => ['Revisi diminta', 'accent'],
        'paid' => ['Pembayaran berhasil', 'green'],
        'verified' => ['Terverifikasi', 'green'],
        'processing' => ['Diproses', 'blue'],
        'in_production' => ['Sedang diproduksi', 'brand'],
        'in_design' => ['Tahap desain', 'brand'],
        'printing' => ['Tahap cetak', 'brand'],
        'finishing' => ['Finishing', 'brand'],
        'packing' => ['Packing', 'brand'],
        'quality_control' => ['Kontrol kualitas', 'purple'],
        'awaiting_customer_approval' => ['Menunggu persetujuan pelanggan', 'purple'],
        'rework' => ['Perbaikan', 'accent'],
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
        'revision_requested' => ['Revisi diminta', 'accent'],
        'approved' => ['Disetujui', 'green'],
        'draft' => ['Draf', 'gray'],
        'active' => ['Aktif', 'green'],
        'inactive' => ['Nonaktif', 'gray'],
    ];
    [$label, $color] = $statusMap[$key] ?? [ucwords(str_replace('_', ' ', $key ?: 'Tidak diketahui')), 'gray'];
@endphp

<x-badge :color="$color" {{ $attributes }}>
    {{ $label }}
</x-badge>
