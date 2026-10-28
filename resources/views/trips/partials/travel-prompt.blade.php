{{-- Offered to each traveller for the places this trip mentions that are not
     on their own travel map yet. "Not now" is remembered on this device. --}}
@if (! empty($travelPrompt))
    @php
        $travelMaps = app(\App\Services\TravelMaps::class);
        $placeNames = collect($travelPrompt)
            ->flatMap(fn (array $areas, string $key) => array_map(fn (string $id) => $travelMaps->get($key)->name($id), $areas))
            ->unique()
            ->values();
        $shown = $placeNames->take(4)->all();
        $more = $placeNames->count() - count($shown);
    @endphp

    <div x-data="{
             hidden: false,
             key: 'sofor.travel.trip.{{ $trip->id }}',
             init() {
                 try { this.hidden = localStorage.getItem(this.key) === 'hidden'; } catch (e) {}
             },
             dismiss() {
                 this.hidden = true;
                 try { localStorage.setItem(this.key, 'hidden'); } catch (e) {}
             },
         }"
         x-show="! hidden"
         class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-3xl border border-brand/25 bg-brand/8 px-4 py-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-brand/15 text-brand">
            <x-icon name="globe" class="size-4.5" />
        </span>

        <p class="min-w-0 flex-[1_1_13rem] text-[12.5px] leading-relaxed text-ink">
            <span class="font-semibold">{{ __('Your travel map:') }}</span>
            {{ __('this trip covers :places.', ['places' => \Illuminate\Support\Arr::join($shown, ', ', $more > 0 ? ', ' : ' '.__('and').' ').($more > 0 ? ' '.__('and :count more', ['count' => $more]) : '')]) }}
        </p>

        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('travel.from-trip', $trip) }}">
                @csrf
                <x-btn type="submit" size="xs" icon="plus">{{ __('Add to my map') }}</x-btn>
            </form>
            <button type="button" @click="dismiss()"
                    class="rounded-full px-2.5 py-1.5 text-[11px] font-medium text-muted transition hover:text-ink">
                {{ __('Not now') }}
            </button>
        </div>
    </div>
@endif
