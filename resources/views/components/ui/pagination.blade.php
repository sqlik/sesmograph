@props(['paginator'])

@if ($paginator->hasPages() || $paginator->total() > 0)
    <nav class="mt-4 flex items-center justify-between text-sm" aria-label="Pagination">
        <p class="text-ink-soft">
            {{ number_format($paginator->firstItem() ?? 0) }}-{{ number_format($paginator->lastItem() ?? 0) }}
            of {{ number_format($paginator->total()) }}
        </p>
        @if ($paginator->hasPages())
            <div class="flex items-center gap-2">
                @if ($paginator->onFirstPage())
                    <span class="rounded-lg border border-edge px-3 py-1.5 font-medium text-ink-faint">Previous</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border border-edge bg-surface px-3 py-1.5 font-medium text-ink hover:bg-edge/50 focus:outline-2 focus:outline-offset-2 focus:outline-focus">Previous</a>
                @endif

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border border-edge bg-surface px-3 py-1.5 font-medium text-ink hover:bg-edge/50 focus:outline-2 focus:outline-offset-2 focus:outline-focus">Next</a>
                @else
                    <span class="rounded-lg border border-edge px-3 py-1.5 font-medium text-ink-faint">Next</span>
                @endif
            </div>
        @endif
    </nav>
@endif
