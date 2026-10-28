<x-layouts.admin title="Trips" :active="'trips'" heading="Trips" subtitle="Every ledger on Sofor, live or ended." wide>

    <div class="flex flex-col gap-5">
        <x-card padding="p-4">
            <form method="GET" action="{{ route('admin.trips.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="flex items-center gap-1 rounded-2xl border border-line bg-surface-2 p-1">
                    @foreach (['all' => 'All', 'active' => 'Live', 'ended' => 'Ended'] as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}"
                           @class([
                               'rounded-xl px-3.5 py-2 text-[13px] font-semibold transition',
                               'bg-surface text-ink shadow-soft' => $filters['status'] === $key,
                               'text-muted hover:text-ink' => $filters['status'] !== $key,
                           ])>{{ $label }}</a>
                    @endforeach
                </div>

                <div class="flex items-center gap-3 text-[12px] text-muted">
                    <span>{{ $counts['active'] }} live</span>
                    <span class="text-faint">/</span>
                    <span>{{ $counts['ended'] }} ended</span>
                </div>

                <div class="lg:ml-auto lg:w-72">
                    <x-input name="q" icon="search" placeholder="Trip or destination" :value="$filters['q']" />
                </div>
            </form>
        </x-card>

        <x-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-[13px]">
                    <thead class="border-b border-line text-[10px] uppercase tracking-wide text-faint">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Trip</th>
                            <th class="px-3 py-3 font-semibold">Leader</th>
                            <th class="px-3 py-3 text-center font-semibold">Travellers</th>
                            <th class="px-3 py-3 text-center font-semibold">Costs</th>
                            <th class="px-3 py-3 text-center font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Spend</th>
                            <th class="px-5 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($trips as $trip)
                            <tr class="transition hover:bg-surface-2">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.trips.show', $trip) }}" class="flex items-center gap-3">
                                        <span class="flex size-9 items-center justify-center rounded-2xl" style="background-image: {{ $trip->coverStyle() }}"><x-glyph :name="$trip->icon" class="size-7" /></span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-ink">{{ $trip->title }}</span>
                                            <span class="block truncate text-[10px] text-faint">
                                                {{ $trip->destination ?: 'No destination' }} - created {{ $trip->created_at->format('d M Y') }}
                                            </span>
                                        </span>
                                    </a>
                                </td>
                                <td class="px-3 py-3 text-muted">{{ $trip->owner?->name }}</td>
                                <td class="px-3 py-3 text-center text-muted">{{ $trip->members_count }}</td>
                                <td class="px-3 py-3 text-center text-muted">{{ $trip->expenses_count }}</td>
                                <td class="px-3 py-3 text-center">
                                    <x-badge :tone="$trip->isEnded() ? 'slate' : 'lime'">{{ $trip->status->label() }}</x-badge>
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-ink">{{ \App\Support\Money::format($trip->expenses_sum_amount ?? 0, $trip->currency) }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($trip->isActive())
                                            <form method="POST" action="{{ route('admin.trips.end', $trip) }}"
                                                  onsubmit="return confirm(@js('End '.$trip->title.' on behalf of the leader?'))">
                                                @csrf
                                                <button type="submit" class="rounded-xl border border-line bg-surface px-2.5 py-1.5 text-[11px] font-medium text-muted transition hover:text-warning">
                                                    End trip
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.trips.destroy', $trip) }}"
                                              onsubmit="return confirm('Delete this trip and all its costs?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="flex size-8 items-center justify-center rounded-xl text-faint transition hover:bg-negative-tint hover:text-negative" aria-label="Delete trip">
                                                <x-icon name="trash" class="size-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-muted">No trips match this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $trips->links() }}
    </div>
</x-layouts.admin>
