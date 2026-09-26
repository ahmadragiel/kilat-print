@props([
    'color' => 'gray',
])

@php
    $colors = [
        'gray' => 'bg-ink-100 text-ink-700 ring-ink-200',
        'orange' => 'bg-flame-50 text-flame-800 ring-flame-200',
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'red' => 'bg-red-50 text-red-700 ring-red-200',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'purple' => 'bg-violet-50 text-violet-700 ring-violet-200',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $colors[$color] ?? $colors['gray']]) }}>
    {{ $slot }}
</span>
