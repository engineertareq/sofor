@php
    $stats = $ledger['stats'];
    $me = $ledger['me'];
    $members = $ledger['members'];
    $splitMembers = $members->filter(fn ($m) => $m->isTravelling())->values();
    $potHolders = ($pots ?? collect())->filter(fn ($pot) => $pot['collection']->isOpen())->pluck('collection.collector_member_id')->unique();
    $payerOptions = $splitMembers->mapWithKeys(fn ($m) => [$m->id => $m->name.($potHolders->contains($m->id) ? ' - '.__('from the pot') : '')])->all();

    $expenseForm = [
        'open' => false,
        'editAction' => null,
        'title' => '',
        'amount' => '',
        'category' => 'accommodation',
        'spentOn' => now()->toDateString(),
        'payer' => (string) ($currentMember?->id ?? $members->first()?->id),
        'splitType' => 'equal',
        'notes' => '',
        'participants' => $members->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'units' => (int) $m->share_units,
            'selected' => (bool) ($m->include_in_split && $m->isTravelling()),
            'custom' => '',
        ])->values()->all(),
    ];
@endphp

<x-layouts.app :title="$trip->title" :active="'trips'" :heading="__('Trip ledger')"
               :subtitle="$trip->destination ?: null" :back="route('trips.index')" wide>
    <x-slot:actions>
        @if ($canRecord)
            <x-btn type="button" size="sm" variant="secondary" icon="wallet" @click="$dispatch('sofor-settle', {})" class="hidden sm:inline-flex">{{ __('Record payment') }}</x-btn>
        @endif

        @if ($canAddExpense)
            <x-btn type="button" size="sm" icon="plus" @click="$dispatch('sofor-add-expense')" class="hidden sm:inline-flex">{{ __('Add cost') }}</x-btn>
        @endif

        <div class="relative" x-data="{ menu: false }" @click.outside="menu = false">
            <button type="button" @click="menu = !menu"
                    class="flex size-10 items-center justify-center rounded-2xl border border-line bg-surface text-muted transition hover:text-ink"
                    aria-label="{{ __('Trip options') }}">
                <x-icon name="dots" class="size-4.5" />
            </button>

            <div x-show="menu" x-cloak x-transition.origin.top.right
                 class="absolute right-0 top-12 z-50 w-60 overflow-hidden rounded-3xl border-[1.5px] border-line bg-surface p-1.5 shadow-[0_24px_48px_-24px_rgba(23,33,31,.3)]">
                @if ($canManage)
                    <button type="button" @click="menu = false; $dispatch('sofor-share')"
                            class="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-[13px] font-medium text-muted transition hover:bg-surface-2 hover:text-ink">
                        <x-icon name="share" class="size-4" /> {{ __('Invite travellers') }}
                    </button>
                @endif

                @if ($canRecord)
                    <button type="button" @click="menu = false; $dispatch('sofor-settle', {})"
                            class="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-[13px] font-medium text-muted transition hover:bg-surface-2 hover:text-ink">
                        <x-icon name="wallet" class="size-4" /> {{ __('Record a payment') }}
                    </button>
                @endif

                <a href="{{ route('trips.edit', $trip) }}"
                   class="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-[13px] font-medium text-muted transition hover:bg-surface-2 hover:text-ink">
                    <x-icon name="pencil" class="size-4" /> {{ __('Trip details') }}
                </a>

                <a href="{{ route('trips.timeline', $trip) }}"
                   class="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-[13px] font-medium text-muted transition hover:bg-surface-2 hover:text-ink">
                    <x-icon name="list" class="size-4" /> {{ __('Cost timeline') }}
                </a>

                <a href="{{ route('trips.recap', $trip) }}"
                   class="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-[13px] font-medium text-muted transition hover:bg-surface-2 hover:text-ink">
                    <x-icon name="sparkles" class="size-4" /> {{ __('Trip recap card') }}
                </a>

                <a href="{{ route('trips.manifest', $trip) }}"
                   class="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-[13px] font-medium text-muted transition hover:bg-surface-2 hover:text-ink">
                    <x-icon name="shield" class="size-4" /> {{ __('Checkpoint list') }}
                </a>

                <a href="{{ route('trips.summary', $trip) }}" @click="menu = false"
                   class="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-[13px] font-medium text-muted transition hover:bg-surface-2 hover:text-ink">
                    <x-icon name="download" class="size-4" /> {{ __('Download PDF summary') }}
                </a>

                @if ($canManage && $trip->isActive())
                    <div class="my-1 border-t border-line"></div>
                    <button type="button" @click="menu = false; $dispatch('sofor-end-trip')"
                            class="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-[13px] font-medium text-warning transition hover:bg-warning/10">
                        <x-icon name="lock" class="size-4" /> {{ __('End this trip') }}
                    </button>
                @endif

                @if ($canManage)
                    @can('delete', $trip)
                        <form method="POST" action="{{ route('trips.destroy', $trip) }}"
                              onsubmit="return confirm('Delete this trip and every cost in it? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-[13px] font-medium text-negative transition hover:bg-negative/10">
                                <x-icon name="trash" class="size-4" /> {{ __('Delete trip') }}
                            </button>
                        </form>
                    @else
                        <p class="px-3 py-2 text-[11.5px] leading-relaxed text-faint">
                            {{ __('Travellers paid through bKash on this trip, so it is kept as their record. End it instead.') }}
                        </p>
                    @endcan
                @elseif ($currentMember)
                    <div class="my-1 border-t border-line"></div>
                    <form method="POST" action="{{ route('trips.leave', $trip) }}"
                          onsubmit="return confirm('Leave this trip? Your past costs stay in the ledger.')">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-[13px] font-medium text-negative transition hover:bg-negative/10">
                            <x-icon name="logout" class="size-4" /> {{ __('Leave trip') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot:actions>

    <div class="flex flex-col gap-6" data-trip-live
         data-live-url="{{ route('trips.live', $trip) }}"
         data-stream-url="{{ route('trips.stream', $trip) }}"
         data-stamp="{{ $trip->activityStamp() }}"
         data-activity-id="{{ $activities->max('id') ?? 0 }}">

        {{-- ================= Hero: the trip ticket ================= --}}
        <section class="rounded-5xl border-[1.5px] border-line bg-surface shadow-soft">
            {{-- Cover band --}}
            <div class="relative h-28 overflow-hidden rounded-t-5xl sm:h-36">
                <div class="absolute inset-0" style="background-image: {{ $trip->coverStyle() }}"></div>
                <div class="absolute inset-0 grid-lines opacity-30"></div>
                <div class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-surface via-surface/70 to-transparent"></div>

                <div class="absolute right-4 top-4 flex flex-wrap items-center justify-end gap-2 sm:right-6 sm:top-6">
                    @if ($trip->isEnded())
                        <span class="stamp bg-surface/85 text-faint backdrop-blur-sm">{{ __('Ended :time', ['time' => $trip->ended_at?->diffForHumans()]) }}</span>
                    @else
                        <span class="stamp bg-surface/85 text-positive backdrop-blur-sm">
                            <span class="size-1.5 animate-pulse rounded-full bg-positive"></span> {{ __('Live ledger') }}
                        </span>
                    @endif

                    <span data-live-indicator data-state="polling"
                          class="stamp bg-surface/85 text-faint backdrop-blur-sm data-[state=live]:text-brand">
                        <span class="size-1.5 animate-pulse rounded-full bg-current"></span>
                        <span data-live-label>{{ __('Syncing') }}</span>
                    </span>
                </div>
            </div>

            {{-- Identity row --}}
            <div class="relative -mt-11 px-5 sm:-mt-14 sm:px-8">
                <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-5">
                    <div class="flex min-w-0 items-end gap-4">
                        <span class="flex size-[4.4rem] shrink-0 items-center justify-center rounded-[1.6rem] border-[1.5px] border-line bg-surface shadow-sticker-sm sm:size-20"><x-glyph :name="$trip->icon" class="size-12 sm:size-14" /></span>

                        <div class="min-w-0 pb-1">
                            <h1 class="font-display text-[1.6rem] font-semibold leading-[1.08] tracking-tight sm:text-[2.1rem]">{{ $trip->title }}</h1>
                            <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-muted">
                                <span class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-3.5 text-brand" /> {{ $trip->destination ?: __('Destination TBD') }}</span>
                                @if ($trip->starts_on)
                                    <span class="flex items-center gap-1.5">
                                        <x-icon name="calendar" class="size-3.5 text-brand" />
                                        {{ $trip->starts_on->translatedFormat('d M') }}@if ($trip->ends_on) - {{ $trip->ends_on->translatedFormat('d M Y') }}@endif
                                    </span>
                                @endif
                                <span class="flex items-center gap-1.5"><x-icon name="flag" class="size-3.5 text-brand" /> {{ __('Led by :name', ['name' => $trip->owner?->name]) }}</span>
                            </p>
                        </div>
                    </div>

                    <dl class="flex items-center gap-3 pb-2 min-[360px]:gap-4 sm:gap-7">
                        <div class="min-w-0">
                            <dt class="eyebrow whitespace-nowrap text-faint max-sm:tracking-[0.06em] max-[359px]:text-[0.6rem]">{{ __('Travellers') }}</dt>
                            <dd class="num mt-1 whitespace-nowrap font-display text-base font-semibold min-[360px]:text-lg sm:text-2xl">{{ $stats['travellers'] }}</dd>
                        </div>
                        <div class="min-w-0 border-l border-line pl-3 min-[360px]:pl-4 sm:pl-7">
                            <dt class="eyebrow whitespace-nowrap text-faint max-sm:tracking-[0.06em] max-[359px]:text-[0.6rem]">{{ __('Logged') }}</dt>
                            <dd class="num mt-1 whitespace-nowrap font-display text-base font-semibold min-[360px]:text-lg sm:text-2xl">{{ \App\Support\Money::format($stats['spent'], $trip->currency) }}</dd>
                        </div>
                        <div class="min-w-0 border-l border-line pl-3 min-[360px]:pl-4 sm:pl-7">
                            <dt class="eyebrow whitespace-nowrap text-faint max-sm:tracking-[0.06em] max-[359px]:text-[0.6rem]">{{ __('Each') }}</dt>
                            <dd class="num mt-1 whitespace-nowrap font-display text-base font-semibold text-brand min-[360px]:text-lg sm:text-2xl">{{ \App\Support\Money::format($stats['per_person'], $trip->currency) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Tear here --}}
            <div class="relative mt-6 border-t-2 border-dashed border-line-strong">
                <span class="absolute -left-[0.7rem] -top-[0.6rem] size-[1.2rem] rounded-full border-[1.5px] border-line bg-canvas"></span>
                <span class="absolute -right-[0.7rem] -top-[0.6rem] size-[1.2rem] rounded-full border-[1.5px] border-line bg-canvas"></span>
            </div>

            {{-- Stub: the actionable half of the ticket --}}
            <div class="rounded-b-5xl bg-surface-2 px-5 py-4 sm:px-8 sm:py-5">
                @if ($trip->isEnded())
                    <div class="flex items-start gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-warning-tint text-warning">
                            <x-icon name="lock" class="size-4" />
                        </span>
                        <p class="text-[12.5px] leading-relaxed text-muted">
                            {{ __('This trip ended :time. New costs cannot be added, and the final split of', ['time' => $trip->ended_at?->diffForHumans()]) }}
                            <span class="num font-semibold text-ink">{{ \App\Support\Money::format($stats['spent'], $trip->currency) }}</span>
                            {{ __('across :participants travellers is locked at', ['participants' => $stats['participants']]) }}
                            <span class="num font-semibold text-ink">{{ \App\Support\Money::format($stats['per_person'], $trip->currency) }}</span> {{ __('each.') }}
                        </p>
                    </div>
                @elseif ($canManage)
                    <div class="flex flex-wrap items-center gap-2.5" x-data="clipboard(@js($inviteUrl))">
                        <span class="flex items-center gap-2 text-[12px] font-semibold text-ink">
                            <x-icon name="link" class="size-3.5 text-brand" /> {{ __('Invite link') }}
                        </span>
                        <code class="min-w-0 flex-1 truncate rounded-2xl border-[1.5px] border-line bg-surface px-3 py-2 font-mono text-[11px] text-muted">{{ $inviteUrl }}</code>
                        <button type="button" @click="copy()"
                                class="sticker flex items-center gap-1.5 rounded-2xl bg-brand px-3.5 py-2 text-[12px] font-semibold text-white">
                            <x-icon name="copy" class="size-3.5" />
                            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                        </button>
                    </div>
                @else
                    <p class="flex items-center gap-2 text-[12px] text-muted">
                        <span class="size-1.5 animate-pulse rounded-full bg-positive"></span>
                        {{ __('Costs update for everyone instantly. Ask :name to add you as an admin to invite others.', ['name' => $trip->owner?->name]) }}
                    </p>
                @endif
            </div>
        </section>

        {{-- ================= Summary (live) ================= --}}
        <section data-live="summary">
            @include('trips.partials.summary', ['trip' => $trip, 'ledger' => $ledger])
        </section>

        @include('trips.partials.travel-prompt')

        {{-- A full ledger is a lot to scroll through on a phone, so below xl these
             five long sections become tabs. From xl up the tab bar hides itself,
             each panel keeps `xl:block`, and the `contents` wrappers stop being
             boxes so the panels fall back into the original two column layout. --}}
        <div x-data="{
                 tab: ['feed', 'members', 'settle', 'plan', 'breakdown', 'activity'].includes(location.hash.slice(1))
                     ? location.hash.slice(1)
                     : 'feed',
                 select(key) {
                     this.tab = key;
                     history.replaceState(null, '', '#' + key);
                     this.$nextTick(() => this.$refs.tabs?.scrollIntoView({ block: 'start', behavior: 'smooth' }));
                 },
             }"
             class="flex min-w-0 flex-col gap-4 xl:gap-0">

            <div x-ref="tabs" class="scroll-mt-20 xl:hidden">
                <div class="no-scrollbar -mx-3 flex gap-2 overflow-x-auto px-3 pb-1 sm:-mx-6 sm:px-6"
                     aria-label="{{ __('Trip sections') }}">
                    @foreach ([
                        'feed' => __('Cost ledger'),
                        'members' => __('Travellers & dues'),
                        'settle' => __('Settle up'),
                        'plan' => __('Plan & polls'),
                        'breakdown' => __('Where it went'),
                        'activity' => __('Timeline'),
                    ] as $key => $label)
                        <button type="button" @click="select('{{ $key }}')"
                                :aria-pressed="tab === '{{ $key }}'"
                                :class="tab === '{{ $key }}'
                                    ? 'border-ink bg-brand text-white shadow-sticker-sm'
                                    : 'border-line bg-surface text-muted'"
                                class="shrink-0 rounded-2xl border-[1.5px] px-3.5 py-2 text-[12.5px] font-semibold transition">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="grid min-w-0 gap-6 xl:grid-cols-[1.55fr_1fr] xl:items-start">
                <div class="contents xl:flex xl:min-w-0 xl:flex-col xl:gap-6">
                    <div data-live="feed" class="min-w-0 xl:block" :class="{ hidden: tab !== 'feed' }">
                        @include('trips.partials.feed', ['trip' => $trip, 'expenses' => $expenses, 'canAddExpense' => $canAddExpense, 'canManage' => $canManage])
                    </div>

                    <div data-live="settle" class="hidden min-w-0 xl:block" :class="{ hidden: tab !== 'settle' }">
                        @include('trips.partials.settle', ['trip' => $trip, 'ledger' => $ledger, 'settlements' => $settlements, 'canRecord' => $canRecord])
                    </div>
                </div>

                <div class="contents xl:flex xl:min-w-0 xl:flex-col xl:gap-6">
                    <div data-live="plan" class="hidden min-w-0 xl:block" :class="{ hidden: tab !== 'plan' }">
                        @include('trips.partials.plan')
                    </div>

                    <div data-live="members" class="hidden min-w-0 xl:block" :class="{ hidden: tab !== 'members' }">
                        @include('trips.partials.members', ['trip' => $trip, 'ledger' => $ledger, 'canManage' => $canManage, 'currentMember' => $currentMember])
                    </div>

                    <div data-live="breakdown" class="hidden min-w-0 xl:block" :class="{ hidden: tab !== 'breakdown' }">
                        @include('trips.partials.breakdown', ['trip' => $trip, 'ledger' => $ledger])
                    </div>

                    <div data-live="activity" class="hidden min-w-0 xl:block" :class="{ hidden: tab !== 'activity' }">
                        @include('trips.partials.activity', ['activities' => $activities])
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($trip->description)
        <x-card class="mt-6">
            <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                <x-icon name="info" class="size-4 text-brand" /> {{ __('Trip notes') }}
            </h2>
            <p class="mt-3 whitespace-pre-line text-[13px] leading-relaxed text-muted">{{ $trip->description }}</p>
        </x-card>
    @endif

    {{-- ================= Floating mobile actions ================= --}}
    @if ($canRecord)
        <button type="button" @click="$dispatch('sofor-settle', {})"
                class="fixed bottom-40 right-4 z-40 flex size-12 items-center justify-center rounded-3xl border border-line bg-surface text-brand shadow-soft transition active:scale-95 lg:hidden"
                aria-label="{{ __('Record a payment') }}">
            <x-icon name="wallet" class="size-5" />
        </button>
    @endif

    @if ($canAddExpense)
        <button type="button" @click="$dispatch('sofor-add-expense')"
                class="sticker fixed bottom-24 right-4 z-40 flex size-14 items-center justify-center rounded-3xl border-[1.5px] border-ink bg-accent text-ink lg:hidden"
                aria-label="{{ __('Add cost') }}">
            <x-icon name="plus" class="size-6" />
        </button>
    @endif

    {{-- ================= Modals ================= --}}
    @if ($canAddExpense)
        <div x-data="expenseSheet({{ \Illuminate\Support\Js::from($expenseForm) }}, {{ \App\Services\ReceiptService::MAX_KILOBYTES }})"
             @sofor-add-expense.window="add()"
             @sofor-edit-expense.window="edit($event.detail)">
            <template x-teleport="body">
                <x-sheet title="{{ __('Log a trip cost') }}" :subtitle="__('Sofor splits it across the travellers you pick')" icon="receipt" size="lg">
                    <form method="POST" x-bind:action="editAction || '{{ route('trips.expenses.store', $trip) }}'" enctype="multipart/form-data" class="flex flex-col gap-4"
                          data-offline-queue x-on:sofor-queued="open = false">
                        @csrf
                        <input type="hidden" name="client_uuid" :value="editAction ? '' : clientUuid">
                        <template x-if="editAction">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="title" :label="__('What was it for?')" required>
                                <x-input name="title" placeholder="{{ __('e.g. Cottage booking') }}" x-model="title" required />
                            </x-field>

                            <x-field name="amount" :label="__('Amount')" :hint="__('In :currency - the bill total, with VAT and service', ['currency' => $trip->currency])" required>
                                <x-input name="amount" type="number" step="0.01" min="0.01" placeholder="5000" x-model="amount" required />
                            </x-field>
                        </div>

                        <x-field name="category" :label="__('Category')">
                            <input type="hidden" name="category" x-model="category">
                            <div class="grid grid-cols-4 gap-2 sm:grid-cols-8" role="radiogroup" aria-label="{{ __('Category') }}">
                                @foreach (\App\Enums\ExpenseCategory::cases() as $case)
                                    <button type="button" role="radio" @click="category = '{{ $case->value }}'"
                                            :aria-checked="(category === '{{ $case->value }}').toString()"
                                            :class="category === '{{ $case->value }}' ? 'border-ink bg-accent-tint shadow-sticker-sm' : 'border-line bg-surface-2 hover:border-line-strong'"
                                            class="flex min-w-0 flex-col items-center gap-1 rounded-2xl border-[1.5px] px-1 py-2 transition">
                                        <x-glyph :name="$case->icon()" class="size-7" />
                                        <span class="w-full truncate text-center text-[10.5px] font-semibold text-ink">{{ $case->label() }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </x-field>

                        <div class="grid gap-4 sm:grid-cols-2">

                            <x-field name="spent_on" :label="__('Date')">
                                <x-input name="spent_on" type="date" x-model="spentOn" max="{{ now()->toDateString() }}" required />
                            </x-field>

                            <x-field name="trip_member_id" :label="__('Who paid?')">
                                <x-select name="trip_member_id" x-model="payer" :options="$payerOptions" />
                            </x-field>
                        </div>

                        {{-- Split control --}}
                        <div class="rounded-3xl border border-line bg-surface-2 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-[13px] font-semibold text-ink">{{ __('Split between') }}</p>
                                    <p class="text-[11px] text-faint" x-show="splitType !== 'itemised'">
                                        <span x-text="selectedCount()"></span> {{ __('travellers selected') }}
                                        <template x-if="amount && selectedCount()">
                                            <span>- <span class="font-semibold text-brand"
                                                    x-text="@js(\App\Support\Money::symbol($trip->currency)) + perShare().toLocaleString(window.SoforLocale, { maximumFractionDigits: 2 }) + ' per share'"></span></span>
                                        </template>
                                    </p>
                                    <p class="text-[11px] text-faint" x-show="splitType === 'itemised'" x-cloak>{{ __('Each person pays for what they had') }}</p>
                                </div>

                                <div class="flex items-center gap-1 rounded-2xl border border-line bg-surface p-1" role="radiogroup" aria-label="{{ __('How to split') }}">
                                    <button type="button" role="radio" :aria-checked="splitType === 'equal'" @click="splitType = 'equal'"
                                            :class="splitType === 'equal' ? 'bg-brand/15 text-brand' : 'text-muted'"
                                            class="rounded-xl px-3 py-1.5 text-[11px] font-semibold transition">{{ __('Equally') }}</button>
                                    <button type="button" role="radio" :aria-checked="splitType === 'custom'" @click="splitType = 'custom'"
                                            :class="splitType === 'custom' ? 'bg-brand/15 text-brand' : 'text-muted'"
                                            class="rounded-xl px-3 py-1.5 text-[11px] font-semibold transition">{{ __('Custom') }}</button>
                                    <button type="button" role="radio" :aria-checked="splitType === 'itemised'" @click="useItems()"
                                            :class="splitType === 'itemised' ? 'bg-brand/15 text-brand' : 'text-muted'"
                                            class="rounded-xl px-3 py-1.5 text-[11px] font-semibold transition">{{ __('By item') }}</button>
                                </div>
                            </div>

                            <input type="hidden" name="split_type" x-model="splitType">

                            {{-- Equal and custom: tick who shares it --}}
                            <div class="mt-3 flex flex-col gap-2" x-show="splitType !== 'itemised'">
                                <template x-for="participant in participants" :key="participant.id">
                                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-line bg-surface px-3 py-2.5">
                                        <input type="checkbox" x-model="participant.selected"
                                               name="participant_ids[]" :value="participant.id"
                                               class="size-4 rounded-md border-line bg-surface-2 text-brand focus:ring-brand/40">
                                        <span class="min-w-0 flex-1 truncate text-[13px] font-medium" x-text="participant.name"></span>

                                        <template x-if="participant.units > 1">
                                            <span class="rounded-full bg-brand-tint px-2 py-0.5 text-[10px] font-bold text-brand" x-text="participant.units + @js(__('x share'))"></span>
                                        </template>

                                        <template x-if="splitType === 'custom' && participant.selected">
                                            <input type="number" step="0.01" min="0" placeholder="0"
                                                   :name="`custom_shares[${participant.id}]`"
                                                   x-model="participant.custom"
                                                   class="w-24 rounded-xl border border-line bg-surface-2 px-2.5 py-1.5 text-right text-[12px]">
                                        </template>
                                    </label>
                                </template>
                            </div>

                            {{-- Itemised: the lines of the bill and who had each --}}
                            <template x-if="splitType === 'itemised'">
                                <div class="mt-3 flex flex-col gap-2.5">
                                    <template x-for="(item, index) in items" :key="item.key">
                                        <div class="rounded-2xl border border-line bg-surface p-3">
                                            <div class="flex items-center gap-2">
                                                <input type="text" data-item-name maxlength="60" placeholder="{{ __('e.g. Chicken biryani') }}"
                                                       :name="`items[${index}][name]`" x-model="item.name" required
                                                       class="min-w-0 flex-1 rounded-xl border border-line bg-surface-2 px-3 py-2 text-[13px]"
                                                       aria-label="{{ __('Item') }}">
                                                <input type="number" step="0.01" min="0.01" placeholder="0" inputmode="decimal"
                                                       :name="`items[${index}][price]`" x-model="item.price" required
                                                       class="w-24 rounded-xl border border-line bg-surface-2 px-2.5 py-2 text-right text-[13px]"
                                                       aria-label="{{ __('Price') }}">
                                                <button type="button" @click="removeItem(index)"
                                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg text-faint transition hover:bg-negative-tint hover:text-negative"
                                                        aria-label="{{ __('Remove item') }}">
                                                    <x-icon name="close" class="size-3.5" />
                                                </button>
                                            </div>

                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                <template x-for="participant in participants.filter(p => p.selected || item.members.includes(p.id))" :key="participant.id">
                                                    <button type="button" @click="toggleItemMember(item, participant.id)"
                                                            :aria-pressed="item.members.includes(participant.id)"
                                                            :class="item.members.includes(participant.id) ? 'border-brand/40 bg-brand/12 text-brand' : 'border-line bg-surface-2 text-faint line-through'"
                                                            class="rounded-full border px-2.5 py-1 text-[11px] font-semibold transition">
                                                        <span x-text="participant.name.split(' ')[0]"></span>
                                                    </button>
                                                </template>
                                            </div>

                                            <template x-for="id in item.members" :key="id">
                                                <input type="hidden" :name="`items[${index}][members][]`" :value="id">
                                            </template>
                                        </div>
                                    </template>

                                    <button type="button" @click="addItem()" x-show="items.length < 40"
                                            class="flex items-center justify-center gap-1.5 rounded-2xl border border-dashed border-line-strong px-3 py-2.5 text-[12px] font-semibold text-muted transition hover:border-brand hover:text-brand">
                                        <x-icon name="plus" class="size-3.5" /> {{ __('Add an item') }}
                                    </button>

                                    <div class="rounded-2xl bg-surface px-3 py-2.5 text-[12px]" x-show="itemsSubtotal() > 0">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-muted">{{ __('Items') }} <span class="num font-semibold text-ink" x-text="@js(\App\Support\Money::symbol($trip->currency)) + itemsSubtotal().toLocaleString(window.SoforLocale)"></span></span>
                                            <span class="flex flex-wrap gap-1">
                                                <button type="button" @click="useItemsTotal()" class="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-semibold text-muted hairline hover:text-ink">{{ __('Total = items') }}</button>
                                                <button type="button" @click="addCharge(10)" class="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-semibold text-muted hairline hover:text-ink">{{ __('+10% service') }}</button>
                                                <button type="button" @click="addCharge(5)" class="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-semibold text-muted hairline hover:text-ink">{{ __('+5% VAT') }}</button>
                                                <button type="button" @click="addCharge(15)" class="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-semibold text-muted hairline hover:text-ink">{{ __('+15% VAT') }}</button>
                                            </span>
                                        </div>
                                        <p class="mt-1.5 text-[11px] text-faint" x-show="amount && Math.abs(extras()) >= 0.01">
                                            <span x-text="extras() > 0 ? @js(__('VAT, service and tips')) : @js(__('Discount'))"></span>
                                            <span class="num font-semibold text-ink" x-text="@js(\App\Support\Money::symbol($trip->currency)) + Math.abs(extras()).toLocaleString(window.SoforLocale)"></span>
                                            {{ __('spread in proportion to what each person had.') }}
                                        </p>
                                    </div>

                                    <ul class="flex flex-col gap-1" x-show="itemPeople().length">
                                        <template x-for="participant in itemPeople()" :key="participant.id">
                                            <li class="flex items-center justify-between gap-3 px-1 text-[12px]">
                                                <span class="truncate text-muted" x-text="participant.name"></span>
                                                <span class="num font-semibold text-ink" x-text="@js(\App\Support\Money::symbol($trip->currency)) + itemShare(participant.id).toLocaleString(window.SoforLocale, { maximumFractionDigits: 2 })"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>

                            @error('items')
                                <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-negative"><x-icon name="alert" class="size-3.5 shrink-0" /> {{ $message }}</p>
                            @enderror

                            <p class="mt-3 flex items-start gap-2 text-[11px] leading-relaxed text-faint">
                                <x-icon name="info" class="mt-0.5 size-3.5 shrink-0" />
                                <span x-show="splitType !== 'itemised'">{{ __('Unchecked travellers are not charged for this cost. Custom amounts are scaled to match the total exactly.') }}</span>
                                <span x-show="splitType === 'itemised'" x-cloak>{{ __('Shared dishes are split between the people tapped on them. The shares always add up to the amount.') }}</span>
                            </p>
                        </div>

                        {{-- Receipt photo --}}
                        <div>
                            <p class="mb-1.5 text-[13px] font-medium text-muted">{{ __('Photo of the bill') }} <span class="text-faint">{{ __('(optional)') }}</span></p>

                            <div class="flex items-center gap-3 rounded-3xl border border-dashed border-line-strong bg-surface-2 p-3">
                                <template x-if="receiptPreview || (existingReceipt && ! removeReceipt)">
                                    <img :src="receiptPreview || existingReceipt" alt="{{ __('Receipt preview') }}"
                                         class="size-14 shrink-0 rounded-2xl object-cover hairline">
                                </template>
                                <template x-if="! receiptPreview && ! (existingReceipt && ! removeReceipt)">
                                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-surface text-faint hairline">
                                        <x-icon name="camera" class="size-5" />
                                    </span>
                                </template>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[12px] font-semibold text-ink"
                                       x-text="receiptName || (existingReceipt && ! removeReceipt ? @js(__('Receipt attached')) : @js(__('Snap or upload the bill')))"></p>
                                    <p class="text-[11px] text-faint">{{ __('Only travellers on this trip can see it. Location data is removed.') }}</p>
                                </div>

                                <label class="shrink-0 cursor-pointer rounded-full bg-surface px-3 py-1.5 text-[12px] font-semibold text-brand hairline transition hover:bg-brand/10">
                                    <span x-text="receiptPreview || (existingReceipt && ! removeReceipt) ? @js(__('Change')) : @js(__('Add photo'))">{{ __('Add photo') }}</span>
                                    <input type="file" name="receipt" accept="image/jpeg,image/png,image/webp" data-receipt-input class="sr-only" @change="pickReceipt($event)">
                                </label>

                                <button type="button" x-show="receiptPreview || (existingReceipt && ! removeReceipt)"
                                        @click="receiptPreview ? clearReceipt() : (removeReceipt = true)"
                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg text-faint transition hover:bg-negative-tint hover:text-negative"
                                        aria-label="{{ __('Remove photo') }}">
                                    <x-icon name="close" class="size-3.5" />
                                </button>
                            </div>
                            <input type="hidden" name="remove_receipt" :value="removeReceipt ? 1 : 0">

                            @error('receipt')
                                <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-negative"><x-icon name="alert" class="size-3.5 shrink-0" /> {{ $message }}</p>
                            @enderror
                        </div>

                        <x-field name="notes" :label="__('Notes')" :hint="__('Optional')">
                            <x-textarea name="notes" rows="2" x-model="notes" placeholder="{{ __('Anything the group should know') }}" />
                        </x-field>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-btn type="button" variant="ghost" @click="open = false">{{ __('Cancel') }}</x-btn>
                            <x-btn type="submit" icon="check" x-text="editAction ? {{ \Illuminate\Support\Js::from(__('Save changes')) }} : {{ \Illuminate\Support\Js::from(__('Add cost and split')) }}"></x-btn>
                        </div>
                    </form>
                </x-sheet>
            </template>
        </div>
    @endif

    {{-- Receipt photos --}}
    <div x-data="{ open: false, src: '', title: '' }" @sofor-receipt.window="open = true; src = $event.detail.src; title = $event.detail.title">
        <template x-teleport="body">
            <x-sheet title="{{ __('Receipt') }}" icon="camera" size="lg">
                <div class="flex flex-col gap-3">
                    <p class="text-[13px] font-semibold text-ink" x-text="title"></p>
                    <a :href="src" target="_blank" rel="noopener" class="block overflow-hidden rounded-3xl bg-surface-2 hairline">
                        <img :src="src" :alt="@js(__('Receipt for :title')).replace(':title', title)" class="mx-auto max-h-[70vh] w-auto object-contain">
                    </a>
                    <p class="text-[11px] text-faint">{{ __('Tap the photo to open it full size.') }}</p>
                </div>
            </x-sheet>
        </template>
    </div>

    {{-- Invite + share (leader only) --}}
    @if ($canManage)
        <div x-data="{ open: false, url: @js($inviteUrl) }" @sofor-share.window="open = true">
            <template x-teleport="body">
                <x-sheet title="{{ __('Invite travellers') }}" :subtitle="__('Share the link or add someone directly')" icon="share" size="md">
                    <div class="flex flex-col gap-5">
                        <div>
                            <p class="text-[13px] font-semibold text-ink">{{ __('Invite link') }}</p>
                            <p class="mt-1 text-[11px] leading-relaxed text-muted">
                                {{ __('Anyone with this link can join the trip and see the split. Rotate it if it leaks.') }}
                            </p>

                            <div class="mt-3 flex items-center gap-2 rounded-2xl border border-line bg-surface-2 p-2" x-data="clipboard(@js($inviteUrl))">
                                <code class="min-w-0 flex-1 truncate px-2 py-1 font-mono text-[11px] text-muted">{{ $inviteUrl }}</code>
                                <button type="button" @click="copy()"
                                        class="flex items-center gap-1.5 rounded-xl bg-brand/12 px-3 py-2 text-[12px] font-semibold text-brand transition hover:bg-brand/18">
                                    <x-icon name="copy" class="size-3.5" />
                                    <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                                </button>
                            </div>

                            <form method="POST" action="{{ route('trips.invite.rotate', $trip) }}" class="mt-3"
                                  onsubmit="return confirm('Generate a new invite link? The current one stops working.')">
                                @csrf
                                <x-btn type="submit" variant="outline" size="sm" icon="refresh">{{ __('Rotate invite link') }}</x-btn>
                            </form>
                        </div>

                        <div class="border-t border-line pt-5">
                            <p class="text-[13px] font-semibold text-ink">{{ __('Add traveller manually') }}</p>
                            <p class="mt-1 text-[11px] text-muted">{{ __('They see the trip immediately if they already have an account.') }}</p>

                            <form method="POST" action="{{ route('trips.members.store', $trip) }}" class="mt-3 flex flex-col gap-3">
                                @csrf
                                <x-field name="name" :label="__('Name')" required>
                                    <x-input name="name" placeholder="{{ __('Traveller name') }}" required />
                                </x-field>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-field name="phone" :label="__('Phone')">
                                        <x-input name="phone" type="tel" placeholder="01712345678" />
                                    </x-field>
                                    <x-field name="email" :label="__('Email')">
                                        <x-input name="email" type="email" placeholder="name@example.com" />
                                    </x-field>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-field name="share_units" :label="__('Shares')" :hint="__('2 means they take a double share')">
                                        <x-input name="share_units" type="number" min="1" max="20" value="1" />
                                    </x-field>
                                    <label class="flex cursor-pointer items-center gap-3 self-end rounded-2xl border border-line bg-surface-2 px-3.5 py-3 text-[12px] text-muted">
                                        <input type="checkbox" name="include_in_split" value="1" checked
                                               class="size-4 rounded-md border-line bg-surface text-brand focus:ring-brand/40">
                                        {{ __('Include in the split') }}
                                    </label>
                                </div>
                                <x-btn type="submit" icon="user-plus" block>{{ __('Add traveller') }}</x-btn>
                            </form>
                        </div>
                    </div>
                </x-sheet>
            </template>
        </div>

        <div x-data="{ open: false, memberAction: '', memberName: '', memberUnits: 1, memberIncluded: true }"
             @sofor-edit-member.window="open = true; memberAction = $event.detail.action; memberName = $event.detail.name; memberUnits = $event.detail.units; memberIncluded = !!$event.detail.included">
            <template x-teleport="body">
                <x-sheet title="{{ __('Traveller share settings') }}" icon="settings" size="sm">
                    <form method="POST" :action="memberAction" class="flex flex-col gap-4">
                        @csrf
                        @method('PUT')

                        <p class="text-[13px] text-muted">
                            {{ __('Adjusting') }} <span class="font-semibold text-ink" x-text="memberName"></span>
                        </p>

                        <x-field name="name" :label="__('Name')">
                            <x-input name="name" x-model="memberName" />
                        </x-field>

                        <x-field name="share_units" :label="__('Shares')" :hint="__('Weight this traveller\'s part of every cost')">
                            <x-input name="share_units" type="number" min="1" max="20" x-model="memberUnits" />
                        </x-field>

                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-2xl border border-line bg-surface-2 px-3.5 py-3 text-[12px] text-muted">
                            {{ __('Include this traveller in new costs') }}
                            <input type="checkbox" name="include_in_split" value="1" x-model="memberIncluded"
                                   class="size-4 rounded-md border-line bg-surface text-brand focus:ring-brand/40">
                        </label>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-btn type="button" variant="ghost" @click="open = false">{{ __('Cancel') }}</x-btn>
                            <x-btn type="submit" icon="check">{{ __('Save traveller') }}</x-btn>
                        </div>
                    </form>
                </x-sheet>
            </template>
        </div>
    @endif

    {{-- Check in (any traveller) --}}
    @if ($currentMember && $currentMember->isTravelling() && $trip->isActive())
        @php
            $checkinSms = app(\App\Services\SmsWallet::class);
        @endphp
        <div x-data="{
                 open: false, place: '', note: '', sms: false, lat: '', lng: '', locating: false, located: false,
                 locate() {
                     if (! navigator.geolocation) { window.Sofor?.toast(window.SoforT('This browser cannot share a location.'), 'warning'); return; }
                     // Browsers only share a location over https.
                     if (! window.isSecureContext) { window.Sofor?.toast(@js(__('Location needs a secure (https) connection. The check-in still works without it.')), 'warning'); return; }
                     this.locating = true;
                     navigator.geolocation.getCurrentPosition(
                         (pos) => { this.lat = pos.coords.latitude.toFixed(6); this.lng = pos.coords.longitude.toFixed(6); this.located = true; this.locating = false; },
                         (error) => {
                             this.locating = false;
                             window.Sofor?.toast(error.code === 1
                                 ? @js(__('Location is blocked for Sofor. Allow it in your browser or phone settings, then tap again.'))
                                 : (error.code === 3
                                     ? @js(__('Could not find you in time. Try again in the open, or check in without it.'))
                                     : window.SoforT('Location was not shared. The check-in still works without it.')), 'warning');
                         },
                         { enableHighAccuracy: true, timeout: 20000, maximumAge: 60000 },
                     );
                 },
             }"
             @sofor-checkin.window="open = true; place = ''; note = ''; lat = ''; lng = ''; located = false">
            <template x-teleport="body">
                <x-sheet title="{{ __('Check in') }}" :subtitle="__('The group sees it, and so does your family link')" icon="map-pin" size="sm">
                    <form method="POST" action="{{ route('trips.checkins.store', $trip) }}" class="flex flex-col gap-4">
                        @csrf

                        <div class="flex flex-wrap gap-1.5">
                            @foreach (\App\Models\TripCheckin::PRESETS as $preset)
                                <button type="button" @click="place = @js($preset === 'Reached' ? __('Reached :place', ['place' => \Illuminate\Support\Str::before((string) $trip->destination, ',') ?: __('the destination')]) : __($preset))"
                                        class="rounded-full border border-line bg-surface-2 px-3 py-1.5 text-[12px] font-semibold text-muted transition hover:border-brand hover:text-brand">
                                    {{ $preset === 'Reached' ? __('Reached :place', ['place' => \Illuminate\Support\Str::before((string) $trip->destination, ',') ?: __('the destination')]) : __($preset) }}
                                </button>
                            @endforeach
                        </div>

                        <x-field name="place" :label="__('Where are you?')" required>
                            <x-input name="place" maxlength="80" x-model="place" placeholder="{{ __('e.g. Khagrachari bus stand') }}" required />
                        </x-field>

                        <x-field name="note" :label="__('Anything to add?')" :hint="__('Optional')">
                            <x-input name="note" maxlength="160" x-model="note" placeholder="{{ __('All good, network is weak here') }}" />
                        </x-field>

                        <input type="hidden" name="latitude" :value="lat">
                        <input type="hidden" name="longitude" :value="lng">

                        <button type="button" @click="locate()" :disabled="locating"
                                class="flex items-center justify-center gap-2 rounded-2xl border border-dashed border-line-strong px-3 py-2.5 text-[12px] font-semibold transition"
                                :class="located ? 'border-positive/50 bg-positive-tint text-positive' : 'text-muted hover:border-brand hover:text-brand'">
                            <x-icon name="map-pin" class="size-3.5" />
                            <span x-text="located ? @js(__('Location added')) : (locating ? @js(__('Finding you...')) : @js(__('Add my location (optional)')))"></span>
                        </button>

                        @if ($currentMember->family_phone)
                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line bg-surface-2 px-3 py-2.5">
                                <input type="checkbox" name="sms" value="1" x-model="sms"
                                       class="mt-0.5 size-4 rounded-md border-line bg-surface text-brand focus:ring-brand/40">
                                <span class="text-[12.5px] text-ink">
                                    {{ __('Text my family too') }}
                                    <span class="block text-[11px] text-faint">
                                        {{ __(':amount from your SMS wallet (:balance left)', ['amount' => \App\Support\Money::format($checkinSms->price()), 'balance' => \App\Support\Money::format((float) auth()->user()->sms_balance)]) }}
                                    </span>
                                </span>
                            </label>
                        @endif

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-btn type="button" variant="ghost" @click="open = false">{{ __('Cancel') }}</x-btn>
                            <x-btn type="submit" icon="check">{{ __('Check in') }}</x-btn>
                        </div>
                    </form>
                </x-sheet>
            </template>
        </div>
    @endif

    {{-- Ask the group (any traveller) --}}
    @if ($currentMember && $currentMember->isTravelling() && $trip->isActive())
        <div x-data="{ open: false, options: ['', ''], multiple: false }" @sofor-poll.window="open = true">
            <template x-teleport="body">
                <x-sheet title="{{ __('Ask the group') }}" :subtitle="__('Everyone on the trip gets a notification to vote')" icon="check-circle" size="md">
                    <form method="POST" action="{{ route('trips.polls.store', $trip) }}" class="flex flex-col gap-4">
                        @csrf

                        <x-field name="question" :label="__('Question')" required>
                            <x-input name="question" maxlength="140" :value="old('question')" placeholder="{{ __('e.g. Which bus should we take?') }}" required />
                        </x-field>

                        <div>
                            <p class="mb-1.5 text-[13px] font-medium text-muted">{{ __('Choices') }}</p>
                            <div class="flex flex-col gap-2">
                                <template x-for="(option, index) in options" :key="index">
                                    <div class="flex items-center gap-2">
                                        <input type="text" name="options[]" maxlength="80" x-model="options[index]"
                                               :placeholder="[@js(__('Hanif at 10pm')), @js(__('Shyamoli at 11pm')), @js(__('Green Line at 9pm')), @js(__('Train')), @js(__('Something else')), @js(__('Another'))][index]"
                                               class="form-field">
                                        <button type="button" x-show="options.length > 2" @click="options.splice(index, 1)"
                                                class="flex size-9 shrink-0 items-center justify-center rounded-xl text-faint transition hover:bg-negative-tint hover:text-negative"
                                                aria-label="{{ __('Remove choice') }}">
                                            <x-icon name="close" class="size-3.5" />
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" x-show="options.length < {{ \App\Models\TripPoll::MAX_OPTIONS }}" @click="options.push('')"
                                    class="mt-2 flex items-center gap-1.5 text-[12px] font-semibold text-brand hover:underline">
                                <x-icon name="plus" class="size-3.5" /> {{ __('Add a choice') }}
                            </button>
                            @error('options')
                                <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-negative"><x-icon name="alert" class="size-3.5 shrink-0" /> {{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-line bg-surface-2 px-3 py-2.5">
                                <input type="checkbox" name="multiple" value="1" x-model="multiple"
                                       class="size-4 rounded-md border-line bg-surface text-brand focus:ring-brand/40">
                                <span class="text-[12.5px] font-medium text-ink">{{ __('People can pick more than one') }}</span>
                            </label>

                            <x-field name="closes_at" :label="__('Voting closes')" :hint="__('Optional')">
                                <x-input name="closes_at" type="datetime-local" min="{{ now()->format('Y-m-d\TH:i') }}" :value="old('closes_at')" />
                            </x-field>
                        </div>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-btn type="button" variant="ghost" @click="open = false">{{ __('Cancel') }}</x-btn>
                            <x-btn type="submit" icon="check">{{ __('Post poll') }}</x-btn>
                        </div>
                    </form>
                </x-sheet>
            </template>
        </div>
    @endif

    {{-- Start an advance collection (leader) --}}
    @if ($canManage && $trip->isActive())
        <div x-data="{ open: false }" @sofor-collect.window="open = true">
            <template x-teleport="body">
                <x-sheet title="{{ __('Collect an advance') }}" :subtitle="__('Everyone sends one person a set amount up front')" icon="coins" size="md">
                    <form method="POST" action="{{ route('trips.collections.store', $trip) }}" class="flex flex-col gap-4">
                        @csrf

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="title" :label="__('What is it for?')" required>
                                <x-input name="title" maxlength="80" :value="old('title', __('Advance'))" placeholder="{{ __('e.g. Bus and cottage advance') }}" required />
                            </x-field>

                            <x-field name="amount_per_person" :label="__('Amount a person')" :hint="__('In :currency - a couple pays double', ['currency' => $trip->currency])" required>
                                <x-input name="amount_per_person" type="number" step="1" min="1" placeholder="2000" required
                                         :value="old('amount_per_person', $ledger['budget']['per_person'] ? (int) $ledger['budget']['per_person'] : null)" />
                            </x-field>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="collector_member_id" :label="__('Who holds the money?')">
                                <x-select name="collector_member_id" :value="old('collector_member_id', $currentMember?->id)"
                                          :options="$splitMembers->mapWithKeys(fn ($m) => [$m->id => $m->name])->all()" />
                            </x-field>

                            <x-field name="due_on" :label="__('Pay by')" :hint="__('Optional')">
                                <x-input name="due_on" type="date" min="{{ now()->toDateString() }}" :value="old('due_on', $trip->starts_on?->isFuture() ? $trip->starts_on->copy()->subDay()->toDateString() : null)" />
                            </x-field>
                        </div>

                        <x-field name="note" :label="__('Note')" :hint="__('Optional')">
                            <x-input name="note" maxlength="240" :value="old('note')" placeholder="{{ __('For the bus tickets and the first night') }}" />
                        </x-field>

                        <p class="flex items-start gap-2 rounded-2xl border border-line bg-surface-2 p-3.5 text-[11px] leading-relaxed text-muted">
                            <x-icon name="info" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                            {{ __('Everyone is told how much to send and where. Costs the holder logs are paid from the pot, and whatever is left at the end is paid back in the settle up - nobody has to do the maths.') }}
                        </p>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-btn type="button" variant="ghost" @click="open = false">{{ __('Cancel') }}</x-btn>
                            <x-btn type="submit" icon="check">{{ __('Start collecting') }}</x-btn>
                        </div>
                    </form>
                </x-sheet>
            </template>
        </div>
    @endif

    {{-- Record a contribution --}}
    @if ($canRecord || $currentMember)
        <div x-data="{ open: false, from: '{{ $currentMember?->id }}', to: '', amount: '', collection: '', collectionTitle: '' }"
             @sofor-settle.window="open = true; from = $event.detail.from ?? '{{ $currentMember?->id }}'; to = ($event.detail.to ?? '') === null ? '' : ($event.detail.to ?? ''); amount = $event.detail.amount ?? ''; collection = $event.detail.collection ?? ''; collectionTitle = $event.detail.collectionTitle ?? ''">
            <template x-teleport="body">
                <x-sheet title="{{ __('Record a payment') }}" :subtitle="__('Money paid directly between travellers')" icon="wallet" size="md">
                    <form method="POST" action="{{ route('trips.settlements.store', $trip) }}" class="flex flex-col gap-4">
                        @csrf
                        <input type="hidden" name="collection_id" :value="collection">

                        <template x-if="collection">
                            <p class="flex items-center gap-2 rounded-2xl border border-accent/40 bg-accent/10 px-3.5 py-2.5 text-[12px] text-ink">
                                <x-icon name="coins" class="size-4 shrink-0" />
                                <span>{{ __('Advance for') }} <span class="font-semibold" x-text="collectionTitle"></span> {{ __('- it goes to whoever holds the pot.') }}</span>
                            </p>
                        </template>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="from_member_id" :label="__('Who paid?')" required>
                                <x-select name="from_member_id" x-model="from"
                                          :options="$splitMembers->mapWithKeys(fn ($m) => [$m->id => $m->name])->all()" />
                            </x-field>

                            <x-field name="to_member_id" :label="__('Who got the money?')">
                                <x-select name="to_member_id" x-model="to" placeholder="{{ __('Let Sofor decide for me') }}"
                                          :options="$splitMembers->mapWithKeys(fn ($m) => [$m->id => $m->name])->all()" />
                            </x-field>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="amount" :label="__('Amount')" :hint="__('In :currency - partial amounts are fine', ['currency' => $trip->currency])" required>
                                <x-input name="amount" type="number" step="0.01" min="0.01" x-model="amount" placeholder="1250" required />
                            </x-field>

                            <x-field name="method" :label="__('How was it paid?')">
                                <x-select name="method" :options="$paymentMethods" />
                            </x-field>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="reference" :label="__('Transaction ID')" :hint="__('From the bKash, Nagad or bank receipt - helps them confirm')">
                                <x-input name="reference" placeholder="e.g. 8N7A2K1" />
                            </x-field>

                            <x-field name="settled_on" :label="__('Date')">
                                <x-input name="settled_on" type="date" :value="now()->toDateString()" max="{{ now()->toDateString() }}" required />
                            </x-field>
                        </div>

                        <x-field name="note" :label="__('Note')" :hint="__('Optional')">
                            <x-textarea name="note" rows="2" placeholder="{{ __('Paid at the bus counter') }}" />
                        </x-field>

                        <p class="flex items-start gap-2 rounded-2xl border border-line bg-surface-2 p-3.5 text-[11px] leading-relaxed text-muted">
                            <x-icon name="info" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                            {{ __('Sofor never handles the money - pay with your own bKash, Nagad, bank or cash, then record it here. If you paid somebody else, they are asked to confirm it. Partial payments add up.') }}
                        </p>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-btn type="button" variant="ghost" @click="open = false">{{ __('Cancel') }}</x-btn>
                            <x-btn type="submit" icon="check">{{ __('Record payment') }}</x-btn>
                        </div>
                    </form>
                </x-sheet>
            </template>
        </div>
    @endif

    {{-- Remind somebody who owes (or, for the leader, everybody who owes) --}}
    @if ($canRecord)
        @php
            $smsWallet = app(\App\Services\SmsWallet::class);
            $smsPrice = $smsWallet->price();
            $smsBalance = (float) auth()->user()->sms_balance;
        @endphp
        <div x-data="{
                 open: false, all: false, member: null, name: '', amount: '', link: '', sms: false,
                 base: @js(route('trips.remind', [$trip, '__MEMBER__'])),
                 get action() { return this.all ? @js(route('trips.remind.all', $trip)) : this.base.replace('__MEMBER__', this.member); },
                 get whatsapp() {
                     return 'https://wa.me/?text=' + encodeURIComponent(@js(__('Hi :name, your share of :trip is :amount. Where to pay: :link')).replace(':name', this.name.split(' ')[0]).replace(':trip', @js($trip->title)).replace(':amount', this.amount).replace(':link', this.link));
                 },
             }"
             @sofor-nudge.window="open = true; all = !! $event.detail.all; member = $event.detail.member ?? null; name = $event.detail.name ?? ''; amount = $event.detail.amount ?? ''; link = $event.detail.link ?? ''; sms = false">
            <template x-teleport="body">
                <x-sheet title="{{ __('Send a reminder') }}" :subtitle="__('Sofor sends it, so you never have to be the one who asks')" icon="bell" size="sm">
                    <form method="POST" :action="action" class="flex flex-col gap-4">
                        @csrf

                        <div class="rounded-3xl border border-line bg-surface-2 p-4">
                            <p class="text-[13px] text-muted" x-show="! all">
                                <span class="font-semibold text-ink" x-text="name"></span> {{ __('still owes') }}
                                <span class="num font-semibold text-ink" x-text="amount"></span>.
                            </p>
                            <p class="text-[13px] text-muted" x-show="all" x-cloak>{{ __('Everybody who still owes money on this trip gets a reminder.') }}</p>
                            <p class="mt-2 flex items-start gap-2 text-[11.5px] leading-relaxed text-faint">
                                <x-icon name="check-circle" class="mt-0.5 size-3.5 shrink-0 text-positive" />
                                {{ __('In the app and by email wherever Sofor can reach them - free. It shows who to pay and their accounts.') }}
                            </p>
                        </div>

                        <label @class([
                            'flex items-start gap-3 rounded-2xl border px-3.5 py-3 text-[12.5px]',
                            'cursor-pointer border-line bg-surface' => $smsBalance >= $smsPrice,
                            'border-line bg-surface-2 opacity-70' => $smsBalance < $smsPrice,
                        ])>
                            <input type="checkbox" name="sms" value="1" x-model="sms" @disabled($smsBalance < $smsPrice)
                                   class="mt-0.5 size-4 rounded-md border-line bg-surface text-brand focus:ring-brand/40">
                            <span class="min-w-0">
                                <span class="block font-semibold text-ink">{{ __('Also send a text') }}</span>
                                <span class="block text-[11.5px] text-muted">
                                    {{ __(':price per text from your SMS wallet - balance :balance.', ['price' => \App\Support\Money::format($smsPrice), 'balance' => \App\Support\Money::format($smsBalance)]) }}
                                    @if ($smsBalance < $smsPrice)
                                        <a href="{{ route('wallet.index') }}" class="font-semibold text-brand hover:underline">{{ __('Top up') }}</a>
                                    @endif
                                </span>
                            </span>
                        </label>

                        <div x-show="! all && link" class="rounded-2xl border border-line bg-surface p-3">
                            <p class="text-[12px] font-semibold text-ink">{{ __('Or share their pay link yourself') }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2" x-data="{ copied: false }">
                                <code class="min-w-0 flex-1 truncate rounded-xl bg-surface-2 px-2.5 py-1.5 font-mono text-[11px] text-muted" x-text="link"></code>
                                <button type="button" @click="navigator.clipboard?.writeText(link); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="rounded-xl border border-line px-2.5 py-1.5 text-[11.5px] font-semibold text-muted hover:text-ink">
                                    <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                                </button>
                                <a :href="whatsapp" target="_blank" rel="noopener"
                                   class="rounded-xl bg-[#25d366] px-2.5 py-1.5 text-[11.5px] font-semibold text-white hover:brightness-95">{{ __('WhatsApp') }}</a>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-btn type="button" variant="ghost" @click="open = false">{{ __('Cancel') }}</x-btn>
                            <x-btn type="submit" icon="send">{{ __('Send reminder') }}</x-btn>
                        </div>
                    </form>
                </x-sheet>
            </template>
        </div>
    @endif

    {{-- End trip confirmation --}}
    @if ($canManage && $trip->isActive())
        <div x-data="{ open: false }" @sofor-end-trip.window="open = true">
            <template x-teleport="body">
                <x-sheet title="{{ __('End this trip?') }}" :subtitle="__('This locks the ledger for everyone')" icon="lock" size="sm">
                    <div class="flex flex-col gap-4">
                        <div class="rounded-3xl border border-warning/25 bg-warning/8 p-4">
                            <p class="text-[13px] leading-relaxed text-ink">
                                {{ __('Once ended, no new costs can be added and traveller settings are frozen. The final split stays visible so everyone can settle up.') }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl border border-line bg-surface-2 p-3">
                                <p class="text-[10px] uppercase tracking-wide text-faint">{{ __('Total spent') }}</p>
                                <p class="mt-1 font-display text-base font-semibold">{{ \App\Support\Money::format($stats['spent'], $trip->currency) }}</p>
                            </div>
                            <div class="rounded-2xl border border-line bg-surface-2 p-3">
                                <p class="text-[10px] uppercase tracking-wide text-faint">{{ __('Each traveller') }}</p>
                                <p class="mt-1 font-display text-base font-semibold">{{ \App\Support\Money::format($stats['per_person'], $trip->currency) }}</p>
                            </div>
                        </div>

                        @if (! empty($ledger['suggestions']))
                            <p class="text-[12px] text-muted">
                                {{ trans_choice(':count transfer still pending after the split.|:count transfers still pending after the split.', count($ledger['suggestions'])) }}
                            </p>
                        @endif

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-btn type="button" variant="ghost" @click="open = false">{{ __('Keep it running') }}</x-btn>
                            <form method="POST" action="{{ route('trips.end', $trip) }}">
                                @csrf
                                <x-btn type="submit" variant="warn" icon="lock" block>{{ __('End trip now') }}</x-btn>
                            </form>
                        </div>
                    </div>
                </x-sheet>
            </template>
        </div>
    @endif

</x-layouts.app>
