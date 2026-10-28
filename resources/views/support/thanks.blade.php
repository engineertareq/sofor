<x-layouts.site title="{{ __('Thank you') }}" :noindex="true" :heading="__('Thank you for backing Sofor')">
    <section class="flex flex-col items-center gap-4 text-center">
        <span class="sticker flex size-16 items-center justify-center rounded-3xl border-[1.5px] border-positive bg-positive text-white">
            <x-icon name="check" class="size-8" />
        </span>

        @if ($donation)
            <p class="max-w-md text-[15px] leading-relaxed text-muted">
                {{ __('Your gift of') }} <span class="font-display text-lg font-semibold text-ink">{{ $donation->amountLabel() }}</span>
                {{ __('came through bKash and is safely recorded. It keeps Sofor free for every traveller who plans a trip with us.') }}
            </p>

            <dl class="mt-2 grid w-full max-w-sm gap-2 rounded-3xl border border-line bg-surface-2 p-4 text-left text-[12px]">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted">{{ __('Your gift') }}</dt>
                    <dd class="font-semibold text-ink">{{ $donation->amountLabel() }}</dd>
                </div>
                @if ((float) $donation->charge > 0)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">{{ __('bKash fee, paid by Sofor') }}</dt>
                        <dd class="font-semibold text-positive">{{ \App\Support\Money::format($donation->charge) }}</dd>
                    </div>
                @endif
                <div class="flex items-center justify-between gap-3 border-t border-line pt-2">
                    <dt class="text-muted">{{ __('Charged to you') }}</dt>
                    <dd class="font-display text-base font-bold text-brand">{{ $donation->totalLabel() }}</dd>
                </div>
                @if ($donation->trx_id)
                    <div class="flex items-center justify-between gap-3 border-t border-line pt-2">
                        <dt class="text-muted">{{ __('bKash transaction') }}</dt>
                        <dd class="font-mono text-[12px] font-semibold text-ink">{{ $donation->trx_id }}</dd>
                    </div>
                @endif
            </dl>

            <p class="max-w-md text-[12px] leading-relaxed text-faint">
                {{ __('Keep the bKash transaction ID above for your records. Support is a one time gift, so there is no subscription to manage and nothing will be charged again.') }}
            </p>
        @else
            <p class="max-w-md text-[15px] leading-relaxed text-muted">
                {{ __('You are back from bKash. If you completed a payment it is already recorded and visible to the Sofor team - no receipts to track, no subscription to cancel.') }}
            </p>
        @endif

        <div class="mt-2 flex flex-col gap-3 sm:flex-row">
            <x-btn :href="auth()->user()?->homeUrl() ?? route('home')" size="lg" icon="grid">
                {{ match (true) {
                    auth()->user()?->isAdmin() === true => __('Back to the console'),
                    auth()->check() => __('Back to your trips'),
                    default => __('Back to the home page'),
                } }}
            </x-btn>
            <x-btn :href="route('support.index')" variant="outline" size="lg" icon="gift">{{ __('Support again') }}</x-btn>
        </div>
    </section>
</x-layouts.site>
