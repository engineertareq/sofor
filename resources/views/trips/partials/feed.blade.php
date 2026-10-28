@php
    $canEdit = $canManage || $trip->user_id === auth()->id();
@endphp

<x-card padding="p-4 sm:p-5">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-accent/12 text-accent">
                <x-icon name="receipt" class="size-4.5" />
            </span>
            <div class="min-w-0">
                <h2 class="truncate font-display text-sm font-semibold">{{ __('Cost ledger') }}</h2>
                <p class="truncate text-[11px] text-faint">{{ __(':count costs - newest first', ['count' => $expenses->count()]) }}</p>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            {{-- Travellers only: the admin console never links into /trips. --}}
            @if ($expenses->isNotEmpty() && ! auth()->user()?->isAdmin())
                <a href="{{ route('trips.timeline', $trip) }}"
                   class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-2.5 py-1 text-[11px] font-semibold text-ink transition hover:border-line-strong">
                    <x-icon name="list" class="size-3.5 text-brand" /> {{ __('Timeline') }}
                </a>
            @endif
            @if ($canAddExpense)
                <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-positive-tint px-2.5 py-1 text-[11px] font-medium text-positive">
                    <span class="size-1.5 animate-pulse rounded-full bg-positive"></span> {{ __('Live') }}
                </span>
            @endif
        </div>
    </div>

    @if ($expenses->isEmpty())
        <x-empty icon="receipt" title="{{ __('No costs yet') }}" tone="lime"
                 :text="__('Add the first cost - the cottage, the bus fare, anything the group shares - and Sofor will divide it instantly.')">
            @if ($canAddExpense)
                <x-btn type="button" size="sm" icon="plus" @click="$dispatch('sofor-add-expense')">{{ __('Add first cost') }}</x-btn>
            @endif
        </x-empty>
    @else
        <ul class="mt-4 flex flex-col gap-2.5">
            @foreach ($expenses as $expense)
                @php
                    $category = $expense->categoryEnum();
                    $shareCount = $expense->shares->count();
                    $shareAmount = $shareCount > 0 ? (float) $expense->amount / $shareCount : 0;
                    $isCustom = $expense->split_type !== \App\Enums\SplitType::Equal;
                    $isItemised = $expense->isItemised();
                    $canTouch = $canEdit || $expense->created_by === auth()->id();
                @endphp

                <li class="group rounded-3xl border border-line bg-surface-2 p-3.5 transition hover:border-line-strong">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-surface hairline"><x-glyph :name="$category->icon()" class="size-7" /></span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="min-w-0 truncate text-[13px] font-semibold text-ink">{{ $expense->title }}</p>
                                <span class="chip shrink-0" data-cat="{{ $category->value }}">{{ $category->label() }}</span>
                                @if ($isItemised)
                                    <span class="shrink-0 rounded-full bg-brand-tint px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-brand">{{ __('By item') }}</span>
                                @elseif ($isCustom)
                                    <span class="shrink-0 rounded-full bg-brand-tint px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-brand">{{ __('Custom split') }}</span>
                                @endif
                                @if ($expense->hasReceipt())
                                    <button type="button"
                                            @click="$dispatch('sofor-receipt', { src: @js($expense->receiptUrl()), title: @js($expense->title) })"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-surface px-2 py-0.5 text-[10px] font-semibold text-muted hairline transition hover:text-ink">
                                        <x-icon name="camera" class="size-3" /> {{ __('Receipt') }}
                                    </button>
                                @endif
                            </div>

                            <p class="mt-1 text-[11px] leading-relaxed text-faint">
                                {{ __('Paid by :name', ['name' => $expense->payer?->name ?? __('a removed traveller')]) }}
                                - {{ $expense->spent_on?->translatedFormat('d M Y') }}
                                @if ($shareCount > 0)
                                    - {{ trans_choice('split :count way|split :count ways', $shareCount) }}
                                    @if (! $isCustom)
                                        ({{ __(':amount each', ['amount' => \App\Support\Money::format($shareAmount, $trip->currency)]) }})
                                    @endif
                                @endif
                            </p>

                            @if ($isItemised)
                                <details class="mt-1.5 rounded-2xl border border-line bg-surface px-3 py-2 text-[11px]">
                                    <summary class="cursor-pointer font-medium text-muted">{{ trans_choice(':count item on the bill|:count items on the bill', count($expense->items)) }}</summary>
                                    <ul class="mt-1.5 flex flex-col gap-1">
                                        @foreach ($expense->items as $item)
                                            <li class="flex items-baseline justify-between gap-3">
                                                <span class="min-w-0 truncate text-ink">{{ $item['name'] }}
                                                    <span class="text-faint">- {{ collect($item['members'])->map(fn ($id) => \Illuminate\Support\Str::before((string) $expense->shares->firstWhere('trip_member_id', $id)?->member?->name, ' ') ?: '?')->implode(', ') }}</span>
                                                </span>
                                                <span class="num shrink-0 font-semibold text-ink">{{ \App\Support\Money::format($item['price'], $trip->currency) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif

                            @if ($expense->notes)
                                <p class="mt-1.5 rounded-2xl border border-line bg-surface px-3 py-2 text-[11px] leading-relaxed text-muted">{{ $expense->notes }}</p>
                            @endif
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="font-display text-[15px] font-semibold text-ink">{{ \App\Support\Money::format($expense->amount, $trip->currency) }}</p>

                            @if ($canTouch && $trip->isActive())
                                <div class="mt-1.5 flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100">
                                    <button type="button"
                                            @click="$dispatch('sofor-edit-expense', {
                                                action: '{{ route('trips.expenses.update', [$trip, $expense]) }}',
                                                title: @js($expense->title),
                                                amount: '{{ (float) $expense->amount }}',
                                                category: '{{ $category->value }}',
                                                spent_on: '{{ $expense->spent_on?->toDateString() }}',
                                                payer: '{{ $expense->trip_member_id }}',
                                                notes: @js((string) $expense->notes),
                                                split_type: '{{ $expense->split_type->value }}',
                                                shares: @js($expense->shares->mapWithKeys(fn ($share) => [$share->trip_member_id => (float) $share->amount])),
                                                items: @js($expense->items ?? []),
                                                receipt: @js($expense->receiptUrl())
                                            })"
                                            class="flex size-7 items-center justify-center rounded-lg text-faint transition hover:bg-surface hover:text-ink"
                                            aria-label="{{ __('Edit cost') }}">
                                        <x-icon name="pencil" class="size-3.5" />
                                    </button>

                                    <form method="POST" action="{{ route('trips.expenses.destroy', [$trip, $expense]) }}"
                                          onsubmit="return confirm('Delete this cost? Balances will be recalculated for everyone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="flex size-7 items-center justify-center rounded-lg text-faint transition hover:bg-negative-tint hover:text-negative" aria-label="{{ __('Delete cost') }}">
                                            <x-icon name="trash" class="size-3.5" />
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($expense->shares->isNotEmpty())
                        <div class="mt-2.5 flex flex-wrap gap-1.5 border-t border-line pt-2.5">
                            @foreach ($expense->shares as $share)
                                <span class="inline-flex max-w-full min-w-0 items-center gap-1.5 rounded-full bg-surface px-2 py-1 text-[10px] hairline">
                                    <span class="min-w-0 truncate font-medium text-muted">{{ $share->member?->name ?? __('Removed') }}</span>
                                    <span class="shrink-0 font-semibold text-ink">{{ \App\Support\Money::format($share->amount, $trip->currency) }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
