@props(['icon' => 'sparkles', 'title', 'text' => null, 'tone' => 'brand'])

@php
    $tones = [
        'brand' => 'bg-brand-tint text-brand',
        'accent' => 'bg-accent-tint text-accent-2',
        'positive' => 'bg-positive-tint text-positive',
        // aliases
        'lime' => 'bg-positive-tint text-positive',
        'amber' => 'bg-warning-tint text-warning',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-3.5 rounded-4xl border-2 border-dashed border-line-strong px-6 py-12 text-center']) }}>
    <span class="flex size-14 rotate-[-3deg] items-center justify-center rounded-2xl {{ $tones[$tone] ?? $tones['brand'] }}">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <div>
        <p class="font-display text-lg font-semibold text-ink">{{ \App\Support\Lang::t($title) }}</p>
        @if ($text)
            <p class="mx-auto mt-1.5 max-w-sm text-[13.5px] leading-relaxed text-muted">{{ \App\Support\Lang::t($text) }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div class="mt-1 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
