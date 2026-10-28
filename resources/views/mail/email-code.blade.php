{{-- The code that confirms an email address (Notifications\VerifyEmailAddress). --}}
<x-mail::message>
# {{ __('Hi :name,', ['name' => $name]) }}

{{ __('Here is the code to confirm your email on Sofor:') }}

<x-mail::code>{{ $code }}</x-mail::code>

{{ __('Type it on the page that asked for it. It works for :minutes minutes.', ['minutes' => $minutes]) }}

{{ __('Never share this code with anyone. Sofor will never ask you for it.') }}

{{ __('Did not sign up for Sofor? You can safely ignore this email.') }}

{{ __('Happy travels,') }}<br>
{{ __('The Sofor team') }}
</x-mail::message>
