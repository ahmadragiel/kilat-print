@props([
    'type' => 'info',
    'title' => null,
])

@php
    $styles = [
        'success' => ['border-emerald-200 bg-emerald-50 text-emerald-900', 'check-circle'],
        'error' => ['border-red-200 bg-red-50 text-red-900', 'x-circle'],
        'warning' => ['border-amber-200 bg-amber-50 text-amber-900', 'alert'],
        'info' => ['border-blue-200 bg-blue-50 text-blue-900', 'info'],
    ];
    [$boxStyle, $iconName] = $styles[$type] ?? $styles['info'];
@endphp

<div x-data="{ visible: true }" x-show="visible" x-transition {{ $attributes->class(['flex gap-3 rounded-lg border p-4', $boxStyle]) }} role="alert">
    <x-icon :name="$iconName" class="mt-0.5 h-5 w-5 shrink-0" />
    <div class="min-w-0 flex-1 text-sm">
        @if ($title)
            <p class="font-bold">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-0.5' : '' }}">{{ $slot }}</div>
    </div>
    <button type="button" x-on:click="visible = false" class="-m-1 rounded p-1 opacity-60 transition hover:opacity-100" aria-label="Tutup pemberitahuan">
        <x-icon name="x" class="h-4 w-4" />
    </button>
</div>
