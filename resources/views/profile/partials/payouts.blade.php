@php
    use App\Enums\PayoutProvider;

    $methods = $user->payoutMethods;
    $placeholders = [
        'bkash' => '01712345678', 'nagad' => '01712345678', 'upay' => '01712345678',
        'rocket' => '017123456789', 'bank' => __('Account number'),
    ];
@endphp

<x-card id="get-paid" class="scroll-mt-24">
    <div class="flex items-start gap-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-tint text-brand">
            <x-icon name="wallet" class="size-5" />
        </span>
        <div class="min-w-0">
            <h2 class="font-display text-base font-semibold">{{ __('How friends pay you') }}</h2>
            <p class="mt-0.5 text-[12px] leading-relaxed text-muted">
                {{ __('When someone owes you on a trip, Sofor shows them these so they can pay you directly. The money goes straight to you - Sofor never touches it.') }}
            </p>
        </div>
    </div>

    @if ($methods->isNotEmpty())
        <ul class="mt-5 flex flex-col gap-2">
            @foreach ($methods as $method)
                <li class="flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-surface-2 px-3.5 py-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl text-[13px] font-bold text-white"
                          style="background-color: {{ $method->provider->color() }}">{{ mb_substr($method->provider->label(), 0, 1) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-x-2 text-[13px] font-semibold text-ink">
                            {{ $method->provider->label() }}
                            <span class="num font-mono text-[12.5px] font-medium text-muted">{{ $method->displayNumber() }}</span>
                            @if ($method->is_primary)
                                <x-badge tone="brand" size="sm">{{ __('Main') }}</x-badge>
                            @endif
                        </p>
                        <p class="truncate text-[11px] text-faint">
                            {{ $method->account_name ? $method->account_name.' - ' : '' }}{{ $method->detail() }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1">
                        @unless ($method->is_primary)
                            <form method="POST" action="{{ route('payout-methods.primary', $method) }}">
                                @csrf
                                <button type="submit" class="rounded-xl px-2.5 py-1.5 text-[11.5px] font-semibold text-brand transition hover:bg-brand-tint">{{ __('Make main') }}</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('payout-methods.destroy', $method) }}"
                              onsubmit="return confirm(@js('Remove this '.$method->provider->label().' account?'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="flex size-8 items-center justify-center rounded-xl text-faint transition hover:bg-negative-tint hover:text-negative"
                                    aria-label="{{ __('Remove :provider :number', ['provider' => $method->provider->label(), 'number' => $method->displayNumber()]) }}">
                                <x-icon name="trash" class="size-3.5" />
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="mt-5 rounded-2xl border border-dashed border-line-strong bg-surface-2 p-4 text-[12.5px] leading-relaxed text-muted">
            {{ __('No accounts yet. Add your bKash or Nagad number so friends can pay you back without asking for it.') }}
        </p>
    @endif

    @if ($methods->count() < \App\Models\PayoutMethod::MAX_PER_USER)
        <form method="POST" action="{{ route('payout-methods.store') }}" class="mt-5 flex flex-col gap-3 border-t border-line pt-5"
              x-data="{ provider: @js(old('provider', 'bkash')), placeholders: @js($placeholders) }">
            @csrf
            <p class="text-[13px] font-semibold text-ink">{{ __('Add an account') }}</p>

            <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-5" role="radiogroup" aria-label="{{ __('Account type') }}">
                @foreach (PayoutProvider::cases() as $case)
                    <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl border-[1.5px] px-2 py-2 text-[12px] font-semibold transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/40"
                           :class="provider === @js($case->value) ? 'border-ink bg-ink text-canvas' : 'border-line bg-surface text-muted hover:border-line-strong'">
                        <input type="radio" name="provider" value="{{ $case->value }}" x-model="provider" class="sr-only">
                        <span class="size-2 shrink-0 rounded-full" style="background-color: {{ $case->color() }}"></span>
                        {{ $case->value === 'bank' ? __('Bank') : $case->label() }}
                    </label>
                @endforeach
            </div>

            <x-field name="account_number" :label="__('Number')">
                <x-input name="account_number" inputmode="numeric" autocomplete="off" x-bind:placeholder="placeholders[provider]" required />
            </x-field>

            <div x-show="provider === 'bank'" x-cloak class="grid gap-3 sm:grid-cols-2">
                <x-field name="account_name" :label="__('Name on the account')">
                    <x-input name="account_name" maxlength="80" />
                </x-field>
                <x-field name="bank_name" :label="__('Bank')">
                    <x-input name="bank_name" maxlength="80" placeholder="{{ __('e.g. Dutch-Bangla Bank') }}" />
                </x-field>
                <x-field name="branch_name" :label="__('Branch')" class="sm:col-span-2">
                    <x-input name="branch_name" maxlength="80" placeholder="{{ __('Optional') }}" />
                </x-field>
            </div>

            <x-btn type="submit" size="sm" icon="plus" class="self-start">{{ __('Add account') }}</x-btn>
        </form>
    @endif
</x-card>
