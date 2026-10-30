{{-- The payments tab of the organiser's dashboard (tours/show): every payment
     and refund, newest first. --}}
@php
    use App\Models\TourPayment;
    use App\Support\Money;

    $statusBadge = [
        TourPayment::STATUS_PENDING => ['warning', __('To confirm')],
        TourPayment::STATUS_CONFIRMED => ['positive', __('Confirmed')],
        TourPayment::STATUS_REJECTED => ['muted', __('Not received')],
    ];
@endphp

<section x-show="tab === 'payments'" x-cloak role="tabpanel">
    @if ($payments->isEmpty())
        <x-empty icon="wallet" :title="__('No payments yet')" :text="__('When someone says they paid, or you record cash, it shows up here.')" />
    @else
        <ul class="divide-y divide-line overflow-hidden rounded-4xl border border-line bg-surface shadow-soft">
            @foreach ($payments as $payment)
                <li class="flex items-center gap-3 px-4 py-3">
                    <span @class([
                        'flex size-9 shrink-0 items-center justify-center rounded-xl',
                        'bg-negative-tint text-negative' => $payment->isRefund(),
                        'bg-positive-tint text-positive' => ! $payment->isRefund(),
                    ])>
                        <x-icon :name="$payment->isRefund() ? 'arrow-up-right' : 'wallet'" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[13.5px]">
                            <span class="truncate font-semibold text-ink">{{ $payment->registration->name }}</span>
                            <x-badge :tone="$statusBadge[$payment->status][0]" size="sm">{{ $payment->isRefund() ? __('Refund') : $statusBadge[$payment->status][1] }}</x-badge>
                        </p>
                        <p class="mt-0.5 truncate text-[12px] text-muted">
                            {{ $payment->paid_on->translatedFormat('j M') }} · {{ $payment->method->label() }}
                            @if ($payment->reference) · <span class="font-mono">{{ $payment->reference }}</span> @endif
                            @if ($payment->sender) · {{ __('from :number', ['number' => $payment->sender]) }} @endif
                        </p>
                    </div>
                    <p @class(['num shrink-0 font-display text-[15px] font-semibold', 'text-negative' => $payment->isRefund(), 'text-ink' => ! $payment->isRefund(), 'line-through opacity-50' => $payment->status === TourPayment::STATUS_REJECTED])>
                        {{ $payment->isRefund() ? '-' : '' }}{{ Money::format($payment->amount, $tour->currency) }}
                    </p>
                    @if ($payment->isPending())
                        <form method="POST" action="{{ route('tours.payments.confirm', [$tour, $payment]) }}">
                            @csrf
                            <button type="submit" class="flex size-8 items-center justify-center rounded-xl bg-positive-tint text-positive transition hover:bg-positive hover:text-white" aria-label="{{ __('Received') }}" title="{{ __('Received') }}">
                                <x-icon name="check" class="size-4" />
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('tours.payments.destroy', [$tour, $payment]) }}"
                          onsubmit="return confirm(@js(__('Remove this payment from the record?')))">
                        @csrf @method('DELETE')
                        <button type="submit" class="flex size-8 items-center justify-center rounded-xl text-faint transition hover:bg-negative-tint hover:text-negative" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}">
                            <x-icon name="trash" class="size-3.5" />
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</section>
