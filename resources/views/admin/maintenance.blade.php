@php
    $active = $maintenance->active();
@endphp

<x-layouts.admin title="Maintenance" :active="'maintenance'" heading="Maintenance mode"
                 subtitle="Close Sofor to everyone except administrators while you work on it.">
    <x-slot:actions>
        <x-btn :href="route('admin.maintenance.preview')" variant="secondary" size="sm" icon="eye" target="_blank" rel="noopener" title="Preview page" class="max-sm:w-9 max-sm:px-0">
            <span class="sr-only sm:not-sr-only">Preview page</span>
        </x-btn>
    </x-slot:actions>

    <div class="grid gap-4 sm:gap-5 xl:grid-cols-[1.4fr_1fr] xl:items-start">
        <x-card>
            {{-- Status --}}
            @if ($active)
                <div class="flex items-start gap-3 rounded-3xl border border-warning/30 bg-warning-tint p-4">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/15 text-warning">
                        <x-icon name="settings" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-display text-base font-semibold text-ink">Sofor is closed for maintenance</p>
                        <p class="mt-0.5 text-[12px] leading-relaxed text-muted">
                            Since {{ $maintenance->since()?->format('d M Y, g:i a') ?? 'just now' }}
                            ({{ $maintenance->since()?->diffForHumans() ?? 'moments ago' }})@if ($maintenance->startedBy()), turned on by {{ $maintenance->startedBy() }}@endif.
                            Only administrators can use the site.
                        </p>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-3 rounded-3xl border border-positive/30 bg-positive-tint p-4">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-positive/15 text-positive">
                        <x-icon name="check-circle" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-display text-base font-semibold text-ink">Sofor is live</p>
                        <p class="mt-0.5 text-[12px] leading-relaxed text-muted">Everyone can sign in, plan trips and pay.</p>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.maintenance.update') }}" class="mt-6 grid gap-4"
                  x-data="{ message: {{ \Illuminate\Support\Js::from(old('message', $maintenance->message() ?? '')) }} }">
                @csrf
                @method('PUT')

                <x-field name="message" label="Message for visitors" hint="Optional. Shown on the maintenance page.">
                    <x-textarea name="message" x-model="message" maxlength="{{ \App\Services\MaintenanceMode::MESSAGE_MAX }}"
                                placeholder="We are upgrading payments. Back by 6pm today." :value="old('message', $maintenance->message())" />
                </x-field>
                <p class="-mt-2 text-right text-[11px] text-faint">
                    <span x-text="message.length">0</span> / {{ \App\Services\MaintenanceMode::MESSAGE_MAX }}
                </p>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    @if ($active)
                        <x-btn type="submit" name="action" value="on" variant="secondary" icon="check">Save message</x-btn>
                        <x-btn type="submit" name="action" value="off" variant="success" icon="globe">Turn off and reopen Sofor</x-btn>
                    @else
                        <x-btn type="submit" name="action" value="on" variant="warn" icon="settings"
                               onclick="return confirm('Close Sofor for maintenance? Everyone except administrators will see the maintenance page until you turn it off.')">
                            Turn on maintenance mode
                        </x-btn>
                    @endif
                </div>
            </form>
        </x-card>

        <x-card>
            <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                <x-icon name="info" class="size-4 text-brand" /> While it is on
            </h2>
            <ul class="mt-4 flex flex-col gap-3 text-[12.5px] leading-relaxed text-muted">
                <li class="flex gap-2.5">
                    <x-icon name="ban" class="mt-0.5 size-3.5 shrink-0 text-warning" />
                    Every page - the home page, trips, payments, invite links - shows the maintenance page instead.
                </li>
                <li class="flex gap-2.5">
                    <x-icon name="users" class="mt-0.5 size-3.5 shrink-0 text-warning" />
                    Travellers stay signed in, but cannot open anything. New sign ins and sign ups are refused.
                </li>
                <li class="flex gap-2.5">
                    <x-icon name="shield" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                    Administrators keep full access. The sign in page stays open, so you can always get back in from
                    any device.
                </li>
                <li class="flex gap-2.5">
                    <x-icon name="refresh" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                    The maintenance page checks every minute, so visitors see Sofor again as soon as you turn it off.
                </li>
            </ul>
        </x-card>
    </div>
</x-layouts.admin>
