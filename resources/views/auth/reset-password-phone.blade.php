<x-layouts.auth title="{{ __('Reset by text') }}" :noindex="true" :heading="__('Enter the code we texted')"
                  :subtitle="__('If an account uses :phone, a 6 digit code is on its way to it. It works for 10 minutes.', ['phone' => \App\Support\Phone::pretty($phone)])"
                  :back="route('password.request')" :back-label="__('Use a different number')">

    <form method="POST" action="{{ route('password.phone.update') }}" class="flex flex-col gap-4" x-data="{ show: false, saving: false }" @submit="saving = true">
        @csrf

        <div>
            <p class="mb-1.5 text-[13px] font-medium text-muted">{{ __('Code from the text') }} <span class="text-negative">*</span></p>
            <x-code-input />
        </div>

        @include('auth.partials.new-password')

        <x-btn type="submit" size="lg" block icon="check" x-bind:disabled="saving">
            <span x-text="saving ? @js(__('Saving...')) : @js(__('Save new password'))">{{ __('Save new password') }}</span>
        </x-btn>
    </form>

    <form method="POST" action="{{ route('password.phone.resend') }}" class="mt-4 text-center" x-data="cooldown({{ $cooldown }})">
        @csrf
        <button type="submit" :disabled="left > 0"
                class="inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-[13px] font-semibold text-brand transition hover:bg-brand-tint disabled:cursor-not-allowed disabled:text-faint disabled:hover:bg-transparent">
            <x-icon name="refresh" class="size-3.5" />
            <span x-show="left === 0">{{ __('Text me a new code') }}</span>
            <span x-show="left > 0" x-cloak>{{ __('New code in') }} <span class="num" x-text="label"></span></span>
        </button>
    </form>
</x-layouts.auth>
