@props([
    'title' => null,
    'active' => null,
    'heading' => null,
    'subtitle' => null,
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

<body class="relative min-h-dvh bg-canvas-tint font-sans text-ink antialiased">
    <x-skip-link />

    <div x-data="{ menu: false }" @keydown.escape.window="menu = false"
         x-effect="document.body.classList.toggle('overflow-hidden', menu)"
         class="relative mx-auto flex min-h-dvh w-full lg:grid lg:grid-cols-[262px_minmax(0,1fr)]">

        {{-- ===================== Sidebar ===================== --}}
        <aside class="sticky top-0 hidden h-dvh flex-col gap-5 border-r border-line bg-surface px-4 py-5 lg:flex">
            <div class="flex items-center gap-2.5 px-1">
                <x-logo size="sm" />
                <x-badge tone="brand" icon="shield">Admin</x-badge>
            </div>

            <nav class="flex flex-1 flex-col gap-5" aria-label="Admin">
                @foreach ($groups as $group)
                    <div>
                        <p class="eyebrow px-3 pb-2">{{ $group['label'] }}</p>
                        <div class="flex flex-col gap-1">
                            @foreach ($group['items'] as $item)
                                <x-nav-link :href="$item['href']" :icon="$item['icon']" :active="$active === $item['key']">
                                    {{ $item['label'] }}
                                    @if (($item['badge'] ?? 0) > 0)
                                        <span @class([
                                            'num ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                            'bg-white/20 text-white' => $active === $item['key'],
                                            'bg-negative-tint text-negative' => $active !== $item['key'],
                                        ])>{{ $item['badge'] }}</span>
                                    @endif
                                </x-nav-link>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="flex flex-col gap-1 border-t border-line pt-4">
                <x-nav-link :href="route('home')" icon="globe">Public site</x-nav-link>
            </div>

            <div class="rounded-4xl border border-line bg-surface-2 p-3">
                <div class="flex items-center gap-3">
                    <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-semibold text-ink">{{ $user?->name }}</p>
                        <p class="truncate text-[11px] text-faint">Administrator</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit"
                            class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-line bg-surface text-[11.5px] font-semibold text-muted transition hover:border-negative/40 hover:text-negative">
                        <x-icon name="logout" class="size-3.5" /> Sign out
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 flex-col">
            {{-- ===================== Top bar ===================== --}}
            <header class="sticky top-0 z-40 border-b border-line bg-canvas-tint/85 backdrop-blur-md safe-top">
                <div class="flex w-full items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <button type="button" @click="menu = true"
                            class="flex size-10 shrink-0 items-center justify-center rounded-2xl border border-line bg-surface text-muted transition active:scale-95 hover:text-ink lg:hidden"
                            aria-label="Open menu" aria-haspopup="dialog">
                        <x-icon name="menu" class="size-5" />
                    </button>

                    <a href="{{ route('admin.dashboard') }}" class="lg:hidden"><x-logo size="sm" compact /></a>

                    <div class="min-w-0 flex-1">
                        @if ($heading)
                            <h1 class="truncate font-display text-[17px] font-semibold text-ink sm:text-xl">{{ $heading }}</h1>
                        @endif
                        @if ($subtitle)
                            <p class="truncate text-[11px] text-muted sm:text-[13px]">{{ $subtitle }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        {{ $actions ?? '' }}
                        <a href="{{ route('admin.profile') }}" class="hidden lg:block" aria-label="Your account">
                            <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="sm" />
                        </a>
                    </div>
                </div>

                {{-- Mobile admin nav --}}
                <div class="flex gap-2 overflow-x-auto px-4 pb-3 no-scrollbar lg:hidden">
                    @foreach ($flat as $item)
                        <a href="{{ $item['href'] }}"
                           @class([
                               'flex shrink-0 items-center gap-2 rounded-full px-3.5 py-2 text-[12.5px] font-semibold transition',
                               'bg-brand text-brand-ink' => $active === $item['key'],
                               'border border-line bg-surface text-muted' => $active !== $item['key'],
                           ])>
                            <x-icon :name="$item['icon']" class="size-4" />
                            {{ $item['label'] }}
                            @if (($item['badge'] ?? 0) > 0)
                                <span class="num rounded-full bg-negative px-1.5 text-[10px] font-bold text-white">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </header>

            <main id="main" class="flex-1 px-4 pb-16 pt-5 sm:px-6 lg:px-8">
                {{-- Hard to miss, so the site is never left closed by accident. --}}
                @if (app(\App\Services\MaintenanceMode::class)->active() && $active !== 'maintenance')
                    <div class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-3xl border border-warning/30 bg-warning-tint px-4 py-3">
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
                   class="absolute inset-y-0 left-0 flex w-[86%] max-w-[19rem] flex-col overflow-y-auto border-r border-line bg-surface safe-top">

                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3.5">
                    <div class="flex items-center gap-2">
                        <x-logo size="sm" />
                        <x-badge tone="brand" size="sm">Admin</x-badge>
                    </div>
                    <button type="button" @click="menu = false"
                            class="flex size-9 items-center justify-center rounded-xl text-faint transition hover:bg-surface-2 hover:text-ink"
                            aria-label="Close menu">
                        <x-icon name="close" class="size-4.5" />
                    </button>
                </div>

                <nav class="flex flex-1 flex-col gap-5 p-4" aria-label="Admin mobile">
                    @foreach ($groups as $group)
                        <div>
                            <p class="eyebrow pb-2">{{ $group['label'] }}</p>
                            <div class="flex flex-col gap-1">
                                @foreach ($group['items'] as $item)
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
                            </div>
                        </div>
                    @endforeach

                    <div class="border-t border-line pt-3">
                        <a href="{{ route('home') }}" @click="menu = false" class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-[13.5px] font-semibold text-muted transition hover:bg-surface-2 hover:text-ink">
                            <x-icon name="globe" class="size-4.5" /> Public site
                        </a>
                    </div>
                </nav>

                <div class="border-t border-line p-4 safe-bottom">
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$user?->name" :gradient="$user?->avatarGradient()" :src="$user?->avatarUrl()" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold text-ink">{{ $user?->name }}</p>
                            <p class="truncate text-[11px] text-faint">Administrator</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit"
                                class="flex h-10 w-full items-center justify-center gap-2 rounded-2xl border border-line bg-surface-2 text-[13px] font-semibold text-muted transition hover:text-negative">
                            <x-icon name="logout" class="size-4" /> Sign out
                        </button>
                    </form>
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
