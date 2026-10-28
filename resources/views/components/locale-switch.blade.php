{{-- বাংলা / English, Bangla first. One tap; remembered on the account when
     signed in. The thumb slides across while the page reloads in the new
     language, and the language already showing is not a submit button.
     `short` labels it বাং / EN, for the site header. --}}
@props(['short' => false])

@php
    $current = app()->getLocale();
    $names = \App\Http\Middleware\SetLocale::LOCALES;
    $codes = array_values(array_unique(['bn', ...array_keys($names)]));
    $abbreviations = ['bn' => 'বাং', 'en' => 'EN'];
@endphp

<form method="POST" action="{{ route('locale') }}" x-data data-on="{{ (int) array_search($current, $codes, true) }}" aria-label="{{ __('Language') }}"
      {{ $attributes->merge(['class' => 'locale-switch inline-grid shrink-0 grid-cols-2 whitespace-nowrap rounded-full border border-line bg-surface-2 p-0.5 font-semibold '.($short ? 'text-[12px]' : 'text-[11.5px]')]) }}>
    @csrf
    <span class="locale-switch-thumb" aria-hidden="true"></span>
    @foreach ($codes as $i => $code)
        <button type="{{ $code === $current ? 'button' : 'submit' }}" name="locale" value="{{ $code }}" lang="{{ $code }}" data-i="{{ $i }}"
                aria-pressed="{{ $code === $current ? 'true' : 'false' }}" @click="$el.form.dataset.on = '{{ $i }}'"
                @if ($short) aria-label="{{ $names[$code] }}" title="{{ $names[$code] }}" @endif
                class="{{ $short ? 'px-2 py-1.5 min-[400px]:px-2.5' : 'px-3 py-1' }} whitespace-nowrap rounded-full text-center transition-colors">{{ $short ? $abbreviations[$code] ?? $names[$code] : $names[$code] }}</button>
    @endforeach
</form>
