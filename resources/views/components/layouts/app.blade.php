@props([
    'title' => null,
    'active' => null,
    'heading' => null,
    'subtitle' => null,
    'back' => null,
    'wide' => false,
])

@php
    $user = auth()->user();
    $unread = $user?->unreadNotifications()->count() ?? 0;

    $nav = [
        ['key' => 'dashboard', 'label' => __('Dashboard'), 'icon' => 'grid', 'href' => route('dashboard')],
        ['key' => 'trips', 'label' => __('My trips'), 'icon' => 'compass', 'href' => route('trips.index')],
        ['key' => 'balances', 'label' => __('Balances'), 'icon' => 'wallet', 'href' => route('balances.index')],
        ['key' => 'map', 'label' => __('Travel map'), 'icon' => 'globe', 'href' => route('travel.index')],
        ['key' => 'explorers', 'label' => __('Explorers'), 'icon' => 'flag', 'href' => route('explorers')],
        ['key' => 'create', 'label' => __('Start a trip'), 'icon' => 'plus', 'href' => route('trips.create')],
        ['key' => 'notifications', 'label' => __('Alerts'), 'icon' => 'bell', 'href' => route('notifications.index'), 'badge' => $unread],
        ['key' => 'wallet', 'label' => __('SMS wallet'), 'icon' => 'send', 'href' => route('wallet.index')],
        ['key' => 'profile', 'label' => __('Profile'), 'icon' => 'user', 'href' => route('profile.edit')],
    ];

    // The phone tab bar: four places and the menu. Everything else lives in the
    // menu, and its tab lights up on those pages.
    $tabs = [
        ['key' => 'dashboard', 'label' => __('Home'), 'icon' => 'home', 'href' => route('dashboard')],
        ['key' => 'trips', 'label' => __('My trips'), 'icon' => 'compass', 'href' => route('trips.index')],
        ['key' => 'create', 'label' => __('New trip'), 'icon' => 'plus', 'href' => route('trips.create')],
        ['key' => 'notifications', 'label' => __('Alerts'), 'icon' => 'bell', 'href' => route('notifications.index'), 'badge' => $unread],
    ];
    $inMenu = in_array($active, ['balances', 'map', 'explorers', 'wallet', 'profile'], true);

    $shortcuts = [
        ['key' => 'balances', 'label' => __('Balances'), 'icon' => 'wallet', 'href' => route('balances.index'), 'tone' => 'bg-positive-tint text-positive'],
        ['key' => 'map', 'label' => __('Travel map'), 'icon' => 'globe', 'href' => route('travel.index'), 'tone' => 'bg-info-tint text-info'],
        ['key' => 'explorers', 'label' => __('Explorers'), 'icon' => 'flag', 'href' => route('explorers'), 'tone' => 'bg-warning-tint text-warning'],
        ['key' => 'wallet', 'label' => __('SMS wallet'), 'icon' => 'send', 'href' => route('wallet.index'), 'tone' => 'bg-negative-tint text-negative'],
    ];

    $about = [
        [__('Support Sofor'), route('support.index'), 'gift', 'bg-accent-tint text-accent-2', __('Keep Sofor free')],
        [__('About Sofor'), route('about'), 'info', 'bg-brand-tint text-brand', null],
        [__('Terms of service'), route('terms'), 'shield', 'bg-surface-2 text-muted', null],
        [__('Privacy policy'), route('privacy'), 'lock', 'bg-surface-2 text-muted', null],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<x-head :title="$title" :description="__('Your Sofor trips and money ledger.')" :noindex="true" />

{{-- data-reload-on-change: a cost, payment or alert saved in another tab brings this page up to date.
     The phone menu and the alerts are panels over the page (resources/js/sheet.js): the
     store says which one is open, and a phone's sheet holds the page behind it still. --}}
<body class="relative min-h-dvh bg-canvas font-sans text-ink antialiased" data-reload-on-change
      x-data @keydown.escape.window="$store.sheets.close()"
      x-effect="document.body.classList.toggle('overflow-hidden', $store.sheets.locked)">
    <x-skip-link />
    <x-backdrop />

    <div class="relative mx-auto flex min-h-dvh w-full lg:grid lg:grid-cols-[264px_minmax(0,1fr)]">

        {{-- ===================== Desktop sidebar ===================== --}}
        <aside class="sticky top-0 hidden h-dvh flex-col gap-5 border-r border-line bg-surface px-4 py-5 lg:flex">
            <div class="flex items-center gap-2 px-1">
                <a href="{{ route('dashboard') }}"><x-logo /></a>
            </div>

            <nav class="flex flex-1 flex-col gap-1" aria-label="{{ __('App') }}">
                @foreach ($nav as $item)
                    <x-nav-link :href="$item['href']" :icon="$item['icon']" :active="$active === $item['key']">
                        {{ $item['label'] }}
                        @if (array_key_exists('badge', $item))
                            <span data-alerts-badge @if ($item['badge'] === 0) hidden @endif
                                  class="num ml-auto rounded-full bg-negative-tint px-2 py-0.5 text-[10px] font-bold text-negative">{{ $item['badge'] }}</span>
                        @endif
                    </x-nav-link>
                @endforeach
            </nav>

            {{-- The ask: donations keep Sofor free, so it is impossible to miss but never nagging. --}}
            <div class="rounded-4xl border border-line bg-surface-2 p-4">
                <p class="flex items-center gap-2 font-display text-[14px] font-semibold text-ink">
                    <x-icon name="gift" class="size-4 text-brand" />
                    {{ __('Keep Sofor free') }}
                </p>
                <p class="mt-1.5 text-[12px] leading-relaxed text-muted">
                    {{ __('No ads, no card required. If Sofor saved your group some arguing, support it with bKash.') }}
                </p>
                <x-btn :href="route('support.index')" variant="outline" size="sm" block class="mt-3">{{ __('Support Sofor') }}</x-btn>
            </div>

            <div class="rounded-4xl border border-line bg-surface-2 p-3">
                <div class="flex items-center gap-3">
                    <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-semibold text-ink">{{ $user?->name }}</p>
                        <p class="truncate text-[11px] text-faint">{{ $user ? '@'.$user->username : '' }}</p>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button type="submit" title="{{ __('Sign out') }}"
                                class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-line bg-surface text-[11.5px] font-semibold text-muted transition hover:border-negative/40 hover:text-negative">
                            <x-icon name="logout" class="size-3.5" /> {{ __('Sign out') }}
                        </button>
                    </form>
                </div>
                <x-locale-switch class="mt-2 w-full" />
            </div>
        </aside>

        <div class="flex min-w-0 flex-col">
            {{-- ===================== Top bar ===================== --}}
            {{-- On a phone the tab bar carries the menu and the alerts, so the bar keeps to the page's own title. --}}
            <header class="sticky top-0 z-40 border-b border-line/70 glass safe-top">
                <div class="mx-auto flex min-h-14 w-full items-center gap-1.5 px-3 py-2 sm:px-6 lg:gap-3 lg:px-8 lg:py-3">
                    @if ($back)
                        <a href="{{ $back }}" aria-label="{{ __('Back') }}"
                           class="-ml-1.5 flex size-10 shrink-0 touch-manipulation items-center justify-center rounded-full text-ink transition active:scale-90 active:bg-surface-2 lg:hidden">
                            <x-icon name="chevron-left" class="size-6" />
                        </a>
                    @endif

                    <div @class(['min-w-0 flex-1', 'pl-1 lg:pl-0' => ! $back])>
                        @if ($heading)
                            <h1 class="truncate font-display text-[17px] font-semibold leading-tight text-ink sm:text-xl">{{ \App\Support\Lang::t($heading) }}</h1>
                        @endif
                        @if ($subtitle)
                            <p class="truncate text-[11px] text-muted sm:text-[13px]">{{ \App\Support\Lang::t($subtitle) }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5 lg:gap-2">
                        {{ $actions ?? '' }}

                        {{-- Opens the alerts panel; without JavaScript it is still the way to the alerts page. --}}
                        <a href="{{ route('notifications.index') }}" @click.prevent="$store.sheets.toggle('alerts', $el)"
                           aria-haspopup="dialog" aria-controls="app-alerts" :aria-expanded="$store.sheets.current === 'alerts'"
                           class="relative hidden size-10 shrink-0 items-center justify-center rounded-2xl border bg-surface transition active:scale-95 hover:text-ink lg:flex"
                           :class="$store.sheets.current === 'alerts' ? 'border-brand/40 bg-brand-tint text-brand' : 'border-line text-muted'"
                           aria-label="{{ __('Alerts') }}">
                            <x-icon name="bell" class="size-4.5" />
                            <span data-alerts-badge @if ($unread === 0) hidden @endif
                                  class="num absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-negative px-1 text-[10px] font-bold text-white">{{ $unread }}</span>
                        </a>

                        <a href="{{ route('profile.edit') }}" class="hidden lg:block" aria-label="{{ __('Profile') }}">
                            <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="sm" />
                        </a>
                    </div>
                </div>
            </header>

            <main id="main" class="mx-auto w-full {{ $wide ? 'max-w-7xl' : 'max-w-5xl' }} flex-1 px-3 pb-[calc(6rem+env(safe-area-inset-bottom))] pt-4 sm:px-6 sm:pt-5 lg:px-8 lg:pb-16">
                {{ $slot }}
            </main>

            <footer class="hidden px-8 pb-8 text-xs text-faint lg:block">
                <div class="flex items-center justify-between border-t border-line pt-4">
                    <p>{{ __('© :date Sofor - All rights reserved.', ['date' => date('Y')]) }}</p>
                    <div class="flex items-center gap-4">
                        <a href="{{ route('about') }}" class="transition hover:text-ink">{{ __('About') }}</a>
                        <a href="{{ route('terms') }}" class="transition hover:text-ink">{{ __('Terms') }}</a>
                        <a href="{{ route('privacy') }}" class="transition hover:text-ink">{{ __('Privacy') }}</a>
                        <a href="{{ route('support.index') }}" class="font-semibold text-brand transition hover:text-accent-2">{{ __('Support us') }}</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    {{-- ===================== Phone tab bar ===================== --}}
    {{-- Solid, not frosted: the page scrolling underneath must never show through. --}}
    <nav class="fixed inset-x-0 bottom-0 z-50 border-t border-line bg-canvas safe-bottom lg:hidden" aria-label="{{ __('Quick navigation') }}">
        <div class="mx-auto grid max-w-lg grid-cols-5 px-1 pb-1 pt-1.5">
            @foreach ($tabs as $tab)
                @php
                    $on = $active === $tab['key'];
                    $isNew = $tab['key'] === 'create';
                    $isAlerts = $tab['key'] === 'notifications';
                @endphp
                <a href="{{ $tab['href'] }}" @if ($on) aria-current="page" @endif
                   @if ($isAlerts) @click.prevent="$store.sheets.toggle('alerts', $el)" aria-haspopup="dialog" aria-controls="app-alerts" :aria-expanded="$store.sheets.current === 'alerts'" @endif
                   class="group flex min-w-0 touch-manipulation select-none flex-col items-center gap-1 py-0.5">
                    @if ($isAlerts)
                        <span class="relative flex h-8 w-14 items-center justify-center rounded-full transition duration-200 group-active:scale-90"
                              :class="($store.sheets.current === 'alerts' || @js($on)) ? 'bg-brand-tint text-brand' : 'text-muted'">
                            <x-icon :name="$tab['icon']" class="size-[21px]" />
                            <span data-alerts-badge data-cap="9" @if ($unread === 0) hidden @endif
                                  class="num absolute right-2 top-0 flex h-4 min-w-4 items-center justify-center rounded-full bg-negative px-1 text-[9.5px] font-bold leading-none text-white ring-2 ring-canvas">{{ $unread > 9 ? '9+' : $unread }}</span>
                        </span>
                        <span class="max-w-full truncate text-[10.5px] leading-tight"
                              :class="($store.sheets.current === 'alerts' || @js($on)) ? 'font-bold text-brand' : 'font-semibold text-muted'">{{ $tab['label'] }}</span>
                    @else
                        <span @class([
                            'relative flex h-8 w-14 items-center justify-center rounded-full transition duration-200 group-active:scale-90',
                            'bg-brand text-brand-ink shadow-[0_6px_14px_-6px_var(--brand)]' => $isNew,
                            'bg-brand-tint text-brand' => $on && ! $isNew,
                            'text-muted' => ! $on && ! $isNew,
                        ])>
                            <x-icon :name="$tab['icon']" class="size-[21px]" />
                        </span>
                        <span @class(['max-w-full truncate text-[10.5px] leading-tight', 'font-bold text-brand' => $on, 'font-semibold text-muted' => ! $on])>{{ $tab['label'] }}</span>
                    @endif
                </a>
            @endforeach

            {{-- The menu wears the traveller's own picture, as in most apps. --}}
            <button type="button" @click="$store.sheets.toggle('menu', $el)" :aria-expanded="$store.sheets.current === 'menu'" aria-haspopup="dialog" aria-controls="app-menu"
                    class="group flex min-w-0 touch-manipulation select-none flex-col items-center gap-1 py-0.5">
                <span class="relative flex h-8 w-14 items-center justify-center rounded-full transition duration-200 group-active:scale-90"
                      :class="($store.sheets.current === 'menu' || @js($inMenu)) && 'bg-brand-tint'">
                    <span class="block size-7 overflow-hidden rounded-full ring-2 transition"
                          :class="($store.sheets.current === 'menu' || @js($inMenu)) ? 'ring-brand' : 'ring-transparent'">
                        <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="xs" :ring="false" />
                    </span>
                </span>
                <span class="max-w-full truncate text-[10.5px] leading-tight"
                      :class="($store.sheets.current === 'menu' || @js($inMenu)) ? 'font-bold text-brand' : 'font-semibold text-muted'">{{ __('Menu') }}</span>
            </button>
        </div>
    </nav>

    {{-- ===================== Phone menu: a sheet over the tab bar ===================== --}}
    {{-- Shown at once (so it can take focus); on the way out it stays visible while it
         slides away, then leaves the page's tab order. --}}
    <div id="app-menu" x-data="sheet('menu')" x-cloak class="fixed inset-0 z-[80] lg:hidden"
         :class="shown ? 'visible' : 'invisible pointer-events-none transition-[visibility] duration-[420ms]'" role="dialog" aria-modal="true" aria-labelledby="app-menu-title">
        <div class="absolute inset-0 sheet-backdrop transition-opacity duration-300" :class="shown ? 'opacity-100' : 'opacity-0'" @click="hide()"></div>

        <section x-ref="sheet" tabindex="-1" :style="sheetStyle"
                 @touchstart="grab($event)" @touchmove="pull($event)" @touchend="letGo($event)" @touchcancel="letGo($event)"
                 class="absolute inset-x-0 bottom-0 mx-auto flex max-h-[92dvh] max-w-lg flex-col rounded-t-[1.75rem] bg-canvas shadow-[0_-18px_40px_-18px_rgb(23_33_31/0.45)] outline-none">

            {{-- Handle and title: the place to grab it by. --}}
            <div class="shrink-0 touch-none px-5 pb-2 pt-2">
                <span class="mx-auto block h-[5px] w-10 rounded-full bg-line-strong" aria-hidden="true"></span>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <h2 id="app-menu-title" class="font-display text-[20px] font-semibold text-ink">{{ __('Menu') }}</h2>
                    <button type="button" @click="hide()" aria-label="{{ __('Close menu') }}"
                            class="flex size-8 items-center justify-center rounded-full bg-surface-2 text-muted transition active:scale-90">
                        <x-icon name="close" class="size-4" />
                    </button>
                </div>
            </div>

            <div data-sheet-list class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-[calc(1.25rem+env(safe-area-inset-bottom))] pt-1">
                {{-- Who is signed in --}}
                <a href="{{ route('profile.edit') }}" @click="hide()" @if ($active === 'profile') aria-current="page" @endif
                   class="flex items-center gap-3.5 rounded-[1.25rem] border border-line bg-surface p-3.5 transition active:scale-[0.99] active:bg-surface-2">
                    <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="lg" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-display text-[17px] font-semibold leading-tight text-ink">{{ $user?->name }}</span>
                        <span class="mt-0.5 block truncate text-[12.5px] text-muted">{{ $user ? '@'.$user->username : '' }}</span>
                        <span class="mt-1 block text-[12px] font-semibold text-brand">{{ __('Edit profile') }}</span>
                    </span>
                    <x-icon name="chevron-right" class="size-5 shrink-0 text-faint" />
                </a>

                {{-- The rest of the app, one tap each --}}
                <div class="mt-3 grid grid-cols-4 gap-2">
                    @foreach ($shortcuts as $item)
                        @php $on = $active === $item['key']; @endphp
                        <a href="{{ $item['href'] }}" @click="hide()" @if ($on) aria-current="page" @endif
                           @class([
                               'flex min-w-0 flex-col items-center gap-2 rounded-[1.1rem] border bg-surface px-1 pb-2.5 pt-3 text-center transition active:scale-95',
                               'border-brand/60 ring-2 ring-brand/15' => $on,
                               'border-line' => ! $on,
                           ])>
                            <span class="flex size-10 items-center justify-center rounded-[0.85rem] {{ $item['tone'] }}">
                                <x-icon :name="$item['icon']" class="size-5" />
                            </span>
                            <span class="line-clamp-2 text-[11px] font-semibold leading-tight text-ink">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Sofor itself --}}
                <div class="mt-4 divide-y divide-line overflow-hidden rounded-[1.25rem] border border-line bg-surface">
                    @foreach ($about as [$label, $href, $icon, $tone, $hint])
                        <a href="{{ $href }}" @click="hide()" class="flex min-h-[3.25rem] items-center gap-3 px-3.5 py-2.5 transition active:bg-surface-2">
                            <span class="flex size-[30px] shrink-0 items-center justify-center rounded-[9px] {{ $tone }}">
                                <x-icon :name="$icon" class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[14.5px] font-medium text-ink">{{ $label }}</span>
                                @if ($hint)
                                    <span class="block truncate text-[11.5px] text-muted">{{ $hint }}</span>
                                @endif
                            </span>
                            <x-icon name="chevron-right" class="size-4 shrink-0 text-faint" />
                        </a>
                    @endforeach
                </div>

                {{-- Language: অ for Bangla, the switch beside it --}}
                <div class="mt-3 flex min-h-[3.25rem] items-center gap-3 rounded-[1.25rem] border border-line bg-surface px-3.5 py-2.5">
                    <span class="flex size-[30px] shrink-0 items-center justify-center rounded-[9px] bg-info-tint text-[15px] font-bold leading-none text-info" lang="bn" aria-hidden="true">অ</span>
                    <span class="min-w-0 flex-1 truncate text-[14.5px] font-medium text-ink">{{ __('Language') }}</span>
                    <x-locale-switch short />
                </div>

                <form method="POST" action="{{ route('logout') }}" class="mt-5">
                    @csrf
                    <button type="submit"
                            class="flex min-h-[3.25rem] w-full items-center justify-center gap-2 rounded-[1.25rem] border border-line bg-surface text-[14.5px] font-semibold text-negative transition active:bg-negative-tint">
                        <x-icon name="logout" class="size-4" /> {{ __('Sign out') }}
                    </button>
                </form>

                <p class="mt-4 text-center text-[11px] text-faint">{{ __('© :date Sofor - All rights reserved.', ['date' => date('Y')]) }}</p>
            </div>
        </section>
    </div>

    @include('notifications.partials.panel')

    <div id="toast-stack" class="pointer-events-none fixed inset-x-3 top-3 z-[95] flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:top-5 sm:w-[22rem] sm:items-end"></div>

    @foreach (['status' => 'success', 'error' => 'error', 'warning' => 'warning'] as $key => $tone)
        @if (session($key))
            <span hidden data-toast="{{ session($key) }}" data-tone="{{ $tone }}"></span>
        @endif
    @endforeach

    @if ($errors->any())
        <span hidden data-toast="{{ $errors->first() }}" data-tone="error"></span>
    @endif

    @stack('scripts')
</body>
</html>
