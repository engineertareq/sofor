@php
    use App\Models\Sponsor;

    /**
     * Sponsors page. Everything a sponsor is promised here is what the site
     * really does with their logo (the home page carousel, this page, a link
     * to their site) - nothing more. Audience numbers are real and only shown
     * once they are worth showing.
     */

    // The hero orbit: real logos first, spread round both rings; spare spots
    // show what kind of brands fit, plus one open "your logo" seat.
    $outerAngles = [-90, -30, 30, 90, 150, 210];
    $innerAngles = [-45, 45, 135, 225];
    $fillOrder = ['o1', 'o4', 'i0', 'i2', 'o2', 'o5', 'i1', 'i3', 'o3', 'o0'];
    $orbiting = $sponsors->take(count($fillOrder))->values();
    $seats = [];
    foreach ($fillOrder as $index => $seat) {
        $seats[$seat] = $orbiting[$index] ?? null;
    }
    $fillers = ['o0' => 'open', 'o1' => 'building', 'o2' => 'compass', 'o3' => 'phone', 'o4' => 'card', 'o5' => 'camera',
                'i0' => 'map-pin', 'i1' => 'globe', 'i2' => 'gift', 'i3' => 'heart'];

    // The uploaded favicon if there is one, otherwise the built in Sofor mark.
    $centerIcon = app(\App\Services\BrandingService::class)->faviconUrl(192);

    $packages = [
        'silver' => [
            'title' => __('Get on the map'),
            'text' => __('A simple, steady presence for local and growing brands.'),
            'items' => [__('Your logo in the home page carousel'), __('Your logo on the sponsors wall'), __('A link straight to your website')],
        ],
        'gold' => [
            'title' => __('Stand out'),
            'text' => __('More room to say who you are, and a place further forward.'),
            'items' => [__('Everything in Silver'), __('A large tile with your tagline'), __('Shown ahead of Silver sponsors')],
        ],
        'platinum' => [
            'title' => __('Lead the way'),
            'text' => __('The headline spot for brands that want to be seen first.'),
            'items' => [__('Everything in Gold'), __('A featured card at the top of this page'), __('First in the home page carousel')],
        ],
    ];

    $sent = session('sponsor_inquiry');
@endphp

<x-layouts.site :wide="true" :card="false" title="{{ __('Sponsors') }}"
                :description="__('Sponsor Sofor and put your brand in front of travellers while they plan their next trip.')">

    <div x-data="{
             tier: @js((string) old('tier', $selectedTier)),
             pick(tier) {
                 this.tier = tier;
                 document.getElementById('contact')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                 setTimeout(() => document.getElementById('name')?.focus({ preventScroll: true }), 650);
             },
         }">

        {{-- ==================================================================
             HERO
             ================================================================== --}}
        <section class="relative isolate overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
                <div class="absolute -left-40 top-24 size-[32rem] rounded-full opacity-70 blur-3xl"
                     style="background: radial-gradient(circle, var(--accent-tint), transparent 66%)"></div>
                <div class="absolute -right-48 -top-20 size-[42rem] rounded-full opacity-80 blur-3xl"
                     style="background: radial-gradient(circle, var(--brand-tint), transparent 64%)"></div>
            </div>

            <div class="mx-auto grid w-full max-w-6xl items-center gap-10 px-4 pb-14 pt-10 sm:px-6 sm:pt-14 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:gap-8 lg:px-8 lg:pb-20 lg:pt-12">
                <div class="relative z-10 text-center lg:text-left">
                    <span class="inline-flex items-center gap-2 rounded-full border-[1.5px] border-line bg-surface py-1 pl-1 pr-3.5 text-[12.5px] font-semibold text-muted shadow-soft">
                        <span class="flex size-6 items-center justify-center rounded-full bg-accent text-ink"><x-icon name="building" class="size-3.5" /></span>
                        {{ __('Sponsor Sofor') }}
                    </span>

                    <h1 class="mt-6 font-headline text-[2.4rem] font-bold leading-[1.08] tracking-[-0.02em] text-ink sm:text-[3.2rem] lg:text-[3.6rem]">
                        {{ __('Ride along on') }}<br class="hidden sm:block"> {{ __('every trip.') }}
                    </h1>

                    <p class="mx-auto mt-5 max-w-[46ch] text-[16px] leading-relaxed text-muted sm:text-[17px] lg:mx-0">
                        {{ __('Sofor is where groups plan their trips, split the costs and map the places they have been. Sponsor it, and your brand is right there while they plan.') }}
                    </p>

                    <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center lg:justify-start">
                        <x-btn href="#contact" size="lg" icon-after="arrow-right">{{ __('Become a sponsor') }}</x-btn>
                        <x-btn href="#benefits" size="lg" variant="outline" icon="sparkles">{{ __('Why sponsor') }}</x-btn>
                    </div>

                    <ul class="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-[12.5px] font-medium text-muted lg:justify-start">
                        @foreach ([__('Your logo, your link'), __('No pop-ups or tracking'), __('Three tiers to choose from')] as $point)
                            <li class="flex items-center gap-1.5"><x-icon name="check-circle" class="size-4 text-positive" /> {{ $point }}</li>
                        @endforeach
                    </ul>
                </div>

                {{-- The orbit: sponsor logos circling the Sofor mark. --}}
                <div class="relative mx-auto w-full max-w-[20rem] sm:max-w-[25rem] lg:max-w-[29rem]">
                    <p class="sr-only">{{ __('Illustration: sponsor logos circling the Sofor logo.') }}</p>

                    <div aria-hidden="true" class="orbit relative aspect-square w-full">
                        <div class="absolute inset-[18%] rounded-full opacity-90 blur-2xl" style="background: radial-gradient(circle, var(--brand-tint), transparent 70%)"></div>

                        @foreach ([['o', $outerAngles, 'inset-[9%]', '90s', ''], ['i', $innerAngles, 'inset-[29%]', '70s', 'is-reverse']] as [$ring, $angles, $inset, $duration, $direction])
                            <div class="orbit-ring {{ $inset }} {{ $direction }}" style="--orbit-duration: {{ $duration }}">
                                @foreach ($angles as $index => $angle)
                                    @php
                                        $seat = $ring.$index;
                                        $sponsor = $seats[$seat];
                                        $filler = $fillers[$seat];
                                    @endphp
                                    <div class="orbit-chip" style="--angle: {{ $angle }}deg">
                                        @if ($sponsor)
                                            <span class="flex size-14 items-center justify-center rounded-2xl border-[1.5px] border-line bg-white p-2 shadow-lift sm:size-[4.5rem] sm:p-2.5">
                                                <img src="{{ $sponsor->logoUrl() }}" alt="" class="max-h-full max-w-full object-contain">
                                            </span>
                                        @elseif ($filler === 'open')
                                            <span class="flex size-14 flex-col items-center justify-center gap-0.5 rounded-2xl border-[1.5px] border-dashed border-brand/60 bg-brand-tint text-center text-[9.5px] font-bold uppercase leading-tight tracking-wide text-brand sm:size-[4.5rem] sm:text-[10.5px]">
                                                <x-icon name="plus" class="size-4" /> {{ __('Your logo') }}
                                            </span>
                                        @else
                                            <span class="flex size-10 items-center justify-center rounded-full border-[1.5px] border-line bg-surface text-faint shadow-soft sm:size-12">
                                                <x-icon :name="$filler" class="size-4 sm:size-5" />
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        <div class="absolute inset-[40%]">
                            @if ($centerIcon)
                                <span class="flex size-full items-center justify-center overflow-hidden rounded-[28%] border-2 border-ink bg-white shadow-sticker">
                                    <img src="{{ $centerIcon }}" alt="" class="size-[72%] object-contain">
                                </span>
                            @else
                                <span class="flex size-full items-center justify-center rounded-[28%] border-2 border-ink bg-brand text-white shadow-sticker">
                                    <svg viewBox="0 0 24 24" fill="none" class="size-[62%]">
                                        <path d="M4 18.5c2.4-5.4 4.3-8.1 6.6-8.1 2.2 0 3 2.6 4.6 2.6 1.2 0 2-.9 2.4-2.1"
                                              stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-dasharray="2.6 2.6" />
                                        <circle cx="4" cy="18.5" r="2.1" fill="currentColor" />
                                        <circle cx="17.6" cy="10.9" r="2.6" fill="var(--accent)" stroke="#fff" stroke-width="1.4" />
                                    </svg>
                                </span>
                            @endif
                        </div>
                    </div>

                    <span aria-hidden="true" class="absolute left-0 top-[1%] hidden animate-float items-center gap-1.5 rounded-full border border-line bg-surface/90 px-3 py-1.5 text-[11.5px] font-semibold text-ink shadow-lift backdrop-blur sm:inline-flex">
                        <x-icon name="sparkles" class="size-3.5 text-accent-2" /> {{ __('On the home page') }}
                    </span>
                    <span aria-hidden="true" class="absolute bottom-[1%] right-0 hidden animate-float items-center gap-1.5 rounded-full border border-line bg-surface/90 px-3 py-1.5 text-[11.5px] font-semibold text-ink shadow-lift backdrop-blur [animation-delay:-4s] sm:inline-flex">
                        <x-icon name="arrow-up-right" class="size-3.5 text-brand" /> {{ __('Links to your site') }}
                    </span>
                </div>
            </div>
        </section>

        {{-- ==================================================================
             THE SPONSORS
             ================================================================== --}}
        <section id="sponsors" class="scroll-mt-20 border-y border-line bg-surface">
            <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div data-reveal class="mx-auto max-w-2xl text-center">
                    <p class="eyebrow text-brand">{{ __('Our sponsors') }}</p>
                    <h2 class="mt-3 font-display text-[2rem] font-semibold leading-[1.08] tracking-tight text-ink sm:text-[2.6rem]">
                        {{ __('The brands that keep Sofor moving.') }}
                    </h2>
                    <p class="mt-4 text-[15px] leading-relaxed text-muted">
                        {{ __('Thank you. Your support keeps Sofor free and free of ads for every traveller.') }}
                    </p>
                </div>

                @if ($byTier->isEmpty())
                    <div data-reveal class="mx-auto mt-12 flex max-w-2xl flex-col items-center rounded-5xl border-[1.5px] border-dashed border-line-strong bg-canvas px-6 py-14 text-center">
                        <span class="sticker flex size-16 items-center justify-center rounded-3xl border-[1.5px] border-ink bg-accent text-ink">
                            <x-icon name="building" class="size-7" />
                        </span>
                        <h3 class="mt-6 font-display text-[22px] font-semibold text-ink">{{ __('This wall is waiting for its first logo.') }}</h3>
                        <p class="mt-2 max-w-md text-[14px] leading-relaxed text-muted">
                            {{ __('Be the brand travellers see first - at the top of this page and in the home page carousel.') }}
                        </p>
                        <x-btn href="#contact" class="mt-7" icon-after="arrow-right">{{ __('Claim the first spot') }}</x-btn>
                    </div>
                @else
                    <div class="mt-12 flex flex-col gap-12">
                        @foreach ($byTier as $tier => $group)
                            <div data-reveal>
                                <div class="mb-5 flex items-center gap-3">
                                    <x-sponsor-tier :tier="$tier" />
                                    <span class="h-px flex-1 bg-line" aria-hidden="true"></span>
                                </div>

                                @if ($tier === 'platinum')
                                    <div @class(['grid gap-5', 'md:grid-cols-2' => $group->count() > 1])>
                                        @foreach ($group as $sponsor)
                                            <article @class([
                                                'group relative grid gap-6 rounded-5xl border-2 border-ink bg-canvas p-5 shadow-sticker transition duration-300 hover:-translate-y-1 sm:p-7',
                                                'md:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] md:items-center md:gap-10' => $group->count() === 1,
                                            ])>
                                                <div class="flex h-32 items-center justify-center rounded-4xl border border-line bg-white p-6 sm:h-40">
                                                    <img src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }} logo" loading="lazy" class="max-h-full max-w-[80%] object-contain">
                                                </div>
                                                <div class="min-w-0">
                                                    <h3 class="font-display text-[24px] font-semibold leading-tight text-ink">{{ $sponsor->name }}</h3>
                                                    @if ($sponsor->tagline)
                                                        <p class="mt-2 text-[14.5px] leading-relaxed text-muted">{{ $sponsor->tagline }}</p>
                                                    @endif
                                                    @if ($sponsor->website)
                                                        <a href="{{ $sponsor->website }}" target="_blank" rel="sponsored noopener"
                                                           class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-ink px-4 py-2 text-[13px] font-semibold text-canvas transition after:absolute after:inset-0 after:rounded-5xl after:content-[''] group-hover:bg-brand">
                                                            {{ __('Visit :website_host', ['website_host' => $sponsor->websiteHost()]) }} <x-icon name="arrow-up-right" class="size-3.5" />
                                                        </a>
                                                    @endif
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                @elseif ($tier === 'gold')
                                    <div @class([
                                        'grid gap-4',
                                        'max-w-md' => $group->count() === 1,
                                        'sm:grid-cols-2' => $group->count() >= 2,
                                        'lg:grid-cols-3' => $group->count() >= 3,
                                    ])>
                                        @foreach ($group as $sponsor)
                                            <article class="group relative flex flex-col rounded-4xl border-[1.5px] border-line bg-canvas p-4 transition duration-300 hover:-translate-y-1 hover:border-line-strong hover:shadow-lift sm:p-5">
                                                <div class="flex h-28 items-center justify-center rounded-3xl border border-line bg-white p-5">
                                                    <img src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }} logo" loading="lazy" class="max-h-full max-w-[80%] object-contain">
                                                </div>
                                                <h3 class="mt-4 font-display text-[18px] font-semibold text-ink">{{ $sponsor->name }}</h3>
                                                @if ($sponsor->tagline)
                                                    <p class="mt-1 line-clamp-2 text-[13px] leading-relaxed text-muted">{{ $sponsor->tagline }}</p>
                                                @endif
                                                @if ($sponsor->website)
                                                    <a href="{{ $sponsor->website }}" target="_blank" rel="sponsored noopener"
                                                       class="mt-auto inline-flex items-center gap-1 pt-4 text-[12.5px] font-semibold text-brand after:absolute after:inset-0 after:rounded-4xl after:content-['']">
                                                        {{ $sponsor->websiteHost() }}
                                                        <x-icon name="arrow-up-right" class="size-3.5 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                                                    </a>
                                                @endif
                                            </article>
                                        @endforeach
                                    </div>
                                @else
                                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                        @foreach ($group as $sponsor)
                                            <li>
                                                <a href="{{ $sponsor->website ?: '#sponsors' }}" @if ($sponsor->website) target="_blank" rel="sponsored noopener" @endif
                                                   class="sponsor-logo group flex h-full flex-col items-center gap-2 rounded-3xl border-[1.5px] border-line bg-white px-3 py-4 text-center transition duration-300 hover:-translate-y-0.5 hover:border-line-strong hover:shadow-soft">
                                                    <span class="flex h-14 w-full items-center justify-center">
                                                        <img src="{{ $sponsor->logoUrl() }}" alt="" loading="lazy" class="max-h-11 max-w-[85%] object-contain">
                                                    </span>
                                                    <span class="w-full truncate text-[12px] font-semibold text-muted transition group-hover:text-ink">{{ $sponsor->name }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div data-reveal class="mt-12 flex flex-col items-center gap-4 rounded-4xl border-[1.5px] border-dashed border-line-strong bg-canvas px-6 py-6 text-center sm:flex-row sm:text-left">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-tint text-brand"><x-icon name="plus" class="size-5" /></span>
                        <div class="min-w-0">
                            <p class="font-display text-[17px] font-semibold text-ink">{{ __('There is room for you on this wall.') }}</p>
                            <p class="mt-0.5 text-[13.5px] text-muted">{{ __('Pick a tier below, or just tell us what you have in mind.') }}</p>
                        </div>
                        <x-btn href="#contact" size="sm" variant="outline" icon-after="arrow-right" class="shrink-0 sm:ml-auto">{{ __('Become a sponsor') }}</x-btn>
                    </div>
                @endif
            </div>
        </section>

        {{-- ==================================================================
             WHY SPONSOR
             ================================================================== --}}
        <section id="benefits" class="mx-auto w-full max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <div data-reveal class="mx-auto max-w-2xl text-center">
                <p class="eyebrow text-brand">{{ __('Why sponsor Sofor') }}</p>
                <h2 class="mt-3 font-display text-[2rem] font-semibold leading-[1.08] tracking-tight text-ink sm:text-[2.75rem]">
                    {{ __('Be there while the trip is being planned.') }}
                </h2>
                <p class="mt-4 text-[15.5px] leading-relaxed text-muted">
                    {{ __('People open Sofor when a trip is coming up - which is exactly when they are choosing how to travel, where to stay and what to pack.') }}
                </p>
            </div>

            @if ($audience)
                <dl data-reveal class="mx-auto mt-10 grid max-w-3xl grid-cols-3 gap-3">
                    @foreach ([[__('Travellers'), $audience['travellers']], [__('Trips planned'), $audience['trips']], [__('Places mapped'), $audience['places']]] as [$label, $value])
                        <div class="rounded-4xl border-[1.5px] border-line bg-surface px-3 py-4 text-center sm:px-5 sm:py-5">
                            <dd class="num font-display text-[1.6rem] font-semibold leading-none text-ink sm:text-[2.2rem]">{{ number_format($value) }}</dd>
                            <dt class="mt-2 text-[11.5px] font-medium text-muted sm:text-[13px]">{{ $label }}</dt>
                        </div>
                    @endforeach
                </dl>
            @endif

            <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {{-- A: the home page strip --}}
                <article data-reveal class="grid gap-6 overflow-hidden rounded-5xl border-[1.5px] border-line bg-surface p-6 shadow-soft sm:p-7 md:col-span-2 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:items-center">
                    <div>
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-brand-tint text-brand"><x-icon name="sparkles" class="size-5" /></span>
                        <h3 class="mt-5 font-display text-[22px] font-semibold leading-tight text-ink">{{ __('Front row on the home page') }}</h3>
                        <p class="mt-2 text-[14px] leading-relaxed text-muted">
                            {{ __('Your logo scrolls past in the "Sponsored by" strip, right under the headline - the first thing everyone sees when they arrive.') }}
                        </p>
                    </div>

                    {{-- Illustration of the strip, with the open seat highlighted. --}}
                    <div aria-hidden="true" class="rounded-4xl border border-line bg-canvas p-4">
                        <div class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-line-strong"></span>
                            <span class="size-2 rounded-full bg-line-strong"></span>
                            <span class="size-2 rounded-full bg-line-strong"></span>
                            <span class="ml-2 h-4 flex-1 rounded-full bg-surface-2"></span>
                        </div>
                        <div class="mt-5 space-y-2">
                            <span class="block h-3.5 w-3/5 rounded-full bg-ink/80"></span>
                            <span class="block h-3.5 w-2/5 rounded-full bg-ink/80"></span>
                            <span class="block h-2 w-4/5 rounded-full bg-line"></span>
                        </div>
                        <p class="mt-5 text-center font-mono text-[8.5px] font-bold uppercase tracking-[0.16em] text-faint">{{ __('Sponsored by') }}</p>
                        <div class="mt-2 grid grid-cols-4 gap-2">
                            <span class="h-9 rounded-lg border border-line bg-surface"></span>
                            <span class="flex h-9 items-center justify-center rounded-lg border-[1.5px] border-dashed border-brand bg-brand-tint text-[9px] font-bold uppercase tracking-wide text-brand">{{ __('You') }}</span>
                            <span class="h-9 rounded-lg border border-line bg-surface"></span>
                            <span class="h-9 rounded-lg border border-line bg-surface"></span>
                        </div>
                    </div>
                </article>

                @foreach ([
                    ['building', 'brand', __('A spot on the sponsors wall'), __('Your logo on this page, with a line about who you are and a link to your site.')],
                    ['compass', 'accent', __('Perfect timing'), __('Transport, stays, food and gear are all on the table while a group plans. That is when Sofor is open.')],
                    ['shield', 'brand', __('Clean placement, no tracking'), __('Logos and links only. No pop-ups, no banner ads, no tracking pixels - part of why travellers trust Sofor.')],
                    ['heart', 'accent', __('Keep Sofor free'), __('Sofor is free for everyone with no limits. Your support keeps it that way, and keeps it free of ads.')],
                ] as $index => [$icon, $tone, $title, $text])
                    <article data-reveal style="--reveal-delay: {{ ($index % 3) * 80 }}ms"
                             class="flex flex-col rounded-5xl border-[1.5px] border-line bg-surface p-6 shadow-soft transition duration-300 hover:-translate-y-1 hover:shadow-lift sm:p-7">
                        <span @class([
                            'flex size-11 items-center justify-center rounded-2xl',
                            'bg-brand-tint text-brand' => $tone === 'brand',
                            'bg-accent-tint text-accent-2' => $tone === 'accent',
                        ])><x-icon :name="$icon" class="size-5" /></span>
                        <h3 class="mt-5 font-display text-[20px] font-semibold leading-tight text-ink">{{ $title }}</h3>
                        <p class="mt-2 text-[14px] leading-relaxed text-muted">{{ $text }}</p>
                    </article>
                @endforeach

                {{-- Who it suits --}}
                <article data-reveal class="relative overflow-hidden rounded-5xl border-2 border-ink bg-brand p-6 text-white shadow-sticker sm:p-7 md:col-span-2 lg:col-span-3">
                    <div aria-hidden="true" class="absolute inset-0 opacity-[0.12]"
                         style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1.3px); background-size: 18px 18px;"></div>
                    <div class="relative grid gap-5 lg:grid-cols-[minmax(0,0.75fr)_minmax(0,1.25fr)] lg:items-center lg:gap-10">
                        <div>
                            <span class="flex size-11 items-center justify-center rounded-2xl bg-white/15"><x-icon name="flag" class="size-5" /></span>
                            <h3 class="mt-5 font-display text-[22px] font-semibold leading-tight">{{ __('Made for travel brands') }}</h3>
                            <p class="mt-2 text-[14px] leading-relaxed text-white/75">{{ __('If travellers would be glad to see you on the way, you fit right in.') }}</p>
                        </div>
                        <ul class="flex flex-wrap gap-2 lg:justify-end">
                            @foreach ([__('Bus and air'), __('Hotels and resorts'), __('Tour operators'), __('Outdoor gear'), __('Mobile data'), __('Banks and wallets'), __('Food and cafes')] as $kind)
                                <li class="rounded-full border border-white/25 bg-white/10 px-3.5 py-1.5 text-[12.5px] font-medium text-white/90">{{ $kind }}</li>
                            @endforeach
                        </ul>
                    </div>
                </article>
            </div>
        </section>

        {{-- ==================================================================
             PACKAGES
             ================================================================== --}}
        <section id="tiers" class="relative isolate scroll-mt-16 overflow-hidden bg-ink text-canvas">
            <div aria-hidden="true" class="absolute inset-0 -z-10 opacity-[0.12]"
                 style="background-image: radial-gradient(circle, #fbf8f2 1px, transparent 1.3px); background-size: 22px 22px;"></div>
            <div aria-hidden="true" class="absolute -left-32 bottom-0 -z-10 size-[30rem] rounded-full opacity-30 blur-3xl"
                 style="background: radial-gradient(circle, var(--brand), transparent 65%)"></div>

            <div class="mx-auto w-full max-w-6xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
                <div data-reveal class="mx-auto max-w-2xl text-center">
                    <p class="eyebrow text-accent">{{ __('Packages') }}</p>
                    <h2 class="mt-3 font-display text-[2rem] font-semibold leading-[1.08] tracking-tight sm:text-[2.75rem]">{{ __('Pick how your brand shows up.') }}</h2>
                    <p class="mt-4 text-[15.5px] leading-relaxed text-canvas/70">
                        {{ __('Pricing depends on the tier and how long you want to run. Tell us what you have in mind and we will send you the details.') }}
                    </p>
                </div>

                <div class="mt-14 grid gap-5 lg:grid-cols-3 lg:items-stretch">
                    @foreach ($packages as $key => $package)
                        @php $top = $key === 'platinum'; @endphp
                        <div data-reveal style="--reveal-delay: {{ $loop->index * 90 }}ms"
                             @class([
                                 'relative flex flex-col rounded-5xl p-7 sm:p-8',
                                 'border-2 border-accent bg-canvas text-ink shadow-[6px_6px_0_0_var(--accent)]' => $top,
                                 'border border-canvas/15 bg-canvas/[0.05] text-canvas' => ! $top,
                             ])>
                            @if ($top)
                                <span class="absolute -top-3.5 right-7 rounded-full border-2 border-ink bg-accent px-3 py-1 font-mono text-[10px] font-bold uppercase tracking-[0.1em] text-ink">{{ __('Top tier') }}</span>
                            @endif

                            <x-sponsor-tier :tier="$key" class="self-start" />
                            <p class="mt-5 font-display text-[26px] font-semibold leading-tight">{{ $package['title'] }}</p>
                            <p @class(['mt-2 text-[14px] leading-relaxed', 'text-muted' => $top, 'text-canvas/65' => ! $top])>{{ $package['text'] }}</p>

                            <ul class="mt-7 flex flex-col gap-3 text-[14px]">
                                @foreach ($package['items'] as $item)
                                    <li class="flex items-start gap-2.5">
                                        <x-icon name="check-circle" @class(['mt-0.5 size-4 shrink-0', 'text-brand' => $top, 'text-accent' => ! $top]) />
                                        {{ $item }}
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-auto pt-8">
                                <a href="{{ route('sponsors', ['tier' => $key]) }}#contact" @click.prevent="pick(@js($key))"
                                   @class([
                                       'flex h-12 w-full items-center justify-center gap-2 rounded-full text-[14.5px] font-semibold transition',
                                       'sticker bg-ink text-canvas hover:bg-brand' => $top,
                                       'border border-canvas/25 text-canvas hover:border-accent hover:bg-accent hover:text-ink' => ! $top,
                                   ])>
                                    {{ __('Ask about :key', ['key' => __(Sponsor::TIERS[$key])]) }} <x-icon name="arrow-right" class="size-4" />
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ==================================================================
             GET IN TOUCH
             ================================================================== --}}
        <section id="contact" class="mx-auto w-full max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-14">
                <div data-reveal>
                    <p class="eyebrow text-brand">{{ __('Get in touch') }}</p>
                    <h2 class="mt-3 font-display text-[2rem] font-semibold leading-[1.08] tracking-tight text-ink sm:text-[2.6rem]">
                        {{ __('Let\'s put your brand on the map.') }}
                    </h2>
                    <p class="mt-4 text-[15px] leading-relaxed text-muted">
                        {{ __('Tell us a little about your brand. A real person on the Sofor team reads every request and replies by email.') }}
                    </p>

                    <ol class="mt-9 flex flex-col gap-6">
                        @foreach ([
                            [__('Send the form'), __('Who you are, which tier you like, and anything else we should know.')],
                            [__('Agree the details'), __('We reply with the options and sort out the length and the logo.')],
                            [__('Go live'), __('Your logo joins the home page carousel and this page, linked to your site.')],
                        ] as $index => [$title, $text])
                            <li class="relative flex gap-4">
                                @unless ($loop->last)
                                    <span aria-hidden="true" class="absolute -bottom-6 left-5 top-10 border-l-2 border-dashed border-line-strong"></span>
                                @endunless
                                <span class="num relative z-10 flex size-10 shrink-0 items-center justify-center rounded-xl border-2 border-ink bg-accent font-display text-[17px] font-semibold text-ink">{{ $index + 1 }}</span>
                                <div class="min-w-0 pt-0.5">
                                    <p class="font-display text-[17px] font-semibold text-ink">{{ $title }}</p>
                                    <p class="mt-0.5 text-[13.5px] leading-relaxed text-muted">{{ $text }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-9 rounded-4xl border border-line bg-surface p-5">
                        <p class="text-[13px] font-semibold text-ink">{{ __('Prefer to talk?') }}</p>
                        <div class="mt-3 flex flex-col gap-2 text-[14px]">
                            <a href="mailto:hello@sofor.io?subject={{ rawurlencode(__('Sponsoring Sofor')) }}" class="inline-flex items-center gap-2 font-medium text-muted transition hover:text-brand">
                                <x-icon name="mail" class="size-4 text-brand" /> hello@sofor.io
                            </a>
                            <a href="tel:+8801922992279" class="inline-flex items-center gap-2 font-medium text-muted transition hover:text-brand">
                                <x-icon name="phone" class="size-4 text-brand" /> <span class="num">+880 1922-992279</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div data-reveal style="--reveal-delay: 90ms">
                    <div class="rounded-5xl border-2 border-ink bg-surface p-5 shadow-sticker sm:p-8">
                        @if ($sent)
                            <div class="flex flex-col items-center py-10 text-center" role="status">
                                <span class="flex size-16 animate-pop items-center justify-center rounded-3xl bg-positive text-white shadow-lift">
                                    <x-icon name="check" class="size-8" />
                                </span>
                                <h3 class="mt-6 font-display text-[26px] font-semibold text-ink">{{ __('Thank you, :name!', ['name' => $sent['name']]) }}</h3>
                                <p class="mt-2 max-w-sm text-[14.5px] leading-relaxed text-muted">
                                    {{ __('Your request is with the Sofor team. We will reply to') }}
                                    <strong class="font-semibold text-ink">{{ $sent['email'] }}</strong>.
                                </p>
                                <div class="mt-8 flex flex-col gap-2.5 sm:flex-row">
                                    <x-btn :href="route('home')" variant="outline" icon="home">{{ __('Back to home') }}</x-btn>
                                    <x-btn :href="route('sponsors').'#contact'" variant="ghost">{{ __('Send another request') }}</x-btn>
                                </div>
                            </div>
                        @else
                            <form method="POST" action="{{ route('sponsors.inquiries.store') }}" class="flex flex-col gap-5"
                                  x-data="{ message: @js((string) old('message', '')), sending: false }" @submit="sending = true">
                                @csrf

                                {{-- Left empty by people; bots fill it in. --}}
                                <div class="absolute -left-[9999px] size-px overflow-hidden" aria-hidden="true">
                                    <label for="nickname">{{ __('Leave this field empty') }}</label>
                                    <input type="text" id="nickname" name="{{ \App\Http\Requests\StoreSponsorInquiryRequest::HONEYPOT }}" tabindex="-1" autocomplete="off">
                                </div>

                                <div>
                                    <h3 class="font-display text-[22px] font-semibold text-ink">{{ __('Become a sponsor') }}</h3>
                                    <p class="mt-1 text-[13px] text-muted">{{ __('Fields marked') }} <span class="text-negative">*</span> {{ __('are required.') }}</p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-field name="name" :label="__('Your name')" required>
                                        <x-input name="name" maxlength="80" autocomplete="name" required />
                                    </x-field>
                                    <x-field name="company" :label="__('Brand or company')" required>
                                        <x-input name="company" maxlength="120" autocomplete="organization" required />
                                    </x-field>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-field name="email" :label="__('Work email')" required>
                                        <x-input name="email" type="email" maxlength="160" autocomplete="email" icon="mail" required />
                                    </x-field>
                                    <x-field name="phone" :label="__('Phone')">
                                        <x-input name="phone" type="tel" maxlength="30" autocomplete="tel" icon="phone" placeholder="{{ __('Optional') }}" />
                                    </x-field>
                                </div>

                                <x-field name="website" :label="__('Website')">
                                    <x-input name="website" maxlength="255" autocomplete="url" icon="link" inputmode="url" placeholder="{{ __('yourbrand.com') }}" />
                                </x-field>

                                <fieldset>
                                    <legend class="mb-1.5 text-[13px] font-medium text-muted">{{ __('Interested in') }}</legend>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        @foreach (['silver' => __('Silver'), 'gold' => __('Gold'), 'platinum' => __('Platinum'), '' => __('Not sure yet')] as $value => $label)
                                            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-2xl border-[1.5px] px-3 py-2.5 text-[13px] font-semibold transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/40"
                                                   :class="tier === @js($value) ? 'border-ink bg-ink text-canvas' : 'border-line bg-surface text-muted hover:border-line-strong hover:text-ink'">
                                                <input type="radio" name="tier" value="{{ $value }}" x-model="tier" class="sr-only"
                                                       @checked((string) old('tier', $selectedTier) === $value)>
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('tier')
                                        <p class="mt-1.5 text-xs font-medium text-negative">{{ $message }}</p>
                                    @enderror
                                </fieldset>

                                <x-field name="message" :label="__('What do you have in mind?')" required>
                                    <div class="relative">
                                        <x-textarea name="message" rows="5" maxlength="2000" x-model="message" required
                                                    placeholder="{{ __('Your brand, who you want to reach, and roughly how long you would like to run.') }}" />
                                        <span class="num pointer-events-none absolute bottom-2.5 right-3 text-[10.5px] text-faint" x-text="window.SoforNum(message.length) + '/' + window.SoforNum(2000)"></span>
                                    </div>
                                </x-field>

                                <div class="flex flex-col-reverse gap-4 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="flex items-start gap-2 text-[11.5px] leading-relaxed text-faint sm:max-w-[16rem]">
                                        <x-icon name="lock" class="mt-0.5 size-3.5 shrink-0" />
                                        {{ __('We only use these details to reply about sponsoring.') }}
                                    </p>
                                    <x-btn type="submit" size="lg" icon="send" class="shrink-0" x-bind:disabled="sending">
                                        <span x-text="sending ? @js(__('Sending...')) : @js(__('Send request'))">{{ __('Send request') }}</span>
                                    </x-btn>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- ==================================================================
             FAQ
             ================================================================== --}}
        <section class="border-t border-line bg-surface">
            <div class="mx-auto grid w-full max-w-6xl gap-10 px-4 py-20 sm:px-6 sm:py-24 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16 lg:px-8">
                <div data-reveal>
                    <p class="eyebrow text-brand">{{ __('Questions') }}</p>
                    <h2 class="mt-3 font-display text-[2rem] font-semibold leading-[1.08] tracking-tight text-ink sm:text-[2.6rem]">{{ __('Before you ask.') }}</h2>
                    <p class="mt-4 text-[15px] leading-relaxed text-muted">{{ __('Anything else? Send it with the form above and we will answer.') }}</p>
                </div>

                <div data-reveal x-data="{ open: 0 }" class="divide-y divide-line border-y border-line">
                    @foreach ([
                        [__('How much does sponsoring cost?'), __('It depends on the tier and how long you want to run. Send the form with what you have in mind and we will reply with the options.')],
                        [__('How soon does my logo go live?'), __('As soon as we have agreed the details and have your logo. Adding it to Sofor takes us a couple of minutes.')],
                        [__('Can I change my logo or link later?'), __('Yes. Send us the new logo or address and we will swap it - your spot stays the same.')],
                        [__('Do you track visitors for sponsors?'), __('No. Sofor has no ads and no tracking pixels, and that does not change for sponsors. Your logo links to your site, so your own analytics see every visit we send.')],
                        [__('What kind of brands do you work with?'), __('Brands travellers are glad to see: transport, stays, tours, outdoor gear, mobile data, payments and food. Not sure you fit? Ask us.')],
                    ] as $index => [$question, $answer])
                        <div>
                            <h3>
                                <button type="button" @click="open = open === {{ $index }} ? null : {{ $index }}"
                                        :aria-expanded="(open === {{ $index }}).toString()" aria-controls="sponsor-faq-{{ $index }}"
                                        class="flex w-full items-center justify-between gap-5 py-5 text-left">
                                    <span class="font-display text-[17px] font-semibold text-ink">{{ $question }}</span>
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full border-[1.5px] border-line-strong text-muted transition"
                                          :class="open === {{ $index }} && 'rotate-45 border-ink bg-ink text-canvas'">
                                        <x-icon name="plus" class="size-4" />
                                    </span>
                                </button>
                            </h3>
                            <div id="sponsor-faq-{{ $index }}" x-show="open === {{ $index }}" x-transition.opacity.duration.200ms @if ($index !== 0) x-cloak @endif>
                                <p class="pb-6 pr-12 text-[14.5px] leading-relaxed text-muted">{{ $answer }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</x-layouts.site>
