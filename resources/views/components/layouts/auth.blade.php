@props([
    'title' => null,
    'heading' => null,
    'subtitle' => null,
    'eyebrow' => null,
    'back' => null,
    'backLabel' => 'Back to Sofor',
    'description' => null,
    // Codes, tokens and private previews have no business in search results.
    'noindex' => false,
    // "login" or "register": shows the switch between the two at the top of the card.
    'tab' => null,
    // The "by continuing you agree" line; sign-up asks with its own checkbox instead.
    'legal' => true,
])

{{-- Sign in, sign up and password pages: nothing but the form, on a night
     sky with Bangladesh drawn behind it (components/auth-art). The form card
     sits on the right on wide screens, with the map to its left; on phones
     the map fades in behind the top of the card. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<x-head :title="$title" :description="$description ?? $subtitle" :noindex="$noindex" />

<body class="relative min-h-dvh bg-ink font-sans text-ink antialiased">
    <x-skip-link />

    <div class="relative isolate flex min-h-dvh flex-col overflow-hidden">
        {{-- The sky: dots, a teal glow and a marigold one --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-20">
            <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle, #fbf8f2 1px, transparent 1.3px); background-size: 24px 24px;"></div>
            <div class="absolute -right-40 -top-40 size-[44rem] rounded-full opacity-45 blur-3xl" style="background: radial-gradient(circle, #0f6f6a, transparent 65%)"></div>
            <div class="absolute -bottom-56 -left-40 size-[40rem] rounded-full opacity-25 blur-3xl" style="background: radial-gradient(circle, #f6b23a, transparent 65%)"></div>
        </div>

        <x-auth-art class="absolute -z-10" />

        {{-- Top bar --}}
        <header class="flex items-center justify-between gap-3 px-4 pb-2 pt-4 sm:px-8 sm:pt-6 safe-top">
            <a href="{{ route('home') }}" aria-label="{{ __('Sofor home') }}" class="rounded-2xl bg-canvas px-2.5 py-2 shadow-soft transition hover:-translate-y-0.5">
                <x-logo size="sm" compact />
            </a>
            <div class="flex items-center gap-3">
                @if ($back)
                    <a href="{{ $back }}" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-canvas/75 transition hover:text-canvas">
                        <x-icon name="arrow-left" class="size-3.5" />
                        <span class="hidden sm:inline">{{ __($backLabel) }}</span>
                    </a>
                @endif
                <x-locale-switch short />
            </div>
        </header>

        {{-- The form --}}
        <main id="main" class="flex flex-1 items-center justify-center px-4 pb-8 pt-6 sm:px-6 lg:justify-end lg:pr-[7vw] xl:pr-[9vw]">
            <div class="w-full max-w-[29rem] animate-rise">
                <div class="rounded-[2rem] bg-surface p-6 shadow-sheet ring-1 ring-black/5 sm:p-8">
                    @if ($tab)
                        <nav class="grid grid-cols-2 rounded-full bg-surface-2 p-1 text-[13.5px] font-semibold" aria-label="{{ __('Log in or sign up') }}">
                            @foreach (['login' => [route('login'), __('Log in')], 'register' => [route('register'), __('Sign up')]] as $key => [$href, $label])
                                <a href="{{ $href }}" @if ($tab === $key) aria-current="page" @endif
                                   @class([
                                       'rounded-full py-2.5 text-center transition',
                                       'bg-ink text-canvas shadow-soft' => $tab === $key,
                                       'text-muted hover:text-ink' => $tab !== $key,
                                   ])>{{ $label }}</a>
                            @endforeach
                        </nav>
                    @endif

                    <div @class(['mt-7' => $tab])>
                        @if ($eyebrow)
                            <p class="eyebrow text-brand">{{ $eyebrow }}</p>
                        @endif
                        @if ($heading)
                            <h1 class="font-display text-[1.9rem] font-semibold leading-[1.1] tracking-tight text-ink sm:text-[2.1rem]">{{ \App\Support\Lang::t($heading) }}</h1>
                        @endif
                        @if ($subtitle)
                            <p class="mt-2.5 text-[14px] leading-relaxed text-muted">{{ \App\Support\Lang::t($subtitle) }}</p>
                        @endif
                    </div>

                    <div class="mt-7">
                        {{ $slot }}
                    </div>

                    @if (isset($below) && ! $below->isEmpty())
                        <div class="mt-7 border-t border-line pt-5 text-center text-[13.5px] text-muted">
                            {{ $below }}
                        </div>
                    @endif
                </div>

                @if ($legal)
                    <p class="mx-auto mt-5 max-w-sm text-center text-[11.5px] leading-relaxed text-canvas/50">
                        {{ __('Sofor is a Solutor product. By continuing you agree to our') }}
                        <a href="{{ route('terms') }}" class="font-semibold text-canvas/75 underline decoration-canvas/30 underline-offset-2 hover:text-canvas">{{ __('terms') }}</a>
                        {{ __('and') }}
                        <a href="{{ route('privacy') }}" class="font-semibold text-canvas/75 underline decoration-canvas/30 underline-offset-2 hover:text-canvas">{{ __('privacy policy') }}</a>.
                    </p>
                @endif
            </div>
        </main>
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
