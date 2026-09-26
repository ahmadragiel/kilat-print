@props([
    'inverse' => false,
])

<a href="/" {{ $attributes->class(['inline-flex items-center gap-2.5 font-extrabold tracking-tight', $inverse ? 'text-white' : 'text-ink-950']) }}>
    <span {{ $attributes->class(['grid h-9 w-9 place-items-center rounded-md', $inverse ? 'bg-flame-400 text-ink-950' : 'bg-ink-950 text-flame-400']) }}>
        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
            <path d="M13.2 2 5 13h6l-.8 9L19 10h-6l.2-8Z"/>
        </svg>
    </span>
    <span class="text-lg">Kilat Print</span>
</a>
