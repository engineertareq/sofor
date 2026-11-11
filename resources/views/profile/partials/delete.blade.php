{{-- Deleting the account: what it does, then the password to confirm (AccountDeletion). --}}
<x-card>
    <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
        <x-icon name="trash" class="size-4 text-negative" /> {{ __('Delete account') }}
    </h2>

    <p class="mt-2 text-sm text-muted">
        {{ __('This removes your name, email, phone, photo, payout numbers and travel map for good. Trips you shared with other people stay for them, with your name shown as "Deleted traveller".') }}
        <a href="{{ route('account.delete') }}" class="font-semibold text-brand hover:underline">{{ __('What exactly is deleted?') }}</a>
    </p>

    <details class="mt-4" @if ($errors->has('delete_password') || $errors->has('account')) open @endif>
        <summary class="cursor-pointer text-sm font-semibold text-negative">{{ __('I want to delete my account') }}</summary>

        <form method="POST" action="{{ route('profile.destroy') }}" class="mt-4 grid gap-4">
            @csrf
            @method('DELETE')

            @error('account')
                <p class="rounded-xl bg-negative/10 px-3 py-2 text-sm text-negative">{{ $message }}</p>
            @enderror

            <x-field name="delete_password" for="delete-password" :label="__('Your password, to confirm')" required>
                <x-input name="delete_password" id="delete-password" type="password" icon="lock" required autocomplete="current-password" />
            </x-field>

            <div class="flex justify-end">
                <x-btn type="submit" variant="danger" icon="trash">{{ __('Delete my account for good') }}</x-btn>
            </div>
        </form>
    </details>
</x-card>
