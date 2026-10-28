@props([
    'title' => null,
    'heading' => null,
    'subtitle' => null,
    'eyebrow' => null,
    'card' => true,
    'wide' => false,
    'description' => null,
    'noindex' => false,
    'canonical' => null,
    'image' => null,
    'schema' => null,
    // Home > this page, for search results; pages deeper down pass their own trail.
    'breadcrumbs' => null,
])

@php
    $breadcrumbs ??= $title && ! request()->routeIs('home')
        ? [['Sofor', route('home')], [$title, $canonical ?? url()->current()]]
        : [];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
{{-- Without its own description a page is described by its subtitle, so no two pages share one. --}}
<x-head :title="$title" :description="$description ?? $subtitle" :noindex="$noindex"
        :canonical="$canonical" :image="$image" :schema="$schema" :breadcrumbs="$breadcrumbs" />

<body class="relative min-h-dvh bg-canvas font-sans text-ink antialiased">
    <x-skip-link />
    <x-backdrop />

    <div x-data="siteNav" @keydown.escape.window="menu = false"
         x-effect="document.body.classList.toggle('overflow-hidden', menu)">

        <x-site-header />

        {{-- The footer follows straight on, so pages keep their own space at the bottom. --}}
        <main id="main" class="{{ $wide ? '' : 'mx-auto w-full max-w-6xl px-4 pb-16 pt-10 sm:px-6 lg:px-8' }}">
            @if ($heading)
                <div class="mb-8 max-w-2xl">
                    @if ($eyebrow)
                        <p class="eyebrow">{{ $eyebrow }}</p>
                    @endif
                    <h1 class="mt-3 font-display text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl">{{ \App\Support\Lang::t($heading) }}</h1>
                    @if ($subtitle)
                        <p class="mt-4 text-[15px] leading-relaxed text-muted">{{ \App\Support\Lang::t($subtitle) }}</p>
                    @endif
                </div>
            @endif

            @if ($card)
                <article class="glass-card flex flex-col gap-6 p-6 text-[14.5px] leading-relaxed text-muted sm:p-8">
                    {{ $slot }}
                </article>
            @else
                {{ $slot }}
            @endif
        </main>

        <x-site-footer />
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
