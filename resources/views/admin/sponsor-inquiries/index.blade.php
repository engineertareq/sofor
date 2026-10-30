@php
    use App\Models\SponsorInquiry;

    $tabs = [
        'open' => 'Open',
        SponsorInquiry::STATUS_NEW => 'New',
        SponsorInquiry::STATUS_CONTACTED => 'Contacted',
        SponsorInquiry::STATUS_CLOSED => 'Closed',
        'all' => 'All',
    ];

    $statusTone = fn (string $status) => match ($status) {
        SponsorInquiry::STATUS_NEW => 'rose',
        SponsorInquiry::STATUS_CONTACTED => 'amber',
        default => 'slate',
    };
@endphp

<x-layouts.admin title="Sponsor requests" :active="'sponsor-requests'" heading="Sponsor requests"
                 subtitle="Brands that asked about sponsoring Sofor from the sponsors page.">

    <x-slot:actions>
        <x-btn :href="route('admin.sponsors.index')" variant="secondary" size="sm" icon="building" class="hidden sm:inline-flex">Sponsors</x-btn>
    </x-slot:actions>

    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <x-admin.tabs :tabs="$tabs" :current="$status" :counts="$counts" urgent="new" label="Filter requests" class="self-start" />

            <x-admin.search :value="$search" placeholder="Search name, brand or email" :keep="['status' => $status]" class="sm:ml-auto sm:w-72" />
        </div>

        @if ($inquiries->isEmpty())
            <x-card class="flex flex-col items-center px-6 py-14 text-center">
                <span class="flex size-14 items-center justify-center rounded-3xl bg-brand-tint text-brand">
                    <x-icon name="mail" class="size-6" />
                </span>
                <p class="mt-4 font-display text-base font-semibold text-ink">
                    {{ $search !== '' ? 'Nothing matches "'.$search.'"' : 'No requests here' }}
                </p>
                <p class="mt-1 max-w-sm text-[12.5px] leading-relaxed text-muted">
                    When a brand fills in the form on <a href="{{ route('sponsors') }}#contact" target="_blank" class="font-semibold text-brand hover:underline">the sponsors page</a>,
                    it shows up here.
                </p>
            </x-card>
        @else
            <ul class="flex flex-col gap-2.5">
                @foreach ($inquiries as $inquiry)
                    @php $isNew = $inquiry->status === SponsorInquiry::STATUS_NEW; @endphp
                    <li>
                        <article @class([
                            'group relative grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-2.5 overflow-hidden rounded-4xl border bg-surface p-3.5 transition hover:-translate-y-0.5 hover:shadow-lift sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:gap-x-4 sm:p-4',
                            'border-brand/35' => $isNew,
                            'border-line' => ! $isNew,
                        ])>
                            @if ($isNew)
                                <span class="absolute inset-y-0 left-0 w-1 bg-brand" aria-hidden="true"></span>
                            @endif

                            <x-avatar :name="$inquiry->company" size="sm" class="shrink-0" />

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <a href="{{ route('admin.sponsor-inquiries.show', $inquiry) }}"
                                       class="truncate font-display text-[15px] font-semibold text-ink after:absolute after:inset-0 after:content-['']">
                                        {{ $inquiry->company }}
                                    </a>
                                    <x-badge :tone="$statusTone($inquiry->status)" size="sm">{{ $inquiry->statusLabel() }}</x-badge>
                                    @if ($inquiry->tier)
                                        <x-sponsor-tier :tier="$inquiry->tier" size="sm" />
                                    @endif
                                </div>
                                <p class="mt-0.5 truncate text-[12.5px] text-muted">
                                    {{ $inquiry->name }} <span class="text-faint">&middot;</span> {{ $inquiry->email }}
                                    @if ($inquiry->phone)
                                        <span class="text-faint">&middot;</span> <span class="num">{{ $inquiry->phone }}</span>
                                    @endif
                                </p>
                                <p class="mt-1.5 line-clamp-2 text-[12.5px] leading-relaxed text-ink/80">{{ $inquiry->message }}</p>
                            </div>

                            <div class="col-span-2 flex items-center justify-between gap-3 sm:col-span-1 sm:flex-col sm:items-end">
                                <time datetime="{{ $inquiry->created_at->toIso8601String() }}" title="{{ $inquiry->created_at->format('d M Y, H:i') }}"
                                      class="text-[11.5px] text-faint">{{ $inquiry->created_at->diffForHumans() }}</time>
                                <span class="relative z-10 flex items-center gap-1.5">
                                    <a href="mailto:{{ $inquiry->email }}?subject={{ rawurlencode('Sponsoring Sofor - '.$inquiry->company) }}"
                                       class="inline-flex h-7 items-center gap-1 rounded-lg border border-line bg-surface px-2.5 text-[11.5px] font-semibold text-muted transition hover:border-brand/40 hover:text-brand">
                                        <x-icon name="mail" class="size-3.5" /> Reply
                                    </a>
                                    @if ($isNew)
                                        <form method="POST" action="{{ route('admin.sponsor-inquiries.update', $inquiry) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="{{ SponsorInquiry::STATUS_CONTACTED }}">
                                            <button type="submit"
                                                    class="inline-flex h-7 items-center gap-1 rounded-lg bg-brand px-2.5 text-[11.5px] font-semibold text-white transition hover:bg-brand-2">
                                                <x-icon name="check" class="size-3.5" /> Contacted
                                            </button>
                                        </form>
                                    @endif
                                </span>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>

            @if ($inquiries->hasPages())
                <div>{{ $inquiries->links() }}</div>
            @endif
        @endif
    </div>
</x-layouts.admin>
