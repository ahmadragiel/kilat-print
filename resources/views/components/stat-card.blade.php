@props([
    'label',
    'value',
    'hint' => null,
    'icon' => 'chart',
    'href' => null,
])

@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['panel block p-5', 'transition hover:border-ink-300 hover:shadow-md' => (bool) $href]) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium text-ink-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-extrabold tracking-tight text-ink-950">{{ $value }}</p>
            @if($hint)
                <p class="mt-1 text-xs leading-5 text-ink-500">{{ $hint }}</p>
            @endif
        </div>
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-flame-50 text-flame-700">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    </div>
</{{ $tag }}>
