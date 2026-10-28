@props(['compact' => false])

@php
    $user = auth()->user();

    // The middle column follows whoever is reading.
    $explore = [[__('Explorers'), route('explorers')], [__('Make a travel map'), route('map-maker')], [__('Trip split calculator'), route('split-calculator')]];

    if ($user === null) {
        array_push($explore, [__('Sign up'), route('register')], [__('Log in'), route('login')]);
    } elseif ($user->isAdmin()) {
        $explore[] = [__('Admin console'), route('admin.dashboard')];
    } else {
        array_push($explore, [__('My trips'), route('dashboard')], [__('Travel map'), route('travel.index')]);

        // Accounts get a username in a migration; until it runs there is no profile to link to.
        if (filled($user->username)) {
            $explore[] = [__('My profile'), $user->publicUrl()];
        }
    }

    $groups = [
        __('Product') => [
            [__('Features'), route('home').'#features'],
            [__('How it works'), route('home').'#how'],
            [__('Free for all'), route('home').'#free'],
            [__('FAQ'), route('home').'#faq'],
        ],
        __('Explore') => $explore,
        __('Company') => [
            [__('About'), route('about')],
            [__('Sponsors'), route('sponsors')],
            [__('Support us'), route('support.index')],
            [__('Terms'), route('terms')],
            [__('Privacy'), route('privacy')],
        ],
    ];
@endphp

{{-- Full width: the white layer runs edge to edge; only the text keeps the
     site's reading width, so lines never stretch across a wide monitor. --}}
<footer class="w-full border-t-[1.5px] border-line bg-surface">
    <div class="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-8 pb-8 pt-10 lg:grid-cols-12 lg:gap-6">
            {{-- Who we are, and the one ask. Centred while the footer is stacked (below lg). --}}
            <div class="text-center lg:col-span-4 lg:text-left">
                <a href="{{ route('home') }}" class="inline-block" aria-label="{{ __('Sofor home') }}"><x-logo size="sm" /></a>
                <p class="mx-auto mt-3 max-w-xs text-[13px] leading-relaxed text-muted lg:mx-0">
                    {{ __('Split trip costs fairly, settle up over bKash, and map everywhere you have been.') }}
                </p>
                <a href="{{ route('support.index') }}"
                   class="mt-4 inline-flex items-center gap-2 rounded-full border border-accent/40 bg-accent-tint py-1.5 pl-1.5 pr-3.5 text-[12.5px] font-semibold text-ink transition hover:border-accent">
                    <span class="flex size-6 items-center justify-center rounded-full bg-accent"><x-icon name="gift" class="size-3.5" /></span>
                    {{ __('Support Sofor with bKash') }}
                </a>
            </div>

            {{-- Links --}}
            <nav aria-label="{{ __('Footer') }}" class="grid grid-cols-3 gap-4 lg:col-span-5">
                @foreach ($groups as $title => $links)
                    <div class="min-w-0">
                        <h3 class="font-mono text-[10.5px] font-bold uppercase tracking-[0.14em] text-faint">{{ $title }}</h3>
                        <ul class="mt-3 flex flex-col gap-2 text-[13.5px]">
                            @foreach ($links as [$label, $href])
                                <li><a href="{{ $href }}" class="font-medium text-muted transition hover:text-ink">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            {{-- Contact. Centred while the footer is stacked, like the block above. --}}
            <div class="text-center lg:col-span-3 lg:text-left">
                <h3 class="font-mono text-[10.5px] font-bold uppercase tracking-[0.14em] text-faint">{{ __('Contact') }}</h3>
                <ul class="mt-3 flex flex-col items-center gap-2 text-[13.5px] lg:items-start">
                    <li>
                        <a href="tel:+8801922992279" class="inline-flex items-center gap-2 font-medium text-muted transition hover:text-ink">
                            <x-icon name="phone" class="size-4 text-brand" /> <span class="num">+880 1922-992279</span>
                        </a>
                    </li>
                    <li>
                        <a href="mailto:hello@sofor.io" class="inline-flex items-center gap-2 font-medium text-muted transition hover:text-ink">
                            <x-icon name="mail" class="size-4 text-brand" /> hello@sofor.io
                        </a>
                    </li>
                    <li class="flex items-start gap-2 text-[13px] leading-snug text-muted">
                        <x-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-brand" />
                        <span>{{ __('Lift 6, Elite House, 54 Motijheel C/A, Dhaka 1000') }}</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="flex flex-col items-center justify-between gap-3 border-t border-line py-5 text-[12px] text-faint sm:flex-row">
            <p>&copy; {{ date('Y') }} Sofor. {{ __('All rights reserved.') }}</p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                <span class="inline-flex items-center gap-1.5"><x-icon name="shield" class="size-3.5 text-brand" /> {{ __('No ads, no tracking') }}</span>
                <button type="button" @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                        class="inline-flex items-center gap-1 font-semibold text-muted transition hover:text-ink">
                    <x-icon name="chevron-up" class="size-3.5" /> {{ __('Back to top') }}
                </button>
            </div>
        </div>
    </div>
</footer>
