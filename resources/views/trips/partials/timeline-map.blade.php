{{--
    The trip on the map, for the cost timeline (TripTimeline::map): the region
    it covered at night, its places lit, a pin for each with the day it was
    reached, and the route between them. It follows the reader: the route
    draws on and today's places glow marigold as the timeline scrolls, and a
    pin takes you to its day. On wide screens the days are listed under it.

    Needs the tripTimeline Alpine scope (active, go, mark, reached).
--}}
@php
    $map = $timeline['map'];
    $unit = $map['unit'];
    $js = fn ($value) => \Illuminate\Support\Js::from($value);
@endphp

<div class="tl-map-card overflow-hidden rounded-[1.6rem] bg-ink text-canvas shadow-soft">
    <div class="flex items-center justify-between gap-3 px-4 pt-3.5">
        <p class="font-mono text-[10.5px] font-bold tracking-[0.14em] text-[#f6b23a]">{{ __('THE ROUTE') }}</p>
        <p class="text-[11.5px] text-canvas/60">{{ trans_choice(':count place|:count places', count($map['stops']), ['count' => \App\Support\Bangla::localize(count($map['stops']))]) }}</p>
    </div>

    <svg viewBox="{{ $map['viewBox'] }}" class="tl-map mt-1 block h-auto w-full" role="img" aria-label="{{ __('Map of the places this trip went') }}"
         style="--u: {{ $unit }}px">
        @foreach ($map['areas'] as $area)
            <path d="{{ $area['d'] }}" @class(['tl-map-area', 'is-lit' => $area['lit']])
                  @if ($area['days'] !== []) :class="{ 'is-now': {{ $js($area['days']) }}.includes(active) }" @endif></path>
        @endforeach

        @if ($map['route'])
            {{-- The whole route faintly, and on top of it the part travelled so far --}}
            <path d="{{ $map['route'] }}" class="tl-map-route-base" style="stroke-width: {{ $unit * 0.9 }}; stroke-dasharray: {{ $unit * 0.4 }} {{ $unit * 2.2 }}"></path>
            <path d="{{ $map['route'] }}" class="tl-map-route" pathLength="1" style="stroke-width: {{ $unit * 1.1 }}"
                  :style="`stroke-dashoffset: ${1 - mark(active)}`"></path>
        @endif

        @foreach ($map['stops'] as $stop)
            <g @class(['tl-map-stop', 'is-static' => $stop['day'] === null])
               @if ($stop['day']) role="button" tabindex="0" aria-label="{{ $stop['name'] }}" @click="go('{{ $stop['day'] }}')" @keydown.enter="go('{{ $stop['day'] }}')"
                  :class="{ 'is-now': {{ $js($stop['days']) }}.includes(active), 'is-past': reached('{{ $stop['day'] }}') }" @endif
               transform="translate({{ round($stop['x'], 1) }} {{ round($stop['y'], 1) }})">
                <circle r="{{ $unit * 5.2 }}" class="tl-map-halo"></circle>
                <circle r="{{ $unit * 3.3 }}" class="tl-map-dot" style="stroke-width: {{ $unit * 0.7 }}"></circle>
                @if ($stop['number'] !== null)
                    <text y="{{ $unit * 1.35 }}" text-anchor="middle" class="tl-map-number" style="font-size: {{ $unit * 3.8 }}px">{{ \App\Support\Bangla::localize($stop['number']) }}</text>
                @endif
                <text y="{{ -$unit * 5 }}" text-anchor="middle" class="tl-map-name" style="font-size: {{ $unit * 5 }}px; stroke-width: {{ $unit * 1.2 }}px">{{ $stop['name'] }}</text>
            </g>
        @endforeach
    </svg>

    {{-- Wide screens: the days, as the map's legend --}}
    <nav class="hidden border-t border-canvas/10 p-2 lg:block" aria-label="{{ __('Days') }}">
        <ol class="flex max-h-[34vh] flex-col gap-0.5 overflow-y-auto">
            @foreach ($timeline['timeline'] as $day)
                <li>
                    <button type="button" @click="go('{{ $day['key'] }}')" x-show="dayVisible('{{ $day['key'] }}')"
                            :class="active === '{{ $day['key'] }}' ? 'bg-canvas text-ink' : 'text-canvas hover:bg-canvas/10'"
                            class="flex w-full items-center gap-3 rounded-2xl px-2.5 py-2 text-left transition">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[13px] font-semibold">{{ $dayTitle($day) }}</span>
                            <span class="block truncate text-[11px] opacity-60">{{ $day['date']->translatedFormat('D, j M') }}</span>
                        </span>
                        <span class="shrink-0 text-[12px] font-semibold">{{ $money($day['total']) }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>
</div>
