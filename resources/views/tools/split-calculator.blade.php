@php
    use App\Support\Schema;

    /**
     * The free trip split calculator: who paid what in, each share and who pays
     * whom out (resources/js/split-calculator.js). Nothing leaves the browser.
     * It answers the searches people make before a trip ends - "trip split
     * calculator", "who owes who", "জনপ্রতি খরচ" - and leads to a real trip.
     */
    $user = auth()->user();
    [$startHref, $startLabel] = match (true) {
        $user?->isAdmin() === true => [route('admin.dashboard'), __('Open the console')],
        $user !== null => [route('dashboard'), __('Open my trips')],
        default => [route('register'), __('Start a trip - it is free')],
    };

    $faqs = [
        [__('How is the per-person cost worked out?'), __('Everything everyone paid is added up and divided equally by the number of people. Whoever paid more than their share gets the difference back; whoever paid less pays it.')],
        [__('Why are there so few payments?'), __('Instead of everyone paying everyone, the calculator matches the biggest debts with the biggest credits. A group of six never needs more than five payments.')],
        [__('Is it free? Do I need an account?'), __('Free, with no account. What you type stays in this browser and is never sent to us.')],
        [__('What if not everyone shared every cost?'), __('This calculator splits everything equally. For costs only some people shared - a separate room, a meal some skipped - make a free Sofor trip: each cost can be split between just the people it was for.')],
        [__('Can friends pay me back over bKash?'), __('Yes. Copy the result into the group chat with each person\'s bKash number. In a Sofor trip, everyone\'s bKash, Nagad or bank details sit right next to what they are owed.')],
    ];

    $description = __('Free trip split calculator: enter who paid what on a group trip and see the per-person share and who owes who, in the fewest bKash payments. No sign-up.');

    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            Schema::freeTool(__('Trip split calculator'), route('split-calculator'), $description, 'FinanceApplication'),
            Schema::faq($faqs),
        ],
    ];

    $labels = [
        'person' => __('Traveller :n'),
        'heading' => __('Trip split (Sofor)'),
        'totals' => __('Total :total · Each :share'),
        'even' => __('Everyone is even. Nobody owes anything.'),
        'link' => route('split-calculator'),
        'copied' => __('Copied. Paste it in the group chat.'),
    ];
@endphp

<x-layouts.site :title="__('Trip split calculator: who owes who')" :wide="true" :card="false" :schema="$schema" :description="$description">

    <div class="mx-auto w-full max-w-6xl px-4 pb-6 pt-8 sm:px-6 sm:pt-10 lg:px-8"
         x-data="splitCalculator({{ \Illuminate\Support\Js::from(['labels' => $labels]) }})">

        {{-- ===================== What this is ===================== --}}
        <header class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div class="min-w-0">
                <p class="eyebrow text-brand">{{ __('Free calculator') }}</p>
                <h1 class="mt-3 text-balance font-display text-[2.3rem] font-semibold leading-[1.05] tracking-tight sm:text-[3.2rem]">{{ __('Trip split calculator') }}</h1>
                <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-muted">
                    {{ __('Write down who paid what on the trip. Sofor works out each person\'s share and exactly who pays whom - in the fewest payments.') }}
                </p>
            </div>
            <ul class="flex flex-wrap gap-2 text-[12px] font-semibold text-ink lg:max-w-xs lg:justify-end">
                <li class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5"><x-icon name="sparkles" class="size-3.5 text-accent-2" /> {{ __('No sign-up') }}</li>
                <li class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5"><x-icon name="shield" class="size-3.5 text-brand" /> {{ __('Stays in this browser') }}</li>
                <li class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5"><x-icon name="check" class="size-3.5 text-positive" /> {{ __('Bangla digits work too') }}</li>
            </ul>
        </header>

        <div class="mt-8 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:items-start">
            {{-- ===================== Who paid what ===================== --}}
            <x-card padding="p-4 sm:p-6" class="min-w-0">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold text-ink">{{ __('Who paid what') }}</h2>
                    <button type="button" @click="reset()" class="text-[12.5px] font-semibold text-muted transition hover:text-ink">{{ __('Start over') }}</button>
                </div>

                <div class="mt-4 hidden grid-cols-[minmax(0,1fr)_9rem_2.5rem] gap-2 px-1 text-[11.5px] font-semibold uppercase tracking-wide text-faint sm:grid">
                    <span>{{ __('Name') }}</span>
                    <span>{{ __('Paid (৳)') }}</span>
                    <span class="sr-only">{{ __('Remove') }}</span>
                </div>

                <ul class="mt-2 flex flex-col gap-2.5 sm:mt-1.5">
                    <template x-for="(person, index) in people" :key="index">
                        <li class="grid grid-cols-[minmax(0,1fr)_7.5rem_2.5rem] items-center gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_2.5rem]">
                            <label class="sr-only" :for="`split-name-${index}`">{{ __('Name') }}</label>
                            <input type="text" maxlength="40" x-model="person.name" :id="`split-name-${index}`" :data-person="index"
                                   :placeholder="label(index)" class="form-field h-11 text-[14px]" autocomplete="off">
                            <label class="sr-only" :for="`split-paid-${index}`">{{ __('Paid (৳)') }}</label>
                            <input type="text" inputmode="decimal" x-model="person.paid" :id="`split-paid-${index}`"
                                   placeholder="0" class="form-field num h-11 text-right text-[14px]" autocomplete="off">
                            <button type="button" @click="remove(index)" :disabled="people.length <= 2"
                                    class="flex size-10 items-center justify-center rounded-xl text-faint transition hover:bg-surface-2 hover:text-negative disabled:opacity-30"
                                    :aria-label="`{{ __('Remove') }} ${label(index)}`">
                                <x-icon name="close" class="size-4" />
                            </button>
                        </li>
                    </template>
                </ul>

                <button type="button" @click="add()" x-show="people.length < 30"
                        class="mt-3 flex h-11 w-full items-center justify-center gap-2 rounded-2xl border-[1.5px] border-dashed border-line-strong text-[13.5px] font-semibold text-muted transition hover:border-ink hover:text-ink">
                    <x-icon name="user-plus" class="size-4" /> {{ __('Add a person') }}
                </button>

                <p class="mt-4 text-[12.5px] leading-relaxed text-faint">{{ __('Add everyone on the trip, even people who have not paid anything yet - they share the cost too.') }}</p>
            </x-card>

            {{-- ===================== The split ===================== --}}
            <div class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-24">
                <x-card padding="p-4 sm:p-6" class="min-w-0" aria-live="polite">
                    <h2 class="font-display text-lg font-semibold text-ink">{{ __('The split') }}</h2>

                    <div class="mt-4 grid grid-cols-2 gap-2.5">
                        <div class="rounded-2xl bg-surface-2 p-3.5">
                            <p class="text-[11.5px] font-semibold text-faint">{{ __('Total spent') }}</p>
                            <p class="mt-1 font-display text-2xl font-semibold text-ink" x-text="money(result.total)">৳০</p>
                        </div>
                        <div class="rounded-2xl bg-brand-tint p-3.5">
                            <p class="text-[11.5px] font-semibold text-brand">{{ __('Each person\'s share') }}</p>
                            <p class="mt-1 font-display text-2xl font-semibold text-brand" x-text="money(Math.round(result.share))">৳০</p>
                        </div>
                    </div>

                    <h3 class="mt-6 text-[12px] font-bold uppercase tracking-wide text-faint">{{ __('Who pays whom') }}</h3>
                    <template x-if="result.total === 0">
                        <p class="mt-3 text-[13.5px] text-muted">{{ __('Add what each person paid to see who owes who.') }}</p>
                    </template>
                    <template x-if="result.total > 0 && result.payments.length === 0">
                        <p class="mt-3 text-[13.5px] text-muted">{{ __('Everyone is even. Nobody owes anything.') }}</p>
                    </template>
                    <ol class="mt-3 flex flex-col gap-2" x-show="result.payments.length">
                        <template x-for="(payment, index) in result.payments" :key="index">
                            <li class="flex items-center gap-2.5 rounded-2xl border border-line px-3.5 py-3">
                                <span class="min-w-0 flex-1 truncate text-[14px] font-semibold text-ink" x-text="payment.from"></span>
                                <x-icon name="arrow-right" class="size-4 shrink-0 text-brand" />
                                <span class="min-w-0 flex-1 truncate text-[14px] font-semibold text-ink" x-text="payment.to"></span>
                                <span class="shrink-0 font-display text-[15px] font-semibold text-ink" x-text="money(payment.amount)"></span>
                            </li>
                        </template>
                    </ol>

                    <button type="button" @click="copy()" :disabled="result.total === 0"
                            class="mt-5 flex h-11 w-full items-center justify-center gap-2 rounded-full bg-ink text-[13.5px] font-semibold text-canvas transition hover:bg-brand disabled:opacity-40">
                        <x-icon name="copy" class="size-4" />
                        <span x-text="copied ? @js(__('Copied')) : @js(__('Copy for WhatsApp'))">{{ __('Copy for WhatsApp') }}</span>
                    </button>
                </x-card>

                <x-card padding="p-4 sm:p-6" class="min-w-0" x-show="result.total > 0" x-cloak>
                    <h3 class="text-[12px] font-bold uppercase tracking-wide text-faint">{{ __('Each person') }}</h3>
                    <ul class="mt-3 divide-y divide-line">
                        <template x-for="person in result.balances" :key="person.index">
                            <li class="flex items-center justify-between gap-3 py-2.5 text-[13.5px]">
                                <span class="min-w-0 truncate font-semibold text-ink" x-text="person.name"></span>
                                <span class="shrink-0 font-semibold"
                                      :class="person.balance > 0 ? 'text-positive' : (person.balance < 0 ? 'text-negative' : 'text-faint')"
                                      x-text="person.balance > 0 ? @js(__('gets back :amount')).replace(':amount', money(person.balance)) : (person.balance < 0 ? @js(__('pays :amount')).replace(':amount', money(-person.balance)) : @js(__('even')))"></span>
                            </li>
                        </template>
                    </ul>
                </x-card>
            </div>
        </div>

        {{-- ===================== The whole trip ===================== --}}
        <section class="mt-8 flex flex-col items-start gap-5 overflow-hidden rounded-[1.75rem] bg-ink p-6 text-canvas sm:flex-row sm:items-center sm:justify-between sm:p-8">
            <div class="max-w-2xl">
                <h2 class="font-display text-[1.6rem] font-semibold leading-tight">{{ __('Doing this for a whole trip?') }}</h2>
                <p class="mt-2 text-[14.5px] leading-relaxed text-canvas/75">{{ __('In Sofor everyone adds their own costs as they spend, from any phone. Shares, bKash numbers and settling up are done for you.') }}</p>
            </div>
            <x-btn :href="$startHref" variant="accent" icon-after="arrow-right" class="shrink-0">{{ $startLabel }}</x-btn>
        </section>

        {{-- ===================== How it works, and questions ===================== --}}
        <section class="mt-16 grid gap-10 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-16">
            <div>
                <h2 class="font-display text-[1.9rem] font-semibold leading-[1.1] tracking-tight text-ink">{{ __('How to split trip costs fairly') }}</h2>
                <ol class="mt-6 flex flex-col gap-5">
                    @foreach ([
                        [__('Add everyone on the trip'), __('Include people who have not paid anything yet - they share the cost too.')],
                        [__('Write what each person paid'), __('Hotel, bus tickets, food: add up what each person spent from their own pocket.')],
                        [__('Settle up in the fewest payments'), __('Make only the payments on the list, over bKash, Nagad or cash, and the whole group is square.')],
                    ] as $step => [$heading, $text])
                        <li class="flex gap-4">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent font-display text-[15px] font-semibold text-ink">{{ $step + 1 }}</span>
                            <div>
                                <h3 class="font-display text-[16.5px] font-semibold text-ink">{{ $heading }}</h3>
                                <p class="mt-1 text-[14px] leading-relaxed text-muted">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-8 text-[14px] text-muted">
                    {{ __('Back from the trip?') }}
                    <a href="{{ route('map-maker') }}" class="font-semibold text-brand underline decoration-brand/30 underline-offset-2 hover:decoration-brand">{{ __('Make your travel map') }}</a>
                </p>
            </div>

            <div>
                <h2 class="font-display text-[1.9rem] font-semibold leading-[1.1] tracking-tight text-ink">{{ __('Questions about splitting trip costs') }}</h2>
                <x-faq-list :faqs="$faqs" id="split-faq" class="mt-4" />
            </div>
        </section>
    </div>
</x-layouts.site>
