{{--
    Advance collections ("the pot"): so much per traveller, paid to one
    collector who pays shared costs from it. Each advance is an ordinary
    payment to the collector, so the balances below always add up.
--}}
@php
    $pots = $pots ?? collect();
    $canManage = $canManage ?? false;
    $money = fn ($value) => \App\Support\Money::format($value, $trip->currency);
    $myMemberId = $ledger['me']['member']['id'] ?? null;
@endphp

@if ($pots->isNotEmpty() || ($canManage && $trip->isActive()))
    <x-card padding="p-4 sm:p-5" class="mb-4 lg:mb-6">
        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-accent/15 text-ink">
                    <x-icon name="coins" class="size-4.5" />
                </span>
                <div class="min-w-0">
                    <h2 class="truncate font-display text-sm font-semibold">{{ __('Trip pot') }}</h2>
                    <p class="text-[11px] text-faint">{{ __('Collect money up front, pay shared costs from one place') }}</p>
                </div>
            </div>

            @if ($canManage && $trip->isActive())
                <x-btn type="button" size="sm" variant="secondary" icon="plus" @click="$dispatch('sofor-collect')">{{ __('Collect an advance') }}</x-btn>
            @endif
        </div>

        @if ($pots->isEmpty())
            <p class="mt-4 rounded-3xl border border-dashed border-line-strong bg-surface-2 p-4 text-[12px] leading-relaxed text-muted">
                {{ __('Ask everyone for, say, :amount before you leave. Sofor tracks who has sent it, reminds the rest, and shows what is left in the pot as the holder pays the bus, the cottage and the food. Whatever is left at the end is paid back automatically in the settle up.', ['amount' => $money(2000)]) }}
            </p>
        @endif

        <div class="mt-4 flex flex-col gap-3">
            @foreach ($pots as $pot)
                @php
                    $collection = $pot['collection'];
                    $collector = $pot['collector'];
                    $open = $collection->isOpen();
                    $mine = $pot['mine'];
                    $statusChip = [
                        'paid' => [__('Paid'), 'bg-positive-tint text-positive'],
                        'pending' => [__('Awaiting confirmation'), 'bg-warning-tint text-warning'],
                        'partial' => [__('Part paid'), 'bg-info-tint text-info'],
                        'unpaid' => [__('Not yet'), 'bg-surface-2 text-muted'],
                    ];
                    $canMark = $open && ($canManage || $pot['is_collector']);
                @endphp

                <div @class(['rounded-3xl border border-line bg-surface-2 p-4', 'opacity-80' => ! $open])>
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-[13px] font-semibold text-ink">
                                {{ $collection->title }}
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide',
                                    'bg-positive-tint text-positive' => $open,
                                    'bg-surface text-faint hairline' => ! $open,
                                ])>{{ $open ? __('Open') : __('Closed') }}</span>
                            </p>
                            <p class="mt-0.5 text-[11px] text-muted">
                                {{ __(':amount a person to :name', ['amount' => $money($collection->amount_per_person), 'name' => $pot['is_collector'] ? __('you') : $collector?->name]) }}
                                @if ($collection->due_on)
                                    &middot; {{ __('due :date', ['date' => $collection->due_on->translatedFormat('D j M')]) }}
                                @endif
                            </p>
                            @if ($collection->note)
                                <p class="mt-1 text-[11px] text-faint">{{ $collection->note }}</p>
                            @endif
                        </div>

                        <p class="text-right">
                            <span class="num block font-display text-[15px] font-semibold text-ink">{{ $money($pot['collected']) }}</span>
                            <span class="text-[10px] text-faint">{{ __('of :amount', ['amount' => $money($pot['target'])]) }}</span>
                        </p>
                    </div>

                    <div class="mt-3">
                        <x-progress :value="$pot['percent']" size="sm" :tone="$pot['percent'] >= 100 ? 'positive' : 'brand'" />
                    </div>

                    <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted">
                        <span>{{ __(':paid_count of :count paid', ['paid_count' => $pot['paid_count'], 'count' => $pot['rows']->count()]) }}</span>
                        @if ($pot['pending'] > 0)
                            <span class="text-warning">{{ __(':amount awaiting confirmation', ['amount' => $money($pot['pending'])]) }}</span>
                        @endif
                        @if ($pot['spent'] > 0 || $pot['collected'] > 0)
                            <span>
                                {{ __('In the pot') }} <span class="num font-semibold {{ $pot['in_pot'] < 0 ? 'text-negative' : 'text-ink' }}">{{ $money($pot['in_pot']) }}</span>
                                @if ($pot['spent'] > 0)
                                    {{ __('after :amount of costs :name paid', ['amount' => $money($pot['spent']), 'name' => $pot['is_collector'] ? __('you') : $collector?->name]) }}
                                @endif
                            </span>
                        @endif
                    </p>

                    {{-- The viewer's own advance --}}
                    @if ($open && $mine && $mine['left'] > 0.009)
                        <div class="mt-3 rounded-2xl border border-brand/30 bg-surface p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-[12.5px] font-semibold text-ink">
                                    {{ __('Your advance: :amount', ['amount' => $money($mine['left'])]) }}
                                    @if ($mine['paid'] + $mine['pending'] > 0)
                                        <span class="font-normal text-muted">{{ __('left of :amount', ['amount' => $money($mine['due'])]) }}</span>
                                    @endif
                                </p>
                                <x-btn type="button" size="xs" icon="check"
                                       @click="$dispatch('sofor-settle', {
                                           from: {{ $myMemberId }},
                                           to: {{ $collector->id }},
                                           amount: {{ \Illuminate\Support\Js::from((string) $mine['left']) }},
                                           collection: {{ $collection->id }},
                                           collectionTitle: {{ \Illuminate\Support\Js::from($collection->title) }}
                                       })">
                                    {{ __('I paid :first_name', ['first_name' => $collector->firstName()]) }}
                                </x-btn>
                            </div>
                            <x-payout-chips :member="$collector" class="mt-2" />
                        </div>
                    @elseif ($pot['is_collector'] && $open)
                        <p class="mt-3 flex items-center gap-2 text-[11px] text-muted">
                            <x-icon name="info" class="size-3.5 shrink-0 text-brand" />
                            {{ __('You hold this pot. Costs you log from now on count as paid from it.') }}
                        </p>
                    @endif

                    {{-- Who has paid in --}}
                    @if ($pot['rows']->isNotEmpty())
                        <details class="mt-3 group/pot" @if ($canManage || $pot['is_collector']) open @endif>
                            <summary class="cursor-pointer text-[11px] font-semibold text-muted hover:text-ink">{{ __('Who has paid') }}</summary>
                            <ul class="mt-2 flex flex-col gap-1.5">
                                @foreach ($pot['rows'] as $row)
                                    @php [$label, $chip] = $statusChip[$row['status']]; @endphp
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-surface px-3 py-2">
                                        <span class="flex min-w-0 items-center gap-2">
                                            <x-avatar :name="$row['member']->name" :src="$row['member']->avatarUrl()" size="xs" />
                                            <span class="truncate text-[12px] font-medium text-ink">{{ $row['member']->name }}</span>
                                        </span>
                                        <span class="flex items-center gap-2">
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $chip }}">
                                                {{ $label }}@if ($row['status'] === 'partial') {{ $money($row['paid'] + $row['pending']) }}/{{ $money($row['due']) }}@endif
                                            </span>
                                            @if ($canMark && $row['left'] > 0.009)
                                                <button type="button"
                                                        @click="$dispatch('sofor-settle', {
                                                            from: {{ $row['member']->id }},
                                                            to: {{ $collector->id }},
                                                            amount: @js((string) $row['left']),
                                                            collection: {{ $collection->id }},
                                                            collectionTitle: @js($collection->title)
                                                        })"
                                                        class="rounded-full bg-brand/12 px-2 py-0.5 text-[10px] font-semibold text-brand transition hover:bg-brand/20">
                                                    {{ __('Mark paid') }}
                                                </button>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    @if ($canManage)
                        <div class="mt-3 flex flex-wrap gap-2 border-t border-line pt-3">
                            @if ($open && $pot['rows']->contains(fn ($row) => $row['left'] > 0.009))
                                <form method="POST" action="{{ route('trips.collections.nudge', [$trip, $collection]) }}">
                                    @csrf
                                    <x-btn type="submit" size="xs" variant="secondary" icon="bell">{{ __('Remind who has not paid') }}</x-btn>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('trips.collections.toggle', [$trip, $collection]) }}">
                                @csrf
                                <x-btn type="submit" size="xs" variant="ghost" :icon="$open ? 'lock' : 'refresh'">{{ $open ? __('Close') : __('Reopen') }}</x-btn>
                            </form>
                            <form method="POST" action="{{ route('trips.collections.destroy', [$trip, $collection]) }}"
                                  onsubmit="return confirm('Remove this collection? Advances already paid stay in the ledger as ordinary payments.')">
                                @csrf
                                @method('DELETE')
                                <x-btn type="submit" size="xs" variant="ghost" icon="trash">{{ __('Remove') }}</x-btn>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-card>
@endif
