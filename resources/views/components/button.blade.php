@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
    'type' => null,
])

@php
    $variants = [
        'primary' => 'bg-flame-500 text-ink-950 hover:bg-flame-400 focus-visible:outline-flame-600 shadow-sm',
        'dark' => 'bg-ink-950 text-white hover:bg-ink-800 focus-visible:outline-ink-950',
        'secondary' => 'border border-ink-300 bg-white text-ink-800 hover:border-ink-400 hover:bg-ink-50 focus-visible:outline-ink-700',
        'ghost' => 'text-ink-700 hover:bg-ink-100 hover:text-ink-950 focus-visible:outline-ink-700',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-700',
        'success' => 'bg-mint-600 text-white hover:bg-mint-700 focus-visible:outline-mint-700',
    ];
    $sizes = [
        'sm' => 'min-h-9 px-3 py-1.5 text-xs',
        'md' => 'min-h-11 px-4 py-2.5 text-sm',
        'lg' => 'min-h-12 px-5 py-3 text-sm',
        'icon' => 'h-10 w-10 p-0',
    ];
    $classes = 'inline-flex items-center justify-center gap-2 rounded-md font-bold transition disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes, $variants[$variant] ?? $variants['primary'], $sizes[$size] ?? $sizes['md']]) }}>
        {{ $slot }}
    </a>
@else
    <button @if($type) type="{{ $type }}" @else type="button" @endif {{ $attributes->class([$classes, $variants[$variant] ?? $variants['primary'], $sizes[$size] ?? $sizes['md']]) }}>
        {{ $slot }}
    </button>
@endif
