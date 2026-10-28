@props(['value' => 0, 'tone' => 'brand', 'size' => 'md', 'label' => null])

@php
    $tones = [
        'brand' => 'bg-brand',
        'positive' => 'bg-positive',
        'negative' => 'bg-negative',
        'warning' => 'bg-warning',
        'accent' => 'bg-accent',
        // aliases
        'lime' => 'bg-positive',
        'amber' => 'bg-warning',
        'violet' => 'bg-accent-2',
    ];

    $heights = ['sm' => 'h-1.5', 'md' => 'h-2.5'];
    $width = max(0, min(100, (float) $value));
@endphp

<div {{ $attributes }}>
    @if ($label)
        <div class="mb-2 flex items-center justify-between text-xs">
            <span class="text-muted">{{ $label }}</span>
            <span class="num font-semibold text-ink">{{ \App\Support\Money::percent($width) }}</span>
        </div>
    @endif

    <div class="w-full overflow-hidden rounded-full bg-surface-2 {{ $heights[$size] ?? $heights['md'] }}">
        <div class="h-full rounded-full {{ $tones[$tone] ?? $tones['brand'] }} transition-[width] duration-700 ease-out"
             style="width: {{ $width }}%"></div>
    </div>
</div>
