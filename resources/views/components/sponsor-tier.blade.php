@props(['tier', 'size' => 'md'])

@php
    // One look per tier, used in the console and on the public page alike.
    $styles = [
        'platinum' => 'bg-ink text-white',
        'gold' => 'bg-accent text-ink',
        'silver' => 'border border-line-strong bg-surface-2 text-muted',
    ];

    $sizes = [
        'sm' => 'px-2 py-0.5 text-[9.5px] gap-1',
        'md' => 'px-2.5 py-1 text-[10.5px] gap-1.5',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center rounded-full font-mono font-bold uppercase tracking-[0.1em] '.($styles[$tier] ?? $styles['silver']).' '.($sizes[$size] ?? $sizes['md'])]) }}>
    <svg viewBox="0 0 12 12" class="{{ $size === 'sm' ? 'size-2.5' : 'size-3' }}" aria-hidden="true">
        <path d="M6 .8l1.5 3.2 3.5.4-2.6 2.4.7 3.4L6 8.5 2.9 10.2l.7-3.4L1 4.4l3.5-.4z" fill="currentColor" />
    </svg>
    {{ __(\App\Models\Sponsor::TIERS[$tier] ?? ucfirst($tier)) }}
</span>
