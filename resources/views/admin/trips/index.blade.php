<x-layouts.admin title="Trips" :active="'trips'" heading="Trips" subtitle="Every ledger on Sofor, live or ended.">

    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <x-admin.tabs :tabs="['all' => 'All', 'active' => 'Live', 'ended' => 'Ended']" :current="$filters['status']"
                          :counts="['active' => $counts['active'], 'ended' => $counts['ended']]" label="Trip status" class="self-start" />

            <x-admin.search :value="$filters['q']" placeholder="Trip or destination" :keep="['status' => $filters['status']]" class="sm:ml-auto sm:w-72" />
        </div>

        <x-card padding="p-0" class="overflow-x-auto">
            <table class="max-md:table-cards w-full text-left text-[13px]">
                <thead class="border-b border-line text-[10px] uppercase tracking-wide text-faint">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Trip</th>
                        <th class="px-3 py-2.5 font-semibold">Leader</th>
                        <th class="px-3 py-2.5 text-center font-semibold">Travellers</th>
                        <th class="px-3 py-2.5 text-center font-semibold">Costs</th>
                        <th class="px-3 py-2.5 text-center font-semibold">Status</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Spend</th>
                        <th class="px-4 py-2.5 text-right font-semibold"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($trips as $trip)
                        <tr class="transition hover:bg-surface-2">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('admin.trips.show', $trip) }}" class="group flex items-center gap-2.5">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl" style="background-image: {{ $trip->coverStyle() }}"><x-glyph :name="$trip->icon" class="size-7" /></span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-ink group-hover:text-brand">{{ $trip->title }}</span>
                                        <span class="block truncate text-[10.5px] text-faint">
                                            {{ $trip->destination ?: 'No destination' }} - created {{ $trip->created_at->format('d M Y') }}
                                        </span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-3 py-2.5 text-muted" data-label="Leader">
                                <span class="block truncate">{{ $trip->owner?->name }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-center text-muted" data-label="Travellers">{{ $trip->members_count }}</td>
                            <td class="px-3 py-2.5 text-center text-muted" data-label="Costs">{{ $trip->expenses_count }}</td>
                            <td class="px-3 py-2.5 text-center" data-label="Status">
                                <x-badge :tone="$trip->isEnded() ? 'slate' : 'lime'" size="sm">{{ $trip->status->label() }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold text-ink" data-label="Spend">{{ \App\Support\Money::format($trip->expenses_sum_amount ?? 0, $trip->currency) }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1.5 md:justify-end">
                                    @if ($trip->isActive())
                                        <form method="POST" action="{{ route('admin.trips.end', $trip) }}"
                                              onsubmit="return confirm(@js('End '.$trip->title.' on behalf of the leader?'))">
                                            @csrf
                                            <x-admin.action icon="lock" tone="warning">End trip</x-admin.action>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.trips.destroy', $trip) }}" class="ml-auto md:ml-0"
                                          onsubmit="return confirm('Delete this trip and all its costs?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-admin.action icon="trash" tone="danger" label="Delete trip" />
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
        </x-card>

        {{ $trips->links() }}
    </div>
</x-layouts.admin>
