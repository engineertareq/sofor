@php
    use App\Models\Sponsor;
@endphp

<x-layouts.admin title="Sponsors" :active="'sponsors'" heading="Sponsors"
                 subtitle="The brands in the home page carousel and on the sponsors page.">

    <x-slot:actions>
        <x-btn :href="route('sponsors')" target="_blank" rel="noopener" variant="secondary" size="sm" icon="arrow-up-right" class="hidden sm:inline-flex">
            View page
        </x-btn>
        {{-- The form sits under the list on smaller screens; this jumps to it. --}}
        <x-btn href="#add-sponsor" size="sm" icon="plus" class="inline-flex xl:hidden">Add</x-btn>
    </x-slot:actions>

    <div class="flex flex-col gap-4 sm:gap-5">
        <section class="grid grid-cols-3 gap-2.5 sm:gap-3">
            <x-stat label="Live" :value="$counts['live']" icon="eye" tone="lime" hint="On the site now" />
            <x-stat label="Hidden" :value="$counts['hidden']" icon="eye-off" tone="slate" hint="Kept for later" />
            <a href="{{ route('admin.sponsor-inquiries.index') }}" class="block">
                <x-stat label="New requests" :value="$counts['requests']" icon="mail" :tone="$counts['requests'] > 0 ? 'rose' : 'brand'"
                        :hint="$counts['requests'] > 0 ? 'Waiting for a reply' : 'All caught up'" />
            </a>
        </section>

        <div class="grid gap-4 sm:gap-5 xl:grid-cols-[minmax(0,1fr)_23rem] xl:items-start">
            {{-- ===================== The sponsors, by tier ===================== --}}
            <div class="flex flex-col gap-4 sm:gap-5">
                @foreach ($byTier as $tier => $group)
                    <x-card padding="p-0">
                        <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                            <div class="flex items-center gap-2.5">
                                <x-sponsor-tier :tier="$tier" />
                                <span class="text-[12px] text-faint">{{ $group->count() }} {{ Str::plural('sponsor', $group->count()) }}</span>
                            </div>
                            @if ($group->count() > 1)
                                <span class="hidden text-[11px] text-faint sm:inline">Use the arrows to set the order</span>
                            @endif
                        </div>

                        @if ($group->isEmpty())
                            <p class="px-5 py-8 text-center text-[12.5px] text-faint">No {{ Sponsor::TIERS[$tier] }} sponsors yet.</p>
                        @else
                            <ul class="divide-y divide-line">
                                @foreach ($group as $sponsor)
                                    <li @class(['flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:gap-4 sm:px-5', 'opacity-60' => ! $sponsor->is_active])>
                                        <div class="flex min-w-0 flex-1 items-center gap-4">
                                            <span class="flex h-16 w-28 shrink-0 items-center justify-center rounded-2xl border border-line bg-white p-2.5 sm:w-32">
                                                <img src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }} logo" loading="lazy"
                                                     class="max-h-full max-w-full object-contain">
                                            </span>

                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="truncate text-[14px] font-semibold text-ink">{{ $sponsor->name }}</p>
                                                    <x-badge :tone="$sponsor->is_active ? 'lime' : 'slate'" size="sm">{{ $sponsor->is_active ? 'Live' : 'Hidden' }}</x-badge>
                                                </div>
                                                @if ($sponsor->website)
                                                    <a href="{{ $sponsor->website }}" target="_blank" rel="noopener nofollow"
                                                       class="mt-0.5 inline-flex max-w-full items-center gap-1 truncate text-[12px] font-medium text-brand hover:underline">
                                                        {{ $sponsor->websiteHost() }} <x-icon name="arrow-up-right" class="size-3 shrink-0" />
                                                    </a>
                                                @else
                                                    <p class="mt-0.5 text-[12px] text-faint">No website link</p>
                                                @endif
                                                @if ($sponsor->tagline)
                                                    <p class="mt-1 line-clamp-1 text-[11.5px] text-muted">{{ $sponsor->tagline }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1 self-end sm:self-auto">
                                            @foreach (['up' => 'chevron-up', 'down' => 'chevron-down'] as $direction => $icon)
                                                @php $edge = $direction === 'up' ? $loop->parent->first : $loop->parent->last; @endphp
                                                <form method="POST" action="{{ route('admin.sponsors.move', $sponsor) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                                    <button type="submit" @disabled($edge)
                                                            class="flex size-9 items-center justify-center rounded-xl text-faint transition hover:bg-surface-2 hover:text-ink disabled:pointer-events-none disabled:opacity-30"
                                                            aria-label="Move {{ $sponsor->name }} {{ $direction }}" title="Move {{ $direction }}">
                                                        <x-icon :name="$icon" class="size-4" />
                                                    </button>
                                                </form>
                                            @endforeach

                                            <span class="mx-1 h-5 w-px bg-line" aria-hidden="true"></span>

                                            <form method="POST" action="{{ route('admin.sponsors.toggle', $sponsor) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="flex size-9 items-center justify-center rounded-xl text-faint transition hover:bg-surface-2 hover:text-ink"
                                                        aria-label="{{ $sponsor->is_active ? 'Hide' : 'Show' }} {{ $sponsor->name }}"
                                                        title="{{ $sponsor->is_active ? 'Hide from the site' : 'Show on the site' }}">
                                                    <x-icon :name="$sponsor->is_active ? 'eye-off' : 'eye'" class="size-4" />
                                                </button>
                                            </form>

                                            <a href="{{ route('admin.sponsors.edit', $sponsor) }}"
                                               class="flex size-9 items-center justify-center rounded-xl text-faint transition hover:bg-surface-2 hover:text-ink"
                                               aria-label="Edit {{ $sponsor->name }}" title="Edit">
                                                <x-icon name="pencil" class="size-4" />
                                            </a>

                                            <form method="POST" action="{{ route('admin.sponsors.destroy', $sponsor) }}"
                                                  onsubmit="return confirm(@js('Remove '.$sponsor->name.' from the sponsors? The logo is deleted too.'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="flex size-9 items-center justify-center rounded-xl text-faint transition hover:bg-negative-tint hover:text-negative"
                                                        aria-label="Remove {{ $sponsor->name }}" title="Remove">
                                                    <x-icon name="trash" class="size-4" />
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>
                @endforeach
            </div>

            {{-- ===================== Add a sponsor ===================== --}}
            <x-card id="add-sponsor" class="scroll-mt-28 xl:sticky xl:top-24">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-tint text-brand">
                        <x-icon name="plus" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-display text-base font-semibold">Add a sponsor</h2>
                        <p class="text-xs text-muted">Live on the site as soon as you save.</p>
                    </div>
                </div>

                {{-- Explicitly null: $sponsor is still set from the list above. --}}
                @include('admin.sponsors._form', ['sponsor' => null])
            </x-card>
        </div>
    </div>
</x-layouts.admin>
