@php
    $payload = [
        'map' => [
            'key' => $map->key,
            'label' => $map->label(),
            'unit' => $map->unit(),
            'units' => $map->unit(2),
            'groupUnits' => $map->groupUnit(2),
            'width' => $map->width(),
            'height' => $map->height(),
            'total' => $map->countedTotal(),
            'groups' => $map->groups(),
            'traveller' => auth()->user()->name,
            'logoUrl' => $logoUrl,
            'worldKey' => config('travel_maps.world'),
            'tabs' => collect($tabs)->mapWithKeys(fn (array $tab) => [$tab['key'] => $tab['stats']['visited']])->all(),
            'urls' => [
                'store' => route('travel.places.store', $map->key),
                'area' => route('travel.places.destroy', [$map->key, 'AREAID']),
            ],
        ],
        'areas' => $map->index(),
        'visited' => $visited,
        'suggestions' => $suggestions,
    ];
@endphp

<x-layouts.app title="{{ __('Travel map') }}" :active="'map'" :heading="__('Travel map')"
               :subtitle="__('Every :unit you have been to, on one map.', ['unit' => $map->unit()])" wide>

    <div class="flex flex-col gap-6" x-data="travelMap({{ \Illuminate\Support\Js::from($payload) }})">

        {{-- One tab per map in config/travel_maps.php --}}
        <nav class="no-scrollbar -mx-1 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="{{ __('Travel maps') }}">
            @foreach ($tabs as $tab)
                @php $current = $tab['key'] === $map->key; @endphp
                <a href="{{ route('travel.show', $tab['key']) }}" @if ($current) aria-current="page" @endif
                   @class([
                       'flex shrink-0 items-center gap-2 rounded-2xl border-[1.5px] px-4 py-2.5 text-[13px] font-semibold transition',
                       'border-ink bg-brand text-white shadow-sticker-sm' => $current,
                       'border-line bg-surface text-muted hover:text-ink' => ! $current,
                   ])>
                    <x-icon :name="$tab['key'] === config('travel_maps.world') ? 'globe' : 'map-pin'" class="size-4" />
                    {{ $tab['label'] }}
                    <span @class(['num rounded-full px-2 py-0.5 text-[11px]', 'bg-white/20' => $current, 'bg-surface-2' => ! $current])>
                        <span x-text="window.SoforNum(tabVisited('{{ $tab['key'] }}'))">{{ $tab['stats']['visited'] }}</span>/{{ $tab['stats']['total'] }}
                    </span>
                </a>
            @endforeach
        </nav>

        {{-- Where this map is shown to others. --}}
        <p class="-mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-[12px] text-muted">
            @if (auth()->user()->profile_public)
                <x-icon name="globe" class="size-3.5 text-brand" />
                {{ __('Shown on your public profile') }}
                <a href="{{ auth()->user()->publicUrl() }}" class="font-semibold text-brand hover:underline">{{ request()->getHttpHost() }}/{{ '@'.auth()->user()->username }}</a>
                <span class="text-faint">-</span>
                <a href="{{ route('explorers', ['map' => $map->key]) }}" class="inline-flex items-center gap-1 font-semibold text-accent-2 hover:underline">
                    <x-icon name="flag" class="size-3.5" />
                    {{ $rank ? __('#:rank on Explorers', ['rank' => $rank]) : __('See the Explorers ranking') }}
                </a>
            @else
                <x-icon name="lock" class="size-3.5 text-faint" />
                {{ __('Your profile is private, so only you see this map.') }}
                <a href="{{ route('profile.edit') }}" class="font-semibold text-brand hover:underline">{{ __('Change') }}</a>
            @endif
        </p>

        {{-- Places marked on the public map maker (in this browser) before signing in. --}}
        <div x-show="pendingDraft.length > 0" x-cloak class="flex flex-wrap items-center gap-3 rounded-3xl border-[1.5px] border-accent/40 bg-accent-tint/70 px-4 py-3">
            <x-glyph name="compass" class="size-9 shrink-0" />
            <p class="min-w-[12rem] flex-1 text-[13px] text-ink"
               x-text="{{ \Illuminate\Support\Js::from(__('You marked :count places on the map maker before signing in. Add them to this map?')) }}.replace(':count', window.SoforNum(pendingDraft.length))"></p>
            <div class="flex items-center gap-2">
                <x-btn type="button" size="sm" icon="check" @click="addDraft()" x-bind:disabled="busy">{{ __('Add them') }}</x-btn>
                <button type="button" @click="forgetDraft()" class="rounded-full px-3 py-1.5 text-[12px] font-medium text-muted transition hover:text-ink">{{ __('No thanks') }}</button>
            </div>
        </div>

        {{-- Totals: one compact row on phones, so the map stays near the top. --}}
        <div class="grid grid-cols-3 gap-2 sm:gap-3">
            <x-card padding="p-3 sm:p-5" class="min-w-0">
                <p class="eyebrow truncate text-faint">{{ __('Visited') }}</p>
                <p class="mt-1.5 font-display text-2xl font-semibold sm:mt-2 sm:text-3xl">
                    <span class="num" x-text="window.SoforNum(stats.visited)">{{ $stats['visited'] }}</span><span class="text-sm text-faint sm:text-lg"> / {{ $stats['total'] }}</span>
                </p>
                <p class="mt-1 hidden text-[12px] text-muted sm:block">{{ $map->unit(2) }}</p>
            </x-card>

            <x-card padding="p-3 sm:p-5" class="min-w-0">
                <p class="eyebrow truncate text-faint">{{ __('Explored') }}</p>
                <p class="mt-1.5 font-display text-2xl font-semibold text-brand sm:mt-2 sm:text-3xl"><span class="num" x-text="window.SoforNum(stats.percent)">{{ $stats['percent'] }}</span>%</p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2 sm:mt-2.5 sm:h-2">
                    <div class="h-full rounded-full bg-brand transition-all duration-500" style="width: {{ $stats['percent'] }}%"
                         :style="`width: ${stats.percent}%`"></div>
                </div>
            </x-card>

            <x-card padding="p-3 sm:p-5" class="min-w-0">
                <p class="eyebrow truncate text-faint">{{ ucfirst($map->groupUnit(2)) }}</p>
                <p class="mt-1.5 font-display text-2xl font-semibold sm:mt-2 sm:text-3xl">
                    <span class="num" x-text="window.SoforNum(stats.groupsVisited)">{{ $stats['groups_visited'] }}</span><span class="text-sm text-faint sm:text-lg"> / {{ $stats['groups_total'] }}</span>
                </p>
                <p class="mt-1 hidden text-[12px] text-muted sm:block">
                    {{ __('with at least one :unit visited', ['unit' => $map->unit()]) }}
                    <span x-show="stats.extra > 0" x-cloak x-text="(stats.extra === 1 ? @js(__('- plus :count territory')) : @js(__('- plus :count territories'))).replace(':count', window.SoforNum(stats.extra))"></span>
                </p>
            </x-card>
        </div>

        <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)] lg:items-start">
            {{-- ===================== Map ===================== --}}
            <x-card padding="p-3 sm:p-5" class="min-w-0">
                <div class="flex min-h-9 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-1">
                    <p class="min-w-0 truncate text-[13px] font-medium text-ink" x-text="caption" aria-live="polite">
                        {{ __('Tap a :unit to mark it as visited', ['unit' => $map->unit()]) }}
                    </p>
                    <div class="flex shrink-0 items-center gap-3 text-[11px] text-muted">
                        <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-brand"></span> {{ __('Visited') }}</span>
                        <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-[#e6dfd0]"></span> {{ __('Not yet') }}</span>
                    </div>
                </div>

                {{-- The list beside the map is the keyboard and screen reader way in. --}}
                <x-travel-map :map="$map" :visited="$visited" class="mt-3" aria-hidden="true" x-ref="svg"
                              x-bind:class="busy ? 'cursor-progress' : ''"
                              @click="pick($event)" @mouseover="hover($event)" @mouseleave="hovered = null" />

                {{-- Download --}}
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line px-1 pt-4">
                    <div class="flex items-center gap-2" role="radiogroup" aria-label="{{ __('Image colours') }}">
                        <span class="text-[12px] font-medium text-muted">{{ __('Image colours') }}</span>
                        <template x-for="(option, key) in themes" :key="key">
                            <button type="button" role="radio" :aria-checked="(theme === key).toString()" :aria-label="option.label"
                                    :title="option.label" @click="theme = key"
                                    class="flex size-8 items-center justify-center rounded-full border-[1.5px] transition"
                                    :class="theme === key ? 'border-ink' : 'border-line'"
                                    :style="`background: linear-gradient(135deg, ${option.background} 50%, ${option.visited} 50%)`">
                            </button>
                        </template>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-btn type="button" size="sm" variant="secondary" icon="share" x-show="canShare" x-cloak
                               @click="share()" x-bind:disabled="exporting">{{ __('Share') }}</x-btn>
                        <x-btn type="button" size="sm" icon="download" @click="download()" x-bind:disabled="exporting">
                            <span x-text="exporting ? @js(__('Making image...')) : @js(__('Download image'))">{{ __('Download image') }}</span>
                        </x-btn>
                    </div>
                </div>

                <p class="mt-3 px-1 text-[10px] text-faint" translate="no">{{ $map->attribution() }}</p>
            </x-card>

            {{-- ===================== Suggestions and list ===================== --}}
            <div class="flex min-w-0 flex-col gap-6">
                <x-card padding="p-4 sm:p-5" x-show="visibleSuggestions.length > 0" x-cloak>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span class="flex size-9 items-center justify-center rounded-2xl bg-accent-tint text-accent-2">
                                <x-icon name="sparkles" class="size-4.5" />
                            </span>
                            <div>
                                <h2 class="font-display text-sm font-semibold">{{ __('From your trips') }}</h2>
                                <p class="text-[11px] text-faint">{{ __('Places your trips mention that are not on your map yet.') }}</p>
                            </div>
                        </div>
                        <x-btn type="button" size="xs" variant="secondary" @click="addAllSuggestions()" x-bind:disabled="busy">{{ __('Add all') }}</x-btn>
                    </div>

                    <ul class="mt-4 flex flex-col gap-2">
                        <template x-for="suggestion in visibleSuggestions" :key="suggestion.id">
                            <li class="flex items-center gap-3 rounded-2xl border border-line bg-surface-2 px-3 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-semibold" x-text="suggestion.name"></p>
                                    <p class="truncate text-[11px] text-faint"
                                       x-text="(suggestion.trips.length > 1 ? @js(__('From :trip and :count more')) : @js(__('From :trip'))).replace(':trip', suggestion.trips[0].title).replace(':count', window.SoforNum(suggestion.trips.length - 1))"></p>
                                </div>
                                <button type="button" @click="addSuggestion(suggestion)" :disabled="busy"
                                        class="shrink-0 rounded-xl bg-brand px-3 py-1.5 text-[11px] font-semibold text-white transition hover:bg-brand-2">
                                    {{ __('Add') }}
                                </button>
                                <button type="button" @click="dismiss(suggestion)" aria-label="{{ __('Not this one') }}"
                                        class="flex size-7 shrink-0 items-center justify-center rounded-lg text-faint transition hover:bg-surface hover:text-ink">
                                    <x-icon name="close" class="size-3.5" />
                                </button>
                            </li>
                        </template>
                    </ul>
                </x-card>

                <x-card padding="p-4 sm:p-5">
                    <label for="travel-search" class="font-display text-sm font-semibold">{{ __('All :unit', ['unit' => $map->unit(2)]) }}</label>
                    <div class="relative mt-3">
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-faint">
                            <x-icon name="search" class="size-4" />
                        </span>
                        <input id="travel-search" type="search" x-model="query" autocomplete="off"
                               placeholder="{{ __('Find a :unit', ['unit' => $map->unit()]) }}" class="form-field pl-10 text-[13px]">
                    </div>

                    <div class="mt-4 max-h-[34rem] overflow-y-auto pr-1">
                        <template x-for="group in groupedAreas" :key="group.code">
                            <section class="mb-4 last:mb-0">
                                <h3 class="sticky top-0 z-10 flex items-center justify-between bg-surface py-1.5 text-[11px] font-semibold uppercase tracking-wide text-faint">
                                    <span x-text="group.name"></span>
                                    <span class="num" x-text="window.SoforNum(group.visited) + ' / ' + window.SoforNum(group.total)"></span>
                                </h3>
                                <ul class="mt-1 grid gap-1">
                                    <template x-for="area in group.areas" :key="area.id">
                                        <li>
                                            <button type="button" @click="toggle(area.id)" :aria-pressed="isVisited(area.id).toString()"
                                                    @mouseenter="hovered = area.id"
                                                    class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-[13px] transition hover:bg-surface-2"
                                                    :class="isVisited(area.id) ? 'font-semibold text-ink' : 'text-muted'">
                                                <span class="flex size-5 shrink-0 items-center justify-center rounded-md border-[1.5px] transition"
                                                      :class="isVisited(area.id) ? 'border-brand bg-brand text-white' : 'border-line-strong bg-surface'">
                                                    <x-icon name="check" class="size-3" x-show="isVisited(area.id)" />
                                                </span>
                                                <span class="min-w-0 flex-1 truncate" x-text="area.name"></span>
                                                <span x-show="area.counted === false" class="shrink-0 text-[10px] text-faint">{{ __('territory') }}</span>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </section>
                        </template>

                        <p x-show="groupedAreas.length === 0" x-cloak class="py-6 text-center text-[12px] text-muted">
                            {{ __('No :unit matches that.', ['unit' => $map->unit()]) }}
                        </p>
                    </div>
                </x-card>

                @if ($visited !== [])
                    <form method="POST" action="{{ route('travel.places.clear', $map->key) }}" class="text-center"
                          onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Clear every :unit from your :label map?', ['unit' => $map->unit(), 'label' => $map->label()])) }})">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-[12px] font-medium text-faint underline decoration-dotted underline-offset-2 transition hover:text-negative">
                            {{ __('Clear my :label map', ['label' => $map->label()]) }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
