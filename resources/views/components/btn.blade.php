@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconAfter' => null,
    'type' => 'button',
    'block' => false,
    'title' => null,
    'sticker' => null,
])

@php
    // Tailwind emits `.inline-flex` *after* `.hidden` in the compiled CSS, so a caller
    // passing `class="hidden sm:inline-flex"` would never actually hide the button.
    // When the caller supplies its own `hidden` utility we drop the base display class
    // so their visibility rules win (responsive variants still apply as expected).
    $callerClass = (string) ($attributes->get('class') ?? '');
    $display = str_contains($callerClass, 'hidden') ? '' : 'inline-flex ';

    $base = $display.'items-center justify-center gap-2 font-semibold transition duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:cursor-not-allowed disabled:opacity-50';

    $sizes = [
        'xs' => 'h-8 px-3 text-xs rounded-full',
        'sm' => 'h-9 px-4 text-[13px] rounded-full',
        'md' => 'h-11 px-5 text-sm rounded-full',
        'lg' => 'h-12 px-6 text-[15px] rounded-full',
        'xl' => 'h-14 px-7 text-base rounded-full',
        'icon' => 'size-10 rounded-2xl',
    ];

    $variants = [
        'primary' => 'bg-brand text-brand-ink hover:bg-brand-2',
        'accent' => 'bg-accent text-ink hover:bg-accent-2 hover:text-white',
        'secondary' => 'bg-surface-2 text-ink hairline hover:bg-canvas-tint',
        'ghost' => 'text-muted hover:text-ink hover:bg-surface-2',
        'outline' => 'bg-surface text-ink hairline hover:border-ink/30 hover:bg-surface-2',
        'danger' => 'bg-negative text-white hover:brightness-95',
        'success' => 'bg-positive text-white hover:brightness-95',
        'warn' => 'bg-warning text-white hover:brightness-95',
        'glass' => 'glass text-ink hairline hover:border-line-strong',
    ];

    // The hard offset shadow is the house style. It only earns its keep on the
    // solid buttons, so outline and ghost stay flat.
    $sticker ??= in_array($variant, ['primary', 'accent', 'danger', 'success'], true);

    $classes = trim(
        $base
        .' '.($sizes[$size] ?? $sizes['md'])
        .' '.($variants[$variant] ?? $variants['primary'])
        .($sticker ? ' sticker' : '')
        .($block ? ' w-full' : '')
    );
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($title) title="{{ $title }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="size-4 shrink-0" />
        @endif
        {{ $slot }}
        @if ($iconAfter)
            <x-icon :name="$iconAfter" class="size-4 shrink-0" />
        @endif
    </a>
@else
    <button type="{{ $type }}" @if ($title) title="{{ $title }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="size-4 shrink-0" />
        @endif
        {{ $slot }}
        @if ($iconAfter)
            <x-icon :name="$iconAfter" class="size-4 shrink-0" />
        @endif
    </button>
@endif
