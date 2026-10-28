<x-layouts.app title="{{ __('Profile') }}" :active="'profile'" :heading="__('Your profile')"
               :subtitle="__('How travellers reach you and how your account is secured.')" :back="route('dashboard')">

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr] lg:items-start">
        <div class="flex flex-col gap-6">
            @include('profile.partials.details')

            @unless ($user->isAdmin())
                @include('profile.partials.payouts')
            @endunless

            @include('profile.partials.password')
        </div>

        <div class="flex flex-col gap-6">
            <x-card>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                            <x-icon name="globe" class="size-4 text-brand" /> {{ __('Language') }}
                        </h2>
                        <p class="mt-0.5 text-[12px] text-muted">{{ __('Read Sofor in English or Bangla.') }}</p>
                    </div>
                    <x-locale-switch />
                </div>
            </x-card>

            <x-card>
                <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                    <x-icon name="chart" class="size-4 text-brand" /> {{ __('Your numbers') }}
                </h2>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-line bg-surface-2 p-3.5">
                        <p class="text-[10px] uppercase tracking-wide text-faint">{{ __('Trips led') }}</p>
                        <p class="mt-1 font-display text-lg font-semibold">{{ $user->ownedTrips()->count() }}</p>
                    </div>
                    <div class="rounded-2xl border border-line bg-surface-2 p-3.5">
                        <p class="text-[10px] uppercase tracking-wide text-faint">{{ __('Trips joined') }}</p>
                        <p class="mt-1 font-display text-lg font-semibold">{{ max(0, $user->memberships()->count() - $user->ownedTrips()->count()) }}</p>
                    </div>
                    <div class="rounded-2xl border border-line bg-surface-2 p-3.5">
                        <p class="text-[10px] uppercase tracking-wide text-faint">{{ __('You owe') }}</p>
                        <p class="mt-1 font-display text-lg font-semibold text-negative">{{ \App\Support\Money::format($portfolio['totals']['due']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-line bg-surface-2 p-3.5">
                        <p class="text-[10px] uppercase tracking-wide text-faint">{{ __('Owed to you') }}</p>
                        <p class="mt-1 font-display text-lg font-semibold text-positive">{{ \App\Support\Money::format($portfolio['totals']['credit']) }}</p>
                    </div>
                </div>
            </x-card>

            <div class="rounded-4xl border border-line bg-surface p-5">
                <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                    <x-icon name="building" class="size-4 text-brand" /> {{ __('Need a hand?') }}
                </h2>
                <p class="mt-2 text-[12px] leading-relaxed text-muted">
                    {{ __('The Sofor team is one call away.') }}
                </p>
                <div class="mt-3 flex flex-col gap-1.5 text-[12px] text-muted">
                    <a href="tel:+8801922992279" class="flex items-center gap-2 hover:text-ink"><x-icon name="phone" class="size-3.5" /> +880 1922-992279</a>
                    <a href="tel:+8801910069929" class="flex items-center gap-2 hover:text-ink"><x-icon name="phone" class="size-3.5" /> +880 1910-069929</a>
                    <span class="flex items-start gap-2"><x-icon name="building" class="mt-0.5 size-3.5" /> {{ __('Elite House, 54 Motijheel C/A, Dhaka - 1000') }}</span>
                </div>

                <div class="mt-4 rounded-3xl border border-brand/25 bg-brand/8 p-3.5">
                    <p class="flex items-center gap-2 text-[12px] font-semibold text-ink">
                        <x-icon name="gift" class="size-3.5 text-brand" /> {{ __('Keep Sofor free') }}
                    </p>
                    <p class="mt-1.5 text-[11px] leading-relaxed text-muted">
                        {{ __('No ads, no limits - Sofor is free for everyone. Support it with any amount you like through bKash and it goes straight into running Sofor.') }}
                    </p>
                    <x-btn :href="route('support.index')" size="sm" variant="secondary" icon="gift" class="mt-3 w-full">
                        {{ __('Support Sofor') }}
                    </x-btn>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
