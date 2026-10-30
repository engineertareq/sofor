<x-layouts.admin title="Support" :active="'donations'" heading="Supporters"
                 subtitle="Voluntary gifts collected through bKash Checkout." wide>

    <div class="flex flex-col gap-4">
        @if (! $bkashEnabled)
            <div class="flex items-start gap-2.5 rounded-2xl border border-warning/40 bg-warning-tint p-4 text-[13px] text-warning">
                <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
                <p>bKash is not configured, so the donate button is hidden and no gift can be collected right now.</p>
            </div>
        @endif

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Raised" :value="\App\Support\Money::format($totals['raised'])" :hint="$totals['supporters'].' completed gift'.($totals['supporters'] === 1 ? '' : 's')" icon="gift" tone="lime" />
            <x-stat label="Average gift" :value="\App\Support\Money::format(round($totals['average'], 2))" hint="Across completed gifts" icon="trending-up" tone="brand" />
            <x-stat label="bKash fee absorbed" :value="\App\Support\Money::format($totals['fees'])" hint="Paid by Sofor, not the donor" icon="card" tone="amber" />
            <x-stat label="Net received" :value="\App\Support\Money::format($totals['net'])" hint="After bKash's fee" icon="wallet" tone="violet" />
        </section>

        <x-admin.tabs :tabs="['completed' => 'Completed', 'pending' => 'Pending', 'cancelled' => 'Cancelled', 'failed' => 'Failed', 'all' => 'All']"
                      :current="$status" :counts="$counts + ['all' => array_sum($counts)]" label="Gift status" class="self-start" />

        <x-card padding="p-0" class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="max-md:table-cards w-full text-left text-[13px]">
                    <thead class="border-b border-line text-[10px] uppercase tracking-wide text-faint">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Supporter</th>
                            <th class="px-3 py-2.5 font-semibold">Contact</th>
                            <th class="px-3 py-2.5 text-center font-semibold">Status</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Gift</th>
                            <th class="px-3 py-2.5 text-right font-semibold">bKash fee</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Donor paid</th>
                            <th class="px-4 py-2.5 text-right font-semibold">bKash reference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($donations as $donation)
                            <tr class="transition hover:bg-surface-2">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$donation->donorLabel()" size="xs" class="size-8 rounded-xl text-[10px]" />
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-ink">{{ $donation->donorLabel() }}</p>
                                            <p class="truncate text-[10px] text-faint">{{ $donation->invoice }} - {{ $donation->created_at->format('d M Y, H:i') }}</p>
                                        </div>
                                    </div>
                                    @if ($donation->message)
                                        <p class="mt-1.5 max-w-xs truncate text-[11px] italic text-muted">"{{ $donation->message }}"</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-muted" data-label="Contact"><span class="block truncate">{{ $donation->contact ?: '-' }}</span></td>
                                <td class="px-3 py-2.5 text-center" data-label="Status">
                                    @php
                                        $tone = match ($donation->status) {
                                            \App\Models\Donation::STATUS_COMPLETED => 'lime',
                                            \App\Models\Donation::STATUS_PENDING => 'amber',
                                            default => 'slate',
                                        };
                                    @endphp
                                    <x-badge :tone="$tone" size="sm">{{ ucfirst($donation->status) }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold text-ink" data-label="Gift">{{ $donation->amountLabel() }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right text-muted" data-label="bKash fee">{{ \App\Support\Money::format($donation->charge) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold text-brand" data-label="Donor paid">{{ $donation->totalLabel() }}</td>
                                <td class="cell-wide px-4 py-2.5 text-right font-mono text-[11px] text-faint" data-label="bKash reference"><span class="block truncate">{{ $donation->trx_id ?: $donation->payment_id ?: '-' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-14 text-center">
                                    <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-brand/12 text-brand">
                                        <x-icon name="gift" class="size-5" />
                                    </span>
                                    <p class="mt-3 font-display text-sm font-semibold text-ink">No gifts in this view</p>
                                    <p class="mt-1 text-[12px] text-muted">
                                        Once somebody supports Sofor through <a href="{{ route('support.index') }}" class="text-brand hover:underline">the donate page</a>, it shows up here.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($donations->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $donations->links() }}</div>
            @endif
        </x-card>
    </div>
</x-layouts.admin>
