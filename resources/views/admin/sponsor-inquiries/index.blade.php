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

    <div class="flex flex-col gap-5">
        <x-card padding="p-3 sm:p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <nav class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-line bg-surface-2 p-1 no-scrollbar" aria-label="Filter requests">
                    @foreach ($tabs as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}" @if ($status === $key) aria-current="page" @endif
                           @class([
                               'flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold transition',
                               'bg-surface text-ink shadow-soft' => $status === $key,
                               'text-muted hover:text-ink' => $status !== $key,
                           ])>
                            {{ $label }}
                            <span @class([
                                'num rounded-full px-1.5 text-[10.5px]',
                                'bg-negative text-white' => $key === 'new' && $counts['new'] > 0,
                                'bg-surface-2 text-faint' => ! ($key === 'new' && $counts['new'] > 0),
                            ])>{{ $counts[$key] }}</span>
                        </a>
                    @endforeach
                </nav>

                <form method="GET" class="lg:ml-auto lg:w-72">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <label for="q" class="sr-only">Search requests</label>
                    <x-input name="q" type="search" :value="$search" icon="search" placeholder="Search name, brand or email" />
                </form>
            </div>
        </x-card>

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
            <ul class="flex flex-col gap-3">
                @foreach ($inquiries as $inquiry)
                    @php $isNew = $inquiry->status === SponsorInquiry::STATUS_NEW; @endphp
                    <li>
                        <article @class([
                            'group relative flex flex-col gap-3 overflow-hidden rounded-4xl border bg-surface p-4 transition hover:-translate-y-0.5 hover:shadow-lift sm:flex-row sm:items-start sm:gap-4 sm:p-5',
                            'border-brand/35' => $isNew,
                            'border-line' => ! $isNew,
                        ])>
                            @if ($isNew)
                                <span class="absolute inset-y-0 left-0 w-1 bg-brand" aria-hidden="true"></span>
                            @endif

                            <x-avatar :name="$inquiry->company" size="md" class="shrink-0" />

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
                                <p class="mt-2 line-clamp-2 text-[13px] leading-relaxed text-ink/80">{{ $inquiry->message }}</p>
                            </div>

                            <div class="flex shrink-0 items-center justify-between gap-3 sm:flex-col sm:items-end">
                                <time datetime="{{ $inquiry->created_at->toIso8601String() }}" title="{{ $inquiry->created_at->format('d M Y, H:i') }}"
                                      class="text-[11.5px] text-faint">{{ $inquiry->created_at->diffForHumans() }}</time>
                                <span class="relative z-10 flex items-center gap-1.5">
                                    <a href="mailto:{{ $inquiry->email }}?subject={{ rawurlencode('Sponsoring Sofor - '.$inquiry->company) }}"
                                       class="inline-flex h-8 items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-[12px] font-semibold text-muted transition hover:border-brand/40 hover:text-brand">
                                        <x-icon name="mail" class="size-3.5" /> Reply
                                    </a>
                                    @if ($isNew)
                                        <form method="POST" action="{{ route('admin.sponsor-inquiries.update', $inquiry) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="{{ SponsorInquiry::STATUS_CONTACTED }}">
                                            <button type="submit"
                                                    class="inline-flex h-8 items-center gap-1.5 rounded-full bg-brand px-3 text-[12px] font-semibold text-white transition hover:bg-brand-2">
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
