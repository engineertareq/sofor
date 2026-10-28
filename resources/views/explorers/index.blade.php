@php
    use App\Support\Bangla;
    use App\Support\ExplorerLevel;
    use App\Support\Glyphs;

    $unit = fn (int $count) => $map->unit($count);
    $total = $summary['total'];
    $percent = fn (int $visited) => $total > 0 ? (int) floor($visited * 100 / $total) : 0;
    $level = fn (int $visited) => ExplorerLevel::for($visited, $total);
    $isWorld = $map->key === config('travel_maps.world');
    $place = $isWorld ? __('the world') : $map->label();
    $mine = auth()->id();

    // Gold, silver, bronze: the ribbon, the avatar ring and the progress ring.
    $medals = [
        1 => ['label' => __('Gold'), 'color' => '#f2a71b', 'tint' => '#fdf1d7'],
        2 => ['label' => __('Silver'), 'color' => '#9aa7b4', 'tint' => '#eef1f4'],
        3 => ['label' => __('Bronze'), 'color' => '#c9773f', 'tint' => '#f8e6d8'],
    ];

    $topAreas = collect($popularity)->take(5);
    $mostPopular = max([1, ...array_values($popularity)]);
    $heat = collect($popularity)->map(fn (int $count) => $count / $mostPopular)->all();
    // The label beside each place on the map: how many explorers have been there.
    $heatTips = collect($map->areas())
        ->map(fn (array $area, string $id) => isset($popularity[$id])
            ? trans_choice(':count explorer|:count explorers', $popularity[$id], ['count' => Bangla::localize($popularity[$id])])
            : __('No explorers yet'))
        ->all();
    $hotspot = $topAreas->keys()->first();
    $waiting = collect($unexplored)->shuffle(crc32($map->key.now()->toDateString()))->take(8);

    $tabUrl = fn (string $key) => route('explorers', array_filter(['map' => $key, 'q' => $search !== '' ? $search : null]));

    // Each board is its own page for search engines; a search within it is not.
    $canonical = $map->key === collect($tabs)->first()['key'] ? route('explorers') : route('explorers', ['map' => $map->key]);

    // The question people ask, as the title. Later pages of the board and searches
    // are left out of results (their links are still followed): the board's first
    // page is the one to show.
    $seoTitle = match (true) {
        $isWorld => __('Explorers worldwide: who has visited the most countries?'),
        $map->key === 'bd' => __('Explorers of Bangladesh: who has visited the most districts?'),
        default => __('Explorers of :place', ['place' => $map->label()]),
    };
    $pageNumber = max(1, (int) request()->query('page', 1));
    $seoTitle .= $pageNumber > 1 ? ' - '.__('page :number', ['number' => $pageNumber]) : '';
    $noindex = $pageNumber > 1 || $search !== '' ? 'follow' : false;

    $mapCta = match ($me['state']) {
        'guest' => [route('register'), __('Join and get on the board')],
        'ranked' => [route('travel.show', $map->key), __('Update my map')],
        'admin' => null,
        default => [route('travel.show', $map->key), __('Fill in my map')],
    };
@endphp

<x-layouts.site :title="$seoTitle" :card="false" :wide="true" :canonical="$canonical" :noindex="$noindex"
                :description="__('The Sofor travellers who have seen the most of :place, ranked by :units visited.', ['place' => $place, 'units' => $map->unit(2)])">

    {{-- ===================== Hero: the community map at night ===================== --}}
    <section class="relative isolate overflow-hidden bg-ink text-canvas">
        <div aria-hidden="true" class="absolute inset-0 -z-10 opacity-[0.12]"
             style="background-image: radial-gradient(circle, #fbf8f2 1px, transparent 1.3px); background-size: 22px 22px;"></div>
        <div aria-hidden="true" class="absolute -right-40 top-0 -z-10 size-[42rem] rounded-full opacity-40 blur-3xl"
             style="background: radial-gradient(circle, #0f6f6a, transparent 65%)"></div>

        <div @class([
            'mx-auto grid w-full max-w-6xl gap-10 px-4 pt-10 sm:px-6 lg:items-center lg:gap-12 lg:px-8',
            'pb-20 sm:pb-24' => $podium->isNotEmpty(),
            'pb-14 sm:pb-16' => $podium->isEmpty(),
            'lg:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)] lg:pt-14' => ! $isWorld,
            'lg:pt-14' => $isWorld,
        ])>
            <div class="min-w-0">
                {{-- Which board: Bangladesh first, then the world, then any map added later. --}}
                <nav class="flex w-full max-w-full gap-1 overflow-x-auto rounded-2xl border border-canvas/15 bg-canvas/[0.06] p-1 no-scrollbar sm:inline-flex sm:w-auto" aria-label="{{ __('Choose a leaderboard') }}">
                    @foreach ($tabs as $tab)
                        @php $current = $tab['key'] === $map->key; @endphp
                        <a href="{{ $tabUrl($tab['key']) }}" @if ($current) aria-current="page" @endif
                           @class([
                               'flex flex-1 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-xl px-3 py-2.5 text-[13px] font-semibold transition sm:flex-none sm:px-4',
                               'bg-accent text-ink' => $current,
                               'text-canvas/70 hover:bg-canvas/10 hover:text-canvas' => ! $current,
                           ])>
                            <x-icon :name="$tab['key'] === config('travel_maps.world') ? 'globe' : 'map-pin'" class="size-4" />
                            {{ $tab['label'] }}
                            <span @class(['num rounded-full px-1.5 text-[11px]', 'bg-ink/15' => $current, 'bg-canvas/10' => ! $current])>{{ $tab['explorers'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <p class="eyebrow mt-8 text-accent">{{ __('Sofor Explorers') }}</p>
                <h1 class="mt-3 max-w-[16ch] text-balance font-display text-[2.4rem] font-semibold leading-[1.04] tracking-tight sm:text-[3.4rem]">
                    {{ __('Who has seen the most of :place?', ['place' => $place]) }}
                </h1>
                <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-canvas/70">
                    {{ __('The travellers who have seen the most of :place. Every :unit marked on a travel map counts - fill in yours and see where you land.', ['place' => $place, 'unit' => $map->unit()]) }}
                </p>

                <dl class="mt-8 grid max-w-xl grid-cols-3 divide-x divide-canvas/15 rounded-3xl border border-canvas/15 bg-canvas/[0.05]">
                    <div class="min-w-0 px-3 py-4 sm:px-5">
                        <dt class="eyebrow truncate text-canvas/55">{{ __('Explorers') }}</dt>
                        <dd class="num mt-1.5 font-display text-[1.7rem] font-semibold leading-none sm:text-[2.2rem]">{{ number_format($summary['explorers']) }}</dd>
                    </div>
                    <div class="min-w-0 px-3 py-4 sm:px-5">
                        <dt class="eyebrow truncate text-canvas/55">{{ __('Top score') }}</dt>
                        <dd class="mt-1.5 whitespace-nowrap font-display text-[1.7rem] font-semibold leading-none text-accent sm:text-[2.2rem]">
                            <span class="num">{{ $summary['best'] }}</span><span class="text-[0.95rem] text-canvas/45">/{{ $total }}</span>
                        </dd>
                    </div>
                    <div class="min-w-0 px-3 py-4 sm:px-5">
                        <dt class="eyebrow truncate text-canvas/55">{{ __('Explored') }}</dt>
                        <dd class="mt-1.5 whitespace-nowrap font-display text-[1.7rem] font-semibold leading-none sm:text-[2.2rem]">
                            <span class="num">{{ $summary['covered'] }}</span><span class="text-[0.95rem] text-canvas/45">/{{ $total }}</span>
                        </dd>
                        <span class="mt-2.5 block h-1 overflow-hidden rounded-full bg-canvas/10" role="presentation">
                            <span class="block h-full rounded-full bg-accent" style="width: {{ max(2, $percent($summary['covered'])) }}%"></span>
                        </span>
                    </div>
                </dl>

                @if ($mapCta)
                    <div class="mt-7 flex flex-wrap items-center gap-3">
                        <x-btn :href="$mapCta[0]" variant="accent" size="lg" icon-after="arrow-right">{{ $mapCta[1] }}</x-btn>
                        @if ($summary['explorers'] > 0)
                            <a href="#ranking" class="inline-flex h-12 items-center gap-2 rounded-full px-4 text-[14px] font-semibold text-canvas/80 transition hover:bg-canvas/10 hover:text-canvas">
                                {{ __('See the ranking') }} <x-icon name="chevron-down" class="size-4" />
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            {{-- The heat map: brighter where more explorers have been --}}
            <figure class="relative min-w-0">
                <div class="mx-auto w-full [filter:drop-shadow(0_0_32px_rgba(242,167,27,0.18))]"
                     style="max-width: {{ $isWorld ? '100%' : 'calc(30rem * '.round($map->width() / $map->height(), 3).')' }}">
                    <x-travel-map :map="$map" :static="true" :weights="$heat" :tips="$heatTips" palette="night" role="img"
                                  :aria-label="__('Heat map of how many explorers have visited each :unit', ['unit' => $map->unit()])" />
                </div>

                <figcaption @class([
                    'mt-4 flex flex-wrap items-center gap-3 text-[11px] text-canvas/60',
                    'lg:absolute lg:bottom-4 lg:left-0 lg:mt-0 lg:max-w-[15rem] lg:flex-col lg:items-stretch lg:gap-2.5 lg:rounded-3xl lg:border lg:border-canvas/15 lg:bg-ink/80 lg:p-4 lg:backdrop-blur' => ! $isWorld,
                ])>
                    @if ($hotspot !== null)
                        <span class="flex items-center gap-2.5">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-accent/15 text-accent"><x-icon name="flame" class="size-4.5" /></span>
                            <span class="min-w-0">
                                <span class="block text-[10px] uppercase tracking-wide text-canvas/50">{{ __('Most explored') }}</span>
                                <span class="block truncate text-[13px] font-semibold text-canvas">{{ $map->name($hotspot) }}</span>
                                <span class="block text-[11px] text-canvas/60">{{ trans_choice(':count explorer|:count explorers', $popularity[$hotspot]) }}</span>
                            </span>
                        </span>
                    @endif
                    <span class="flex min-w-[10rem] flex-1 items-center gap-2">
                        <span>{{ __('Fewer') }}</span>
                        <span class="h-1.5 flex-1 rounded-full" style="background: linear-gradient(90deg, #2b6158, #f6b23a)"></span>
                        <span>{{ __('More explorers') }}</span>
                    </span>
                </figcaption>
            </figure>
        </div>
    </section>

    <div class="mx-auto w-full max-w-6xl px-4 pb-6 sm:px-6 lg:px-8">
        @if ($summary['explorers'] === 0)
            {{-- ===================== Nobody yet ===================== --}}
            <section class="mt-10 flex flex-col items-center rounded-5xl border-[1.5px] border-dashed border-line-strong bg-surface px-6 py-16 text-center">
                <x-glyph name="compass" class="size-16" />
                <h2 class="mt-5 font-display text-2xl font-semibold">{{ __('No explorers yet') }}</h2>
                <p class="mt-2 max-w-md text-[14px] leading-relaxed text-muted">
                    {{ __('Nobody has put :place on their travel map yet. Mark the :unit you have been to and take first place.', ['place' => $place, 'unit' => $map->unit(2)]) }}
                </p>
                <x-btn :href="auth()->check() ? route('travel.index') : route('register')" icon="sparkles" class="mt-6">
                    {{ auth()->check() ? __('Fill in my travel map') : __('Create my travel map') }}
                </x-btn>
            </section>
        @else
            {{-- ===================== Leaders: the top three in one slim bar ===================== --}}
            @if ($podium->isNotEmpty())
                <section aria-labelledby="podium-title" class="relative -mt-12 sm:-mt-14">
                    <h2 id="podium-title" class="sr-only">{{ __('Top :count explorers', ['count' => $podium->count()]) }}</h2>
                    <ol class="grid overflow-hidden rounded-3xl border-[1.5px] border-line bg-surface shadow-lift sm:grid-cols-3">
                        @foreach ($podium as $entry)
                            @php
                                $person = $users[$entry['user_id']];
                                $rank = $entry['rank'];
                                $medal = $medals[$rank];
                                $personLevel = $level($entry['visited']);
                            @endphp
                            <li @class(['min-w-0 border-line', 'border-t sm:border-l sm:border-t-0' => ! $loop->first])>
                                <a href="{{ $person->publicUrl() }}"
                                   aria-label="{{ __(':medal, number :rank: :name, :count :unit', ['medal' => $medal['label'], 'rank' => $rank, 'name' => $person->name, 'count' => $entry['visited'], 'unit' => $unit($entry['visited'])]) }}"
                                   class="group flex h-full items-center gap-3.5 px-4 py-3.5 transition hover:bg-surface-2 sm:px-5 sm:py-4"
                                   @if ($rank === 1) style="background-image: linear-gradient(90deg, {{ $medal['tint'] }}, transparent 85%)" @endif>
                                    {{-- A medal-coloured ring round the avatar --}}
                                    <span class="relative shrink-0 rounded-full p-[2.5px]" style="background: {{ $medal['color'] }}">
                                        <span class="block rounded-full bg-surface p-[2px]">
                                            <x-avatar :name="$person->name" :gradient="$person->avatarGradient()" :src="$person->avatarUrl()" size="md" :ring="false" class="rounded-full!" />
                                        </span>
                                        <span class="num absolute -bottom-1 -right-1 flex size-5 items-center justify-center rounded-full border-2 border-surface text-[10px] font-bold text-ink"
                                              style="background: {{ $medal['color'] }}">{{ $rank }}</span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex min-w-0 items-center gap-1.5">
                                            <span class="truncate text-[14px] font-semibold text-ink">{{ $person->name }}</span>
                                            @if ($rank === 1)
                                                <x-icon name="sparkles" class="size-3.5 shrink-0 text-accent-2" />
                                            @endif
                                        </span>
                                        <span class="mt-0.5 flex min-w-0 items-center gap-1 text-[11.5px] text-muted">
                                            <x-glyph :name="$personLevel['icon']" class="size-4 shrink-0" />
                                            <span class="truncate">{{ $personLevel['label'] }}</span>
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-right leading-none">
                                        <span class="num block font-display text-[1.45rem] font-semibold text-ink">{{ $entry['visited'] }}</span>
                                        <span class="mt-1 block text-[10.5px] text-faint">{{ $unit($entry['visited']) }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            {{-- ===================== Your race ===================== --}}
            @if (in_array($me['state'], ['guest', 'private', 'unranked', 'ranked'], true))
                <section @class(['rounded-4xl border-[1.5px] p-5 sm:p-6', 'mt-6' => $podium->isNotEmpty(), 'mt-8' => $podium->isEmpty(),
                                 'border-brand/30 bg-brand-tint/50' => $me['state'] === 'ranked', 'border-line bg-surface' => $me['state'] !== 'ranked'])>
                    @if ($me['state'] === 'ranked')
                        @php
                            $myVisited = $me['entry']['visited'];
                            $myLevel = $level($myVisited);
                            $nextLevel = collect($levels)->first(fn ($candidate) => $candidate['from'] > $myVisited);
                        @endphp
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-4">
                            <span class="num flex size-16 shrink-0 flex-col items-center justify-center rounded-3xl border-2 border-ink bg-brand font-display text-white shadow-sticker-sm">
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-white/70">{{ __('Rank') }}</span>
                                <span class="text-[1.4rem] font-semibold leading-none">#{{ $me['entry']['rank'] }}</span>
                            </span>
                            <div class="min-w-[13rem] flex-1">
                                <p class="font-display text-lg font-semibold">{{ __('You are #:rank of :explorers', ['rank' => $me['entry']['rank'], 'explorers' => number_format($me['explorers'])]) }}</p>
                                <p class="mt-0.5 text-[13px] text-muted">
                                    @if ($me['ahead'])
                                        {!! __('Visit :count more :unit to pass :person.', ['count' => e($me['needed']), 'unit' => e($unit($me['needed'])), 'person' => '<a href="'.e($me['ahead']->publicUrl()).'" class="font-semibold text-brand hover:underline">'.e('@'.$me['ahead']->username).'</a>']) !!}
                                    @else
                                        {{ __('Nobody has seen more - keep it up.') }}
                                    @endif
                                </p>
                            </div>
                            <x-btn :href="route('travel.show', $map->key)" size="sm" icon="globe" class="w-full sm:w-auto">{{ __('Update my map') }}</x-btn>
                        </div>

                        {{-- The race: you, the person just ahead, and the leader on one track --}}
                        <div class="mt-6" aria-hidden="true">
                            <div class="relative h-11">
                                <span class="absolute inset-x-0 top-1/2 h-2 -translate-y-1/2 rounded-full bg-surface"></span>
                                <span class="absolute left-0 top-1/2 h-2 -translate-y-1/2 rounded-full bg-brand" style="width: {{ max(2, $percent($myVisited)) }}%"></span>
                                @if ($me['leader'])
                                    <span class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2" style="left: {{ $percent($me['leaderVisited']) }}%" title="{{ $me['leader']->name }}">
                                        <x-avatar :name="$me['leader']->name" :gradient="$me['leader']->avatarGradient()" :src="$me['leader']->avatarUrl()" size="xs" class="rounded-full! ring-2 ring-accent" />
                                    </span>
                                @endif
                                @if ($me['ahead'] && (! $me['leader'] || $me['ahead']->id !== $me['leader']->id))
                                    <span class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2" style="left: {{ $percent($me['aheadVisited']) }}%" title="{{ $me['ahead']->name }}">
                                        <x-avatar :name="$me['ahead']->name" :gradient="$me['ahead']->avatarGradient()" :src="$me['ahead']->avatarUrl()" size="xs" class="rounded-full! opacity-90" />
                                    </span>
                                @endif
                                <span class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2" style="left: {{ max(3, $percent($myVisited)) }}%">
                                    <x-avatar :name="auth()->user()->name" :gradient="auth()->user()->avatarGradient()" :src="auth()->user()->avatarUrl()" size="sm" class="rounded-full! ring-[3px] ring-brand" />
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-[11px] text-muted">
                                <span class="inline-flex items-center gap-1.5"><x-glyph :name="$myLevel['icon']" class="size-4" /> {{ $myLevel['label'] }}</span>
                                <span>
                                    @if ($nextLevel)
                                        {{ __(':count more to :level', ['count' => $nextLevel['from'] - $myVisited, 'level' => $nextLevel['label']]) }}
                                    @else
                                        {{ __(':visited of :total :units.', ['visited' => $myVisited, 'total' => $total, 'units' => $map->unit(2)]) }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
                            @if ($me['state'] === 'private')
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-surface-2 text-muted"><x-icon name="lock" class="size-5" /></span>
                            @else
                                <x-glyph name="backpack" class="size-12 shrink-0" />
                            @endif
                            <div class="min-w-[12rem] flex-1">
                                <p class="font-display text-base font-semibold">
                                    @switch($me['state'])
                                        @case('guest') {{ __('Where would you rank?') }} @break
                                        @case('private') {{ __('Your profile is private') }} @break
                                        @default {{ __('You are not on this board yet') }}
                                    @endswitch
                                </p>
                                <p class="text-[13px] text-muted">
                                    @switch($me['state'])
                                        @case('guest') {{ __('Create a free account, mark the :units you have been to and join the board.', ['units' => $map->unit(2)]) }} @break
                                        @case('private') {{ __('Private profiles are never listed here. Make yours public to take your place.') }} @break
                                        @default {{ __('Mark the :units you have been to on your travel map and you will show up here.', ['units' => $map->unit(2)]) }}
                                    @endswitch
                                </p>
                            </div>
                            @switch($me['state'])
                                @case('guest')
                                    <x-btn :href="route('register')" size="sm" icon-after="arrow-right" class="w-full sm:w-auto">{{ __('Join Sofor') }}</x-btn>
                                    @break
                                @case('private')
                                    <x-btn :href="route('profile.edit')" size="sm" variant="secondary" icon="user" class="w-full sm:w-auto">{{ __('Profile settings') }}</x-btn>
                                    @break
                                @default
                                    <x-btn :href="route('travel.show', $map->key)" size="sm" icon="globe" class="w-full sm:w-auto">{{ __('Fill in my map') }}</x-btn>
                            @endswitch
                        </div>
                    @endif
                </section>
            @endif

            {{-- ===================== Ranking and the community ===================== --}}
            <div class="mt-10 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
                <section id="ranking" class="min-w-0 scroll-mt-24" aria-labelledby="ranking-title">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 id="ranking-title" class="font-display text-[1.6rem] font-semibold leading-tight">
                                {{ $search !== '' ? __('Explorers matching ":search"', ['search' => $search]) : ($podium->isNotEmpty() ? __('The rest of the field') : __('Ranking')) }}
                            </h2>
                            <p class="mt-1 text-[12px] text-faint">{{ __('Most :unit first. Ties go to whoever got there first.', ['unit' => $map->unit(2)]) }}</p>
                        </div>

                        <form method="GET" action="{{ route('explorers') }}#ranking" role="search" class="relative w-full sm:w-72" x-data>
                            <input type="hidden" name="map" value="{{ $map->key }}">
                            <label for="explorer-search" class="sr-only">{{ __('Find an explorer') }}</label>
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-faint"><x-icon name="search" class="size-4" /></span>
                            <input id="explorer-search" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Find an explorer') }}"
                                   autocomplete="off" class="form-field h-12 rounded-full pl-11 text-[13.5px]"
                                   @input.debounce.600ms="$el.form.requestSubmit()"
                                   x-init="if ($el.value) { $el.focus(); $el.setSelectionRange($el.value.length, $el.value.length) }">
                        </form>
                    </div>

                    @if ($rows->isEmpty())
                        <div class="mt-5 flex flex-col items-center rounded-4xl border-[1.5px] border-dashed border-line-strong bg-surface px-6 py-12 text-center">
                            <x-glyph :name="$search !== '' ? 'maybe' : 'settled'" class="size-12" />
                            @if ($search !== '')
                                <p class="mt-4 font-display text-base font-semibold">{{ __('No explorer matches ":search"', ['search' => $search]) }}</p>
                                <p class="mt-1 text-[13px] text-muted">{{ __('Only public profiles are listed. Check the spelling or try their username.') }}</p>
                                <x-btn :href="route('explorers', ['map' => $map->key])" variant="secondary" size="sm" class="mt-4">{{ __('Clear search') }}</x-btn>
                            @else
                                <p class="mt-4 font-display text-base font-semibold">{{ __('Everyone is on the podium') }}</p>
                                <p class="mt-1 text-[13px] text-muted">{{ __('Mark your :unit and you could be next.', ['unit' => $map->unit(2)]) }}</p>
                            @endif
                        </div>
                    @else
                        <ol class="mt-5 overflow-hidden rounded-4xl border-[1.5px] border-line bg-surface shadow-soft" start="{{ $rows->first()['rank'] }}">
                            @foreach ($rows as $entry)
                                @php
                                    $person = $users[$entry['user_id']];
                                    $isMe = $person->id === $mine;
                                    $share = $percent($entry['visited']);
                                    $personLevel = $level($entry['visited']);
                                    $reached = $groups[$person->id] ?? 0;
                                @endphp
                                <li @class(['border-t border-line first:border-t-0'])>
                                    <a href="{{ $person->publicUrl() }}"
                                       @class([
                                           'group grid grid-cols-[2.25rem_auto_minmax(0,1fr)_auto] items-center gap-x-3 px-3 py-3.5 transition sm:grid-cols-[3rem_auto_minmax(0,1fr)_12rem_auto] sm:gap-x-4 sm:px-5',
                                           'bg-brand-tint/60 hover:bg-brand-tint' => $isMe,
                                           'hover:bg-surface-2' => ! $isMe,
                                       ])>
                                        <span class="num text-center font-display text-[1.25rem] font-semibold text-faint group-hover:text-ink sm:text-[1.5rem]">{{ $entry['rank'] }}</span>
                                        <x-avatar :name="$person->name" :gradient="$person->avatarGradient()" :src="$person->avatarUrl()" size="md" class="rounded-full!" />

                                        <span class="min-w-0">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <span class="truncate text-[14.5px] font-semibold text-ink">{{ $person->name }}</span>
                                                @if ($isMe)
                                                    <span class="shrink-0 rounded-full bg-brand px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white">{{ __('You') }}</span>
                                                @endif
                                            </span>
                                            <span class="mt-0.5 flex min-w-0 items-center gap-1.5 text-[11.5px] text-faint">
                                                <x-glyph :name="$personLevel['icon']" class="size-4 shrink-0" />
                                                <span class="truncate">{{ $personLevel['label'] }} <span class="hidden sm:inline">· {{ '@'.$person->username }}</span></span>
                                            </span>
                                        </span>

                                        {{-- The trail: share of the map --}}
                                        <span class="hidden sm:block">
                                            <span class="flex items-baseline justify-between gap-2 text-[11px] text-faint">
                                                <span>{{ trans_choice(':count :group|:count :groups', $reached, ['group' => $map->groupUnit(1), 'groups' => $map->groupUnit(2)]) }}</span>
                                                <span class="num">{{ \App\Support\Money::percent($share) }}</span>
                                            </span>
                                            <span class="mt-1.5 block h-2 overflow-hidden rounded-full bg-surface-2" role="presentation">
                                                <span class="block h-full rounded-full bg-gradient-to-r from-brand to-[#3a9a8c]" style="width: {{ max(3, $share) }}%"></span>
                                            </span>
                                        </span>

                                        <span class="flex items-baseline justify-end gap-1">
                                            <span class="num font-display text-[1.25rem] font-semibold text-ink">{{ $entry['visited'] }}</span>
                                            <span class="text-[11px] text-faint">/{{ $total }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>

                        @if ($rows->hasPages())
                            <div class="mt-5">{{ $rows->fragment('ranking')->links() }}</div>
                        @endif
                    @endif
                </section>

                <aside class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-24">
                    {{-- Levels: a next goal for everyone --}}
                    <div class="rounded-4xl border-[1.5px] border-line bg-surface p-5">
                        <h2 class="font-display text-base font-semibold">{{ __('Explorer levels') }}</h2>
                        <p class="mt-0.5 text-[12px] text-muted">{{ __('Mark more :units to climb.', ['units' => $map->unit(2)]) }}</p>
                        <ol class="mt-4 flex flex-col gap-1.5">
                            @foreach (array_reverse($levels) as $tier)
                                <li class="flex items-center gap-3 rounded-2xl px-2 py-1.5 transition hover:bg-surface-2">
                                    <x-glyph :name="$tier['icon']" class="size-8 shrink-0" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-semibold text-ink">{{ $tier['label'] }}</span>
                                        <span class="block text-[11px] text-faint">{{ __(':count+ :units', ['count' => $tier['from'], 'units' => $map->unit($tier['from'])]) }}</span>
                                    </span>
                                    <span class="num rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-semibold text-muted" title="{{ trans_choice(':count explorer|:count explorers', $levelCounts[$tier['key']]) }}">{{ $levelCounts[$tier['key']] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- Where explorers go --}}
                    @if ($topAreas->isNotEmpty())
                        <div class="rounded-4xl border-[1.5px] border-line bg-surface p-5">
                            <h2 class="font-display text-base font-semibold">{{ __('Where explorers go') }}</h2>
                            <p class="mt-0.5 text-[12px] text-muted">{{ __(':covered of :total :unit visited by at least one explorer.', ['covered' => $summary['covered'], 'total' => $total, 'unit' => $map->unit(2)]) }}</p>
                            <ol class="mt-4 flex flex-col gap-3">
                                @foreach ($topAreas as $id => $count)
                                    <li class="flex items-center gap-3">
                                        <span @class(['num flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold', 'bg-accent text-ink' => $loop->first, 'bg-surface-2 text-muted' => ! $loop->first])>{{ $loop->iteration }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-baseline justify-between gap-2">
                                                <span class="truncate text-[13px] font-semibold text-ink">{{ $map->name($id) }}</span>
                                                <span class="shrink-0 text-[11px] text-muted">{{ trans_choice(':count explorer|:count explorers', $count) }}</span>
                                            </span>
                                            <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-surface-2">
                                                <span class="block h-full rounded-full bg-accent" style="width: {{ round($count * 100 / $mostPopular) }}%"></span>
                                            </span>
                                        </span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif

                    {{-- Still waiting for a first explorer --}}
                    <div class="rounded-4xl border-[1.5px] border-dashed border-line-strong bg-canvas-tint p-5">
                        <div class="flex items-start gap-3">
                            <x-glyph name="camp" class="size-9 shrink-0" />
                            <div class="min-w-0">
                                @if ($waiting->isNotEmpty())
                                    <h2 class="font-display text-base font-semibold">{{ __('Still waiting for an explorer') }}</h2>
                                    <p class="mt-0.5 text-[12px] text-muted">{{ trans_choice('Nobody has marked :count :unit yet. Be the first.|Nobody has marked these :count :units yet. Be the first.', count($unexplored), ['unit' => $map->unit(1), 'units' => $map->unit(2)]) }}</p>
                                @else
                                    <h2 class="font-display text-base font-semibold">{{ __('Every :unit has an explorer', ['unit' => $map->unit()]) }}</h2>
                                    <p class="mt-0.5 text-[12px] text-muted">{{ __('Each one has been marked by at least one traveller. Now go for more of them yourself.') }}</p>
                                @endif
                            </div>
                        </div>
                        @if ($waiting->isNotEmpty())
                            <ul class="mt-4 flex flex-wrap gap-1.5">
                                @foreach ($waiting as $id)
                                    <li class="rounded-full border border-line bg-surface px-2.5 py-1 text-[12px] font-medium text-ink">{{ $map->name($id) }}</li>
                                @endforeach
                                @if (count($unexplored) > $waiting->count())
                                    <li class="rounded-full px-2 py-1 text-[12px] text-faint">{{ __('+ :count more', ['count' => count($unexplored) - $waiting->count()]) }}</li>
                                @endif
                            </ul>
                        @endif
                    </div>

                    <p class="flex items-start gap-2 px-1 text-[11px] leading-relaxed text-faint">
                        <x-icon name="shield" class="mt-0.5 size-3.5 shrink-0" />
                        {{ __('Only public profiles take part. Private profiles never appear here, and nobody\'s trips or money are shown.') }}
                    </p>
                </aside>
            </div>
        @endif
    </div>
</x-layouts.site>
