<x-layouts.admin :title="$user->name" :active="'users'" :heading="$user->name"
                 :subtitle="'@'.$user->username.' - '.$user->handle()" :back="route('admin.users.index')" wide>

    <div class="grid gap-4 sm:gap-5 xl:grid-cols-[1fr_1.6fr] xl:items-start">
        <div class="flex flex-col gap-4 sm:gap-5">
            <x-card padding="p-4 sm:p-5">
                <div class="flex items-center gap-3.5">
                    <x-avatar :name="$user->name" :gradient="$user->avatarGradient()" :src="$user->avatarUrl()" size="lg" />
                    <div class="min-w-0">
                        <h2 class="font-display text-lg font-semibold">{{ $user->name }}</h2>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <x-badge :tone="$user->isAdmin() ? 'violet' : 'slate'">{{ $user->isAdmin() ? 'Administrator' : 'Traveller' }}</x-badge>
                            <x-badge :tone="$user->is_active ? 'lime' : 'rose'">{{ $user->is_active ? 'Active' : 'Paused' }}</x-badge>
                        </div>
                    </div>
                </div>

                <ul class="mt-5 flex flex-col divide-y divide-line text-[13px]">
                    @foreach ([
                        ['Email', $user->email, $user->hasVerifiedEmail() ? $user->email_verified_at : null],
                        ['Phone', $user->phone ? \App\Support\Phone::pretty($user->phone) : null, $user->hasVerifiedPhone() ? $user->phone_verified_at : null],
                    ] as [$label, $value, $verifiedAt])
                        <li class="flex items-center justify-between gap-4 py-2.5">
                            <span class="shrink-0 text-muted">{{ $label }}</span>
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="truncate font-medium text-ink" title="{{ $value }}">{{ $value ?? '-' }}</span>
                                @if ($value)
                                    <x-verified :at="$verifiedAt" />
                                @endif
                            </span>
                        </li>
                    @endforeach
                    @foreach ([
                        ['Joined', $user->created_at->format('d M Y')],
                        ['Last seen', $user->last_seen_at?->diffForHumans() ?? 'Never'],
                        ['Trips led', $stats['trips_led']],
                        ['Trips joined', $stats['trips_joined']],
                        ['Logged as payer', \App\Support\Money::format($stats['spend'])],
                        ['Notifications', $stats['notifications']],
                    ] as [$label, $value])
                        <li class="flex items-center justify-between gap-4 py-2.5">
                            <span class="text-muted">{{ $label }}</span>
                            <span class="truncate font-medium text-ink">{{ $value }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4 flex flex-wrap gap-1.5">
                    <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                        @csrf
                        <x-admin.action icon="ban" :tone="$user->is_active ? 'warning' : 'default'">{{ $user->is_active ? 'Pause account' : 'Reactivate' }}</x-admin.action>
                    </form>

                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="role" value="{{ $user->isAdmin() ? 'user' : 'admin' }}">
                        <input type="hidden" name="is_active" value="{{ $user->is_active ? 1 : 0 }}">
                        <x-admin.action icon="shield">{{ $user->isAdmin() ? 'Remove admin' : 'Make admin' }}</x-admin.action>
                    </form>

                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="ml-auto"
                          onsubmit="return confirm('Delete this account and all related data?')">
                        @csrf
                        @method('DELETE')
                        <x-admin.action icon="trash" tone="danger">Delete</x-admin.action>
                    </form>
                </div>
            </x-card>
        </div>

        <x-card padding="p-0">
            <div class="px-4 py-3.5 sm:px-5">
                <h2 class="font-display text-sm font-semibold">Trips in this account</h2>
                <p class="text-[11px] text-faint">{{ $trips->count() }} ledgers, newest first</p>
            </div>

            <div class="overflow-x-auto border-t border-line md:border-t-0">
                <table class="max-md:table-cards w-full text-left text-[13px]">
                    <thead class="border-y border-line text-[10px] uppercase tracking-wide text-faint">
                        <tr>
                            <th class="px-5 py-2.5 font-semibold">Trip</th>
                            <th class="px-3 py-2.5 font-semibold">Role</th>
                            <th class="px-3 py-2.5 text-center font-semibold">Travellers</th>
                            <th class="px-3 py-2.5 text-center font-semibold">Status</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Spend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($trips as $trip)
                            <tr class="transition hover:bg-surface-2">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.trips.show', $trip) }}" class="flex items-center gap-2.5">
                                        <span class="flex size-8 items-center justify-center rounded-xl" style="background-image: {{ $trip->coverStyle() }}"><x-glyph :name="$trip->icon" class="size-6" /></span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-ink">{{ $trip->title }}</span>
                                            <span class="block truncate text-[10px] text-faint">{{ $trip->destination ?: 'No destination' }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="px-3 py-3 text-muted" data-label="Role">{{ $trip->user_id === $user->id ? 'Leader' : 'Traveller' }}</td>
                                <td class="px-3 py-3 text-center text-muted" data-label="Travellers">{{ $trip->members_count }}</td>
                                <td class="px-3 py-3 text-center" data-label="Status">
                                    <x-badge :tone="$trip->isEnded() ? 'slate' : 'lime'" size="sm">{{ $trip->status->label() }}</x-badge>
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-ink" data-label="Spend">{{ \App\Support\Money::format($trip->expenses_sum_amount ?? 0, $trip->currency) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-muted">This traveller has no trips yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-layouts.admin>
