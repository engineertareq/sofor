<x-layouts.auth title="{{ __('Log in') }}" tab="login" :heading="__('Welcome back')" :subtitle="__('Log in with the email or phone number you registered with.')">

    @if (app(\App\Services\MaintenanceMode::class)->active())
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-warning/30 bg-warning-tint p-4">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-warning/15 text-warning">
                <x-icon name="settings" class="size-4.5" />
            </span>
            <div>
                <p class="text-[13px] font-semibold text-ink">{{ __('Sofor is down for maintenance') }}</p>
                <p class="mt-0.5 text-[12px] leading-relaxed text-muted">{{ __('Only administrators can sign in right now. Everyone else, please check back soon.') }}</p>
            </div>
        </div>
    @elseif (! empty($pendingInvite))
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-brand/30 bg-brand-tint/60 p-4">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand/15 text-brand">
                <x-icon name="users" class="size-4.5" />
            </span>
            <div>
                <p class="text-[13px] font-semibold text-ink">{{ __('You are joining :title', ['title' => $pendingInvite->title]) }}</p>
                <p class="mt-0.5 text-[12px] leading-relaxed text-muted">{{ __('Finish logging in and Sofor adds you to this trip automatically, with your share of every cost.') }}</p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4" x-data="{ show: false, sending: false }" @submit="sending = true">
        @csrf

        <x-field name="login" :label="__('Email or phone number')" required>
            <x-input name="login" icon="user" type="text" :placeholder="__('you@example.com or 01712345678')"
                     autocomplete="username" :value="old('login')" autofocus required />
        </x-field>

        <x-field name="password" :label="__('Password')" required>
            <div class="relative">
                <x-input name="password" icon="lock" x-bind:type="show ? 'text' : 'password'"
                         placeholder="{{ __('Your password') }}" autocomplete="current-password" required />
                <button type="button" @click="show = !show"
                        class="absolute right-2 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-xl text-faint transition hover:bg-surface-2 hover:text-ink"
                        :aria-pressed="show.toString()" aria-label="{{ __('Toggle password visibility') }}">
                    <x-icon name="eye" class="size-4" x-show="!show" />
                    <x-icon name="eye-off" class="hidden size-4" x-show="show" />
                </button>
            </div>
        </x-field>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="flex cursor-pointer items-center gap-2.5 text-[13.5px] text-muted">
                <input type="checkbox" name="remember" value="1"
                       class="size-4 rounded-md border-line bg-surface-2 text-brand focus:ring-brand/40"
                       @checked(old('remember'))>
                {{ __('Keep me signed in') }}
            </label>

            <a href="{{ route('password.request') }}" class="text-[13px] font-semibold text-brand hover:underline">{{ __('Forgot password?') }}</a>
        </div>

        <x-btn type="submit" size="lg" block icon-after="arrow-right" class="mt-2" x-bind:disabled="sending">{{ __('Log in') }}</x-btn>
    </form>

    <x-slot:below>
        {{ __('New to Sofor?') }}
        <a href="{{ route('register') }}" class="font-semibold text-brand hover:underline">{{ __('Create an account') }}</a>
    </x-slot:below>
</x-layouts.auth>
