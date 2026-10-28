@php
    $suggestions = $ledger['suggestions'];
    $myMemberId = $ledger['me']['member']['id'] ?? null;
    $stats = $ledger['stats'];
    $viewer = auth()->user();

    // Payments a traveller reported that still wait for the receiver.
    $pending = $settlements->filter(fn ($settlement) => $settlement->isPending());
    $toConfirm = $pending->filter(fn ($settlement) => $viewer?->can('confirmSettlement', [$trip, $settlement]));

    // Everyone who has paid money into the trip, with what they put in so far.
    $contributorRows = collect($ledger['balances'])
        ->filter(fn (array $row) => $row['contributed'] > 0)
        ->sortByDesc('contributed')
        ->map(fn (array $row) => [
            'member' => $ledger['members']->firstWhere('id', $row['member_id']),
            'total' => $row['contributed'],
            'count' => $row['contribution_count'],
        ])
        ->filter(fn (array $row) => $row['member'] !== null)
        ->values();
@endphp

@include('trips.partials.pot')

<x-card padding="p-4 sm:p-5">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-brand-tint text-brand">
                <x-icon name="wallet" class="size-4.5" />
            </span>
            <div class="min-w-0">
                <h2 class="truncate font-display text-sm font-semibold">{{ __('Settle up') }}</h2>
                <p class="text-[11px] text-faint">
                    {{ __('Pay each other directly - :amount paid so far', ['amount' => \App\Support\Money::format($stats['contribution_total'], $trip->currency)]) }}
                </p>
            </div>
        </div>

        @if ($canRecord ?? false)
            <x-btn type="button" size="sm" variant="secondary" icon="plus" @click="$dispatch('sofor-settle', {})">{{ __('Record a payment') }}</x-btn>
        @endif
    </div>

    {{-- ===== Waiting for this viewer to confirm ===== --}}
    @if ($toConfirm->isNotEmpty())
        <ul class="mt-4 flex flex-col gap-2">
            @foreach ($toConfirm as $settlement)
                <li class="rounded-3xl border-[1.5px] border-warning/40 bg-warning-tint p-3.5">
                    <p class="text-[12.5px] font-semibold text-ink">
                        {{ $settlement->to?->user_id === $viewer?->id
                            ? __(':name says they paid you', ['name' => $settlement->from?->name])
                            : __(':name says they paid :to', ['name' => $settlement->from?->name, 'to' => $settlement->to?->name]) }}
                        <span class="num">{{ \App\Support\Money::format($settlement->amount, $trip->currency) }}</span>
                    </p>
                    <p class="mt-0.5 text-[11px] text-muted">
                        {{ $settlement->methodEnum()->label() }}@if ($settlement->reference) - {{ __('transaction') }} <span class="font-mono">{{ $settlement->reference }}</span>@endif
                        - {{ $settlement->created_at?->diffForHumans() }}
                    </p>
                    <div class="mt-2.5 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('trips.settlements.confirm', [$trip, $settlement]) }}">
                            @csrf
                            <x-btn type="submit" size="xs" icon="check">{{ __('Yes, I got it') }}</x-btn>
                        </form>
                        <form method="POST" action="{{ route('trips.settlements.reject', [$trip, $settlement]) }}"
                              onsubmit="return confirm('Mark this payment as not received? It comes off the ledger and the payer is told.')">
                            @csrf
                            <x-btn type="submit" size="xs" variant="ghost">{{ __('Not received') }}</x-btn>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($contributorRows->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($contributorRows as $row)
                <span class="inline-flex max-w-full min-w-0 items-center gap-2 rounded-full border border-line bg-surface-2 px-2.5 py-1.5">
                    <x-avatar :name="$row['member']->name" :gradient="$row['member']->avatarGradient()" :src="$row['member']->avatarUrl()" size="xs" class="size-6 shrink-0 rounded-lg" />
                    <span class="min-w-0 truncate text-[11px] font-medium text-muted">{{ $row['member']->name }}</span>
                    <span class="shrink-0 text-[11px] font-semibold text-ink">{{ \App\Support\Money::format($row['total'], $trip->currency) }}</span>
                    @if ($row['count'] > 1)
                        <span class="shrink-0 text-[10px] text-faint">x{{ $row['count'] }}</span>
                    @endif
                </span>
            @endforeach
        </div>
    @endif

    @if (empty($suggestions))
        <div class="mt-4 flex items-center gap-3 rounded-3xl border border-positive/40 bg-positive-tint p-4">
            <x-icon name="check-circle" class="size-5 shrink-0 text-positive" />
            <div>
                <p class="text-[13px] font-semibold text-ink">{{ __('Everyone is settled') }}</p>
                <p class="text-[11px] text-muted">{{ __('No payment is pending.') }}</p>
            </div>
        </div>
    @else
        <ul class="mt-4 flex flex-col gap-2.5">
            @foreach ($suggestions as $suggestion)
                @php
                    $iPay = $myMemberId !== null && $suggestion['from_id'] === $myMemberId;
                    $involvesMe = $iPay || ($myMemberId !== null && $suggestion['to_id'] === $myMemberId);
                @endphp

                <li class="rounded-3xl border p-3.5 {{ $involvesMe ? 'border-brand/35 bg-brand/8' : 'border-line bg-surface-2' }}">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <x-avatar :name="$suggestion['from']->name" :gradient="$suggestion['from']->avatarGradient()" :src="$suggestion['from']->avatarUrl()" size="xs" />
                            <x-icon name="chevron-right" class="size-3.5 text-faint" />
                            <x-avatar :name="$suggestion['to']->name" :gradient="$suggestion['to']->avatarGradient()" :src="$suggestion['to']->avatarUrl()" size="xs" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[12px] font-medium text-ink">
                                {{ $iPay ? __('You pay :name', ['name' => $suggestion['to']->name]) : __(':from pays :to', ['from' => $suggestion['from']->name, 'to' => $suggestion['to']->name]) }}
                            </p>
                        </div>

                        <p class="shrink-0 font-display text-sm font-semibold text-ink">{{ \App\Support\Money::format($suggestion['amount'], $trip->currency) }}</p>
                    </div>

                    {{-- Where the money goes: straight to the receiver. --}}
                    <x-payout-chips :member="$suggestion['to']" :compact="! $iPay" class="mt-2.5" />

                    <div class="mt-2.5 flex flex-wrap justify-end gap-2">
                        {{-- The leader, or the person this money is owed to, can send a reminder. --}}
                        @if (! $iPay && (($canManage ?? false) || ($myMemberId !== null && $suggestion['to_id'] === $myMemberId)))
                            <button type="button" @click="$dispatch('sofor-nudge', {
                                        member: {{ $suggestion['from_id'] }},
                                        name: @js($suggestion['from']->name),
                                        amount: @js(\App\Support\Money::format($suggestion['amount'], $trip->currency)),
                                        link: @js($suggestion['from']->payUrl()),
                                    })"
                                    class="flex items-center gap-1.5 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-[11px] font-medium text-muted transition hover:text-ink">
                                <x-icon name="bell" class="size-3.5" /> {{ __('Remind') }}
                            </button>
                        @endif
                        @if ($iPay || ($canRecord ?? false))
                            <button type="button"
                                    @click="$dispatch('sofor-settle', { from: {{ $suggestion['from_id'] }}, to: {{ $suggestion['to_id'] }}, amount: @js((string) $suggestion['amount']) })"
                                    @class([
                                        'flex items-center gap-1.5 rounded-xl px-2.5 py-1.5 text-[11px] font-semibold transition',
                                        'bg-brand text-white hover:bg-brand-2' => $iPay,
                                        'border border-line bg-surface text-muted hover:text-ink' => ! $iPay,
                                    ])>
                                <x-icon name="check" class="size-3.5" /> {{ $iPay ? __('I paid :name', ['name' => $suggestion['to']->name]) : __('Mark paid') }}
                            </button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        {{-- Leader tools: chase everyone at once, and the automatic reminders. --}}
        @if ($canManage ?? false)
            <div class="mt-3 flex flex-col gap-2.5 rounded-3xl border border-dashed border-line-strong p-3.5 sm:flex-row sm:items-center sm:justify-between">
                <form method="POST" action="{{ route('trips.remind.settings', $trip) }}" x-data="{ on: {{ $trip->sms_reminders ? 'true' : 'false' }} }" x-ref="auto">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="sms_reminders" :value="on ? 1 : 0">
                    <label class="flex cursor-pointer items-start gap-2.5 text-[11.5px] leading-relaxed text-muted">
                        <button type="button" role="switch" class="switch mt-0.5" :aria-checked="on.toString()" :data-on="on.toString()"
                                aria-checked="{{ $trip->sms_reminders ? 'true' : 'false' }}" data-on="{{ $trip->sms_reminders ? 'true' : 'false' }}"
                                @click="on = ! on; $nextTick(() => $refs.auto.submit())" aria-label="{{ __('Also text the automatic reminders') }}"></button>
                        <span>
                            {{ __('After the trip ends Sofor reminds whoever still owes, in the app and by email.') }}
                            <span class="font-semibold text-ink">{{ __('Also text them') }}</span> {{ __('from your SMS wallet.') }}
                        </span>
                    </label>
                </form>
                <button type="button" @click="$dispatch('sofor-nudge', { all: true })"
                        class="flex shrink-0 items-center justify-center gap-1.5 rounded-xl bg-ink px-3 py-2 text-[11.5px] font-semibold text-canvas transition hover:bg-brand">
                    <x-icon name="bell" class="size-3.5" /> {{ __('Remind everyone') }}
                </button>
            </div>
        @endif
    @endif

    @if ($settlements->isNotEmpty())
        <div class="mt-5 border-t border-line pt-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">{{ __('Payment history') }}</p>
            <ul class="mt-3 flex flex-col gap-2">
                @foreach ($settlements as $settlement)
                    <li class="flex items-center gap-3 rounded-2xl border border-line bg-surface-2 px-3 py-2.5">
                        <span @class([
                            'flex size-8 shrink-0 items-center justify-center rounded-xl',
                            'bg-positive-tint text-positive' => ! $settlement->isPending(),
                            'bg-warning-tint text-warning' => $settlement->isPending(),
                        ])>
                            <x-icon :name="$settlement->isPending() ? 'clock' : 'wallet'" class="size-3.5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[12px] font-medium text-ink">
                                {{ __(':name paid :to', ['name' => $settlement->from?->name, 'to' => $settlement->to?->name]) }}
                            </p>
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-faint">
                                <span>{{ $settlement->methodEnum()->label() }}</span>
                                @if ($settlement->isPending())
                                    <span class="rounded-full bg-warning-tint px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-warning">{{ __('Awaiting :name', ['name' => $settlement->to?->name]) }}</span>
                                @endif
                                @if ($settlement->paidThroughGateway())
                                    <span class="shrink-0 rounded-full bg-pink-500/12 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-pink-500">
                                        {{ __(ucfirst((string) $settlement->gateway)) }}
                                    </span>
                                @endif
                                @if ($settlement->collection_id) <span>{{ __('Advance') }}</span> @endif
                                @if ($settlement->reference) - <span translate="no">{{ $settlement->reference }}</span> @endif
                                - {{ $settlement->settled_on?->translatedFormat('d M Y') }}
                                @if ($settlement->note) - {{ $settlement->note }} @endif
                            </p>
                        </div>
                        <p class="shrink-0 text-[13px] font-semibold text-ink">{{ \App\Support\Money::format($settlement->amount, $trip->currency) }}</p>

                        @if ($viewer?->can('deleteSettlement', [$trip, $settlement]))
                            <form method="POST" action="{{ route('trips.settlements.destroy', [$trip, $settlement]) }}"
                                  onsubmit="return confirm('Remove this payment? Balances will be recalculated for everybody.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="flex size-7 items-center justify-center rounded-lg text-faint transition hover:bg-negative-tint hover:text-negative" aria-label="{{ __('Remove payment') }}">
                                    <x-icon name="close" class="size-3.5" />
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-card>
