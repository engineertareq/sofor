@props(['eyebrow' => null, 'heading' => null, 'text' => null, 'center' => false, 'level' => 2])

@php
    $tag = 'h'.$level;
@endphp

<div {{ $attributes->merge(['class' => 'max-w-2xl'.($center ? ' mx-auto text-center' : '')]) }}>
    @if ($eyebrow)
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif

    @if ($heading)
        <{{ $tag }} class="mt-3 font-display text-[1.75rem] font-semibold leading-[1.1] tracking-tight text-ink sm:text-[2.25rem]">
            {{ $heading }}
        </{{ $tag }}>
    @endif

    @if ($text)
        <p class="mt-3.5 text-[15px] leading-relaxed text-muted">{{ $text }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-6 {{ $center ? 'flex justify-center' : '' }}">{{ $slot }}</div>
    @endif
</div>
