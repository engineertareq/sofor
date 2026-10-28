@php
    use App\Models\SmsTransaction;
    use App\Support\Money;
@endphp

<x-layouts.app title="{{ __('SMS wallet') }}" :active="'wallet'" :heading="__('SMS wallet')"
               :subtitle="__('Credit for the texts you send to your travellers.')" :back="route('dashboard')">

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:items-start">
        <div class="flex flex-col gap-6">
            {{-- ===== Balance ===== --}}
            <section class="relative overflow-hidden rounded-5xl border-2 border-ink bg-brand p-6 text-white shadow-sticker sm:p-7">
                <div aria-hidden="true" class="absolute inset-0 opacity-[0.12]"
                     style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1.3px); background-size: 18px 18px;"></div>
                <div class="relative">
                    <p class="font-mono text-[11px] font-bold uppercase tracking-[0.16em] text-white/70">{{ __('Balance') }}</p>
                    <p class="num mt-2 font-display text-[3rem] font-semibold leading-none">{{ Money::format($user->sms_balance) }}</p>
                    <p class="mt-2 text-[14px] text-white/80">
                        {!! trans_choice('About :count text at :amount each|About :count texts at :amount each', $messagesLeft, ['count' => '<strong class="font-semibold text-white">'.e(number_format($messagesLeft)).'</strong>', 'amount' => e(Money::format($price))]) !!}
                    </p>
                </div>
            </section>

            {{-- ===== Top up ===== --}}
            <x-card>
                <h2 class="font-display text-base font-semibold">{{ __('Top up') }}</h2>
                <p class="mt-0.5 text-[12px] text-muted">{{ __('Pay with bKash. You pay exactly the amount you pick - nothing is added on top.') }}</p>

                @if (! $bkashEnabled)
                    <p class="mt-4 flex items-start gap-2 rounded-2xl border border-warning/40 bg-warning-tint p-3.5 text-[12px] text-warning">
                        <x-icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ __('bKash is not connected on this installation yet, so top-ups are paused.') }}
                    </p>
                @endif

                <form method="POST" action="{{ route('wallet.topup') }}" class="mt-5 flex flex-col gap-4"
                      x-data="{
                          amount: {{ (int) old('amount', 50) }},
                          busy: false,
                          price: {{ $price }},
                          async startBkash() {
                              this.busy = true;
                              try {
                                  await window.SoforBkash.pay({
                                      scriptUrl: @js($bkashScriptUrl),
                                      createUrl: @js(route('wallet.bkash.create')),
                                      executeUrl: @js(route('wallet.bkash.execute')),
                                      amount: Number(this.amount),
                                      payload: {},
                                      onDone: (result) => { if (result?.redirect) window.location.href = result.redirect; },
                                  });
                              } finally { this.busy = false; }
                          },
                      }"
                      @if ($bkashMode === 'browser') @submit.prevent="startBkash()" @endif>
                    @csrf

                    <div class="grid grid-cols-5 gap-2">
                        @foreach ($presets as $preset)
                            <button type="button" @click="amount = {{ $preset }}"
                                    class="rounded-2xl border-[1.5px] px-2 py-3 text-center transition"
                                    :class="Number(amount) === {{ $preset }} ? 'border-ink bg-ink text-canvas' : 'border-line bg-surface-2 text-ink hover:border-line-strong'">
                                <span class="num block font-display text-[15px] font-semibold">৳{{ $preset }}</span>
                                <span class="block text-[10px] opacity-70">{{ __(':price texts', ['price' => (int) floor($preset / $price)]) }}</span>
                            </button>
                        @endforeach
                    </div>

                    <x-field name="amount" :label="__('Or enter an amount')" :hint="__('Whole taka, from :min to :max.', ['min' => Money::format($minimum), 'max' => Money::format($maximum)])">
                        <x-input name="amount" type="number" :min="$minimum" :max="$maximum" step="1" x-model.number="amount" required />
                    </x-field>

                    <p class="text-[12.5px] text-muted">
                        {{ __('That is about') }} <strong class="num font-semibold text-ink" x-text="window.SoforNum(Math.floor((Number(amount) || 0) / price))"></strong> {{ __('texts.') }}
                    </p>

                    <x-btn type="submit" icon="mobile" block :disabled="! $bkashEnabled">
                        <span x-show="! busy">{{ __('Top up') }} <span x-text="'৳' + window.SoforNum(Number(amount) || 0)"></span> {{ __('with bKash') }}</span>
                        <span x-show="busy" x-cloak>{{ __('Opening bKash...') }}</span>
                    </x-btn>
                </form>
            </x-card>

            <div class="rounded-4xl border border-line bg-surface-2 p-5">
                <p class="text-[13px] font-semibold text-ink">{{ __('What the credit pays for') }}</p>
                <ul class="mt-3 flex flex-col gap-2.5 text-[12.5px] text-muted">
                    @foreach ([
                        ['bell', __('Payment reminders you text to friends who owe you')],
                        ['link', __('Pay links for travellers who do not use the app')],
                        ['map-pin', __('Check-in texts to a traveller\'s family')],
                    ] as [$icon, $text])
                        <li class="flex items-start gap-2.5"><x-icon :name="$icon" class="mt-0.5 size-4 shrink-0 text-brand" /> {{ $text }}</li>
                    @endforeach
                </ul>
                <p class="mt-3 flex items-start gap-2 border-t border-line pt-3 text-[11.5px] leading-relaxed text-faint">
                    <x-icon name="shield" class="mt-0.5 size-3.5 shrink-0" />
                    {{ __('Verification and password codes are always free. A long or Bangla text may count as two.') }}
                </p>
            </div>
        </div>

        {{-- ===== History ===== --}}
        <x-card padding="p-0">
            <div class="border-b border-line px-5 py-4">
                <h2 class="font-display text-sm font-semibold">{{ __('History') }}</h2>
            </div>

            @if ($transactions->isEmpty())
                <p class="px-5 py-12 text-center text-[13px] text-muted">{{ __('No top-ups or texts yet.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($transactions as $transaction)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span @class([
                                'flex size-9 shrink-0 items-center justify-center rounded-xl',
                                'bg-positive-tint text-positive' => $transaction->type !== SmsTransaction::TYPE_CHARGE,
                                'bg-surface-2 text-muted' => $transaction->type === SmsTransaction::TYPE_CHARGE,
                            ])>
                                <x-icon :name="match ($transaction->type) { SmsTransaction::TYPE_TOPUP => 'plus', SmsTransaction::TYPE_REFUND => 'refresh', default => 'send' }" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-medium text-ink">{{ $transaction->label() }}</p>
                                <p class="truncate text-[11px] text-faint">
                                    {{ $transaction->created_at->translatedFormat('d M Y, H:i') }}
                                    @if ($transaction->recipient) - {!! __('to :phone', ['phone' => '<span class="num">'.e($transaction->recipient).'</span>']) !!} @endif
                                    @if ($transaction->trip) - {{ $transaction->trip->title }} @endif
                                    @if ($transaction->messages > 1) - {{ __(':count parts', ['count' => $transaction->messages]) }} @endif
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p @class(['num text-[13px] font-semibold', 'text-positive' => (float) $transaction->amount > 0, 'text-ink' => (float) $transaction->amount <= 0])>
                                    {{ (float) $transaction->amount > 0 ? '+' : '-' }}{{ Money::format(abs((float) $transaction->amount)) }}
                                </p>
                                <p class="num text-[10.5px] text-faint">{{ Money::format($transaction->balance_after) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($transactions->hasPages())
                    <div class="border-t border-line px-5 py-4">{{ $transactions->links() }}</div>
                @endif
            @endif
        </x-card>
    </div>
</x-layouts.app>
