@php $unread = auth()->user()->unreadNotifications->count(); @endphp

<x-layouts.app title="{{ __('Alerts') }}" :active="'notifications'" :heading="__('Alerts')"
               :subtitle="__('Invites, new costs and payments across your trips.')" :back="route('dashboard')">

    <x-slot:actions>
        @if ($unread > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <x-btn type="submit" variant="secondary" size="sm" icon="check">{{ __('Mark all read') }}</x-btn>
            </form>
        @endif
    </x-slot:actions>

    @if ($notifications->isEmpty())
        <x-empty icon="bell" title="{{ __('No alerts yet') }}"
                 :text="__('When a cost is added, a payment recorded or a trip ends, you will see it here.')">
            <x-btn :href="route('dashboard')" size="sm" variant="secondary" icon="grid">{{ __('Back to dashboard') }}</x-btn>
        </x-empty>
    @else
        <x-card padding="p-1.5 sm:p-2">
            <ul class="flex flex-col gap-0.5">
                @foreach ($notifications as $notification)
                    @include('notifications.partials.item')
                @endforeach
            </ul>
        </x-card>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</x-layouts.app>
