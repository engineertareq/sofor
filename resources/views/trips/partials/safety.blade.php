{{--
    Check-ins ("Reached Sajek") and the viewer's family link: a private page
    for the people at home showing where the group is.
--}}
@php
    $checkins = $checkins ?? collect();
    $me = $currentMember ?? null;
    $canCheckIn = $me !== null && $me->isTravelling() && $trip->isActive();
@endphp

<x-card padding="p-4 sm:p-5" class="mt-4 lg:mt-6">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-positive-tint text-positive">
                <x-icon name="map-pin" class="size-4.5" />
            </span>
            <div class="min-w-0">
                <h2 class="truncate font-display text-sm font-semibold">{{ __('Check-ins') }}</h2>
                <p class="text-[11px] text-faint">{{ __('Let the group, and the people at home, know you are safe') }}</p>
            </div>
        </div>

        @if ($canCheckIn)
            <x-btn type="button" size="sm" icon="map-pin" @click="$dispatch('sofor-checkin')">{{ __('Check in') }}</x-btn>
        @endif
    </div>

    @if ($checkins->isEmpty())
        <p class="mt-4 text-[12px] leading-relaxed text-muted">
            {{ __('Tap "Check in" when you leave, arrive or are back home. Everyone on the trip sees it, and so does your family on your family link.') }}
        </p>
    @else
        <ol class="mt-4 flex flex-col">
            @foreach ($checkins as $checkin)
                <li class="group relative flex gap-3 pb-3 last:pb-0">
                    @unless ($loop->last)
                        <span class="absolute left-[7px] top-4 h-full w-px bg-line" aria-hidden="true"></span>
                    @endunless
                    <span class="relative z-10 mt-1 size-3.5 shrink-0 rounded-full border-2 {{ $loop->first ? 'border-positive bg-positive' : 'border-line-strong bg-surface' }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[12.5px] font-semibold text-ink">{{ $checkin->place }}</p>
                        <p class="text-[10.5px] text-faint">
                            {{ $checkin->member?->name ?? __('A traveller') }} &middot; {{ $checkin->created_at->diffForHumans() }}
                            @if ($checkin->hasLocation())
                                &middot; <a href="{{ $checkin->mapUrl() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-brand">{{ __('map') }}</a>
                            @endif
                        </p>
                        @if ($checkin->note)
                            <p class="mt-0.5 text-[11.5px] text-muted">{{ $checkin->note }}</p>
                        @endif
                    </div>
                    @if (($me && $checkin->trip_member_id === $me->id) || ($canManage ?? false))
                        <form method="POST" action="{{ route('trips.checkins.destroy', [$trip, $checkin]) }}" class="opacity-0 transition group-hover:opacity-100 focus-within:opacity-100">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="flex size-6 items-center justify-center rounded-lg text-faint hover:bg-negative-tint hover:text-negative" aria-label="{{ __('Remove check-in') }}">
                                <x-icon name="close" class="size-3" />
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    {{-- ===== My family link ===== --}}
    @if ($me && $me->isTravelling())
        <div class="mt-4 rounded-3xl border border-line bg-surface-2 p-3.5">
            <p class="text-[12.5px] font-semibold text-ink">{{ __('Family link') }}</p>
            @if ($me->family_token)
                @php $familyUrl = route('family.show', $me->family_token); @endphp
                <p class="mt-0.5 text-[11px] text-muted">{{ __('Anyone with this link sees the trip and its check-ins, no login. Only share it with family.') }}</p>

                <div class="mt-2.5 flex items-center gap-2 rounded-2xl border border-line bg-surface p-1.5" x-data="clipboard(@js($familyUrl), 'Family link copied')">
                    <code class="min-w-0 flex-1 truncate px-2 font-mono text-[11px] text-muted">{{ $familyUrl }}</code>
                    <button type="button" @click="copy()" class="rounded-xl bg-brand/12 px-2.5 py-1.5 text-[11px] font-semibold text-brand">
                        <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                    </button>
                    <a href="https://wa.me/?text={{ rawurlencode(__('Follow my trip :trip on Sofor - I will check in on the way: :url', ['trip' => $trip->title, 'url' => $familyUrl])) }}"
                       target="_blank" rel="noopener" class="rounded-xl bg-[#25D366]/15 px-2.5 py-1.5 text-[11px] font-semibold text-[#128C7E]">{{ __('WhatsApp') }}</a>
                </div>

                <form method="POST" action="{{ route('trips.family.phone', $trip) }}" class="mt-2.5 flex items-center gap-2">
                    @csrf
                    @method('PUT')
                    <input type="tel" name="family_phone" value="{{ old('family_phone', $me->family_phone) }}" maxlength="20" inputmode="tel"
                           placeholder="{{ __('Family mobile for check-in texts') }}" aria-label="{{ __('Family mobile for check-in texts') }}"
                           class="form-field h-9 flex-1 text-[12px] {{ $errors->has('family_phone') ? 'form-field-error' : '' }}">
                    <x-btn type="submit" size="xs" variant="secondary">{{ __('Save') }}</x-btn>
                </form>
                @error('family_phone')
                    <p class="mt-1 text-[11px] font-medium text-negative">{{ $message }}</p>
                @enderror

                <div class="mt-2 flex flex-wrap gap-3 text-[11px]">
                    <form method="POST" action="{{ route('trips.family.link', $trip) }}" onsubmit="return confirm('Make a new link? The one you shared stops working.')">
                        @csrf
                        <button type="submit" class="font-semibold text-muted hover:text-ink">{{ __('New link') }}</button>
                    </form>
                    <form method="POST" action="{{ route('trips.family.disable', $trip) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-semibold text-muted hover:text-negative">{{ __('Turn off') }}</button>
                    </form>
                </div>
            @else
                <p class="mt-0.5 text-[11px] text-muted">{{ __('A private page your family can keep open to see where the group is and when you check in. No app or login for them.') }}</p>
                <form method="POST" action="{{ route('trips.family.link', $trip) }}" class="mt-2.5">
                    @csrf
                    <x-btn type="submit" size="xs" variant="secondary" icon="link">{{ __('Make my family link') }}</x-btn>
                </form>
            @endif
        </div>
    @endif
</x-card>
