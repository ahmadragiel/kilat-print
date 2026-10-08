@extends('layouts.operator')

@php
    $jobOrder = $order ?? data_get($job, 'order');
    $jobItems = $items ?? data_get($jobOrder, 'items', []);
    $jobTimeline = $timeline ?? data_get($job, 'statusHistories', []);
    $designFiles = $designFiles ?? data_get($jobOrder, 'designFiles', []);
    $statusUrl = route('operator.jobs.status', $job);
    $qualityUrl = route('operator.jobs.quality-check', $job);
    $noteUrl = route('operator.jobs.note', $job);
    $photoUrl = route('operator.jobs.photo', $job);
@endphp

@section('title', 'Pekerjaan #'.data_get($job, 'order.number', data_get($job, 'order_number', data_get($job, 'id'))))

@section('content')
<div class="space-y-7">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div><div class="flex flex-wrap items-center gap-2"><p class="section-kicker">Detail pekerjaan</p><x-status-badge :status="data_get($job, 'status')" /></div><h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">#{{ data_get($job, 'order.number', data_get($job, 'order_number', data_get($job, 'id'))) }}</h1><p class="mt-1 text-sm text-ink-500">{{ data_get($jobOrder, 'customer.user.name', data_get($jobOrder, 'customer_name', 'Pelanggan')) }}</p></div>
        <x-button :href="route('operator.jobs.index')" variant="secondary"><x-icon name="arrow-right" class="h-4 w-4" /> Daftar pekerjaan</x-button>
    </div>

    <div class="grid items-start gap-7 xl:grid-cols-[1fr_360px]">
        <div class="space-y-7">
            <section class="panel overflow-hidden"><div class="panel-head"><h2 class="font-extrabold text-ink-950">Spesifikasi pekerjaan</h2></div><dl class="grid gap-x-6 gap-y-5 p-5 sm:grid-cols-2"><div><dt class="text-xs font-semibold text-ink-500">Produk</dt><dd class="mt-1 font-semibold text-ink-800">{{ data_get($jobOrder, 'items.0.product_name', data_get($job, 'product_name', 'Belum tersedia')) }}</dd></div><div><dt class="text-xs font-semibold text-ink-500">Material</dt><dd class="mt-1 font-semibold text-ink-800">{{ data_get($jobOrder, 'items.0.material_name', 'Belum tersedia') }}</dd></div><div><dt class="text-xs font-semibold text-ink-500">Finishing</dt><dd class="mt-1 font-semibold text-ink-800">{{ data_get($jobOrder, 'items.0.finishing_name', 'Belum tersedia') }}</dd></div><div><dt class="text-xs font-semibold text-ink-500">Jumlah</dt><dd class="mt-1 font-semibold text-ink-800">{{ data_get($jobOrder, 'items.0.quantity', data_get($job, 'quantity', '—')) }}</dd></div><div><dt class="text-xs font-semibold text-ink-500">Ukuran</dt><dd class="mt-1 font-semibold text-ink-800">{{ data_get($jobOrder, 'items.0.size', '—') }} {{ data_get($jobOrder, 'items.0.length_cm', '') }} × {{ data_get($jobOrder, 'items.0.width_cm', '') }}</dd></div><div><dt class="text-xs font-semibold text-ink-500">Deadline</dt><dd class="mt-1 font-semibold text-ink-800"><span class="inline-flex items-center gap-1.5 rounded-full bg-accent-100 px-2.5 py-1 text-xs font-bold text-accent-800 ring-1 ring-inset ring-accent-300"><x-icon name="clock" class="h-3.5 w-3.5" /> {{ data_get($job, 'deadline')?->format('d F Y') ?? 'Belum ditentukan' }}</span></dd></div></dl></section>

            <section class="panel p-5 sm:p-6"><h2 class="font-extrabold text-ink-950">Digital Job Ticket</h2><p class="mt-1 text-xs text-ink-500">Tanggal pesanan: {{ data_get($jobOrder, 'created_at')?->format('d M Y') ?? '-' }}</p>@if(data_get($job, 'notes') || data_get($job, 'design_note') || data_get($jobOrder, 'internal_notes'))<p class="mt-3 whitespace-pre-line text-sm leading-6 text-ink-600">{{ data_get($job, 'notes') ?: data_get($job, 'design_note', data_get($jobOrder, 'internal_notes')) }}</p>@else<x-empty-state compact title="Catatan belum tersedia" icon="file" />@endif @if(collect($designFiles)->isNotEmpty())<div class="mt-4 space-y-2">@foreach($designFiles as $design)<a href="{{ \Illuminate\Support\Facades\Route::has('operator.designs.download') ? route('operator.designs.download', $design) : data_get($design, 'file_url') }}" target="_blank" rel="noopener" class="action-link"><x-icon name="file" class="h-4 w-4" /> {{ data_get($design, 'original_filename', 'File desain') }}</a>@endforeach</div>@endif</section>

            <section class="panel p-5 sm:p-6"><h2 class="font-extrabold text-ink-950">Riwayat kerja</h2>@if(collect($jobTimeline)->isNotEmpty())<ol class="mt-5 space-y-5">@foreach($jobTimeline as $event)<li class="flex gap-3"><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-brand-600 ring-4 ring-brand-100"></span><div><p class="text-sm font-bold text-ink-800">{{ data_get($event, 'new_status.label', data_get($event, 'new_status', data_get($event, 'title', 'Pembaruan pekerjaan'))) }}</p>@if(data_get($event, 'note', data_get($event, 'description')))<p class="mt-1 text-sm text-ink-500">{{ data_get($event, 'note', data_get($event, 'description')) }}</p>@endif</div></li>@endforeach</ol>@else<x-empty-state compact title="Riwayat belum tersedia" icon="clock" />@endif</section>
        </div>

        <aside class="space-y-5">
            <section class="panel p-5"><h2 class="font-extrabold text-ink-950">Perbarui tahap</h2><form method="POST" action="{{ $statusUrl }}" class="mt-4 space-y-3">@csrf<x-select name="status" label="Tahap produksi" required><option value="PRINTING">Printing</option><option value="FINISHING">Finishing</option><option value="PACKING">Packing</option><option value="QUALITY_CONTROL">Quality Control</option></x-select><x-input name="progress" label="Progres (%)" type="number" min="0" max="100" :value="data_get($job, 'progress')" /><x-textarea name="note" label="Catatan" :rows="2" /><x-button type="submit" class="w-full"><x-icon name="factory" class="h-4 w-4" /> Perbarui tahap</x-button></form></section>
            <section class="panel p-5"><h2 class="font-extrabold text-ink-950">Quality Control</h2><div class="mt-4 grid gap-3"><form method="POST" action="{{ $qualityUrl }}" class="space-y-3">@csrf<x-select name="result" label="Hasil pemeriksaan" required><option value="PASS">PASS</option><option value="FAIL">FAIL</option></x-select><x-textarea name="notes" label="Catatan QC (wajib jika FAIL)" :rows="2" /><x-button type="submit" variant="success" class="w-full"><x-icon name="shield" class="h-4 w-4" /> Simpan QC</x-button></form></div></section>
            @if($photoUrl)<section class="panel p-5"><h2 class="font-extrabold text-ink-950">Foto progres</h2><form method="POST" action="{{ $photoUrl }}" enctype="multipart/form-data" class="mt-4 space-y-3">@csrf<x-input name="photo" label="Unggah foto" type="file" accept=".jpg,.jpeg,.png,.webp" required /><x-button type="submit" variant="secondary" class="w-full"><x-icon name="upload" class="h-4 w-4" /> Unggah foto</x-button></form></section>@endif
            <section class="panel p-5"><h2 class="font-extrabold text-ink-950">Catatan pekerjaan</h2><form method="POST" action="{{ $noteUrl }}" class="mt-4 space-y-3">@csrf<x-textarea name="notes" label="Tambahkan catatan" :rows="3" required /><input type="hidden" name="note" value=""><x-button type="submit" variant="ghost" class="w-full"><x-icon name="file" class="h-4 w-4" /> Simpan catatan</x-button></form></section>
        </aside>
    </div>
</div>
@endsection
