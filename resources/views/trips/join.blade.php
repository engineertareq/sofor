<x-layouts.guest :title="__('Join :trip', ['trip' => $trip->title])" :noindex="true" :heading="__('You have been invited')" :subtitle="__('Review the trip before you jump in.')">

    <div class="flex flex-col gap-5">
        <div class="overflow-hidden rounded-4xl border border-line">
            <div class="relative h-24" style="background-image: {{ $trip->coverStyle() }}">
                <div class="absolute inset-0 grid-lines opacity-20"></div>
            </div>
            <div class="bg-surface p-4">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-surface-2 hairline"><x-glyph :name="$trip->icon" class="size-8" /></span>
                    <div class="min-w-0">
                        <p class="truncate font-display text-base font-semibold">{{ $trip->title }}</p>
                        <p class="text-[11px] text-faint">{{ __(':destination - led by :name', ['destination' => $trip->destination ?: 'Destination TBD', 'name' => $trip->owner?->name]) }}</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2">
                    <div class="rounded-2xl border border-line bg-surface-2 p-3">
                        <p class="text-[10px] uppercase tracking-wide text-faint">{{ __('Spent so far') }}</p>
                        <p class="mt-1 font-display text-sm font-semibold">{{ \App\Support\Money::format($ledger['stats']['spent'], $trip->currency) }}</p>
                    </div>
                    <div class="rounded-2xl border border-line bg-surface-2 p-3">
                        <p class="text-[10px] uppercase tracking-wide text-faint">{{ __('Per person') }}</p>
                        <p class="mt-1 font-display text-sm font-semibold text-brand">{{ \App\Support\Money::format($ledger['stats']['per_person'], $trip->currency) }}</p>
                    </div>
                    <div class="rounded-2xl border border-line bg-surface-2 p-3">
                        <p class="text-[10px] uppercase tracking-wide text-faint">{{ __('Travellers') }}</p>
                        <p class="mt-1 font-display text-sm font-semibold">{{ $ledger['stats']['travellers'] }}</p>
                    </div>
                </div>

                @php $budget = $ledger['budget']; @endphp
                @if ($budget['total'] !== null)
                    <div class="mt-3 flex items-center gap-3 rounded-2xl border border-line bg-surface-2 px-3 py-2.5">
                        <x-icon name="wallet" class="size-4 shrink-0 text-brand" />
                        <p class="text-[12px] text-ink">
                            {{ __('Planned budget') }}
                            <span class="font-semibold">{{ __(':amount a person', ['amount' => \App\Support\Money::format($budget['per_person'], $trip->currency)]) }}</span>
                            @if ($trip->starts_on && $trip->ends_on)
                                <span class="text-faint">&middot; {{ $trip->starts_on->translatedFormat('j M') }}&ndash;{{ $trip->ends_on->translatedFormat('j M') }}</span>
                            @endif
                        </p>
                    </div>
                @elseif ($estimate)
                    <div class="mt-3 flex items-center gap-3 rounded-2xl border border-info/25 bg-info-tint/60 px-3 py-2.5">
                        <x-icon name="chart" class="size-4 shrink-0 text-info" />
                        <p class="text-[12px] text-ink">
                            {{ __('Trips like this usually cost about') }}
                            <span class="font-semibold">{{ __(':amount a person', ['amount' => \App\Support\Money::format($estimate['per_person'], $trip->currency)]) }}</span>
                            <span class="text-faint">{{ __('· from :trips finished Sofor trips', ['trips' => $estimate['trips']]) }}</span>
                        </p>
                    </div>
                @endif

                @if ($memberNames->isNotEmpty())
                    <div class="mt-4 flex items-center gap-2">
                        <div class="flex -space-x-2">
                            @foreach ($memberNames->take(5) as $name)
                                <x-avatar :name="$name" size="xs" class="ring-2 ring-surface" />
                            @endforeach
                        </div>
                        <p class="text-[11px] text-faint">{{ $memberNames->implode(', ') }}</p>
                    </div>
                @endif
            </div>
        </div>

        @if ($trip->isEnded())
            <div class="rounded-3xl border border-warning/25 bg-warning/8 p-4 text-[13px] leading-relaxed text-ink">
                {{ __('This trip has already ended, so new travellers cannot join. Ask :name for a copy of the final split.', ['name' => $trip->owner?->name]) }}
            </div>
        @elseif (auth()->check())
            <form method="POST" action="{{ route('trips.join', $trip->invite_code) }}">
                @csrf
                <x-btn type="submit" size="lg" block icon="sparkles">{{ __('Join the trip and see my share') }}</x-btn>
            </form>
            <p class="text-center text-[11px] text-faint">
                {{ __('You will be added as :name and your share of every cost becomes visible instantly.', ['name' => auth()->user()->name]) }}
            </p>
        @else
            <div class="flex flex-col gap-3">
                <x-btn :href="route('register')" size="lg" block icon="sparkles">{{ __('Create an account to join') }}</x-btn>
                <x-btn :href="route('login')" variant="outline" size="lg" block icon="logout">{{ __('I already have an account') }}</x-btn>
            </div>
            <p class="text-center text-[11px] text-faint">
                {{ __('Signing up takes a phone number or an email - no verification needed yet. Sofor links this invite to your account, so you are added to :title the moment you finish.', ['title' => $trip->title]) }}
            </p>
        @endif
    </div>
</x-layouts.guest>
