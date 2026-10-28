@php
    $trend = $signupSeries->sum('value');
@endphp

<x-layouts.admin title="Overview" :active="'dashboard'" heading="Platform overview"
                 subtitle="Everything happening across Sofor right now." wide>

    <x-slot:actions>
        <x-btn :href="route('admin.users.index')" variant="secondary" size="sm" icon="users" class="hidden sm:inline-flex">Manage users</x-btn>
    </x-slot:actions>

    <div class="flex flex-col gap-6">
        {{-- Headline stats --}}
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Travellers" :value="number_format($stats['users'])" icon="users" tone="brand"
                    :hint="'+'.$stats['new_users_7d'].' this week'" trend="up" />
            <x-stat label="Trips" :value="number_format($stats['trips']['total'])" icon="compass" tone="accent"
                    :hint="$stats['trips']['active'].' live - '.$stats['trips']['ended'].' ended'" />
            <x-stat label="Tracked spend" :value="\App\Support\Money::compact($stats['expenses']['sum'])" icon="receipt" tone="lime"
                    :hint="$stats['expenses']['count'].' costs logged'" trend="up" />
            <x-stat label="Contributions" :value="\App\Support\Money::compact($stats['expenses']['settlements'])" icon="wallet" tone="violet"
                    :hint="$stats['expenses']['settlement_count'].' payments recorded'" />
        </section>

        {{-- Supporters: the voluntary income that keeps Sofor running --}}
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Support raised" :value="\App\Support\Money::format($stats['donations']['raised'])" icon="gift" tone="lime"
                    :hint="$stats['donations']['count'].' completed gift'.($stats['donations']['count'] === 1 ? '' : 's')" />
            <x-stat label="MFS fees paid" :value="\App\Support\Money::format($stats['donations']['fees'])" icon="card" tone="amber"
                    hint="Charged on top of gifts" />
            <x-stat label="Total charged" :value="\App\Support\Money::format($stats['donations']['charged'])" icon="wallet" tone="violet"
                    hint="Collected by bKash" />
            <a href="{{ route('admin.donations.index') }}" class="block">
                <x-stat label="Supporters" :value="number_format($stats['donations']['supporters'])" icon="trending-up" tone="brand"
                        :hint="$stats['donations']['pending'] > 0 ? $stats['donations']['pending'].' pending' : 'View the full list'" />
            </a>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr] xl:items-start">
            <div class="flex flex-col gap-6">
                {{-- Signups chart --}}
                <x-card>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-sm font-semibold">New travellers - last 14 days</h2>
                            <p class="text-[11px] text-faint">{{ $trend }} sign ups in this window</p>
                        </div>
                        <x-badge tone="lime" icon="trending-up">
                            {{ $stats['new_users_30d'] }} in 30 days
                        </x-badge>
                    </div>

                    <x-chart-bars :series="$signupSeries" class="mt-5" height="h-40" tone="brand" />
                </x-card>

                {{-- Top trips --}}
                <x-card padding="p-0">
                    <div class="flex items-center justify-between px-5 py-4">
                        <div>
                            <h2 class="font-display text-sm font-semibold">Biggest ledgers</h2>
                            <p class="text-[11px] text-faint">Trips ranked by total spend</p>
                        </div>
                        <a href="{{ route('admin.trips.index') }}" class="flex items-center gap-1 text-xs font-medium text-brand hover:underline">
                            All trips <x-icon name="chevron-right" class="size-3.5" />
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-[13px]">
                            <thead class="border-y border-line text-[10px] uppercase tracking-wide text-faint">
                                <tr>
                                    <th class="px-5 py-2.5 font-semibold">Trip</th>
                                    <th class="px-3 py-2.5 font-semibold">Leader</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">Travellers</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">Costs</th>
                                    <th class="px-5 py-2.5 text-right font-semibold">Spend</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @forelse ($topTrips as $trip)
                                    <tr class="transition hover:bg-surface-2">
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-2.5">
                                                <span class="flex size-8 items-center justify-center rounded-xl" style="background-image: {{ $trip->coverStyle() }}"><x-glyph :name="$trip->icon" class="size-6" /></span>
                                                <div class="min-w-0">
                                                    <p class="truncate font-medium text-ink">{{ $trip->title }}</p>
                                                    <p class="truncate text-[10px] text-faint">{{ $trip->destination ?: 'No destination' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-muted">{{ $trip->owner?->name }}</td>
                                        <td class="px-3 py-3 text-right text-muted">{{ $trip->members_count }}</td>
                                        <td class="px-3 py-3 text-right text-muted">{{ $trip->expenses_count }}</td>
                                        <td class="px-5 py-3 text-right font-semibold text-ink">{{ \App\Support\Money::format($trip->expenses_sum_amount, $trip->currency) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-8 text-center text-muted">No costs logged yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>

                {{-- Recent activity --}}
                <x-card>
                    <h2 class="font-display text-sm font-semibold">Live activity feed</h2>
                    @if ($recentActivity->isEmpty())
                        <p class="mt-3 text-[13px] text-muted">Nothing logged yet.</p>
                    @else
                        <ol class="mt-4 flex flex-col gap-3.5">
                            @foreach ($recentActivity as $activity)
                                <li class="flex gap-3">
                                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-faint hairline">
                                        <x-icon :name="match ($activity->type) {
                                            'expense_added' => 'receipt',
                                            'settlement' => 'wallet',
                                            'trip_ended' => 'lock',
                                            'member_invited', 'member_joined' => 'users',
                                            default => 'sparkles',
                                        }" class="size-3.5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[12px] leading-snug text-ink">{{ $activity->description }}</p>
                                        <p class="mt-0.5 text-[10px] text-faint">
                                            <a href="{{ route('admin.trips.show', $activity->trip) }}" class="hover:text-brand">{{ $activity->trip?->title }}</a>
                                            - {{ $activity->created_at?->diffForHumans() }}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>
            </div>

            <div class="flex flex-col gap-6">
                {{-- Brands waiting for a reply about sponsoring --}}
                <x-card padding="p-0">
                    <div class="flex items-center justify-between gap-3 px-5 py-4">
                        <div class="flex min-w-0 items-center gap-2">
                            <h2 class="font-display text-sm font-semibold">Sponsor requests</h2>
                            @if ($sponsorRequests['count'] > 0)
                                <span class="num rounded-full bg-negative px-1.5 text-[10.5px] font-bold text-white">{{ $sponsorRequests['count'] }} new</span>
                            @endif
                        </div>
                        <a href="{{ route('admin.sponsor-inquiries.index') }}" class="shrink-0 text-xs font-medium text-brand hover:underline">All requests</a>
                    </div>

                    @if ($sponsorRequests['latest']->isEmpty())
                        <p class="flex items-center gap-2 border-t border-line px-5 py-4 text-[12.5px] text-muted">
                            <x-icon name="check-circle" class="size-4 text-positive" /> No new requests. You are all caught up.
                        </p>
                    @else
                        <ul class="divide-y divide-line border-t border-line">
                            @foreach ($sponsorRequests['latest'] as $inquiry)
                                <li>
                                    <a href="{{ route('admin.sponsor-inquiries.show', $inquiry) }}" class="flex items-start gap-3 px-5 py-3 transition hover:bg-surface-2">
                                        <x-avatar :name="$inquiry->company" size="sm" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[13px] font-semibold text-ink">{{ $inquiry->company }}</p>
                                            <p class="truncate text-[11.5px] text-muted">{{ $inquiry->message }}</p>
                                        </div>
                                        <span class="shrink-0 text-[10.5px] text-faint">{{ $inquiry->created_at->diffForHumans(short: true) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                <x-card>
                    <h2 class="font-display text-sm font-semibold">Spend by category</h2>
                    <p class="text-[11px] text-faint">Across every trip on the platform</p>

                    @if ($categoryRows->isEmpty())
                        <p class="mt-4 text-[13px] text-muted">No costs yet.</p>
                    @else
                        <x-chart-donut class="mt-5" :rows="$categoryRows->map(function ($row) {
                            $value = $row->category;
                            $category = $value instanceof \App\Enums\ExpenseCategory
                                ? $value
                                : \App\Enums\ExpenseCategory::tryFrom((string) $value);

                            return [
                                'label' => $category?->label() ?? 'Other',
                                'value' => (int) round((float) $row->total),
                            ];
                        })->all()" />
                    @endif
                </x-card>

                <x-card>
                    <h2 class="font-display text-sm font-semibold">Trips created per month</h2>
                    <x-chart-bars :series="$monthlySeries" class="mt-4" height="h-28" tone="violet" />
                </x-card>

                <x-card>
                    <h2 class="font-display text-sm font-semibold">Platform health</h2>
                    <ul class="mt-4 flex flex-col divide-y divide-line text-[13px]">
                        @foreach ([
                            ['Average trip size', $stats['avg_trip_size'].' travellers'],
                            ['Average share', \App\Support\Money::format($stats['avg_per_person'])],
                            ['Average cost entry', \App\Support\Money::format($stats['expenses']['avg'])],
                            ['Paused accounts', $stats['paused_users']],
                            ['Administrators', $stats['admins']],
                            ['Timeline entries', number_format($stats['activities'])],
                        ] as [$label, $value])
                            <li class="flex items-center justify-between py-2.5">
                                <span class="text-muted">{{ $label }}</span>
                                <span class="font-semibold text-ink">{{ $value }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>

                <x-card padding="p-0">
                    <div class="flex items-center justify-between px-5 py-4">
                        <h2 class="font-display text-sm font-semibold">Newest travellers</h2>
                        <a href="{{ route('admin.users.index') }}" class="text-xs font-medium text-brand hover:underline">All users</a>
                    </div>

                    <ul class="divide-y divide-line">
                        @foreach ($recentUsers as $user)
                            <li class="flex items-center gap-3 px-5 py-3">
                                <x-avatar :name="$user->name" :gradient="$user->avatarGradient()" :src="$user->avatarUrl()" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-medium text-ink">{{ $user->name }}</p>
                                    <p class="truncate text-[10px] text-faint">{{ $user->handle() }}</p>
                                </div>
                                <x-badge :tone="$user->is_active ? 'lime' : 'rose'">{{ $user->is_active ? 'Active' : 'Paused' }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            </div>
        </div>
    </div>
</x-layouts.admin>
