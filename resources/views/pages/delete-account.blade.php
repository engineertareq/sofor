<x-layouts.site title="{{ __('Delete your Sofor account') }}" :heading="__('Delete your Sofor account')"
                 :subtitle="__('How to delete your account and its data, from the app or the website.')">

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('How to delete your account') }}</h2>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('In the Sofor app: open Profile, tap "Delete account" and confirm with your password.'),
                __('On the website: sign in, open your profile, choose "Delete account" at the bottom and confirm with your password.'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="check" class="mt-1 size-4 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
        <p>{{ __('Your account is deleted at once. There is no waiting period, and it cannot be undone.') }}</p>
        <div>
            @auth
                <x-btn :href="route('profile.edit')" icon="user">{{ __('Open my profile') }}</x-btn>
            @else
                <x-btn :href="route('login')" icon="lock">{{ __('Sign in to delete my account') }}</x-btn>
            @endauth
        </div>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('What is deleted') }}</h2>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('Your name, username, email address, phone number and password.'),
                __('Your profile photo, your payout numbers (bKash, Nagad, bank) and your travel map.'),
                __('ID details you added to checkpoint lists, your check-ins and family links.'),
                __('Your alerts, and every phone and browser you were signed in on.'),
                __('Trips that only you were on, with all of their costs and receipts.'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="check" class="mt-1 size-4 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('What is kept, and why') }}</h2>
        <p>
            {{ __('A trip is a shared record. On trips you took with other people, the costs and payments you were part of stay, so that their balances do not change. Your name on those trips is replaced with "Deleted traveller" and nothing there identifies you any more.') }}
        </p>
        <p>
            {{ __('A trip you were leading that other people are still on is kept for them and closed, with its final dues.') }}
        </p>
        <p>
            {{ __('Records of SMS credit you bought are kept for as long as accounting rules require, without your name.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Before you delete') }}</h2>
        <p>
            {{ __('If you are organising a tour that people have booked, complete or cancel it first. Unused SMS credit is lost when the account is deleted.') }}
        </p>
    </section>
</x-layouts.site>
