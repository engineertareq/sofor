<x-layouts.app :title="__('New tour')" :active="'tours'" :heading="__('New tour')"
               :subtitle="__('People book from one link and pay you directly.')" :back="route('tours.index')">
    <div class="mx-auto max-w-3xl">
        @include('tours.partials.form', ['action' => route('tours.store')])
    </div>
</x-layouts.app>
