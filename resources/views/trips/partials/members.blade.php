@php
    $members = $ledger['members'];
    $balances = $ledger['balances'];
@endphp

<x-card padding="p-4 sm:p-5">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-brand/12 text-brand">
                <x-icon name="users" class="size-4.5" />
            </span>
            <div class="min-w-0">
                <h2 class="truncate font-display text-sm font-semibold">{{ __('Travellers & dues') }}</h2>
                <p class="truncate text-[11px] text-faint">{{ __(':count in the trip', ['count' => $members->count()]) }}</p>
            </div>
        </div>

        @if ($canManage && $trip->isActive())
            <span class="shrink-0 text-[11px] text-faint">{{ __('You can edit splits') }}</span>
        @endif
    </div>

    <ul class="mt-4 flex flex-col gap-2.5">
        @foreach ($members as $member)
            @php $row = $balances[$member->id] ?? null; @endphp

            <li class="rounded-3xl border border-line bg-surface-2 p-3.5 {{ $member->status === \App\Enums\MemberStatus::Removed ? 'opacity-60' : '' }}">
                <div class="flex items-center gap-3">
                    <x-avatar :name="$member->name" :gradient="$member->avatarGradient()" :src="$member->avatarUrl()" size="sm" />

                    <div class="min-w-0 flex-1">
                        <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="min-w-0 max-w-full truncate text-[13px] font-semibold text-ink">{{ $member->name }}</p>
                            @if ($member->isOwner())
                                <span class="shrink-0 rounded-full bg-brand/12 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-brand">{{ __('Leader') }}</span>
                            @endif
                            @if ($member->user_id === auth()->id())
                                <span class="shrink-0 rounded-full bg-accent/12 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-accent">{{ __('You') }}</span>
                            @endif
                        </div>
                        <p class="truncate text-[11px] text-faint">
                            {{ $member->isRegistered() ? __('Sofor account') : __('Invite pending') }} -
                            {{ $member->contactLabel() }}
                            @if ($member->share_units > 1)
                                - {{ __(':count shares', ['count' => $member->share_units]) }}
                            @endif
                            @if (! $member->include_in_split)
                                - {{ __('not in the split') }}
                            @endif
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        @if ($row)
                            @if ($row['due'] > 0.009)
                                <p class="font-display text-sm font-semibold text-negative">{{ \App\Support\Money::format($row['due'], $trip->currency) }}</p>
                                <p class="text-[9.5px] uppercase leading-tight text-faint sm:text-[10px] xl:tracking-wide">{{ __('Due') }}</p>
                            @elseif ($row['credit'] > 0.009)
                                <p class="font-display text-sm font-semibold text-positive">{{ \App\Support\Money::format($row['credit'], $trip->currency) }}</p>
                                <p class="text-[9.5px] uppercase leading-tight text-faint sm:text-[10px] xl:tracking-wide">{{ __('To receive') }}</p>
                            @else
                                <p class="font-display text-sm font-semibold text-muted">{{ __('Settled') }}</p>
                                <p class="text-[9.5px] uppercase leading-tight text-faint sm:text-[10px] xl:tracking-wide">{{ __('0 due') }}</p>
                            @endif
                        @endif
                    </div>
                </div>

                @if ($row)
                    <div class="mt-3 grid min-w-0 grid-cols-3 gap-2 border-t border-line pt-3 text-center">
                        <div>
                            <p class="text-[9.5px] uppercase leading-tight text-faint sm:text-[10px] xl:tracking-wide">{{ __('Paid for group') }}</p>
                            <p class="text-[12px] font-semibold text-ink">{{ \App\Support\Money::format($row['paid'], $trip->currency) }}</p>
                        </div>
                        <div>
                            <p class="text-[9.5px] uppercase leading-tight text-faint sm:text-[10px] xl:tracking-wide">{{ __('Share') }}</p>
                            <p class="text-[12px] font-semibold text-ink">{{ \App\Support\Money::format($row['share'], $trip->currency) }}</p>
                        </div>
                        <div>
                            <p class="text-[9.5px] uppercase leading-tight text-faint sm:text-[10px] xl:tracking-wide">{{ __('Paid in') }}</p>
                            <p class="text-[12px] font-semibold text-ink">{{ \App\Support\Money::format($row['contributed'], $trip->currency) }}</p>
                            @if ($row['contribution_count'] > 1)
                                <p class="text-[10px] text-faint">{{ __(':contribution_count payments', ['contribution_count' => $row['contribution_count']]) }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($canManage && $trip->isActive() && ! $member->isOwner())
                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
                        <form method="POST" action="{{ route('trips.members.update', [$trip, $member]) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="include_in_split" value="{{ $member->include_in_split ? 0 : 1 }}">
                            <button type="submit"
                                    class="flex items-center gap-2 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-[11px] font-medium text-muted transition hover:text-ink">
                                <span class="switch" data-on="{{ $member->include_in_split ? 'true' : 'false' }}"></span>
                                {{ $member->include_in_split ? __('In the split') : __('Excluded') }}
                            </button>
                        </form>

                        <button type="button"
                                @click="$dispatch('sofor-edit-member', { action: '{{ route('trips.members.update', [$trip, $member]) }}', name: @js($member->name), units: {{ $member->share_units }}, included: {{ $member->include_in_split ? 1 : 0 }} })"
                                class="flex items-center gap-1.5 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-[11px] font-medium text-muted transition hover:text-ink">
                            <x-icon name="pencil" class="size-3.5" /> {{ __('Share settings') }}
                        </button>

                        @if (! $member->isRegistered())
                            <form method="POST" action="{{ route('trips.members.remind', [$trip, $member]) }}">
                                @csrf
                                <button type="submit" class="flex items-center gap-1.5 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-[11px] font-medium text-muted transition hover:text-ink">
                                    <x-icon name="bell" class="size-3.5" /> {{ __('Remind') }}
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('trips.members.destroy', [$trip, $member]) }}"
                              onsubmit="return confirm(@js('Remove '.$member->name.' from the trip?'))" class="ml-auto">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="flex items-center gap-1.5 rounded-xl border border-transparent px-2.5 py-1.5 text-[11px] font-medium text-faint transition hover:bg-negative-tint hover:text-negative">
                                <x-icon name="trash" class="size-3.5" /> {{ __('Remove') }}
                            </button>
                        </form>
                    </div>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($trip->isEnded())
        <p class="mt-4 flex items-start gap-2 rounded-2xl border border-line bg-surface-2 p-3 text-[11px] leading-relaxed text-muted">
            <x-icon name="lock" class="mt-0.5 size-3.5 shrink-0 text-warning" />
            {{ __('This trip has ended, so travellers and split settings are locked.') }}
        </p>
    @endif
</x-card>
