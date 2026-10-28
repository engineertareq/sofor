{{-- The username box used on sign up and in profile settings. Pass
     `suggest-from` with the id of a name input to offer a username from it. --}}
@props(['value' => '', 'suggestFrom' => null])

@php
    $hasError = $errors->has('username');
    $config = [
        'value' => (string) $value,
        'url' => route('username.check'),
        'suggestFrom' => $suggestFrom,
        'current' => auth()->user()?->username,
    ];
@endphp

<div x-data="usernameField({{ \Illuminate\Support\Js::from($config) }})">
    <div class="relative">
        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[14px] font-semibold text-faint">@</span>
        <input id="username" name="username" type="text" value="{{ $value }}" x-model="username" @input="typed()"
               required minlength="3" maxlength="30" placeholder="{{ __('yourname') }}"
               autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="username-status"
               @if ($hasError) aria-invalid="true" @endif
               class="form-field pl-8 pr-10 {{ $hasError ? 'form-field-error' : '' }}">
        <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2">
            <x-icon name="check-circle" class="size-4 text-positive" x-show="status === 'ok'" x-cloak />
            <x-icon name="alert" class="size-4 text-negative" x-show="status === 'bad'" x-cloak />
        </span>
    </div>

    <p id="username-status" class="mt-1.5 text-xs" aria-live="polite">
        <span x-show="message" x-cloak x-text="message" :class="status === 'ok' ? 'text-positive' : 'text-negative'"></span>
        <span x-show="! message" class="text-faint">
            {{ __('Your public profile:') }} <span translate="no">{{ request()->getHttpHost() }}/@</span><span x-text="username || 'yourname'">{{ $value ?: 'yourname' }}</span>
        </span>
    </p>
</div>
