@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div>
        @if($eyebrow)
            <p class="section-kicker mb-2">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-2xl font-extrabold tracking-tight text-ink-950 sm:text-3xl">{{ $title }}</h1>
        @if($description)
            <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-600">{{ $description }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</div>
