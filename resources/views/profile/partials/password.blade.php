<x-card>
    <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
        <x-icon name="lock" class="size-4 text-brand" /> {{ __('Change password') }}
    </h2>

    <form method="POST" action="{{ route('profile.password') }}" class="mt-5 grid gap-4">
        @csrf
        @method('PUT')

        <x-field name="current_password" :label="__('Current password')" required>
            <x-input name="current_password" type="password" icon="lock" required autocomplete="current-password" />
        </x-field>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="password" :label="__('New password')" required>
                <x-input name="password" type="password" icon="shield" required autocomplete="new-password" />
            </x-field>
            <x-field name="password_confirmation" :label="__('Confirm new password')" required>
                <x-input name="password_confirmation" type="password" icon="shield" required autocomplete="new-password" />
            </x-field>
        </div>

        <div class="flex justify-end">
            <x-btn type="submit" variant="secondary" icon="lock">{{ __('Update password') }}</x-btn>
        </div>
    </form>
</x-card>
