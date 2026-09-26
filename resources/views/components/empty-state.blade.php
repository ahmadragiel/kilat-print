@props([
    'title' => 'Belum ada data',
    'description' => null,
    'icon' => 'box',
    'compact' => false,
])

<div {{ $attributes->class(['flex flex-col items-center justify-center text-center', $compact ? 'py-8' : 'py-14']) }}>
    <span class="mb-4 grid h-12 w-12 place-items-center rounded-lg bg-ink-100 text-ink-500">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <h3 class="font-bold text-ink-900">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-md text-sm leading-6 text-ink-500">{{ $description }}</p>
    @endif
    @php
    $hasSlot = isset($slot) && (is_object($slot) ? (method_exists($slot, 'isEmpty') ? !$slot->isEmpty() : true) : (string) $slot !== '');
@endphp

@if (!$hasSlot)
        @if(isset($action))
            <div class="mt-5">{{ $action }}</div>
        @endif
    @else
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
