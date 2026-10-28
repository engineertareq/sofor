<x-layouts.app title="{{ __('Checkpoint list') }}" :active="'trips'" :heading="__('Checkpoint list')"
               :subtitle="$trip->title" :back="route('trips.show', $trip)">
    <x-slot:actions>
        @if ($canManage)
            <x-btn :href="route('trips.manifest.pdf', $trip)" size="sm" icon="download">{{ __('Download PDF') }}</x-btn>
        @endif
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr] lg:items-start">
        <div class="flex flex-col gap-6">
            {{-- Why, and how it is kept --}}
            <x-card>
                <div class="flex items-start gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-tint text-brand">
                        <x-icon name="shield" class="size-5.5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-display text-base font-semibold">{{ __('One list for every checkpoint') }}</h2>
                        <p class="mt-1 text-[13px] leading-relaxed text-muted">
                            {{ __('Sajek, Bandarban, Rangamati and other hill-tract trips pass army and police checkpoints that ask for every traveller\'s name, age, ID and phone - and resorts ask again at check-in. Each traveller fills it in once here; :name prints copies to hand over.', ['name' => $trip->owner?->name ?? 'the leader']) }}
                        </p>
                        <ul class="mt-3 grid gap-2 text-[12px] text-ink sm:grid-cols-3">
                            <li class="flex items-center gap-2"><x-icon name="lock" class="size-3.5 text-brand" /> {{ __('Encrypted when stored') }}</li>
                            <li class="flex items-center gap-2"><x-icon name="users" class="size-3.5 text-brand" /> {{ __('Only you and the leader') }}</li>
                            <li class="flex items-center gap-2"><x-icon name="trash" class="size-3.5 text-brand" /> {{ __('Deleted :days days after the trip', ['days' => \App\Http\Controllers\TripManifestController::KEEP_DAYS]) }}</li>
                        </ul>
                    </div>
                </div>
            </x-card>

            {{-- My own details --}}
            @if ($me)
                <x-card>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-base font-semibold">{{ __('Your details') }}</h2>
                            <p class="text-xs text-muted">
                                @if ($mine)
                                    {{ __('On the list since :date. Update them any time.', ['date' => $mine->updated_at?->translatedFormat('j M')]) }}
                                @elseif ($remembered)
                                    {{ __('Filled in from the copy you saved last time - check and add yourself.') }}
                                @else
                                    {{ __('Write them exactly as on your ID card.') }}
                                @endif
                            </p>
                        </div>
                        @if ($mine)
                            <span class="flex items-center gap-1.5 rounded-full bg-positive-tint px-2.5 py-1 text-[11px] font-semibold text-positive">
                                <x-icon name="check" class="size-3.5" /> {{ __('On the list') }}
                            </span>
                        @endif
                    </div>

                    @if ($trip->isActive())
                        <form method="POST" action="{{ route('trips.manifest.mine', $trip) }}" class="mt-5 flex flex-col gap-4">
                            @csrf
                            @method('PUT')

                            <x-traveller-fields :values="$prefill" key="me" />

                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line bg-surface-2 px-3 py-2.5">
                                <input type="checkbox" name="remember" value="1" @checked(old('form') === 'me' ? old('remember') : $remembered)
                                       class="mt-0.5 size-4 rounded-md border-line bg-surface text-brand focus:ring-brand/40">
                                <span class="text-[12.5px] leading-relaxed text-ink">
                                    {{ __('Save a copy for my next trips') }}
                                    <span class="block text-[11px] text-faint">{{ __('Kept encrypted on your account so the next list takes one tap. Untick to delete it.') }}</span>
                                </span>
                            </label>

                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                @if ($mine)
                                    <x-btn type="submit" form="forget-my-details" variant="ghost" icon="trash">{{ __('Remove from this trip') }}</x-btn>
                                @endif
                                <x-btn type="submit" icon="check">{{ $mine ? __('Update my details') : __('Add me to the list') }}</x-btn>
                            </div>
                        </form>

                        @if ($mine)
                            <form id="forget-my-details" method="POST" action="{{ route('trips.manifest.forget', $trip) }}"
                                  onsubmit="return confirm('Remove your details from this trip\'s list?')">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    @else
                        <p class="mt-4 text-[13px] text-muted">{{ __('This trip has ended, so the list can no longer change.') }}</p>
                    @endif
                </x-card>
            @endif

            {{-- Vehicle and route (leader) --}}
            @if ($canManage)
                <x-card>
                    <h2 class="font-display text-base font-semibold">{{ __('Vehicle and route') }}</h2>
                    <p class="text-xs text-muted">{{ __('Checkpoints usually note the vehicle and driver too. Printed at the top of the list.') }}</p>

                    <form method="POST" action="{{ route('trips.manifest.trip', $trip) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf
                        @method('PUT')

                        <x-field name="vehicle" :label="__('Vehicle')">
                            <x-input name="vehicle" maxlength="60" :value="$checkpoint['vehicle'] ?? null" placeholder="{{ __('e.g. Chander gari, microbus') }}" />
                        </x-field>
                        <x-field name="vehicle_number" :label="__('Registration number')">
                            <x-input name="vehicle_number" maxlength="30" :value="$checkpoint['vehicle_number'] ?? null" placeholder="{{ __('e.g. Khagrachari Cha 11-0234') }}" />
                        </x-field>
                        <x-field name="driver_name" :label="__('Driver')">
                            <x-input name="driver_name" maxlength="80" :value="$checkpoint['driver_name'] ?? null" />
                        </x-field>
                        <x-field name="driver_phone" :label="__('Driver\'s mobile')">
                            <x-input name="driver_phone" type="tel" maxlength="24" :value="$checkpoint['driver_phone'] ?? null" placeholder="{{ __('01XXXXXXXXX') }}" />
                        </x-field>
                        <x-field name="route" :label="__('Route')" class="sm:col-span-2">
                            <x-input name="route" maxlength="160" :value="$checkpoint['route'] ?? null" placeholder="{{ __('e.g. Dhaka - Khagrachari - Sajek') }}" />
                        </x-field>
                        <x-field name="stay" :label="__('Staying at')" class="sm:col-span-2">
                            <x-input name="stay" maxlength="120" :value="$checkpoint['stay'] ?? null" placeholder="{{ __('e.g. Megh Bilash cottage, Ruilui para') }}" />
                        </x-field>
                        <x-field name="notes" :label="__('Notes')" class="sm:col-span-2">
                            <x-textarea name="notes" rows="2" :value="$checkpoint['notes'] ?? null" />
                        </x-field>

                        <div class="sm:col-span-2 flex justify-end">
                            <x-btn type="submit" variant="secondary" icon="check">{{ __('Save trip details') }}</x-btn>
                        </div>
                    </form>
                </x-card>
            @endif
        </div>

        {{-- Who is on the list --}}
        <x-card class="lg:sticky lg:top-24">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-base font-semibold">{{ __('Travellers') }}</h2>
                    <p class="text-xs text-muted">{{ __(':ready of :count ready', ['ready' => $ready, 'count' => $travellers->count()]) }}</p>
                </div>
                <span class="num font-display text-2xl font-semibold {{ $ready === $travellers->count() ? 'text-positive' : 'text-ink' }}">
                    {{ $travellers->count() > 0 ? round($ready / $travellers->count() * 100) : 0 }}%
                </span>
            </div>
            <x-progress class="mt-3" :value="$travellers->count() > 0 ? $ready / $travellers->count() * 100 : 0" size="sm" :tone="$ready === $travellers->count() ? 'positive' : 'brand'" />

            <ul class="mt-4 flex flex-col gap-2">
                @foreach ($travellers as $traveller)
                    @php
                        $detail = $traveller->travellerDetail;
                        $canFill = $canManage && $traveller->user_id === null && $trip->isActive();
                        $formKey = 'member-'.$traveller->id;
                    @endphp
                    <li class="rounded-2xl border border-line bg-surface-2 p-3" x-data="{ open: @js(old('form') === $formKey) }">
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <x-avatar :name="$traveller->name" :src="$traveller->avatarUrl()" size="sm" />
                                <span class="min-w-0">
                                    <span class="block truncate text-[13px] font-semibold text-ink">{{ $detail?->value('full_name') ?? $traveller->name }}</span>
                                    <span class="block truncate text-[11px] text-faint">
                                        @if ($detail && ($canManage || $traveller->is($me)))
                                            {{ $detail->maskedId() }} &middot; {{ __(':age yrs', ['age' => $detail->age()]) }}
                                            @if ($detail->value('blood_group')) &middot; {{ $detail->value('blood_group') }} @endif
                                        @elseif ($detail)
                                            {{ __('Details added') }}
                                        @elseif ($traveller->user_id === null)
                                            {{ __('No account - the leader adds their details') }}
                                        @else
                                            {{ __('Not filled in yet') }}
                                        @endif
                                    </span>
                                </span>
                            </span>

                            @if ($canFill)
                                <button type="button" @click="open = ! open"
                                        class="shrink-0 rounded-full bg-surface px-2.5 py-1 text-[11px] font-semibold text-brand hairline">
                                    {{ $detail ? __('Edit') : __('Add') }}
                                </button>
                            @elseif ($detail)
                                <x-icon name="check-circle" class="size-4.5 shrink-0 text-positive" />
                            @else
                                <x-icon name="clock" class="size-4.5 shrink-0 text-faint" />
                            @endif
                        </div>

                        @if ($canFill)
                            <form x-show="open" x-cloak method="POST" action="{{ route('trips.manifest.member', [$trip, $traveller]) }}" class="mt-3 flex flex-col gap-3 border-t border-line pt-3">
                                @csrf
                                @method('PUT')
                                <x-traveller-fields :values="$detail?->data ?? ['full_name' => $traveller->name, 'phone' => $traveller->phone]" :key="$formKey" />
                                <x-btn type="submit" size="sm" icon="check" class="self-end">{{ __('Save :first_name', ['first_name' => $traveller->firstName()]) }}</x-btn>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($canManage && $ready < $travellers->count() && $trip->isActive())
                <form method="POST" action="{{ route('trips.manifest.remind', $trip) }}" class="mt-4">
                    @csrf
                    <x-btn type="submit" variant="secondary" size="sm" icon="bell" block>{{ __('Remind who has not filled it in') }}</x-btn>
                </form>
            @endif

            @if ($purgeOn)
                <p class="mt-4 text-[11px] text-faint">{{ __('Every detail on this list is deleted on :date.', ['date' => $purgeOn->translatedFormat('j M Y')]) }}</p>
            @endif
        </x-card>
    </div>
</x-layouts.app>
