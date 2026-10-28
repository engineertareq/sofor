{{--
    The budget meter on the trip page: spent against the plan, what is left,
    where the pace lands by the last day, and the viewer's own share against
    their per-person budget. With no budget, the leader sees what trips to
    the same place usually cost.
--}}
@php
    $budget = $ledger['budget'];
    $me = $ledger['me'];
    $estimate = $estimate ?? null;
    $canUpdate = $canUpdate ?? false;
    $money = fn ($value) => \App\Support\Money::format($value, $trip->currency);
    $toneText = ['positive' => 'text-positive', 'warning' => 'text-warning', 'negative' => 'text-negative'];
@endphp

@if ($budget['total'] !== null)
    <div class="mt-3 rounded-4xl border border-line bg-surface p-4 shadow-soft">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <span @class([
                    'flex size-10 shrink-0 items-center justify-center rounded-2xl',
                    'bg-positive-tint text-positive' => $budget['tone'] === 'positive',
                    'bg-warning-tint text-warning' => $budget['tone'] === 'warning',
                    'bg-negative-tint text-negative' => $budget['tone'] === 'negative',
                ])>
                    <x-icon name="wallet" class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-[13px] font-semibold text-ink">
                        @if ($budget['mode'] === 'person')
                            {{ __('Budget :amount a person', ['amount' => $money($budget['per_person'])]) }}
                            <span class="font-normal text-muted">&middot; {{ __(':amount for the group', ['amount' => $money($budget['total'])]) }}</span>
                        @else
                            {{ __('Budget :amount', ['amount' => $money($budget['total'])]) }}
                            @if ($budget['per_person'])
                                <span class="font-normal text-muted">&middot; {{ __('about :amount a person', ['amount' => $money($budget['per_person'])]) }}</span>
                            @endif
                        @endif
                    </p>
                    <p class="text-[11px] text-muted">
                        <span class="num font-semibold {{ $toneText[$budget['tone']] ?? 'text-ink' }}">{{ \App\Support\Money::percent($budget['used']) }}</span> {{ __('used') }}
                        &middot;
                        @if ($budget['left'] >= 0)
                            {{ __(':amount left', ['amount' => $money($budget['left'])]) }}
                        @else
                            <span class="font-semibold text-negative">{{ __(':amount over', ['amount' => $money(abs($budget['left']))]) }}</span>
                        @endif
                    </p>
                </div>
            </div>

            @if ($canUpdate)
                <a href="{{ route('trips.edit', $trip) }}#budget-amount" class="text-[11px] font-semibold text-brand hover:underline">{{ __('Change') }}</a>
            @endif
        </div>

        <div class="relative mt-3">
            <x-progress :value="$budget['used']" size="sm" :tone="$budget['tone']" />
            {{-- The 80% warning line. --}}
            <span class="absolute top-[-2px] h-2.5 w-px bg-ink/25" style="left: 80%" aria-hidden="true"></span>
        </div>

        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-[11px] text-muted">
            @if ($budget['forecast'] !== null)
                <span class="flex items-center gap-1.5 {{ $budget['forecast_over'] ? 'text-negative' : '' }}">
                    <x-icon name="trending-up" class="size-3.5" />
                    {{ __('At this pace :amount by :date', ['amount' => $money($budget['forecast']), 'date' => $trip->ends_on->translatedFormat('D j M')]) }}
                    @if ($budget['forecast_over'])
                        &middot; {{ __(':amount over', ['amount' => $money($budget['forecast'] - $budget['total'])]) }}
                    @endif
                </span>
            @endif

            @if ($me && $me['is_member'] && ($me['budget'] ?? null))
                <span class="flex items-center gap-1.5">
                    <x-icon name="user" class="size-3.5" />
                    {{ __('Your share :share of :budget', ['share' => $money($me['share']), 'budget' => $money($me['budget'])]) }}
                    <span class="num font-semibold {{ ($me['budget_used'] ?? 0) >= 100 ? 'text-negative' : (($me['budget_used'] ?? 0) >= 80 ? 'text-warning' : 'text-ink') }}">({{ \App\Support\Money::percent($me['budget_used']) }})</span>
                </span>
            @endif
        </div>
    </div>
@elseif ($canUpdate && $estimate)
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-4xl border border-info/25 bg-info-tint/60 p-4">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-surface text-info">
                <x-icon name="chart" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="text-[13px] font-semibold text-ink">
                    {{ __('Trips to :destination usually cost about :amount a person', ['destination' => \Illuminate\Support\Str::limit(preg_split('/\s*,\s*/', $trip->destination)[0], 40), 'amount' => $money($estimate['per_person'])]) }}
                </p>
                <p class="text-[11px] text-muted">
                    {{ $estimate['days']
                        ? trans_choice(':low–:high for :count day, from :trips finished Sofor trips. Set a budget and Sofor warns the group before it runs out.|:low–:high for :count days, from :trips finished Sofor trips. Set a budget and Sofor warns the group before it runs out.', $estimate['days'], ['low' => $money($estimate['low']), 'high' => $money($estimate['high']), 'trips' => $estimate['trips']])
                        : __(':low–:high, from :trips finished Sofor trips. Set a budget and Sofor warns the group before it runs out.', ['low' => $money($estimate['low']), 'high' => $money($estimate['high']), 'trips' => $estimate['trips']]) }}
                </p>
            </div>
        </div>
        <x-btn :href="route('trips.edit', $trip).'#budget-amount'" variant="secondary" size="sm" icon="wallet">{{ __('Set a budget') }}</x-btn>
    </div>
@endif
