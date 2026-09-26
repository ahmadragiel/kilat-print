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
        'waiting_verification' => ['Menunggu verifikasi', 'accent'],
        'payment_review' => ['Verifikasi pembayaran', 'accent'],
        'payment_confirmed' => ['Pembayaran dikonfirmasi', 'green'],
        'design_review' => ['Review desain', 'purple'],
        'design_revision' => ['Revisi desain', 'red'],
        'design_approved' => ['Desain disetujui', 'green'],
        'waiting_production' => ['Menunggu produksi', 'accent'],
        'revision_required' => ['Revisi diminta', 'accent'],
        'paid' => ['Sudah dibayar', 'blue'],
        'verified' => ['Terverifikasi', 'green'],
        'processing' => ['Diproses', 'blue'],
        'in_production' => ['Dalam produksi', 'brand'],
        'finishing' => ['Finishing', 'brand'],
        'quality_check' => ['Pemeriksaan', 'purple'],
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
    <span class="mr-1 inline-block h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
    {{ $label }}
</x-badge>
