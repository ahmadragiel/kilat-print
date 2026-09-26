@props([
    'color' => 'gray',
])

@php
    /* Brand-aware badge palette.
       `brand`  = merah solid (label brand / kategori)
       `accent` = kuning (Popular / Baru / Best Seller — gunakan hemat)
       `gray|red|green|blue|violet` = status semantik, tetap berbeda dari brand. */
    $colors = [
        'gray' => 'bg-ink-100 text-ink-700 ring-ink-200',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-200',
        'brand-solid' => 'bg-brand-600 text-white ring-brand-700',
        'accent' => 'bg-accent-100 text-accent-800 ring-accent-300',
        'accent-solid' => 'bg-accent-400 text-ink-950 ring-accent-500',
        'orange' => 'bg-accent-50 text-accent-800 ring-accent-200',
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'red' => 'bg-danger-50 text-danger-700 ring-danger-200',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'purple' => 'bg-violet-50 text-violet-700 ring-violet-200',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $colors[$color] ?? $colors['gray']]) }}>
    {{ $slot }}
</span>
