@php
    use App\Support\Phone;

    $prettyPhone = Phone::pretty($user->phone);

    // A shortcut to the inbox for the big webmail providers.
    $domain = strtolower((string) substr(strrchr((string) $user->email, '@') ?: '', 1));
    $inbox = match (true) {
        in_array($domain, ['gmail.com', 'googlemail.com'], true) => ['Gmail', 'https://mail.google.com/mail/u/0/#search/from%3A'.rawurlencode((string) config('mail.from.address'))],
        in_array($domain, ['outlook.com', 'hotmail.com', 'live.com'], true) => ['Outlook', 'https://outlook.live.com/mail/0/'],
        in_array($domain, ['yahoo.com', 'ymail.com'], true) => ['Yahoo Mail', 'https://mail.yahoo.com/'],
        default => null,
    };

    [$heading, $subtitle] = match (true) {
        $canSkip && $emailPending => [__('Confirm your email'), __('Your account is already verified - this just confirms the new email is yours.')],
        $canSkip => [__('Confirm your phone number'), __('Your account is already verified - this just confirms the new number is yours.')],
        $emailPending && $phonePending => [__('Confirm it\'s you'), __('One quick step before your trips open: confirm the email or phone number on your account.')],
        $emailPending => [__('Confirm it\'s you'), __('One quick step before your trips open: confirm the email on your account.')],
        default => [__('Confirm it\'s you'), __('One quick step before your trips open: confirm the phone number on your account.')],
    };

    $fixLabel = match (true) {
        $emailPending && $phonePending => __('Wrong email or number? Fix it'),
        $emailPending => __('Wrong email? Fix it'),
        default => __('Wrong number? Fix it'),
    };
@endphp

<x-layouts.guest title="{{ __('Confirm your account') }}" :noindex="true" :heading="$heading" :subtitle="$subtitle">

    <div class="flex flex-col gap-4">
        {{-- ===================== Email ===================== --}}
        @if ($emailPending)
            <section class="rounded-4xl border-[1.5px] border-line bg-surface p-5 shadow-soft">
                <div class="flex items-start gap-3.5">
                    <span class="relative flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-tint text-brand">
                        <x-icon name="mail" class="size-5" />
                        @if ($emailCodeSent)
                            <span class="absolute -right-0.5 -top-0.5 size-3 animate-pulse-ring rounded-full border-2 border-surface bg-accent"></span>
                        @endif
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-display text-[17px] font-semibold text-ink">{{ $emailCodeSent ? __('Enter the code we emailed') : __('Confirm your email') }}</h2>
                        <p class="mt-1 text-[13.5px] leading-relaxed text-muted">
                            @if ($emailCodeSent)
                                {!! __('It went to :email and works for :minutes minutes.', ['email' => '<strong class="font-semibold text-ink [overflow-wrap:anywhere]">'.e($user->email).'</strong>', 'minutes' => \App\Services\EmailCodes::TTL_MINUTES]) !!}
                            @else
                                {!! __('We will email a 6 digit code to :email.', ['email' => '<strong class="font-semibold text-ink [overflow-wrap:anywhere]">'.e($user->email).'</strong>']) !!}
                            @endif
                        </p>
                    </div>
                </div>

                @if ($emailCodeSent)
                    <form method="POST" action="{{ route('verification.email.verify') }}" class="mt-5">
                        @csrf
                        <x-code-input name="email_code" :auto-submit="true" />
                        <x-btn type="submit" block class="mt-4" icon="check">{{ __('Confirm my email') }}</x-btn>
                    </form>
                @endif

                <div @class(['flex flex-wrap items-center gap-2', 'mt-3 justify-center' => $emailCodeSent, 'mt-4' => ! $emailCodeSent])>
                    @if ($inbox)
                        <x-btn :href="$inbox[1]" target="_blank" rel="noopener" size="sm" :variant="$emailCodeSent ? 'ghost' : 'outline'" icon="arrow-up-right">{{ __('Open :inbox', ['inbox' => $inbox[0]]) }}</x-btn>
                    @endif

                    <form method="POST" action="{{ route('verification.send') }}" x-data="cooldown({{ $emailCooldown }})" @class(['w-full' => ! $emailCodeSent && ! $inbox])>
                        @csrf
                        @if ($emailCodeSent)
                            <button type="submit" :disabled="left > 0"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-[13px] font-semibold text-brand transition hover:bg-brand-tint disabled:cursor-not-allowed disabled:text-faint disabled:hover:bg-transparent">
                                <x-icon name="refresh" class="size-3.5" />
                                <span x-show="left === 0">{{ __('Email me a new code') }}</span>
                                <span x-show="left > 0" x-cloak>{{ __('New code in') }} <span class="num" x-text="label"></span></span>
                            </button>
                        @else
                            <x-btn type="submit" size="sm" :block="! $inbox" icon="send" x-bind:disabled="left > 0">
                                <span x-show="left === 0">{{ __('Email me the code') }}</span>
                                <span x-show="left > 0" x-cloak>{{ __('Send again in') }} <span class="num" x-text="label"></span></span>
                            </x-btn>
                        @endif
                    </form>
                </div>

                {{-- With a code out, the code box shows its own errors. --}}
                @unless ($emailCodeSent)
                    @error('email_code')
                        <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-negative">
                            <x-icon name="alert" class="size-3.5 shrink-0" /> {{ $message }}
                        </p>
                    @enderror
                @endunless

                <p class="mt-3 text-[11.5px] leading-relaxed text-faint">
                    {{ __('Not there? Check spam and promotions. It comes from :config.', ['config' => config('mail.from.address')]) }}
                </p>
            </section>
        @endif

        @if ($emailPending && $phonePending)
            <div class="flex items-center gap-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-faint" aria-hidden="true">
                <span class="h-px flex-1 bg-line"></span> {{ __('or') }} <span class="h-px flex-1 bg-line"></span>
            </div>
        @endif

        {{-- ===================== Phone ===================== --}}
        @if ($phonePending)
            <section class="rounded-4xl border-[1.5px] border-line bg-surface p-5 shadow-soft">
                <div class="flex items-start gap-3.5">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-accent-tint text-accent-2">
                        <x-icon name="phone" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-display text-[17px] font-semibold text-ink">{{ $codeSent ? __('Enter the code we texted') : __('Confirm your phone') }}</h2>
                        <p class="mt-1 text-[13.5px] leading-relaxed text-muted">
                            @if ($codeSent)
                                {!! __('It went to :phone and works for 10 minutes.', ['phone' => '<strong class="num font-semibold text-ink">'.e($prettyPhone).'</strong>']) !!}
                            @else
                                {!! __('We will text a 6 digit code to :phone.', ['phone' => '<strong class="num font-semibold text-ink">'.e($prettyPhone).'</strong>']) !!}
                            @endif
                        </p>
                    </div>
                </div>

                @if ($codeSent)
                    <form method="POST" action="{{ route('verification.phone.verify') }}" class="mt-5">
                        @csrf
                        <x-code-input :auto-submit="true" />
                        <x-btn type="submit" block class="mt-4" icon="check">{{ __('Confirm my number') }}</x-btn>
                    </form>
                @endif

                <form method="POST" action="{{ route('verification.phone.send') }}" x-data="cooldown({{ $phoneCooldown }})"
                      @class(['mt-3 text-center' => $codeSent, 'mt-4' => ! $codeSent])>
                    @csrf
                    @if ($codeSent)
                        <button type="submit" :disabled="left > 0"
                                class="inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-[13px] font-semibold text-brand transition hover:bg-brand-tint disabled:cursor-not-allowed disabled:text-faint disabled:hover:bg-transparent">
                            <x-icon name="refresh" class="size-3.5" />
                            <span x-show="left === 0">{{ __('Text me a new code') }}</span>
                            <span x-show="left > 0" x-cloak>{{ __('New code in') }} <span class="num" x-text="label"></span></span>
                        </button>
                    @else
                        <x-btn type="submit" block icon="send" x-bind:disabled="left > 0">
                            <span x-show="left === 0">{{ __('Text me the code') }}</span>
                            <span x-show="left > 0" x-cloak>{{ __('Send again in') }} <span class="num" x-text="label"></span></span>
                        </x-btn>
                        @error('code')
                            <p class="mt-2 text-xs font-medium text-negative">{{ $message }}</p>
                        @enderror
                    @endif
                </form>
            </section>
        @endif

        {{-- ===================== Typo in the address ===================== --}}
        <details class="group rounded-3xl border border-line bg-surface-2 px-4 py-3" @if ($errors->hasAny(['email', 'phone'])) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-[13px] font-semibold text-muted transition hover:text-ink">
                {{ $fixLabel }}
                <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
            </summary>

            <form method="POST" action="{{ route('verification.contact.update') }}" class="mt-3 flex flex-col gap-3"
                  x-data="{ type: @js(old('type', $emailPending ? 'email' : 'phone')) }">
                @csrf
                @method('PUT')
                <input type="hidden" name="type" :value="type">

                @if ($emailPending && $phonePending)
                    <div class="grid grid-cols-2 gap-1 rounded-2xl border border-line bg-surface p-1">
                        @foreach (['email' => __('Email'), 'phone' => __('Phone')] as $value => $label)
                            <button type="button" @click="type = @js($value)"
                                    :class="type === @js($value) ? 'bg-ink text-canvas' : 'text-muted hover:text-ink'"
                                    class="rounded-xl py-2 text-[12.5px] font-semibold transition">{{ $label }}</button>
                        @endforeach
                    </div>
                @endif

                @if ($emailPending)
                    <div x-show="type === 'email'" @if (! ($emailPending && ! $phonePending)) x-cloak @endif>
                        <x-field name="email" :label="__('Correct email')">
                            <x-input name="email" type="email" icon="mail" :value="$user->email" autocomplete="email" />
                        </x-field>
                    </div>
                @endif

                @if ($phonePending)
                    <div x-show="type === 'phone'" @if ($emailPending) x-cloak @endif>
                        <x-field name="phone" :label="__('Correct mobile number')">
                            <x-input name="phone" type="tel" icon="phone" :value="Phone::normalize($user->phone) ? '0'.substr(Phone::normalize($user->phone), 4) : ''" autocomplete="tel" />
                        </x-field>
                    </div>
                @endif

                <x-btn type="submit" size="sm" variant="secondary" class="self-start">{{ __('Save and send again') }}</x-btn>
            </form>
        </details>

        <div class="mt-1 flex items-center justify-between gap-3 text-[13px]">
            @if ($canSkip)
                <a href="{{ $user->homeUrl() }}" class="font-semibold text-muted transition hover:text-ink">{{ __('Skip for now') }}</a>
            @else
                <span class="text-faint">{{ __('Signed in as :username', ['username' => '@'.$user->username]) }}</span>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="font-semibold text-muted transition hover:text-negative">{{ __('Sign out') }}</button>
            </form>
        </div>
    </div>

    <x-slot:art>
        <span class="stamp text-brand">{{ __('Why we ask') }}</span>
        <h2 class="mt-6 max-w-[18ch] font-display text-[2rem] font-semibold leading-[1.12] tracking-tight text-ink">
            {{ __('One tap now saves a lot of trouble later.') }}
        </h2>
        <ul class="mt-7 flex flex-col gap-3.5">
            @foreach ([
                ['users', __('Trip invites sent to your email or number find their way to you.')],
                ['lock', __('You can always get back in if you forget your password.')],
                ['shield', __('Nobody else can sign up as you and slip into your trips.')],
            ] as [$icon, $text])
                <li class="flex items-start gap-3 text-[14px] leading-relaxed text-muted">
                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl bg-brand-tint text-brand">
                        <x-icon :name="$icon" class="size-4" />
                    </span>
                    {{ $text }}
                </li>
            @endforeach
        </ul>
    </x-slot:art>
</x-layouts.guest>
