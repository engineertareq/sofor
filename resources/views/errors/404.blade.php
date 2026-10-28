@php
    // Unknown addresses reach this page through the fallback route, so the
    // session is running and the way back matches who is signed in.
    $user = auth()->user();

    [$homeUrl, $homeLabel, $homeIcon] = match (true) {
        $user?->isAdmin() === true => [route('admin.dashboard'), __('Go to the console'), 'shield'],
        $user !== null => [route('dashboard'), __('Back to my trips'), 'compass'],
        default => [route('home'), __('Go to the home page'), 'home'],
    };
@endphp

<x-layouts.error title="{{ __('Page not found') }}">
    {{-- The logo's dotted route, wandering off to a place that is not on the map. --}}
    <svg viewBox="0 0 220 120" fill="none" class="w-56 sm:w-64" aria-hidden="true">
        <path d="M14 96c22-40 44-58 70-58 24 0 30 30 54 30 22 0 40-6 54-18"
              stroke="var(--brand)" stroke-width="3" stroke-linecap="round" stroke-dasharray="5 7" />
        <circle cx="14" cy="96" r="7" fill="var(--brand)" />
        <circle cx="14" cy="96" r="12" stroke="var(--brand)" stroke-opacity=".25" stroke-width="2" />
        <g transform="translate(176 4)">
            <path d="M16 2C8.3 2 2 8.3 2 16c0 10.5 14 26 14 26s14-15.5 14-26C30 8.3 23.7 2 16 2Z"
                  fill="var(--accent)" stroke="var(--ink)" stroke-width="2" stroke-linejoin="round" />
            <path d="M12.6 12.8a3.6 3.6 0 0 1 7 1.1c0 2.4-3.6 2.9-3.6 5.1" stroke="var(--ink)" stroke-width="2.2" stroke-linecap="round" />
            <circle cx="16" cy="23.6" r="1.4" fill="var(--ink)" />
        </g>
    </svg>

    <p class="eyebrow mt-8 text-brand">{{ __('Error 404') }}</p>
    <h1 class="mt-3 font-display text-[2rem] font-semibold leading-tight tracking-tight sm:text-5xl">
        {{ __('This page took a wrong turn') }}
    </h1>
    <p class="mt-4 max-w-md text-[14px] leading-relaxed text-muted">
        {{ __('The link may be out of date, or the page was moved or deleted. Your trips and balances are exactly where you left them.') }}
    </p>

    <p class="mt-5 max-w-full truncate rounded-2xl border border-line bg-surface px-3 py-1.5 font-mono text-[11px] text-faint">
        /{{ ltrim(request()->path(), '/') }}
    </p>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <x-btn :href="$homeUrl" :icon="$homeIcon">{{ $homeLabel }}</x-btn>

        {{-- Back only makes sense when the visitor came from another Sofor page. --}}
        <x-btn type="button" variant="outline" icon="arrow-left"
               onclick="document.referrer.indexOf(window.location.origin) === 0 ? history.back() : window.location.assign('{{ $homeUrl }}')">
            {{ __('Go back') }}
        </x-btn>
    </div>
</x-layouts.error>
