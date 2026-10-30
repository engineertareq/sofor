{{-- The bookings tab of the organiser's dashboard (tours/show): search and
     filter in the browser (tourDashboard.shows), one card per booking. --}}
@php
    use App\Enums\TourBookingStatus;
    use App\Models\TourRegistration;
    use App\Support\Money;
    use App\Support\Phone;

    $stateOf = fn (TourRegistration $registration) => $registration->isBooked() ? $registration->paymentState() : $registration->status->value;
    $states = $tour->registrations->map($stateOf);
    $filters = [
        'all' => __('All'),
        TourRegistration::STATE_UNPAID => __('Unpaid'),
        TourRegistration::STATE_PARTLY => __('Partly paid'),
        TourRegistration::STATE_PAID => __('Paid'),
        TourBookingStatus::Waitlisted->value => __('Waitlist'),
        TourBookingStatus::Cancelled->value => __('Cancelled'),
    ];
    $badges = [
        TourRegistration::STATE_PAID => ['positive', __('Paid')],
        TourRegistration::STATE_PARTLY => ['warning', __('Partly paid')],
        TourRegistration::STATE_UNPAID => ['negative', __('Unpaid')],
        TourBookingStatus::Waitlisted->value => ['warning', __('Waitlist')],
        TourBookingStatus::Cancelled->value => ['muted', __('Cancelled')],
    ];
    $chip = 'inline-flex h-8 shrink-0 items-center gap-1.5 rounded-xl border px-2.5 text-[12px] font-semibold transition';
    $item = 'flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-[13px] font-medium text-ink transition hover:bg-surface-2';
@endphp

<section x-show="tab === 'bookings'" role="tabpanel" class="flex flex-col gap-3">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <label class="relative min-w-0 flex-1">
            <span class="sr-only">{{ __('Search bookings') }}</span>
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-faint" />
            <input type="search" x-model="query" placeholder="{{ __('Search by name or phone') }}" class="form-field pl-10">
        </label>
        <div class="flex gap-2">
            <x-btn type="button" icon="plus" class="flex-1 sm:flex-none" @click="$dispatch('tour-booking')">{{ __('Add booking') }}</x-btn>
            @if ($tour->registrations->isNotEmpty())
                <x-btn :href="route('tours.export', $tour)" variant="outline" icon="download" :title="__('Download the bookings as a spreadsheet')">
                    <span class="sr-only sm:not-sr-only">{{ __('Export') }}</span>
                </x-btn>
            @endif
        </div>
    </div>

    @if ($tour->registrations->isEmpty())
        <x-empty icon="users" :title="__('No bookings yet')" :text="__('Share the tour link in your Facebook group or Messenger. Bookings show up here the moment they come in.')" />
    @else
        <div class="-mx-3 flex gap-1.5 overflow-x-auto px-3 pb-0.5 no-scrollbar sm:mx-0 sm:px-0">
            @foreach ($filters as $key => $label)
                @php $count = $key === 'all' ? $states->count() : $states->filter(fn ($state) => $state === $key)->count(); @endphp
                @if ($key === 'all' || $count > 0)
                    <button type="button" @click="state = @js($key)"
                            class="{{ $chip }}" :class="state === @js($key) ? 'border-brand bg-brand text-brand-ink' : 'border-line bg-surface text-muted hover:text-ink'">
                        {{ $label }} <span class="num opacity-70">{{ $count }}</span>
                    </button>
                @endif
            @endforeach
        </div>

        <ul class="flex flex-col gap-2" x-ref="bookings">
            @foreach ($tour->registrations as $registration)
                @php
                    $state = $stateOf($registration);
                    $paid = $registration->paid();
                    $due = $registration->due();
                @endphp
                <li data-state="{{ $state }}" data-search="{{ mb_strtolower($registration->name.' '.$registration->phone.' '.Phone::pretty($registration->phone).' 0'.substr((string) $registration->phone, 4)) }}"
                    x-show="shows($el)" class="rounded-3xl border border-line bg-surface p-3.5 shadow-soft sm:p-4">
                    <div class="flex items-start gap-3">
                        <x-avatar :name="$registration->name" size="sm" />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <p class="truncate text-[14px] font-semibold text-ink">{{ $registration->name }}</p>
                                <x-badge :tone="$badges[$state][0]" size="sm">{{ $badges[$state][1] }}</x-badge>
                                @if ($registration->hasPendingPayment())
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-warning"><x-icon name="clock" class="size-3" /> {{ __('Payment to confirm') }}</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-[12px] text-muted">
                                {{ $registration->package?->name }}@if ($registration->quantity > 1) × <span class="num">{{ $registration->quantity }}</span>@endif
                                · {{ trans_choice(':count seat|:count seats', $registration->seats, ['count' => $registration->seats]) }}
                                · <a href="tel:{{ $registration->phone }}" class="num whitespace-nowrap hover:text-brand">{{ Phone::pretty($registration->phone) }}</a>
                            </p>
                            @if ($registration->note)
                                <p class="mt-1 line-clamp-2 text-[12px] italic text-faint">"{{ $registration->note }}"</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="num font-display text-[15px] font-semibold text-ink">{{ Money::format($registration->total, $tour->currency) }}</p>
                            <p class="num mt-0.5 text-[11.5px] {{ $due > 0 ? 'text-negative' : 'text-muted' }}">
                                {{ $due > 0 ? __(':amount due', ['amount' => Money::format($due, $tour->currency)]) : ($paid > 0 ? __(':amount paid', ['amount' => Money::format($paid, $tour->currency)]) : '') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-1.5 border-t border-line pt-3">
                        @if ($registration->isBooked() && $due > 0)
                            <button type="button" class="{{ $chip }} border-transparent bg-brand-tint text-brand hover:bg-brand hover:text-brand-ink"
                                    @click="$dispatch('tour-pay', { url: @js(route('tours.payments.store', [$tour, $registration])), name: @js($registration->name), amount: {{ $due }}, paid: {{ $paid }}, type: 'payment' })">
                                <x-icon name="wallet" class="size-3.5" /> {{ __('Record payment') }}
                            </button>
                        @endif

                        @if ($registration->status !== TourBookingStatus::Cancelled)
                            <form method="POST" action="{{ route('tours.bookings.remind', [$tour, $registration]) }}"
                                  onsubmit="return confirm(@js(__('Text :name their booking link? It costs about :price per text from your SMS wallet (balance :balance).', ['name' => $registration->firstName(), 'price' => Money::format($smsPrice), 'balance' => Money::format($smsBalance)])))">
                                @csrf
                                <button type="submit" class="{{ $chip }} border-line bg-surface text-muted hover:text-ink">
                                    <x-icon name="send" class="size-3.5" /> {{ $due > 0 ? __('Remind') : __('Text link') }}
                                </button>
                            </form>
                        @endif

                        @if ($registration->reminded_at)
                            <span class="hidden text-[11px] text-faint sm:inline">{{ __('Texted :time', ['time' => $registration->reminded_at->diffForHumans()]) }}</span>
                        @endif

                        {{-- Everything else about this booking --}}
                        <div class="relative ml-auto" x-data="{ more: false }" @click.outside="more = false" @keydown.escape="more = false">
                            <button type="button" @click="more = ! more" :aria-expanded="more" aria-label="{{ __('More for :name', ['name' => $registration->name]) }}"
                                    class="flex size-8 items-center justify-center rounded-xl text-muted transition hover:bg-surface-2 hover:text-ink">
                                <x-icon name="dots" class="size-4" />
                            </button>
                            <div x-show="more" x-cloak x-transition.origin.top.right
                                 class="absolute right-0 top-full z-20 mt-1 w-60 rounded-2xl border border-line bg-surface p-1.5 shadow-lift">
                                <div x-data="clipboard(@js($registration->url()), @js(__('Booking link copied')))">
                                    <button type="button" class="{{ $item }}" @click="copy(); more = false"><x-icon name="copy" class="size-4 text-faint" /> {{ __('Copy their booking link') }}</button>
                                </div>
                                <a href="{{ $registration->url() }}" target="_blank" rel="noopener" class="{{ $item }}"><x-icon name="arrow-up-right" class="size-4 text-faint" /> {{ __('Open their booking page') }}</a>

                                @if (! $registration->isBooked())
                                    <form method="POST" action="{{ route('tours.bookings.update', [$tour, $registration]) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="action" value="book">
                                        <button type="submit" class="{{ $item }}"><x-icon name="check-circle" class="size-4 text-positive" /> {{ __('Give them their seats') }}</button>
                                    </form>
                                @endif

                                @if ($paid > 0)
                                    <button type="button" class="{{ $item }}" @click="more = false; $dispatch('tour-pay', { url: @js(route('tours.payments.store', [$tour, $registration])), name: @js($registration->name), amount: {{ $registration->refundOwed() ?: $paid }}, paid: {{ $paid }}, type: 'refund' })">
                                        <x-icon name="refresh" class="size-4 text-faint" /> {{ __('Record a refund') }}
                                    </button>
                                @endif

                                @if ($registration->status !== TourBookingStatus::Cancelled)
                                    <form method="POST" action="{{ route('tours.bookings.update', [$tour, $registration]) }}"
                                          onsubmit="return confirm(@js(__('Cancel :name\'s booking? Their seats free up.', ['name' => $registration->name])))">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="{{ $item }} text-negative hover:bg-negative-tint"><x-icon name="ban" class="size-4" /> {{ __('Cancel booking') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <p x-show="! [...$refs.bookings.children].some((row) => shows(row))" x-cloak class="rounded-3xl border border-dashed border-line px-4 py-8 text-center text-[13px] text-muted">
            {{ __('No booking matches.') }}
        </p>
    @endif
</section>
