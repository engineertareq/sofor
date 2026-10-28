<x-layouts.app title="{{ __('Start a trip') }}" :active="'create'" :heading="__('Start a trip')"
               :subtitle="__('Name it, add the travellers and Sofor handles the maths.')" :back="route('trips.index')">
    <x-slot:actions>
        <x-btn :href="route('trips.index')" variant="ghost" size="sm" icon="arrow-left" class="hidden sm:inline-flex">{{ __('Back') }}</x-btn>
    </x-slot:actions>

    <form method="POST" action="{{ route('trips.store') }}" class="flex flex-col gap-6"
          x-data="{
              title: @js((string) old('title')),
              cover: @js((string) old('cover', 'aurora')),
              icon: @js((string) old('icon', 'compass')),
              glyphs: {{ \Illuminate\Support\Js::from(collect(\App\Support\Glyphs::TRIP)->mapWithKeys(fn ($key) => [$key => \App\Support\Glyphs::svg($key, 'size-8')])) }},
              covers: {{ \Illuminate\Support\Js::from($covers) }},
              members: {{ \Illuminate\Support\Js::from(old('members', []) ?: []) }},
              addMember() {
                  if (this.members.length < 40) {
                      this.members.push({ name: '', email: '', phone: '', share_units: 1 });
                  }
              },
              removeMember(index) { this.members.splice(index, 1); },
              memberCount() { return this.members.filter(m => m.name || m.email || m.phone).length + 1; },
              quickFill(set) { this.members = set; },

              {{-- Travellers from earlier trips --}}
              past: { q: '', results: [], open: false, loading: false, loaded: false, failed: false, active: -1 },
              pastUrl: '{{ route('trips.past-travellers') }}',
              pastTimer: null,
              pastSeq: 0,
              openPast() {
                  this.past.open = true;
                  if (! this.past.loaded) this.searchPast(true);
              },
              searchPast(immediate = false) {
                  this.past.open = true;
                  clearTimeout(this.pastTimer);
                  this.pastTimer = setTimeout(() => this.fetchPast(), immediate ? 0 : 220);
              },
              async fetchPast() {
                  const seq = ++this.pastSeq;
                  this.past.loading = true;
                  this.past.failed = false;
                  try {
                      const response = await fetch(this.pastUrl + '?q=' + encodeURIComponent(this.past.q.trim()), {
                          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                      });
                      if (! response.ok) throw new Error(response.status);
                      const data = await response.json();
                      if (seq !== this.pastSeq) return;
                      this.past.results = data.travellers ?? [];
                      this.past.active = this.past.results.findIndex(p => ! this.isListed(p));
                      this.past.loaded = true;
                  } catch (error) {
                      if (seq === this.pastSeq) {
                          this.past.results = [];
                          this.past.failed = true;
                      }
                  } finally {
                      if (seq === this.pastSeq) this.past.loading = false;
                  }
              },
              movePast(step) {
                  this.past.open = true;
                  const total = this.past.results.length;
                  if (total === 0) return;
                  this.past.active = (this.past.active + step + total) % total;
              },
              isListed(person) {
                  const digits = (value) => String(value || '').replace(/\D/g, '').slice(-10);
                  return this.members.some((m) => {
                      if (person.email && String(m.email || '').trim().toLowerCase() === person.email) return true;
                      if (person.phone && digits(m.phone) !== '' && digits(m.phone) === digits(person.phone)) return true;
                      return ! person.email && ! person.phone && String(m.name || '').trim().toLowerCase() === person.name.toLowerCase();
                  });
              },
              addPast(person) {
                  if (! person || this.isListed(person)) return;
                  const row = { name: person.name, email: person.email || '', phone: person.phone || '', share_units: 1 };
                  const blank = this.members.findIndex(m => ! m.name && ! m.email && ! m.phone);
                  if (blank !== -1) {
                      this.members.splice(blank, 1, row);
                  } else if (this.members.length < 40) {
                      this.members.push(row);
                  } else {
                      window.Sofor?.toast(window.SoforT('A trip can hold up to 40 travellers.'), 'warning');
                      return;
                  }
                  this.past.q = '';
                  this.past.open = false;
                  {{-- The list was for the old search; show recent travellers next time. --}}
                  this.past.loaded = false;
                  window.Sofor?.toast(window.SoforT(':name added from a past trip.', { name: person.name }), 'success');
              },
          }">
        @csrf

        <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr] lg:items-start">
            <div class="flex flex-col gap-6">
                {{-- Basics --}}
                <x-card>
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-2xl bg-brand/12 text-brand">
                            <x-icon name="compass" class="size-5" />
                        </span>
                        <div>
                            <h2 class="font-display text-base font-semibold">{{ __('Trip details') }}</h2>
                            <p class="text-xs text-muted">{{ __('Only the name is required, everything else can wait.') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <x-field name="title" :label="__('Trip name')" required>
                            <x-input name="title" placeholder="{{ __('e.g. Cox\'s Bazar weekend') }}" x-model="title" required autofocus />
                        </x-field>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="destination" :label="__('Destination')">
                                <x-input name="destination" icon="map-pin" placeholder="{{ __('e.g. Cox\'s Bazar, Chattogram') }}" :value="old('destination')" />
                            </x-field>

                            <x-field name="currency" :label="__('Currency')">
                                <x-select name="currency" :value="old('currency', 'BDT')" :options="[
                                    'BDT' => __('BDT - Bangladeshi Taka (৳)'),
                                    'USD' => __('USD - US Dollar ($)'),
                                    'INR' => __('INR - Indian Rupee (₹)'),
                                    'AED' => __('AED - UAE Dirham'),
                                    'MYR' => __('MYR - Malaysian Ringgit'),
                                    'SGD' => __('SGD - Singapore Dollar'),
                                    'THB' => __('THB - Thai Baht (฿)'),
                                    'EUR' => __('EUR - Euro (€)'),
                                    'GBP' => __('GBP - Pound Sterling (£)'),
                                ]" />
                            </x-field>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="starts_on" :label="__('Starts')">
                                <x-input name="starts_on" type="date" :value="old('starts_on')" />
                            </x-field>
                            <x-field name="ends_on" :label="__('Ends')">
                                <x-input name="ends_on" type="date" :value="old('ends_on')" />
                            </x-field>
                        </div>

                        <x-budget-field />

                        <x-field name="description" :label="__('Notes for the group')" :hint="__('Optional. Meeting point, plan, anything useful.')">
                            <x-textarea name="description" :value="old('description')" placeholder="{{ __('Bus at 11pm from Sayedabad, cottage booked for 2 nights...') }}" />
                        </x-field>
                    </div>
                </x-card>

                {{-- Travellers --}}
                <x-card>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-2xl bg-info-tint text-info">
                                <x-icon name="users" class="size-5" />
                            </span>
                            <div>
                                <h2 class="font-display text-base font-semibold">{{ __('Travellers') }}</h2>
                                <p class="text-xs text-muted">
                                    <span x-text="window.SoforNum(memberCount())">1</span> {{ __('in the trip - you are included as the trip leader.') }}
                                </p>
                            </div>
                        </div>

                        <x-btn type="button" variant="secondary" size="sm" icon="plus" @click="addMember()">{{ __('Add traveller') }}</x-btn>
                    </div>

                    {{-- Search people from earlier trips. No name attribute, so it is never submitted. --}}
                    <div class="relative mt-5" @click.outside="past.open = false">
                        <label for="past-traveller-search" class="text-[13px] font-medium text-muted">{{ __('Add from a past trip') }}</label>

                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-faint">
                                <x-icon name="search" class="size-4" />
                            </span>

                            <input id="past-traveller-search" type="search" autocomplete="off" x-model="past.q"
                                   placeholder="{{ __('Search by name or email') }}"
                                   class="form-field pl-10 pr-10 text-[13px]"
                                   role="combobox" aria-autocomplete="list" aria-controls="past-traveller-results"
                                   :aria-expanded="past.open.toString()"
                                   :aria-activedescendant="past.open && past.active >= 0 ? 'past-traveller-' + past.active : null"
                                   @focus="openPast()" @input="searchPast()"
                                   @keydown.arrow-down.prevent="movePast(1)" @keydown.arrow-up.prevent="movePast(-1)"
                                   @keydown.enter.prevent="addPast(past.results[past.active])"
                                   @keydown.escape="past.open = false">

                            <span x-show="past.loading" x-cloak class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-faint">
                                <x-icon name="refresh" class="size-4 animate-spin" />
                            </span>
                        </div>

                        <div x-show="past.open" x-cloak x-transition.opacity
                             id="past-traveller-results" role="listbox" aria-label="{{ __('Travellers from your past trips') }}"
                             class="absolute inset-x-0 top-full z-20 mt-2 max-h-80 overflow-y-auto rounded-3xl border-[1.5px] border-line bg-surface p-1.5 shadow-lift">
                            <template x-for="(person, index) in past.results" :key="person.key">
                                <button type="button" role="option" :id="'past-traveller-' + index"
                                        :aria-selected="(index === past.active).toString()" :aria-disabled="isListed(person).toString()"
                                        @click="addPast(person)" @mouseenter="past.active = index"
                                        :class="[index === past.active ? 'bg-surface-2' : '', isListed(person) ? 'cursor-default opacity-55' : '']"
                                        class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-left transition">
                                    <span class="relative flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-xl text-xs font-bold text-white shadow-soft"
                                          :style="'background-image: ' + person.gradient">
                                        <span x-text="person.initials"></span>
                                        <template x-if="person.avatar">
                                            <img :src="person.avatar" alt="" class="absolute inset-0 size-full object-cover" onerror="this.remove()">
                                        </template>
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-1.5">
                                            <span class="truncate text-sm font-semibold" x-text="person.name"></span>
                                            <span x-show="person.registered" class="shrink-0 text-brand" title="{{ __('Has a Sofor account') }}">
                                                <x-icon name="verified" class="size-3.5" />
                                            </span>
                                        </span>
                                        <span class="block truncate text-[11px] text-faint" x-text="person.contact"></span>
                                        <span class="flex min-w-0 items-center gap-1 text-[11px] text-muted"><span class="shrink-0" x-html="person.last_icon"></span><span class="truncate"
                                              x-text="person.last_trip + (person.trips > 1 ? ' + ' + @js(__(':count more')).replace(':count', window.SoforNum(person.trips - 1)) : '')"></span></span>
                                    </span>

                                    <span x-show="isListed(person)" class="flex shrink-0 items-center gap-1 text-[11px] font-semibold text-positive">
                                        <x-icon name="check" class="size-3.5" /> {{ __('Added') }}
                                    </span>
                                </button>
                            </template>

                            <p x-show="! past.loading && past.failed" class="flex items-center gap-1.5 px-3 py-3 text-xs text-negative">
                                <x-icon name="alert" class="size-3.5" /> {{ __('Could not load your travellers. Try again.') }}
                            </p>

                            <p x-show="! past.failed && past.loaded && past.results.length === 0" class="px-3 py-3 text-xs text-muted"
                               x-text="past.q.trim() ? @js(__('Nobody from your past trips matches that.')) : @js(__('People you travel with show up here after your first trip.'))"></p>

                            <p x-show="! past.loaded && past.loading" class="px-3 py-3 text-xs text-muted">{{ __('Looking through your trips...') }}</p>
                        </div>
                    </div>

                    @error('members')
                        <p class="mt-3 flex items-center gap-1.5 text-xs font-medium text-negative">
                            <x-icon name="alert" class="size-3.5" /> {{ $message }}
                        </p>
                    @enderror

                    <div class="mt-5 flex flex-col gap-3">
                        {{-- The leader row is fixed --}}
                        <div class="flex items-center gap-3 rounded-3xl border border-brand/25 bg-brand/8 p-3.5">
                            <x-avatar :name="auth()->user()->name" :gradient="auth()->user()->avatarGradient()" :src="auth()->user()->avatarUrl()" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                                <p class="truncate text-[11px] text-faint">{{ auth()->user()->handle() }}</p>
                            </div>
                            <x-badge tone="brand" icon="flag">{{ __('Trip leader') }}</x-badge>
                        </div>

                        <template x-for="(member, index) in members" :key="index">
                            <div class="rounded-3xl border border-line bg-surface-2 p-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-surface text-faint hairline" x-text="window.SoforNum(index + 2)"></span>

                                    <div class="grid flex-1 gap-2 sm:grid-cols-3">
                                        <input type="text" :name="`members[${index}][name]`" x-model="member.name"
                                               placeholder="{{ __('Name') }}" class="form-field text-[13px]">
                                        <input type="email" :name="`members[${index}][email]`" x-model="member.email"
                                               placeholder="{{ __('Email (optional)') }}" class="form-field text-[13px]">
                                        <input type="tel" :name="`members[${index}][phone]`" x-model="member.phone"
                                               placeholder="{{ __('Phone (optional)') }}" class="form-field text-[13px]">
                                    </div>

                                    <button type="button" @click="removeMember(index)"
                                            class="flex size-9 shrink-0 items-center justify-center rounded-xl text-faint transition hover:bg-negative-tint hover:text-negative"
                                            aria-label="{{ __('Remove traveller') }}">
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </template>

                        <div x-show="members.length === 0" x-cloak class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-2xl border border-dashed border-line px-3.5 py-2 text-xs font-medium text-muted transition hover:border-brand/40 hover:text-ink"
                                    @click="quickFill([{ name: '', email: '', phone: '' }])">
                                {{ __('+ Add one traveller') }}
                            </button>
                            <button type="button" class="rounded-2xl border border-dashed border-line px-3.5 py-2 text-xs font-medium text-muted transition hover:border-brand/40 hover:text-ink"
                                    @click="quickFill([{ name: '', email: '', phone: '' }, { name: '', email: '', phone: '' }, { name: '', email: '', phone: '' }])">
                                {{ __('+ Add three travellers') }}
                            </button>
                        </div>
                    </div>

                    <p class="mt-4 flex items-start gap-2 rounded-2xl border border-line bg-surface-2 p-3.5 text-xs leading-relaxed text-muted">
                        <x-icon name="info" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                        {{ __('Travellers with a Sofor account see the trip instantly. Everyone else gets the invite saved against their phone or email and joins automatically when they sign up.') }}
                    </p>
                </x-card>
            </div>

            {{-- Appearance + submit --}}
            <div class="flex flex-col gap-6">
                <x-card>
                    <h2 class="font-display text-base font-semibold">{{ __('Cover style') }}</h2>
                    <p class="mt-1 text-xs text-muted">{{ __('Give the trip a look your group will recognise.') }}</p>

                    <input type="hidden" name="cover" x-model="cover">

                    <div class="mt-4 grid grid-cols-3 gap-2.5">
                        @foreach ($covers as $key => $gradient)
                            <button type="button" @click="cover = '{{ $key }}'"
                                    :class="cover === '{{ $key }}' ? 'ring-2 ring-brand ring-offset-2 ring-offset-surface' : 'opacity-70 hover:opacity-100'"
                                    class="relative h-16 overflow-hidden rounded-2xl border-[1.5px] border-line transition"
                                    style="background-image: {{ $gradient }}"
                                    :aria-pressed="cover === '{{ $key }}'"
                                    aria-label="{{ __('Cover style: :style', ['style' => \App\Models\Trip::coverLabel($key)]) }}"
                                    title="{{ \App\Models\Trip::coverLabel($key) }}">
                                <span class="absolute inset-0 grid-lines opacity-20"></span>
                                <span class="sr-only">{{ \App\Models\Trip::coverLabel($key) }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-5">
                        <p class="text-[13px] font-medium text-muted">{{ __('Trip icon') }}</p>
                        <input type="hidden" name="icon" x-model="icon">
                        <div class="mt-2 flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('Trip icon') }}">
                            @foreach (\App\Support\Glyphs::TRIP as $choice)
                                <button type="button" role="radio" @click="icon = '{{ $choice }}'"
                                        :aria-checked="(icon === '{{ $choice }}').toString()"
                                        :class="icon === '{{ $choice }}' ? 'border-ink bg-accent-tint shadow-sticker-sm' : 'border-line bg-surface-2 hover:border-line-strong'"
                                        title="{{ \App\Support\Glyphs::label($choice) }}" aria-label="{{ \App\Support\Glyphs::label($choice) }}"
                                        class="flex size-11 items-center justify-center rounded-2xl border-[1.5px] transition"><x-glyph :name="$choice" class="size-7" /></button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6 rounded-3xl border border-line bg-surface-2 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">{{ __('Preview') }}</p>
                        <div class="mt-3 flex items-center gap-3">
                            <span class="flex size-11 items-center justify-center rounded-2xl border-[1.5px] border-line shadow-soft"
                                  :style="'background-image: ' + (covers[cover] || Object.values(covers)[0])" x-html="glyphs[icon] || glyphs.compass"></span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold" x-text="title || @js(__('Your trip name'))">{{ __('Your trip name') }}</p>
                                <p class="text-[11px] text-faint" x-text="(memberCount() === 1 ? @js(__(':count traveller')) : @js(__(':count travellers'))).replace(':count', window.SoforNum(memberCount()))"></p>
                            </div>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <h2 class="font-display text-base font-semibold">{{ __('How the split works') }}</h2>
                    <ul class="mt-4 flex flex-col gap-3 text-[13px] leading-relaxed text-muted">
                        <li class="flex gap-2.5">
                            <x-icon name="bolt" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                            {{ __('Every cost you log is divided by the travellers included in the split.') }}
                        </li>
                        <li class="flex gap-2.5">
                            <x-icon name="users" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                            {{ __('You can exclude someone from a specific cost, or give them a heavier share.') }}
                        </li>
                        <li class="flex gap-2.5">
                            <x-icon name="lock" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                            {{ __('Ending the trip locks the ledger so the final numbers cannot change.') }}
                        </li>
                    </ul>

                    <x-btn type="submit" size="lg" block icon="sparkles" class="mt-5">{{ __('Create trip and open ledger') }}</x-btn>
                    <p class="mt-3 text-center text-[11px] text-faint">{{ __('You can invite more travellers any time.') }}</p>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
