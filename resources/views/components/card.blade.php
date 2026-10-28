@props(['padding' => 'p-5 sm:p-6', 'glass' => true, 'hover' => false, 'as' => 'div', 'receipt' => false])

@php
    $classes = trim(
        ($receipt ? 'receipt ' : ($glass ? 'glass-card ' : 'bg-surface hairline rounded-4xl '))
        .$padding
        .' '
        .($hover ? 'transition duration-300 hover:-translate-y-1 hover:border-line-strong hover:shadow-lift' : '')
    );
@endphp

<{{ $as }} {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
    </{{ $as }}>
