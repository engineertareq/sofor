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
        <div class="flex flex-col gap-3">
            @foreach ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $isUnread = $notification->read_at === null;
                    $tone = match ($data['tone'] ?? 'info') {
                        'success' => 'lime',
                        'warning' => 'amber',
                        'error' => 'rose',
                        default => 'brand',
                    };
                @endphp

                <div class="flex items-start gap-4 rounded-4xl border p-4 transition
                            {{ $isUnread ? 'border-brand/30 bg-brand/6' : 'border-line bg-surface' }}">
                    <span @class([
                        'flex size-10 shrink-0 items-center justify-center rounded-2xl',
                        'bg-brand/12 text-brand' => $tone === 'brand',
                        'bg-positive-tint text-positive' => $tone === 'lime',
                        'bg-warning-tint text-warning' => $tone === 'amber',
                        'bg-negative-tint text-negative' => $tone === 'rose',
                    ])>
                        <x-icon :name="$data['icon'] ?? 'bell'" class="size-4.5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[13px] font-semibold text-ink">{{ $data['title'] ?? __('Sofor update') }}</p>
                            @if ($isUnread)
                                <span class="rounded-full bg-brand/15 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-brand">{{ __('New') }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-[12px] leading-relaxed text-muted">{{ $data['body'] ?? '' }}</p>
                        <p class="mt-1.5 text-[10px] text-faint">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>

                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="shrink-0">
                        @csrf
                        <x-btn type="submit" variant="secondary" size="xs" iconAfter="arrow-up-right">{{ __('Open') }}</x-btn>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</x-layouts.app>
