@php
    use App\Support\Money;

    $totals = $rows->groupBy('currency')->map(fn ($group) => [
        'owe' => abs($group->where('net', '<', 0)->sum('net')),
        'owed' => $group->where('net', '>', 0)->sum('net'),
    ]);
@endphp

<x-layouts.app title="{{ __('Balances') }}" :active="'balances'" :heading="__('Balances')"
               :subtitle="__('Where you stand with each friend, over every trip you shared.')" :back="route('dashboard')">

    <div class="flex flex-col gap-6">
        {{-- ===== Totals ===== --}}
        <div class="grid gap-3 sm:grid-cols-2">
            @forelse ($totals as $currency => $total)
                <x-stat :label="__('You owe').($totals->count() > 1 ? ' ('.$currency.')' : '')" :value="Money::format($total['owe'], $currency)" icon="trending-down" tone="rose"
                        :hint="trans_choice(':count friend|:count friends', $owe->where('currency', $currency)->count())" />
                <x-stat :label="__('Owed to you').($totals->count() > 1 ? ' ('.$currency.')' : '')" :value="Money::format($total['owed'], $currency)" icon="trending-up" tone="lime"
                        :hint="trans_choice(':count friend|:count friends', $owed->where('currency', $currency)->count())" />
            @empty
                <x-stat :label="__('You owe')" :value="Money::format(0)" icon="trending-down" tone="rose" :hint="__('Nobody')" />
                <x-stat :label="__('Owed to you')" :value="Money::format(0)" icon="trending-up" tone="lime" :hint="__('Nobody')" />
            @endforelse
        </div>

        {{-- ===== Settle ups waiting on someone ===== --}}
        @foreach ($pending as $batch => $items)
            @php
                $first = $items->first();
                $recordedByMe = $first->recorded_by === auth()->id();
                $friend = $first->from?->user_id === auth()->id() ? $first->to?->user : $first->from?->user;
                $canAnswer = auth()->user()->can('confirmSettlement', [$first->trip, $first]);
            @endphp
            <div class="rounded-4xl border-[1.5px] border-warning/40 bg-warning-tint p-4">
                <p class="text-[13px] font-semibold text-ink">
                    @if ($recordedByMe)
                        {{ __('Waiting for :name to confirm your settle up', ['name' => $friend?->name ?? __('your friend')]) }}
                    @else
                        {{ trans_choice(':name settled up with you across :count trip|:name settled up with you across :count trips', $items->pluck('trip_id')->unique()->count(), ['name' => $friend?->name ?? __('A friend')]) }}
                    @endif
                </p>
                <p class="mt-0.5 text-[11px] text-muted">{{ $first->note }}</p>
                <ul class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($items as $item)
                        <li class="rounded-full bg-surface px-2.5 py-1 text-[11px] text-ink hairline">
                            {{ $item->trip?->title }}: {{ $item->from?->firstName() }} &rarr; {{ $item->to?->firstName() }} {{ Money::format($item->amount, $item->trip?->currency ?? 'BDT') }}
                        </li>
                    @endforeach
                </ul>
                @if ($canAnswer)
                    <div class="mt-3 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('trips.settlements.confirm', [$first->trip, $first]) }}">
                            @csrf
                            <x-btn type="submit" size="xs" icon="check">{{ __('Yes, I got it') }}</x-btn>
                        </form>
                        <form method="POST" action="{{ route('trips.settlements.reject', [$first->trip, $first]) }}"
                              onsubmit="return confirm('Take this settle up off every trip?')">
                            @csrf
                            <x-btn type="submit" size="xs" variant="ghost">{{ __('I did not get it') }}</x-btn>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach

        @if ($rows->isEmpty())
            <x-card>
                <x-empty icon="check-circle" title="{{ __('All square') }}" tone="lime"
                         :text="__('Nobody owes you and you owe nobody, on any trip. When a trip leaves something open, it shows up here per friend.')" />
            </x-card>
        @else
            <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
                @foreach ([[__('You owe'), $owe, 'negative'], [__('Owed to you'), $owed, 'positive']] as [$heading, $group, $tone])
                    @if ($group->isNotEmpty())
                        <x-card padding="p-4 sm:p-5">
                            <h2 class="font-display text-sm font-semibold">{{ $heading }}</h2>
                            <ul class="mt-3 flex flex-col gap-3">
                                @foreach ($group as $row)
                                    @include('balances.partials.friend', ['row' => $row, 'tone' => $tone])
                                @endforeach
                            </ul>
                        </x-card>
                    @endif
                @endforeach
            </div>

            @if ($even->isNotEmpty())
                <x-card padding="p-4 sm:p-5">
                    <h2 class="font-display text-sm font-semibold">{{ __('Evens out') }}</h2>
                    <p class="text-[11px] text-faint">{{ __('You owe each other the same across trips - settle up to clear every trip at once.') }}</p>
                    <ul class="mt-3 flex flex-col gap-3">
                        @foreach ($even as $row)
                            @include('balances.partials.friend', ['row' => $row, 'tone' => 'muted'])
                        @endforeach
                    </ul>
                </x-card>
            @endif
        @endif
    </div>
</x-layouts.app>
