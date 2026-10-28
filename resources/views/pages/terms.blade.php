<x-layouts.site title="{{ __('Terms of service') }}" :heading="__('Terms of service')"
                 :subtitle="__('Plain language rules for using Sofor. Last updated :date.', ['date' => now()->translatedFormat('F Y')])">

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('1. Your account') }}</h2>
        <p>
            {{ __('You can create a Sofor account with a phone number or an email address. Phone and email verification is not enabled yet, so please make sure the details you enter are correct - invites and notifications are matched against them. You are responsible for keeping your password safe.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('2. The ledger is a record, not a payment') }}</h2>
        <p>
            {{ __('Sofor records costs, shares and settlements between travellers. We do not move money, hold funds, guarantee a debt or act as an escrow. Any payment you make happens directly between travellers, and the accuracy of what is recorded is the shared responsibility of the people in that trip.') }}
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
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('5. Availability and changes') }}</h2>
        <p>
            {{ __('Sofor is under active development. Features may change, and while we work hard to keep the ledger available, we cannot promise uninterrupted service. If we make a material change to these terms we will tell you inside the app.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('6. Contact') }}</h2>
        <p>
            {{ __('Questions about these terms? Call +880 1922-992279 or visit us at Lift 6, Elite House, 54 Motijheel C/A, Dhaka - 1000, Bangladesh.') }}
        </p>
    </section>
</x-layouts.site>
