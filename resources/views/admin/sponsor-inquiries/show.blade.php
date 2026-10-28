@php
    use App\Models\SponsorInquiry;

    $mailto = 'mailto:'.$inquiry->email.'?subject='.rawurlencode('Sponsoring Sofor - '.$inquiry->company);
@endphp

<x-layouts.admin :title="$inquiry->company" :active="'sponsor-requests'" heading="Sponsor request" :subtitle="$inquiry->company">

    <div class="flex flex-col gap-4">
        <a href="{{ route('admin.sponsor-inquiries.index') }}" class="inline-flex items-center gap-1.5 self-start text-[12.5px] font-semibold text-muted transition hover:text-ink">
            <x-icon name="arrow-left" class="size-3.5" /> All requests
        </a>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
            {{-- ===================== The message ===================== --}}
            <x-card>
                <div class="flex items-start gap-4">
                    <x-avatar :name="$inquiry->company" size="lg" class="shrink-0" />
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-xl font-semibold leading-tight text-ink sm:text-2xl">{{ $inquiry->company }}</h2>
                        <p class="mt-1 text-[13px] text-muted">From {{ $inquiry->name }}</p>
                        <div class="mt-2.5 flex flex-wrap items-center gap-2">
                            @if ($inquiry->tier)
                                <x-sponsor-tier :tier="$inquiry->tier" />
                            @else
                                <x-badge tone="slate">Tier not chosen</x-badge>
                            @endif
                            <span class="text-[11.5px] text-faint">{{ $inquiry->created_at->format('d M Y, H:i') }} &middot; {{ $inquiry->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 rounded-3xl border border-line bg-canvas p-5">
                    <p class="eyebrow">Message</p>
                    <p class="mt-3 whitespace-pre-line break-words text-[14.5px] leading-relaxed text-ink">{{ $inquiry->message }}</p>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <x-btn :href="$mailto" icon="mail">Reply by email</x-btn>
                    @if ($inquiry->phone)
                        <x-btn :href="'tel:'.preg_replace('/[^0-9+]/', '', $inquiry->phone)" variant="outline" icon="phone">Call</x-btn>
                    @endif
                </div>
            </x-card>

            {{-- ===================== Details and status ===================== --}}
            <div class="flex flex-col gap-4">
                <x-card>
                    <h3 class="font-display text-sm font-semibold">Status</h3>
                    <form method="POST" action="{{ route('admin.sponsor-inquiries.update', $inquiry) }}" class="mt-3 grid grid-cols-3 gap-1 rounded-2xl border border-line bg-surface-2 p-1">
                        @csrf
                        @method('PUT')
                        @foreach (SponsorInquiry::STATUSES as $value => $label)
                            <button type="submit" name="status" value="{{ $value }}" @if ($inquiry->status === $value) aria-pressed="true" @endif
                                    @class([
                                        'rounded-xl px-2 py-2 text-[12.5px] font-semibold transition',
                                        'bg-surface text-ink shadow-soft' => $inquiry->status === $value,
                                        'text-muted hover:text-ink' => $inquiry->status !== $value,
                                    ])>{{ $label }}</button>
                        @endforeach
                    </form>
                    <p class="mt-2.5 text-[11px] leading-relaxed text-faint">
                        New requests are counted in the console menu until they are marked contacted or closed.
                    </p>
                </x-card>

                <x-card>
                    <h3 class="font-display text-sm font-semibold">Contact</h3>
                    <dl class="mt-3 flex flex-col divide-y divide-line text-[13px]">
                        <div class="flex flex-col gap-0.5 py-2.5">
                            <dt class="text-[11px] text-faint">Email</dt>
                            <dd><a href="{{ $mailto }}" class="break-all font-medium text-brand hover:underline">{{ $inquiry->email }}</a></dd>
                        </div>
                        <div class="flex flex-col gap-0.5 py-2.5">
                            <dt class="text-[11px] text-faint">Phone</dt>
                            <dd class="num font-medium text-ink">{{ $inquiry->phone ?: '-' }}</dd>
                        </div>
                        <div class="flex flex-col gap-0.5 py-2.5">
                            <dt class="text-[11px] text-faint">Website</dt>
                            <dd>
                                @if ($inquiry->website)
                                    <a href="{{ $inquiry->website }}" target="_blank" rel="noopener nofollow noreferrer"
                                       class="inline-flex max-w-full items-center gap-1 break-all font-medium text-brand hover:underline">
                                        {{ preg_replace('~^https?://(www\.)?~i', '', $inquiry->website) }} <x-icon name="arrow-up-right" class="size-3 shrink-0" />
                                    </a>
                                @else
                                    <span class="text-ink">-</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex flex-col gap-0.5 py-2.5">
                            <dt class="text-[11px] text-faint">Sent from</dt>
                            <dd class="font-mono text-[11.5px] text-muted">{{ $inquiry->ip_address ?: '-' }}</dd>
                        </div>
                    </dl>
                </x-card>

                <form method="POST" action="{{ route('admin.sponsor-inquiries.destroy', $inquiry) }}"
                      onsubmit="return confirm('Delete this request for good?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="flex h-10 w-full items-center justify-center gap-2 rounded-2xl border border-line bg-surface text-[12.5px] font-semibold text-muted transition hover:border-negative/40 hover:text-negative">
                        <x-icon name="trash" class="size-4" /> Delete request
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
