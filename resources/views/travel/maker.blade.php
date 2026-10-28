@php
    // The same map component as a traveller's own map. A visitor's marks live in
    // the browser (local); a signed-in traveller's map is their saved one.
    $payload = [
        'local' => ! $account,
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
            'traveller' => $account ? auth()->user()->name : null,
            'logoUrl' => $logoUrl,
            'worldKey' => config('travel_maps.world'),
            'tabs' => collect($tabs)->mapWithKeys(fn (array $tab) => [$tab['key'] => $tab['visited']])->all(),
            'urls' => [
                'store' => $account ? route('travel.places.store', $map->key) : null,
                'area' => $account ? route('travel.places.destroy', [$map->key, 'AREAID']) : null,
            ],
        ],
        'areas' => $map->index(),
        'visited' => $visited,
        'suggestions' => [],
    ];

    // What people search for: "ভ্রমণ ম্যাপ বানাবো কিভাবে", "কয়টি জেলা ঘুরেছেন",
    // "bd travel map maker", "visited countries map". Each map answers its own.
    $isWorld = $map->key === config('travel_maps.world');
    $total = $map->countedTotal();
    $groupTotal = count($map->groups());

    $seoTitle = $isWorld
        ? __('Visited countries map maker: how many countries have you been to?')
        : __('Bangladesh travel map maker: how many districts have you visited?');
    $seoDescription = $isWorld
        ? __('Make a free visited countries map: tap every country you have been to, see your count out of :total and download the map as an image. No sign-up.', ['total' => $total])
        : __('Make your Bangladesh travel map free: tap the :total districts you have visited, see how many you have covered and download the map as an image. No sign-up.', ['total' => $total]);
    $question = $isWorld ? __('How many countries have you visited?') : __('How many districts have you visited?');

    $faqs = $isWorld
        ? [
            [__('How do I make a map of the countries I have visited?'), __('Open this page, tap every country you have been to and press Download image. The picture, with your name and your count, saves to your phone or computer.')],
            [__('How do I see how many countries I have visited?'), __('The counter above the map counts every country you mark out of :total, with the share of the world you have seen and how many of the :groups continents you have set foot on.', ['total' => $total, 'groups' => $groupTotal])],
            [__('Is the travel map free? Do I need an account?'), __('It is free and needs no account. Your marks stay in this browser. With a free Sofor account the map is saved for good, shows on your profile and counts on the Explorers board.')],
            [__('Can I make a map of the districts of Bangladesh too?'), __('Yes. Switch to the Bangladesh tab to mark the districts you have been to, out of 64, and download that map as well.')],
            [__('How do I share my travel map on Facebook?'), __('Press Download image, then post the picture on Facebook, Instagram or WhatsApp like any photo. On a phone, the Share button sends it straight to an app.')],
        ]
        : [
            [__('How do I make my travel map?'), __('Open this page, tap every district you have been to and press Download image. The picture, with your name and your count, saves to your phone or computer.')],
            [__('How do I see how many districts I have visited?'), __('The counter above the map counts every district you mark out of :total, with the share of Bangladesh you have covered and how many of the :groups divisions you have set foot in.', ['total' => $total, 'groups' => $groupTotal])],
            [__('Is the travel map free? Do I need an account?'), __('It is free and needs no account. Your marks stay in this browser. With a free Sofor account the map is saved for good, shows on your profile and counts on the Explorers board.')],
            [__('Can I make a world map of the countries I have visited?'), __('Yes. Switch to the Worldwide tab to mark every country you have been to, out of 195, and download that map too.')],
            [__('How do I share my travel map on Facebook?'), __('Press Download image, then post the picture on Facebook, Instagram or WhatsApp like any photo. On a phone, the Share button sends it straight to an app.')],
        ];

    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            \App\Support\Schema::freeTool($seoTitle, $canonical, $seoDescription, 'TravelApplication'),
            \App\Support\Schema::faq($faqs),
        ],
    ];

    // Every place on the map as plain text, by division or continent: the list
    // people search for ("বাংলাদেশের ৬৪ জেলার নাম", "countries visited checklist").
    $directory = collect($map->areas())
        ->filter(fn (array $area, string $id) => $map->isCounted($id))
        ->map(fn (array $area, string $id) => ['group' => $area['group'], 'name' => $map->name($id)])
        ->groupBy('group')
        ->map(fn ($areas) => $areas->pluck('name')->sort()->values())
        ->sortKeys();
@endphp

<x-layouts.site :title="$seoTitle" :wide="true" :card="false" :canonical="$canonical" :schema="$schema" :description="$seoDescription">

    <div class="mx-auto w-full max-w-6xl px-4 pb-6 pt-8 sm:px-6 sm:pt-10 lg:px-8" x-data="travelMap({{ \Illuminate\Support\Js::from($payload) }})">

        {{-- ===================== What this is ===================== --}}
        <header class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div class="min-w-0">
                <p class="eyebrow text-brand">{{ $account ? __('Your travel map') : ($isWorld ? __('Free world travel map') : __('Free Bangladesh travel map')) }}</p>
                <h1 class="mt-3 text-balance font-display text-[2.3rem] font-semibold leading-[1.05] tracking-tight sm:text-[3.2rem]">
                    {{ __('Make your travel map') }}
                    <span class="mt-1.5 block text-[0.62em] leading-tight text-muted">{{ $question }}</span>
                </h1>
                <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-muted">
                    @if ($account)
                        {{ __('Tap every :unit you have been to - it saves to your account - and download your travel map as a picture.', ['unit' => $map->unit()]) }}
                    @else
                        {{ __('Tap every :unit you have been to, put your name on it and download your travel map as a picture. Free, and no account needed.', ['unit' => $map->unit()]) }}
                    @endif
                </p>
            </div>
            <ul class="flex flex-wrap gap-2 text-[12px] font-semibold text-ink lg:max-w-xs lg:justify-end">
                @if ($account)
                    <li class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5"><x-icon name="check-circle" class="size-3.5 text-positive" /> {{ __('Saved to your account') }}</li>
                @else
                    <li class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5"><x-icon name="sparkles" class="size-3.5 text-accent-2" /> {{ __('No sign-up') }}</li>
                @endif
                <li class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5"><x-icon name="download" class="size-3.5 text-brand" /> {{ __('Free picture download') }}</li>
                @unless ($account)
                    <li class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5"><x-icon name="shield" class="size-3.5 text-brand" /> {{ __('Stays in this browser') }}</li>
                @endunless
            </ul>
        </header>

        {{-- One tab per map in config/travel_maps.php --}}
        <nav class="no-scrollbar -mx-1 mt-7 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="{{ __('Travel maps') }}">
            @foreach ($tabs as $tab)
                @php $current = $tab['key'] === $map->key; @endphp
                <a href="{{ $loop->first ? route('map-maker') : route('map-maker', $tab['key']) }}" @if ($current) aria-current="page" @endif
                   @class([
                       'flex shrink-0 items-center gap-2 rounded-2xl border-[1.5px] px-4 py-2.5 text-[13px] font-semibold transition',
                       'border-ink bg-brand text-white shadow-sticker-sm' => $current,
                       'border-line bg-surface text-muted hover:text-ink' => ! $current,
                   ])>
                    <x-icon :name="$tab['key'] === config('travel_maps.world') ? 'globe' : 'map-pin'" class="size-4" />
                    {{ $tab['label'] }}
                    <span @class(['num rounded-full px-2 py-0.5 text-[11px]', 'bg-white/20' => $current, 'bg-surface-2' => ! $current])>
                        <span x-text="window.SoforNum(tabVisited('{{ $tab['key'] }}'))">0</span>/{{ $tab['total'] }}
                    </span>
                </a>
            @endforeach
        </nav>

        {{-- Totals --}}
        <div class="mt-5 grid grid-cols-3 gap-2 sm:gap-3">
            <x-card padding="p-3 sm:p-5" class="min-w-0">
                <p class="eyebrow truncate text-faint">{{ __('Visited') }}</p>
                <p class="mt-1.5 font-display text-2xl font-semibold sm:mt-2 sm:text-3xl">
                    <span class="num" x-text="window.SoforNum(stats.visited)">0</span><span class="text-sm text-faint sm:text-lg"> / {{ $map->countedTotal() }}</span>
                </p>
                <p class="mt-1 hidden text-[12px] text-muted sm:block">{{ $map->unit(2) }}</p>
            </x-card>
            <x-card padding="p-3 sm:p-5" class="min-w-0">
                <p class="eyebrow truncate text-faint">{{ __('Explored') }}</p>
                <p class="mt-1.5 font-display text-2xl font-semibold text-brand sm:mt-2 sm:text-3xl"><span class="num" x-text="window.SoforNum(stats.percent)">0</span>%</p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2 sm:mt-2.5 sm:h-2">
                    <div class="h-full rounded-full bg-brand transition-all duration-500" style="width: 0%" :style="`width: ${stats.percent}%`"></div>
                </div>
            </x-card>
            <x-card padding="p-3 sm:p-5" class="min-w-0">
                <p class="eyebrow truncate text-faint">{{ ucfirst($map->groupUnit(2)) }}</p>
                <p class="mt-1.5 font-display text-2xl font-semibold sm:mt-2 sm:text-3xl">
                    <span class="num" x-text="window.SoforNum(stats.groupsVisited)">0</span><span class="text-sm text-faint sm:text-lg"> / {{ count($map->groups()) }}</span>
                </p>
                <p class="mt-1 hidden text-[12px] text-muted sm:block">{{ __('with at least one :unit visited', ['unit' => $map->unit()]) }}</p>
            </x-card>
        </div>

        <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)] lg:items-start">
            {{-- ===================== The map and the picture ===================== --}}
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
                <x-travel-map :map="$map" :visited="$visited" class="mt-3" aria-hidden="true" x-ref="svg" x-bind:class="busy ? 'cursor-progress' : ''"
                              @click="pick($event)" @mouseover="hover($event)" @mouseleave="hovered = null" />

                {{-- Make it yours, then download it --}}
                <div class="mt-4 grid gap-4 border-t border-line px-1 pt-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    @if ($account)
                        {{-- The picture carries the traveller's own name. --}}
                        <p class="flex min-w-0 items-center gap-2 text-[12.5px] text-muted">
                            <x-icon name="check-circle" class="size-4 shrink-0 text-positive" />
                            <span>{{ __('Every tap saves to your account.') }}</span>
                        </p>
                    @else
                        <div class="min-w-0">
                            <label for="map-name" class="text-[12px] font-semibold text-ink">{{ __('Name on the picture') }}</label>
                            <input id="map-name" type="text" maxlength="40" autocomplete="given-name" x-model="travellerName"
                                   placeholder="{{ __('Your name (optional)') }}" class="form-field mt-1.5 h-11 text-[14px]">
                        </div>
                    @endif
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
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 px-1">
                    <p class="text-[12px] text-muted" x-text="title() + ' · ' + subtitle()"></p>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-btn type="button" size="sm" variant="secondary" icon="share" x-show="canShare" x-cloak
                               @click="share()" x-bind:disabled="exporting">{{ __('Share') }}</x-btn>
                        <x-btn type="button" icon="download" @click="download()" x-bind:disabled="exporting">
                            <span x-text="exporting ? {{ \Illuminate\Support\Js::from(__('Making image...')) }} : {{ \Illuminate\Support\Js::from(__('Download image')) }}">{{ __('Download image') }}</span>
                        </x-btn>
                    </div>
                </div>

                <p class="mt-3 px-1 text-[10px] text-faint" translate="no">{{ $map->attribution() }}</p>
            </x-card>

            {{-- ===================== Keep it, and the full list ===================== --}}
            <div class="flex min-w-0 flex-col gap-6">
                @if ($account)
                    {{-- Marked here before signing in, in this browser, and not on the saved map yet. --}}
                    <div x-show="pendingDraft.length > 0" x-cloak class="rounded-4xl border-[1.5px] border-accent/40 bg-accent-tint/70 p-5">
                        <div class="flex items-start gap-3">
                            <x-glyph name="compass" class="size-9 shrink-0" />
                            <p class="min-w-0 text-[13px] text-ink"
                               x-text="{{ \Illuminate\Support\Js::from(__('You marked :count places on the map maker before signing in. Add them to this map?')) }}.replace(':count', window.SoforNum(pendingDraft.length))"></p>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <x-btn type="button" size="sm" icon="check" @click="addDraft()" x-bind:disabled="busy">{{ __('Add them') }}</x-btn>
                            <button type="button" @click="forgetDraft()" class="rounded-full px-3 py-1.5 text-[12px] font-medium text-muted transition hover:text-ink">{{ __('No thanks') }}</button>
                        </div>
                    </div>

                    <div class="rounded-4xl border-[1.5px] border-brand/30 bg-brand-tint/60 p-5">
                        <div class="flex items-start gap-3">
                            <x-glyph name="settled" class="size-10 shrink-0" />
                            <div class="min-w-0">
                                <h2 class="font-display text-base font-semibold">{{ __('This is your saved travel map') }}</h2>
                                <p class="mt-1 text-[12.5px] leading-relaxed text-muted">{{ __('Every place you mark is saved to your account straight away. It shows on your profile and counts on Explorers.') }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-btn :href="route('travel.show', $map->key)" size="sm" icon="globe">{{ __('Open my travel map') }}</x-btn>
                            @if (filled(auth()->user()->username) && auth()->user()->profile_public)
                                <x-btn :href="auth()->user()->publicUrl()" size="sm" variant="secondary" icon="user">{{ __('My profile') }}</x-btn>
                            @endif
                        </div>
                    </div>
                @elseif ($needsConfirm)
                    <div class="rounded-4xl border-[1.5px] border-line bg-surface p-5">
                        <div class="flex items-start gap-3">
                            <x-glyph name="maybe" class="size-9 shrink-0" />
                            <div class="min-w-0">
                                <h2 class="font-display text-base font-semibold">{{ __('Confirm your account to save this map') }}</h2>
                                <p class="mt-1 text-[12.5px] leading-relaxed text-muted">{{ __('For now the map stays in this browser. Confirm your email or phone and it is saved to your account.') }}</p>
                            </div>
                        </div>
                        <x-btn :href="route('verification.notice')" size="sm" icon-after="arrow-right" class="mt-4">{{ __('Confirm it') }}</x-btn>
                    </div>
                @elseif (auth()->guest())
                    <div class="relative isolate overflow-hidden rounded-4xl border-[1.5px] border-ink bg-ink p-5 text-canvas">
                        <div aria-hidden="true" class="absolute -right-16 -top-16 -z-10 size-48 rounded-full opacity-50 blur-3xl" style="background: radial-gradient(circle, #0f6f6a, transparent 65%)"></div>
                        <h2 class="font-display text-lg font-semibold">{{ __('Keep your map for good') }}</h2>
                        <p class="mt-1 text-[12.5px] leading-relaxed text-canvas/65">{{ __('This map lives in this browser only. With a free account it is saved for good, and:') }}</p>
                        <ul class="mt-4 flex flex-col gap-2.5 text-[13px]">
                            <li class="flex items-center gap-2.5"><x-glyph name="going" class="size-5 shrink-0" /> {{ __('Opens on any phone or computer') }}</li>
                            <li class="flex items-center gap-2.5"><x-glyph name="going" class="size-5 shrink-0" /> {{ __('Shows on your public travel profile') }}</li>
                            <li class="flex items-center gap-2.5"><x-glyph name="going" class="size-5 shrink-0" /> {{ __('Puts you on the Explorers board') }}</li>
                        </ul>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <x-btn :href="route('register')" variant="accent" icon-after="arrow-right" class="flex-1">{{ __('Create a free account') }}</x-btn>
                            <a href="{{ route('login') }}" class="inline-flex h-11 items-center rounded-full px-4 text-[13px] font-semibold text-canvas/80 transition hover:bg-canvas/10 hover:text-canvas">{{ __('Log in') }}</a>
                        </div>
                        <p class="mt-3 text-[11px] text-canvas/50">{{ __('Your marks come with you: after signing up, your travel map offers to add them.') }}</p>
                    </div>
                @endif

                <x-card padding="p-4 sm:p-5">
                    <label for="travel-search" class="font-display text-sm font-semibold">{{ __('All :unit', ['unit' => $map->unit(2)]) }}</label>
                    <div class="relative mt-3">
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-faint"><x-icon name="search" class="size-4" /></span>
                        <input id="travel-search" type="search" x-model="query" autocomplete="off"
                               placeholder="{{ __('Find a :unit', ['unit' => $map->unit()]) }}" class="form-field pl-10 text-[13px]">
                    </div>

                    <div class="mt-4 max-h-[30rem] overflow-y-auto pr-1">
                        <template x-for="group in groupedAreas" :key="group.code">
                            <section class="mb-4 last:mb-0">
                                <h3 class="sticky top-0 z-10 flex items-center justify-between bg-surface py-1.5 text-[11px] font-semibold uppercase tracking-wide text-faint">
                                    <span x-text="group.name"></span>
                                    <span class="num" x-text="window.SoforNum(group.visited) + ' / ' + window.SoforNum(group.total)"></span>
                                </h3>
                                <ul class="mt-1 grid gap-1">
                                    <template x-for="area in group.areas" :key="area.id">
                                        <li>
                                            <button type="button" @click="toggle(area.id)" :aria-pressed="isVisited(area.id).toString()" @mouseenter="hovered = area.id"
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

                        <p x-show="groupedAreas.length === 0" x-cloak class="py-6 text-center text-[12px] text-muted">{{ __('No :unit matches that.', ['unit' => $map->unit()]) }}</p>
                    </div>
                </x-card>

                <button type="button" x-show="local && Object.keys(visited).length > 0" x-cloak
                        @click="clearLocal({{ \Illuminate\Support\Js::from(__('Clear every :unit you marked on this map?', ['unit' => $map->unit()])) }})"
                        class="text-center text-[12px] font-medium text-faint underline decoration-dotted underline-offset-2 transition hover:text-negative">
                    {{ __('Start this map over') }}
                </button>
            </div>
        </div>

        {{-- ===================== How to, and questions ===================== --}}
        <section class="mt-16 grid gap-10 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-16">
            <div>
                <h2 class="font-display text-[1.9rem] font-semibold leading-[1.1] tracking-tight text-ink">{{ __('How to make your travel map') }}</h2>
                <ol class="mt-6 flex flex-col gap-5">
                    @foreach ([
                        [__('Tap the places you have been'), $isWorld ? __('Tap a country on the map, or tick it in the list. Tap again to take it off.') : __('Tap a district on the map, or tick it in the list. Tap again to take it off.')],
                        [__('Put your name on it'), __('Add your name and pick the colours you like. The counter shows how many you have covered.')],
                        [__('Download and share'), __('Download the map as an image and post it on Facebook, Instagram or WhatsApp. It is free, and no account is needed.')],
                    ] as $step => [$heading, $text])
                        <li class="flex gap-4">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent font-display text-[15px] font-semibold text-ink">{{ $step + 1 }}</span>
                            <div>
                                <h3 class="font-display text-[16.5px] font-semibold text-ink">{{ $heading }}</h3>
                                <p class="mt-1 text-[14px] leading-relaxed text-muted">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-8 text-[14px] text-muted">
                    {{ __('Splitting the cost of a trip?') }}
                    <a href="{{ route('split-calculator') }}" class="font-semibold text-brand underline decoration-brand/30 underline-offset-2 hover:decoration-brand">{{ __('Trip split calculator') }}</a>
                </p>
            </div>

            <div>
                <h2 class="font-display text-[1.9rem] font-semibold leading-[1.1] tracking-tight text-ink">{{ __('Travel map questions') }}</h2>
                <x-faq-list :faqs="$faqs" id="map-faq" class="mt-4" />
            </div>
        </section>

        {{-- ===================== Every place, as text ===================== --}}
        <section class="mt-16">
            <h2 class="font-display text-[1.6rem] font-semibold leading-tight tracking-tight text-ink">
                {{ $isWorld ? __('All :total countries of the world, by continent', ['total' => $total]) : __('All :total districts of Bangladesh, by division', ['total' => $total]) }}
            </h2>
            <div class="mt-6 columns-1 gap-6 sm:columns-2 lg:columns-4">
                @foreach ($directory as $group => $names)
                    <div class="mb-6 break-inside-avoid">
                        <h3 class="flex items-baseline justify-between gap-2 border-b border-line pb-1.5 font-display text-[14.5px] font-semibold text-ink">
                            {{ $map->groups()[$group] ?? $group }}
                            <span class="text-[12px] font-medium text-faint">{{ $names->count() }}</span>
                        </h3>
                        <p class="mt-2 text-[13px] leading-relaxed text-muted">{{ $names->implode(', ') }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.site>
