@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
    'type' => null,
])

@php
    /* Brand button system
       primary  = merah solid, teks putih (CTA utama)
       accent   = kuning solid, teks gelap (CTA sekunder / highlight)
       outline  = putih + border merah (aksi sekunder)
       ghost    = transparan (link-ish)
       soft     = merah muda (aksen halus)
       dark     = charcoal solid
       danger   = merah tua destructive (terbaca berbeda dari primary)
       success  = hijau (semantic success) */
    $variants = [
        'primary' => 'bg-brand-600 text-white hover:bg-brand-700 active:bg-brand-800 focus-visible:outline-brand-700 shadow-brand',
        'accent' => 'bg-accent-400 text-ink-950 hover:bg-accent-500 active:bg-accent-600 focus-visible:outline-accent-600 shadow-accent',
        'outline' => 'border border-brand-200 bg-white text-brand-700 hover:border-brand-500 hover:bg-brand-50 active:bg-brand-100 focus-visible:outline-brand-600',
        'soft' => 'bg-brand-50 text-brand-700 hover:bg-brand-100 active:bg-brand-200 focus-visible:outline-brand-600',
        'ghost' => 'text-ink-700 hover:bg-ink-100 hover:text-ink-950 focus-visible:outline-ink-700',
        'dark' => 'bg-ink-950 text-white hover:bg-ink-800 active:bg-ink-900 focus-visible:outline-ink-950',
        'danger' => 'bg-danger-700 text-white hover:bg-danger-800 active:bg-danger-900 focus-visible:outline-danger-800 shadow-sm',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-700 active:bg-emerald-800 focus-visible:outline-emerald-700',
    ];
    /* Backwards-compatible alias: `secondary` === `outline`. */
    $variants['secondary'] = $variants['outline'];

    $sizes = [
        'sm' => 'min-h-9 px-3 py-1.5 text-xs',
        'md' => 'min-h-11 px-4 py-2.5 text-sm',
        'lg' => 'min-h-12 px-6 py-3 text-base',
        'icon' => 'h-10 w-10 p-0',
    ];
    $classes = 'btn-base '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </a>
@else
    <button @if($type) type="{{ $type }}" @else type="button" @endif {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </button>
@endif
