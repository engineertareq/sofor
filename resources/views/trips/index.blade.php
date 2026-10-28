@php
    $tracked = $trips->sum(fn ($trip) => (float) ($trip->expenses_sum_amount ?? 0));
    $totals = $portfolio['totals'];
@endphp

<x-layouts.app title="{{ __('My trips') }}" :active="'trips'" :heading="__('My trips')"
               :subtitle="__('Every ledger you lead or travel in, newest activity first.')">

    <x-slot:actions>
        <x-btn :href="route('trips.create')" size="sm" icon="plus" class="hidden sm:inline-flex">{{ __('New trip') }}</x-btn>
    </x-slot:actions>

    <div class="flex flex-col gap-6">
        {{-- Summary --}}
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat :label="__('Trips')" :value="$counts['all']" icon="compass" tone="brand" :hint="__(':live live - :ended ended', ['live' => $counts['active'], 'ended' => $counts['ended']])" />
            <x-stat :label="__('Tracked here')" :value="\App\Support\Money::compact($tracked)" icon="receipt" tone="accent" :hint="__('Costs on this page')" />
            <x-stat :label="__('You owe')" :value="\App\Support\Money::format($totals['due'])" icon="trending-down" tone="rose" :hint="__('Across all trips')" />
            <x-stat :label="__('Owed to you')" :value="\App\Support\Money::format($totals['credit'])" icon="trending-up" tone="lime" :hint="__('Across all trips')" />
        </section>

        {{-- Filters --}}
        <section class="glass-card p-4">
            <form method="GET" action="{{ route('trips.index') }}" class="flex flex-col gap-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-1 rounded-2xl border border-line bg-surface-2 p-1">
                        @foreach (['all' => __('All'), 'active' => __('Live'), 'ended' => __('Ended')] as $key => $label)
                            <a href="{{ request()->fullUrlWithQuery(['filter' => $key, 'page' => null]) }}"
                               @class([
                                   'rounded-xl px-3.5 py-2 text-[13px] font-semibold transition',
                                   'bg-brand text-white shadow-sticker-sm' => $filters['filter'] === $key,
                                   'text-muted hover:text-ink' => $filters['filter'] !== $key,
                               ])>{{ $label }}</a>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-1 rounded-2xl border border-line bg-surface-2 p-1">
                        @foreach (['all' => __('Anyone'), 'owned' => __('I lead'), 'joined' => __('I joined')] as $key => $label)
                            <a href="{{ request()->fullUrlWithQuery(['role' => $key, 'page' => null]) }}"
                               @class([
                                   'rounded-xl px-3.5 py-2 text-[13px] font-semibold transition',
                                   'bg-surface text-ink shadow-soft' => $filters['role'] === $key,
                                   'text-muted hover:text-ink' => $filters['role'] !== $key,
                               ])>{{ $label }}</a>
                        @endforeach
                    </div>

                    <div class="sm:ml-auto sm:w-72">
                        <div class="relative">
                            <x-input name="q" icon="search" placeholder="{{ __('Search trips or destinations') }}" :value="$filters['q']" />
                        </div>
                    </div>
                </div>
            </form>
        </section>

        {{-- Grid --}}
        @if ($trips->isEmpty())
            <x-empty icon="compass" title="{{ __('No trips match this view') }}"
                     :text="__('Try another filter, or start a fresh trip and Sofor will split every cost for you.')">
                <x-btn :href="route('trips.create')" size="sm" icon="plus">{{ __('Start a trip') }}</x-btn>
            </x-empty>
        @else
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($trips as $trip)
                    <x-trip-card :trip="$trip" :balance="$portfolio['trips'][$trip->id] ?? null" />
                @endforeach
            </section>

            {{ $trips->links() }}
        @endif
    </div>
</x-layouts.app>
