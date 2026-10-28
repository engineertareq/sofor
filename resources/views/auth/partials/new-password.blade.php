{{-- New password and its confirmation, with one show/hide toggle. Expects `show` in the form's x-data. --}}
<x-field name="password" :label="__('New password')" :hint="__('At least 8 characters with letters and numbers.')" required>
    <div class="relative">
        <x-input name="password" icon="lock" x-bind:type="show ? 'text' : 'password'"
                 placeholder="{{ __('Create a new password') }}" autocomplete="new-password" required />
        <button type="button" @click="show = !show"
                class="absolute right-3 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-faint transition hover:text-ink"
                aria-label="{{ __('Toggle password visibility') }}">
            <x-icon name="eye" class="size-4" x-show="!show" />
            <x-icon name="eye-off" class="hidden size-4" x-show="show" />
        </button>
    </div>
</x-field>

<x-field name="password_confirmation" :label="__('Confirm new password')" required>
    <x-input name="password_confirmation" icon="shield" x-bind:type="show ? 'text' : 'password'"
             placeholder="{{ __('Repeat the new password') }}" autocomplete="new-password" required />
</x-field>
