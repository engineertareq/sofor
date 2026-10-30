{{-- The organiser's forms, each a sheet opened by an event from the dashboard
     (tours/show): tour-pay with the booking's details, tour-booking, tour-cost. --}}
@php
    use App\Models\TourPayment;
    use App\Support\Money;

    $packageOptions = $tour->packages->mapWithKeys(fn ($package) => [$package->id => $package->name.' - '.Money::format($package->price, $tour->currency)])->all();
@endphp

{{-- A payment received, or a refund sent --}}
<div x-data="{ open: false, ctx: { url: '', name: '', amount: 0, paid: 0, type: 'payment' } }"
     @tour-pay.window="ctx = $event.detail; open = true; $nextTick(() => $refs.amount?.focus())">
    <template x-teleport="body">
        <x-sheet :title="__('Record money')" icon="wallet">
            <form method="POST" :action="ctx.url" class="flex flex-col gap-4">
                @csrf
                <p class="text-[13px] text-muted"><span x-text="ctx.type === 'refund' ? @js(__('Refund to')) : @js(__('Payment from'))"></span> <span class="font-semibold text-ink" x-text="ctx.name"></span></p>

                <div class="grid grid-cols-2 gap-1 rounded-2xl bg-surface-2 p-1" role="radiogroup">
                    @foreach ([TourPayment::TYPE_PAYMENT => __('Payment received'), TourPayment::TYPE_REFUND => __('Refund sent')] as $type => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="{{ $type }}" class="peer sr-only" x-model="ctx.type">
                            <span class="flex h-10 items-center justify-center rounded-xl text-[13px] font-semibold text-muted transition peer-checked:bg-surface peer-checked:text-ink peer-checked:shadow-[0_1px_2px_rgb(24_24_27/0.1)]">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p x-show="ctx.type === 'refund'" class="-mt-2 text-[12px] text-muted">{{ __('They have paid') }} <span class="num font-semibold text-ink" x-text="'৳' + SoforNum(ctx.paid)"></span>.</p>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="amount" for="pay-amount" :label="__('Amount')">
                        <input id="pay-amount" name="amount" type="number" step="0.01" min="1" required inputmode="decimal" class="form-field num" x-ref="amount" :value="ctx.amount">
                    </x-field>
                    <x-field name="method" for="pay-method" :label="__('How')">
                        <x-select name="method" id="pay-method" :options="$methods" value="bkash" />
                    </x-field>
                    <x-field name="reference" for="pay-reference" :label="__('Transaction ID')">
                        <x-input name="reference" id="pay-reference" maxlength="80" placeholder="e.g. 8N7A2K1Q" />
                    </x-field>
                    <x-field name="paid_on" for="pay-paid_on" :label="__('Date')">
                        <x-input name="paid_on" id="pay-paid_on" type="date" :value="now()->toDateString()" />
                    </x-field>
                </div>
                <x-field name="note" for="pay-note" :label="__('Note')">
                    <x-input name="note" id="pay-note" maxlength="240" :placeholder="__('Optional')" />
                </x-field>
                <x-btn type="submit" icon="check" block>{{ __('Save') }}</x-btn>
            </form>
        </x-sheet>
    </template>
</div>

{{-- A booking taken by the organiser: over the phone, or cash at the office --}}
<div x-data="{ open: false }" @tour-booking.window="open = true; $nextTick(() => $refs.bookingName?.focus())">
    <template x-teleport="body">
        <x-sheet :title="__('Add a booking')" :subtitle="__('For someone who booked over the phone or in person')" icon="users">
            <form method="POST" action="{{ route('tours.bookings.store', $tour) }}" class="flex flex-col gap-4">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="name" for="booking-name" :label="__('Name')" required>
                        <input id="booking-name" name="name" maxlength="80" required class="form-field" x-ref="bookingName" value="{{ old('name') }}">
                    </x-field>
                    <x-field name="phone" for="booking-phone" :label="__('Mobile number')" required>
                        <x-input name="phone" id="booking-phone" type="tel" required placeholder="01XXX-XXXXXX" />
                    </x-field>
                    <x-field name="tour_package_id" for="booking-tour_package_id" :label="__('Package')" required>
                        <x-select name="tour_package_id" id="booking-tour_package_id" :options="$packageOptions" :value="$tour->packages->first()?->id" />
                    </x-field>
                    <x-field name="quantity" for="booking-quantity" :label="__('How many')">
                        <x-input name="quantity" id="booking-quantity" type="number" min="1" max="10" value="1" required inputmode="numeric" />
                    </x-field>
                    <x-field name="email" for="booking-email" :label="__('Email')" class="sm:col-span-2">
                        <x-input name="email" id="booking-email" type="email" maxlength="160" :placeholder="__('Optional')" />
                    </x-field>
                </div>
                <x-field name="note" for="booking-note" :label="__('Note')">
                    <x-input name="note" id="booking-note" maxlength="300" :placeholder="__('Names of the others, food needs...')" />
                </x-field>
                <div class="grid gap-3 rounded-3xl border border-line bg-surface-2 p-3.5 sm:grid-cols-2">
                    <x-field name="paid" for="booking-paid" :label="__('Paid now')" :hint="__('Leave empty if nothing is paid yet.')">
                        <x-input name="paid" id="booking-paid" type="number" min="0" step="0.01" inputmode="decimal" />
                    </x-field>
                    <x-field name="method" for="booking-method" :label="__('How')">
                        <x-select name="method" id="booking-method" :options="$methods" value="cash" />
                    </x-field>
                </div>
                <x-btn type="submit" icon="check" block>{{ __('Add booking') }}</x-btn>
            </form>
        </x-sheet>
    </template>
</div>

{{-- A cost of the tour --}}
<div x-data="{ open: false }" @tour-cost.window="open = true; $nextTick(() => $refs.costTitle?.focus())">
    <template x-teleport="body">
        <x-sheet :title="__('Add a cost')" icon="receipt">
            <form method="POST" action="{{ route('tours.costs.store', $tour) }}" class="flex flex-col gap-4">
                @csrf
                <x-field name="title" for="cost-title" :label="__('What for')" required>
                    <input id="cost-title" name="title" maxlength="120" required class="form-field" x-ref="costTitle" placeholder="{{ __('e.g. Bus fare, both ways') }}">
                </x-field>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="amount" for="cost-amount" :label="__('Amount')" required>
                        <x-input name="amount" id="cost-amount" type="number" min="1" step="0.01" required inputmode="decimal" />
                    </x-field>
                    <x-field name="category" for="cost-category" :label="__('Kind')">
                        <x-select name="category" id="cost-category" :options="$categories" value="transport" />
                    </x-field>
                    <x-field name="spent_on" for="cost-spent_on" :label="__('Date')">
                        <x-input name="spent_on" id="cost-spent_on" type="date" :value="now()->toDateString()" required />
                    </x-field>
                    <x-field name="note" for="cost-note" :label="__('Note')">
                        <x-input name="note" id="cost-note" maxlength="240" :placeholder="__('Optional')" />
                    </x-field>
                </div>
                <x-btn type="submit" icon="check" block>{{ __('Add cost') }}</x-btn>
            </form>
        </x-sheet>
    </template>
</div>
