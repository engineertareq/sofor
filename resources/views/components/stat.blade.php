@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'brand', 'trend' => null])

@php
    $tones = [
        'brand' => 'text-brand bg-brand-tint',
        'accent' => 'text-accent-2 bg-accent-tint',
        'positive' => 'text-positive bg-positive-tint',
        'negative' => 'text-negative bg-negative-tint',
        'warning' => 'text-warning bg-warning-tint',
        'info' => 'text-info bg-info-tint',
        'muted' => 'text-muted bg-surface-2',

        // aliases kept for existing call sites
        'lime' => 'text-positive bg-positive-tint',
        'amber' => 'text-warning bg-warning-tint',
        'rose' => 'text-negative bg-negative-tint',
        'violet' => 'text-accent-2 bg-accent-tint',
        'sky' => 'text-info bg-info-tint',
        'slate' => 'text-muted bg-surface-2',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'glass-card min-w-0 p-3.5 sm:p-5']) }}>
    <div class="flex items-start justify-between gap-2">
        <p class="eyebrow min-w-0 line-clamp-2 max-[359px]:tracking-[0.08em]">{{ \App\Support\Lang::t($label) }}</p>
        @if ($icon)
            <span class="flex size-8 shrink-0 items-center justify-center rounded-xl sm:size-9 {{ $tones[$tone] ?? $tones['brand'] }}">
                <x-icon :name="$icon" class="size-4 sm:size-4.5" />
            </span>
        @endif
    </div>

    <p class="num mt-2.5 truncate font-display text-[19px] font-semibold leading-none tracking-tight text-ink min-[360px]:text-[22px] sm:mt-3 sm:text-[26px]">{{ $value }}</p>

    @if ($hint || $trend)
        <p class="mt-2 flex items-start gap-1.5 text-[11px] leading-snug text-muted sm:text-xs">
            @if ($trend === 'up')
                <x-icon name="trending-up" class="mt-0.5 size-3.5 shrink-0 text-positive" />
            @elseif ($trend === 'down')
                <x-icon name="trending-down" class="mt-0.5 size-3.5 shrink-0 text-negative" />
            @endif
            <span class="min-w-0 line-clamp-2">{{ \App\Support\Lang::t($hint) }}</span>
        </p>
    @endif

    {{ $slot }}
</div>
