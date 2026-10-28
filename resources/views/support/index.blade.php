<x-layouts.site title="{{ __('Support Sofor') }}" :heading="__('Support Sofor')"
                 :subtitle="__('Sofor is free for everyone, with no limits. If it saved your group some arguing, you can support it and keep it that way.')">

    {{-- Live gift calculator. bKash's MFS fee is absorbed by Sofor, so the amount
         the donor picks is exactly what leaves their account. --}}
    <section x-data="{
                 amount: {{ (float) old('amount', $initialAmount) }},
                 sandbox: {{ $sandbox ? 'true' : 'false' }},
                 browserFlow: {{ $bkashMode === 'browser' ? 'true' : 'false' }},
                 busy: false,
                 presets: {{ \Illuminate\Support\Js::from($presets) }},
                 base() { return Number(this.amount || 0); },
                 money(value) {
                     return '{{ \App\Support\Money::symbol() }} ' + Number(value || 0).toLocaleString(window.SoforLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                 },
                 async startBkash(form) {
                     const data = new FormData(form);

                     this.busy = true;

                     try {
                         await window.SoforBkash.pay({
                             scriptUrl: @js($bkashScriptUrl),
                             createUrl: @js(route('support.bkash.create')),
                             executeUrl: @js(route('support.bkash.execute')),
                             amount: this.base(),
                             payload: {
                                 name: data.get('name') || null,
                                 contact: data.get('contact') || null,
                                 message: data.get('message') || null,
                             },
                             onStart: () => this.busy = true,
                             onDone: (result) => {
                                 this.busy = false;
                                 if (result?.redirect) window.location.href = result.redirect;
                             },
                         });
                     } finally {
                         this.busy = false;
                     }
                 }
             }"
             class="flex flex-col gap-5">

        <div class="flex flex-wrap items-center gap-2.5">
            <x-badge tone="lime" icon="gift">{{ __('Optional and appreciated') }}</x-badge>
            <x-badge tone="slate" icon="shield">{{ __('Secure bKash checkout') }}</x-badge>
            @if ($supporterCount > 0)
                <x-badge tone="brand" icon="users">{{ trans_choice(':count supporter so far|:count supporters so far', $supporterCount, ['count' => number_format($supporterCount)]) }}</x-badge>
            @endif
        </div>

        @if (! $bkashEnabled)
            <div class="flex items-start gap-2.5 rounded-3xl border border-warning/40 bg-warning-tint p-4 text-[13px] text-warning">
                <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
                <p>
                    {{ __('Online support is paused because bKash is not connected on this installation yet. The Sofor team would still love to hear from you - call') }}
                    <a href="tel:+8801922992279" class="font-semibold underline decoration-dotted">+880 1922-992279</a>.
                </p>
            </div>
        @endif

        <form method="POST" action="{{ route('support.start') }}" class="flex flex-col gap-5"
              @if ($bkashMode === 'browser') @submit.prevent="startBkash($el)" @endif>
            @csrf

            <div class="flex flex-col gap-3">
                <p class="text-[13px] font-medium text-muted">{{ __('Pick an amount') }}</p>

                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                    @foreach ($presets as $preset)
                        <button type="button" @click="amount = {{ $preset }}"
                                class="rounded-2xl border px-3 py-3.5 text-center transition"
                                :class="base() === {{ $preset }}
                                    ? 'border-brand/60 bg-brand/12 text-ink ring-2 ring-brand/25'
                                    : 'border-line bg-surface-2 text-muted hover:border-brand/35 hover:text-ink'">
                            <span class="block font-display text-lg font-semibold">{{ \App\Support\Money::format($preset) }}</span>
                            <span class="mt-0.5 block text-[10px] uppercase tracking-wide text-faint">{{ __('One time') }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="amount" :label="__('Or enter your own')" :hint="__('In BDT, between :min and :max', ['min' => \App\Support\Money::format($minAmount), 'max' => \App\Support\Money::format($maxAmount)])" required>
                        <x-input name="amount" type="number" :min="$minAmount" :max="$maxAmount" step="1" x-model.number="amount" required />
                    </x-field>

                    <div class="flex flex-col justify-end">
                        <div class="rounded-3xl border border-line bg-surface-2 p-4 text-[12px]">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-muted">{{ __('Your gift') }}</span>
                                <span class="font-semibold text-ink" x-text="money(base())"></span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3 border-t border-line pt-3">
                                <span class="font-medium text-brand">{{ __('Leaves your account') }}</span>
                                <span class="font-display text-lg font-bold text-brand" x-text="money(base())"></span>
                            </div>
                            <p class="mt-2 text-[10.5px] leading-relaxed text-faint">
                                {{ __('Nothing is added on top - Sofor covers the bKash fee.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <p class="flex items-start gap-2 text-[11px] leading-relaxed text-muted">
                    <x-icon name="info" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                    {{ __('You are charged exactly the amount you choose. bKash\'s :percent% mobile financial service fee comes out of Sofor\'s side, not yours.', ['percent' => rtrim(rtrim(number_format($chargePercent, 2), '0'), '.')]) }}
                    @if ($sandbox) {{ __('The sandbox rounds the charge up to the nearest taka.') }} @endif
                    {{ __('Leaving your name is optional - support it silently if you prefer.') }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" :label="__('Your name')">
                    <x-input name="name" :value="old('name')" placeholder="{{ __('Optional') }}" maxlength="80" />
                </x-field>

                <x-field name="contact" :label="__('Phone or email')" :hint="__('Optional, only used to say thank you')">
                    <x-input name="contact" :value="old('contact')" placeholder="{{ __('Optional') }}" maxlength="160" />
                </x-field>
            </div>

            <x-field name="message" :label="__('Leave a note')">
                <x-textarea name="message" :value="old('message')" rows="2" maxlength="240"
                            placeholder="{{ __('Anything you want the Sofor team to know') }}" />
            </x-field>

            <div class="flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[11px] leading-relaxed text-faint">
                    {{ __('Gifts are one time and non refundable. Sofor never stores card numbers or your bKash PIN.') }}
                </p>

                <x-btn type="submit" size="lg" icon="gift" :disabled="! $bkashEnabled" class="shrink-0">
                    <span x-show="! busy">{{ __('Support with bKash') }}</span>
                    <span x-show="busy" x-cloak>{{ __('Opening bKash...') }}</span>
                </x-btn>
            </div>
        </form>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Where your money goes') }}</h2>
        <ul class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['mobile', __('Hosting and servers'), __('The app, the database and the real time sync that keeps every traveller on the same numbers.')],
                ['bolt', __('Gateway charges'), __('bKash settlement costs and the small fees that come with collecting money online.')],
                ['sparkles', __('New features'), __('Phone and email verification, receipts, and the reports trip leaders keep asking for.')],
            ] as [$icon, $title, $body])
                <li class="flex flex-col gap-2 rounded-3xl border border-line bg-surface-2 p-4">
                    <span class="flex size-9 items-center justify-center rounded-2xl bg-brand/12 text-brand">
                        <x-icon :name="$icon" class="size-4.5" />
                    </span>
                    <p class="text-[13px] font-semibold text-ink">{{ $title }}</p>
                    <p class="text-[12px] leading-relaxed text-muted">{{ $body }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($raised > 0 || $recent->isNotEmpty())
        <section class="flex flex-col gap-3">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Thank you, supporters') }}</h2>
            <p>
                {!! trans_choice(':count gift recorded, adding up to :amount.|:count gifts recorded, adding up to :amount.', $supporterCount, ['count' => number_format($supporterCount), 'amount' => '<span class="font-semibold text-ink">'.e(\App\Support\Money::format($raised)).'</span>']) !!}
            </p>

            <ul class="flex flex-wrap gap-2">
                @foreach ($recent as $gift)
                    <li class="inline-flex items-center gap-2 rounded-2xl border border-line bg-surface-2 px-3 py-2">
                        <span class="text-[12px] font-medium text-ink">{{ $gift->donorLabel() }}</span>
                        <span class="text-[12px] font-semibold text-brand">{{ $gift->amountLabel() }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($recent->contains(fn ($gift) => filled($gift->message)))
                <ul class="flex flex-col gap-2">
                    @foreach ($recent->whereNotNull('message') as $gift)
                        <li class="rounded-3xl border border-line bg-surface-2 p-4 text-[13px] text-muted">
                            <p class="text-ink">"{{ $gift->message }}"</p>
                            <p class="mt-1 text-[11px] text-faint">- {{ $gift->donorLabel() }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Questions') }}</h2>

        <div class="flex flex-col gap-3">
            @foreach ([
                [__('Do I have to pay to use Sofor?'), __('No. Every feature is free for everyone, with no limit on trips or travellers. Donating here is entirely voluntary and unlocks nothing extra.')],
                [__('Is donating a subscription?'), __('No. A donation here is a one time gift with nothing to cancel and no account attached. Sofor has no subscriptions at all.')],
                [__('Can I support from outside Bangladesh?'), __('Online support runs on bKash today. Call the Sofor team and we will find another way.')],
                [__('Will you show my name?'), __('Only if you type it in. With a blank name your gift appears as "Kind supporter".')],
            ] as [$question, $answer])
                <details class="group rounded-3xl border border-line bg-surface-2 p-4">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 text-[13px] font-semibold text-ink marker:content-none">
                        {{ $question }}
                        <x-icon name="chevron-down" class="size-4 shrink-0 text-faint transition group-open:rotate-180" />
                    </summary>
                    <p class="mt-2 text-[13px] leading-relaxed text-muted">{{ $answer }}</p>
                </details>
            @endforeach
        </div>

        <p class="text-[13px] text-muted">
            {{ __('Prefer talking to a human? Call') }} <a href="tel:+8801922992279" class="font-semibold text-ink hover:text-brand">+880 1922-992279</a>
            {{ __('or') }} <a href="tel:+8801910069929" class="font-semibold text-ink hover:text-brand">+880 1910-069929</a>{{ __(', or write to us at Lift 6, Elite House, 54 Motijheel C/A, Dhaka - 1000.') }}
        </p>
    </section>
</x-layouts.site>
