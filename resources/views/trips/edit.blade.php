@php
    $covers = $covers ?? \App\Models\Trip::COVERS;
@endphp

<x-layouts.app :title="__('Edit :trip', ['trip' => $trip->title])" :active="'trips'" :heading="__('Trip details')"
               :subtitle="__('Update the plan for everyone in the ledger.')" :back="route('trips.show', $trip)">
    <form method="POST" action="{{ route('trips.update', $trip) }}" class="flex flex-col gap-6" x-data="{ cover: @js((string) old('cover', $trip->cover)), icon: @js((string) old('icon', $trip->icon)) }">
        @csrf
        @method('PUT')

        <x-card>
            <div class="grid gap-4">
                <x-field name="title" :label="__('Trip name')" required>
                    <x-input name="title" :value="old('title', $trip->title)" required />
                </x-field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="destination" :label="__('Destination')">
                        <x-input name="destination" icon="map-pin" :value="old('destination', $trip->destination)" />
                    </x-field>

                </div>

                <x-field name="icon" :label="__('Trip icon')" :hint="__('A quick visual tag for the trip')">
                    <input type="hidden" name="icon" x-model="icon">
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('Trip icon') }}">
                        @foreach (\App\Support\Glyphs::TRIP as $choice)
                            <button type="button" role="radio" @click="icon = '{{ $choice }}'"
                                    :aria-checked="(icon === '{{ $choice }}').toString()"
                                    :class="icon === '{{ $choice }}' ? 'border-ink bg-accent-tint shadow-sticker-sm' : 'border-line bg-surface-2 hover:border-line-strong'"
                                    title="{{ \App\Support\Glyphs::label($choice) }}" aria-label="{{ \App\Support\Glyphs::label($choice) }}"
                                    class="flex size-11 items-center justify-center rounded-2xl border-[1.5px] transition"><x-glyph :name="$choice" class="size-7" /></button>
                        @endforeach
                    </div>
                </x-field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="currency" :label="__('Currency')">
                        <x-select name="currency" :value="old('currency', $trip->currency)" :options="[
                            'BDT' => __('BDT - Bangladeshi Taka (৳)'),
                            'USD' => __('USD - US Dollar ($)'),
                            'INR' => __('INR - Indian Rupee (₹)'),
                            'AED' => __('AED - UAE Dirham'),
                            'MYR' => __('MYR - Malaysian Ringgit'),
                            'SGD' => __('SGD - Singapore Dollar'),
                            'THB' => __('THB - Thai Baht (฿)'),
                            'EUR' => __('EUR - Euro (€)'),
                            'GBP' => __('GBP - Pound Sterling (£)'),
                        ]" />
                    </x-field>

                    <x-field name="cover" :label="__('Cover style')">
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="cover" x-model="cover">
                            @foreach ($covers as $key => $gradient)
                                <button type="button" @click="cover = '{{ $key }}'"
                                        :class="cover === '{{ $key }}' ? 'ring-2 ring-brand ring-offset-2 ring-offset-surface' : 'opacity-70 hover:opacity-100'"
                                        :aria-pressed="cover === '{{ $key }}'"
                                        aria-label="{{ __('Cover style: :style', ['style' => \App\Models\Trip::coverLabel($key)]) }}"
                                        class="h-10 flex-1 rounded-xl border-[1.5px] border-line transition"
                                        style="background-image: {{ $gradient }}"
                                        title="{{ \App\Models\Trip::coverLabel($key) }}">
                                    <span class="sr-only">{{ \App\Models\Trip::coverLabel($key) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </x-field>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="starts_on" :label="__('Starts')">
                        <x-input name="starts_on" type="date" :value="old('starts_on', $trip->starts_on?->toDateString())" />
                    </x-field>
                    <x-field name="ends_on" :label="__('Ends')">
                        <x-input name="ends_on" type="date" :value="old('ends_on', $trip->ends_on?->toDateString())" />
                    </x-field>
                </div>

                <x-budget-field :trip="$trip" />

                <x-field name="description" :label="__('Notes for the group')">
                    <x-textarea name="description" rows="4" :value="old('description', $trip->description)" />
                </x-field>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-btn :href="route('trips.show', $trip)" variant="ghost">{{ __('Cancel') }}</x-btn>
                <x-btn type="submit" icon="check">{{ __('Save changes') }}</x-btn>
            </div>
        </x-card>
    </form>
</x-layouts.app>
