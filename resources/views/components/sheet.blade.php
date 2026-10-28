@props(['title' => null, 'subtitle' => null, 'icon' => null, 'size' => 'md'])

@php
    $sizes = [
        'sm' => 'sm:max-w-md',
        'md' => 'sm:max-w-lg',
        'lg' => 'sm:max-w-2xl',
        'xl' => 'sm:max-w-3xl',
    ];
@endphp

{{-- Expects an Alpine scope with an `open` property; place inside <template x-teleport="body"> --}}
<div x-show="open" x-cloak class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-6"
     @keydown.escape.window="open = false">
    <div class="absolute inset-0 sheet-backdrop" x-show="open" x-transition.opacity.duration.200ms @click="open = false"></div>

    <div role="dialog" aria-modal="true"
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-8 opacity-0 sm:translate-y-4"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0 opacity-100"
         x-transition:leave-end="translate-y-8 opacity-0 sm:translate-y-4"
         class="relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-4xl border border-line bg-surface shadow-sheet sm:rounded-4xl {{ $sizes[$size] ?? $sizes['md'] }}">

        <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
            <div class="flex items-center gap-3">
                @if ($icon)
                    <span class="flex size-10 items-center justify-center rounded-2xl bg-brand-tint text-brand">
                        <x-icon :name="$icon" class="size-5" />
                    </span>
                @endif
                <div>
                    <h3 class="font-display text-base font-semibold text-ink">{{ \App\Support\Lang::t($title) }}</h3>
                    @if ($subtitle)
                        <p class="text-xs text-muted">{{ \App\Support\Lang::t($subtitle) }}</p>
                    @endif
                </div>
            </div>

            <button type="button" @click="open = false"
                    class="flex size-9 items-center justify-center rounded-xl text-faint transition hover:bg-surface-2 hover:text-ink"
                    aria-label="{{ __('Close') }}">
                <x-icon name="close" class="size-4.5" />
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-5 sm:px-6">
            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="border-t border-line px-5 py-4 sm:px-6">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
