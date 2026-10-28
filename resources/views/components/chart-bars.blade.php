@props(['series' => [], 'height' => 'h-40', 'tone' => 'brand'])

@php
    $bars = collect($series);
    $max = max(1, (int) $bars->max('value'));

    $tones = [
        'brand' => 'bg-brand',
        'accent' => 'bg-accent',
        'violet' => 'bg-accent-2',
        'positive' => 'bg-positive',
    ];
    $fill = $tones[$tone] ?? $tones['brand'];
@endphp

<div {{ $attributes }}>
    <div class="flex {{ $height }} items-end gap-[3px] sm:gap-2">
        @foreach ($bars as $index => $bar)
            @php
                $value = (int) ($bar['value'] ?? 0);
                $pct = $value > 0 ? max(4, round($value / $max * 100)) : 2;
                $isPeak = $value === $max && $value > 0;
            @endphp

            <div class="group relative flex h-full flex-1 flex-col justify-end" title="{{ $bar['label'] ?? '' }}: {{ $value }}">
                <div @class([
                        'w-full rounded-t-lg transition-all duration-500 ease-out',
                        'opacity-100' => $isPeak,
                        $fill => $isPeak,
                        'bg-line-strong/70 group-hover:bg-brand/60' => ! $isPeak,
                     ])
                     style="height: {{ $pct }}%"></div>
            </div>
        @endforeach
    </div>

    @if ($bars->isNotEmpty())
        <div class="mt-2.5 flex gap-[3px] sm:gap-2">
            @foreach ($bars as $bar)
                <span class="flex-1 truncate text-center font-mono text-[9px] uppercase tracking-wide text-faint">
                    {{ $bar['short'] ?? $bar['label'] ?? '' }}
                </span>
            @endforeach
        </div>
    @endif
</div>
