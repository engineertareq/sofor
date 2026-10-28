@props(['tone' => 'brand', 'size' => 'md', 'icon' => null])

@php
    // Legacy tone names stay mapped so existing call sites keep working while
    // the palette behind them moves to the Sofor colours.
    $tones = [
        'brand' => 'bg-brand-tint text-brand',
        'accent' => 'bg-accent-tint text-accent-2',
        'positive' => 'bg-positive-tint text-positive',
        'negative' => 'bg-negative-tint text-negative',
        'warning' => 'bg-warning-tint text-warning',
        'info' => 'bg-info-tint text-info',
        'muted' => 'bg-surface-2 text-muted',

        // aliases
        'lime' => 'bg-positive-tint text-positive',
        'slate' => 'bg-surface-2 text-muted',
        'amber' => 'bg-warning-tint text-warning',
        'rose' => 'bg-negative-tint text-negative',
        'violet' => 'bg-accent-tint text-accent-2',
        'sky' => 'bg-info-tint text-info',
    ];

    $sizes = [
        'sm' => 'px-2 py-0.5 text-[10px] gap-1',
        'md' => 'px-2.5 py-1 text-[10.5px] gap-1',
        'lg' => 'px-3 py-1.5 text-[11.5px] gap-1.5',
    ];

    $iconSizes = ['sm' => 'size-3', 'md' => 'size-3.5', 'lg' => 'size-4'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full font-mono font-bold uppercase tracking-[0.09em] '.($tones[$tone] ?? $tones['brand']).' '.($sizes[$size] ?? $sizes['md'])]) }}>
    @if ($icon)
        <x-icon :name="$icon" class="{{ $iconSizes[$size] ?? $iconSizes['md'] }} shrink-0" />
    @endif
    {{ $slot }}
</span>
