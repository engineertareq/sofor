<x-layouts.auth title="{{ __('Choose a new password') }}" :noindex="true" :heading="__('Choose a new password')"
                  :subtitle="__('Pick something you have not used on Sofor before. You will be signed out on your other devices.')">

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4" x-data="{ show: false, saving: false }" @submit="saving = true">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-field name="email" :label="__('Email')" required>
            <x-input name="email" type="email" icon="mail" :value="old('email', $email)" autocomplete="email" required />
        </x-field>

        @include('auth.partials.new-password')

        <x-btn type="submit" size="lg" block icon="check" x-bind:disabled="saving">
            <span x-text="saving ? @js(__('Saving...')) : @js(__('Save new password'))">{{ __('Save new password') }}</span>
        </x-btn>
    </form>

    <p class="mt-6 text-center text-[13px] text-muted">
        {{ __('Link expired?') }} <a href="{{ route('password.request') }}" class="font-semibold text-brand hover:underline">{{ __('Ask for a new one') }}</a>
    </p>
</x-layouts.auth>
