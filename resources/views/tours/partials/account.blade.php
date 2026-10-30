{{-- A tour's account: what came in, what it cost, what is left (TourAccount).
     The organiser's dashboard shows it with the package sales and the switch
     that shares it ($forOrganiser); everyone's booking page shows the statement
     itself when that switch is on. The flag has its own name: an include sees the
     page's variables, and the booking page has an $organiser (a user) of its own. --}}
@php
    use App\Support\Money;

    $currency = $tour->currency;
    $forOrganiser ??= false;
    $paidIn = round($account['received'] + $account['refunded'], 2);
@endphp

<div @class(['grid gap-4', 'lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:items-start' => $forOrganiser])>
    <x-card padding="p-4 sm:p-5">
        <h3 class="font-display text-[15px] font-semibold text-ink">{{ __('Where the money went') }}</h3>

        <dl class="mt-4 flex flex-col text-[13px]">
            <div class="flex items-center justify-between py-1.5">
                <dt class="text-muted">{{ __('Payments received') }}</dt>
                <dd class="num font-semibold text-ink">{{ Money::format($paidIn, $currency) }}</dd>
            </div>
            @if ($account['refunded'] > 0)
                <div class="flex items-center justify-between py-1.5">
                    <dt class="text-muted">{{ __('Refunds sent') }}</dt>
                    <dd class="num font-semibold text-negative">-{{ Money::format($account['refunded'], $currency) }}</dd>
                </div>
            @endif

            <div class="mt-2 border-t border-dashed border-line pt-2">
                <dt class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-faint">{{ __('Costs') }}</dt>
                @forelse ($account['by_category'] as $row)
                    <div class="flex items-center justify-between py-1.5">
                        <span class="flex items-center gap-2 text-muted"><x-glyph :name="$row['category']->icon()" class="size-5" />{{ $row['category']->label() }}</span>
                        <span class="num font-semibold text-ink">-{{ Money::format($row['amount'], $currency) }}</span>
                    </div>
                @empty
                    <p class="py-1.5 text-[12.5px] text-faint">{{ __('No costs logged yet.') }}</p>
                @endforelse
            </div>

            <div @class([
                'mt-3 flex items-center justify-between rounded-2xl px-3.5 py-3',
                'bg-positive-tint' => $account['balance'] >= 0,
                'bg-negative-tint' => $account['balance'] < 0,
            ])>
                <dt class="font-semibold text-ink">{{ $account['balance'] >= 0 ? __('Left over') : __('Short by') }}</dt>
                <dd @class(['num font-display text-[20px] font-semibold', 'text-positive' => $account['balance'] >= 0, 'text-negative' => $account['balance'] < 0])>
                    {{ Money::format(abs($account['balance']), $currency) }}
                </dd>
            </div>
        </dl>

        @if ($forOrganiser && ($account['outstanding'] > 0 || $account['refunds_owed'] > 0))
            <p class="mt-3 text-[12.5px] leading-relaxed text-muted">
                {{ __('Once everyone pays the :due still due:', ['due' => Money::format($account['outstanding'], $currency)]) }}
                <span class="num font-semibold text-ink">{{ Money::format($account['projected'], $currency) }}</span>
                @if ($account['refunds_owed'] > 0)
                    {{ __('(after :refunds of refunds still owed to cancelled bookings)', ['refunds' => Money::format($account['refunds_owed'], $currency)]) }}
                @endif
            </p>
        @endif
    </x-card>

    @if ($forOrganiser)
        <div class="flex flex-col gap-4">
            <x-card padding="p-4 sm:p-5">
                <h3 class="font-display text-[15px] font-semibold text-ink">{{ __('Packages sold') }}</h3>
                <ul class="mt-3 divide-y divide-line text-[13px]">
                    @foreach ($account['packages'] as $row)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-ink">{{ $row['package']->name }}</span>
                                <span class="block text-[11.5px] text-muted">{{ Money::format($row['package']->price, $currency) }} · {{ trans_choice(':count booking|:count bookings', $row['bookings'], ['count' => $row['bookings']]) }}</span>
                            </span>
                            <span class="num shrink-0 font-semibold text-ink">{{ Money::format($row['amount'], $currency) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card padding="p-4 sm:p-5">
                <form method="POST" action="{{ route('tours.sharing', $tour) }}" x-data x-ref="sharing">
                    @csrf @method('PATCH')
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="hidden" name="show_account" value="0">
                        <input type="checkbox" name="show_account" value="1" class="peer sr-only" @checked($tour->show_account) @change="$refs.sharing.requestSubmit()">
                        <span class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full bg-line-strong transition after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:bg-brand peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-brand/40"></span>
                        <span>
                            <span class="block text-[14px] font-semibold text-ink">{{ __('Show this account to everyone who booked') }}</span>
                            <span class="mt-0.5 block text-[12.5px] leading-relaxed text-muted">{{ __('It appears on each booking page. Names of who paid stay private.') }}</span>
                        </span>
                    </label>
                    <noscript><x-btn type="submit" size="sm" variant="outline" class="mt-3">{{ __('Save') }}</x-btn></noscript>
                </form>
            </x-card>
        </div>
    @endif
</div>
