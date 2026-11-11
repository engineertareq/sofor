{{-- The privacy policy for the website and the Android app. Google Play checks it
     against the app's Data safety answers: keep both saying the same thing. --}}
<x-layouts.site title="{{ __('Privacy policy') }}" :heading="__('Privacy policy')"
                 :subtitle="__('What we store, why we store it and how to get it removed. Last updated :date.', ['date' => now()->translatedFormat('F Y')])">

    <section class="flex flex-col gap-3">
        <p>
            {{ __('Sofor is run by Solutor. This policy covers the website sofor.io and the Sofor app for Android, which share the same account.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('What we collect') }}</h2>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('Your account: your name, username, the email address and phone number you give, your password (stored only as a one-way hash), the language you read Sofor in and, if you add one, a profile photo.'),
                __('Your trips: the trips you create or join, the travellers on them, every cost, its split and receipt photo, the payments you record, polls and replies, and the trip budget.'),
                __('Where friends pay you: the bKash, Nagad, Rocket, Upay or bank account numbers you add to your profile.'),
                __('Codes we send to confirm your email or phone and to reset your password. We keep only a one-way hash of each code, and it expires within minutes.'),
                __('Technical data: your signed in sessions and when you were last active, and errors our servers record so we can fix them.'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="info" class="mt-1 size-3.5 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>

        <h3 class="mt-2 font-semibold text-ink">{{ __('Only if you use these features') }}</h3>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('Checkpoint lists: the full name, ID type and number, date of birth, gender, phone, address, blood group and emergency contact you enter for army, police or hotel checks. They are stored encrypted and deleted 14 days after the trip ends.'),
                __('Check-ins: the place you name and, only when you choose to share it at that moment, your location. Sofor never tracks your location in the background.'),
                __('Family links: the private link and family phone number you set up, so your family can see your check-ins.'),
                __('Your travel map: the districts and countries you mark as visited.'),
                __('SMS credit and donations on the website: the amount, the bKash transaction ID and, for a donation, the name and contact you give. Payment is made in bKash\'s own checkout; we never see your PIN or card.'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="info" class="mt-1 size-3.5 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>

        <h3 class="mt-2 font-semibold text-ink">{{ __('In the Android app') }}</h3>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('The name of your phone and Android version (for example "Pixel 7 · Android 15"), so you can recognise each place you are signed in.'),
                __('A push notification token from Google Firebase Cloud Messaging, so we can send alerts to your phone, and the app\'s version.'),
                __('Costs you add without a connection are kept on your phone until they are sent. Your sign in is stored encrypted on the phone and is not included in phone backups.'),
                __('The app reads verification codes with Google\'s SMS Retriever, which hands it only the one text that carries Sofor\'s own code. The app cannot read your other messages, and it does not ask for your contacts, location, camera or files.'),
                __('The app contains no advertising and no analytics or tracking tools.'),
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
            {{ __('Solely to run the service: to show you your trips, to work out who owes what, to match invites to the right traveller, to confirm your email or phone, and to tell the people on a trip when something changes, in the app, by email, by push notification or by text.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Who can see your data') }}</h2>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('The travellers on a trip see the trip, its costs, who paid and the resulting balances.'),
                __('Travellers who owe you see the payout numbers you added, so they can pay you.'),
                __('Anyone with your family link sees your check-ins on that trip, until you turn the link off.'),
                __('Your public profile (sofor.io/@username) shows your name, photo and travel map. You can make it private from your profile.'),
                __('Sofor administrators can see account and trip records for support and to stop abuse.'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="info" class="mt-1 size-3.5 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
        <p>
            {{ __('We do not sell your data, and we do not share it with advertisers.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Services we use') }}</h2>
        <p>{{ __('These companies process data for us, only to provide Sofor:') }}</p>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('Hostinger: hosts the website, the database and our email.'),
                __('Cloudflare R2: stores profile and receipt photos.'),
                __('sms.net.bd: sends verification codes and reminders by text.'),
                __('Google Firebase Cloud Messaging: delivers push notifications to the Android app.'),
                __('bKash: processes SMS credit top-ups and donations on the website.'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="info" class="mt-1 size-3.5 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Payments') }}</h2>
        <p>
            {{ __('Sofor never moves money between travellers: you pay your friends directly, and Sofor only records what you tell it. We never store card numbers, bKash PINs or bank passwords. Payment references you type in are notes for your group only.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('How we keep it safe') }}</h2>
        <p>
            {{ __('Everything travels over HTTPS. Passwords and codes are stored only as one-way hashes, and checkpoint ID details are encrypted. Photos are served only to the people allowed to see them.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('How long we keep it') }}</h2>
        <p>
            {{ __('We keep your data for as long as you have an account. Checkpoint ID details are deleted 14 days after each trip ends. When you delete your account, your personal details are removed at once; costs and payments on trips you shared stay for the other travellers, under the name "Deleted traveller", so their balances do not change.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Your control') }}</h2>
        <ul class="flex flex-col gap-2">
            @foreach ([
                __('Update your name, username, email, phone, photo and payout numbers at any time from your profile.'),
                __('Make your public profile private, and turn push notifications off in your phone\'s settings.'),
                __('Trip leaders can remove a traveller from future splits.'),
            ] as $item)
                <li class="flex items-start gap-2.5">
                    <x-icon name="info" class="mt-1 size-3.5 shrink-0 text-brand" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
        <p>
            {{ __('You can delete your account yourself, in the app (Profile, then Delete account) or on the website (your profile, then Delete account).') }}
            <a href="{{ route('account.delete') }}" class="font-semibold text-brand hover:underline">{{ __('How to delete your account and what is deleted') }}</a>
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Children') }}</h2>
        <p>
            {{ __('Sofor is meant for people aged 18 and over, and we do not knowingly collect data from children.') }}
        </p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Contact') }}</h2>
        <p>
            {{ __('Questions about your data, or a request we can help with: write to') }}
            <a href="mailto:hello@sofor.io" class="font-semibold text-brand hover:underline">hello@sofor.io</a>.
            {{ __('If this policy changes, the date at the top changes with it.') }}
        </p>
    </section>
</x-layouts.site>
