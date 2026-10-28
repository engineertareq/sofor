<x-layouts.auth title="{{ __('Forgot password') }}" :noindex="true" :heading="__('Forgot your password?')"
                  :subtitle="__('It happens. Enter the email or phone number on your account and we will help you set a new one.')"
                  :back="route('login')" :back-label="__('Back to log in')">

    @if (session('status'))
        <div class="mb-5 flex items-start gap-3 rounded-3xl border border-positive/30 bg-positive-tint p-4" role="status">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-positive/15 text-positive">
                <x-icon name="mail" class="size-5" />
            </span>
            <div>
                <p class="text-[13px] font-semibold text-ink">{{ __('Check your inbox') }}</p>
                <p class="mt-0.5 text-[12px] leading-relaxed text-muted">{{ __(':session Not there? Look in spam, or ask again in a minute.', ['session' => session('status')]) }}</p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4" x-data="{ sending: false }" @submit="sending = true">
        @csrf

        <x-field name="login" :label="__('Email or phone number')" required>
            <x-input name="login" icon="user" :placeholder="__('you@example.com or 01712345678')" autocomplete="username"
                     :value="old('login')" autofocus required />
        </x-field>

        <x-btn type="submit" size="lg" block icon-after="arrow-right" x-bind:disabled="sending">
            <span x-text="sending ? @js(__('Sending...')) : @js(__('Send reset instructions'))">{{ __('Send reset instructions') }}</span>
        </x-btn>
    </form>

    <div class="mt-6 grid gap-3 sm:grid-cols-2">
        @foreach ([
            ['mail', __('By email'), __('A link to choose a new password.')],
            ['phone', __('By phone'), __('A 6 digit code by text message.')],
        ] as [$icon, $title, $text])
            <div class="flex items-start gap-3 rounded-3xl border border-line bg-surface-2 p-3.5">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-tint text-brand"><x-icon :name="$icon" class="size-4" /></span>
                <div>
                    <p class="text-[13px] font-semibold text-ink">{{ $title }}</p>
                    <p class="mt-0.5 text-[11.5px] leading-relaxed text-muted">{{ $text }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <p class="mt-6 text-center text-[13px] text-muted">
        {{ __('Remembered it?') }} <a href="{{ route('login') }}" class="font-semibold text-brand hover:underline">{{ __('Log in') }}</a>
    </p>
</x-layouts.auth>
