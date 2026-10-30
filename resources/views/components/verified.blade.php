{{-- "Verified" or "Not verified" beside an email or phone number in the console.
     `at` is when it was verified (null if never); hover shows the date. --}}
@props(['at' => null])

<x-badge :tone="$at ? 'lime' : 'amber'" size="sm" :icon="$at ? 'check' : 'alert'"
         :title="$at ? 'Verified '.$at->format('d M Y') : 'Not verified yet'" class="shrink-0">
    {{ $at ? 'Verified' : 'Not verified' }}
</x-badge>
