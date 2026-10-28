{{-- Shell for error pages (404, maintenance, and the 4xx / 5xx fallbacks).
     Kept deliberately light: no navigation that could itself fail, only the
     logo, the message and a way home. --}}
@props([
    'title' => null,
    // Seconds before the page reloads itself, e.g. to notice maintenance ending.
    'refresh' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<x-head :title="$title" :noindex="true" :manifest="false" />

<body class="relative min-h-dvh bg-canvas font-sans text-ink antialiased">
    <x-skip-link />
    <x-backdrop />

    <div class="relative mx-auto flex min-h-dvh w-full max-w-3xl flex-col px-5 py-7 sm:px-8">
        <header class="flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" aria-label="{{ __('Sofor home') }}"><x-logo /></a>
        </header>

        <main id="main" class="flex flex-1 flex-col items-center justify-center py-14 text-center">
            {{ $slot }}
        </main>

        <footer class="text-center text-[11px] text-faint">
            {{ __('© :year Sofor - a Solutor product', ['year' => now()->year]) }}
        </footer>
    </div>

    @if ($refresh)
        <script>setTimeout(() => window.location.reload(), {{ (int) $refresh * 1000 }});</script>
    @endif
</body>
</html>
