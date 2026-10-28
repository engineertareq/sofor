@php
    use App\Support\Money;

    $net = $row['net'];
    $iOwe = $net < -0.009;
    $amount = Money::format(abs($net), $row['currency']);
    $first = \Illuminate\Support\Str::before($row['name'], ' ');
@endphp

<li class="rounded-3xl border border-line bg-surface-2 p-3.5" x-data="{ open: false, settle: false }">
    <div class="flex items-center justify-between gap-3">
        <button type="button" @click="open = ! open" class="flex min-w-0 items-center gap-3 text-left" :aria-expanded="open">
            <x-avatar :name="$row['name']" :src="$row['user']?->avatarUrl() ?? $row['member']->avatarUrl()" size="sm" />
            <span class="min-w-0">
                <span class="block truncate text-[13px] font-semibold text-ink">{{ $row['name'] }}</span>
                <span class="block text-[11px] text-faint">
                    {{ trans_choice(':open open on :count trip|:open open on :count trips', collect($row['items'])->pluck('trip.id')->unique()->count(), ['open' => count($row['items'])]) }}
                    @unless ($row['user']) &middot; {{ __('no Sofor account') }} @endunless
                </span>
            </span>
        </button>

        <span class="text-right">
            <span @class(['num block font-display text-[15px] font-semibold', 'text-negative' => $tone === 'negative', 'text-positive' => $tone === 'positive', 'text-ink' => $tone === 'muted'])>{{ $amount }}</span>
            @if ($row['nettable'])
                <button type="button" @click="settle = ! settle" class="text-[11px] font-semibold text-brand hover:underline">
                    {{ $iOwe ? __('Settle up') : ($net > 0.009 ? __('Mark received') : __('Clear all')) }}
                </button>
            @endif
        </span>
    </div>

    {{-- Per trip --}}
    <ul x-show="open" x-cloak class="mt-3 flex flex-col gap-1.5 border-t border-line pt-3">
        @foreach ($row['items'] as $item)
            <li class="flex items-center justify-between gap-3 text-[12px]">
                <a href="{{ route('trips.show', $item['trip']) }}#settle" class="flex min-w-0 items-center gap-1.5 text-muted hover:text-ink">
                    <x-glyph :name="$item['trip']->icon" class="size-4.5 shrink-0" />
                    <span class="truncate">{{ $item['trip']->title }}</span>
                </a>
                <span class="shrink-0 {{ $item['you_owe'] ? 'text-negative' : 'text-positive' }}">
                    {{ $item['you_owe'] ? __('You owe') : __('Owes you') }} <span class="num font-semibold">{{ Money::format($item['amount'], $row['currency']) }}</span>
                </span>
            </li>
        @endforeach
    </ul>

    {{-- Settle everything with one payment --}}
    @if ($row['nettable'])
        <form x-show="settle" x-cloak method="POST" action="{{ route('balances.settle', $row['user']) }}" class="mt-3 flex flex-col gap-3 border-t border-line pt-3">
            @csrf
            <input type="hidden" name="currency" value="{{ $row['currency'] }}">

            <p class="text-[12px] leading-relaxed text-ink">
                @if ($iOwe)
                    {!! __('Pay :name :amount once, with your own bKash, Nagad or bank, and every trip between you is settled. :name confirms it arrived.', ['name' => e($first), 'amount' => '<span class="font-semibold">'.e($amount).'</span>']) !!}
                @elseif ($net > 0.009)
                    {!! __('Got :amount from :name? Mark it received and every trip between you is settled at once.', ['name' => e($first), 'amount' => '<span class="font-semibold">'.e($amount).'</span>']) !!}
                @else
                    {{ __('What you owe each other cancels out. Clear it and every trip between you is square.') }}
                @endif
            </p>

            @if ($iOwe)
                <x-payout-chips :member="$row['member']" />
            @endif

            <div class="grid gap-2 sm:grid-cols-2">
                <x-select name="method" :id="'method-'.md5($row['key'])" :options="$methods" :value="$iOwe ? 'bkash' : 'cash'" aria-label="{{ __('How was it paid?') }}" />
                <x-input name="reference" :id="'reference-'.md5($row['key'])" maxlength="80" placeholder="{{ __('Transaction ID (optional)') }}" aria-label="{{ __('Transaction ID') }}" />
            </div>

            <x-btn type="submit" size="sm" icon="check" class="self-end">
                {{ $iOwe ? __('I paid :name :amount', ['name' => $first, 'amount' => $amount]) : ($net > 0.009 ? __('Mark :amount received', ['amount' => $amount]) : __('Clear all trips')) }}
            </x-btn>
        </form>
    @endif
</li>
