@php
    $categories = $ledger['categories'];
    $maxTotal = collect($categories)->max('total') ?: 0;
@endphp

<x-card padding="p-4 sm:p-5">
    <div class="flex items-center gap-2.5">
        <span class="flex size-9 items-center justify-center rounded-2xl bg-info-tint text-info">
            <x-icon name="chart" class="size-4.5" />
        </span>
        <div>
            <h2 class="font-display text-sm font-semibold">{{ __('Where the money went') }}</h2>
            <p class="text-[11px] text-faint">
                {{ __(':amount tracked in total', ['amount' => \App\Support\Money::format($ledger['stats']['spent'], $trip->currency)]) }}
            </p>
        </div>
    </div>

    @if (empty($categories))
        <p class="mt-4 text-[13px] text-muted">{{ __('Category insights appear once you start logging costs.') }}</p>
    @else
        <ul class="mt-4 flex flex-col gap-3.5">
            @foreach ($categories as $row)
                <li>
                    <div class="flex items-center justify-between gap-3 text-[12px]">
                        <span class="flex min-w-0 items-center gap-2 font-medium text-ink">
                            <x-glyph :name="$row['icon']" class="size-5 shrink-0" /> {{ $row['label'] }}
                            <span class="shrink-0 text-[10px] text-faint">{{ __(':count costs', ['count' => $row['count']]) }}</span>
                        </span>
                        <span class="flex shrink-0 items-baseline gap-2.5">
                            <span class="num font-mono text-[11px] text-muted">{{ \App\Support\Money::percent($row['percent']) }}</span>
                            <span class="num font-semibold text-ink">{{ \App\Support\Money::format($row['total'], $trip->currency) }}</span>
                        </span>
                    </div>
                    <div class="mt-1.5">
                        <x-progress :value="$row['percent']" size="sm" tone="brand" />
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
