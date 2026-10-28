<x-layouts.app title="{{ __('Trip recap') }}" :active="'trips'" :heading="__('Trip recap')"
               :subtitle="$trip->title" :back="route('trips.show', $trip)">

    <div class="grid gap-6 lg:grid-cols-[minmax(0,26rem)_1fr] lg:items-start" x-data="recapCard(@js(\Illuminate\Support\Str::slug($trip->title) ?: 'trip'))">
        <div class="overflow-hidden rounded-[1.75rem] shadow-sheet ring-1 ring-black/10" x-ref="card">
            {{-- The shareable image is drawn in English: its embedded fonts are Latin only. --}}
            @php($pageLocale = app()->getLocale())
            @php(app()->setLocale('en'))
            @include('trips.partials.recap-svg', ['svgClass' => 'block h-auto w-full'])
            @php(app()->setLocale($pageLocale))
        </div>

        <x-card>
            <h2 class="font-display text-lg font-semibold">{{ __('Share how it went') }}</h2>
            <p class="mt-1 text-[13px] leading-relaxed text-muted">
                {{ __('A travel poster of the trip: where you went on the map, what it cost, per person and per day, and where the money went. There are no names on it, so it is safe to post anywhere.') }}
            </p>

            <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                <x-btn type="button" icon="share" @click="share()" x-show="canShare" x-cloak>{{ __('Share image') }}</x-btn>
                <x-btn type="button" :variant="'secondary'" icon="download" @click="download()">
                    <span x-text="busy ? @js(__('Preparing...')) : @js(__('Download PNG'))">{{ __('Download PNG') }}</span>
                </x-btn>
            </div>

            <ul class="mt-6 flex flex-col gap-2 text-[12px] text-muted">
                <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-positive" /> {{ __('1080 x 1350, the size Instagram and Facebook like') }}</li>
                <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-positive" /> {{ __('Updates as costs are added - make it again after the trip') }}</li>
            </ul>
        </x-card>
    </div>
</x-layouts.app>
