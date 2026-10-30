{{-- A tour's public page, /t/<code> (PublicTourController): what the tour is,
     what it costs and the form to book. Made to be shared in a Facebook group or
     on Messenger, so it reads well on a phone first; the booking form follows the
     reader down the page on a laptop, and a bar at the bottom of a phone leads to it.
     tourBooking (resources/js/tours.js) keeps the total and the waitlist warning. --}}
@php
    use App\Enums\TourStatus;
    use App\Support\Money;
    use App\Support\Phone;

    $currency = $tour->currency;
    $from = (float) $tour->packages->min('price');
    $open = $tour->takesRegistrations() || $preview;
    $full = $seatsLeft === 0;
    $days = $tour->starts_on && $tour->ends_on ? $tour->starts_on->diffInDays($tour->ends_on) + 1 : null;
    $dates = $tour->starts_on
        ? $tour->starts_on->translatedFormat('j M').($tour->ends_on && ! $tour->ends_on->isSameDay($tour->starts_on) ? ' - '.$tour->ends_on->translatedFormat('j M Y') : $tour->starts_on->translatedFormat(' Y'))
        : null;
    $closed = match (true) {
        $preview => null,
        $tour->status === TourStatus::Cancelled => __('This tour has been cancelled.'),
        $tour->status === TourStatus::Completed => __('This tour has already happened.'),
        ! $tour->takesRegistrations() => __('Registration for this tour is closed.'),
        default => null,
    };
    $packages = $tour->packages->map(fn ($package) => [
        'id' => $package->id,
        'price' => (float) $package->price,
        'seats' => $package->seats,
        'advance' => $package->advance !== null ? (float) $package->advance : null,
    ])->values();
    $summary = collect([$tour->destination, $dates, $from > 0 ? __('From :price', ['price' => Money::format($from, $currency)]) : null])->filter()->implode(' · ');
@endphp

<x-layouts.site :title="$tour->title" :description="$summary ?: \Illuminate\Support\Str::limit((string) $tour->description, 150)" :noindex="true" wide>
    <div class="mx-auto w-full max-w-6xl px-4 pb-28 pt-5 sm:px-6 sm:pt-8 lg:px-8 lg:pb-16">
        @if ($preview)
            <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-3xl border border-warning/40 bg-warning-tint px-4 py-3 text-[13px]">
                <x-icon name="eye" class="size-4 text-warning" />
                <span class="font-semibold text-ink">{{ __('Preview: only you can see this page until registration opens.') }}</span>
                <a href="{{ route('tours.show', $tour) }}" class="ml-auto font-semibold text-warning underline decoration-dotted underline-offset-2">{{ __('Back to the dashboard') }}</a>
            </div>
        @endif

        {{-- ===== The tour at a glance ===== --}}
        <section class="overflow-hidden rounded-4xl border border-line bg-surface shadow-soft">
            <div class="relative h-32 sm:h-44" style="background-image: {{ $tour->coverStyle() }}">
                <span class="absolute -bottom-7 left-5 flex size-16 items-center justify-center rounded-3xl border-[1.5px] border-line bg-surface shadow-soft sm:left-8 sm:size-20">
                    <x-glyph name="compass" class="size-11 sm:size-14" />
                </span>
            </div>
            <div class="px-5 pb-6 pt-10 sm:px-8 sm:pb-8">
                <p class="flex items-center gap-2 text-[12.5px] text-muted">
                    <x-avatar :name="$tour->organiser?->name" :gradient="$tour->organiser?->avatarGradient()" :src="$tour->organiser?->avatarUrl()" size="xs" />
                    {{ __('Organised by :name', ['name' => $tour->organiser?->name]) }}
                </p>
                <h1 class="mt-2 font-display text-[1.85rem] font-semibold leading-[1.1] tracking-tight text-ink sm:text-[2.6rem]">{{ $tour->title }}</h1>

                <div class="mt-4 flex flex-wrap gap-2 text-[12.5px]">
                    @if ($tour->destination)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-2 px-3 py-1.5 text-ink"><x-icon name="map-pin" class="size-3.5 text-brand" />{{ $tour->destination }}</span>
                    @endif
                    @if ($dates)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-2 px-3 py-1.5 text-ink">
                            <x-icon name="calendar" class="size-3.5 text-brand" />{{ $dates }}
                            @if ($days && $days > 1)<span class="text-muted">· {{ trans_choice(':count day|:count days', $days, ['count' => $days]) }}</span>@endif
                        </span>
                    @endif
                    @if ($seatsLeft !== null && $tour->takesRegistrations())
                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-semibold',
                            'bg-negative-tint text-negative' => $full,
                            'bg-warning-tint text-warning' => ! $full && $seatsLeft <= 5,
                            'bg-positive-tint text-positive' => $seatsLeft > 5,
                        ])>
                            <x-icon name="users" class="size-3.5" />
                            {{ $full ? __('Full - join the waitlist') : trans_choice(':count seat left|:count seats left', $seatsLeft, ['count' => $seatsLeft]) }}
                        </span>
                    @endif
                </div>
            </div>
        </section>

        <div class="mt-5 grid gap-5 lg:mt-6 lg:grid-cols-[minmax(0,1fr)_25rem] lg:items-start lg:gap-6">
            <div class="flex flex-col gap-5">
                @if (filled($tour->description))
                    <x-card padding="p-5 sm:p-6">
                        <h2 class="font-display text-[17px] font-semibold text-ink">{{ __('About the tour') }}</h2>
                        <div class="mt-3 text-[14px] leading-relaxed text-muted">{!! nl2br(e($tour->description)) !!}</div>
                    </x-card>
                @endif

                <x-card padding="p-5 sm:p-6">
                    <h2 class="font-display text-[17px] font-semibold text-ink">{{ __('Details') }}</h2>
                    <dl class="mt-3 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        @foreach (array_filter([
                            ['calendar', __('When'), $dates ? $dates.($days && $days > 1 ? ' · '.trans_choice(':count day|:count days', $days, ['count' => $days]) : '') : null],
                            ['pin', __('Meeting point'), $tour->meeting_point],
                            ['clock', __('Meeting time'), $tour->meeting_at ? \App\Support\Bangla::moment($tour->meeting_at) : null],
                            ['map-pin', __('Destination'), $tour->destination],
                        ], fn ($row) => filled($row[2])) as [$icon, $label, $value])
                            <div class="flex items-start gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-tint text-brand"><x-icon :name="$icon" class="size-4" /></span>
                                <div class="min-w-0">
                                    <dt class="text-[12px] text-faint">{{ $label }}</dt>
                                    <dd class="text-[14px] font-medium text-ink">{{ $value }}</dd>
                                </div>
                            </div>
                        @endforeach
                        @if ($tour->contact_phone)
                            <div class="flex items-start gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-positive-tint text-positive"><x-icon name="phone" class="size-4" /></span>
                                <div class="min-w-0">
                                    <dt class="text-[12px] text-faint">{{ __('Questions? Call') }}</dt>
                                    <dd><a href="tel:{{ $tour->contact_phone }}" class="num text-[14px] font-semibold text-brand hover:underline">{{ Phone::pretty($tour->contact_phone) }}</a></dd>
                                </div>
                            </div>
                        @endif
                    </dl>
                </x-card>

                <x-card padding="p-5 sm:p-6">
                    <h2 class="font-display text-[17px] font-semibold text-ink">{{ __('Packages') }}</h2>
                    <ul class="mt-3 flex flex-col gap-2.5">
                        @foreach ($tour->packages as $package)
                            <li class="flex items-start justify-between gap-4 rounded-3xl border border-line bg-surface-2 p-4">
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2 font-semibold text-ink">
                                        {{ $package->name }}
                                        @if ($package->seats > 1)
                                            <span class="rounded-full bg-brand-tint px-2 py-0.5 text-[11px] font-semibold text-brand">{{ trans_choice(':count seat|:count seats', $package->seats, ['count' => $package->seats]) }}</span>
                                        @endif
                                    </p>
                                    @if ($package->description)
                                        <p class="mt-0.5 text-[13px] text-muted">{{ $package->description }}</p>
                                    @endif
                                    @if ($package->advance !== null && (float) $package->advance < (float) $package->price)
                                        <p class="mt-1 text-[12px] text-faint">{{ __(':amount to hold your booking', ['amount' => Money::format($package->advance, $currency)]) }}</p>
                                    @endif
                                </div>
                                <p class="num shrink-0 font-display text-[19px] font-semibold text-ink">{{ Money::format($package->price, $currency) }}</p>
                            </li>
                        @endforeach
                    </ul>
                </x-card>

                <x-card padding="p-5 sm:p-6">
                    <h2 class="font-display text-[17px] font-semibold text-ink">{{ __('How booking works') }}</h2>
                    <ol class="mt-3 flex flex-col gap-3">
                        @foreach ([
                            ['users', __('Book here with your name and mobile number. No account or app needed.')],
                            ['wallet', __('Pay the organiser directly with bKash, Nagad, bank or cash. Your booking page shows where.')],
                            ['check-circle', __('Your booking page keeps every payment. The organiser confirms each one.')],
                        ] as $i => [$icon, $text])
                            <li class="flex items-start gap-3 text-[13.5px] leading-relaxed text-muted">
                                <span class="num flex size-7 shrink-0 items-center justify-center rounded-full bg-ink text-[12px] font-bold text-canvas">{{ $i + 1 }}</span>
                                <span class="pt-0.5">{{ $text }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-4 flex items-start gap-2 rounded-2xl bg-surface-2 p-3 text-[12px] leading-relaxed text-faint">
                        <x-icon name="shield" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                        {{ __('Sofor never holds your money. It only keeps the record, so you and the organiser see the same numbers.') }}
                    </p>
                </x-card>
            </div>

            {{-- ===== Booking ===== --}}
            <aside id="book" class="scroll-mt-20 lg:sticky lg:top-24">
                @if ($closed)
                    <div class="rounded-4xl border border-line bg-surface p-6 text-center shadow-soft">
                        <span class="mx-auto flex size-14 items-center justify-center rounded-3xl bg-surface-2 text-muted"><x-icon name="lock" class="size-6" /></span>
                        <p class="mt-4 font-display text-[17px] font-semibold text-ink">{{ $closed }}</p>
                        <p class="mt-1 text-[13px] text-muted">{{ __('Already booked? Your booking page is still there, from the link you got when you booked.') }}</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('tours.register', $tour->code) }}"
                          class="rounded-4xl border-2 border-ink bg-surface p-5 shadow-sticker sm:p-6"
                          x-data="tourBooking({ packages: @js($packages), seatsLeft: @js($seatsLeft), selected: @js(old('tour_package_id') ? (int) old('tour_package_id') : null), quantity: @js((int) old('quantity', 1)) })">
                        @csrf
                        <h2 class="font-display text-[20px] font-semibold text-ink">{{ __('Book your seat') }}</h2>

                        <fieldset class="mt-4">
                            <legend class="mb-2 text-[13px] font-medium text-muted">{{ __('Package') }}</legend>
                            <div class="flex flex-col gap-2">
                                @foreach ($tour->packages as $package)
                                    <label class="relative cursor-pointer">
                                        <input type="radio" name="tour_package_id" value="{{ $package->id }}" x-model.number="packageId" class="peer sr-only" required>
                                        <span class="flex items-center justify-between gap-3 rounded-2xl border-[1.5px] border-line bg-surface px-3.5 py-3 transition peer-checked:border-brand peer-checked:bg-brand-tint/50 peer-focus-visible:ring-2 peer-focus-visible:ring-brand/40">
                                            <span class="min-w-0">
                                                <span class="block truncate text-[14px] font-semibold text-ink">{{ $package->name }}</span>
                                                @if ($package->seats > 1)
                                                    <span class="block text-[11.5px] text-muted">{{ trans_choice(':count seat|:count seats', $package->seats, ['count' => $package->seats]) }}</span>
                                                @endif
                                            </span>
                                            <span class="num shrink-0 font-display text-[16px] font-semibold text-ink">{{ Money::format($package->price, $currency) }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('tour_package_id')<p class="mt-1.5 text-xs font-medium text-negative">{{ $message }}</p>@enderror
                        </fieldset>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <span class="text-[13px] font-medium text-muted">{{ __('How many') }}</span>
                            <div class="flex items-center rounded-2xl border-[1.5px] border-line-strong">
                                <button type="button" @click="step(-1)" :disabled="quantity <= 1" aria-label="{{ __('One less') }}"
                                        class="flex size-10 items-center justify-center text-muted transition hover:text-ink disabled:opacity-30"><x-icon name="minus" class="size-4" /></button>
                                <input type="number" name="quantity" min="1" max="10" x-model.number="quantity" inputmode="numeric" aria-label="{{ __('How many') }}"
                                       class="num w-10 border-0 bg-transparent text-center text-[15px] font-semibold text-ink focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" @click="step(1)" :disabled="quantity >= 10" aria-label="{{ __('One more') }}"
                                        class="flex size-10 items-center justify-center text-muted transition hover:text-ink disabled:opacity-30"><x-icon name="plus" class="size-4" /></button>
                            </div>
                        </div>

                        <div class="mt-4 flex items-baseline justify-between border-t border-dashed border-line pt-4">
                            <span class="text-[13px] font-semibold text-ink">{{ __('Total') }}</span>
                            <span class="num font-display text-[24px] font-semibold text-ink" x-text="totalLabel">{{ Money::format($from, $currency) }}</span>
                        </div>
                        <p x-show="waitlist" x-cloak class="mt-2 flex items-start gap-1.5 rounded-2xl bg-warning-tint px-3 py-2 text-[12.5px] text-warning">
                            <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                            {{ __('Not enough seats left for this. Your booking joins the waitlist and gets seats if they free up.') }}
                        </p>

                        <div class="mt-4 flex flex-col gap-3">
                            <x-field name="name" :label="__('Your name')" required>
                                <x-input name="name" maxlength="80" required autocomplete="name" />
                            </x-field>
                            <x-field name="phone" :label="__('Mobile number')" required :hint="__('The organiser will reach you on this number.')">
                                <x-input name="phone" type="tel" required autocomplete="tel" placeholder="01XXX-XXXXXX" inputmode="tel" />
                            </x-field>
                            <x-field name="email" :label="__('Email')" :hint="__('Optional. We send your booking link here too.')">
                                <x-input name="email" type="email" maxlength="160" autocomplete="email" />
                            </x-field>
                            <x-field name="note" :label="__('Anything the organiser should know')">
                                <x-input name="note" maxlength="300" :placeholder="__('Names of the others, food needs...')" />
                            </x-field>
                            {{-- Left empty by people; bots fill it in. --}}
                            <div class="hidden" aria-hidden="true">
                                <label for="website">Website</label>
                                <input id="website" name="website" tabindex="-1" autocomplete="off">
                            </div>
                        </div>

                        @if ($preview)
                            <p class="mt-4 rounded-2xl bg-surface-2 px-3 py-2.5 text-center text-[12.5px] text-muted">{{ __('Bookings open when you open registration.') }}</p>
                        @else
                            <x-btn type="submit" block size="lg" icon="check" class="mt-5">
                                <span x-text="waitlist ? @js(__('Join the waitlist')) : @js(__('Book now'))">{{ __('Book now') }}</span>
                            </x-btn>
                            <p class="mt-3 text-center text-[11.5px] leading-relaxed text-faint">{{ __('You pay the organiser directly. Your booking page shows where and how.') }}</p>
                        @endif
                    </form>
                @endif
            </aside>
        </div>
    </div>

    {{-- A phone's way to the form, always in reach --}}
    @if (! $closed && ! $preview)
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-canvas/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur lg:hidden">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[11.5px] text-muted">{{ __('From') }}</p>
                    <p class="num truncate font-display text-[18px] font-semibold leading-tight text-ink">{{ Money::format($from, $currency) }}</p>
                </div>
                <x-btn href="#book" icon="check">{{ $full ? __('Join the waitlist') : __('Book now') }}</x-btn>
            </div>
        </div>
    @endif
</x-layouts.site>
