{{-- Somebody's own booking page, /r/<token> (TourTicketController): what they
     booked, what is still due, where to pay it and every payment they made.
     No account needed. Built like the pay link page (pay/show). --}}
@php
    use App\Enums\TourBookingStatus;
    use App\Enums\TourStatus;
    use App\Models\TourPayment;
    use App\Support\Money;
    use App\Support\Phone;

    $currency = $tour->currency;
    $organiser = $tour->organiser;
    $paid = $registration->paid();
    $due = $registration->due();
    $advanceDue = $registration->advanceDue();
    $total = (float) $registration->total;
    $percent = $total > 0 ? min(100, round($paid / $total * 100)) : 100;
    $status = $registration->status;
    $canPay = $registration->isBooked() && $due > 0 && $tour->status->isActive();
    $canCancel = $status !== TourBookingStatus::Cancelled
        && ! in_array($tour->status, [TourStatus::Completed, TourStatus::Cancelled], true)
        && $paid <= 0 && ! $registration->hasPendingPayment();
    $dates = $tour->starts_on
        ? $tour->starts_on->translatedFormat('j M').($tour->ends_on && ! $tour->ends_on->isSameDay($tour->starts_on) ? ' - '.$tour->ends_on->translatedFormat('j M Y') : $tour->starts_on->translatedFormat(' Y'))
        : null;
    $paymentBadge = [
        TourPayment::STATUS_PENDING => ['warning', __('Waiting for confirmation')],
        TourPayment::STATUS_CONFIRMED => ['positive', __('Confirmed')],
        TourPayment::STATUS_REJECTED => ['negative', __('Not found by the organiser')],
    ];
@endphp

<x-layouts.guest :title="__(':tour - your booking', ['tour' => $tour->title])" :noindex="true"
                 :heading="__('Hi :name', ['name' => $registration->firstName()])"
                 :subtitle="__('Your booking for :tour.', ['tour' => $tour->title])">

    <div class="flex flex-col gap-4">
        {{-- Keep this page --}}
        <div class="flex items-center gap-3 rounded-3xl border border-line bg-surface-2 p-3" x-data="clipboard(@js($registration->url()), @js(__('Booking link copied')))">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-tint text-brand"><x-icon name="link" class="size-4" /></span>
            <p class="min-w-0 flex-1 text-[12.5px] leading-snug text-muted">{{ __('This page is your booking. Save the link to come back to it.') }}</p>
            <button type="button" @click="copy()" class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl border border-line bg-surface px-3 text-[12.5px] font-semibold text-ink transition hover:border-line-strong">
                <x-icon name="copy" class="size-3.5" x-show="! copied" /><x-icon name="check" class="size-3.5 text-positive" x-show="copied" x-cloak />
                <span x-text="copied ? @js(__('Copied')) : @js(__('Copy link'))">{{ __('Copy link') }}</span>
            </button>
        </div>

        {{-- ===== Where the booking stands ===== --}}
        @if ($status === TourBookingStatus::Cancelled)
            <section class="rounded-4xl border border-line bg-surface-2 p-5">
                <p class="font-display text-[20px] font-semibold text-ink">{{ __('This booking is cancelled') }}</p>
                <p class="mt-1 text-[13px] text-muted">
                    {{ $registration->refundOwed() > 0
                        ? __('The organiser owes you a refund of :amount.', ['amount' => Money::format($registration->refundOwed(), $currency)])
                        : __('Nothing is owed either way.') }}
                </p>
            </section>
        @elseif ($status === TourBookingStatus::Waitlisted)
            <section class="flex items-start gap-4 rounded-4xl border-[1.5px] border-warning/40 bg-warning-tint p-5">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-warning text-white"><x-icon name="clock" class="size-6" /></span>
                <div>
                    <p class="font-display text-[20px] font-semibold text-ink">{{ __('You are on the waitlist') }}</p>
                    <p class="mt-1 text-[13px] text-muted">{{ __('The tour was full when you booked. If seats free up, the organiser gives them to you and you pay then.') }}</p>
                </div>
            </section>
        @elseif ($due <= 0)
            <section class="flex items-center gap-4 rounded-4xl border-[1.5px] border-positive/40 bg-positive-tint p-5">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-positive text-white"><x-icon name="check" class="size-6" /></span>
                <div>
                    <p class="font-display text-[20px] font-semibold text-ink">{{ __('Paid in full') }}</p>
                    <p class="text-[13px] text-muted">{{ __('You are all set for :tour.', ['tour' => $tour->title]) }}</p>
                </div>
            </section>
        @else
            <section class="rounded-4xl border-2 border-ink bg-surface p-5 shadow-sticker">
                <p class="eyebrow text-faint">{{ __('Still to pay') }}</p>
                <p class="num mt-1 font-display text-[2.4rem] font-semibold leading-none text-ink">{{ Money::format($due, $currency) }}</p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-surface-2">
                    <div class="h-full rounded-full bg-brand transition-all" style="width: {{ $percent }}%"></div>
                </div>
                <p class="num mt-2 text-[12.5px] text-muted">{{ __('Paid :paid of :total', ['paid' => Money::format($paid, $currency), 'total' => Money::format($total, $currency)]) }}</p>
                @if ($advanceDue > 0 && $advanceDue < $due)
                    <p class="mt-3 flex items-start gap-1.5 rounded-2xl bg-warning-tint px-3 py-2 text-[12.5px] text-warning">
                        <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                        {{ __('Pay at least :amount to hold your seats.', ['amount' => Money::format($advanceDue, $currency)]) }}
                    </p>
                @endif
            </section>
        @endif

        {{-- ===== Paying the organiser ===== --}}
        @if ($canPay)
            <section class="rounded-4xl border-[1.5px] border-line bg-surface p-5 shadow-soft" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
                <div class="flex items-center gap-3">
                    <x-avatar :name="$organiser?->name" :gradient="$organiser?->avatarGradient()" :src="$organiser?->avatarUrl()" size="md" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] text-muted">{{ __('Pay the organiser') }}</p>
                        <p class="truncate font-display text-[18px] font-semibold text-ink">{{ $organiser?->name }}</p>
                    </div>
                    @if ($tour->contact_phone)
                        <a href="tel:{{ $tour->contact_phone }}" class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-positive-tint text-positive transition hover:bg-positive hover:text-white" aria-label="{{ __('Call the organiser') }}">
                            <x-icon name="phone" class="size-4.5" />
                        </a>
                    @endif
                </div>

                <x-payout-chips :user="$organiser" class="mt-4" />

                <x-btn type="button" block icon="check" class="mt-4" x-show="! open" @click="open = true">{{ __('I paid') }}</x-btn>

                <form method="POST" action="{{ route('tours.ticket.paid', $registration->token) }}" x-show="open" x-cloak class="mt-4 flex flex-col gap-3 border-t border-line pt-4">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="amount" :label="__('Amount you paid')">
                            <x-input name="amount" type="number" step="0.01" min="1" inputmode="decimal" :value="old('amount', number_format($due, 2, '.', ''))" required />
                        </x-field>
                        <x-field name="method" :label="__('Paid with')">
                            <x-select name="method" :options="$methods" :value="old('method', 'bkash')" />
                        </x-field>
                        <x-field name="reference" :label="__('Transaction ID')" :hint="__('From your bKash, Nagad or bank receipt.')">
                            <x-input name="reference" maxlength="80" placeholder="e.g. 8N7A2K1Q" />
                        </x-field>
                        <x-field name="sender" :label="__('Sent from number')" :hint="__('Helps the organiser find it.')">
                            <x-input name="sender" maxlength="30" inputmode="tel" placeholder="01XXX-XXXXXX" />
                        </x-field>
                    </div>
                    <div class="flex gap-2">
                        <x-btn type="submit" icon="check">{{ __('Send to the organiser') }}</x-btn>
                        <x-btn type="button" variant="ghost" @click="open = false">{{ __('Cancel') }}</x-btn>
                    </div>
                </form>
            </section>
        @endif

        {{-- ===== Their payments ===== --}}
        @if ($registration->payments->isNotEmpty())
            <section class="rounded-4xl border border-line bg-surface p-4 shadow-soft">
                <h2 class="px-1 text-[13px] font-semibold text-ink">{{ __('Your payments') }}</h2>
                <ul class="mt-2 divide-y divide-line">
                    @foreach ($registration->payments as $payment)
                        <li class="flex items-center gap-3 px-1 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-medium text-ink">{{ $payment->isRefund() ? __('Refund to you') : $payment->method->label() }} <span class="text-faint">· {{ $payment->paid_on->translatedFormat('j M') }}</span></p>
                                <x-badge :tone="$payment->isRefund() ? 'muted' : $paymentBadge[$payment->status][0]" size="sm" class="mt-1">{{ $payment->isRefund() ? __('Refund') : $paymentBadge[$payment->status][1] }}</x-badge>
                            </div>
                            <p @class(['num shrink-0 font-display text-[15px] font-semibold', 'text-ink' => ! $payment->isRefund(), 'text-negative' => $payment->isRefund(), 'line-through opacity-50' => $payment->status === TourPayment::STATUS_REJECTED])>
                                {{ $payment->isRefund() ? '-' : '' }}{{ Money::format($payment->amount, $currency) }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- ===== The booking and the tour ===== --}}
        <section class="rounded-4xl border border-line bg-surface p-5 shadow-soft">
            <h2 class="text-[13px] font-semibold text-ink">{{ __('Your booking') }}</h2>
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-[13px]">
                <div><dt class="text-[11.5px] text-faint">{{ __('Package') }}</dt><dd class="font-medium text-ink">{{ $registration->package?->name }}@if ($registration->quantity > 1) × <span class="num">{{ $registration->quantity }}</span>@endif</dd></div>
                <div><dt class="text-[11.5px] text-faint">{{ __('Seats') }}</dt><dd class="num font-medium text-ink">{{ $registration->seats }}</dd></div>
                <div><dt class="text-[11.5px] text-faint">{{ __('Total') }}</dt><dd class="num font-medium text-ink">{{ Money::format($total, $currency) }}</dd></div>
                <div><dt class="text-[11.5px] text-faint">{{ __('Booked on') }}</dt><dd class="font-medium text-ink">{{ $registration->created_at->translatedFormat('j M Y') }}</dd></div>
                <div class="col-span-2"><dt class="text-[11.5px] text-faint">{{ __('Name and number') }}</dt><dd class="font-medium text-ink">{{ $registration->name }} · <span class="num">{{ Phone::pretty($registration->phone) }}</span></dd></div>
                @if ($registration->note)
                    <div class="col-span-2"><dt class="text-[11.5px] text-faint">{{ __('Note') }}</dt><dd class="text-ink">{{ $registration->note }}</dd></div>
                @endif
            </dl>

            <div class="mt-4 border-t border-line pt-4">
                <h2 class="text-[13px] font-semibold text-ink">{{ __('The tour') }}</h2>
                <ul class="mt-2 flex flex-col gap-2 text-[13px] text-muted">
                    @if ($dates)<li class="flex items-center gap-2"><x-icon name="calendar" class="size-4 text-brand" />{{ $dates }}</li>@endif
                    @if ($tour->meeting_point)<li class="flex items-center gap-2"><x-icon name="pin" class="size-4 text-brand" />{{ $tour->meeting_point }}</li>@endif
                    @if ($tour->meeting_at)<li class="flex items-center gap-2"><x-icon name="clock" class="size-4 text-brand" />{{ \App\Support\Bangla::moment($tour->meeting_at) }}</li>@endif
                    @if ($tour->contact_phone)<li class="flex items-center gap-2"><x-icon name="phone" class="size-4 text-brand" /><a href="tel:{{ $tour->contact_phone }}" class="num font-semibold text-brand hover:underline">{{ Phone::pretty($tour->contact_phone) }}</a></li>@endif
                </ul>
                <a href="{{ $tour->publicUrl() }}" class="mt-3 inline-flex items-center gap-1 text-[12.5px] font-semibold text-brand hover:underline">{{ __('See the tour page') }} <x-icon name="chevron-right" class="size-3.5" /></a>
            </div>
        </section>

        {{-- ===== The account, when the organiser shares it ===== --}}
        @if ($account)
            @include('tours.partials.account', ['account' => $account, 'tour' => $tour, 'forOrganiser' => false])
        @endif

        @if ($canCancel)
            <form method="POST" action="{{ route('tours.ticket.cancel', $registration->token) }}" class="text-center"
                  onsubmit="return confirm(@js(__('Cancel your booking? Your seats go to someone else.')))">
                @csrf
                <button type="submit" class="text-[12.5px] font-semibold text-muted underline decoration-dotted underline-offset-2 transition hover:text-negative">{{ __('Cancel my booking') }}</button>
            </form>
        @endif
    </div>

    <x-slot:art>
        <span class="stamp text-brand">{{ __('Your booking') }}</span>
        <div class="mt-6 overflow-hidden rounded-4xl border border-line bg-surface shadow-soft">
            <div class="h-20" style="background-image: {{ $tour->coverStyle() }}"></div>
            <div class="p-6">
                <h2 class="font-display text-[1.7rem] font-semibold leading-tight text-ink">{{ $tour->title }}</h2>
                @if ($tour->destination)
                    <p class="mt-1 flex items-center gap-1.5 text-[13px] text-muted"><x-icon name="map-pin" class="size-3.5 text-brand" /> {{ $tour->destination }}</p>
                @endif
                @if ($dates)
                    <p class="mt-1 flex items-center gap-1.5 text-[13px] text-muted"><x-icon name="calendar" class="size-3.5 text-brand" /> {{ $dates }}</p>
                @endif
                <p class="mt-5 text-[13px] leading-relaxed text-muted">
                    {{ __('You pay :name directly. Sofor only keeps the record, so you both see the same numbers.', ['name' => $organiser?->name]) }}
                </p>
            </div>
        </div>
    </x-slot:art>
</x-layouts.guest>
