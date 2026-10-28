@php
    $firstName = explode(' ', trim((string) $user->name))[0] ?? 'traveller';
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
    $totals = $portfolio['totals'];
    $tracked = $trips->sum(fn ($trip) => (float) ($trip->expenses_sum_amount ?? 0));
    $net = round($totals['credit'] - $totals['due'], 2);
@endphp

<x-layouts.app title="{{ __('Dashboard') }}" :active="'dashboard'" :heading="$greeting.', '.$firstName"
               :subtitle="__('Here is where your trips and dues stand right now.')">

    <x-slot:actions>
        <x-btn :href="route('trips.create')" size="sm" icon="plus" class="hidden sm:inline-flex">{{ __('New trip') }}</x-btn>
    </x-slot:actions>

    <div class="flex flex-col gap-6">

        {{-- ============ Standing card ============ --}}
        <section class="relative overflow-hidden rounded-5xl border border-line bg-gradient-to-br from-brand/12 via-accent/6 to-transparent p-5 sm:p-7">
            <div class="absolute -right-16 -top-20 size-56 rounded-full bg-brand/20 blur-3xl"></div>

            <div class="relative grid gap-6 lg:grid-cols-[1.15fr_1fr] lg:items-center">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-faint">{{ __('Your standing') }}</p>

                    @if ($net > 0.009)
                        <h2 class="mt-2 font-display text-3xl font-semibold text-ink sm:text-4xl">
                            {{ __('You should receive') }} <span class="text-gradient">{{ \App\Support\Money::format($net) }}</span>
                        </h2>
                        <p class="mt-2 max-w-lg text-sm leading-relaxed text-muted">
                            {{ trans_choice('Travellers owe you across :count trip. Open Balances to see who owes what, or mark it received there in one go.|Travellers owe you across :count trips. Open Balances to see who owes what, or mark it received there in one go.', count($portfolio['trips'])) }}
                        </p>
                    @elseif ($net < -0.009)
                        <h2 class="mt-2 font-display text-3xl font-semibold text-ink sm:text-4xl">
                            {{ __('You owe') }} <span class="text-gradient">{{ \App\Support\Money::format(abs($net)) }}</span>
                        </h2>
                        <p class="mt-2 max-w-lg text-sm leading-relaxed text-muted">
                            {{ __('Settle up and both sides see it instantly. Sofor keeps the receipt either way.') }}
                        </p>
                    @else
                        <h2 class="mt-2 font-display text-3xl font-semibold text-ink sm:text-4xl">
                            {{ __('Everything is') }} <span class="text-gradient">{{ __('settled') }}</span>
                        </h2>
                        <p class="mt-2 max-w-lg text-sm leading-relaxed text-muted">
                            {{ __('No pending dues across your trips. Beautifully boring.') }}
                        </p>
                    @endif

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <x-btn :href="route('trips.create')" icon="plus">{{ __('Start a trip') }}</x-btn>
                        <x-btn :href="route('trips.index')" variant="outline" icon="compass">{{ __('All trips') }}</x-btn>
                        @if (abs($net) > 0.009)
                            <x-btn :href="route('balances.index')" variant="ghost" icon="wallet">{{ __('Per friend') }}</x-btn>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-3xl border border-line bg-surface p-4">
                        <div class="flex items-center gap-2 text-faint">
                            <x-icon name="trending-down" class="size-4 text-negative" />
                            <p class="text-[11px] font-semibold uppercase tracking-wide">{{ __('You owe') }}</p>
                        </div>
                        <p class="mt-2 font-display text-xl font-semibold">{{ \App\Support\Money::format($totals['due']) }}</p>
                    </div>
                    <div class="rounded-3xl border border-line bg-surface p-4">
                        <div class="flex items-center gap-2 text-faint">
                            <x-icon name="trending-up" class="size-4 text-positive" />
                            <p class="text-[11px] font-semibold uppercase tracking-wide">{{ __('Owed to you') }}</p>
                        </div>
                        <p class="mt-2 font-display text-xl font-semibold">{{ \App\Support\Money::format($totals['credit']) }}</p>
                    </div>
                    <div class="rounded-3xl border border-line bg-surface p-4">
                        <div class="flex items-center gap-2 text-faint">
                            <x-icon name="receipt" class="size-4 text-brand" />
                            <p class="text-[11px] font-semibold uppercase tracking-wide">{{ __('Tracked') }}</p>
                        </div>
                        <p class="mt-2 font-display text-xl font-semibold">{{ \App\Support\Money::compact($tracked) }}</p>
                    </div>
                    <div class="rounded-3xl border border-line bg-surface p-4">
                        <div class="flex items-center gap-2 text-faint">
                            <x-icon name="compass" class="size-4 text-accent" />
                            <p class="text-[11px] font-semibold uppercase tracking-wide">{{ __('Live trips') }}</p>
                        </div>
                        <p class="mt-2 font-display text-xl font-semibold">{{ $activeTrips->count() }}</p>
                    </div>

                    @if ($totals['settled_out'] > 0)
                        <div class="col-span-2 rounded-3xl border border-line bg-surface p-4">
                            <div class="flex items-center gap-2 text-faint">
                                <x-icon name="wallet" class="size-4 text-brand" />
                                <p class="text-[11px] font-semibold uppercase tracking-wide">{{ __('You have paid in') }}</p>
                            </div>
                            <p class="mt-2 font-display text-xl font-semibold">{{ \App\Support\Money::format($totals['settled_out']) }}</p>
                            <p class="mt-1 text-[11px] text-faint">{{ __('Contributions recorded across your trips') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[1.6fr_1fr] lg:items-start">
            <div class="flex flex-col gap-6">
                {{-- ============ Active trips ============ --}}
                <section>
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-display text-lg font-semibold">{{ __('Live trips') }}</h2>
                        <a href="{{ route('trips.index') }}" class="flex items-center gap-1 text-xs font-medium text-brand hover:underline">
                            {{ __('View all') }} <x-icon name="chevron-right" class="size-3.5" />
                        </a>
                    </div>

                    @if ($activeTrips->isEmpty())
                        <x-empty icon="compass" title="{{ __('No live trips yet') }}"
                                 :text="__('Create your first trip, add the travellers and start logging costs. Sofor splits everything automatically.')">
                            <x-btn :href="route('trips.create')" size="sm" icon="plus">{{ __('Start a trip') }}</x-btn>
                        </x-empty>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($activeTrips->take(4) as $trip)
                                <x-trip-card :trip="$trip" :balance="$portfolio['trips'][$trip->id] ?? null" />
                            @endforeach
                        </div>
                    @endif
                </section>

                {{-- ============ Ended trips ============ --}}
                @if ($trips->where('status', \App\Enums\TripStatus::Ended)->isNotEmpty())
                    <section>
                        <h2 class="mb-3 font-display text-lg font-semibold">{{ __('Recently ended') }}</h2>
                        <div class="flex flex-col gap-3">
                            @foreach ($trips->where('status', \App\Enums\TripStatus::Ended)->take(3) as $trip)
                                <a href="{{ route('trips.show', $trip) }}"
                                   class="flex items-center gap-4 rounded-3xl border border-line bg-surface p-3.5 transition hover:border-brand/35">
                                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl" style="background-image: {{ $trip->coverStyle() }}"><x-glyph :name="$trip->icon" class="size-8" /></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold">{{ $trip->title }}</p>
                                        <p class="text-[11px] text-faint">
                                            {{ __('Ended :time - :amount total', ['time' => $trip->ended_at?->diffForHumans(), 'amount' => \App\Support\Money::format($trip->expenses_sum_amount ?? 0, $trip->currency)]) }}
                                        </p>
                                    </div>
                                    <x-badge tone="slate" icon="flag">{{ __('Ended') }}</x-badge>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            {{-- ============ Side column ============ --}}
            <div class="flex flex-col gap-6">
                @if ($upcoming->isNotEmpty())
                    <x-card>
                        <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                            <x-icon name="calendar" class="size-4 text-brand" /> {{ __('Starting soon') }}
                        </h2>
                        <div class="mt-4 flex flex-col gap-3">
                            @foreach ($upcoming as $trip)
                                <a href="{{ route('trips.show', $trip) }}" class="flex items-center gap-3 rounded-2xl border border-line bg-surface-2 p-3 transition hover:border-brand/35">
                                    <div class="text-center">
                                        <p class="font-display text-lg font-bold leading-none">{{ $trip->starts_on->format('d') }}</p>
                                        <p class="text-[10px] uppercase tracking-wide text-faint">{{ $trip->starts_on->translatedFormat('M') }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1 border-l border-line pl-3">
                                        <p class="truncate text-[13px] font-medium">{{ $trip->title }}</p>
                                        <p class="text-[11px] text-faint">{{ $trip->starts_on->diffForHumans() }}</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </x-card>
                @endif

                <x-card>
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                            <x-icon name="clock" class="size-4 text-brand" /> {{ __('Activity') }}
                        </h2>
                        <span class="flex items-center gap-1.5 text-[11px] font-medium text-positive">
                            <span class="size-1.5 animate-pulse rounded-full bg-positive"></span> {{ __('Live') }}
                        </span>
                    </div>

                    @if ($activity->isEmpty())
                        <p class="mt-4 text-sm text-muted">{{ __('Nothing has happened yet. Create a trip and add your first cost.') }}</p>
                    @else
                        <ol class="mt-4 flex flex-col gap-4">
                            @foreach ($activity as $item)
                                <li class="flex gap-3">
                                    <span class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-faint hairline">
                                        <x-icon :name="match ($item->type) {
                                            'expense_added' => 'receipt',
                                            'expense_updated', 'expense_deleted' => 'pencil',
                                            'settlement' => 'wallet',
                                            'trip_ended' => 'lock',
                                            'member_invited', 'member_joined', 'member_left', 'member_removed' => 'users',
                                            default => 'sparkles',
                                        }" class="size-3.5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[13px] leading-snug text-ink">{{ $item->description }}</p>
                                        <p class="mt-0.5 text-[11px] text-faint">
                                            <a href="{{ route('trips.show', $item->trip) }}" class="hover:text-brand">{{ $item->trip->title }}</a>
                                            - {{ $item->created_at?->diffForHumans() }}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>

                @php $smsWallet = app(\App\Services\SmsWallet::class); @endphp
                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                            <x-icon name="send" class="size-4 text-brand" /> {{ __('SMS wallet') }}
                        </h2>
                        <a href="{{ route('wallet.index') }}" class="text-xs font-medium text-brand hover:underline">{{ __('History') }}</a>
                    </div>
                    <p class="num mt-3 font-display text-[1.7rem] font-semibold leading-none text-ink">{{ \App\Support\Money::format(auth()->user()->sms_balance) }}</p>
                    <p class="mt-1.5 text-[12px] text-muted">
                        {{ __('About :user texts for reminders and pay links, at :amount each.', ['user' => number_format($smsWallet->messagesLeft(auth()->user())), 'amount' => \App\Support\Money::format($smsWallet->price())]) }}
                    </p>
                    <x-btn :href="route('wallet.index')" size="sm" icon="plus" block class="mt-4">{{ __('Top up with bKash') }}</x-btn>
                </x-card>

                <x-card>
                    <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                        <x-icon name="heart" class="size-4 text-brand" /> {{ __('Free for everyone') }}
                    </h2>
                    <p class="mt-3 text-[13px] leading-relaxed text-muted">
                        {{ __('As many trips as you like, every feature, no ads. Sofor is kept free by its') }}
                        <a href="{{ route('sponsors') }}" class="font-semibold text-ink underline decoration-line-strong underline-offset-2 hover:text-brand">{{ __('sponsors') }}</a>
                        {{ __('and the travellers who') }}
                        <a href="{{ route('support.index') }}" class="font-semibold text-ink underline decoration-line-strong underline-offset-2 hover:text-brand">{{ __('support it') }}</a>.
                    </p>
                    <x-btn :href="route('trips.create')" variant="outline" size="sm" icon="plus" block class="mt-4">
                        {{ __('Start a trip') }}
                    </x-btn>
                </x-card>

                <x-card class="border-brand/20">
                    <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                        <x-icon name="sparkles" class="size-4 text-brand" /> {{ __('Sofor tips') }}
                    </h2>
                    <ul class="mt-4 flex flex-col gap-3 text-[13px] leading-relaxed text-muted">
                        <li class="flex gap-2.5">
                            <x-icon name="bolt" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                            {{ __('Log costs as they happen - the moment you add one, everybody\'s due updates.') }}
                        </li>
                        <li class="flex gap-2.5">
                            <x-icon name="users" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                            {{ __('Invite with a phone number. They can join even before they sign up.') }}
                        </li>
                        <li class="flex gap-2.5">
                            <x-icon name="lock" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                            {{ __('End the trip when spending stops to freeze the final split.') }}
                        </li>
                    </ul>
                </x-card>
            </div>
        </div>
    </div>
</x-layouts.app>
