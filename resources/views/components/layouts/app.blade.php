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

    // The phone tab bar has room for four; everything else is in the menu.
    $bottomNav = collect($nav)->whereIn('key', ['dashboard', 'trips', 'create', 'notifications'])->values()->all();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<x-head :title="$title" :description="__('Your Sofor trips and money ledger.')" :noindex="true" />

{{-- data-reload-on-change: a cost, payment or alert saved in another tab brings this page up to date. --}}
<body class="relative min-h-dvh bg-canvas font-sans text-ink antialiased" data-reload-on-change>
    <x-skip-link />
    <x-backdrop />

    {{-- The bottom tab bar sits outside this block, so its Menu button opens
         the drawer with a `sofor-open-menu` event instead of touching `menu`. --}}
    <div x-data="{ menu: false }" @keydown.escape.window="menu = false" @sofor-open-menu.window="menu = true"
         x-effect="document.body.classList.toggle('overflow-hidden', menu)"
         class="relative mx-auto flex min-h-dvh w-full lg:grid lg:grid-cols-[264px_minmax(0,1fr)]">

        {{-- ===================== Desktop sidebar ===================== --}}
        <aside class="sticky top-0 hidden h-dvh flex-col gap-5 border-r border-line bg-surface px-4 py-5 lg:flex">
            <div class="flex items-center gap-2 px-1">
                <a href="{{ route('dashboard') }}"><x-logo /></a>
            </div>

            <nav class="flex flex-1 flex-col gap-1" aria-label="{{ __('App') }}">
                @foreach ($nav as $item)
                    <x-nav-link :href="$item['href']" :icon="$item['icon']" :active="$active === $item['key']">
                        {{ $item['label'] }}
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="num ml-auto rounded-full bg-negative-tint px-2 py-0.5 text-[10px] font-bold text-negative">{{ $item['badge'] }}</span>
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
            <header class="sticky top-0 z-40 border-b border-line/70 glass safe-top">
                <div class="mx-auto flex w-full items-center gap-2 px-3 py-2.5 sm:px-6 lg:gap-3 lg:px-8 lg:py-3">
                    <button type="button" @click="menu = true"
                            class="flex size-10 shrink-0 items-center justify-center rounded-2xl border border-line bg-surface text-muted transition active:scale-95 hover:text-ink lg:hidden"
                            aria-label="{{ __('Open menu') }}" aria-haspopup="dialog">
                        <x-icon name="menu" class="size-5" />
                    </button>

                    <div class="min-w-0 flex-1">
                        @if ($heading)
                            <h1 class="truncate font-display text-[17px] font-semibold leading-tight text-ink sm:text-xl">{{ \App\Support\Lang::t($heading) }}</h1>
                        @endif
                        @if ($subtitle)
                            <p class="truncate text-[11px] text-muted sm:text-[13px]">{{ \App\Support\Lang::t($subtitle) }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5 lg:gap-2">
                        {{ $actions ?? '' }}

                        <a href="{{ route('notifications.index') }}"
                           class="relative flex size-10 shrink-0 items-center justify-center rounded-2xl border border-line bg-surface text-muted transition active:scale-95 hover:text-ink"
                           aria-label="{{ __('Alerts') }}">
                            <x-icon name="bell" class="size-4.5" />
                            @if ($unread > 0)
                                <span class="num absolute -right-1 -top-1 flex size-5 items-center justify-center rounded-full bg-negative text-[10px] font-bold text-white">{{ $unread }}</span>
                            @endif
                        </a>

                        <a href="{{ route('profile.edit') }}" class="hidden lg:block" aria-label="{{ __('Profile') }}">
                            <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="sm" />
                        </a>
                    </div>
                </div>
            </header>

            <main id="main" class="mx-auto w-full {{ $wide ? 'max-w-7xl' : 'max-w-5xl' }} flex-1 px-3 pb-[calc(7rem+env(safe-area-inset-bottom))] pt-4 sm:px-6 sm:pt-5 lg:px-8 lg:pb-16">
                @if ($back)
                    <a href="{{ $back }}" class="mb-3 inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3 py-2 text-[12px] font-semibold text-muted transition hover:text-ink lg:hidden">
                        <x-icon name="arrow-left" class="size-3.5" /> {{ __('Back') }}
                    </a>
                @endif

                {{ $slot }}
            </main>

            <footer class="hidden px-8 pb-8 text-xs text-faint lg:block">
                <div class="flex items-center justify-between border-t border-line pt-4">
                    <p>{{ __('© :date Sofor - Build Your Digital Legacy.', ['date' => date('Y')]) }}</p>
                    <div class="flex items-center gap-4">
                        <a href="{{ route('about') }}" class="transition hover:text-ink">{{ __('About') }}</a>
                        <a href="{{ route('terms') }}" class="transition hover:text-ink">{{ __('Terms') }}</a>
                        <a href="{{ route('privacy') }}" class="transition hover:text-ink">{{ __('Privacy') }}</a>
                        <a href="{{ route('support.index') }}" class="font-semibold text-brand transition hover:text-accent-2">{{ __('Support us') }}</a>
                    </div>
                </div>
            </footer>
        </div>

        {{-- ===================== Mobile drawer ===================== --}}
        <div x-show="menu" x-cloak class="fixed inset-0 z-[80] lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Main menu') }}">
            <div class="absolute inset-0 sheet-backdrop" x-show="menu" x-transition.opacity.duration.200ms @click="menu = false"></div>

            <aside x-show="menu"
                   x-transition:enter="transition ease-out duration-300"
                   x-transition:enter-start="-translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in duration-200"
                   x-transition:leave-start="translate-x-0"
                   x-transition:leave-end="-translate-x-full"
                   class="absolute inset-y-0 left-0 flex w-[86%] max-w-[19rem] flex-col overflow-y-auto border-r border-line bg-surface safe-top">

                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3.5">
                    <x-logo size="sm" />
                    <button type="button" @click="menu = false"
                            class="flex size-9 items-center justify-center rounded-xl text-faint transition hover:bg-surface-2 hover:text-ink"
                            aria-label="{{ __('Close menu') }}">
                        <x-icon name="close" class="size-4.5" />
                    </button>
                </div>

                <div class="border-b border-line px-4 py-4">
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="md" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13.5px] font-semibold text-ink">{{ $user?->name }}</p>
                            <p class="truncate text-[11px] text-faint">{{ $user ? '@'.$user->username : '' }}</p>
                        </div>
                    </div>
                </div>

                <nav class="flex flex-1 flex-col gap-1 p-3" aria-label="{{ __('Mobile menu') }}">
                    @foreach ($nav as $item)
                        <a href="{{ $item['href'] }}" @click="menu = false"
                           @class([
                               'flex items-center gap-3 rounded-2xl px-3 py-2.5 text-[13.5px] font-semibold transition',
                               'bg-brand-tint text-brand' => $active === $item['key'],
                               'text-muted hover:bg-surface-2 hover:text-ink' => $active !== $item['key'],
                           ])>
                            <x-icon :name="$item['icon']" class="size-4.5" />
                            {{ $item['label'] }}
                            @if (($item['badge'] ?? 0) > 0)
                                <span class="num ml-auto rounded-full bg-negative-tint px-2 py-0.5 text-[10px] font-bold text-negative">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach

                    <div class="my-2 border-t border-line"></div>

                    <a href="{{ route('support.index') }}" @click="menu = false"
                       class="mb-1 flex items-center gap-3 rounded-2xl border border-line bg-accent-tint px-3 py-2.5 text-[13.5px] font-semibold text-accent-2 transition hover:border-accent/50">
                        <x-icon name="gift" class="size-4.5" />
                        {{ __('Support Sofor') }}
                    </a>

                    @foreach ([
                        [__('About Sofor'), route('about'), 'info'],
                        [__('Terms of service'), route('terms'), 'shield'],
                        [__('Privacy policy'), route('privacy'), 'lock'],
                    ] as [$label, $href, $icon])
                        <a href="{{ $href }}" @click="menu = false"
                           class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-[13.5px] font-semibold text-muted transition hover:bg-surface-2 hover:text-ink">
                            <x-icon :name="$icon" class="size-4.5" />
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>

                <div class="flex flex-col gap-3 border-t border-line p-3 safe-bottom">
                    <x-locale-switch class="w-full" />

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex h-11 w-full items-center justify-center gap-2 rounded-2xl border border-line bg-surface-2 text-[13px] font-semibold text-muted transition hover:text-negative">
                            <x-icon name="logout" class="size-4" /> {{ __('Sign out') }}
                        </button>
                    </form>

                    <div class="rounded-2xl border border-line bg-surface-2 p-3 text-[11.5px] leading-relaxed text-faint">
                        <p class="font-semibold text-muted">{{ __('Need a human?') }}</p>
                        <a href="tel:+8801922992279" class="mt-1 block transition hover:text-ink">+880 1922-992279</a>
                        <a href="tel:+8801910069929" class="block transition hover:text-ink">+880 1910-069929</a>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- ===================== Mobile bottom navigation ===================== --}}
    {{-- Solid, not frosted: the page scrolling underneath must never show through. --}}
    <nav x-data class="fixed inset-x-0 bottom-0 z-50 border-t border-line bg-canvas shadow-[0_-6px_20px_-12px_rgb(24_24_27/0.18)] safe-bottom lg:hidden" aria-label="{{ __('Quick navigation') }}">
        <div class="mx-auto flex max-w-lg items-end justify-between px-1.5 pb-1 pt-1.5">
            @foreach ($bottomNav as $item)
                @php $isCenter = $item['key'] === 'create'; @endphp

                @if ($isCenter)
                    <a href="{{ $item['href'] }}" class="flex flex-1 touch-manipulation select-none flex-col items-center gap-1 text-[10px] font-semibold transition active:scale-95">
                        <span class="sticker flex size-11 -translate-y-3 items-center justify-center rounded-2xl bg-brand text-brand-ink ring-4 ring-canvas">
                            <x-icon name="plus" class="size-5" />
                        </span>
                        <span class="-mt-2.5 text-faint">{{ __('New trip') }}</span>
                    </a>
                @else
                    <a href="{{ $item['href'] }}"
                       @class([
                           'relative flex min-w-0 flex-1 touch-manipulation select-none flex-col items-center gap-1 rounded-2xl py-2 text-[10px] font-semibold transition active:scale-95 active:bg-surface-2',
                           'text-brand' => $active === $item['key'],
                           'text-faint' => $active !== $item['key'],
                       ])>
                        <x-icon :name="$item['icon']" class="size-5" />
                        <span class="max-w-full truncate">{{ $item['label'] }}</span>
                        @if ($active === $item['key'])
                            <span class="absolute -bottom-0.5 h-1 w-6 rounded-full bg-brand"></span>
                        @endif
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="absolute right-1/4 top-1 size-2 rounded-full bg-negative"></span>
                        @endif
                    </a>
                @endif
            @endforeach

            <button type="button" @click="$dispatch('sofor-open-menu')"
                    class="relative flex min-w-0 flex-1 touch-manipulation select-none flex-col items-center gap-1 rounded-2xl py-2 text-[10px] font-semibold text-faint transition active:scale-95 active:bg-surface-2"
                    aria-label="{{ __('Open menu') }}">
                <span class="relative">
                    <x-icon name="grid" class="size-5" />
                    @if ($unread > 0)
                        <span class="absolute -right-1.5 -top-1 size-2 rounded-full bg-negative"></span>
                    @endif
                </span>
                <span>{{ __('Menu') }}</span>
            </button>
        </div>
    </nav>

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
