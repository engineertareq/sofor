@php
    $stats = $ledger['stats'];
    $me = $ledger['me'];
    $canRecord = $canRecord ?? false;
@endphp

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-stat :label="__('Total spent')" :value="\App\Support\Money::format($stats['spent'], $trip->currency)" icon="receipt" tone="brand"
            :hint="__(':count costs logged', ['count' => $stats['expense_count']])" />

    <x-stat :label="__('Per person')" :value="\App\Support\Money::format($stats['per_person'], $trip->currency)" icon="users" tone="accent"
            :hint="__(':count sharing the split', ['count' => $stats['participants']])" />

    <x-stat :label="__('Settled')" :value="\App\Support\Money::percent($ledger['progress']['settled'])" icon="check-circle" tone="lime"
            :hint="__(':settled of :total travellers clear', ['settled' => $ledger['progress']['settled_members'], 'total' => $ledger['progress']['total_members']])" />

    @if ($me && $me['is_member'])
        @if ($me['balance'] < -0.009)
            <x-stat :label="__('Your due')" :value="\App\Support\Money::format($me['due'], $trip->currency)" icon="trending-down" tone="rose"
                    :hint="__('Pay the leader or whoever covered you')" />
        @elseif ($me['balance'] > 0.009)
            <x-stat :label="__('Owed to you')" :value="\App\Support\Money::format($me['credit'], $trip->currency)" icon="trending-up" tone="lime"
                    :hint="__('You covered more than your share')" />
        @else
            <x-stat :label="__('Your balance')" value="Settled" icon="check-circle" tone="slate"
                    :hint="__('Your share is :amount', ['amount' => \App\Support\Money::format($me['share'], $trip->currency)])" />
        @endif
    @else
        <x-stat :label="__('Travellers')" :value="$stats['travellers']" icon="compass" tone="violet" :hint="__('People in this trip')" />
    @endif
</div>

@if ($me && $me['is_member'] && ($me['due'] ?? 0) > 0.009)
    @php $next = $me['next_payment']; @endphp
    <div class="mt-3 rounded-4xl border border-brand/30 bg-brand/8 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand/15 text-brand">
                    <x-icon name="wallet" class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-[13px] font-semibold text-ink">
                        {{ __('Your share still open: :amount', ['amount' => \App\Support\Money::format($me['due'], $trip->currency)]) }}
                    </p>
                    <p class="text-[11px] text-muted">
                        @if ($next)
                            {{ __('Pay :name :amount directly, then tap "I paid" so they can confirm.', ['name' => $next['to']->name, 'amount' => \App\Support\Money::format($next['amount'], $trip->currency)]) }}
                        @else
                            {{ __('Pay whoever covered you, then record it here.') }}
                        @endif
                    </p>
                </div>
            </div>

            @if ($canRecord)
                <x-btn type="button" size="sm" icon="check"
                       @click="$dispatch('sofor-settle', {
                           from: {{ $me['member']->id }},
                           to: {{ $next['to_id'] ?? 'null' }},
                           amount: {{ \Illuminate\Support\Js::from((string) ($next['amount'] ?? $me['due'])) }}
                       })">
                    {{ __('I paid :to', ['to' => $next ? $next['to']->name : '']) }}
                </x-btn>
            @endif
        </div>

        @if ($next)
            <x-payout-chips :member="$next['to']" class="mt-3" />
        @endif
    </div>
@endif

@foreach (($pots ?? collect())->filter(fn ($pot) => $pot['collection']->isOpen() && ($pot['mine']['left'] ?? 0) > 0.009) as $pot)
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-4xl border border-accent/40 bg-accent/10 p-4">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-accent/25 text-ink">
                <x-icon name="coins" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="text-[13px] font-semibold text-ink">
                    {{ __(':collection: send :amount to :collector', ['collection' => $pot['collection']->title, 'amount' => \App\Support\Money::format($pot['mine']['left'], $trip->currency), 'collector' => $pot['collector']->name]) }}
                </p>
                <p class="text-[11px] text-muted">
                    {{ $pot['collection']->due_on
                        ? __('Due :date - :paid of :count have paid in', ['date' => $pot['collection']->due_on->translatedFormat('D j M'), 'paid' => $pot['paid_count'], 'count' => $pot['rows']->count()])
                        : __(':paid of :count have paid in', ['paid' => $pot['paid_count'], 'count' => $pot['rows']->count()]) }}
                </p>
            </div>
        </div>
        <x-btn type="button" size="sm" icon="check"
               @click="$dispatch('sofor-settle', {
                   from: {{ $pot['mine']['member']->id }},
                   to: {{ $pot['collector']->id }},
                   amount: {{ \Illuminate\Support\Js::from((string) $pot['mine']['left']) }},
                   collection: {{ $pot['collection']->id }},
                   collectionTitle: {{ \Illuminate\Support\Js::from($pot['collection']->title) }}
               })">
            {{ __('I paid :first_name', ['first_name' => $pot['collector']->firstName()]) }}
        </x-btn>
    </div>
@endforeach

@if (($currentMember ?? null) && $currentMember->isTravelling() && $currentMember->rsvp === null && $trip->isActive() && (! $trip->starts_on || ! $trip->starts_on->isPast()))
    <form method="POST" action="{{ route('trips.rsvp', $trip) }}"
          class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-4xl border border-accent-2/30 bg-accent-2/8 p-4">
        @csrf
        @method('PUT')
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-accent-2/15"><x-glyph name="backpack" class="size-7" /></span>
            <div class="min-w-0">
                <p class="text-[13px] font-semibold text-ink">{{ __('Are you coming to :title?', ['title' => $trip->title]) }}</p>
                <p class="text-[11px] text-muted">{{ __('Let :name know so they can book for the right number of people.', ['name' => $trip->owner?->name ?? __('the leader')]) }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-1.5">
            @foreach (\App\Enums\Rsvp::cases() as $case)
                <button type="submit" name="rsvp" value="{{ $case->value }}"
                        class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5 text-[12px] font-semibold text-ink transition hover:border-line-strong">
                    <x-glyph :name="$case->icon()" class="size-4" /> {{ $case->label() }}
                </button>
            @endforeach
        </div>
    </form>
@endif

@if ($trip->isEnded() && $ledger['stats']['expense_count'] > 0)
    <a href="{{ route('trips.recap', $trip) }}"
       class="mt-3 flex items-center justify-between gap-3 rounded-4xl border-[1.5px] border-ink bg-accent/25 p-4 shadow-sticker-sm transition hover:-translate-y-0.5">
        <span class="flex min-w-0 items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-surface hairline"><x-glyph :name="$trip->icon" class="size-7" /></span>
            <span class="min-w-0">
                <span class="block text-[13px] font-semibold text-ink">{{ __('That was a trip. Share the recap card') }}</span>
                <span class="block text-[11px] text-muted">{{ __('What it cost, per person and per day - no names, ready for Instagram.') }}</span>
            </span>
        </span>
        <x-icon name="arrow-right" class="size-4 shrink-0 text-ink" />
    </a>
@endif

@include('trips.partials.budget')
