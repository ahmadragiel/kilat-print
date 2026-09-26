@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-500">
            {{ __('Showing') }}
            @if ($paginator->firstItem())
                <span class="font-bold text-ink-800">{{ $paginator->firstItem() }}</span>
                {{ __('to') }}
                <span class="font-bold text-ink-800">{{ $paginator->lastItem() }}</span>
            @else
                <span class="font-bold text-ink-800">{{ $paginator->count() }}</span>
            @endif
            {{ __('of') }}
            <span class="font-bold text-ink-800">{{ $paginator->total() }}</span>
            {{ __('results') }}
        </p>

        <div class="flex flex-wrap items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-ink-200 bg-ink-50 px-3 text-sm font-semibold text-ink-400" aria-disabled="true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
                    <span class="hidden sm:inline">{{ __('pagination.previous') }}</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 text-sm font-semibold text-ink-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
                    <span class="hidden sm:inline">{{ __('pagination.previous') }}</span>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-dashed border-ink-200 px-3 text-sm font-semibold text-ink-500">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg bg-brand-600 px-3 text-sm font-black text-white shadow-sm">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-ink-200 bg-white px-3 text-sm font-semibold text-ink-700 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 text-sm font-semibold text-ink-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700">
                    <span class="hidden sm:inline">{{ __('pagination.next') }}</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                </a>
            @else
                <span class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-ink-200 bg-ink-50 px-3 text-sm font-semibold text-ink-400" aria-disabled="true">
                    <span class="hidden sm:inline">{{ __('pagination.next') }}</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
