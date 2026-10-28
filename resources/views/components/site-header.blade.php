{{-- The public site's header: the logo, three mega menus (Explore, Product,
     Community), the language switch and the way in. Below lg the menus
     move into a panel that slides in from the right. Lives inside the site
     layout's x-data="siteNav" (resources/js/site-nav.js). --}}
@php
    $user = auth()->user();
    $traveller = $user !== null && ! $user->isAdmin();
    $home = route('home');

    $explore = [
        ['glyph' => 'peak', 'label' => __('Explorers'), 'text' => __('Who has seen the most of Bangladesh, and the world.'), 'href' => route('explorers'), 'current' => request()->routeIs('explorers')],
        ['glyph' => 'plane', 'label' => __('Make a travel map'), 'text' => __('Mark where you have been and download the picture.'), 'href' => route('map-maker'), 'current' => request()->routeIs('map-maker')],
    ];

    if ($traveller) {
        $explore[] = ['glyph' => 'hills', 'label' => __('Your travel map'), 'text' => __('Every district and country you have marked.'), 'href' => route('travel.index'), 'current' => false];

        // Accounts get a username in a migration; until it runs there is no profile to link to.
        if (filled($user->username)) {
            $explore[] = ['glyph' => 'camera', 'label' => __('My profile'), 'text' => __('Your public explorer page.'), 'href' => $user->publicUrl(), 'current' => false];
        }
    }

    $menus = [
        'explore' => [
            'label' => __('Explore'),
            'current' => request()->routeIs('explorers', 'map-maker', 'profile.public'),
            'items' => $explore,
        ],
        'product' => [
            'label' => __('Product'),
            'current' => false,
            'items' => [
                ['glyph' => 'roadtrip', 'label' => __('How it works'), 'text' => __('Make a trip, bring the group in, settle up.'), 'href' => $home.'#how', 'current' => false],
                ['glyph' => 'backpack', 'label' => __('Features'), 'text' => __('Fair splits, the fewest payments, one invite link.'), 'href' => $home.'#features', 'current' => false],
                ['glyph' => 'ticket', 'label' => __('Trip split calculator'), 'text' => __('Who owes who, worked out in seconds. Free.'), 'href' => route('split-calculator'), 'current' => request()->routeIs('split-calculator')],
                ['glyph' => 'guide', 'label' => __('FAQ'), 'text' => __('Quick answers before you start.'), 'href' => $home.'#faq', 'current' => false],
            ],
        ],
        'community' => [
            'label' => __('Community'),
            'current' => request()->routeIs('about', 'sponsors', 'support.*', 'privacy', 'terms'),
            'items' => [
                ['glyph' => 'compass', 'label' => __('About'), 'text' => __('Who makes Sofor, and why.'), 'href' => route('about'), 'current' => request()->routeIs('about')],
                ['glyph' => 'parcel', 'label' => __('Sponsors'), 'text' => __('The people and brands keeping Sofor free.'), 'href' => route('sponsors'), 'current' => request()->routeIs('sponsors')],
                ['glyph' => 'chat', 'label' => __('Contact'), 'text' => __('Write to hello@sofor.io, we read everything.'), 'href' => 'mailto:hello@sofor.io', 'current' => false],
                ['glyph' => 'going', 'label' => __('Privacy'), 'text' => __('No ads, no tracking. What we keep, and why.'), 'href' => route('privacy'), 'current' => request()->routeIs('privacy')],
            ],
        ],
    ];

    $start = match (true) {
        $user === null => ['href' => route('register'), 'label' => __('Start a trip'), 'title' => __('Plan your next trip in two minutes.')],
        $user->isAdmin() => ['href' => route('admin.dashboard'), 'label' => __('Go to the admin console'), 'title' => __('Everything Sofor runs on, in one place.')],
        default => ['href' => route('dashboard'), 'label' => __('Go to my trips'), 'title' => __('Your trips are waiting.')],
    };
@endphp

<header class="site-header sticky top-0 z-50 safe-top" :class="{ 'is-raised': scrolled || open }" @keydown.escape="close(true)">
    {{-- Below 400px everything tightens a little (see .site-logo in app.css), so the
         logo - even a wide uploaded one - the language switch, "Get started" and
         the menu button share one line on a 360px phone. --}}
    <div class="relative mx-auto flex w-full max-w-6xl items-center gap-2 px-3 py-2.5 min-[400px]:px-4 sm:gap-3 sm:px-6 lg:px-8">
        <div class="site-logo flex min-w-0 lg:flex-1">
            <a href="{{ $home }}" aria-label="{{ __('Sofor home') }}" class="min-w-0 rounded-2xl"><x-logo compact /></a>
        </div>

        {{-- Desktop: three mega menus, open on hover or click. --}}
        <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('Primary') }}" @click.outside="open && close()">
            @foreach ($menus as $key => $menu)
                <div @pointerenter="hover('{{ $key }}', $event)" @pointerleave="unhover($event)"
                     @focusout="$el.contains($event.relatedTarget) || open !== '{{ $key }}' || close()">
                    <button type="button" data-mega-trigger="{{ $key }}" @click="toggle('{{ $key }}')"
                            aria-expanded="false" :aria-expanded="open === '{{ $key }}' ? 'true' : 'false'" aria-controls="mega-{{ $key }}"
                            @if ($menu['current']) data-current @endif
                            :class="{ 'bg-surface text-ink! shadow-soft': open === '{{ $key }}' }"
                            class="nav-trigger relative flex items-center gap-1 whitespace-nowrap rounded-full py-2 pl-3.5 pr-2.5 text-[13.5px] font-semibold transition {{ $menu['current'] ? 'text-ink' : 'text-muted hover:text-ink' }}">
                        {{ $menu['label'] }}
                        <span class="inline-flex text-faint transition-transform duration-200" :class="{ 'rotate-180': open === '{{ $key }}' }">
                            <x-icon name="chevron-down" class="size-3.5" />
                        </span>
                    </button>

                    <div id="mega-{{ $key }}" x-show="open === '{{ $key }}'" x-cloak
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1.5" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="absolute inset-x-0 top-full px-4 pt-2 sm:px-6 lg:px-8">
                        <div class="mx-auto grid max-w-[58rem] grid-cols-[minmax(0,1fr)_16.5rem] gap-2 rounded-[1.75rem] border border-line bg-surface p-2 shadow-sheet">
                            <ul @class(['grid content-start gap-1 p-1.5', 'grid-cols-2' => count($menu['items']) > 2])>
                                @foreach ($menu['items'] as $item)
                                    <li>
                                        <a href="{{ $item['href'] }}" @click="close()" @if ($item['current']) aria-current="page" @endif
                                           @class(['group flex items-start gap-3.5 rounded-2xl p-3 outline-none transition hover:bg-canvas-tint focus-visible:bg-canvas-tint focus-visible:ring-2 focus-visible:ring-brand/40', 'bg-canvas-tint' => $item['current']])>
                                            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl border border-line bg-canvas transition duration-300 group-hover:-rotate-6 group-hover:scale-105 group-hover:bg-surface">
                                                <x-glyph :name="$item['glyph']" class="size-7" />
                                            </span>
                                            <span class="min-w-0 pt-0.5">
                                                <span class="flex items-center gap-1.5 font-display text-[14.5px] font-semibold text-ink">
                                                    {{ $item['label'] }}
                                                    <x-icon name="arrow-right" class="size-3.5 -translate-x-1 text-brand opacity-0 transition duration-200 group-hover:translate-x-0 group-hover:opacity-100" />
                                                </span>
                                                <span class="mt-0.5 block text-[12.5px] leading-snug text-muted">{{ $item['text'] }}</span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($key === 'product')
                                {{-- The way in, for whoever is reading. --}}
                                <div class="relative flex flex-col justify-between overflow-hidden rounded-[1.4rem] bg-brand p-5 text-white">
                                    <svg viewBox="0 0 200 120" fill="none" class="pointer-events-none absolute -bottom-3 -right-10 w-52 opacity-20" aria-hidden="true">
                                        <path d="M6 108c26-58 50-86 76-86 25 0 33 30 52 30 14 0 24-10 30-24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="5 7" />
                                        <circle cx="164" cy="28" r="9" fill="var(--accent)" stroke="currentColor" stroke-width="3" />
                                    </svg>
                                    <div class="relative">
                                        <p class="font-mono text-[10.5px] font-bold uppercase tracking-[0.14em] text-white/70">{{ __('Free forever') }}</p>
                                        <p class="mt-2 font-display text-[19px] font-semibold leading-snug">{{ $start['title'] }}</p>
                                    </div>
                                    <a href="{{ $start['href'] }}" @click="close()"
                                       class="relative mt-8 inline-flex items-center gap-1.5 self-start rounded-full bg-white px-4 py-2 text-[13px] font-semibold text-brand transition hover:bg-accent hover:text-ink">
                                        {{ $start['label'] }} <x-icon name="arrow-right" class="size-3.5" />
                                    </a>
                                </div>
                            @elseif ($key === 'explore')
                                {{-- A small night sky, like the Explorers board itself. --}}
                                <a href="{{ route('explorers') }}" @click="close()" class="mega-night group relative flex flex-col justify-between overflow-hidden rounded-[1.4rem] p-5 text-white">
                                    <svg viewBox="0 0 200 140" class="pointer-events-none absolute inset-0 size-full" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                                        <path d="M120 30 148 52 174 40 186 74 158 96" fill="none" stroke="rgba(246,178,58,.4)" stroke-width="1" stroke-dasharray="2 3" />
                                        @foreach ([[120, 30, 2.4, 0], [148, 52, 3.2, .6], [174, 40, 2, 1.2], [186, 74, 2.6, .3], [158, 96, 2.2, 1.6], [96, 16, 1.2, .9], [30, 22, 1, 2], [60, 60, 1.1, .4], [196, 118, 1.2, 1]] as [$cx, $cy, $r, $delay])
                                            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="{{ $r > 2 ? '#f6b23a' : '#fbf8f2' }}" class="star-twinkle" style="animation-delay: {{ $delay }}s"></circle>
                                        @endforeach
                                    </svg>
                                    <div class="relative">
                                        <p class="font-mono text-[10.5px] font-bold uppercase tracking-[0.14em] text-white/60">{{ __('Explorers') }}</p>
                                        <p class="mt-2 font-display text-[19px] font-semibold leading-snug">{{ __('Climb the Explorers board') }}</p>
                                        <p class="mt-1.5 text-[12.5px] leading-snug text-white/70">{{ __('Every place you mark lifts you higher.') }}</p>
                                    </div>
                                    <span class="relative mt-8 inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#f6b23a]">
                                        {{ __('See the board') }} <x-icon name="arrow-right" class="size-3.5 transition group-hover:translate-x-0.5" />
                                    </span>
                                </a>
                            @else
                                {{-- The one ask. --}}
                                <a href="{{ route('support.index') }}" @click="close()" class="group relative flex flex-col justify-between overflow-hidden rounded-[1.4rem] border border-accent/40 bg-accent-tint p-5">
                                    <span class="flex size-11 items-center justify-center rounded-2xl bg-accent text-ink shadow-sticker-sm transition group-hover:-rotate-6"><x-icon name="gift" class="size-5" /></span>
                                    <div class="mt-5">
                                        <p class="font-display text-[17px] font-semibold leading-snug text-ink">{{ __('Support Sofor with bKash') }}</p>
                                        <p class="mt-1.5 text-[12.5px] leading-snug text-muted">{{ __('Any amount keeps Sofor free for every group.') }}</p>
                                    </div>
                                    <span class="mt-5 inline-flex items-center gap-1.5 text-[13px] font-semibold text-ink">
                                        {{ __('Support us') }} <x-icon name="arrow-right" class="size-3.5 transition group-hover:translate-x-0.5" />
                                    </span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="ml-auto flex shrink-0 items-center gap-1.5 min-[400px]:gap-2 lg:ml-0 lg:flex-1 lg:justify-end">
            <x-locale-switch short />

            @guest
                <a href="{{ route('login') }}" class="hidden whitespace-nowrap rounded-full px-3 py-2 text-[13.5px] font-semibold text-muted transition hover:text-ink sm:block">{{ __('Log in') }}</a>
                <x-btn :href="route('register')" size="sm" class="hidden whitespace-nowrap px-3.5! min-[360px]:inline-flex min-[400px]:px-4!">{{ __('Get started') }}</x-btn>
            @else
                @if ($user->isAdmin())
                    <x-btn :href="route('admin.dashboard')" size="sm" icon="shield" class="hidden whitespace-nowrap sm:inline-flex">{{ __('Admin console') }}</x-btn>
                @else
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center gap-2 whitespace-nowrap rounded-full border border-line bg-surface py-1 pl-1 pr-1 text-[13px] font-semibold text-ink shadow-soft transition hover:border-line-strong min-[360px]:pr-3.5">
                        <x-avatar :name="$user->name" :gradient="$user->avatarGradient()" :src="$user->avatarUrl()" size="xs" :ring="false" class="rounded-full!" />
                        <span class="hidden min-[360px]:inline">{{ __('My trips') }}</span>
                    </a>
                @endif
            @endguest

            <button type="button" @click="menu = true"
                    class="flex size-9 shrink-0 items-center justify-center rounded-2xl border border-line bg-surface text-muted transition active:scale-95 hover:text-ink min-[400px]:size-10 lg:hidden"
                    aria-label="{{ __('Open menu') }}" aria-haspopup="dialog" :aria-expanded="menu ? 'true' : 'false'">
                <x-icon name="menu" class="size-5" />
            </button>
        </div>
    </div>
</header>

{{-- Phones and tablets: the same menus, sliding in from the right. Outside the
     header, whose backdrop blur would otherwise pin this to the header. --}}
<div x-show="menu" x-cloak class="fixed inset-0 z-[80] lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}">
    <div class="absolute inset-0 sheet-backdrop" x-show="menu" x-transition.opacity.duration.200ms @click="menu = false"></div>

    <aside x-show="menu"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="translate-x-full"
           class="absolute inset-y-0 right-0 flex w-[88%] max-w-[22rem] flex-col border-l border-line bg-canvas safe-top">

        <div class="flex items-center justify-between gap-3 px-5 pb-2 pt-4">
            <x-logo size="sm" />
            <button type="button" @click="menu = false"
                    class="flex size-9 items-center justify-center rounded-xl border border-line bg-surface text-muted transition hover:text-ink"
                    aria-label="{{ __('Close menu') }}">
                <x-icon name="close" class="size-4.5" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto overscroll-contain px-4 pb-5" aria-label="{{ __('Mobile') }}">
            @foreach ($menus as $key => $menu)
                <p class="px-1.5 pb-2 pt-5 font-mono text-[10.5px] font-bold uppercase tracking-[0.14em] text-faint">{{ $menu['label'] }}</p>
                <ul class="overflow-hidden rounded-2xl border border-line bg-surface">
                    @foreach ($menu['items'] as $item)
                        <li class="border-t border-line first:border-t-0">
                            <a href="{{ $item['href'] }}" @click="menu = false" @if ($item['current']) aria-current="page" @endif
                               @class(['flex items-center gap-3 px-3 py-2.5 transition hover:bg-canvas-tint active:bg-canvas-tint', 'bg-canvas-tint' => $item['current']])>
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-canvas"><x-glyph :name="$item['glyph']" class="size-6" /></span>
                                <span class="min-w-0 flex-1 text-[14.5px] font-semibold text-ink">{{ $item['label'] }}</span>
                                <x-icon name="chevron-right" class="size-4 text-faint" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach

            <a href="{{ route('support.index') }}" @click="menu = false"
               class="mt-4 flex items-center gap-3 rounded-2xl border border-accent/40 bg-accent-tint px-3 py-3 transition hover:border-accent">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-accent"><x-icon name="gift" class="size-4.5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[14px] font-semibold text-ink">{{ __('Support Sofor with bKash') }}</span>
                    <span class="block text-[12px] text-muted">{{ __('No ads, no tracking') }}</span>
                </span>
                <x-icon name="arrow-right" class="size-4 text-ink" />
            </a>
        </nav>

        <div class="flex flex-col gap-3 border-t border-line bg-surface p-4 safe-bottom">
            <div class="flex items-center justify-between gap-3">
                <span class="flex items-center gap-1.5 text-[12.5px] font-semibold text-muted"><x-icon name="globe" class="size-4 text-brand" /> {{ __('Language') }}</span>
                <x-locale-switch />
            </div>
            @guest
                <x-btn :href="route('register')" block icon-after="arrow-right">{{ __('Claim your free account') }}</x-btn>
                <x-btn :href="route('login')" variant="outline" block>{{ __('Log in') }}</x-btn>
            @else
                <x-btn :href="$start['href']" block :icon="$user->isAdmin() ? 'shield' : 'compass'">{{ $start['label'] }}</x-btn>
            @endguest
        </div>
    </aside>
</div>
