{{-- The public link to this timeline. The leader switches it on and off, picks
     whether it says who paid, and can swap it for a new link; everyone else
     on the trip can copy it while it is on. --}}
@php $public = $trip->timelineIsPublic(); @endphp

<div class="rounded-[1.6rem] border border-line bg-surface p-4 shadow-soft">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="flex items-center gap-1.5 text-[13.5px] font-semibold text-ink">
                <x-icon name="link" class="size-4 text-brand" /> {{ __('Public link') }}
            </p>
            <p class="mt-0.5 text-[12px] leading-snug text-muted">
                {{ $public ? __('Anyone with the link can see this timeline.') : __('Only people on this trip can see it.') }}
            </p>
        </div>

        @if ($canShare)
            <form method="POST" action="{{ route('trips.timeline.share', $trip) }}" class="shrink-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="shared" value="{{ $public ? 0 : 1 }}">
                <button type="submit" role="switch" aria-checked="{{ $public ? 'true' : 'false' }}" aria-label="{{ __('Public link') }}"
                        @class(['relative inline-flex h-7 w-12 items-center rounded-full transition', 'bg-brand' => $public, 'bg-line-strong' => ! $public])>
                    <span @class(['inline-block size-5.5 rounded-full bg-white shadow-soft transition-transform', 'translate-x-6' => $public, 'translate-x-1' => ! $public])></span>
                </button>
            </form>
        @endif
    </div>

    @if ($public)
        <div class="mt-3 flex items-center gap-2" x-data="clipboard({{ \Illuminate\Support\Js::from($trip->timelineUrl()) }}, {{ \Illuminate\Support\Js::from(__('Timeline link copied')) }})">
            <input type="text" readonly value="{{ $trip->timelineUrl() }}" aria-label="{{ __('Public link') }}" @focus="$el.select()"
                   class="form-field h-10 min-w-0 flex-1 truncate text-[12px]" translate="no">
            <button type="button" @click="copy()" class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-line bg-surface text-muted transition hover:text-ink" :aria-label="copied ? {{ \Illuminate\Support\Js::from(__('Copied')) }} : {{ \Illuminate\Support\Js::from(__('Copy link')) }}">
                <x-icon name="copy" class="size-4" x-show="!copied" />
                <x-icon name="check" class="size-4 text-positive" x-show="copied" x-cloak />
            </button>
            <a href="{{ $trip->timelineUrl() }}" target="_blank" rel="noopener" class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-line bg-surface text-muted transition hover:text-ink" aria-label="{{ __('Open the public timeline') }}">
                <x-icon name="arrow-up-right" class="size-4" />
            </a>
        </div>

        @if ($canShare)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
                <form method="POST" action="{{ route('trips.timeline.share', $trip) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="names" value="{{ $trip->timeline_names ? 0 : 1 }}">
                    <button type="submit" role="switch" aria-checked="{{ $trip->timeline_names ? 'true' : 'false' }}" class="flex items-center gap-2 text-[12.5px] font-medium text-ink">
                        <span @class(['relative inline-flex h-5 w-9 items-center rounded-full transition', 'bg-brand' => $trip->timeline_names, 'bg-line-strong' => ! $trip->timeline_names])>
                            <span @class(['inline-block size-4 rounded-full bg-white transition-transform', 'translate-x-4.5' => $trip->timeline_names, 'translate-x-0.5' => ! $trip->timeline_names])></span>
                        </span>
                        {{ __('Show who paid') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('trips.timeline.rotate', $trip) }}"
                      x-data @submit="if (! confirm({{ \Illuminate\Support\Js::from(__('Make a new link? The old one will stop working.')) }})) $event.preventDefault()">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1 text-[12px] font-semibold text-muted transition hover:text-ink">
                        <x-icon name="refresh" class="size-3.5" /> {{ __('New link') }}
                    </button>
                </form>
            </div>
        @endif
    @elseif (! $canShare)
        <p class="mt-3 rounded-2xl bg-surface-2 px-3 py-2.5 text-[12px] text-muted">{{ __('The trip leader can turn on a link to share it with anyone.') }}</p>
    @endif
</div>
