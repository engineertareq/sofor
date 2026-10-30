<x-layouts.app :title="__('Edit :tour', ['tour' => $tour->title])" :active="'tours'" :heading="__('Edit tour')"
               :subtitle="$tour->title" :back="route('tours.show', $tour)">
    <div class="mx-auto max-w-3xl">
        @include('tours.partials.form', ['action' => route('tours.update', $tour)])
    </div>
</x-layouts.app>
