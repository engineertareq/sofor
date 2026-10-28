<x-card padding="p-4 sm:p-5">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <span class="flex size-9 items-center justify-center rounded-2xl bg-warning/12 text-warning">
                <x-icon name="clock" class="size-4.5" />
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold">{{ __('Trip timeline') }}</h2>
                <p class="text-[11px] text-faint">{{ __('Everything that happened, newest first') }}</p>
            </div>
        </div>

        <span class="flex items-center gap-1.5 text-[11px] font-medium text-positive">
            <span class="size-1.5 animate-pulse rounded-full bg-positive"></span> {{ __('Live') }}
        </span>
    </div>

    @if ($activities->isEmpty())
        <p class="mt-4 text-[13px] text-muted">{{ __('The timeline starts with your first action in this trip.') }}</p>
    @else
        <ol class="mt-4 flex flex-col">
            @foreach ($activities as $activity)
                <li class="relative flex gap-3 pb-4 last:pb-0">
                    @unless ($loop->last)
                        <span class="absolute left-[15px] top-8 h-full w-px bg-line"></span>
                    @endunless

                    <span class="relative z-10 mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-faint hairline">
                        <x-icon :name="match ($activity->type) {
                            'trip_created' => 'sparkles',
                            'expense_added' => 'receipt',
                            'expense_updated', 'expense_deleted' => 'pencil',
                            'settlement' => 'wallet',
                            'settlement_deleted' => 'close',
                            'trip_ended' => 'lock',
                            'member_invited' => 'user-plus',
                            'member_joined' => 'users',
                            'member_left', 'member_removed' => 'logout',
                            'member_updated' => 'settings',
                            'invite_rotated' => 'link',
                            'budget_alert' => 'wallet',
                            'collection_started', 'collection_removed' => 'coins',
                            'poll_created', 'poll_closed', 'rsvp' => 'check-circle',
                            'manifest_printed' => 'shield',
                            'checkin' => 'map-pin',
                            default => 'info',
                        }" class="size-3.5" />
                    </span>

                    <div class="min-w-0 flex-1 pt-1">
                        <p class="text-[12px] leading-snug text-ink">{{ $activity->text() }}</p>
                        <p class="mt-0.5 text-[10px] text-faint">
                            {{ $activity->created_at?->diffForHumans() }}
                            @if ($activity->user)
                                - {{ __('by :name', ['name' => $activity->user->name]) }}
                            @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-card>
