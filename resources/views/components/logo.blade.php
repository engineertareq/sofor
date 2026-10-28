@props(['size' => 'md', 'showTagline' => false, 'compact' => false])

@php
    $sizes = [
        'sm' => ['mark' => 'size-8 rounded-xl', 'icon' => 'size-4', 'text' => 'text-[15px]'],
        'md' => ['mark' => 'size-10 rounded-2xl', 'icon' => 'size-5', 'text' => 'text-lg'],
        'lg' => ['mark' => 'size-12 rounded-2xl', 'icon' => 'size-6', 'text' => 'text-2xl'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];

    // `compact` keeps only the mark on very narrow screens so the wordmark never
    // squeezes the header actions off a 320px viewport.
    $wordmark = $compact ? 'hidden sm:flex flex-col leading-none' : 'flex flex-col leading-none';

    // A logo uploaded in Admin -> Settings replaces the built in mark and wordmark.
    $uploaded = app(\App\Services\BrandingService::class)->logoUrl();

    // An uploaded logo has no separate mark to fall back to, so on narrow
    // screens `compact` shrinks it instead. Written out in full so Tailwind
    // finds every class.
    $imageSizes = [
        'sm' => ['h-7 max-w-[14rem]', 'h-6 max-w-[7.5rem] sm:h-7 sm:max-w-[14rem]'],
        'md' => ['h-9 max-w-[14rem]', 'h-6 max-w-[7.5rem] sm:h-9 sm:max-w-[14rem]'],
        'lg' => ['h-11 max-w-[14rem]', 'h-6 max-w-[7.5rem] sm:h-11 sm:max-w-[14rem]'],
    ];
    $imageSize = ($imageSizes[$size] ?? $imageSizes['md'])[$compact ? 1 : 0];
@endphp

@if ($uploaded)
<span {{ $attributes->merge(['class' => 'inline-flex flex-col items-start leading-none']) }}>
    <img src="{{ $uploaded }}" alt="{{ config('app.name', 'Sofor') }}" class="{{ $imageSize }} w-auto object-contain object-left">
    @if ($showTagline)
        <span class="mt-2 font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-faint">{{ __('Split trips, not friendships') }}</span>
    @endif
</span>
@else
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="relative flex {{ $s['mark'] }} shrink-0 items-center justify-center bg-brand text-brand-ink">
        {{-- A dotted route with a marigold destination: the whole product in one mark. --}}
        <svg viewBox="0 0 24 24" fill="none" class="{{ $s['icon'] }}" aria-hidden="true">
            <path d="M4 18.5c2.4-5.4 4.3-8.1 6.6-8.1 2.2 0 3 2.6 4.6 2.6 1.2 0 2-.9 2.4-2.1"
                  stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-dasharray="2.6 2.6" />
            <circle cx="4" cy="18.5" r="2.1" fill="currentColor" />
            <circle cx="17.6" cy="10.9" r="2.6" fill="var(--accent)" stroke="#fff" stroke-width="1.4" />
        </svg>
    </span>

    <span class="{{ $wordmark }}">
        <span class="font-display {{ $s['text'] }} font-semibold tracking-tight text-ink">{{ __('Sofor') }}</span>
        @if ($showTagline)
            <span class="mt-1 font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-faint">{{ __('Split trips, not friendships') }}</span>
        @endif
    </span>
</span>
@endif
