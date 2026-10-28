@php
    $window = 2;
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $from = max(1, $current - $window);
    $to = min($last, $current + $window);
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
         class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-4xl border border-line bg-surface px-4 py-3">

        <p class="text-[12.5px] text-muted">
            {!! __('Showing :first to :last of :total', [
                'first' => '<span class="num font-semibold text-ink">'.e($paginator->firstItem()).'</span>',
                'last' => '<span class="num font-semibold text-ink">'.e($paginator->lastItem()).'</span>',
                'total' => '<span class="num font-semibold text-ink">'.e(number_format($paginator->total())).'</span>',
            ]) !!}
        </p>

        <div class="flex items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="flex size-9 items-center justify-center rounded-xl border border-line text-faint opacity-50">
                    <x-icon name="chevron-left" class="size-4" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous page') }}"
                   class="flex size-9 items-center justify-center rounded-xl border border-line bg-surface text-muted transition hover:border-ink/30 hover:text-ink">
                    <x-icon name="chevron-left" class="size-4" />
                </a>
            @endif

            @if ($from > 1)
                <a href="{{ $paginator->url(1) }}"
                   class="num flex h-9 min-w-9 items-center justify-center rounded-xl border border-line bg-surface px-2 text-[13px] font-semibold text-muted transition hover:border-ink/30 hover:text-ink">1</a>
                @if ($from > 2)
                    <span class="px-1 text-faint">&hellip;</span>
                @endif
            @endif

            @foreach ($paginator->getUrlRange($from, $to) as $page => $url)
                @if ($page === $current)
                    <span aria-current="page"
                          class="num flex h-9 min-w-9 items-center justify-center rounded-xl bg-brand px-2 text-[13px] font-bold text-brand-ink">{{ $page }}</span>
                @else
                    <a href="{{ $url }}"
                       class="num flex h-9 min-w-9 items-center justify-center rounded-xl border border-line bg-surface px-2 text-[13px] font-semibold text-muted transition hover:border-ink/30 hover:text-ink">{{ $page }}</a>
                @endif
            @endforeach

            @if ($to < $last)
                @if ($to < $last - 1)
                    <span class="px-1 text-faint">&hellip;</span>
                @endif
                <a href="{{ $paginator->url($last) }}"
                   class="num flex h-9 min-w-9 items-center justify-center rounded-xl border border-line bg-surface px-2 text-[13px] font-semibold text-muted transition hover:border-ink/30 hover:text-ink">{{ $last }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next page') }}"
                   class="flex size-9 items-center justify-center rounded-xl border border-line bg-surface text-muted transition hover:border-ink/30 hover:text-ink">
                    <x-icon name="chevron-right" class="size-4" />
                </a>
            @else
                <span class="flex size-9 items-center justify-center rounded-xl border border-line text-faint opacity-50">
                    <x-icon name="chevron-right" class="size-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
