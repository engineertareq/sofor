@php
    // Shown by maintenance mode (Admin -> Settings -> Maintenance), by
    // `php artisan down`, and as a preview from the console.
    $maintenance = app(\App\Services\MaintenanceMode::class);
    $message = $maintenance->message();
    $preview = $preview ?? false;
@endphp

<x-layouts.error title="{{ __('Back soon') }}" :refresh="$preview ? null : 60">
    @if ($preview)
        <div class="mb-8 flex items-center gap-2 rounded-full border border-warning/30 bg-warning-tint px-4 py-1.5 text-[11px] font-semibold text-warning">
            <x-icon name="eye" class="size-3.5" /> {{ __('Preview - this is what visitors see while maintenance mode is on') }}
        </div>
    @endif

    <span class="sticker flex size-20 items-center justify-center rounded-4xl border-[1.5px] border-ink bg-accent text-ink">
        <x-icon name="settings" class="size-9 animate-[spin_6s_linear_infinite] motion-reduce:animate-none" />
    </span>

    <p class="eyebrow mt-8 text-accent-2">{{ __('Down for maintenance') }}</p>
    <h1 class="mt-3 font-display text-[2rem] font-semibold leading-tight tracking-tight sm:text-5xl">
        {{ __('Sofor is getting a tune-up') }}
    </h1>
    <p class="mt-4 max-w-md text-[14px] leading-relaxed text-muted">
        {{ __('We are making a few improvements and will be back shortly. Your trips, costs and balances are safe.') }}
    </p>

    @if ($message)
        <div class="mt-6 max-w-md rounded-3xl border border-line bg-surface px-5 py-4 text-left">
            <p class="eyebrow text-faint">{{ __('A note from the team') }}</p>
            <p class="mt-1.5 whitespace-pre-line text-[13px] leading-relaxed text-ink">{{ $message }}</p>
        </div>
    @endif

    <p class="mt-6 flex items-center gap-2 text-[11px] text-faint">
        <span class="size-1.5 animate-pulse rounded-full bg-warning"></span>
        {{ __('This page checks again every minute.') }}
    </p>

    @guest
        {{-- The sign in screen stays open so an administrator can always get back in. --}}
        <a href="{{ route('login') }}" class="mt-10 text-[11px] font-medium text-faint underline decoration-dotted underline-offset-2 transition hover:text-ink">
            {{ __('Administrator sign in') }}
        </a>
    @endguest
</x-layouts.error>
