@php
    use App\Support\Money;

    /**
     * Landing page.
     *
     * The product pictures are illustrations built from real components, labelled
     * as samples where they show a trip. The Explorers figures and avatars are
     * real and only ever come from public profiles. No reviews or customers are
     * invented anywhere on this page.
     */

    // The hero route: a smooth curve through each district's centre (Catmull-Rom),
    // drawn on the real map so it lands where the places really are.
    $areas = $heroMap->areas();
    $points = array_map(fn (string $id) => [(float) $areas[$id]['cx'], (float) $areas[$id]['cy']], $heroRoute);
    $routePath = '';

    if ($points !== []) {
        $routePath = 'M'.$points[0][0].','.$points[0][1];

        for ($i = 0; $i < count($points) - 1; $i++) {
            [$p0, $p1, $p2, $p3] = [$points[$i - 1] ?? $points[$i], $points[$i], $points[$i + 1], $points[$i + 2] ?? $points[$i + 1]];
            $c1 = [round($p1[0] + ($p2[0] - $p0[0]) / 6, 1), round($p1[1] + ($p2[1] - $p0[1]) / 6, 1)];
            $c2 = [round($p2[0] - ($p3[0] - $p1[0]) / 6, 1), round($p2[1] - ($p3[1] - $p1[1]) / 6, 1)];
            $routePath .= " C{$c1[0]},{$c1[1]} {$c2[0]},{$c2[1]} {$p2[0]},{$p2[1]}";
        }
    }

    // Real numbers only once they are worth showing.
    $showExplorerProof = $explorers['count'] >= 3;
    $topExplorers = collect($explorers['top']);

    // Shown in the FAQ section and given to search engines as FAQPage data, so the two never disagree.
    $faqs = [
        [__('Is Sofor actually free?'), __('Yes, completely. Every feature, as many trips and travellers as you like, and no adverts. Sofor is kept running by its sponsors and by travellers who support it through bKash - never by locking anything away.')],
        [__('Do my friends need to install anything?'), __('No. You send a link, they open it in any phone browser and they are in. If they sign up later with the same phone or email, everything they already added is matched to them automatically.')],
        [__('Does Sofor touch our money?'), __('Never. Each traveller adds their own bKash, Nagad, Rocket or bank account, friends pay them directly, and the person who received it confirms. Sofor only keeps the record.')],
        [__('Can I log costs without signal?'), __('Yes. On the road to Sajek or Bandarban, add costs as usual - Sofor keeps them on your phone and sends them the moment you are back online, without ever logging one twice.')],
        [__('What is the checkpoint list?'), __('Hill-tract checkpoints and resorts ask for every traveller\'s name, age, ID and phone. Each traveller fills it in once, the leader prints it, and Sofor deletes it two weeks after the trip.')],
        [__('What if the total does not split evenly?'), __('One share carries the small leftover, so the ledger always balances to the exact taka. Nobody comes up short by a rounding error, and you can see who carries it.')],
        [__('Who can see my travel map?'), __('You choose. Your public profile shows your name, photo and travel map, and never your email, phone, trips or money. Switch it to private in your profile and it disappears from Explorers too.')],
        [__('What happens when the trip ends?'), __('The trip leader ends it and the ledger locks, so the numbers everybody agreed on stop moving. You can still download a PDF summary whenever you like.')],
    ];

    // What people search for: "group trip expense splitter", "trip expense calculator",
    // "ট্যুরের খরচ", "খরচের হিসাব রাখার অ্যাপ", "ভ্রমণ ম্যাপ".
    $seoTitle = __('Split group trip costs and settle up over bKash');
    $seoDescription = __('Free app to split group trip costs in Bangladesh: see who owes who, settle up over bKash and make a travel map of every district you have visited.');

    // Structured data for search engines: who runs Sofor, the site, the app (free), and the FAQ.
    $home = url('/');
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $home.'#organization',
                'name' => 'Sofor',
                'url' => $home,
                'logo' => asset('icons/icon-512.png'),
                'email' => 'hello@sofor.io',
                'telephone' => '+8801922992279',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => 'Lift 6, Elite House, 54 Motijheel C/A',
                    'addressLocality' => 'Dhaka',
                    'postalCode' => '1000',
                    'addressCountry' => 'BD',
                ],
                'parentOrganization' => ['@type' => 'Organization', 'name' => 'Solutor'],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'name' => 'Sofor',
                'alternateName' => ['সফর', 'Sofor trip app'],
                'url' => $home,
                'inLanguage' => ['bn', 'en'],
                'publisher' => ['@id' => $home.'#organization'],
            ],
            [
                '@type' => 'WebApplication',
                'name' => 'Sofor',
                'url' => \App\Support\Seo::url($home, app()->getLocale()),
                'description' => $seoDescription,
                'inLanguage' => app()->getLocale(),
                'applicationCategory' => 'TravelApplication',
                'applicationSubCategory' => 'Group trip expense splitter',
                'operatingSystem' => 'Any',
                'browserRequirements' => 'Requires a modern web browser',
                'isAccessibleForFree' => true,
                'featureList' => [__('Split group trip costs fairly'), __('Settle up over bKash, Nagad or bank'), __('Travel map of every district and country'), __('Works offline on the road')],
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'BDT'],
                'publisher' => ['@id' => $home.'#organization'],
            ],
            \App\Support\Schema::faq($faqs),
        ],
    ];

    $user = auth()->user();
    [$primaryHref, $primaryLabel] = match (true) {
        $user?->isAdmin() === true => [route('admin.dashboard'), __('Open the console')],
        $user !== null => [route('trips.create'), __('Start a trip')],
        default => [route('register'), __('Start a trip - it is free')],
    };
@endphp

<x-layouts.site :wide="true" :card="false" :title="$seoTitle" :schema="$schema" :description="$seoDescription">

    {{-- ==================================================================
         HERO
         ================================================================== --}}
    <section class="relative isolate overflow-hidden">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
            <div class="absolute -left-48 top-32 size-[36rem] rounded-full opacity-70 blur-3xl"
                 style="background: radial-gradient(circle, var(--accent-tint), transparent 66%)"></div>
            <div class="absolute -right-40 -top-24 size-[44rem] rounded-full opacity-80 blur-3xl"
                 style="background: radial-gradient(circle, var(--brand-tint), transparent 64%)"></div>
        </div>

        <div class="mx-auto grid w-full max-w-6xl items-center gap-12 px-4 pb-4 pt-8 sm:px-6 sm:pb-16 sm:pt-12 lg:min-h-[calc(100dvh-4.5rem)] lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:gap-6 lg:px-8 lg:pb-20 lg:pt-6">
            <div class="relative z-10 text-center lg:text-left">
                <a href="{{ route('explorers') }}"
                   class="group inline-flex max-w-full items-center gap-2 rounded-full border-[1.5px] border-line bg-surface py-1 pl-1 pr-3.5 text-[12.5px] font-semibold text-muted shadow-soft transition hover:border-line-strong hover:text-ink">
                    <span class="rounded-full bg-accent px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-ink">{{ __('New') }}</span>
                    <span class="truncate sm:hidden">{{ __('Meet the Explorers') }}</span>
                    <span class="hidden truncate sm:inline">{{ __('Explorers: who has seen the most of Bangladesh?') }}</span>
                    <x-icon name="arrow-right" class="size-3.5 shrink-0 transition group-hover:translate-x-0.5" />
                </a>

                {{-- The slogan, led by what the app is - the words people search for. --}}
                <h1 class="mt-7 font-headline text-[2.6rem] font-bold leading-[1.08] tracking-[-0.02em] text-ink max-[359px]:text-[2.25rem] sm:text-[3.4rem] lg:text-[4rem]">
                    <span class="mb-3 block font-sans text-[13px] font-semibold uppercase leading-snug tracking-[0.12em] text-brand sm:text-[14px]">{{ __('Split group trip costs, free') }}</span>
                    {{ __('Happy trips') }}<br>
                    {{ __('Tidy accounts') }}
                </h1>

                <p class="mx-auto mt-6 max-w-[44ch] text-pretty text-[16px] leading-relaxed text-muted sm:text-[17px] lg:mx-0">
                    {{ __('On a trip with friends, who spent how much and who owes whom - Sofor keeps it all in one place. You just focus on the journey.') }}
                </p>

                <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center lg:justify-start">
                    <x-btn :href="$primaryHref" size="lg" icon-after="arrow-right">{{ $primaryLabel }}</x-btn>
                    <x-btn :href="route('home').'#how'" size="lg" variant="outline" icon="play">{{ __('See how a trip goes') }}</x-btn>
                </div>

                <ul class="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-[12.5px] font-medium text-muted lg:justify-start">
                    @foreach ([__('Free, no trip limit'), __('Friends join free'), __('No app to install')] as $point)
                        <li class="flex items-center gap-1.5">
                            <x-icon name="check-circle" class="size-4 text-positive" /> {{ $point }}
                        </li>
                    @endforeach
                </ul>

                @if ($showExplorerProof)
                    <a href="{{ route('explorers') }}" class="mt-8 inline-flex items-center gap-3 rounded-full border border-line bg-surface/70 py-1.5 pl-1.5 pr-4 text-left backdrop-blur transition hover:border-line-strong">
                        <span class="flex">
                            @foreach ($topExplorers as $index => $entry)
                                <x-avatar :name="$entry['user']->name" :gradient="$entry['user']->avatarGradient()" :src="$entry['user']->avatarUrl()"
                                          size="xs" class="{{ $index > 0 ? '-ml-1.5' : '' }}" />
                            @endforeach
                        </span>
                        <span class="text-[12.5px] leading-snug text-muted">
                            <strong class="font-semibold text-ink">{{ __(':count explorers', ['count' => number_format($explorers['count'])]) }}</strong>
                            {{ __('have mapped :covered of :total districts', ['covered' => $explorers['covered'], 'total' => $explorers['total']]) }}
                        </span>
                    </a>
                @endif
            </div>

            {{-- The journey: a real map that colours in along a route. --}}
            <div class="relative mx-auto w-full max-w-[21rem] sm:max-w-[25rem] lg:max-w-[28rem]"
                 x-data="heroJourney(@js($heroRoute))">
                <p class="sr-only">
                    {{ __('Illustration: a map of Bangladesh colouring in district by district along a trip from Dhaka to Cox\'s Bazar.') }}
                </p>

                <div aria-hidden="true" class="relative mx-auto w-full py-4">
                    <div class="relative">
                        <x-travel-map :map="$heroMap" :static="true" class="hero-map drop-shadow-[0_30px_35px_rgba(15,111,106,0.14)]" />

                        <svg viewBox="0 0 {{ $heroMap->width() }} {{ $heroMap->height() }}" fill="none"
                             class="pointer-events-none absolute inset-0 h-full w-full">
                            <defs>
                                <mask id="hero-route-reveal">
                                    <path d="{{ $routePath }}" class="hero-route-mask" pathLength="1" stroke="#fff" stroke-width="40" stroke-linecap="round" />
                                </mask>
                            </defs>
                            <path d="{{ $routePath }}" mask="url(#hero-route-reveal)" stroke="var(--ink)" stroke-width="7"
                                  stroke-linecap="round" stroke-dasharray="0.5 16" />
                            @foreach ($points as $index => [$x, $y])
                                @php $last = $loop->last; @endphp
                                <circle data-stop="{{ $index }}" cx="{{ $x }}" cy="{{ $y }}" r="{{ $last ? 15 : 11 }}"
                                        class="hero-stop" fill="{{ $last ? 'var(--accent)' : '#fff' }}" stroke="var(--ink)" stroke-width="4.5" />
                            @endforeach
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================================================================
         SPONSORED BY
         Logos come from Admin -> Sponsors. Three or more scroll past in a
         loop; one or two sit still beside an open spot; none, and the space
         invites a first sponsor instead.
         ================================================================== --}}
    @php
        $scrollSponsors = $sponsors->count() >= 3;
        // Enough copies that one set is always wider than the strip, so the loop never shows a gap.
        $sponsorSet = $scrollSponsors ? collect(range(1, (int) ceil(9 / $sponsors->count())))->flatMap(fn () => $sponsors) : collect();
        $sponsorTile = 'sponsor-logo flex h-[4.5rem] w-40 shrink-0 items-center justify-center rounded-2xl border-[1.5px] border-line bg-surface px-5 transition duration-300 hover:-translate-y-0.5 hover:border-line-strong hover:shadow-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 sm:h-20 sm:w-48';
    @endphp

    <section aria-labelledby="sponsors-title" class="pb-4 pt-12 sm:pt-4">
        <div class="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <span aria-hidden="true" class="h-px flex-1 bg-gradient-to-r from-transparent to-line-strong"></span>
                <h2 id="sponsors-title" class="eyebrow shrink-0 text-muted">{{ __('Sponsored by') }}</h2>
                <span aria-hidden="true" class="h-px flex-1 bg-gradient-to-l from-transparent to-line-strong"></span>
            </div>

            @if ($sponsors->isEmpty())
                <div class="mx-auto mt-7 flex max-w-3xl flex-col items-center gap-4 rounded-4xl border-[1.5px] border-dashed border-line-strong bg-surface/70 px-6 py-6 text-center sm:flex-row sm:px-7 sm:text-left">
                    <span class="sticker flex size-12 shrink-0 items-center justify-center rounded-2xl border-[1.5px] border-ink bg-accent text-ink">
                        <x-icon name="building" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-display text-[17px] font-semibold text-ink">{{ __('Your brand could be the first one here') }}</p>
                        <p class="mt-0.5 text-[13.5px] leading-relaxed text-muted">{{ __('Sponsor Sofor and be seen by travellers while they plan their next trip.') }}</p>
                    </div>
                    <x-btn :href="route('sponsors')" variant="outline" size="sm" icon-after="arrow-right" class="shrink-0 sm:ml-auto">{{ __('Become a sponsor') }}</x-btn>
                </div>
            @elseif (! $scrollSponsors)
                <ul class="mt-7 flex flex-wrap items-center justify-center gap-3 sm:gap-4">
                    @foreach ($sponsors as $sponsor)
                        <li>
                            <a href="{{ $sponsor->website ?: route('sponsors') }}" @if ($sponsor->website) target="_blank" rel="sponsored noopener" @endif
                               class="{{ $sponsorTile }}" title="{{ $sponsor->name }}">
                                <img src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }}" decoding="async" class="max-h-9 max-w-full object-contain sm:max-h-10">
                            </a>
                        </li>
                    @endforeach
                    <li>
                        <a href="{{ route('sponsors') }}"
                           class="flex h-[4.5rem] w-40 flex-col items-center justify-center gap-0.5 rounded-2xl border-[1.5px] border-dashed border-line-strong text-center transition hover:border-brand/50 hover:bg-brand-tint/40 sm:h-20 sm:w-48">
                            <span class="text-[13px] font-semibold text-ink">{{ __('Your brand here') }}</span>
                            <span class="inline-flex items-center gap-1 text-[11.5px] font-medium text-brand">{{ __('Become a sponsor') }} <x-icon name="arrow-right" class="size-3" /></span>
                        </a>
                    </li>
                </ul>
            @endif
        </div>

        @if ($scrollSponsors)
            <div class="mx-auto mt-7 w-full max-w-6xl sm:px-6 lg:px-8">
                <div class="sponsor-marquee py-1" style="--sponsor-duration: {{ $sponsorSet->count() * 3.2 }}s">
                    <div class="sponsor-track">
                        @foreach ([false, true] as $copy)
                            <ul class="sponsor-set" @if ($copy) aria-hidden="true" @endif>
                                @foreach ($sponsorSet as $sponsor)
                                    <li>
                                        <a href="{{ $sponsor->website ?: route('sponsors') }}" @if ($sponsor->website) target="_blank" rel="sponsored noopener" @endif
                                           @if ($copy || $loop->index >= $sponsors->count()) tabindex="-1" @endif
                                           class="{{ $sponsorTile }}" title="{{ $sponsor->name }}">
                                            <img src="{{ $sponsor->logoUrl() }}" alt="{{ $copy || $loop->index >= $sponsors->count() ? '' : $sponsor->name }}"
                                                 decoding="async" class="max-h-9 max-w-full object-contain sm:max-h-10">
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                    </div>
                </div>
            </div>

            <p class="mt-5 text-center text-[13px] text-muted">
                <a href="{{ route('sponsors') }}" class="inline-flex items-center gap-1 font-semibold text-ink underline decoration-line-strong decoration-2 underline-offset-4 transition hover:text-brand hover:decoration-brand">
                    {{ __('Meet our sponsors') }} <x-icon name="arrow-right" class="size-3.5" />
                </a>
                <span class="mx-2 text-faint" aria-hidden="true">&middot;</span>
                <a href="{{ route('sponsors') }}#contact" class="font-semibold text-brand hover:underline">{{ __('Become one') }}</a>
            </p>
        @endif
    </section>

    {{-- ==================================================================
         FEATURES. The first two are moments every group trip has (one
         made-up trip: Rifat pays for everything, then who owes him), then
         the Explorers board and the invite link.
         ================================================================== --}}
    @php
        [$payer, $others] = [__('Rifat'), [__('Nadia'), __('Tanvir'), __('Sakib')]];
        $tripCosts = [['meal', __('Restaurant bill'), 3200], ['stay', __('Hotel'), 8400], ['transport', __('Car hire'), 4400]];
        $tripTotal = array_sum(array_column($tripCosts, 2));
        $tripShare = $tripTotal / (count($others) + 1);
        $payerBack = $tripTotal - $tripShare;
    @endphp
    <section id="features" class="mx-auto w-full max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
        <div data-reveal class="mx-auto max-w-2xl text-center">
            <p class="eyebrow text-brand">{{ __('Everything in one place') }}</p>
            <h2 class="mt-3 text-balance font-display text-[2rem] font-semibold leading-[1.08] tracking-tight text-ink sm:text-[2.75rem]">
                {{ __('The money, the split, and the stories worth telling.') }}
            </h2>
        </div>

        <div class="mt-14 grid gap-4 md:grid-cols-2 lg:grid-cols-6">
            {{-- 1: one friend paid for everything --}}
            <article data-reveal class="grid gap-6 rounded-5xl border-[1.5px] border-line bg-surface p-6 shadow-soft sm:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] sm:p-7 md:col-span-2 lg:col-span-4">
                <div class="flex flex-col sm:pt-1">
                    <h3 class="text-balance font-display text-[22px] font-semibold leading-tight text-ink sm:text-[24px]">{{ __('One person paid for everything? No problem.') }}</h3>
                    <p class="mt-3 text-pretty text-[14.5px] leading-relaxed text-muted">{{ __('Restaurant bills, the hotel, the car - whoever pays first, add it to the trip. Sofor keeps track of the rest.') }}</p>
                </div>

                <div aria-hidden="true" class="rounded-4xl border-[1.5px] border-line bg-canvas p-5">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="min-w-0 text-[13px] font-semibold leading-snug text-ink">{{ __("Cox's Bazar weekend") }}</p>
                        <span class="shrink-0 text-[11.5px] text-muted">{{ trans_choice(':count traveller|:count travellers', count($others) + 1) }}</span>
                    </div>

                    <ul class="mt-4 flex flex-col gap-3">
                        @foreach ($tripCosts as [$glyph, $label, $amount])
                            <li class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-surface shadow-soft"><x-glyph :name="$glyph" class="size-6" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-semibold leading-snug text-ink">{{ $label }}</span>
                                    <span class="block truncate text-[11.5px] text-muted">{{ __(':name paid', ['name' => $payer]) }}</span>
                                </span>
                                <span class="num shrink-0 font-display text-[15px] font-semibold text-ink">{{ Money::format($amount) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="perf" data-on="surface"></div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-[12px] text-muted">{{ __('Each share') }} <span class="num font-semibold text-ink">{{ Money::format($tripShare) }}</span></span>
                        <span class="rounded-full bg-positive-tint px-3 py-1.5 text-[12px] font-semibold text-positive">
                            {{ __(':name gets back :amount', ['name' => $payer, 'amount' => Money::format($payerBack)]) }}
                        </span>
                    </div>
                </div>
            </article>

            {{-- 2: who owes whom, without the calculator --}}
            <article data-reveal style="--reveal-delay: 80ms" class="flex flex-col rounded-5xl border-[1.5px] border-line bg-surface p-6 shadow-soft sm:p-7 lg:col-span-2">
                <h3 class="text-balance font-display text-[20px] font-semibold leading-tight text-ink">{{ __('Who owes whom? See it at a glance.') }}</h3>
                <p class="mt-2.5 text-pretty text-[13.5px] leading-relaxed text-muted">{{ __('No calculator, no notes, no digging through the Messenger chat.') }}</p>

                <div aria-hidden="true" class="mt-auto pt-6">
                    <p class="flex flex-wrap gap-1.5">
                        @foreach ([__('Calculator'), __('Notes'), 'Messenger'] as $oldWay)
                            <span class="rounded-full border border-dashed border-line-strong px-2.5 py-0.5 text-[11px] font-medium text-faint line-through decoration-negative/70">{{ $oldWay }}</span>
                        @endforeach
                    </p>

                    <ul class="mt-3 flex flex-col gap-1.5 rounded-3xl bg-surface-2 p-1.5">
                        @foreach ($others as $name)
                            <li class="flex items-center gap-2 rounded-2xl bg-surface px-2.5 py-2">
                                <x-avatar :name="$name" size="xs" :ring="false" class="size-6! rounded-full! text-[9px]!" />
                                <span class="flex min-w-0 flex-1 items-center gap-1.5 text-[12.5px] font-semibold text-ink">
                                    <span class="truncate">{{ $name }}</span>
                                    <x-icon name="arrow-right" class="size-3.5 shrink-0 text-faint" />
                                    <span class="truncate">{{ $payer }}</span>
                                </span>
                                <span class="num shrink-0 text-[13px] font-semibold text-ink">{{ Money::format($tripShare) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </article>

            {{-- Explorers - a night sky where Bangladesh is a constellation of its districts --}}
            @php
                [$skyWidth, $skyHeight] = $explorers['sky'];
                $leaders = collect($explorers['top'])->take(3)->values();
                $leaderMedals = [1 => '#f2a71b', 2 => '#9aa7b4', 3 => '#c9773f'];
                $brightest = collect($explorers['stars'])->sortByDesc(fn ($star) => $star[2])->take(3)->keys()->all();
            @endphp
            <article data-reveal class="relative isolate flex flex-col overflow-hidden rounded-5xl border-[1.5px] border-ink bg-ink p-6 text-canvas shadow-soft sm:p-7 lg:col-span-3">
                <div aria-hidden="true" class="absolute inset-0 -z-10 opacity-[0.06]"
                     style="background-image: radial-gradient(circle, #fbf8f2 1px, transparent 1.3px); background-size: 18px 18px;"></div>
                <div aria-hidden="true" class="absolute -right-24 -top-24 -z-10 size-72 rounded-full opacity-50 blur-3xl"
                     style="background: radial-gradient(circle, #0f6f6a, transparent 65%)"></div>

                <div class="grid grid-cols-[minmax(0,1fr)_6.5rem] gap-5 sm:grid-cols-[minmax(0,1fr)_8.5rem]">
                    <div class="min-w-0">
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-accent/15 text-accent"><x-icon name="flag" class="size-5" /></span>
                        <h3 class="mt-5 font-display text-[20px] font-semibold leading-tight">{{ __('Climb the Explorers board') }}</h3>
                        <p class="mt-2 text-[13.5px] leading-relaxed text-canvas/65">{{ __('Public profiles are ranked by how much of Bangladesh they have seen. Where would you land?') }}</p>
                    </div>

                    {{-- Every district is a star in its real place; the more explorers, the brighter it burns. --}}
                    <figure class="min-w-0 self-start">
                        <svg viewBox="0 0 {{ $skyWidth }} {{ $skyHeight }}" class="h-auto w-full" role="img"
                             aria-label="{{ __(':covered of :total districts lit up', ['covered' => $explorers['covered'], 'total' => $explorers['total']]) }}">
                            <defs>
                                <radialGradient id="star-glow">
                                    <stop offset="0" stop-color="#f6b23a" stop-opacity="0.55" />
                                    <stop offset="1" stop-color="#f6b23a" stop-opacity="0" />
                                </radialGradient>
                            </defs>
                            @foreach ($explorers['stars'] as $index => [$x, $y, $glow])
                                @if ($glow > 0)
                                    <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ round(22 + 30 * $glow) }}" fill="url(#star-glow)" />
                                    <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ round(6 + 6 * $glow) }}" fill="#f6b23a" fill-opacity="{{ 0.45 + 0.55 * $glow }}"
                                            @if (in_array($index, $brightest, true)) class="star-twinkle" style="animation-delay: {{ $loop->index % 3 * 0.9 }}s" @endif />
                                @else
                                    <circle cx="{{ $x }}" cy="{{ $y }}" r="6.5" fill="#fbf8f2" fill-opacity="0.34" />
                                @endif
                            @endforeach
                        </svg>
                        <figcaption class="mt-1.5 text-center text-[10.5px] leading-snug text-canvas/55">
                            {{ __(':covered of :total districts lit up', ['covered' => $explorers['covered'], 'total' => $explorers['total']]) }}
                        </figcaption>
                    </figure>
                </div>

                {{-- The top three, with an open seat for every place nobody holds yet --}}
                <ol class="mt-5 flex flex-col gap-2" aria-label="{{ __('Top explorers right now') }}">
                    @foreach ([1, 2, 3] as $place)
                        @php $entry = $leaders->get($place - 1); @endphp
                        @if ($entry)
                            @php $entryLevel = \App\Support\ExplorerLevel::for($entry['visited'], $explorers['total']); @endphp
                            <li>
                                <a href="{{ $entry['user']->publicUrl() }}" class="flex items-center gap-3 rounded-2xl border border-canvas/10 bg-canvas/[0.05] px-3 py-2.5 transition hover:border-canvas/25 hover:bg-canvas/10">
                                    <span class="num flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-ink" style="background: {{ $leaderMedals[$place] }}">{{ $place }}</span>
                                    <x-avatar :name="$entry['user']->name" :gradient="$entry['user']->avatarGradient()" :src="$entry['user']->avatarUrl()" size="sm" :ring="false" class="rounded-full!" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[13.5px] font-semibold">{{ $entry['user']->name }}</span>
                                        <span class="flex min-w-0 items-center gap-1 text-[11px] text-canvas/55">
                                            <x-glyph :name="$entryLevel['icon']" class="size-3.5 shrink-0" />
                                            <span class="truncate">{{ $entryLevel['label'] }}</span>
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-right leading-none">
                                        <span @class(['num block font-display text-[1.2rem] font-semibold', 'text-accent' => $place === 1])>{{ $entry['visited'] }}</span>
                                        <span class="mt-1 block text-[10px] text-canvas/50">{{ __('districts') }}</span>
                                    </span>
                                </a>
                            </li>
                        @elseif ($place === $leaders->count() + 1)
                            {{-- The first open seat is the invitation... --}}
                            <li class="flex items-center gap-3 rounded-2xl border border-dashed border-accent/50 bg-accent/[0.06] px-3 py-2.5">
                                <span class="num flex size-6 shrink-0 items-center justify-center rounded-full border border-accent/60 text-[11px] font-bold text-accent">{{ $place }}</span>
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full border border-dashed border-accent/60 text-[13px] font-semibold text-accent">?</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[13.5px] font-semibold">{{ __('Your spot is open') }}</span>
                                    <span class="block truncate text-[11px] text-canvas/55">{{ __('Mark your districts to take it') }}</span>
                                </span>
                            </li>
                        @else
                            {{-- ...the rest wait quietly. --}}
                            <li class="flex items-center gap-3 rounded-2xl border border-dashed border-canvas/15 px-3 py-2">
                                <span class="num flex size-6 shrink-0 items-center justify-center rounded-full border border-canvas/25 text-[11px] font-bold text-canvas/45">{{ $place }}</span>
                                <span class="text-[12px] text-canvas/45">{{ __('Still open') }}</span>
                            </li>
                        @endif
                    @endforeach
                </ol>

                <div class="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2 pt-5">
                    <span class="flex items-center gap-2 text-[12px] text-canvas/60">
                        <x-icon name="users" class="size-4 text-accent" />
                        {{ $explorers['count'] > 0 ? trans_choice(':count explorer|:count explorers', $explorers['count']) : __('Be the first explorer') }}
                    </span>
                    <a href="{{ route('explorers') }}" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-accent hover:underline">
                        {{ __('See the full ranking') }} <x-icon name="arrow-right" class="size-3.5" />
                    </a>
                </div>
            </article>

            {{-- Invite --}}
            <article data-reveal style="--reveal-delay: 80ms" class="flex flex-col rounded-5xl border-[1.5px] border-line bg-surface p-6 shadow-soft sm:p-7 md:col-span-2 lg:col-span-3">
                <span class="flex size-11 items-center justify-center rounded-2xl bg-[#ede7fc] text-[#7c5cf0]"><x-icon name="link" class="size-5" /></span>
                <h3 class="mt-5 font-display text-[20px] font-semibold leading-tight text-ink">{{ __('One link brings everyone in') }}</h3>
                <p class="mt-2 text-[13.5px] leading-relaxed text-muted">{{ __('Drop the invite in your group chat. Friends join free from any phone browser, with no app and no card.') }}</p>

                <div aria-hidden="true" class="mt-auto flex flex-col gap-2 pt-6">
                    <p class="max-w-[85%] self-start rounded-2xl rounded-bl-md bg-surface-2 px-3.5 py-2 text-[12.5px] text-ink">
                        {{ __('Who is in for Sylhet next weekend?') }} <x-glyph name="tea" class="inline-block size-4 align-[-3px]" />
                    </p>
                    <div class="max-w-[85%] self-end rounded-2xl rounded-br-md bg-brand p-1.5 text-white">
                        <div class="flex items-center gap-2.5 rounded-xl bg-white/12 px-2.5 py-2">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white"><x-glyph name="tea" class="size-6" /></span>
                            <span class="min-w-0">
                                <span class="block truncate text-[12px] font-semibold">{{ __('Join "Sylhet tea tour" on Sofor') }}</span>
                                <span class="block truncate font-mono text-[10px] text-white/70">sofor.io/join/K7Q2RX</span>
                            </span>
                        </div>
                        <p class="px-2 pb-0.5 pt-1.5 text-[12px]">{{ __('Costs go in here, we split it after') }}</p>
                    </div>
                    <p class="self-end pr-1 text-[10px] text-faint">{{ __('Seen by 3') }}</p>
                </div>
            </article>
        </div>
    </section>

    {{-- ==================================================================
         HOW A TRIP GOES (dark band, a route with four stops)
         ================================================================== --}}
    <section id="how" class="relative isolate scroll-mt-16 overflow-hidden bg-ink text-canvas">
        <div aria-hidden="true" class="absolute inset-0 -z-10 opacity-[0.12]"
             style="background-image: radial-gradient(circle, #fbf8f2 1px, transparent 1.3px); background-size: 22px 22px;"></div>
        <div aria-hidden="true" class="absolute -right-32 top-0 -z-10 size-[30rem] rounded-full opacity-30 blur-3xl"
             style="background: radial-gradient(circle, var(--brand), transparent 65%)"></div>

        <div class="mx-auto w-full max-w-6xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <div data-reveal class="max-w-2xl">
                <p class="eyebrow text-accent">{{ __('How a trip goes') }}</p>
                <h2 class="mt-3 text-balance font-display text-[2rem] font-semibold leading-[1.08] tracking-tight sm:text-[2.75rem]">
                    {{ __('Start to finish, the money is this simple.') }}
                </h2>
            </div>

            <ol class="relative mt-14 grid gap-10 lg:grid-cols-4 lg:gap-6">
                <span aria-hidden="true" class="absolute bottom-6 left-7 top-6 border-l-2 border-dashed border-canvas/20 lg:bottom-auto lg:left-[12%] lg:right-[12%] lg:top-7 lg:border-l-0 lg:border-t-2"></span>

                @foreach ([
                    [__('Open a trip'), __('Set up one trip with everyone in it.'), __('Sundarbans boat trip'), 'boat'],
                    [__('Add the costs'), __('Whenever someone pays, add it right then.'), __('Dinner :amount - 4 ways', ['amount' => Money::format(3200)]), 'meal'],
                    [__('Everyone sees their share'), __('Who paid what and who still owes - all clear.'), __('You get back :amount', ['amount' => Money::format(1250)]), 'settled'],
                    [__("That's it, keep travelling."), __('No more 50 messages about money in the group chat.'), __('Group chat: photos only'), 'chat'],
                ] as $index => [$title, $text, $chip, $chipIcon])
                    <li data-reveal style="--reveal-delay: {{ $index * 90 }}ms" class="relative flex gap-5 lg:flex-col lg:items-center lg:text-center">
                        <span class="num relative z-10 flex size-14 shrink-0 items-center justify-center rounded-2xl border-2 border-ink bg-accent font-display text-[22px] font-semibold text-ink shadow-[4px_4px_0_0_rgba(251,248,242,0.18)]">
                            {{ $index + 1 }}
                        </span>
                        <div class="min-w-0 pt-1 lg:pt-0">
                            <h3 class="font-display text-[20px] font-semibold">{{ $title }}</h3>
                            <p class="mt-1.5 text-pretty text-[14px] leading-relaxed text-canvas/70">{{ $text }}</p>
                            <span class="mt-3.5 inline-flex max-w-full items-center gap-2 rounded-full border border-canvas/15 bg-canvas/[0.06] py-1 pl-1 pr-3 text-[12px] text-canvas/85">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-canvas"><x-glyph :name="$chipIcon" class="size-4.5" /></span>
                                <span class="truncate">{{ $chip }}</span>
                            </span>
                        </div>
                    </li>
                @endforeach
            </ol>

            <div data-reveal class="mt-14 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <x-btn :href="$primaryHref" variant="accent" size="lg" icon-after="arrow-right">{{ $primaryLabel }}</x-btn>
                <span class="text-[13px] text-canvas/60">{{ __('No card. No app. About a minute.') }}</span>
            </div>
        </div>
    </section>

    {{-- ==================================================================
         FREE (the old pricing section: there is nothing to buy)
         ================================================================== --}}
    <section id="free" class="mx-auto w-full max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
        <div data-reveal class="mx-auto max-w-2xl text-center">
            <p class="eyebrow text-brand">{{ __('Pricing') }}</p>
            <h2 class="mt-3 font-display text-[2rem] font-semibold leading-[1.08] tracking-tight text-ink sm:text-[2.75rem]">{{ __('Free. All of it, for everyone.') }}</h2>
            <p class="mt-4 text-[15.5px] leading-relaxed text-muted">{{ __('No trip limit, no premium tier, no ads. Every feature is yours, from the first trip to the hundredth.') }}</p>
        </div>

        <div class="mx-auto mt-12 grid max-w-5xl gap-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <div data-reveal class="relative flex flex-col rounded-5xl border-2 border-ink bg-surface p-7 shadow-sticker sm:p-9">
                <span class="absolute -top-3.5 right-7 rounded-full border-2 border-ink bg-accent px-3 py-1 font-mono text-[10px] font-bold uppercase tracking-[0.1em] text-ink">{{ __('For everyone') }}</span>
                <p class="font-display text-[19px] font-semibold text-ink">{{ __('Sofor') }}</p>
                <p class="mt-3 font-display text-[3.2rem] font-semibold leading-none tracking-tight text-ink">
                    {{ Money::format(0) }}<span class="text-[16px] font-medium text-muted"> {{ __('forever') }}</span>
                </p>
                <ul class="mt-7 grid gap-3 text-[14px] text-ink sm:grid-cols-2">
                    @foreach ([__('Unlimited trips'), __('Unlimited travellers'), __('Live split and settle up'), __('Budgets and cost estimates'), __('Itemised bills and receipt photos'), __('Advance collections'), __('Polls, RSVPs and check-ins'), __('Checkpoint list'), __('Works offline'), __('English and বাংলা'), __('PDF trip summaries'), __('Travel map and Explorers')] as $item)
                        <li class="flex items-start gap-2.5"><x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-positive" /> {{ $item }}</li>
                    @endforeach
                </ul>
                <div class="mt-auto pt-8"><x-btn :href="$primaryHref" block icon-after="arrow-right">{{ $primaryLabel }}</x-btn></div>
            </div>

            <div data-reveal style="--reveal-delay: 90ms" class="flex flex-col rounded-5xl border-[1.5px] border-line bg-surface-2 p-7 sm:p-8">
                <p class="font-display text-[19px] font-semibold text-ink">{{ __('How it stays free') }}</p>
                <p class="mt-2 text-[14px] leading-relaxed text-muted">{{ __('No adverts, and your data is never sold. Instead:') }}</p>
                <ul class="mt-6 flex flex-col gap-5">
                    @foreach ([
                        ['building', __('Sponsors'), __('Travel brands put their logo on Sofor.'), route('sponsors'), __('Meet our sponsors')],
                        ['gift', __('Supporters'), __('Travellers support Sofor with any amount they like through bKash.'), route('support.index'), __('Support Sofor')],
                    ] as [$icon, $title, $text, $href, $cta])
                        <li class="flex items-start gap-3.5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-surface text-brand shadow-soft"><x-icon :name="$icon" class="size-5" /></span>
                            <div class="min-w-0">
                                <p class="text-[14.5px] font-semibold text-ink">{{ $title }}</p>
                                <p class="mt-0.5 text-[13.5px] leading-relaxed text-muted">{{ $text }}</p>
                                <a href="{{ $href }}" class="mt-1 inline-flex items-center gap-1 text-[13px] font-semibold text-brand hover:underline">
                                    {{ $cta }} <x-icon name="arrow-right" class="size-3.5" />
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <ul data-reveal class="mx-auto mt-10 flex max-w-4xl flex-wrap items-center justify-center gap-x-7 gap-y-3 text-[13px] text-muted">
            <li class="flex items-center gap-2"><x-icon name="users" class="size-4 text-brand" /> {{ __('Friends join free') }}</li>
            <li class="flex items-center gap-2"><x-icon name="shield" class="size-4 text-brand" /> {{ __('Sofor never holds your money') }}</li>
            <li class="flex items-center gap-2"><x-icon name="ban" class="size-4 text-brand" /> {{ __('No ads, ever') }}</li>
        </ul>
    </section>

    {{-- ==================================================================
         FAQ: question cards in two columns that stack on their own, so an
         open answer never leaves a gap beside it. All closed to start, one
         open at a time; closed answers stay in the page (for search
         engines) but inert.
         ================================================================== --}}
    @php
        // An icon and a tint for each question, in the order of $faqs.
        $faqLooks = [
            ['gift', 'bg-accent-tint text-accent-2'],
            ['mobile', 'bg-brand-tint text-brand'],
            ['shield', 'bg-positive-tint text-positive'],
            ['refresh', 'bg-[#ede7fc] text-[#7c5cf0]'],
            ['card', 'bg-accent-tint text-accent-2'],
            ['percent', 'bg-brand-tint text-brand'],
            ['globe', 'bg-positive-tint text-positive'],
            ['lock', 'bg-[#ede7fc] text-[#7c5cf0]'],
        ];
    @endphp
    <section id="faq" class="mx-auto w-full max-w-6xl scroll-mt-20 px-4 pb-16 pt-4 sm:px-6 sm:pb-20 sm:pt-6 lg:px-8">
        <div data-reveal class="mx-auto max-w-2xl text-center">
            <p class="eyebrow text-brand">{{ __('Questions') }}</p>
            <h2 class="mt-3 text-balance font-display text-[2rem] font-semibold leading-[1.08] tracking-tight text-ink sm:text-[2.6rem]">{{ __('Your questions, our answers') }}</h2>
        </div>

        <div data-reveal x-data="{ open: null }" class="mt-10 grid gap-3 lg:grid-cols-2 lg:gap-4">
            @foreach (array_chunk($faqs, (int) ceil(count($faqs) / 2), true) as $column)
                <div class="flex flex-col gap-3 lg:gap-4">
                    @foreach ($column as $index => [$question, $answer])
                        @php [$icon, $tint] = $faqLooks[$index] ?? ['info', 'bg-surface-2 text-muted']; @endphp
                        <div class="faq-card rounded-4xl border-[1.5px] border-line bg-surface transition duration-200"
                             :class="{ 'is-open': open === {{ $index }} }">
                            <h3>
                                <button type="button" @click="open = open === {{ $index }} ? null : {{ $index }}"
                                        :aria-expanded="(open === {{ $index }}).toString()" aria-controls="faq-{{ $index }}"
                                        aria-expanded="false"
                                        class="flex w-full items-center gap-3.5 rounded-4xl p-3.5 text-left sm:gap-4 sm:p-4">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl {{ $tint }} sm:size-11">
                                        <x-icon :name="$icon" class="size-5" />
                                    </span>
                                    <span class="min-w-0 flex-1 font-display text-[15.5px] font-semibold leading-snug text-ink sm:text-[16px]">{{ $question }}</span>
                                    <span class="faq-toggle flex size-8 shrink-0 items-center justify-center rounded-full border-[1.5px] border-line-strong text-muted transition duration-200">
                                        <x-icon name="plus" class="size-4" />
                                    </span>
                                </button>
                            </h3>
                            <div id="faq-{{ $index }}" class="faq-answer" x-effect="$el.inert = open !== {{ $index }}" inert>
                                <div class="overflow-hidden">
                                    <p class="pb-5 pl-[4.25rem] pr-5 text-[14px] leading-relaxed text-muted sm:pb-6 sm:pl-[4.75rem] sm:pr-14 sm:text-[14.5px]">{{ $answer }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>


    {{-- Phones: keep the way in within reach once the hero has scrolled away. --}}
    @guest
        <div x-data="{ show: false }" @scroll.window.throttle.100ms="show = window.scrollY > 640"
             x-show="show" x-cloak x-transition.opacity
             class="fixed inset-x-3 bottom-3 z-40 sm:hidden safe-bottom">
            <x-btn :href="route('register')" block icon-after="arrow-right" class="shadow-lift">{{ __('Start a trip - it is free') }}</x-btn>
        </div>
    @endguest
</x-layouts.site>
