{{-- The console's filter tabs: links that set one query value. Slim, and they
     scroll sideways rather than squeeze, so five still fit a phone. --}}
@props([
    // [value => label]
    'tabs',
    'current',
    'param' => 'status',
    // [value => count], shown beside the label
    'counts' => [],
    // The value whose count turns red while it is above zero.
    'urgent' => null,
    'label' => 'Filter',
])

<nav aria-label="{{ $label }}" {{ $attributes->merge(['class' => 'flex max-w-full items-center gap-0.5 overflow-x-auto rounded-xl border border-line bg-surface-2 p-0.5 no-scrollbar']) }}>
    @foreach ($tabs as $value => $text)
        @php
            $on = (string) $current === (string) $value;
            $alert = $value === $urgent && ($counts[$value] ?? 0) > 0;
        @endphp
        <a href="{{ request()->fullUrlWithQuery([$param => $value, 'page' => null]) }}" @if ($on) aria-current="page" @endif
           @class([
               'flex h-7 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-[9px] px-2.5 text-[12px] font-semibold transition',
               'bg-surface text-ink shadow-[0_1px_2px_rgb(24_24_27/0.1)]' => $on,
               'text-muted hover:text-ink' => ! $on,
           ])>
            {{ $text }}
            @if (array_key_exists($value, $counts))
                <span @class([
                    'num min-w-4 rounded-full px-1 text-center text-[10px] leading-4',
                    'bg-negative text-white' => $alert,
                    'bg-canvas-tint text-faint' => ! $alert,
                ])>{{ $counts[$value] }}</span>
            @endif
        </a>
    @endforeach
</nav>
