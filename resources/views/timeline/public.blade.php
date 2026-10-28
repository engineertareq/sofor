{{-- /timeline/{token}: a trip's cost timeline, shared by its leader with anyone
     who has the link. Never indexed; no receipts, no contact details. --}}
<x-layouts.site :title="__(':trip - trip timeline', ['trip' => $trip->title])" :card="false" :wide="true" :noindex="true"
                :description="__('Every cost of :trip, day by day, kept with Sofor.', ['trip' => $trip->title])">
    @include('trips.partials.timeline', ['trip' => $trip, 'timeline' => $timeline, 'public' => true, 'share' => null])
</x-layouts.site>
