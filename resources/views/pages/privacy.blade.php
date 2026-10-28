<x-layouts.site title="{{ __('Privacy policy') }}" :heading="__('Privacy policy')"
                 :subtitle="__('What we store, why we store it and how to get it removed. Last updated :date.', ['date' => now()->translatedFormat('F Y')])">

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('What we collect') }}</h2>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('Your name, and the email address or phone number you signed up with.'),
                __('The trips you create or join, including costs, shares and settlements.'),
                __('Basic technical data such as session information and the last time you signed in.'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="info" class="mt-1 size-3.5 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Why we collect it') }}</h2>
        <p>
            {{ __('Solely to run the service: to show you your trips, to work out who owes what, to match invites to the right traveller and to notify the people in a trip when something changes.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Who can see your data') }}</h2>
        <p>
            {{ __('Travellers inside a trip can see the trip, its costs, who paid and the resulting balances. Sofor administrators can see account and trip records for support and abuse prevention. We do not sell your data and we do not share it with advertisers.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Payments') }}</h2>
        <p>
            {{ __('Because Sofor does not process payments, we never store card numbers, bKash PINs or bank credentials. Payment references you type in are notes for your group only.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Your control') }}</h2>
        <p>
            {{ __('You can update your name, email or phone at any time from your profile. Trip leaders can remove a traveller from future splits. To delete your account entirely, contact the Sofor team and we will remove your account and personal details.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Verification, coming later') }}</h2>
        <p>
            {{ __('Phone and email verification is deliberately not enforced in this release. When it arrives, verification data will be used only to confirm ownership of the contact details on your account.') }}
        </p>
    </section>
</x-layouts.site>
