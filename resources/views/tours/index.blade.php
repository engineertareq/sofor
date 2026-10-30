@php use App\Support\Money; @endphp

<x-layouts.app :title="__('Organised tours')" :active="'tours'" :heading="__('Organised tours')"
               :subtitle="__('Sell seats, collect payments and show everyone an honest account.')">

    <x-slot:actions>
        @if ($tours->isNotEmpty())
            <x-btn :href="route('tours.create')" size="sm" icon="plus">{{ __('New tour') }}</x-btn>
        @endif
    </x-slot:actions>

    @if ($tours->isEmpty())
        {{-- What organiser mode is, for someone who has not tried it --}}
        <section class="overflow-hidden rounded-4xl border border-line bg-surface shadow-soft">
            <div class="h-24 sm:h-28" style="background-image: {{ \App\Models\Trip::COVERS['sunset'] }}"></div>
            <div class="-mt-9 px-5 pb-6 sm:px-8 sm:pb-8">
                <span class="flex size-16 items-center justify-center rounded-3xl border-[1.5px] border-line bg-surface shadow-soft">
                    <x-icon name="flag" class="size-7 text-brand" />
                </span>
                <h2 class="mt-4 font-display text-[22px] font-semibold leading-tight text-ink sm:text-[26px]">{{ __('Run a tour, not a spreadsheet') }}</h2>
                <p class="mt-2 max-w-xl text-[14px] leading-relaxed text-muted">
                    {{ __('For tour groups, university clubs and office picnics. People book from one link and pay you directly - Sofor keeps track of every seat and every taka.') }}
                </p>

                <ol class="mt-6 grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['icon' => 'pencil', 'title' => __('Set it up'), 'text' => __('Dates, meeting point, seats and packages with their prices.')],
                        ['icon' => 'share', 'title' => __('Share one link'), 'text' => __('People book in a minute and pay your bKash or Nagad directly.')],
                        ['icon' => 'check-circle', 'title' => __('Stay on top'), 'text' => __('Confirm payments, log costs and show everyone the account.')],
                    ] as $i => $step)
                        <li class="rounded-3xl border border-line bg-surface-2 p-4">
                            <span class="flex items-center gap-2">
                                <span class="flex size-8 items-center justify-center rounded-xl bg-brand-tint text-brand"><x-icon :name="$step['icon']" class="size-4" /></span>
                                <span class="num text-[11px] font-bold text-faint">{{ $i + 1 }}</span>
                            </span>
                            <p class="mt-3 font-display text-[15px] font-semibold text-ink">{{ $step['title'] }}</p>
                            <p class="mt-1 text-[12.5px] leading-relaxed text-muted">{{ $step['text'] }}</p>
                        </li>
                    @endforeach
                </ol>

                <x-btn :href="route('tours.create')" icon="plus" class="mt-6">{{ __('Create your first tour') }}</x-btn>
            </div>
        </section>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($tours as $tour)
                @php
                    $seats = (int) $tour->seats_booked;
                    $received = (float) $tour->paid_in - (float) $tour->paid_out;
                @endphp
                <a href="{{ route('tours.show', $tour) }}"
                   class="group flex flex-col overflow-hidden rounded-4xl border border-line bg-surface shadow-soft transition hover:-translate-y-0.5 hover:border-line-strong hover:shadow-lift">
                    <div class="relative h-16" style="background-image: {{ $tour->coverStyle() }}">
                        <x-badge :tone="$tour->status->tone()" size="sm" class="absolute right-3 top-3">{{ $tour->status->label() }}</x-badge>
                    </div>
                    <div class="flex flex-1 flex-col p-4">
                        <h3 class="line-clamp-2 font-display text-[16px] font-semibold leading-snug text-ink group-hover:text-brand">{{ $tour->title }}</h3>
                        <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[12px] text-muted">
                            @if ($tour->starts_on)
                                <span class="flex items-center gap-1"><x-icon name="calendar" class="size-3.5 text-faint" />{{ $tour->starts_on->translatedFormat('j M') }}@if ($tour->ends_on && ! $tour->ends_on->isSameDay($tour->starts_on)) - {{ $tour->ends_on->translatedFormat('j M') }}@endif</span>
                            @endif
                            @if ($tour->destination)
                                <span class="flex min-w-0 items-center gap-1"><x-icon name="map-pin" class="size-3.5 shrink-0 text-faint" /><span class="truncate">{{ $tour->destination }}</span></span>
                            @endif
                        </p>

                        <div class="mt-auto pt-4">
                            <div class="flex items-center justify-between text-[12px]">
                                <span class="font-semibold text-ink">
                                    {{ $tour->capacity ? __(':booked of :capacity seats', ['booked' => $seats, 'capacity' => $tour->capacity]) : trans_choice(':count seat booked|:count seats booked', $seats, ['count' => $seats]) }}
                                </span>
                                <span class="num text-muted">{{ Money::format($received, $tour->currency) }}</span>
                            </div>
                            @if ($tour->capacity)
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-2">
                                    <div class="h-full rounded-full bg-brand" style="width: {{ min(100, round($seats / max(1, $tour->capacity) * 100)) }}%"></div>
                                </div>
                            @endif
                            @if ($tour->pending_count > 0)
                                <p class="mt-2.5 inline-flex items-center gap-1.5 rounded-full bg-warning-tint px-2.5 py-1 text-[11px] font-semibold text-warning">
                                    <x-icon name="wallet" class="size-3.5" />
                                    {{ trans_choice(':count payment to confirm|:count payments to confirm', $tour->pending_count, ['count' => $tour->pending_count]) }}
                                </p>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
