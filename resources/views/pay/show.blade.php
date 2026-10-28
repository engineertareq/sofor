@php
    use App\Support\Money;

    $currency = $trip->currency;
    $due = (float) ($balance['due'] ?? 0);
@endphp

<x-layouts.guest :title="__(':trip - your share', ['trip' => $trip->title])" :noindex="true"
                 :heading="__('Hi :name', ['name' => $member->firstName()])"
                 :subtitle="__('Here is where your share from :trip stands.', ['trip' => $trip->title])">

    <div class="flex flex-col gap-4">
        {{-- ===== The number that matters ===== --}}
        @if ($owes->isEmpty())
            <section class="flex items-center gap-4 rounded-4xl border-[1.5px] border-positive/40 bg-positive-tint p-5">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-positive text-white"><x-icon name="check" class="size-6" /></span>
                <div>
                    <p class="font-display text-[20px] font-semibold text-ink">{{ __('You are all settled') }}</p>
                    <p class="text-[13px] text-muted">{{ __('Nothing is open on this trip right now.') }}</p>
                </div>
            </section>
        @else
            <section class="rounded-4xl border-2 border-ink bg-surface p-5 shadow-sticker">
                <p class="eyebrow text-faint">{{ __('You owe') }}</p>
                <p class="num mt-1 font-display text-[2.4rem] font-semibold leading-none text-ink">{{ Money::format($due, $currency) }}</p>
                <p class="mt-2 text-[12.5px] text-muted">{{ __('Pay each person directly with your own bKash, Nagad, bank or cash, then tap "I paid".') }}</p>
            </section>

            @foreach ($owes as $row)
                <section class="rounded-4xl border-[1.5px] border-line bg-surface p-5 shadow-soft"
                         x-data="{ open: {{ $errors->any() && (int) old('to_member_id') === $row['to_id'] ? 'true' : 'false' }} }">
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$row['to']->name" :gradient="$row['to']->avatarGradient()" :src="$row['to']->avatarUrl()" size="md" />
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] text-muted">{{ __('Pay') }}</p>
                            <p class="truncate font-display text-[18px] font-semibold text-ink">{{ $row['to']->name }}</p>
                        </div>
                        <p class="num shrink-0 font-display text-[20px] font-semibold text-ink">{{ Money::format($row['amount'], $currency) }}</p>
                    </div>

                    <x-payout-chips :member="$row['to']" class="mt-4" />

                    <x-btn type="button" block icon="check" class="mt-4" x-show="! open" @click="open = true">{{ __('I paid :first_name', ['first_name' => $row['to']->firstName()]) }}</x-btn>

                    <form method="POST" action="{{ route('pay.paid', $member->pay_token) }}" x-show="open" x-cloak class="mt-4 flex flex-col gap-3 border-t border-line pt-4">
                        @csrf
                        <input type="hidden" name="to_member_id" value="{{ $row['to_id'] }}">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field name="amount" :label="__('Amount you paid')">
                                <x-input name="amount" type="number" step="0.01" min="1" :value="old('amount', number_format($row['amount'], 2, '.', ''))" required />
                            </x-field>
                            <x-field name="method" :label="__('Paid with')">
                                <x-select name="method" :options="$methods" :value="old('method', 'bkash')" />
                            </x-field>
                        </div>
                        <x-field name="reference" :label="__('Transaction ID')" :hint="__('From your bKash, Nagad or bank receipt - it helps them find it.')">
                            <x-input name="reference" maxlength="80" placeholder="e.g. 8N7A2K1Q" />
                        </x-field>
                        <div class="flex gap-2">
                            <x-btn type="submit" icon="check">{{ __('Send to :first_name', ['first_name' => $row['to']->firstName()]) }}</x-btn>
                            <x-btn type="button" variant="ghost" @click="open = false">{{ __('Cancel') }}</x-btn>
                        </div>
                    </form>
                </section>
            @endforeach
        @endif

        @if ($pending->isNotEmpty())
            <section class="rounded-4xl border border-warning/40 bg-warning-tint p-4">
                <p class="text-[13px] font-semibold text-ink">{{ __('Waiting for confirmation') }}</p>
                <ul class="mt-2 flex flex-col gap-1.5 text-[12.5px] text-muted">
                    @foreach ($pending as $payment)
                        <li class="flex items-center justify-between gap-3">
                            <span>{{ $payment->to?->name }} - {{ $payment->methodEnum()->label() }}</span>
                            <span class="num font-semibold text-ink">{{ Money::format($payment->amount, $currency) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($shares->isNotEmpty())
            <details class="group rounded-4xl border border-line bg-surface-2 px-4 py-3">
                <summary class="flex cursor-pointer list-none items-center justify-between text-[13px] font-semibold text-muted">
                    {{ __('What your share is made of') }}
                    <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                </summary>
                <ul class="mt-3 flex flex-col divide-y divide-line text-[12.5px]">
                    @foreach ($shares as $share)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate text-ink">{{ $share->expense->title }}</span>
                            <span class="num shrink-0 font-medium text-muted">{{ Money::format($share->amount, $currency) }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($balance)
                    <dl class="mt-2 grid grid-cols-3 gap-2 border-t border-line pt-3 text-center text-[11px] text-faint">
                        <div><dt>{{ __('Your share') }}</dt><dd class="num mt-0.5 text-[13px] font-semibold text-ink">{{ Money::format($balance['share'], $currency) }}</dd></div>
                        <div><dt>{{ __('You paid for') }}</dt><dd class="num mt-0.5 text-[13px] font-semibold text-ink">{{ Money::format($balance['paid'], $currency) }}</dd></div>
                        <div><dt>{{ __('Paid back') }}</dt><dd class="num mt-0.5 text-[13px] font-semibold text-ink">{{ Money::format($balance['sent'], $currency) }}</dd></div>
                    </dl>
                @endif
            </details>
        @endif

        <p class="mt-2 text-center text-[12.5px] text-muted">
            {{ __('Want to see the whole trip?') }} <a href="{{ route('register') }}" class="font-semibold text-brand hover:underline">{{ __('Create a free Sofor account') }}</a>
        </p>
    </div>

    <x-slot:art>
        <span class="stamp text-brand">{{ __('Your trip') }}</span>
        <div class="mt-6 rounded-4xl border border-line bg-surface p-6 shadow-soft">
            <x-glyph :name="$trip->icon" class="size-16" />
            <h2 class="mt-4 font-display text-[1.8rem] font-semibold leading-tight text-ink">{{ $trip->title }}</h2>
            @if ($trip->destination)
                <p class="mt-1 flex items-center gap-1.5 text-[13px] text-muted"><x-icon name="map-pin" class="size-3.5 text-brand" /> {{ $trip->destination }}</p>
            @endif
            <p class="mt-5 text-[13px] leading-relaxed text-muted">
                {{ __('Sofor keeps the trip\'s money honest: every cost, every share, and who still owes whom. It never holds anybody\'s money.') }}
            </p>
        </div>
    </x-slot:art>
</x-layouts.guest>
