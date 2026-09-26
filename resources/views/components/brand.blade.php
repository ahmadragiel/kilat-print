@props([
    'inverse' => false,
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => ['h-8 w-8', 'h-4 w-4', 'text-base'],
        'md' => ['h-9 w-9', 'h-5 w-5', 'text-lg'],
        'lg' => ['h-11 w-11', 'h-6 w-6', 'text-2xl'],
    ];
    [$markSize, $glyphSize, $wordSize] = $sizes[$size] ?? $sizes['md'];
@endphp

<a href="/" {{ $attributes->class(['inline-flex items-center gap-2.5 font-extrabold tracking-tight', $inverse ? 'text-white' : 'text-ink-950']) }}>
    <span {{ $attributes->class(['grid shrink-0 place-items-center rounded-lg', $markSize, $inverse ? 'bg-accent-400 text-ink-950' : 'bg-brand-600 text-white']) }}>
        <svg viewBox="0 0 24 24" class="{{ $glyphSize }}" fill="currentColor" aria-hidden="true">
            <path d="M13.2 2 5 13h6l-.8 9L19 10h-6l.2-8Z"/>
        </svg>
    </span>
    <span class="{{ $wordSize }}">Kilat Print</span>
</a>
