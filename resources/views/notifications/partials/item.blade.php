{{-- One alert, on the alerts page and in the alerts panel (NotificationController::feed).
     Opening it marks it read: a form, so it works without JavaScript, which the panel
     sends with fetch instead. data-unread drives the unread look, so marking it read in
     the browser is only a matter of taking that attribute away. --}}
@php
    $data = $notification->data;
    $tone = match ($data['tone'] ?? 'info') {
        'success' => 'bg-positive-tint text-positive',
        'warning' => 'bg-warning-tint text-warning',
        'error' => 'bg-negative-tint text-negative',
        default => 'bg-brand-tint text-brand',
    };
@endphp

<li>
    <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="group/alert"
          data-alert @if ($notification->read_at === null) data-unread @endif>
        @csrf
        <button type="submit"
                class="flex w-full items-start gap-3 rounded-2xl px-3 py-3 text-left transition hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 active:scale-[0.99] active:bg-surface-2 group-data-[unread]/alert:bg-brand-tint/45 group-data-[unread]/alert:hover:bg-brand-tint/70">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-[0.85rem] {{ $tone }}">
                <x-icon :name="$data['icon'] ?? 'bell'" class="size-[18px]" />
            </span>

            <span class="min-w-0 flex-1">
                <span class="line-clamp-2 text-[13.5px] font-medium leading-snug text-ink group-data-[unread]/alert:font-semibold">{{ $data['title'] ?? __('Sofor update') }}</span>
                @if (filled($data['body'] ?? null))
                    <span class="mt-0.5 line-clamp-2 text-[12.5px] leading-snug text-muted">{{ $data['body'] }}</span>
                @endif
                <time datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $notification->created_at->format('d M Y, H:i') }}"
                      class="mt-1 block text-[11px] text-faint">{{ $notification->created_at->diffForHumans() }}</time>
            </span>

            <span class="mt-1.5 hidden size-2.5 shrink-0 rounded-full bg-brand group-data-[unread]/alert:block">
                <span class="sr-only">{{ __('Unread') }}</span>
            </span>
        </button>
    </form>
</li>
