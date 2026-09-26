@props([
    'src' => null,
    'alt' => 'Gambar produk',
    'class' => 'aspect-[4/3]',
])

@php
    $placeholderId = 'sheet-' . \Illuminate\Support\Str::random(8);
    $imageUrl = filled($src) && ! preg_match('#^(https?:|//|/|data:)#i', (string) $src)
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($src)
        : $src;
@endphp
<div {{ $attributes->except('class')->class(['relative overflow-hidden bg-ink-100', $class]) }}>
    @if (filled($imageUrl))
        <img src="{{ $imageUrl }}" alt="{{ $alt }}" class="h-full w-full object-cover" loading="lazy">
    @else
        <div class="subtle-grid absolute inset-0 bg-gradient-to-br from-ink-50 via-white to-flame-50">
            <svg viewBox="0 0 400 300" class="h-full w-full" role="img" aria-label="Placeholder gambar produk Kilat Print">
                <defs>
                    <linearGradient id="{{ $placeholderId }}" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#ffffff" />
                        <stop offset="1" stop-color="#e8edf4" />
                    </linearGradient>
                </defs>
                <rect x="95" y="62" width="210" height="176" rx="8" fill="url(#{{ $placeholderId }})" stroke="#cbd5e1" />
                <path d="M130 195c30-50 75-72 140-66" fill="none" stroke="#fb8017" stroke-width="10" stroke-linecap="round" opacity=".9" />
                <path d="m247 72-18 35h20l-15 31 43-42h-23l17-24Z" fill="#111827" />
                <circle cx="115" cy="91" r="7" fill="#fb8017" opacity=".22" />
            </svg>
        </div>
    @endif
</div>
