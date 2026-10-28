<x-layouts.auth title="{{ __('Create your account') }}" tab="register" :legal="false" :heading="__('Create your Sofor account')" :subtitle="__('Sign up with an email address or a phone number. We will send a link or a code to confirm it is yours.')">

    @if (! empty($pendingInvite))
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-brand/30 bg-brand-tint/60 p-4">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand/15 text-brand">
                <x-icon name="users" class="size-4.5" />
            </span>
            <div>
                <p class="text-[13px] font-semibold text-ink">{{ __('You are joining :title', ['title' => $pendingInvite->title]) }}</p>
                <p class="mt-0.5 text-[12px] leading-relaxed text-muted">{{ __('Create your account and Sofor adds you to this trip right away - no code to enter again.') }}</p>
            </div>
        </div>
    @endif

    {{-- The password rules (8+, letters, numbers) tick off as they are met, as RegisterRequest checks them. --}}
    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-4" @submit="sending = true"
          x-data="{ type: @js(old('contact_type') === 'phone' ? 'phone' : 'email'), show: false, sending: false, password: '', confirm: '',
                    get rules() { return [[...this.password].length >= 8, /\p{L}/u.test(this.password), /\p{N}/u.test(this.password)]; } }">
        @csrf
        <input type="hidden" name="contact_type" :value="type">

        <x-field name="name" :label="__('Your name')" required>
            <x-input name="name" icon="user" placeholder="{{ __('Your full name') }}" autocomplete="name" :value="old('name')" required autofocus />
        </x-field>

        <x-field name="username" :label="__('Username')" required>
            <x-username-input :value="old('username', '')" suggest-from="name" />
        </x-field>

        {{-- Email or phone: one tap to switch --}}
        <div>
            <p class="mb-1.5 text-[13px] font-medium text-muted">{{ __('How should travellers reach you?') }}</p>
            <div class="relative grid grid-cols-2 rounded-2xl bg-surface-2 p-1" role="radiogroup" aria-label="{{ __('How should travellers reach you?') }}">
                <span aria-hidden="true" class="absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-xl bg-surface shadow-soft ring-1 ring-line transition-transform duration-300"
                      :class="type === 'phone' && 'translate-x-full'"></span>
                @foreach (['email' => ['mail', __('Email')], 'phone' => ['phone', __('Phone')]] as $option => [$icon, $label])
                    <button type="button" role="radio" @click="type = '{{ $option }}'" :aria-checked="(type === '{{ $option }}').toString()"
                            :class="type === '{{ $option }}' ? 'text-ink' : 'text-muted hover:text-ink'"
                            class="relative flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-[13.5px] font-semibold transition-colors">
                        <x-icon :name="$icon" class="size-4" /> {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div x-show="type === 'email'" x-cloak>
            <x-field name="email" :label="__('Email address')">
                <x-input name="email" type="email" icon="mail" placeholder="you@example.com" autocomplete="email" :value="old('email')" />
            </x-field>
        </div>

        <div x-show="type === 'phone'" x-cloak>
            <x-field name="phone" :label="__('Mobile number')" :hint="__('Bangladeshi numbers only for now, for example 01712345678.')">
                <x-input name="phone" type="tel" icon="phone" placeholder="01712345678" autocomplete="tel" :value="old('phone')" />
            </x-field>
        </div>

        <x-field name="password" :label="__('Password')" required>
            <div class="relative">
                <x-input name="password" icon="lock" x-bind:type="show ? 'text' : 'password'" x-model="password"
                         placeholder="{{ __('Create a password') }}" autocomplete="new-password" required />
                <button type="button" @click="show = !show"
                        class="absolute right-2 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-xl text-faint transition hover:bg-surface-2 hover:text-ink"
                        :aria-pressed="show.toString()" aria-label="{{ __('Toggle password visibility') }}">
                    <x-icon name="eye" class="size-4" x-show="!show" />
                    <x-icon name="eye-off" class="size-4" x-show="show" x-cloak />
                </button>
            </div>
            <ul class="mt-2.5 flex flex-wrap gap-1.5" aria-live="polite">
                @foreach ([__('8+ characters'), __('Letters'), __('Numbers')] as $index => $rule)
                    <li class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11.5px] font-semibold transition-colors"
                        :class="rules[{{ $index }}] ? 'bg-positive-tint text-positive' : 'bg-surface-2 text-faint'">
                        <x-icon name="check" class="size-3" /> {{ $rule }}
                    </li>
                @endforeach
            </ul>
        </x-field>

        <x-field name="password_confirmation" :label="__('Confirm password')" required>
            <div class="relative">
                <x-input name="password_confirmation" icon="shield" x-bind:type="show ? 'text' : 'password'" x-model="confirm"
                         placeholder="{{ __('Repeat the password') }}" autocomplete="new-password" required />
                <span x-show="confirm !== '' && confirm === password" x-cloak x-transition.opacity
                      class="pointer-events-none absolute right-3 top-1/2 flex size-6 -translate-y-1/2 items-center justify-center rounded-full bg-positive text-white"
                      title="{{ __('Passwords match') }}">
                    <x-icon name="check" class="size-3.5" />
                </span>
            </div>
        </x-field>

        <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-surface-2 p-3.5 text-[12.5px] leading-relaxed text-muted">
            <input type="checkbox" name="terms" value="1"
                   class="mt-0.5 size-4 rounded-md border-line bg-surface text-brand focus:ring-brand/40"
                   @checked(old('terms')) required>
            <span>
                {{ __('I agree to the') }}
                <a href="{{ route('terms') }}" class="font-semibold text-brand hover:underline">{{ __('terms of service') }}</a>
                {{ __('and') }}
                <a href="{{ route('privacy') }}" class="font-semibold text-brand hover:underline">{{ __('privacy policy') }}</a>.
            </span>
        </label>

        <x-btn type="submit" size="lg" block icon-after="arrow-right" class="mt-2" x-bind:disabled="sending">{{ __('Create account') }}</x-btn>
    </form>

    <x-slot:below>
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" class="font-semibold text-brand hover:underline">{{ __('Log in') }}</a>
    </x-slot:below>
</x-layouts.auth>
