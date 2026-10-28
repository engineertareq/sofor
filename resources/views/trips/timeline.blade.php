<x-layouts.app title="{{ __('Trip timeline') }}" :active="'trips'" :wide="true" :heading="__('Trip timeline')" :subtitle="$trip->title" :back="route('trips.show', $trip)">
    @include('trips.partials.timeline', ['trip' => $trip, 'timeline' => $timeline, 'public' => false, 'share' => 'trips.partials.timeline-share'])
</x-layouts.app>
