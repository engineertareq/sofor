<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ __('Sofor - split trips, not friendships.') }}<br>
© {{ date('Y') }} Sofor. {{ __('You are getting this email because this address was used on Sofor.') }} [{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'sofor.io' }}]({{ config('app.url') }})
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
