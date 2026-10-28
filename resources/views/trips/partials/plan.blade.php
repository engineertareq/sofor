{{--
    Deciding together: who is coming, and quick polls ("Friday or
    Saturday?"). Any traveller can post a poll and vote.
--}}
@php
    $polls = $polls ?? collect();
    $canManage = $canManage ?? false;
    $me = $currentMember ?? null;
    $canTakePart = $me !== null && $me->isTravelling() && $trip->isActive();
    $travellers = $ledger['members']->filter(fn ($m) => $m->isTravelling())->values();
    $answers = collect(\App\Enums\Rsvp::cases())->mapWithKeys(fn ($case) => [
        $case->value => $travellers->filter(fn ($m) => $m->rsvp === $case)->values(),
    ]);
    $unanswered = $travellers->filter(fn ($m) => $m->rsvp === null)->values();
    $rsvpTone = [
        'going' => 'bg-positive-tint text-positive border-positive/30',
        'maybe' => 'bg-warning-tint text-warning border-warning/30',
        'not_going' => 'bg-negative-tint text-negative border-negative/30',
    ];
@endphp

<x-card padding="p-4 sm:p-5">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-accent-2/15 text-accent-2">
                <x-icon name="check-circle" class="size-4.5" />
            </span>
            <div class="min-w-0">
                <h2 class="truncate font-display text-sm font-semibold">{{ __('Plan together') }}</h2>
                <p class="text-[11px] text-faint">{{ __('Who is coming, and what the group decides') }}</p>
            </div>
        </div>

        @if ($canTakePart)
            <x-btn type="button" size="sm" variant="secondary" icon="plus" @click="$dispatch('sofor-poll')">{{ __('New poll') }}</x-btn>
        @endif
    </div>

    {{-- ===== Who is coming ===== --}}
    <div class="mt-4 rounded-3xl border border-line bg-surface-2 p-3.5">
        <div class="flex flex-wrap items-center gap-2 text-[11px]">
            @foreach (\App\Enums\Rsvp::cases() as $case)
                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-semibold {{ $rsvpTone[$case->value] }}">
                    <x-glyph :name="$case->icon()" class="size-4" /> {{ $answers[$case->value]->count() }} {{ mb_strtolower($case->label()) }}
                </span>
            @endforeach
            @if ($unanswered->isNotEmpty())
                <span class="rounded-full border border-line bg-surface px-2.5 py-1 font-semibold text-muted">{{ __(':count not answered', ['count' => $unanswered->count()]) }}</span>
            @endif
        </div>

        @if ($canTakePart)
            <form method="POST" action="{{ route('trips.rsvp', $trip) }}" class="mt-3">
                @csrf
                @method('PUT')
                <p class="mb-2 text-[12px] font-semibold text-ink">{{ $me->rsvp ? __('Your answer') : __('Are you coming?') }}</p>
                <div class="grid grid-cols-3 gap-1.5" role="group" aria-label="{{ __('Are you coming?') }}">
                    @foreach (\App\Enums\Rsvp::cases() as $case)
                        <button type="submit" name="rsvp" value="{{ $case->value }}" aria-pressed="{{ $me->rsvp === $case ? 'true' : 'false' }}"
                                @class([
                                    'flex flex-col items-center gap-1 rounded-2xl border px-2 py-2 text-[12px] font-semibold transition',
                                    $rsvpTone[$case->value].' ring-2 ring-offset-1 ring-offset-surface-2 ring-current' => $me->rsvp === $case,
                                    'border-line bg-surface text-muted hover:text-ink' => $me->rsvp !== $case,
                                ])>
                            <x-glyph :name="$case->icon()" class="size-6" /> {{ $case->label() }}
                        </button>
                    @endforeach
                </div>
            </form>
        @endif

        <ul class="mt-3 flex flex-col gap-1">
            @foreach ($travellers as $traveller)
                <li class="flex items-center justify-between gap-2 rounded-xl px-1 py-1">
                    <span class="flex min-w-0 items-center gap-2">
                        <x-avatar :name="$traveller->name" :src="$traveller->avatarUrl()" size="xs" />
                        <span class="truncate text-[12px] text-ink">{{ $traveller->name }}</span>
                    </span>

                    @if ($canManage && $trip->isActive() && $traveller->user_id === null)
                        {{-- No account: the leader answers for them. --}}
                        <form method="POST" action="{{ route('trips.rsvp', $trip) }}" class="flex gap-1">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="member_id" value="{{ $traveller->id }}">
                            @foreach (\App\Enums\Rsvp::cases() as $case)
                                <button type="submit" name="rsvp" value="{{ $case->value }}" title="{{ $case->label() }}"
                                        aria-label="{{ __('Mark :name as :answer', ['name' => $traveller->name, 'answer' => mb_strtolower($case->label())]) }}"
                                        @class([
                                            'flex size-7 items-center justify-center rounded-lg border text-[12px] transition',
                                            $rsvpTone[$case->value] => $traveller->rsvp === $case,
                                            'border-line bg-surface opacity-60 hover:opacity-100' => $traveller->rsvp !== $case,
                                        ])><x-glyph :name="$case->icon()" class="size-4.5" /></button>
                            @endforeach
                        </form>
                    @elseif ($traveller->rsvp)
                        <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $rsvpTone[$traveller->rsvp->value] }}">{{ $traveller->rsvp->label() }}</span>
                    @else
                        <span class="shrink-0 text-[10px] text-faint">{{ __('No answer yet') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    {{-- ===== Checkpoint list ===== --}}
    @php
        $listed = \App\Models\TravellerDetail::query()->whereIn('trip_member_id', $travellers->pluck('id'))->count();
        $iAmListed = $me && $listed > 0 && \App\Models\TravellerDetail::query()->where('trip_member_id', $me->id)->exists();
    @endphp
    <a href="{{ route('trips.manifest', $trip) }}"
       class="mt-3 flex items-center justify-between gap-3 rounded-3xl border border-line bg-surface-2 p-3.5 transition hover:border-line-strong">
        <span class="flex min-w-0 items-center gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-brand-tint text-brand">
                <x-icon name="shield" class="size-4.5" />
            </span>
            <span class="min-w-0">
                <span class="block text-[13px] font-semibold text-ink">{{ __('Checkpoint list') }}</span>
                <span class="block truncate text-[11px] text-muted">
                    {{ __(':listed of :total ready', ['listed' => $listed, 'total' => $travellers->count()]) }}
                    @if ($me && ! $iAmListed && $trip->isActive())
                        &middot; <span class="font-semibold text-brand">{{ __('add your ID details') }}</span>
                    @endif
                </span>
            </span>
        </span>
        <x-icon name="arrow-right" class="size-4 shrink-0 text-faint" />
    </a>

    {{-- ===== Polls ===== --}}
    @if ($polls->isEmpty())
        @if ($canTakePart)
            <p class="mt-4 rounded-3xl border border-dashed border-line-strong p-4 text-[12px] leading-relaxed text-muted">
                {{ __('Friday or Saturday? Bus or train? Cottage or hotel? Ask the group and settle it in one tap instead of a hundred messages.') }}
            </p>
        @endif
    @else
        <div class="mt-4 flex flex-col gap-3">
            @foreach ($polls as $poll)
                @php
                    $open = $poll->isOpen() && $trip->isActive();
                    $voters = $poll->options->flatMap->votes->pluck('trip_member_id')->unique();
                    $totalVotes = $poll->options->sum(fn ($option) => $option->votes->count());
                    $top = $poll->options->max(fn ($option) => $option->votes->count());
                    $mine = $me ? $poll->options->filter(fn ($option) => $option->votes->contains('trip_member_id', $me->id))->pluck('id') : collect();
                    $canControl = auth()->id() === $poll->created_by || $canManage;
                @endphp

                <div class="rounded-3xl border border-line bg-surface-2 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[13px] font-semibold text-ink">{{ $poll->question }}</p>
                            <p class="mt-0.5 text-[10px] text-faint">
                                {{ $poll->author?->name ?? __('A traveller') }}
                                &middot; {{ __(':voted of :total voted', ['voted' => $voters->count(), 'total' => $travellers->count()]) }}
                                @if ($poll->multiple) &middot; {{ __('pick any') }} @endif
                                @if (! $open)
                                    &middot; <span class="font-semibold">{{ __('closed') }}</span>
                                @elseif ($poll->closes_at)
                                    &middot; {{ __('closes :time', ['time' => $poll->closes_at->diffForHumans()]) }}
                                @endif
                            </p>
                        </div>

                        @if ($canControl && $trip->isActive())
                            <div class="flex shrink-0 items-center gap-1">
                                @if ($poll->closed_at === null)
                                    <form method="POST" action="{{ route('trips.polls.close', [$trip, $poll]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg px-2 py-1 text-[10px] font-semibold text-muted transition hover:bg-surface hover:text-ink">{{ __('Close') }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('trips.polls.destroy', [$trip, $poll]) }}" onsubmit="return confirm('Remove this poll and its votes?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex size-6 items-center justify-center rounded-lg text-faint transition hover:bg-negative-tint hover:text-negative" aria-label="{{ __('Remove poll') }}">
                                        <x-icon name="trash" class="size-3" />
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>

                    <ul class="mt-3 flex flex-col gap-1.5">
                        @foreach ($poll->options as $option)
                            @php
                                $count = $option->votes->count();
                                $percent = $totalVotes > 0 ? round($count / max(1, $poll->multiple ? $voters->count() : $totalVotes) * 100) : 0;
                                $chosen = $mine->contains($option->id);
                                $leading = ! $open && $count > 0 && $count === $top;
                            @endphp
                            <li>
                                <form method="POST" action="{{ route('trips.polls.vote', [$trip, $poll]) }}">
                                    @csrf
                                    <input type="hidden" name="option_id" value="{{ $option->id }}">
                                    <button type="submit" @disabled(! $open || ! $canTakePart) aria-pressed="{{ $chosen ? 'true' : 'false' }}"
                                            @class([
                                                'relative w-full overflow-hidden rounded-2xl border px-3 py-2 text-left transition',
                                                'border-brand/50 bg-surface' => $chosen,
                                                'border-line bg-surface hover:border-line-strong' => ! $chosen,
                                                'cursor-default' => ! $open || ! $canTakePart,
                                            ])>
                                        <span class="absolute inset-y-0 left-0 {{ $chosen || $leading ? 'bg-brand/14' : 'bg-surface-2' }} transition-[width] duration-500" style="width: {{ $percent }}%" aria-hidden="true"></span>
                                        <span class="relative flex items-center justify-between gap-3">
                                            <span class="flex min-w-0 items-center gap-2 text-[12.5px] font-medium text-ink">
                                                <span @class([
                                                    'flex size-4 shrink-0 items-center justify-center border-[1.5px]',
                                                    'rounded-md' => $poll->multiple,
                                                    'rounded-full' => ! $poll->multiple,
                                                    'border-brand bg-brand text-white' => $chosen,
                                                    'border-line-strong' => ! $chosen,
                                                ])>
                                                    @if ($chosen)<x-icon name="check" class="size-2.5" />@endif
                                                </span>
                                                <span class="truncate">{{ $option->label }}</span>
                                                @if ($leading) <span class="text-[10px] font-bold uppercase tracking-wide text-brand">{{ __('Picked') }}</span> @endif
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                <span class="flex -space-x-1.5">
                                                    @foreach ($option->votes->take(4) as $vote)
                                                        <x-avatar :name="$vote->member?->name ?? '?'" :src="$vote->member?->avatarUrl()" size="xs" class="ring-2 ring-surface" title="{{ $vote->member?->name }}" />
                                                    @endforeach
                                                </span>
                                                <span class="num text-[11px] font-semibold text-muted">{{ $count }}</span>
                                            </span>
                                        </span>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</x-card>

@include('trips.partials.safety')
