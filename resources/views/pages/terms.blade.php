{{-- The terms for the website and the Android app; Google Play links here. Keep
     them in step with the privacy policy (pages/privacy) and /delete-account. --}}
<x-layouts.site title="{{ __('Terms of service') }}" :heading="__('Terms of service')"
                 :subtitle="__('Plain language rules for using Sofor. Last updated :date.', ['date' => now()->translatedFormat('F Y')])">

    <section class="flex flex-col gap-3">
        <p>
            {{ __('Sofor is run by Solutor. These terms cover the website sofor.io and the Sofor app for Android, which share the same account. By creating an account you agree to them.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('1. Your account') }}</h2>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('You can create a Sofor account with a phone number or an email address, and you confirm it with a code we text or email to you.'),
                __('Sofor is for people aged 18 and over. One account is for one person.'),
                __('Keep your details correct: invites and alerts are matched to your email and phone. You are responsible for keeping your password safe.'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="check-circle" class="mt-1 size-3.5 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('2. The ledger is a record, not a payment') }}</h2>
        <p>
            {{ __('Sofor records costs, shares and settlements between travellers. We do not move money, hold funds, guarantee a debt or act as an escrow. Any payment you make happens directly between travellers, and the accuracy of what is recorded is the shared responsibility of the people in that trip.') }}
        </p>
        <p>
            {{ __('A payment someone records is confirmed by the person who received it. The payout numbers you add (bKash, Nagad, bank and others) are shown to travellers who owe you; make sure they are yours and correct.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('3. Trip leaders and travellers') }}</h2>
        <p>
            {{ __('Whoever creates a trip becomes its leader. Leaders can invite travellers, adjust split weights, remove travellers and end the trip. Once a trip has ended the ledger is locked - no new costs can be added by anyone, including the leader.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('4. Fair use') }}</h2>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('Do not use Sofor to record anything illegal or to harass another traveller.'),
                __('Do not attempt to access trips you were not invited to.'),
                __('Do not scrape, resell or overload the service.'),
                __('Administrators may pause or remove an account that breaks these rules.'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="check-circle" class="mt-1 size-3.5 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('5. The Android app') }}</h2>
        <p>
            {{ __('The Sofor app is free from Google Play, with no ads and nothing to buy inside it. Google Play\'s own terms also apply to downloading it. To keep your data correct, we may ask you to update an old version of the app before it can be used again.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('6. SMS credit and donations') }}</h2>
        <p>
            {{ __('On the website you can buy SMS credit through bKash, to send payment reminders by text. Credit is used up as texts are sent, and unused credit is lost when you delete your account. Donations are voluntary and do not buy anything.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('7. Your data and leaving Sofor') }}</h2>
        <p>
            <a href="{{ route('privacy') }}" class="font-semibold text-brand hover:underline">{{ __('How we handle your data is explained in our privacy policy.') }}</a>
            {{ __('You can delete your account at any time, in the app or on the website.') }}
            <a href="{{ route('account.delete') }}" class="font-semibold text-brand hover:underline">{{ __('How to delete your account and what is deleted') }}</a>
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('8. Availability and changes') }}</h2>
        <p>
            {{ __('Sofor is under active development. Features may change, and while we work hard to keep the ledger available, we cannot promise uninterrupted service. If we make a material change to these terms we will tell you in the app or by email, and the date at the top changes with it.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('9. Contact') }}</h2>
        <p>
            {{ __('Questions about these terms? Call +880 1922-992279 or visit us at Lift 6, Elite House, 54 Motijheel C/A, Dhaka - 1000, Bangladesh.') }}
            {{ __('You can also write to') }}
            <a href="mailto:hello@sofor.io" class="font-semibold text-brand hover:underline">hello@sofor.io</a>.
        </p>
    </section>
</x-layouts.site>
