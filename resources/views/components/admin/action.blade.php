{{-- A small button for a table row or a card. With text it is outlined; an
     icon on its own is bare, and `label` names it for screen readers. --}}
@props(['icon' => null, 'label' => null, 'tone' => 'default', 'href' => null, 'type' => 'submit'])

@php
    $bare = $slot->isEmpty();

    $tones = $bare
        ? [
            'default' => 'text-faint hover:bg-surface-2 hover:text-ink',
            'warning' => 'text-faint hover:bg-warning-tint hover:text-warning',
            'danger' => 'text-faint hover:bg-negative-tint hover:text-negative',
        ]
        : [
            'default' => 'text-muted hover:border-line-strong hover:text-ink',
            'warning' => 'text-muted hover:border-warning/40 hover:text-warning',
            'danger' => 'text-negative hover:border-negative/40 hover:bg-negative-tint',
        ];

    $classes = 'inline-flex h-7 shrink-0 items-center justify-center gap-1 rounded-lg text-[11.5px] font-semibold transition '
        .($bare ? 'w-7 ' : 'border border-line bg-surface px-2.5 ')
        .($tones[$tone] ?? $tones['default']);
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($label) aria-label="{{ $label }}" title="{{ $label }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-3.5" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @if ($label) aria-label="{{ $label }}" title="{{ $label }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-3.5" />@endif
        {{ $slot }}
    </button>
@endif
