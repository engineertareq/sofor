{{-- The organiser's dashboard for one tour: its link and status, the numbers,
     payments waiting to be confirmed, then bookings, payments, costs and the
     account under tabs (tourDashboard in resources/js/tours.js). Every action
     is a plain form; the sheets that hold them are in tours/partials/sheets. --}}
@php
    use App\Enums\TourStatus;
    use App\Support\Money;

    $currency = $tour->currency;
    $steps = [
        'open' => ['label' => $tour->status === TourStatus::Draft ? __('Open registration') : __('Reopen registration'), 'icon' => 'play'],
        'closed' => ['label' => __('Close registration'), 'icon' => 'lock'],
        'completed' => ['label' => __('Mark as completed'), 'icon' => 'check-circle'],
        'cancelled' => ['label' => __('Cancel tour'), 'icon' => 'ban'],
    ];
    $primary = collect($next)->first(fn ($step) => in_array($step, ['open', 'closed'], true));
    $others = collect($next)->reject(fn ($step) => $step === $primary)->values();
    $balanceTone = $account['balance'] < 0 ? 'negative' : 'positive';
@endphp

<x-layouts.app :title="$tour->title" :active="'tours'" :heading="$tour->title" :subtitle="__('Organised tour')" :back="route('tours.index')" wide>
    <x-slot:actions>
        <x-btn :href="route('tours.edit', $tour)" variant="outline" size="sm" icon="pencil" :title="__('Edit tour')" class="max-sm:w-9 max-sm:px-0">
            <span class="sr-only sm:not-sr-only">{{ __('Edit') }}</span>
        </x-btn>
    </x-slot:actions>

    <div class="flex flex-col gap-4 sm:gap-5" x-data="tourDashboard({ key: {{ $tour->id }} })">

        {{-- ===== The tour, its link and where it stands ===== --}}
        <section class="overflow-hidden rounded-4xl border border-line bg-surface shadow-soft">
            <div class="h-12 sm:h-14" style="background-image: {{ $tour->coverStyle() }}"></div>
            <div class="p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-2 text-[12px] text-muted">
                    <x-badge :tone="$tour->status->tone()">{{ $tour->status->label() }}</x-badge>
                    @if ($tour->starts_on)
                        <span class="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2.5 py-1"><x-icon name="calendar" class="size-3.5 text-brand" />{{ $tour->starts_on->translatedFormat('j M') }}@if ($tour->ends_on && ! $tour->ends_on->isSameDay($tour->starts_on)) - {{ $tour->ends_on->translatedFormat('j M Y') }}@endif</span>
                    @endif
                    @if ($tour->destination)
                        <span class="inline-flex max-w-full items-center gap-1 rounded-full bg-surface-2 px-2.5 py-1"><x-icon name="map-pin" class="size-3.5 shrink-0 text-brand" /><span class="truncate">{{ $tour->destination }}</span></span>
                    @endif
                    @if ($tour->meeting_at)
                        <span class="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2.5 py-1"><x-icon name="clock" class="size-3.5 text-brand" />{{ \App\Support\Bangla::moment($tour->meeting_at) }}</span>
                    @endif
                    @if ($tour->closes_on && $tour->status === TourStatus::Open)
                        <span class="inline-flex items-center gap-1 rounded-full bg-warning-tint px-2.5 py-1 text-warning">{{ __('Bookings close :date', ['date' => $tour->closes_on->translatedFormat('j M')]) }}</span>
                    @endif
                </div>

                {{-- The link to share --}}
                <div class="mt-4" x-data="clipboard(@js($tour->publicUrl()), @js(__('Tour link copied')))">
                    <p class="mb-1.5 text-[12px] font-semibold text-muted">
                        {{ $tour->status === TourStatus::Draft ? __('Tour page (only you can see it until registration opens)') : __('Share this link to take bookings') }}
                    </p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <div class="flex min-w-0 flex-1 items-center gap-2 rounded-2xl border border-line bg-surface-2 px-3 py-2.5">
                            <x-icon name="link" class="size-4 shrink-0 text-brand" />
                            <span class="truncate font-mono text-[12.5px] text-ink">{{ $tour->publicUrl() }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 sm:flex">
                            <x-btn type="button" size="sm" icon="copy" @click="copy()">
                                <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                            </x-btn>
                            {{-- @js is not compiled inside a component's attributes; Js::from in an echo is. --}}
                            <x-btn type="button" size="sm" variant="outline" icon="share"
                                   @click="navigator.share ? navigator.share({ title: {{ \Illuminate\Support\Js::from($tour->title) }}, url: {{ \Illuminate\Support\Js::from($tour->publicUrl()) }} }).catch(() => {}) : copy()">{{ __('Share') }}</x-btn>
                            <x-btn :href="$tour->publicUrl()" target="_blank" rel="noopener" size="sm" variant="outline" icon="arrow-up-right">{{ __('Open') }}</x-btn>
                        </div>
                    </div>
                </div>

                {{-- Where it goes next --}}
                @if ($next !== [] || $account['bookings'] + $account['waitlisted'] + $account['cancelled'] === 0)
                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-4">
                        @if ($primary)
                            <form method="POST" action="{{ route('tours.status', $tour) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $primary }}">
                                <x-btn type="submit" size="sm" :variant="$primary === 'open' ? 'primary' : 'outline'" :icon="$steps[$primary]['icon']">{{ $steps[$primary]['label'] }}</x-btn>
                            </form>
                        @endif
                        @foreach ($others as $step)
                            <form method="POST" action="{{ route('tours.status', $tour) }}"
                                  @if ($step === 'cancelled') onsubmit="return confirm(@js(__('Cancel this tour? Bookings stay so you can record refunds.')))" @endif>
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $step }}">
                                <x-btn type="submit" size="sm" variant="ghost" :icon="$steps[$step]['icon']" :class="$step === 'cancelled' ? 'hover:text-negative' : ''">{{ $steps[$step]['label'] }}</x-btn>
                            </form>
                        @endforeach
                        @if ($account['bookings'] + $account['waitlisted'] + $account['cancelled'] === 0)
                            <form method="POST" action="{{ route('tours.destroy', $tour) }}" class="ml-auto"
                                  onsubmit="return confirm(@js(__('Delete this tour? This cannot be undone.')))">
                                @csrf @method('DELETE')
                                <x-btn type="submit" size="sm" variant="ghost" icon="trash" class="hover:text-negative">{{ __('Delete') }}</x-btn>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </section>

        {{-- ===== The numbers ===== --}}
        <section class="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-4">
            <x-stat :label="__('Seats booked')" icon="users" tone="brand"
                    :value="$account['capacity'] ? $account['seats'].' / '.$account['capacity'] : (string) $account['seats']"
                    :hint="$account['waitlisted'] > 0 ? trans_choice(':count on the waitlist|:count on the waitlist', $account['waitlisted'], ['count' => $account['waitlisted']]) : ($account['seats_left'] !== null ? trans_choice(':count seat left|:count seats left', $account['seats_left'], ['count' => $account['seats_left']]) : __('No seat limit'))">
                @if ($account['capacity'])
                    <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-surface-2">
                        <div class="h-full rounded-full bg-brand" style="width: {{ min(100, round($account['seats'] / max(1, $account['capacity']) * 100)) }}%"></div>
                    </div>
                @endif
            </x-stat>
            <x-stat :label="__('Received')" icon="wallet" tone="positive" :value="Money::format($account['received'], $currency)"
                    :hint="$account['outstanding'] > 0 ? __(':amount still due', ['amount' => Money::format($account['outstanding'], $currency)]) : __('Nothing left to collect')" />
            <x-stat :label="__('Costs')" icon="receipt" tone="warning" :value="Money::format($account['spent'], $currency)"
                    :hint="trans_choice(':count cost logged|:count costs logged', $tour->expenses->count(), ['count' => $tour->expenses->count()])" />
            <x-stat :label="__('Balance')" icon="chart-pie" :tone="$balanceTone" :value="($account['balance'] < 0 ? '-' : '').Money::format(abs($account['balance']), $currency)"
                    :hint="__('Once all is paid: :amount', ['amount' => Money::format($account['projected'], $currency)])" />
        </section>

        {{-- ===== Payments waiting for the organiser ===== --}}
        @if ($account['pending']->isNotEmpty())
            <section class="rounded-4xl border border-warning/40 bg-warning-tint/60 p-4 sm:p-5">
                <h2 class="flex items-center gap-2 font-display text-[15px] font-semibold text-ink">
                    <x-icon name="wallet" class="size-4.5 text-warning" />
                    {{ trans_choice(':count payment to confirm|:count payments to confirm', $account['pending']->count(), ['count' => $account['pending']->count()]) }}
                </h2>
                <p class="mt-0.5 text-[12.5px] text-muted">{{ __('Find each one in your bKash, Nagad or bank before you confirm it.') }}</p>
                <ul class="mt-3 flex flex-col gap-2">
                    @foreach ($account['pending'] as $payment)
                        <li class="flex flex-col gap-3 rounded-3xl border border-line bg-surface p-3.5 sm:flex-row sm:items-center">
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-baseline gap-x-2 text-[13.5px]">
                                    <span class="font-semibold text-ink">{{ $payment->registration->name }}</span>
                                    <span class="num font-display text-[16px] font-semibold text-ink">{{ Money::format($payment->amount, $currency) }}</span>
                                </p>
                                <p class="mt-0.5 text-[12px] text-muted">
                                    {{ $payment->method->label() }}
                                    @if ($payment->reference) · <span class="font-mono">{{ $payment->reference }}</span> @endif
                                    @if ($payment->sender) · {{ __('from :number', ['number' => $payment->sender]) }} @endif
                                    · {{ $payment->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('tours.payments.confirm', [$tour, $payment]) }}" class="flex-1 sm:flex-none">
                                    @csrf
                                    <x-btn type="submit" size="sm" icon="check" block>{{ __('Received') }}</x-btn>
                                </form>
                                <form method="POST" action="{{ route('tours.payments.reject', [$tour, $payment]) }}" class="flex-1 sm:flex-none"
                                      onsubmit="return confirm(@js(__('Mark this payment as not received? :name will see it.', ['name' => $payment->registration->firstName()])))">
                                    @csrf
                                    <x-btn type="submit" size="sm" variant="outline" icon="close" block>{{ __('Not received') }}</x-btn>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- ===== Tabs ===== --}}
        @php
            $tabs = [
                'bookings' => [__('Bookings'), $tour->registrations->count()],
                'payments' => [__('Payments'), $payments->count()],
                'costs' => [__('Costs'), $tour->expenses->count()],
                'account' => [__('Account'), null],
            ];
        @endphp
        <div role="tablist" aria-label="{{ __('Tour') }}" class="grid grid-cols-4 gap-1 rounded-2xl border border-line bg-surface-2 p-1">
            @foreach ($tabs as $key => [$label, $count])
                <button type="button" role="tab" @click="tab = @js($key)" :aria-selected="tab === @js($key)"
                        class="flex h-10 min-w-0 items-center justify-center gap-1.5 rounded-xl px-1 text-[12.5px] font-semibold transition sm:text-[13px]"
                        :class="tab === @js($key) ? 'bg-surface text-ink shadow-[0_1px_2px_rgb(24_24_27/0.1)]' : 'text-muted hover:text-ink'">
                    <span class="truncate">{{ $label }}</span>
                    @if ($count !== null)
                        <span class="num hidden rounded-full bg-canvas-tint px-1.5 text-[10.5px] leading-4 text-faint min-[420px]:inline">{{ $count }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        @include('tours.partials.bookings')
        @include('tours.partials.payments')
        @include('tours.partials.costs')

        <section x-show="tab === 'account'" x-cloak role="tabpanel">
            @include('tours.partials.account', ['forOrganiser' => true])
        </section>
    </div>

    @include('tours.partials.sheets')
</x-layouts.app>
