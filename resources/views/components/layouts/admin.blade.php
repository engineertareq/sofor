@props([
    'title' => null,
    'active' => null,
    'heading' => null,
    'subtitle' => null,
    // A page inside a section (one traveller, one trip): the arrow back to its list.
    'back' => null,
])

@php
    $user = auth()->user();
    $sponsorRequests = app(\App\Services\SponsorShowcase::class)->unanswered();

    $groups = [
        [
            'label' => 'Overview',
            'items' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'chart-pie', 'href' => route('admin.dashboard')],
            ],
        ],
        [
            'label' => 'Community',
            'items' => [
                ['key' => 'users', 'label' => 'Travellers', 'icon' => 'users', 'href' => route('admin.users.index')],
                ['key' => 'trips', 'label' => 'Trips', 'icon' => 'compass', 'href' => route('admin.trips.index')],
            ],
        ],
        [
            'label' => 'Business',
            'items' => [
                ['key' => 'donations', 'label' => 'Supporters', 'icon' => 'gift', 'href' => route('admin.donations.index')],
                ['key' => 'sponsors', 'label' => 'Sponsors', 'icon' => 'building', 'href' => route('admin.sponsors.index')],
                ['key' => 'sponsor-requests', 'label' => 'Sponsor requests', 'icon' => 'mail', 'href' => route('admin.sponsor-inquiries.index'), 'badge' => $sponsorRequests],
            ],
        ],
        [
            'label' => 'Settings',
            'items' => [
                ['key' => 'settings', 'label' => 'Branding', 'icon' => 'sparkles', 'href' => route('admin.settings')],
                ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'settings', 'href' => route('admin.maintenance')],
                ['key' => 'profile', 'label' => 'Your account', 'icon' => 'user', 'href' => route('admin.profile')],
            ],
        ],
    ];

    $flat = collect($groups)->flatMap(fn ($g) => $g['items'])->all();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<x-head :title="$title ? $title.' - Admin' : 'Admin'" :description="'Sofor admin console.'" :noindex="true" :manifest="false" />

{{-- data-console: the console's denser look (resources/css/app.css).
     data-reload-on-change: whatever is saved in another console tab shows up here too. --}}
<body class="relative min-h-dvh bg-canvas-tint font-sans text-ink antialiased" data-console data-reload-on-change>
    <x-skip-link />

    <div x-data="{ menu: false }" @keydown.escape.window="menu = false"
         x-effect="document.body.classList.toggle('overflow-hidden', menu)"
         class="relative mx-auto flex min-h-dvh w-full lg:grid lg:grid-cols-[240px_minmax(0,1fr)]">

        {{-- ===================== Sidebar ===================== --}}
        <aside class="sticky top-0 hidden h-dvh flex-col gap-4 overflow-y-auto border-r border-line bg-surface px-3 py-4 no-scrollbar lg:flex">
            <div class="flex items-center gap-2 px-1.5">
                <x-logo size="sm" />
                <x-badge tone="brand" size="sm" icon="shield">Admin</x-badge>
            </div>

            <x-admin.nav :groups="$groups" :active="$active" class="flex-1" aria-label="Admin" />

            <div class="flex flex-col gap-2 border-t border-line pt-3">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 rounded-xl px-2.5 py-2 text-[13px] font-semibold text-muted transition hover:bg-surface-2 hover:text-ink">
                    <x-icon name="globe" class="size-4" /> Public site
                </a>

                <div class="flex items-center gap-2.5 rounded-2xl border border-line bg-surface-2 p-2">
                    <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[12.5px] font-semibold text-ink">{{ $user?->name }}</p>
                        <p class="truncate text-[10.5px] text-faint">Administrator</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="Sign out" aria-label="Sign out"
                                class="flex size-8 items-center justify-center rounded-lg text-faint transition hover:bg-negative-tint hover:text-negative">
                            <x-icon name="logout" class="size-4" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-col">
            {{-- ===================== Top bar ===================== --}}
            <header class="sticky top-0 z-40 border-b border-line bg-canvas-tint/85 backdrop-blur-md safe-top">
                <div class="flex w-full items-center gap-2 px-4 py-2.5 sm:gap-3 sm:px-6 lg:px-8">
                    <button type="button" @click="menu = true"
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-line bg-surface text-muted transition active:scale-95 hover:text-ink lg:hidden"
                            aria-label="Open menu" aria-haspopup="dialog">
                        <x-icon name="menu" class="size-4.5" />
                    </button>

                    @if ($back)
                        <a href="{{ $back }}" aria-label="Back" title="Back"
                           class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-line bg-surface text-muted transition active:scale-95 hover:text-ink">
                            <x-icon name="arrow-left" class="size-4" />
                        </a>
                    @endif

                    <div class="min-w-0 flex-1">
                        @if ($heading)
                            <h1 class="truncate font-display text-base font-semibold leading-tight text-ink sm:text-lg">{{ $heading }}</h1>
                        @endif
                        @if ($subtitle)
                            <p class="truncate text-[11px] text-muted sm:text-[12.5px]">{{ $subtitle }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        {{ $actions ?? '' }}
                        <a href="{{ route('admin.profile') }}" class="hidden lg:block" aria-label="Your account">
                            <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="sm" />
                        </a>
                    </div>
                </div>

                {{-- Phones and tablets: every section one swipe away, the open one scrolled into view. --}}
                <nav x-data x-init="const on = $el.querySelector('[aria-current=page]'); if (on) $el.scrollLeft = on.offsetLeft - ($el.clientWidth - on.offsetWidth) / 2"
                     class="relative flex gap-1.5 overflow-x-auto px-4 pb-2.5 no-scrollbar sm:px-6 lg:hidden" aria-label="Admin sections">
                    @foreach ($flat as $item)
                        @php $on = $active === $item['key']; @endphp
                        <a href="{{ $item['href'] }}" @if ($on) aria-current="page" @endif
                           @class([
                               'flex h-8 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-[12px] font-semibold transition',
                               'bg-brand text-brand-ink' => $on,
                               'border border-line bg-surface text-muted hover:text-ink' => ! $on,
                           ])>
                            <x-icon :name="$item['icon']" class="size-3.5" />
                            {{ $item['label'] }}
                            @if (($item['badge'] ?? 0) > 0)
                                <span class="num rounded-full bg-negative px-1.5 text-[10px] font-bold leading-4 text-white">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </header>

            <main id="main" class="flex-1 px-4 pb-16 pt-4 sm:px-6 sm:pt-5 lg:px-8">
                {{-- Hard to miss, so the site is never left closed by accident. --}}
                @if (app(\App\Services\MaintenanceMode::class)->active() && $active !== 'maintenance')
                    <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-2xl border border-warning/30 bg-warning-tint px-4 py-2.5">
                        <span class="flex items-center gap-2 text-[12.5px] font-semibold text-ink">
                            <x-icon name="settings" class="size-4 text-warning" /> Maintenance mode is on
                        </span>
                        <span class="text-[12px] text-muted">Only administrators can use Sofor right now.</span>
                        <a href="{{ route('admin.maintenance') }}"
                           class="ml-auto text-[12px] font-semibold text-warning underline decoration-dotted underline-offset-2">
                            Manage
                        </a>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>

        {{-- ===================== Mobile drawer ===================== --}}
        <div x-show="menu" x-cloak class="fixed inset-0 z-[80] lg:hidden" role="dialog" aria-modal="true" aria-label="Admin menu">
            <div class="absolute inset-0 sheet-backdrop" x-show="menu" x-transition.opacity.duration.200ms @click="menu = false"></div>

            <aside x-show="menu"
                   x-transition:enter="transition ease-out duration-300"
                   x-transition:enter-start="-translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in duration-200"
                   x-transition:leave-start="translate-x-0"
                   x-transition:leave-end="-translate-x-full"
                   class="absolute inset-y-0 left-0 flex w-[82%] max-w-[17.5rem] flex-col overflow-y-auto border-r border-line bg-surface safe-top">

                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <div class="flex items-center gap-2">
                        <x-logo size="sm" />
                        <x-badge tone="brand" size="sm">Admin</x-badge>
                    </div>
                    <button type="button" @click="menu = false"
                            class="flex size-8 items-center justify-center rounded-lg text-faint transition hover:bg-surface-2 hover:text-ink"
                            aria-label="Close menu">
                        <x-icon name="close" class="size-4" />
                    </button>
                </div>

                <x-admin.nav :groups="$groups" :active="$active" class="flex-1 p-3" aria-label="Admin mobile" />

                <div class="flex flex-col gap-2 border-t border-line p-3 safe-bottom">
                    <a href="{{ route('home') }}" @click="menu = false" class="flex items-center gap-2.5 rounded-xl px-2.5 py-2 text-[13px] font-semibold text-muted transition hover:bg-surface-2 hover:text-ink">
                        <x-icon name="globe" class="size-4" /> Public site
                    </a>

                    <div class="flex items-center gap-2.5 rounded-2xl border border-line bg-surface-2 p-2">
                        <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[12.5px] font-semibold text-ink">{{ $user?->name }}</p>
                            <p class="truncate text-[10.5px] text-faint">Administrator</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="flex h-8 items-center gap-1.5 rounded-lg border border-line bg-surface px-2.5 text-[11.5px] font-semibold text-muted transition hover:border-negative/40 hover:text-negative">
                                <x-icon name="logout" class="size-3.5" /> Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <div id="toast-stack" class="pointer-events-none fixed inset-x-4 top-3 z-[95] flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:top-5 sm:w-[22rem] sm:items-end"></div>

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
