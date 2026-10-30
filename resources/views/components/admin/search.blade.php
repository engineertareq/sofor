{{-- The console's search box. Slim, but 16px text on a phone so iPhones do
     not zoom in on it, and it keeps the page's other filters (`keep`). --}}
@props(['value' => '', 'placeholder' => 'Search', 'keep' => []])

<form method="GET" role="search" {{ $attributes->merge(['class' => 'relative']) }}>
    @foreach ($keep as $name => $kept)
        @if ($kept !== null && $kept !== '')
            <input type="hidden" name="{{ $name }}" value="{{ $kept }}">
        @endif
    @endforeach

    <label for="console-search" class="sr-only">{{ $placeholder }}</label>
    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-faint" />
    <input id="console-search" type="search" name="q" value="{{ $value }}" placeholder="{{ $placeholder }}"
           class="form-field h-9 rounded-[10px] py-0 pl-8 pr-3 text-[16px] sm:h-8 sm:text-[12.5px]">
</form>
