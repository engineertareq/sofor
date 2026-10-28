@props(['trip', 'balance' => null])

@php
    use App\Support\Money;

    $b = $balance ?? ['due' => 0.0, 'credit' => 0.0, 'share' => 0.0];
    $logged = (float) ($trip->expenses_sum_amount ?? 0);
    $members = (int) ($trip->members_count ?? $trip->members->count());
    $ended = $trip->isEnded();
    $due = (float) ($b['due'] ?? 0);
    $credit = (float) ($b['credit'] ?? 0);

    $dates = $trip->starts_on
        ? $trip->starts_on->translatedFormat('d M').($trip->ends_on ? ' - '.$trip->ends_on->translatedFormat('d M Y') : '')
        : __('Dates not set yet');
@endphp

<a href="{{ route('trips.show', $trip) }}"
   class="group flex flex-col rounded-4xl border-[1.5px] border-line bg-surface shadow-soft transition duration-300 hover:-translate-y-1 hover:border-line-strong hover:shadow-lift">

    {{-- Cover: a light travel-journal band with the trip's icon acting as a stamp. --}}
    <div class="relative h-[74px] shrink-0 overflow-hidden rounded-t-4xl" style="background-image: {{ $trip->coverStyle() }}">
        <div aria-hidden="true" class="absolute inset-0 opacity-40"
             style="background-image: radial-gradient(circle, color-mix(in oklab, #17211f 16%, transparent) 1px, transparent 1.3px); background-size: 14px 14px;"></div>

        <span class="absolute left-4 top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-2xl bg-surface/85 shadow-soft backdrop-blur-sm">
            <x-glyph :name="$trip->icon" class="size-8" />
        </span>

        <span class="absolute right-4 top-4">
            @if ($ended)
                <span class="rounded-full bg-surface/85 px-2.5 py-1 font-mono text-[10px] font-bold uppercase tracking-[0.09em] text-muted backdrop-blur-sm">{{ __('Ended') }}</span>
            @else
                <span class="flex items-center gap-1.5 rounded-full bg-surface/85 px-2.5 py-1 font-mono text-[10px] font-bold uppercase tracking-[0.09em] text-positive backdrop-blur-sm">
                    <span class="size-1.5 rounded-full bg-positive"></span> {{ __('Live') }}
                </span>
            @endif
        </span>
    </div>

    <div class="flex flex-1 flex-col p-4">
        <h3 class="truncate font-display text-[17px] font-semibold leading-tight text-ink">{{ $trip->title }}</h3>

        <p class="mt-1 flex items-center gap-1.5 truncate text-[12.5px] text-muted">
            <x-icon name="map-pin" class="size-3.5 shrink-0 text-faint" />
            {{ $trip->destination ?: __('Somewhere good') }}
        </p>
        <p class="mt-0.5 flex items-center gap-1.5 truncate text-[12px] text-faint">
            <x-icon name="calendar" class="size-3.5 shrink-0" />
            {{ $dates }}
        </p>

        <div class="perf" data-on="surface"></div>

        <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-[12px]">
            <div>
                <dt class="text-faint">{{ __('Logged') }}</dt>
                <dd class="num font-semibold text-ink">{{ Money::format($logged, $trip->currency) }}</dd>
            </div>
            <div>
                <dt class="text-faint">{{ __('Your share') }}</dt>
                <dd class="num font-semibold text-ink">{{ Money::format($b['share'] ?? 0, $trip->currency) }}</dd>
            </div>
        </dl>

        <div class="mt-3.5 flex items-center justify-between gap-2">
            <span @class([
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold',
                'bg-positive-tint text-positive' => $credit > 0.009,
                'bg-negative-tint text-negative' => $due > 0.009,
                'bg-surface-2 text-muted' => $credit <= 0.009 && $due <= 0.009,
            ])>
                @if ($credit > 0.009)
                    <x-icon name="trending-up" class="size-3.5" />
                    {{ __('Owed :amount', ['amount' => Money::format($credit, $trip->currency)]) }}
                @elseif ($due > 0.009)
                    <x-icon name="trending-down" class="size-3.5" />
                    {{ __('You owe :amount', ['amount' => Money::format($due, $trip->currency)]) }}
                @else
                    <x-icon name="check-circle" class="size-3.5" />
                    {{ __('Settled up') }}
                @endif
            </span>

            <span class="flex items-center gap-1.5 text-[11px] font-semibold text-muted">
                <x-icon name="users" class="size-3.5" />
                <span class="num">{{ $members }}</span>
                <x-icon name="chevron-right" class="size-3.5 transition group-hover:translate-x-0.5" />
            </span>
        </div>
    </div>
</a>
