{{--
    The cost timeline (App\Services\TripTimeline): a night-sky hero with the
    total and where the money went, then the trip day by day along Sofor's
    dotted route - it fills with marigold as you scroll, each cost hangs off it
    as its category picture, each day is a pin with its total and the running
    total so far. A day navigator and category filters stay at hand.

    Used by the travellers' page (trips/timeline) and the public link
    (timeline/public). $public hides receipts; $share is a view drawn above the
    navigator (the leader's link controls), or null.
--}}
@php
    use App\Support\Bangla;
    use App\Support\Money;

    $money = fn ($value) => Money::format((float) $value, $trip->currency);
    $catVar = fn (\App\Enums\ExpenseCategory $category) => 'var(--color-cat-'.match ($category->value) {
        'accommodation' => 'stay', 'transport' => 'travel', 'activities' => 'fun', 'shopping' => 'shop', default => $category->value,
    }.')';
    $clock = fn (\Carbon\CarbonInterface $at) => Bangla::active()
        ? Bangla::digits($at->copy()->locale('bn')->isoFormat('A h:mm'))
        : $at->format('g:i a');
    $dayTitle = fn (array $day) => match (true) {
        $day['kind'] === 'before' => __('Before the trip'),
        $day['kind'] === 'after' => __('After the trip'),
        $day['number'] !== null => __('Day :number', ['number' => $day['number']]),
        default => $day['date']->translatedFormat('D, j M'),
    };
    $dates = $trip->starts_on
        ? $trip->starts_on->translatedFormat('j M').($trip->ends_on && ! $trip->ends_on->isSameDay($trip->starts_on) ? ' - '.$trip->ends_on->translatedFormat('j M Y') : ' '.$trip->starts_on->translatedFormat('Y'))
        : null;
    $dayCats = collect($timeline['timeline'])->mapWithKeys(fn ($day) => [$day['key'] => array_keys($day['mix'])])->all();
@endphp

<div class="trip-timeline" x-data="tripTimeline({{ \Illuminate\Support\Js::from(['days' => $dayCats, 'marks' => $timeline['map']['marks'] ?? []]) }})">

    {{-- ===================== The trip, in one slim card =====================
         Its own cover colour behind its picture, the facts on one line, the
         total on the right, and where the money went as the card's bottom
         edge (the category filters below carry the matching colours). --}}
    <div @class(['mx-auto max-w-6xl px-4 pt-6 sm:px-6 sm:pt-8 lg:px-8' => $public])>
        <section class="relative overflow-hidden rounded-[1.5rem] border border-line bg-surface shadow-soft">
            <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:gap-6 sm:p-5 sm:pr-6">
                <div class="flex min-w-0 flex-1 items-center gap-3.5 sm:gap-4">
                    <span class="flex size-13 shrink-0 items-center justify-center rounded-2xl ring-1 ring-black/5 sm:size-15" style="background: {{ $trip->coverStyle() }}">
                        <x-glyph :name="$trip->icon" class="size-8 sm:size-9" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11.5px] font-semibold text-brand">{{ __('Trip timeline') }}</p>
                        <h1 class="mt-0.5 font-display text-[1.35rem] font-semibold leading-tight tracking-tight text-ink sm:text-[1.6rem]">{{ $trip->title }}</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[12.5px] text-muted">
                            @foreach (array_values(array_filter([
                                $trip->destination,
                                $dates,
                                $timeline['days'] > 0 ? trans_choice(':count day|:count days', $timeline['days'], ['count' => Bangla::localize($timeline['days'])]) : null,
                                trans_choice(':count traveller|:count travellers', $timeline['travellers'], ['count' => Bangla::localize($timeline['travellers'])]),
                                trans_choice(':count cost|:count costs', $timeline['costs'], ['count' => Bangla::localize($timeline['costs'])]),
                            ])) as $fact)
                                @unless ($loop->first)<span aria-hidden="true" class="text-line-strong">&middot;</span>@endunless
                                <span class="whitespace-nowrap">{{ $fact }}</span>
                            @endforeach
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-end justify-between gap-4 border-t border-line pt-3 sm:block sm:border-0 sm:pt-0 sm:text-right">
                    <div>
                        <p class="text-[11.5px] font-semibold text-muted">{{ __('Spent together') }}</p>
                        <p class="mt-0.5 font-display text-[1.75rem] font-semibold leading-none tracking-tight text-ink sm:text-[2rem]">{{ $money($timeline['total']) }}</p>
                    </div>
                    @if ($timeline['per_person'] !== null && $timeline['total'] > 0)
                        <p class="text-[12.5px] text-muted sm:mt-1.5">{{ __(':amount each', ['amount' => $money($timeline['per_person'])]) }}</p>
                    @endif
                </div>
            </div>

            @if ($timeline['categories'] !== [])
                {{-- Where it went: the card's bottom edge --}}
                <div class="flex h-1.5" role="img" aria-label="{{ __('Where the money went') }}">
                    @foreach ($timeline['categories'] as $row)
                        <span class="tl-grow h-full" title="{{ $row['category']->label() }} · {{ Money::percent($row['percent']) }} · {{ $money($row['total']) }}"
                              style="width: {{ $row['percent'] }}%; background: {{ $catVar($row['category']) }}; --tl-delay: {{ $loop->index * 90 }}ms"></span>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    @if ($timeline['timeline'] === [])
        <div @class(['mt-8', 'mx-auto max-w-6xl px-4 sm:px-6 lg:px-8' => $public])>
            <x-empty icon="receipt" :title="__('No costs yet')" :text="__('The timeline fills in as the group adds costs: every bus fare, cottage and meal, day by day.')" />
        </div>
    @else
        <div @class(['mt-8 grid gap-8 lg:grid-cols-[17rem_minmax(0,1fr)] lg:items-start', 'mx-auto max-w-6xl px-4 pb-6 sm:px-6 lg:px-8' => $public])>

            {{-- ===================== At hand: the link, the days, the filters ===================== --}}
            {{-- On phones only the map and the link controls show here; the rest is desktop. --}}
            <aside @class([
                'min-w-0 flex-col gap-4 lg:sticky lg:top-24 lg:flex lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto no-scrollbar',
                'flex' => $share || $timeline['map'],
                'hidden' => ! $share && ! $timeline['map'],
            ])>
                @if ($timeline['map'])
                    @include('trips.partials.timeline-map')
                @endif

                @if ($share)
                    @include($share)
                @endif

                <nav @class(['hidden rounded-[1.6rem] border border-line bg-surface p-3 shadow-soft', 'lg:block' => ! $timeline['map']]) aria-label="{{ __('Days') }}">
                    <p class="px-2 pb-2 pt-1 font-mono text-[10.5px] font-bold tracking-[0.14em] text-faint">{{ __('DAYS') }}</p>
                    <ol class="flex max-h-[42vh] flex-col gap-0.5 overflow-y-auto">
                        @foreach ($timeline['timeline'] as $day)
                            <li>
                                <button type="button" @click="go('{{ $day['key'] }}')" x-show="dayVisible('{{ $day['key'] }}')"
                                        :class="active === '{{ $day['key'] }}' ? 'bg-ink text-canvas' : 'text-ink hover:bg-surface-2'"
                                        class="flex w-full items-center gap-3 rounded-2xl px-2.5 py-2 text-left transition">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[13px] font-semibold">{{ $dayTitle($day) }}</span>
                                        <span class="block truncate text-[11px] opacity-60">{{ $day['date']->translatedFormat('D, j M') }}</span>
                                    </span>
                                    <span class="shrink-0 text-[12px] font-semibold">{{ $money($day['total']) }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </nav>

                <div class="hidden rounded-[1.6rem] border border-line bg-surface p-4 shadow-soft lg:block">
                    <p class="font-mono text-[10.5px] font-bold tracking-[0.14em] text-faint">{{ __('BIGGEST COSTS') }}</p>
                    <ol class="mt-3 flex flex-col gap-2.5">
                        @foreach ($timeline['biggest'] as $index => $item)
                            <li>
                                <button type="button" @click="go('{{ $item['date']->toDateString() }}', {{ $item['id'] }})" class="group flex w-full items-center gap-3 text-left">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-accent-tint font-display text-[12px] font-semibold text-accent-2">{{ $index + 1 }}</span>
                                    <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-ink group-hover:text-brand">{{ $item['title'] }}</span>
                                    <span class="shrink-0 text-[12.5px] font-semibold text-ink">{{ $money($item['amount']) }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </aside>

            <div class="min-w-0">
                {{-- Phones and tablets: the days in a strip that stays in reach --}}
                <nav class="no-scrollbar sticky top-[4.25rem] z-20 -mx-3 mb-4 flex gap-2 overflow-x-auto bg-canvas/90 px-3 py-2 backdrop-blur sm:-mx-6 sm:px-6 lg:hidden" aria-label="{{ __('Days') }}" x-ref="strip">
                    @foreach ($timeline['timeline'] as $day)
                        <button type="button" @click="go('{{ $day['key'] }}')" x-show="dayVisible('{{ $day['key'] }}')" data-strip="{{ $day['key'] }}"
                                :class="active === '{{ $day['key'] }}' ? 'border-ink bg-ink text-canvas' : 'border-line bg-surface text-ink'"
                                class="shrink-0 rounded-full border px-3.5 py-1.5 text-[12.5px] font-semibold transition">
                            {{ $dayTitle($day) }}
                        </button>
                    @endforeach
                </nav>

                {{-- Filter by what it was spent on --}}
                <div class="no-scrollbar -mx-3 mb-6 flex gap-2 overflow-x-auto px-3 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0" role="group" aria-label="{{ __('Show costs for') }}">
                    <button type="button" @click="filter = 'all'" :aria-pressed="(filter === 'all').toString()"
                            :class="filter === 'all' ? 'border-ink bg-ink text-canvas' : 'border-line bg-surface text-muted hover:text-ink'"
                            class="shrink-0 rounded-full border px-3.5 py-1.5 text-[12.5px] font-semibold transition">
                        {{ __('Everything') }} <span class="opacity-60">{{ Bangla::localize($timeline['costs']) }}</span>
                    </button>
                    @foreach ($timeline['categories'] as $row)
                        <button type="button" @click="filter = '{{ $row['category']->value }}'" :aria-pressed="(filter === '{{ $row['category']->value }}').toString()"
                                :class="filter === '{{ $row['category']->value }}' ? 'border-ink bg-ink text-canvas' : 'border-line bg-surface text-muted hover:text-ink'"
                                class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-[12.5px] font-semibold transition">
                            <span class="size-2 shrink-0 rounded-full" style="background: {{ $catVar($row['category']) }}"></span>
                            {{ $row['category']->label() }} <span class="opacity-60">{{ Money::percent($row['percent']) }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- ===================== The road ===================== --}}
                <div class="tl relative" x-ref="track" style="--tl-progress: 0%">
                    <div aria-hidden="true" class="tl-track"><div class="tl-fill"></div></div>

                    {{-- Setting off --}}
                    <div class="tl-row">
                        <span class="tl-pin tl-pin-start"><x-icon name="flag" class="size-4" /></span>
                        <p class="pt-1.5 text-[13px] text-muted">
                            <span class="font-semibold text-ink">{{ __('The trip begins') }}</span>
                            @if ($trip->starts_on)
                                · {{ $trip->starts_on->translatedFormat('D, j M Y') }}
                            @endif
                        </p>
                    </div>

                    @foreach ($timeline['timeline'] as $day)
                        <section class="tl-day scroll-mt-32" id="tl-{{ $day['key'] }}" data-day="{{ $day['key'] }}" x-show="dayVisible('{{ $day['key'] }}')">
                            {{-- The day: a pin on the road, its total, and a bar of the whole trip's
                                 spending - grey for the days before, this day in its colours. --}}
                            @php $before = $timeline['total'] > 0 ? ($day['cumulative'] - $day['total']) / $timeline['total'] * 100 : 0; @endphp
                            <header class="tl-row tl-day-head">
                                <span @class(['tl-pin tl-pin-day', 'is-aside' => $day['kind'] !== 'day'])>
                                    @if ($day['number'] !== null && $day['kind'] === 'day')
                                        <span class="font-display text-[15px] font-semibold leading-none">{{ Bangla::localize($day['number']) }}</span>
                                    @else
                                        <x-icon :name="$day['kind'] === 'before' ? 'calendar' : 'check'" class="size-4" />
                                    @endif
                                </span>
                                <div class="min-w-0 flex-1 pt-0.5">
                                    <div class="flex items-baseline justify-between gap-4">
                                        <h2 class="min-w-0 font-display text-[1.35rem] font-semibold leading-tight text-ink">
                                            {{ $dayTitle($day) }}
                                            <span class="ml-1 whitespace-nowrap font-sans text-[12.5px] font-medium text-muted">{{ $day['date']->translatedFormat('D, j M') }}</span>
                                        </h2>
                                        <p class="shrink-0 font-display text-[1.3rem] font-semibold leading-tight text-ink">{{ $money($day['total']) }}</p>
                                    </div>
                                    <div class="mt-2.5 flex h-2 overflow-hidden rounded-full bg-line/55" role="img"
                                         aria-label="{{ __(':percent of the trip', ['percent' => Money::percent($day['share'])]) }}">
                                        <span class="h-full bg-ink/15" style="width: {{ round($before, 2) }}%"></span>
                                        @foreach ($day['mix'] as $value => $percent)
                                            <span class="h-full" style="width: {{ round($percent * $day['total'] / max($timeline['total'], 0.01), 2) }}%; background: {{ $catVar(\App\Enums\ExpenseCategory::from($value)) }}"></span>
                                        @endforeach
                                    </div>
                                    <div class="mt-1.5 flex justify-between gap-3 text-[11.5px] font-medium text-muted">
                                        <span>{{ __(':amount so far', ['amount' => $money($day['cumulative'])]) }}</span>
                                        <span>{{ __(':percent of the trip', ['percent' => Money::percent($day['share'])]) }}</span>
                                    </div>
                                </div>
                            </header>

                            <ol class="tl-items">
                                @foreach ($day['items'] as $item)
                                    <li class="tl-row tl-item" id="tl-cost-{{ $item['id'] }}" data-cat="{{ $item['category']->value }}" style="--cat: {{ $catVar($item['category']) }}"
                                        x-show="filter === 'all' || filter === '{{ $item['category']->value }}'" x-transition.opacity.duration.200ms>
                                        <span class="tl-node"><x-glyph :name="$item['category']->icon()" class="size-6" /></span>

                                        <article class="tl-card group min-w-0 flex-1">
                                            <div class="flex items-start gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <p class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11.5px] font-semibold">
                                                        <span class="tl-cat">{{ $item['category']->label() }}</span>
                                                        @if ($item['time'])
                                                            <span class="inline-flex items-center gap-1 text-muted"><x-icon name="clock" class="size-3.5" /> {{ $clock($item['time']) }}</span>
                                                        @endif
                                                        @if ($item['big'])
                                                            <span class="inline-flex items-center gap-1 rounded-full bg-accent px-2 py-0.5 text-[10.5px] font-bold text-ink"><x-icon name="flame" class="size-3" /> {{ __('Big one') }}</span>
                                                        @endif
                                                    </p>
                                                    <h3 class="mt-1.5 text-[15.5px] font-semibold leading-snug text-ink">{{ $item['title'] }}</h3>
                                                </div>
                                                <div class="shrink-0 text-right">
                                                    <p class="font-display text-[1.35rem] font-semibold leading-none tracking-tight text-ink">{{ $money($item['amount']) }}</p>
                                                    @if ($item['each'] !== null && $item['shares'] > 1)
                                                        <p class="mt-1 text-[11.5px] font-medium text-muted">{{ __(':amount each', ['amount' => $money($item['each'])]) }}</p>
                                                    @endif
                                                </div>
                                            </div>

                                            @if ($item['notes'])
                                                <p class="tl-note mt-2.5">{{ $item['notes'] }}</p>
                                            @endif

                                            <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11.5px] font-medium text-muted">
                                                @if ($item['payer'])
                                                    <span class="tl-tag pl-0.5">
                                                        <x-avatar :name="$item['payer']['name']" :gradient="$item['payer']['gradient']" :src="$item['payer']['avatar']" size="xs" :ring="false" class="size-5.5! rounded-full! text-[9px]!" />
                                                        {{ __(':name paid', ['name' => $item['payer']['name']]) }}
                                                    </span>
                                                @endif
                                                @if ($item['shares'] > 0)
                                                    <span class="tl-tag">
                                                        <x-icon name="split" class="size-3.5" />
                                                        {{ $item['each'] !== null
                                                            ? trans_choice('Split :count way|Split :count ways', $item['shares'], ['count' => Bangla::localize($item['shares'])])
                                                            : trans_choice('Custom split between :count|Custom split between :count', $item['shares'], ['count' => Bangla::localize($item['shares'])]) }}
                                                    </span>
                                                @endif
                                                @if ($item['items'] > 0)
                                                    <span class="tl-tag"><x-icon name="list" class="size-3.5" /> {{ trans_choice(':count item|:count items', $item['items'], ['count' => Bangla::localize($item['items'])]) }}</span>
                                                @endif
                                                @if ($item['receipt'])
                                                    <a href="{{ $item['receipt'] }}" target="_blank" rel="noopener" class="tl-tag text-brand hover:bg-brand-tint"><x-icon name="camera" class="size-3.5" /> {{ __('Receipt') }}</a>
                                                @endif
                                            </div>
                                        </article>
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endforeach

                    {{-- The end of the road --}}
                    <div class="tl-row">
                        <span class="tl-pin tl-pin-end"><x-glyph name="settled" class="size-7" /></span>
                        <div class="min-w-0 flex-1 rounded-[1.6rem] bg-ink p-5 text-canvas sm:p-6">
                            <p class="font-mono text-[10.5px] font-bold tracking-[0.14em] text-[#f6b23a]">{{ __('THE WHOLE TRIP') }}</p>
                            <p class="mt-1.5 font-display text-[1.9rem] font-semibold leading-tight">{{ $money($timeline['total']) }}</p>
                            <p class="mt-1 text-[13px] text-canvas/70">
                                {{ trans_choice(':count cost|:count costs', $timeline['costs'], ['count' => Bangla::localize($timeline['costs'])]) }}@if ($timeline['per_person'] !== null) · {{ __(':amount each', ['amount' => $money($timeline['per_person'])]) }}@endif
                            </p>
                            @if ($public)
                                {{-- Stacked on phones, so the sentence gets the full width; side by side from sm. --}}
                                <div class="mt-5 flex flex-col gap-3.5 border-t border-canvas/15 pt-5 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                                    <p class="text-pretty text-[13.5px] leading-relaxed text-canvas/75 sm:min-w-0 sm:flex-1 sm:basis-56 sm:text-[13px]">{{ __('Kept with Sofor: the free way to split trip costs fairly.') }}</p>
                                    <a href="{{ route('home') }}" class="inline-flex h-11 w-full shrink-0 items-center justify-center gap-1.5 rounded-full bg-[#f6b23a] px-4 text-[13.5px] font-semibold text-ink transition hover:bg-[#ffc65a] sm:h-10 sm:w-auto sm:text-[13px]">
                                        {{ __('Plan your trip free') }} <x-icon name="arrow-right" class="size-3.5" />
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
