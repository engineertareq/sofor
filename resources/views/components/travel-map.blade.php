{{-- One travel map as inline SVG, visited areas filled. Used by the traveller's
     own map and the public map maker (interactive, wired up with Alpine
     attributes passed in), by the public profile and the home page (`static`)
     and by Explorers, which passes `weights` (area => 0..1) to shade a heat map.
     `palette="night"` draws it for a dark background: land in deep teal, heat
     rising to marigold.

     Every place carries its name and division or continent, and `tips` can add
     a line of its own (area => text). resources/js/map-tips.js shows them in a
     label beside the pointer or finger. `tip-mode` "state" adds whether the
     place is visited; "plain" shows the name only. --}}
@props(['map', 'visited' => [], 'static' => false, 'weights' => null, 'tips' => [], 'palette' => 'paper', 'tipMode' => null])

@php
    $lookup = array_flip($visited);
    $dotRadius = round($map->width() / 280, 1);
    $night = $palette === 'night';
    $shade = fn (string $id) => $weights !== null && ($weights[$id] ?? 0) > 0
        ? ($night
            ? 'fill: color-mix(in oklab, #f6b23a '.(14 + (int) round($weights[$id] * 86)).'%, #2b6158)'
            : 'fill: color-mix(in oklab, var(--brand) '.(18 + (int) round($weights[$id] * 82)).'%, #e6dfd0)')
        : null;
    $groups = $map->groups();
    $tipMode ??= match (true) {
        $weights !== null => 'plain',
        ! $static => 'state',
        default => $visited === [] ? 'plain' : 'state',
    };
    // Name, group and the extra line, ready to drop into an element.
    $label = fn (string $id, array $area) => 'data-name="'.e($map->name($id)).'" data-group="'.e($groups[$area['group']] ?? '').'"'
        .(isset($tips[$id]) ? ' data-tip="'.e($tips[$id]).'"' : '');
@endphp

<svg viewBox="0 0 {{ $map->width() }} {{ $map->height() }}" data-map-tips="{{ $tipMode }}" @unless ($static) data-map-interactive @endunless
     {{ $attributes->class(['h-auto w-full select-none', 'tm-static' => $static, 'tm-night' => $night]) }}>
    @foreach ($map->areas() as $id => $area)
        @if ($area['d'] !== '')
            <path data-area="{{ $id }}" {!! $label($id, $area) !!} d="{{ $area['d'] }}" @class(['tm-area', 'is-visited' => isset($lookup[$id])])
                  @if ($style = $shade($id)) style="{{ $style }}" @endif></path>
        @endif
    @endforeach
    @foreach ($map->areas() as $id => $area)
        @if (($area['tiny'] ?? false) && (isset($lookup[$id]) || ($weights[$id] ?? 0) > 0))
            <circle data-area="{{ $id }}" {!! $label($id, $area) !!} cx="{{ $area['cx'] }}" cy="{{ $area['cy'] }}" r="{{ $dotRadius }}"
                    class="tm-dot is-visited" @if ($style = $shade($id)) style="{{ $style }}" @endif></circle>
        @elseif ($area['tiny'] ?? false)
            <circle data-area="{{ $id }}" {!! $label($id, $area) !!} cx="{{ $area['cx'] }}" cy="{{ $area['cy'] }}" r="{{ $dotRadius }}" class="tm-dot"></circle>
        @endif
    @endforeach
</svg>
