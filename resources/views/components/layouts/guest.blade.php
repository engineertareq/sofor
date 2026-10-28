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
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<x-head :title="$title" :description="$description ?? $subtitle" :noindex="$noindex" />

<body class="relative min-h-dvh bg-canvas font-sans text-ink antialiased">
    <x-skip-link />
    <x-backdrop />

    <div class="grid min-h-dvh grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1fr)_minmax(0,0.92fr)]">

        {{-- ===================== Form column ===================== --}}
        <div class="flex flex-col px-5 py-7 sm:px-10 lg:px-14">
            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('home') }}" aria-label="{{ __('Sofor home') }}"><x-logo compact /></a>
                <div class="flex items-center gap-3 sm:gap-4">
                    @if ($back)
                        <a href="{{ $back }}" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-muted transition hover:text-ink">
                            <x-icon name="arrow-left" class="size-3.5" />
                            {{ __($backLabel) }}
                        </a>
                    @endif
                    <x-locale-switch short />
                </div>
            </div>

            <div class="flex flex-1 flex-col justify-center py-10">
                <main id="main" class="mx-auto w-full max-w-[27rem]">
                    @if ($eyebrow)
                        <p class="eyebrow">{{ $eyebrow }}</p>
                    @endif

                    @if ($heading)
                        <h1 class="mt-3 font-display text-[2.1rem] font-semibold leading-[1.06] tracking-tight sm:text-[2.5rem]">{{ \App\Support\Lang::t($heading) }}</h1>
                    @endif

                    @if ($subtitle)
                        <p class="mt-3.5 text-[14.5px] leading-relaxed text-muted">{{ \App\Support\Lang::t($subtitle) }}</p>
                    @endif

                    <div class="mt-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>

            <div class="mx-auto flex w-full max-w-[27rem] flex-col items-center gap-3">
                <p class="text-center text-[11.5px] leading-relaxed text-faint">
                    {{ __('Sofor is a Solutor product. By continuing you agree to our') }}
                    <a href="{{ route('terms') }}" class="font-semibold text-muted underline decoration-line-strong underline-offset-2 hover:text-ink">{{ __('terms') }}</a>
                    {{ __('and') }}
                    <a href="{{ route('privacy') }}" class="font-semibold text-muted underline decoration-line-strong underline-offset-2 hover:text-ink">{{ __('privacy policy') }}</a>.
                </p>
            </div>
        </div>

        {{-- ===================== Art column ===================== --}}
        <aside class="relative hidden overflow-hidden border-l border-line bg-surface-2 lg:flex lg:flex-col lg:justify-center lg:gap-6 lg:px-14">
            <div aria-hidden="true"
                 class="absolute inset-0 opacity-70"
                 style="background:
                     radial-gradient(circle at 80% 12%, var(--brand-tint) 0 8%, transparent 42%),
                     radial-gradient(circle at 10% 88%, var(--accent-tint) 0 8%, transparent 44%),
                     radial-gradient(circle, color-mix(in oklab, var(--ink) 7%, transparent) 1px, transparent 1.3px);
                     background-size: auto, auto, 20px 20px;"></div>

            <div class="relative">
                @if (isset($art) && ! $art->isEmpty())
                    {{ $art }}
                @else
                    <span class="stamp text-brand">{{ __('Split trips, not friendships') }}</span>

                    <h2 class="mt-6 max-w-[18ch] font-display text-[2rem] font-semibold leading-[1.12] tracking-tight text-ink">
                        {{ __('Money sorted, so the') }} <span class="marker">{{ __('trip') }}</span> {{ __('can carry on.') }}
                    </h2>

                    <ul class="mt-7 flex flex-col gap-3.5">
                        @foreach ([
                            ['icon' => 'wallet', 'text' => __('Log a cost once. Everybody sees their share instantly.')],
                            ['icon' => 'users', 'text' => __('Invite travellers by phone or email, no app install needed.')],
                            ['icon' => 'receipt', 'text' => __('A running ledger in Bangladeshi taka, down to the paisa.')],
                            ['icon' => 'bolt', 'text' => __('Dues update live while you are still at the table.')],
                        ] as $point)
                            <li class="flex items-start gap-3 text-[14px] leading-relaxed text-muted">
                                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl bg-brand-tint text-brand">
                                    <x-icon :name="$point['icon']" class="size-4" />
                                </span>
                                {{ $point['text'] }}
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-9 rounded-4xl border border-line bg-surface p-5 shadow-soft">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-2xl bg-accent-tint text-accent-2">
                                <x-icon name="map-pin" class="size-5" />
                            </span>
                            <div>
                                <p class="font-display text-[15px] font-semibold text-ink">{{ __('Cox\'s Bazar, 3 nights') }}</p>
                                <p class="text-[12px] text-faint">{{ __('4 travellers · settled in 2 transfers') }}</p>
                            </div>
                        </div>
                        <div class="perf" data-on="surface"></div>
                        <dl class="flex flex-col gap-2 text-[13px]">
                            <div class="flex items-center justify-between">
                                <dt class="text-muted">{{ __('Total logged') }}</dt>
                                <dd class="num font-semibold text-ink">৳ 18,400</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-muted">{{ __('Each share') }}</dt>
                                <dd class="num font-semibold text-ink">৳ 4,600</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="font-semibold text-positive">{{ __('Settled') }}</dt>
                                <dd class="num font-semibold text-positive">৳ 13,800</dd>
                            </div>
                        </dl>
                    </div>
                @endif
            </div>
        </aside>
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
