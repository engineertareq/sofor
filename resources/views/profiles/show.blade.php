@php
    use App\Support\ExplorerLevel;
    use Illuminate\Support\Js;
    use Illuminate\Support\Str;

    /**
     * sofor.io/@username: a traveller's explorer page. Their own map glows under
     * a night sky (the Explorers board's look), with their level, their place on
     * the board and, division by division, where they have been. Contact
     * details, trips and money never appear here.
     */
    $firstName = Str::of($user->name)->before(' ')->toString() ?: $user->name;
    $isWorld = $map->key === config('travel_maps.world');
    $tab = collect($tabs)->firstWhere('key', $map->key);
    $stats = $tab['stats'];

    $summary = collect($tabs)
        ->map(fn (array $tab) => __(':visited of :total :units in :place', ['visited' => $tab['stats']['visited'], 'total' => $tab['stats']['total'], 'units' => $tab['unit'], 'place' => $tab['label']]))
        ->implode(' '.__('and').' ');

    // Their level on this map, and what the next one takes.
    $levels = ExplorerLevel::all($stats['total']);
    $levelIndex = $stats['visited'] > 0 ? array_search(ExplorerLevel::for($stats['visited'], $stats['total'])['key'], array_column($levels, 'key'), true) : -1;
    $level = $levels[$levelIndex] ?? null;
    $next = $levels[$levelIndex + 1] ?? null;
    $levelProgress = match (true) {
        $next === null => 100,
        $level === null => 0,
        default => (int) floor(($stats['visited'] - $level['from']) * 100 / max(1, $next['from'] - $level['from'])),
    };
    $rank = collect($ranks)->firstWhere('key', $map->key);

    // Division by division (continent by continent): one tile per place.
    $lookup = array_flip($visited);
    $groups = collect($map->areas())
        ->filter(fn (array $area, string $id) => $map->isCounted($id))
        ->map(fn (array $area, string $id) => ['id' => $id, 'group' => $area['group'], 'name' => $map->name($id), 'on' => isset($lookup[$id])])
        ->groupBy('group')
        ->map(fn ($areas, $code) => [
            'name' => $map->groups()[$code] ?? $code,
            'areas' => $areas->sortBy('name')->values(),
            'visited' => $areas->where('on', true)->count(),
        ])
        ->sortBy([['visited', 'desc'], ['name', 'asc']])
        ->values();

    // A signed-in traveller looking at someone else's map: what the two of them share.
    $names = fn (array $ids) => collect($ids)->filter(fn (string $id) => $map->has($id) && $map->isCounted($id))->map(fn (string $id) => $map->name($id))->sort()->values();
    $common = $mine !== null ? $names(array_intersect($visited, $mine)) : null;
    $theirsOnly = $mine !== null ? $names(array_diff($visited, $mine))->count() : 0;
    $mineOnly = $mine !== null ? $names(array_diff($mine, $visited))->count() : 0;

    // Tall maps (Bangladesh) stop at a comfortable height; wide ones use the column.
    $mapWidth = 'calc(32rem * '.round($map->width() / $map->height(), 3).')';
@endphp

{{-- Kept out of search results on purpose (a person's name and travels), but a
     shared link still previews nicely, with their photo when they have one. --}}
<x-layouts.site :title="$user->name.' (@'.$user->username.')'" :card="false" :wide="true" :noindex="true"
                :image="$user->avatarUrl()"
                :description="__(':name has been to :summary. Their travel map on Sofor.', ['name' => $user->name, 'summary' => $summary])">

    @if ($isOwner && ! $user->profile_public)
        <div class="mx-auto w-full max-w-6xl px-4 pt-5 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-3 rounded-3xl border border-warning/30 bg-warning-tint px-4 py-3 text-[12.5px] text-ink">
                <x-icon name="lock" class="size-4 text-warning" />
                <span class="min-w-0 flex-1">{{ __('Only you can see this page. Your profile is private, so visitors see nothing.') }}</span>
                <a href="{{ route('profile.edit') }}" class="font-semibold text-warning underline decoration-dotted underline-offset-2">{{ __('Make it public') }}</a>
            </div>
        </div>
    @endif

    {{-- ===================== Under the night sky ===================== --}}
    <section class="relative isolate mt-4 overflow-hidden bg-ink text-canvas">
        <div aria-hidden="true" class="absolute inset-0 -z-10 opacity-[0.12]"
             style="background-image: radial-gradient(circle, #fbf8f2 1px, transparent 1.3px); background-size: 22px 22px;"></div>
        <div aria-hidden="true" class="absolute -right-40 top-10 -z-10 size-[40rem] rounded-full opacity-40 blur-3xl"
             style="background: radial-gradient(circle, #0f6f6a, transparent 65%)"></div>
        <div aria-hidden="true" class="absolute -left-48 -bottom-40 -z-10 size-[30rem] rounded-full opacity-25 blur-3xl"
             style="background: radial-gradient(circle, #f6b23a, transparent 65%)"></div>

        <div class="mx-auto grid w-full max-w-6xl gap-10 px-4 pb-24 pt-10 sm:px-6 sm:pb-28 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-center lg:gap-12 lg:px-8 lg:pt-14">

            {{-- Who --}}
            <div class="min-w-0 text-center lg:text-left">
                <div class="relative mx-auto size-36 sm:size-40 lg:mx-0">
                    {{-- The ring fills with how much of this map they have explored. --}}
                    <svg viewBox="0 0 100 100" class="absolute inset-0 size-full -rotate-90" aria-hidden="true">
                        <circle cx="50" cy="50" r="46" fill="none" stroke="rgba(251,248,242,0.12)" stroke-width="3.5" />
                        <circle cx="50" cy="50" r="46" fill="none" stroke="#f6b23a" stroke-width="3.5" stroke-linecap="round"
                                pathLength="100" stroke-dasharray="{{ max(0.5, $stats['percent']) }} 100" class="profile-ring"></circle>
                    </svg>
                    <div class="absolute inset-[11px]">
                        <x-avatar :name="$user->name" :gradient="$user->avatarGradient()" :src="$user->avatarUrl()" size="xl" :ring="false"
                                  class="size-full! rounded-full! text-4xl! shadow-none!" />
                    </div>
                    @if ($level)
                        <span class="absolute -bottom-1 right-1 flex size-12 items-center justify-center rounded-full border-[3px] border-ink bg-canvas" title="{{ $level['label'] }}">
                            <x-glyph :name="$level['icon']" class="size-8" />
                        </span>
                    @endif
                </div>

                <p class="mt-6 font-mono text-[11.5px] font-bold tracking-[0.12em] text-[#f6b23a]">
                    {{ $level ? __(':level · Sofor explorer', ['level' => $level['label']]) : __('Sofor explorer') }}
                </p>
                <h1 class="mt-2 text-balance font-display text-[2.4rem] font-semibold leading-[1.05] tracking-tight sm:text-[3.1rem]">{{ $user->name }}</h1>
                <p class="mt-2 text-[13.5px] text-canvas/65">
                    <span class="font-semibold text-canvas/90" translate="no">{{ '@'.$user->username }}</span>
                    <span aria-hidden="true" class="mx-1.5 text-canvas/30">·</span>
                    {{ __('Travelling with Sofor since :date', ['date' => $user->created_at->translatedFormat('F Y')]) }}
                </p>

                @if ($ranks !== [])
                    <div class="mt-4 flex flex-wrap justify-center gap-2 lg:justify-start">
                        @foreach ($ranks as $board)
                            <a href="{{ route('explorers', ['map' => $board['key']]) }}"
                               class="inline-flex items-center gap-1.5 rounded-full border border-[#f6b23a]/40 bg-[#f6b23a]/10 px-3 py-1.5 text-[12px] font-semibold text-[#f6b23a] transition hover:bg-[#f6b23a]/20">
                                <x-icon name="flag" class="size-3.5" /> {{ __('#:rank explorer :label', ['rank' => $board['rank'], 'label' => $board['label']]) }}
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- Share, copy, and for the owner the way to change it --}}
                <div class="mt-7 flex flex-wrap justify-center gap-2 lg:justify-start" x-data="{ canShare: false }" x-init="canShare = !! navigator.share">
                    <button type="button" x-show="canShare" x-cloak
                            @click="navigator.share({ title: {{ Js::from($user->name.' - Sofor') }}, url: {{ Js::from($user->publicUrl()) }} }).catch(() => {})"
                            class="inline-flex h-11 items-center gap-2 rounded-full bg-[#f6b23a] px-5 text-[13.5px] font-semibold text-ink transition hover:bg-[#ffc65a]">
                        <x-icon name="share" class="size-4" /> {{ __('Share profile') }}
                    </button>
                    <div class="contents" x-data="clipboard({{ Js::from($user->publicUrl()) }}, {{ Js::from(__('Profile link copied')) }})">
                        <button type="button" @click="copy()"
                                class="inline-flex h-11 items-center gap-2 rounded-full border border-canvas/20 bg-canvas/5 px-5 text-[13.5px] font-semibold text-canvas transition hover:bg-canvas/10">
                            <x-icon name="link" class="size-4" />
                            <span x-text="copied ? {{ Js::from(__('Copied')) }} : {{ Js::from(__('Copy link')) }}">{{ __('Copy link') }}</span>
                        </button>
                    </div>
                    @if ($isOwner)
                        <a href="{{ route('travel.show', $map->key) }}"
                           class="inline-flex h-11 items-center gap-2 rounded-full border border-canvas/20 bg-canvas/5 px-5 text-[13.5px] font-semibold text-canvas transition hover:bg-canvas/10">
                            <x-icon name="globe" class="size-4" /> {{ __('Update my map') }}
                        </a>
                        <a href="{{ route('profile.edit') }}" class="inline-flex h-11 items-center gap-2 rounded-full px-3 text-[13px] font-semibold text-canvas/70 transition hover:text-canvas">
                            <x-icon name="pencil" class="size-4" /> {{ __('Edit profile') }}
                        </a>
                    @endif
                </div>
            </div>

            {{-- Where: their map, lit up --}}
            <div class="min-w-0">
                <div class="flex flex-wrap items-center justify-center gap-2 lg:justify-end">
                    <nav class="inline-flex rounded-full border border-canvas/15 bg-canvas/5 p-1" aria-label="{{ __('Travel maps') }}">
                        @foreach ($tabs as $option)
                            @php $on = $option['key'] === $map->key; @endphp
                            <a href="{{ route('profile.public', ['username' => $user->username, 'map' => $option['key']]) }}" @if ($on) aria-current="page" @endif
                               @class([
                                   'flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold transition',
                                   'bg-canvas text-ink' => $on,
                                   'text-canvas/70 hover:text-canvas' => ! $on,
                               ])>
                                <x-icon :name="$option['key'] === config('travel_maps.world') ? 'globe' : 'map-pin'" class="size-3.5" />
                                {{ $option['label'] }}
                                <span @class(['num text-[11px]', 'text-muted' => $on, 'text-canvas/45' => ! $on])>{{ $option['stats']['visited'] }}/{{ $option['stats']['total'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                </div>

                <div class="relative mx-auto mt-6 w-full" style="max-width: {{ $mapWidth }}">
                    <x-travel-map :map="$map" :visited="$visited" :static="true" palette="night" tip-mode="state" role="img" class="profile-map"
                                  :aria-label="__(':name\'s :map map: :count :unit visited', ['name' => $firstName, 'map' => $map->label(), 'count' => $stats['visited'], 'unit' => $map->unit($stats['visited'])])" />
                    @unless ($hasAnything)
                        <div class="absolute inset-0 flex items-center justify-center">
                            <p class="rounded-full border border-canvas/15 bg-ink/80 px-4 py-2 text-[13px] font-semibold text-canvas/80 backdrop-blur">
                                {{ $isOwner ? __('Your map is still empty') : __(':name has not added any places yet', ['name' => $firstName]) }}
                            </p>
                        </div>
                    @endunless
                </div>

                <div class="mt-5 flex items-center justify-center gap-4 text-[11.5px] text-canvas/60 lg:justify-end">
                    <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-[#f6b23a]"></span> {{ __('Visited') }}</span>
                    <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-[#1f3a35] ring-1 ring-canvas/15"></span> {{ __('Not yet') }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== The numbers, floating on the sky's edge ===================== --}}
    <div class="relative z-10 mx-auto -mt-14 w-full max-w-6xl px-4 sm:-mt-16 sm:px-6 lg:px-8">
        <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($tabs as $option)
                @foreach ([
                    [$option['unit'], $option['stats']['visited'], $option['stats']['total'], $option['stats']['percent'], 'brand'],
                    [$option['groupUnit'], $option['stats']['groups_visited'], $option['stats']['groups_total'], $option['stats']['groups_total'] > 0 ? (int) floor($option['stats']['groups_visited'] * 100 / $option['stats']['groups_total']) : 0, 'accent'],
                ] as [$label, $count, $total, $percent, $tone])
                    <div class="rounded-[1.6rem] border border-line bg-surface p-4 shadow-lift sm:p-5">
                        <dt class="flex items-center justify-between gap-2">
                            <span class="truncate text-[12px] font-semibold text-muted">{{ $option['label'] }} · {{ $label }}</span>
                            <span class="num shrink-0 text-[11.5px] font-semibold {{ $tone === 'brand' ? 'text-brand' : 'text-accent-2' }}">{{ $percent }}%</span>
                        </dt>
                        <dd class="mt-2 font-display text-[1.9rem] font-semibold leading-none text-ink">
                            <span class="num">{{ $count }}</span><span class="text-base font-medium text-faint"> / {{ $total }}</span>
                        </dd>
                        <dd class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-2">
                            <span class="block h-full rounded-full {{ $tone === 'brand' ? 'bg-brand' : 'bg-accent' }}" style="width: {{ max($percent, $count > 0 ? 3 : 0) }}%"></span>
                        </dd>
                    </div>
                @endforeach
            @endforeach
        </dl>
    </div>

    <div class="mx-auto grid w-full max-w-6xl gap-6 px-4 pb-6 pt-8 sm:px-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)] lg:items-start lg:px-8">

        {{-- ===================== Division by division ===================== --}}
        <section class="glass-card min-w-0 p-5 sm:p-7">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="eyebrow text-brand">{{ $map->label() }}</p>
                    <h2 class="mt-2 font-display text-[1.5rem] font-semibold leading-tight text-ink">{{ $isWorld ? __('Continent by continent') : __('Division by division') }}</h2>
                </div>
                <p class="text-[12.5px] text-muted">{{ __(':count :unit visited', ['count' => $stats['visited'], 'unit' => $map->unit($stats['visited'])]) }}</p>
            </div>

            <ul class="mt-6 flex flex-col divide-y divide-line">
                @foreach ($groups as $group)
                    @php $visitedHere = $group['areas']->where('on', true)->pluck('name'); @endphp
                    <li class="py-4 first:pt-0 last:pb-0">
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 @class(['font-display text-[15.5px] font-semibold', 'text-ink' => $group['visited'] > 0, 'text-faint' => $group['visited'] === 0])>{{ $group['name'] }}</h3>
                            <span class="num shrink-0 text-[12px] font-semibold {{ $group['visited'] > 0 ? 'text-brand' : 'text-faint' }}">{{ $group['visited'] }}/{{ $group['areas']->count() }}</span>
                        </div>
                        {{-- One tile per place, lit where they have been. --}}
                        <div class="mt-2.5 flex flex-wrap gap-1">
                            @foreach ($group['areas'] as $area)
                                <span @class(['waffle-tile size-3.5 sm:size-4', 'is-on' => $area['on']]) title="{{ $area['name'] }}"></span>
                            @endforeach
                        </div>
                        @if ($visitedHere->isNotEmpty())
                            <p class="mt-2 text-[12.5px] leading-relaxed text-muted">{{ $visitedHere->implode(', ') }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>

            <p class="mt-6 text-[10px] text-faint" translate="no">{{ $map->attribution() }}</p>
        </section>

        {{-- ===================== Level, board, and you ===================== --}}
        <aside class="flex min-w-0 flex-col gap-4">
            {{-- Explorer level on this map, and the next one --}}
            <div class="glass-card p-5 sm:p-6">
                <p class="text-[12px] font-semibold text-muted">{{ __('Explorer level on :map', ['map' => $map->label()]) }}</p>
                <div class="mt-3 flex items-center gap-4">
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-3xl border border-line bg-canvas">
                        <x-glyph :name="$level['icon'] ?? 'backpack'" class="size-11 {{ $level ? '' : 'opacity-40 grayscale' }}" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-display text-[1.45rem] font-semibold leading-tight text-ink">{{ $level['label'] ?? __('Not started yet') }}</p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            @if ($next)
                                {{ __(':count more :unit to :level', ['count' => $next['from'] - $stats['visited'], 'unit' => $map->unit($next['from'] - $stats['visited']), 'level' => $next['label']]) }}
                            @else
                                {{ __('The top level. Nowhere left to climb.') }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-surface-2">
                    <span class="block h-full rounded-full bg-gradient-to-r from-brand to-[#3fbfa8]" style="width: {{ max(2, $levelProgress) }}%"></span>
                </div>

                {{-- The ladder: every level, reached ones in colour --}}
                <ol class="mt-4 grid grid-cols-5 gap-1.5">
                    @foreach ($levels as $index => $step)
                        <li class="flex flex-col items-center gap-1 text-center" title="{{ $step['label'] }}">
                            <span @class([
                                'flex size-9 items-center justify-center rounded-2xl border transition',
                                'border-brand/30 bg-brand-tint' => $index <= $levelIndex,
                                'border-line bg-surface-2' => $index > $levelIndex,
                            ])>
                                <x-glyph :name="$step['icon']" class="size-6 {{ $index <= $levelIndex ? '' : 'opacity-35 grayscale' }}" />
                            </span>
                            <span @class(['truncate text-[10.5px] font-semibold w-full', 'text-ink' => $index <= $levelIndex, 'text-faint' => $index > $levelIndex])>{{ $step['label'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Their place on this map's board --}}
            @if ($rank)
                <a href="{{ route('explorers', ['map' => $map->key]) }}" class="group relative overflow-hidden rounded-[1.75rem] bg-ink p-5 text-canvas sm:p-6">
                    <div aria-hidden="true" class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle, #fbf8f2 1px, transparent 1.3px); background-size: 18px 18px;"></div>
                    <div class="relative flex items-center gap-4">
                        <span class="font-display text-[2.6rem] font-semibold leading-none text-[#f6b23a]">#<span class="num">{{ $rank['rank'] }}</span></span>
                        <div class="min-w-0">
                            <p class="text-[13.5px] font-semibold">{{ __('On the Explorers board :label', ['label' => $rank['label']]) }}</p>
                            <p class="mt-0.5 text-[12px] text-canvas/60">{{ trans_choice('Out of :count explorer|Out of :count explorers', $rank['of'], ['count' => $rank['of']]) }}</p>
                        </div>
                        <x-icon name="arrow-right" class="ml-auto size-4 shrink-0 text-canvas/50 transition group-hover:translate-x-0.5 group-hover:text-canvas" />
                    </div>
                </a>
            @endif

            {{-- A traveller looking at someone else: what they share --}}
            @if ($common !== null)
                <div class="glass-card p-5 sm:p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex -space-x-3">
                            <x-avatar :name="auth()->user()->name" :gradient="auth()->user()->avatarGradient()" :src="auth()->user()->avatarUrl()" size="sm" />
                            <x-avatar :name="$user->name" :gradient="$user->avatarGradient()" :src="$user->avatarUrl()" size="sm" />
                        </div>
                        <p class="font-display text-[15.5px] font-semibold text-ink">{{ __('You and :name', ['name' => $firstName]) }}</p>
                    </div>
                    <p class="mt-4 font-display text-[1.6rem] font-semibold leading-tight text-brand">
                        {{ __(':count :unit in common', ['count' => $common->count(), 'unit' => $map->unit($common->count())]) }}
                    </p>
                    @if ($common->isNotEmpty())
                        <ul class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($common->take(14) as $place)
                                <li class="rounded-full border border-brand/25 bg-brand-tint/60 px-2.5 py-1 text-[11.5px] font-medium text-brand">{{ $place }}</li>
                            @endforeach
                            @if ($common->count() > 14)
                                <li class="px-1 py-1 text-[11.5px] font-medium text-faint">+{{ $common->count() - 14 }}</li>
                            @endif
                        </ul>
                    @endif
                    <dl class="mt-4 grid grid-cols-2 gap-2 text-[12px]">
                        <div class="rounded-2xl bg-surface-2 p-3">
                            <dt class="text-muted">{{ __('Only :name', ['name' => $firstName]) }}</dt>
                            <dd class="num mt-0.5 font-display text-lg font-semibold text-ink">{{ $theirsOnly }}</dd>
                        </div>
                        <div class="rounded-2xl bg-surface-2 p-3">
                            <dt class="text-muted">{{ __('Only you') }}</dt>
                            <dd class="num mt-0.5 font-display text-lg font-semibold text-ink">{{ $mineOnly }}</dd>
                        </div>
                    </dl>
                </div>
            @elseif ($isOwner && ! $hasAnything)
                <div class="glass-card p-5 sm:p-6">
                    <p class="font-display text-[1.1rem] font-semibold text-ink">{{ __('Your map is still empty') }}</p>
                    <p class="mt-1 text-[13px] text-muted">{{ __('Mark the districts and countries you have been to, and they show up here.') }}</p>
                    <x-btn :href="route('travel.index')" icon="globe" class="mt-4">{{ __('Fill in my travel map') }}</x-btn>
                </div>
            @elseif (! $isOwner)
                {{-- Everyone else: make your own and compare --}}
                <div class="relative overflow-hidden rounded-[1.75rem] border border-accent/40 bg-accent-tint p-5 sm:p-6">
                    <x-glyph name="compass" class="pointer-events-none absolute -bottom-7 -right-7 size-32 opacity-20" />
                    <p class="relative font-display text-[1.35rem] font-semibold leading-tight text-ink">{{ $isWorld ? __('How many countries have you visited?') : __('How many districts have you visited?') }}</p>
                    <p class="relative mt-1.5 max-w-[24rem] text-[13px] leading-relaxed text-muted">{{ __('Make your own travel map in a minute and download it as a picture. Free, no sign-up.') }}</p>
                    <x-btn :href="route('map-maker', $isWorld ? $map->key : null)" variant="accent" icon-after="arrow-right" class="relative mt-4">{{ __('Make your travel map') }}</x-btn>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.site>
