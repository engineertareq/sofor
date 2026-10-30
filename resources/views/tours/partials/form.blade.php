{{-- The tour's settings: tours/create and tours/edit. Packages are a list the
     organiser grows and trims here; one somebody booked cannot be removed
     (SaveTourRequest), only changed. --}}
@php
    $lockedIds = $tour->exists ? $tour->packages()->whereHas('registrations')->pluck('id')->all() : [];

    $rows = old('packages', $tour->exists
        ? $tour->packages->map(fn ($package) => [
            'id' => $package->id,
            'name' => $package->name,
            'description' => $package->description,
            'price' => rtrim(rtrim((string) $package->price, '0'), '.'),
            'seats' => $package->seats,
            'advance' => $package->advance !== null ? rtrim(rtrim((string) $package->advance, '0'), '.') : '',
        ])->all()
        : [['id' => null, 'name' => __('Regular'), 'description' => '', 'price' => '', 'seats' => 1, 'advance' => '']]);

    $covers = \App\Http\Controllers\TourController::covers();
    $cover = old('cover', $tour->cover ?? 'aurora');
@endphp

<form method="POST" action="{{ $action }}" class="flex flex-col gap-4"
      x-data="{
          packages: @js(array_values($rows)),
          locked: @js($lockedIds),
          errors: @js($errors->getMessages()),
          add() { if (this.packages.length < {{ \App\Http\Requests\SaveTourRequest::MAX_PACKAGES }}) this.packages.push({ id: null, name: '', description: '', price: '', seats: 1, advance: '' }); },
          remove(i) { this.packages.splice(i, 1); },
          isLocked(row) { return row.id !== null && this.locked.includes(Number(row.id)); },
          error(i, field) { return (this.errors[`packages.${i}.${field}`] ?? [])[0] ?? ''; },
      }">
    @csrf
    @if ($tour->exists)
        @method('PUT')
    @endif

    {{-- ===== The tour ===== --}}
    <x-card padding="p-4 sm:p-6">
        <h2 class="font-display text-[16px] font-semibold text-ink">{{ __('The tour') }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-field name="title" :label="__('Name')" required class="sm:col-span-2">
                <x-input name="title" :value="$tour->title" maxlength="120" required :placeholder="__('e.g. Sajek Valley, 3 days 2 nights')" />
            </x-field>
            <x-field name="destination" :label="__('Destination')">
                <x-input name="destination" :value="$tour->destination" maxlength="120" icon="map-pin" :placeholder="__('e.g. Sajek, Rangamati')" />
            </x-field>
            <x-field name="contact_phone" :label="__('Phone for questions')" :hint="__('Shown on the tour page. Leave empty to show none.')">
                <x-input name="contact_phone" type="tel" :value="$tour->contact_phone ? \App\Support\Phone::pretty($tour->contact_phone) : null" icon="phone" placeholder="01XXX-XXXXXX" />
            </x-field>
            <x-field name="description" :label="__('About the tour')" :hint="__('What is included, the plan for each day, what to bring.')" class="sm:col-span-2">
                <x-textarea name="description" :value="$tour->description" rows="5" maxlength="3000" />
            </x-field>
        </div>

        <fieldset class="mt-5">
            <legend class="mb-2 text-[13px] font-medium text-muted">{{ __('Cover colour') }}</legend>
            <div class="grid grid-cols-3 gap-2.5 sm:grid-cols-6">
                @foreach ($covers as $key => $swatch)
                    <label class="group relative cursor-pointer">
                        <input type="radio" name="cover" value="{{ $key }}" class="peer sr-only" @checked($cover === $key)>
                        <span class="block h-12 rounded-2xl border-[1.5px] border-line transition peer-checked:ring-2 peer-checked:ring-brand peer-checked:ring-offset-2 peer-checked:ring-offset-surface peer-focus-visible:ring-2 peer-focus-visible:ring-brand/50"
                              style="background-image: {{ $swatch['style'] }}"></span>
                        <span class="mt-1 block truncate text-center text-[11px] text-muted">{{ $swatch['label'] }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    </x-card>

    {{-- ===== When and where ===== --}}
    <x-card padding="p-4 sm:p-6">
        <h2 class="font-display text-[16px] font-semibold text-ink">{{ __('When and where') }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-field name="starts_on" :label="__('Starts on')">
                <x-input name="starts_on" type="date" :value="$tour->starts_on?->format('Y-m-d')" />
            </x-field>
            <x-field name="ends_on" :label="__('Ends on')">
                <x-input name="ends_on" type="date" :value="$tour->ends_on?->format('Y-m-d')" />
            </x-field>
            <x-field name="meeting_point" :label="__('Meeting point')">
                <x-input name="meeting_point" :value="$tour->meeting_point" maxlength="160" icon="pin" :placeholder="__('e.g. Sayedabad bus counter')" />
            </x-field>
            <x-field name="meeting_at" :label="__('Meeting time')">
                <x-input name="meeting_at" type="datetime-local" :value="$tour->meeting_at?->format('Y-m-d\TH:i')" />
            </x-field>
        </div>
    </x-card>

    {{-- ===== Packages ===== --}}
    <x-card padding="p-4 sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-[16px] font-semibold text-ink">{{ __('Packages and prices') }}</h2>
                <p class="mt-0.5 text-[12.5px] text-muted">{{ __('One or more ways to join: regular, couple, student...') }}</p>
            </div>
        </div>
        @error('packages')
            <p class="mt-3 rounded-2xl bg-negative-tint px-3 py-2 text-[12.5px] font-medium text-negative">{{ $message }}</p>
        @enderror

        <div class="mt-4 flex flex-col gap-3">
            <template x-for="(row, i) in packages" :key="i">
                <div class="rounded-3xl border border-line bg-surface-2 p-3.5 sm:p-4">
                    <input type="hidden" :name="`packages[${i}][id]`" :value="row.id ?? ''">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[12px] font-semibold text-faint">{{ __('Package') }} <span class="num" x-text="i + 1"></span></p>
                        <button type="button" x-show="packages.length > 1 && ! isLocked(row)" @click="remove(i)"
                                class="inline-flex h-7 items-center gap-1 rounded-lg px-2 text-[11.5px] font-semibold text-muted transition hover:bg-negative-tint hover:text-negative">
                            <x-icon name="trash" class="size-3.5" /> {{ __('Remove') }}
                        </button>
                        <span x-show="isLocked(row)" class="inline-flex items-center gap-1 text-[11px] text-faint"><x-icon name="lock" class="size-3" /> {{ __('Has bookings') }}</span>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,0.7fr)_minmax(0,1fr)]">
                        <label class="block">
                            <span class="mb-1 block text-[12px] font-medium text-muted">{{ __('Name') }} <span class="text-negative">*</span></span>
                            <input type="text" :name="`packages[${i}][name]`" x-model="row.name" maxlength="80" required class="form-field" placeholder="{{ __('e.g. Couple') }}">
                            <span class="mt-1 block text-[11.5px] text-negative" x-show="error(i, 'name')" x-text="error(i, 'name')"></span>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[12px] font-medium text-muted">{{ __('Price') }} (৳) <span class="text-negative">*</span></span>
                            <input type="number" :name="`packages[${i}][price]`" x-model="row.price" min="0" step="1" required inputmode="numeric" class="form-field num" placeholder="6500">
                            <span class="mt-1 block text-[11.5px] text-negative" x-show="error(i, 'price')" x-text="error(i, 'price')"></span>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[12px] font-medium text-muted">{{ __('Seats') }}</span>
                            <input type="number" :name="`packages[${i}][seats]`" x-model="row.seats" min="1" max="10" step="1" required inputmode="numeric" class="form-field num">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[12px] font-medium text-muted">{{ __('Advance') }} (৳)</span>
                            <input type="number" :name="`packages[${i}][advance]`" x-model="row.advance" min="0" step="1" inputmode="numeric" class="form-field num" placeholder="{{ __('Optional') }}">
                            <span class="mt-1 block text-[11.5px] text-negative" x-show="error(i, 'advance')" x-text="error(i, 'advance')"></span>
                        </label>
                        <label class="block sm:col-span-4">
                            <span class="mb-1 block text-[12px] font-medium text-muted">{{ __('What it includes') }}</span>
                            <input type="text" :name="`packages[${i}][description]`" x-model="row.description" maxlength="160" class="form-field" placeholder="{{ __('e.g. AC bus, shared cottage, all meals') }}">
                        </label>
                    </div>
                </div>
            </template>
        </div>

        <button type="button" @click="add()" x-show="packages.length < {{ \App\Http\Requests\SaveTourRequest::MAX_PACKAGES }}"
                class="mt-3 inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-2xl border-[1.5px] border-dashed border-line-strong text-[13px] font-semibold text-muted transition hover:border-brand hover:text-brand">
            <x-icon name="plus" class="size-4" /> {{ __('Add a package') }}
        </button>
        <p class="mt-3 text-[12px] leading-relaxed text-faint">
            {{ __('Seats: how many places one booking takes - 2 for a couple. Advance: what holds a booking; leave empty to ask for the full price.') }}
        </p>
    </x-card>

    {{-- ===== Seats and registration ===== --}}
    <x-card padding="p-4 sm:p-6">
        <h2 class="font-display text-[16px] font-semibold text-ink">{{ __('Seats and registration') }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-field name="capacity" :label="__('Total seats')" :hint="__('When they are taken, new bookings join a waitlist. Leave empty for no limit.')">
                <x-input name="capacity" type="number" min="1" max="5000" inputmode="numeric" :value="$tour->capacity" icon="users" />
            </x-field>
            <x-field name="closes_on" :label="__('Last day to book')" :hint="__('Optional. Bookings stop after this day.')">
                <x-input name="closes_on" type="date" :value="$tour->closes_on?->format('Y-m-d')" />
            </x-field>
        </div>
    </x-card>

    {{-- ===== The account ===== --}}
    <x-card padding="p-4 sm:p-6">
        <label class="flex cursor-pointer items-start gap-3">
            <input type="hidden" name="show_account" value="0">
            <input type="checkbox" name="show_account" value="1" class="peer sr-only" @checked(old('show_account', $tour->show_account ?? true))>
            <span class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full bg-line-strong transition after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:bg-brand peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-brand/40"></span>
            <span>
                <span class="block font-display text-[15px] font-semibold text-ink">{{ __('Show the account to everyone who booked') }}</span>
                <span class="mt-0.5 block text-[12.5px] leading-relaxed text-muted">{{ __('They see what came in, what each cost was and what is left - the honest way clubs and office tours are run.') }}</span>
            </span>
        </label>
    </x-card>

    {{-- ===== Save ===== --}}
    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        @if ($tour->exists)
            <x-btn :href="route('tours.show', $tour)" variant="ghost">{{ __('Cancel') }}</x-btn>
            <x-btn type="submit" icon="check">{{ __('Save changes') }}</x-btn>
        @else
            <x-btn type="submit" variant="outline">{{ __('Save as draft') }}</x-btn>
            <x-btn type="submit" name="publish" value="1" icon="check">{{ __('Create and open registration') }}</x-btn>
        @endif
    </div>
</form>
