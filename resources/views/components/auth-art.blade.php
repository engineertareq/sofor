{{-- The night sky behind the sign-in and sign-up forms: Bangladesh at night,
     a few favourite trip spots softly lit, and a marigold route that draws
     itself from Rajshahi to Cox's Bazar - the dotted route of Sofor's own
     mark. Decoration only: hidden from screen readers and never in the way
     of a tap. --}}
@php
    $map = app(\App\Services\TravelMaps::class)->get('bd');
    $areas = $map->areas();

    // Rajshahi, Dhaka, Sylhet, Rangamati (Sajek), Bandarban, Cox's Bazar.
    $route = array_values(array_filter(['BD-54', 'BD-13', 'BD-60', 'BD-56', 'BD-01', 'BD-11'], fn (string $id) => isset($areas[$id])));
    $lit = [...$route, 'BD-38', 'BD-61', 'BD-10', 'BD-05', 'BD-51'];

    // A smooth curve through each stop (Catmull-Rom), as on the home page.
    $points = array_map(fn (string $id) => [(float) $areas[$id]['cx'], (float) $areas[$id]['cy']], $route);
    $path = '';

    if ($points !== []) {
        $path = 'M'.$points[0][0].','.$points[0][1];

        for ($i = 0; $i < count($points) - 1; $i++) {
            [$p0, $p1, $p2, $p3] = [$points[$i - 1] ?? $points[$i], $points[$i], $points[$i + 1], $points[$i + 2] ?? $points[$i + 1]];
            $c1 = [round($p1[0] + ($p2[0] - $p0[0]) / 6, 1), round($p1[1] + ($p2[1] - $p0[1]) / 6, 1)];
            $c2 = [round($p2[0] - ($p3[0] - $p1[0]) / 6, 1), round($p2[1] - ($p3[1] - $p1[1]) / 6, 1)];
            $path .= " C{$c1[0]},{$c1[1]} {$c2[0]},{$c2[1]} {$p2[0]},{$p2[1]}";
        }
    }
@endphp

<div aria-hidden="true" {{ $attributes->merge(['class' => 'auth-art pointer-events-none select-none']) }}>
    <div class="relative">
        <x-travel-map :map="$map" :visited="$lit" :static="true" palette="night" tip-mode="plain" class="auth-map" />

        <svg viewBox="0 0 {{ $map->width() }} {{ $map->height() }}" class="absolute inset-0 size-full overflow-visible" fill="none">
            <defs>
                <mask id="auth-route-reveal" maskUnits="userSpaceOnUse">
                    <path d="{{ $path }}" stroke="#fff" stroke-width="14" stroke-linecap="round" pathLength="1" class="auth-route-mask" />
                </mask>
            </defs>

            {{-- The route: dashed, revealed as if drawn --}}
            <path id="auth-route" d="{{ $path }}" class="auth-route" mask="url(#auth-route-reveal)" />

            {{-- The stops pop in one after another; the last is the destination. --}}
            @foreach ($points as $index => [$x, $y])
                @php $last = $index === count($points) - 1; @endphp
                <g class="auth-stop" style="--stop-delay: {{ 0.5 + $index * 0.9 }}s">
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $last ? 22 : 16 }}" class="auth-stop-glow" />
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $last ? 9 : 6 }}" fill="#f6b23a" stroke="#fbf8f2" stroke-width="{{ $last ? 3 : 2.2 }}" />
                </g>
            @endforeach

            {{-- A traveller on the road, once it is drawn --}}
            <circle r="5" fill="#fbf8f2" opacity="0" class="auth-traveller">
                <animate attributeName="opacity" from="0" to="1" begin="6s" dur="0.6s" fill="freeze" />
                <animateMotion dur="9s" begin="6s" repeatCount="indefinite" rotate="auto" keyPoints="0;1" keyTimes="0;1" calcMode="linear">
                    <mpath href="#auth-route" />
                </animateMotion>
            </circle>
        </svg>
    </div>
</div>
