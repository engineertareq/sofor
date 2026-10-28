@props(['member', 'compact' => false])

{{-- Where to send money to this traveller: their own wallets and bank, each
     with a copy button, so nobody has to ask "what's your bKash?". --}}
@php
    $methods = $member?->user?->payoutMethods ?? collect();
@endphp

@if ($methods->isEmpty())
    <p {{ $attributes->merge(['class' => 'flex items-start gap-1.5 text-[11px] leading-relaxed text-faint']) }}>
        <x-icon name="info" class="mt-0.5 size-3 shrink-0" />
        {{ __(':name has not added a bKash, Nagad or bank account yet - ask them where to send it.', ['name' => $member?->name]) }}
    </p>
@else
    <ul {{ $attributes->merge(['class' => 'flex flex-wrap gap-1.5']) }}>
        @foreach ($compact ? $methods->take(2) : $methods as $method)
            <li x-data="clipboard(@js($method->copyValue()), @js(__(':provider number copied', ['provider' => $method->provider->label()])))">
                <button type="button" @click="copy()"
                        class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-line bg-surface py-1 pl-1 pr-2.5 text-[11.5px] transition hover:border-line-strong"
                        title="{{ __('Copy :provider number', ['provider' => $method->provider->label()]) }}">
                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full text-[9px] font-bold text-white"
                          style="background-color: {{ $method->provider->color() }}">{{ mb_substr($method->provider->label(), 0, 1) }}</span>
                    <span class="font-semibold text-ink">{{ $method->provider === \App\Enums\PayoutProvider::Bank ? ($method->bank_name ?: __('Bank')) : $method->provider->label() }}</span>
                    <span class="num truncate font-mono text-muted">{{ $method->displayNumber() }}</span>
                    <x-icon name="copy" class="size-3 shrink-0 text-faint" x-show="! copied" />
                    <x-icon name="check" class="size-3 shrink-0 text-positive" x-show="copied" x-cloak />
                </button>
            </li>
        @endforeach
    </ul>
@endif
