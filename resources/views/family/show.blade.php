<x-layouts.guest :title="__(':name on :trip', ['name' => $member->firstName(), 'trip' => $trip->title])" :noindex="true"
                 :heading="__(':name is travelling', ['name' => $member->firstName()])"
                 :subtitle="$trip->title.($trip->destination ? ' - '.$trip->destination : '')">

    <div class="flex flex-col gap-4">
        @if (! $visible)
            <section class="rounded-4xl border-[1.5px] border-line bg-surface p-5 text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-positive-tint text-positive"><x-icon name="check" class="size-6" /></span>
                <p class="mt-3 font-display text-lg font-semibold text-ink">{{ __('This trip is over') }}</p>
                <p class="mt-1 text-[13px] text-muted">{{ __('Check-ins are only shared while the group is travelling.') }}</p>
            </section>
        @else
            {{-- ===== Latest ===== --}}
            @php $latest = $checkins->first(); @endphp
            <section class="rounded-4xl border-2 border-ink bg-surface p-5 shadow-sticker">
                <p class="eyebrow text-faint">{{ __('Latest check-in') }}</p>
                @if ($latest)
                    <p class="mt-1 font-display text-[1.6rem] font-semibold leading-tight text-ink">{{ $latest->place }}</p>
                    <p class="mt-1 text-[12.5px] text-muted">
                        {{ $latest->member?->firstName() ?? __('The group') }} &middot;
                        <time datetime="{{ $latest->created_at->toIso8601String() }}">{{ $latest->created_at->diffForHumans() }}</time>
                        ({{ \App\Support\Bangla::moment($latest->created_at) }})
                    </p>
                    @if ($latest->note)
                        <p class="mt-2 rounded-2xl bg-surface-2 px-3 py-2 text-[12.5px] text-ink">{{ $latest->note }}</p>
                    @endif
                    @if ($latest->hasLocation())
                        <a href="{{ $latest->mapUrl() }}" target="_blank" rel="noopener noreferrer"
                           class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-brand/12 px-3 py-1.5 text-[12px] font-semibold text-brand">
                            <x-icon name="map-pin" class="size-3.5" /> {{ __('See it on the map') }}
                        </a>
                    @endif
                @else
                    <p class="mt-1 font-display text-lg font-semibold text-ink">{{ __('No check-ins yet') }}</p>
                    <p class="mt-1 text-[12.5px] text-muted">{{ __('When anyone in the group checks in, it shows up here. Keep this page - it updates by itself.') }}</p>
                @endif
            </section>

            {{-- ===== The trip ===== --}}
            <section class="rounded-4xl border-[1.5px] border-line bg-surface p-5">
                <dl class="grid grid-cols-2 gap-3 text-[12.5px]">
                    <div>
                        <dt class="text-faint">{{ __('Group') }}</dt>
                        <dd class="font-semibold text-ink">{{ __(':group_size travellers', ['group_size' => $groupSize]) }}</dd>
                    </div>
                    <div>
                        <dt class="text-faint">{{ __('Led by') }}</dt>
                        <dd class="font-semibold text-ink">{{ $trip->owner?->name ?? __('A Sofor traveller') }}</dd>
                    </div>
                    @if ($trip->starts_on)
                        <div>
                            <dt class="text-faint">{{ __('Dates') }}</dt>
                            <dd class="font-semibold text-ink">{{ $trip->starts_on->translatedFormat('j M') }}{{ $trip->ends_on ? ' - '.$trip->ends_on->translatedFormat('j M') : '' }}</dd>
                        </div>
                    @endif
                    @if ($stay)
                        <div>
                            <dt class="text-faint">{{ __('Staying at') }}</dt>
                            <dd class="font-semibold text-ink">{{ $stay }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- ===== Timeline ===== --}}
            @if ($checkins->count() > 1)
                <section class="rounded-4xl border-[1.5px] border-line bg-surface p-5">
                    <p class="eyebrow text-faint">{{ __('Earlier') }}</p>
                    <ol class="mt-3 flex flex-col">
                        @foreach ($checkins->skip(1) as $checkin)
                            <li class="relative flex gap-3 pb-4 last:pb-0">
                                @unless ($loop->last)
                                    <span class="absolute left-[7px] top-4 h-full w-px bg-line" aria-hidden="true"></span>
                                @endunless
                                <span class="relative z-10 mt-1 size-3.5 shrink-0 rounded-full border-2 border-brand bg-surface"></span>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-ink">{{ $checkin->place }}</p>
                                    <p class="text-[11px] text-faint">
                                        {{ $checkin->member?->firstName() ?? __('The group') }} &middot; {{ \App\Support\Bangla::moment($checkin->created_at) }}
                                        @if ($checkin->hasLocation())
                                            &middot; <a href="{{ $checkin->mapUrl() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-brand">{{ __('map') }}</a>
                                        @endif
                                    </p>
                                    @if ($checkin->note)
                                        <p class="mt-1 text-[12px] text-muted">{{ $checkin->note }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            <p class="text-center text-[11px] leading-relaxed text-faint">
                {{ __(':first_name shared this private page with you. It refreshes every few minutes while it is open.', ['first_name' => $member->firstName()]) }}
            </p>

            <script>
                // Families leave this open; refresh it now and then, but not while hidden.
                setInterval(() => { if (document.visibilityState === 'visible') location.reload(); }, 180000);
            </script>
        @endif
    </div>
</x-layouts.guest>
