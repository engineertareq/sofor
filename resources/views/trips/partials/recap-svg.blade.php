{{--
    The recap card: a night travel poster of the trip, 1080 x 1350. Bangladesh
    at night with the places the trip went glowing marigold and a dotted route
    flying in from Dhaka (Sofor's own mark), the total, an ink stamp once it
    is all settled, where the money went and the biggest splurge.

    Plain SVG with embedded or system fonts only: an SVG drawn onto a canvas
    cannot load the page's web fonts or any other file (see recapCard in app.js).
--}}
@php
    use App\Support\Glyphs;
    use App\Support\Money;

    $money = fn ($value) => Money::format(round((float) $value), $trip->currency);
    $sans = "'Sofor Taka', 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    $display = "'Sofor Taka', Poppins, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    $cream = '#fbf8f2';
    $gold = '#f6b23a';

    // ---- The map: Bangladesh scaled into the right-hand column.
    $map = $recap['map'];
    $scale = 0.68;
    [$mapX, $mapY] = [596, 150];
    $at = fn (string $id) => $map ? [$mapX + (float) $map->areas()[$id]['cx'] * $scale, $mapY + (float) $map->areas()[$id]['cy'] * $scale] : [0, 0];
    $lit = array_flip($recap['places']);
    $stops = $map ? array_map($at, $recap['places']) : [];
    $home = $map && $map->has('BD-13') ? $at('BD-13') : null;
    // The route flies in from Dhaka unless the trip was in Dhaka itself.
    $routeFrom = $home && ! isset($lit['BD-13']) ? $home : null;
    $route = '';
    if ($map && $stops !== []) {
        $points = $routeFrom ? [$routeFrom, ...$stops] : $stops;
        $route = 'M'.round($points[0][0], 1).','.round($points[0][1], 1);
        for ($i = 1; $i < count($points); $i++) {
            [$x1, $y1] = $points[$i - 1];
            [$x2, $y2] = $points[$i];
            // A gentle arc: the control point sits off the middle of each leg.
            $cx = ($x1 + $x2) / 2 - ($y2 - $y1) * 0.25;
            $cy = ($y1 + $y2) / 2 + ($x2 - $x1) * 0.25;
            $route .= ' Q'.round($cx, 1).','.round($cy, 1).' '.round($x2, 1).','.round($y2, 1);
        }
    }

    // ---- The title, left of the map.
    $titleY = 362;
    $afterTitle = $titleY + (count($recap['title_lines']) - 1) * 80;

    // ---- Where it went: a donut, the rest of the money as one grey slice.
    $palette = [$gold, '#3fbfa8', '#ef7b5a', '#a99cf5'];
    $rows = array_slice($recap['categories'], 0, 4);
    $shown = array_sum(array_column($rows, 'percent'));
    $radius = 66;
    $circumference = 2 * M_PI * $radius;
    $slices = [];
    $offset = 0;
    foreach ($rows as $index => $row) {
        $slices[] = ['color' => $palette[$index], 'percent' => $row['percent'], 'offset' => $offset];
        $offset += $row['percent'];
    }
    if ($rows !== [] && $shown < 99.5) {
        $slices[] = ['color' => '#4a5a56', 'percent' => 100 - $shown, 'offset' => $offset];
    }

    $total = $money($recap['spent']);
    $totalSize = mb_strlen($total) > 9 ? 96 : (mb_strlen($total) > 7 ? 112 : 128);
    $totalY = 792;
@endphp
{{-- The card is a shareable image and its fonts carry Latin only, so it stays in English with Latin digits (translate="no"). --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1350" width="1080" height="1350" role="img" translate="no"
     aria-label="{{ $trip->title }} trip recap" @isset($svgClass) class="{{ $svgClass }}" @endisset>
    <defs>
        <linearGradient id="recap-night" x1="0" y1="0" x2="0.35" y2="1">
            <stop offset="0" stop-color="#173430" />
            <stop offset="0.55" stop-color="#12201d" />
            <stop offset="1" stop-color="#0d1715" />
        </linearGradient>
        <radialGradient id="recap-teal" cx="0.85" cy="0.18" r="0.55">
            <stop offset="0" stop-color="#0f6f6a" stop-opacity="0.55" />
            <stop offset="1" stop-color="#0f6f6a" stop-opacity="0" />
        </radialGradient>
        <radialGradient id="recap-gold" cx="0.05" cy="0.95" r="0.5">
            <stop offset="0" stop-color="{{ $gold }}" stop-opacity="0.22" />
            <stop offset="1" stop-color="{{ $gold }}" stop-opacity="0" />
        </radialGradient>
        <pattern id="recap-stars" width="30" height="30" patternUnits="userSpaceOnUse">
            <circle cx="3" cy="3" r="1.4" fill="{{ $cream }}" fill-opacity="0.13" />
        </pattern>
        <filter id="recap-glow" x="-50%" y="-50%" width="200%" height="200%">
            <feGaussianBlur stdDeviation="9" />
        </filter>
    </defs>

    {{-- The sky --}}
    <rect width="1080" height="1350" fill="url(#recap-night)" />
    <rect width="1080" height="1350" fill="url(#recap-stars)" />
    <rect width="1080" height="1350" fill="url(#recap-teal)" />
    <rect width="1080" height="1350" fill="url(#recap-gold)" />

    {{-- Header: Sofor's mark, and what this is --}}
    <g transform="translate(72 66)">
        <rect width="64" height="64" rx="18" fill="#0f6f6a" />
        <g transform="translate(5.3 5.3) scale(2.2)" fill="none">
            <path d="M4 18.5c2.4-5.4 4.3-8.1 6.6-8.1 2.2 0 3 2.6 4.6 2.6 1.2 0 2-.9 2.4-2.1" stroke="#fff" stroke-width="1.9" stroke-linecap="round" stroke-dasharray="2.6 2.6" />
            <circle cx="4" cy="18.5" r="2.1" fill="#fff" />
            <circle cx="17.6" cy="10.9" r="2.6" fill="{{ $gold }}" stroke="#fff" stroke-width="1.4" />
        </g>
    </g>
    <text x="154" y="111" font-family="{{ $display }}" font-size="38" font-weight="700" fill="{{ $cream }}">Sofor</text>
    <rect x="806" y="72" width="202" height="52" rx="26" fill="none" stroke="{{ $cream }}" stroke-opacity="0.3" stroke-width="2" />
    <text x="907" y="106" text-anchor="middle" font-family="{{ $sans }}" font-size="20" font-weight="700" letter-spacing="4" fill="{{ $cream }}" fill-opacity="0.85">TRIP RECAP</text>

    {{-- The map: where the trip went, lit, with the route flying in --}}
    @if ($map)
        <g transform="translate({{ $mapX }} {{ $mapY }}) scale({{ $scale }})">
            @foreach ($map->areas() as $id => $area)
                @if ($area['d'] !== '')
                    <path d="{{ $area['d'] }}" fill="{{ isset($lit[$id]) ? $gold : '#1f3a35' }}" stroke="#0d1715" stroke-width="1.5" />
                @endif
            @endforeach
            {{-- A soft glow under the places it went --}}
            <g filter="url(#recap-glow)" opacity="0.75">
                @foreach ($recap['places'] as $id)
                    @if (($map->areas()[$id]['d'] ?? '') !== '')
                        <path d="{{ $map->areas()[$id]['d'] }}" fill="{{ $gold }}" />
                    @endif
                @endforeach
            </g>
        </g>

        @if ($route !== '')
            <path d="{{ $route }}" fill="none" stroke="{{ $cream }}" stroke-width="5" stroke-linecap="round" stroke-dasharray="2 13" />
        @endif
        @if ($routeFrom)
            <circle cx="{{ round($routeFrom[0], 1) }}" cy="{{ round($routeFrom[1], 1) }}" r="9" fill="{{ $cream }}" />
        @endif
        @foreach ($stops as $index => [$x, $y])
            <circle cx="{{ round($x, 1) }}" cy="{{ round($y, 1) }}" r="26" fill="{{ $gold }}" fill-opacity="0.25" />
            <circle cx="{{ round($x, 1) }}" cy="{{ round($y, 1) }}" r="13" fill="{{ $gold }}" stroke="{{ $cream }}" stroke-width="5" />
        @endforeach
        {{-- The first place named beside its pin --}}
        @php [$labelX, $labelY] = $stops[0]; @endphp
        <text x="{{ round(min(max($labelX, 700), 940), 1) }}" y="{{ round($labelY - 36, 1) }}" text-anchor="middle"
              font-family="{{ $display }}" font-size="28" font-weight="700" fill="{{ $cream }}"
              stroke="#0d1715" stroke-width="8" stroke-linejoin="round" paint-order="stroke">{{ $map->name($recap['places'][0]) }}</text>
    @else
        {{-- Somewhere the map does not show: the trip's own picture, glowing --}}
        <circle cx="802" cy="420" r="190" fill="{{ $gold }}" fill-opacity="0.08" />
        <circle cx="802" cy="420" r="130" fill="{{ $cream }}" />
        <g transform="translate(722 340) scale(4)">{!! Glyphs::inner($trip->icon) !!}</g>
    @endif

    {{-- Who and when --}}
    <circle cx="112" cy="238" r="40" fill="{{ $cream }}" />
    <g transform="translate(88 214) scale(1.2)">{!! Glyphs::inner($trip->icon) !!}</g>
    @foreach ($recap['title_lines'] as $index => $line)
        <text x="72" y="{{ $titleY + $index * 80 }}" font-family="{{ $display }}" font-size="70" font-weight="700" letter-spacing="-1.5" fill="{{ $cream }}">{{ $line }}</text>
    @endforeach
    @if ($recap['destination'])
        <text x="74" y="{{ $afterTitle + 58 }}" font-family="{{ $sans }}" font-size="28" fill="{{ $cream }}" fill-opacity="0.72">{{ mb_strimwidth($recap['destination'], 0, 30, '...') }}</text>
    @endif
    @if ($recap['dates'])
        <text x="74" y="{{ $afterTitle + ($recap['destination'] ? 98 : 58) }}" font-family="{{ $sans }}" font-size="28" fill="{{ $cream }}" fill-opacity="0.72">{{ $recap['dates'] }}</text>
    @endif
    @php
        $chips = array_values(array_filter([
            $recap['days'] ? $recap['days'].' '.($recap['days'] === 1 ? 'DAY' : 'DAYS') : null,
            $recap['travellers'].' '.($recap['travellers'] === 1 ? 'TRAVELLER' : 'TRAVELLERS'),
        ]));
        $chipY = $afterTitle + ($recap['destination'] && $recap['dates'] ? 132 : ($recap['destination'] || $recap['dates'] ? 92 : 52));
        $chipX = 72;
    @endphp
    @foreach ($chips as $chip)
        @php $chipWidth = 44 + mb_strlen($chip) * 15; @endphp
        <rect x="{{ $chipX }}" y="{{ $chipY }}" width="{{ $chipWidth }}" height="48" rx="24" fill="{{ $cream }}" fill-opacity="0.1" stroke="{{ $cream }}" stroke-opacity="0.25" stroke-width="2" />
        <text x="{{ $chipX + $chipWidth / 2 }}" y="{{ $chipY + 32 }}" text-anchor="middle" font-family="{{ $sans }}" font-size="19" font-weight="700" letter-spacing="2.5" fill="{{ $cream }}">{{ $chip }}</text>
        @php $chipX += $chipWidth + 12; @endphp
    @endforeach

    {{-- The big number --}}
    <text x="74" y="{{ $totalY }}" font-family="{{ $sans }}" font-size="23" font-weight="700" letter-spacing="5" fill="{{ $gold }}">TOTAL SPENT TOGETHER</text>
    <text x="68" y="{{ $totalY + $totalSize * 0.92 }}" font-family="{{ $display }}" font-size="{{ $totalSize }}" font-weight="700" letter-spacing="-3" fill="{{ $cream }}">{{ $total }}</text>
    <text x="74" y="{{ $totalY + $totalSize * 0.92 + 56 }}" font-family="{{ $sans }}" font-size="30" fill="{{ $cream }}" fill-opacity="0.78">
        <tspan font-weight="700" fill-opacity="1">{{ $money($recap['per_person']) }}</tspan> each{{ $recap['per_day'] ? '  ·  ' : '' }}@if ($recap['per_day'])<tspan font-weight="700" fill-opacity="1">{{ $money($recap['per_day']) }}</tspan> a day each @endif
    </text>

    {{-- An ink stamp once nobody owes anybody --}}
    @if ($recap['settled'] && $recap['spent'] > 0)
        <g transform="rotate(-14 900 860)" opacity="0.92">
            <circle cx="900" cy="860" r="88" fill="none" stroke="{{ $gold }}" stroke-width="5" />
            <circle cx="900" cy="860" r="74" fill="none" stroke="{{ $gold }}" stroke-width="2" stroke-dasharray="4 6" />
            <text x="900" y="842" text-anchor="middle" font-family="{{ $sans }}" font-size="22" font-weight="800" letter-spacing="4" fill="{{ $gold }}">ALL</text>
            <text x="900" y="874" text-anchor="middle" font-family="{{ $sans }}" font-size="26" font-weight="800" letter-spacing="3" fill="{{ $gold }}">SETTLED</text>
            <path d="M882 893 l12 12 l24 -24" fill="none" stroke="{{ $gold }}" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" />
        </g>
    @endif

    <line x1="72" y1="1036" x2="1008" y2="1036" stroke="{{ $cream }}" stroke-opacity="0.18" stroke-width="2" stroke-dasharray="8 10" />

    {{-- Where it went --}}
    @if ($rows !== [])
        <g transform="rotate(-90 160 1150)">
            <circle cx="160" cy="1150" r="{{ $radius }}" fill="none" stroke="{{ $cream }}" stroke-opacity="0.08" stroke-width="30" />
            @foreach ($slices as $slice)
                @php $length = max(0.1, $slice['percent'] / 100 * $circumference - (count($slices) > 1 ? 5 : 0)); @endphp
                <circle cx="160" cy="1150" r="{{ $radius }}" fill="none" stroke="{{ $slice['color'] }}" stroke-width="30"
                        stroke-dasharray="{{ round($length, 2) }} {{ round($circumference, 2) }}" stroke-dashoffset="{{ round(-$slice['offset'] / 100 * $circumference, 2) }}" />
            @endforeach
        </g>
        <text x="160" y="1146" text-anchor="middle" font-family="{{ $display }}" font-size="34" font-weight="700" fill="{{ $cream }}">{{ $recap['costs'] }}</text>
        <text x="160" y="1174" text-anchor="middle" font-family="{{ $sans }}" font-size="17" font-weight="700" letter-spacing="2" fill="{{ $cream }}" fill-opacity="0.6">{{ $recap['costs'] === 1 ? 'COST' : 'COSTS' }}</text>

        <text x="268" y="1080" font-family="{{ $sans }}" font-size="19" font-weight="700" letter-spacing="4" fill="{{ $cream }}" fill-opacity="0.6">WHERE IT WENT</text>
        @foreach ($rows as $index => $row)
            @php $rowY = 1112 + $index * 40; @endphp
            <circle cx="276" cy="{{ $rowY - 7 }}" r="8" fill="{{ $palette[$index] }}" />
            <text x="296" y="{{ $rowY }}" font-family="{{ $sans }}" font-size="23" font-weight="600" fill="{{ $cream }}">{{ $row['category']->label() }}</text>
            <text x="590" y="{{ $rowY }}" text-anchor="end" font-family="{{ $sans }}" font-size="23" font-weight="700" fill="{{ $cream }}" fill-opacity="0.85">{{ Money::percent($row['percent']) }}</text>
        @endforeach
    @else
        <text x="74" y="1150" font-family="{{ $sans }}" font-size="28" fill="{{ $cream }}" fill-opacity="0.6">No costs on this trip yet.</text>
    @endif

    {{-- The biggest splurge, as a sticker --}}
    @if ($recap['biggest'])
        <g transform="rotate(-4 830 1150)">
            <rect x="648" y="1078" width="360" height="144" rx="26" fill="{{ $gold }}" />
            <rect x="648" y="1078" width="360" height="144" rx="26" fill="none" stroke="#0d1715" stroke-width="3" />
            <text x="676" y="1120" font-family="{{ $sans }}" font-size="17" font-weight="800" letter-spacing="3.5" fill="#0d1715" fill-opacity="0.7">BIGGEST SPLURGE</text>
            <text x="676" y="1160" font-family="{{ $sans }}" font-size="25" font-weight="700" fill="#0d1715">{{ mb_strimwidth($recap['biggest']['title'], 0, 22, '...') }}</text>
            <text x="676" y="1200" font-family="{{ $display }}" font-size="30" font-weight="700" fill="#0d1715">{{ $money($recap['biggest']['amount']) }}</text>
        </g>
    @endif

    {{-- Footer --}}
    <text x="74" y="1298" font-family="{{ $sans }}" font-size="25" fill="{{ $cream }}" fill-opacity="0.7">Split fairly with <tspan font-weight="700" fill="{{ $gold }}" fill-opacity="1">Sofor</tspan></text>
    <text x="1006" y="1298" text-anchor="end" font-family="{{ $sans }}" font-size="25" font-weight="700" fill="{{ $cream }}">{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'sofor.io' }}</text>
</svg>
