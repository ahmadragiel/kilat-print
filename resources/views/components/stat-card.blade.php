@props([
    'label',
    'value',
    'hint' => null,
    'icon' => 'chart',
    'href' => null,
    'tone' => 'brand',
])

@php
    $tag = $href ? 'a' : 'div';
    /* tone: brand (default) | accent | neutral */
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'accent' => 'bg-accent-100 text-accent-700',
        'neutral' => 'bg-ink-100 text-ink-600',
    ];
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['panel group block p-5', 'transition duration-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-panel-lg' => (bool) $href]) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium text-ink-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-black tracking-tight text-brand-600">{{ $value }}</p>
            @if($hint)
                <p class="mt-1 text-xs leading-5 text-ink-500">{{ $hint }}</p>
            @endif
        </div>
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    </div>
    @if ($href)
        <span class="mt-4 inline-flex items-center gap-1 text-xs font-bold text-brand-600 transition group-hover:gap-1.5">
            Selengkapnya <x-icon name="arrow-right" class="h-3.5 w-3.5" />
        </span>
    @endif
</{{ $tag }}>
