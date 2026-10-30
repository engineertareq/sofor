{{-- A read only copy of the trip ledger. The traveller partials are reused with
     every action flag off, so nothing here can add, edit or pay anything. --}}
<x-layouts.admin :title="$trip->title" :active="'trips'" heading="Trip ledger" :subtitle="$trip->title.' - read only'"
                 :back="route('admin.trips.index')">

    <div class="flex flex-col gap-4 sm:gap-5">
        <x-card padding="p-4 sm:p-5">
            <div class="flex flex-wrap items-start gap-3.5">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl border-[1.5px] border-line shadow-soft"
                      style="background-image: {{ $trip->coverStyle() }}"><x-glyph :name="$trip->icon" class="size-10" /></span>

                <div class="min-w-0 flex-[1_1_14rem]">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="min-w-0 font-display text-lg font-semibold">{{ $trip->title }}</h2>
                        <x-badge :tone="$trip->isEnded() ? 'slate' : 'lime'">{{ $trip->status->label() }}</x-badge>
                    </div>

                    <p class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-muted">
                        <span class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-3.5 text-brand" /> {{ $trip->destination ?: 'No destination' }}</span>
                        @if ($trip->starts_on)
                            <span class="flex items-center gap-1.5">
                                <x-icon name="calendar" class="size-3.5 text-brand" />
                                {{ $trip->starts_on->format('d M') }}@if ($trip->ends_on) - {{ $trip->ends_on->format('d M Y') }}@endif
                            </span>
                        @endif
                        @if ($trip->owner)
                            <a href="{{ route('admin.users.show', $trip->owner) }}" class="flex items-center gap-1.5 transition hover:text-brand">
                                <x-icon name="flag" class="size-3.5 text-brand" /> Led by {{ $trip->owner->name }}
                            </a>
                        @endif
                        <span class="flex items-center gap-1.5"><x-icon name="clock" class="size-3.5 text-brand" /> Created {{ $trip->created_at->format('d M Y') }}</span>
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1.5">
                    @if ($trip->isActive())
                        <form method="POST" action="{{ route('admin.trips.end', $trip) }}"
                              onsubmit="return confirm(@js('End '.$trip->title.' on behalf of the leader?'))">
                            @csrf
                            <x-admin.action icon="lock" tone="warning">End trip</x-admin.action>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.trips.destroy', $trip) }}"
                          onsubmit="return confirm('Delete this trip and all its costs?')">
                        @csrf
                        @method('DELETE')
                        <x-admin.action icon="trash" tone="danger">Delete</x-admin.action>
                    </form>
                </div>
            </div>

            @if ($trip->description)
                <p class="mt-4 text-[12.5px] leading-relaxed text-muted">{{ $trip->description }}</p>
            @endif

            <p class="mt-4 flex items-start gap-2 rounded-2xl border border-line bg-surface-2 p-3.5 text-[11px] leading-relaxed text-muted">
                <x-icon name="eye" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                You are viewing this trip as an administrator. Costs, splits and contributions are managed by its travellers.
            </p>
        </x-card>

        @include('trips.partials.summary', ['trip' => $trip, 'ledger' => $ledger, 'canRecord' => false, 'bkashEnabled' => false])

        <div class="grid min-w-0 gap-4 sm:gap-5 xl:grid-cols-[1.55fr_1fr] xl:items-start">
            <div class="flex min-w-0 flex-col gap-4 sm:gap-5">
                @include('trips.partials.feed', ['trip' => $trip, 'expenses' => $expenses, 'canAddExpense' => false, 'canManage' => false])
                @include('trips.partials.settle', ['trip' => $trip, 'ledger' => $ledger, 'settlements' => $settlements, 'canRecord' => false, 'bkashEnabled' => false])
            </div>

            <div class="flex min-w-0 flex-col gap-4 sm:gap-5">
                @include('trips.partials.members', ['trip' => $trip, 'ledger' => $ledger, 'canManage' => false, 'currentMember' => null])
                @include('trips.partials.breakdown', ['trip' => $trip, 'ledger' => $ledger])
                @include('trips.partials.activity', ['activities' => $activities])
            </div>
        </div>
    </div>
</x-layouts.admin>
