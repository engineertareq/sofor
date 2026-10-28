@php
    use App\Services\BrandingService;
@endphp

<x-layouts.admin title="Branding" :active="'settings'" heading="Branding"
                 subtitle="The logo and favicon used on every page of Sofor.">

    <div class="grid gap-6 xl:grid-cols-2 xl:items-start">
        {{-- ===================== Logo ===================== --}}
        <x-card x-data="imagePicker({{ BrandingService::LOGO_MAX_KILOBYTES }})">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand/12 text-brand">
                        <x-icon name="sparkles" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-display text-base font-semibold">Logo</h2>
                        <p class="text-xs text-muted">Headers, menus, sign in pages, the footer and trip PDFs.</p>
                    </div>
                </div>
                <x-badge :tone="$branding->hasLogo() ? 'lime' : 'slate'">{{ $branding->hasLogo() ? 'Custom' : 'Built in' }}</x-badge>
            </div>

            {{-- The logo at the sizes it really appears: a desktop and a phone header. --}}
            <div class="mt-5 grid gap-3 rounded-3xl border border-line bg-canvas p-4">
                <p class="eyebrow text-faint" x-text="preview ? 'Preview of the new logo' : 'Current logo'">Current logo</p>

                <div class="flex min-w-0 items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3">
                    <template x-if="preview">
                        <img :src="preview" alt="New logo, desktop header size" class="h-9 w-auto max-w-[14rem] object-contain object-left">
                    </template>
                    <div x-show="! preview" class="min-w-0"><x-logo /></div>
                    <span class="ml-auto hidden h-8 w-20 shrink-0 rounded-full bg-surface-2 sm:block" aria-hidden="true"></span>
                </div>

                <div class="flex w-full max-w-[20rem] min-w-0 items-center gap-3 rounded-2xl border border-line bg-surface px-3 py-2.5">
                    <span class="size-8 shrink-0 rounded-xl bg-surface-2" aria-hidden="true"></span>
                    <template x-if="preview">
                        <img :src="preview" alt="New logo, phone header size" class="h-6 w-auto max-w-[7.5rem] object-contain object-left">
                    </template>
                    @if ($branding->hasLogo())
                        <img x-show="! preview" src="{{ $branding->logoUrl() }}" alt="Current logo, phone header size"
                             class="h-6 w-auto max-w-[7.5rem] object-contain object-left">
                    @else
                        <span x-show="! preview" class="min-w-0"><x-logo size="sm" /></span>
                    @endif
                    <span class="ml-auto text-[10px] text-faint">Phone</span>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.settings.logo.update') }}" enctype="multipart/form-data"
                  class="mt-5" @submit="saving = true">
                @csrf

                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="sr-only"
                       x-ref="file" @change="pick($event)" tabindex="-1" aria-hidden="true">

                <div x-show="preview" x-cloak class="flex flex-wrap items-center gap-2">
                    <x-btn type="submit" size="sm" icon="check" x-bind:disabled="saving">
                        <span x-text="saving ? 'Saving...' : 'Use this logo'">Use this logo</span>
                    </x-btn>
                    <x-btn type="button" variant="ghost" size="sm" @click="cancel()" x-bind:disabled="saving">Cancel</x-btn>
                </div>

                <div x-show="! preview" class="flex flex-wrap items-center gap-2">
                    <x-btn type="button" variant="secondary" size="sm" icon="share" @click="$refs.file.click()">
                        {{ $branding->hasLogo() ? 'Upload a new logo' : 'Upload a logo' }}
                    </x-btn>
                    @if ($branding->hasLogo())
                        <x-btn type="submit" variant="ghost" size="sm" icon="refresh" form="reset-logo">Reset to default</x-btn>
                    @endif
                </div>
            </form>

            @if ($branding->hasLogo())
                <form id="reset-logo" method="POST" action="{{ route('admin.settings.logo.destroy') }}" class="hidden"
                      onsubmit="return confirm('Go back to the built in Sofor logo?')">
                    @csrf
                    @method('DELETE')
                </form>
            @endif

            <p x-show="error" x-cloak x-text="error" class="mt-3 text-xs font-medium text-negative"></p>
            @error('logo')
                <p x-show="! error && ! preview" class="mt-3 flex items-center gap-1.5 text-xs font-medium text-negative">
                    <x-icon name="alert" class="size-3.5" /> {{ $message }}
                </p>
            @enderror

            <p class="mt-3 text-[11px] leading-relaxed text-faint">
                A PNG with a transparent background works best, and a wide wordmark is fine - it is shown 36px tall.
                Transparent edges are trimmed for you. At least 32px tall, PNG, JPG or WebP, up to 4 MB.
            </p>
        </x-card>

        {{-- ===================== Favicon ===================== --}}
        <x-card x-data="imagePicker({{ BrandingService::FAVICON_MAX_KILOBYTES }})">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-info-tint text-info">
                        <x-icon name="globe" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-display text-base font-semibold">Favicon</h2>
                        <p class="text-xs text-muted">Browser tabs, bookmarks and the icon of the installed app.</p>
                    </div>
                </div>
                <x-badge :tone="$branding->hasFavicon() ? 'lime' : 'slate'">{{ $branding->hasFavicon() ? 'Custom' : 'Built in' }}</x-badge>
            </div>

            @php
                $currentTab = $branding->faviconUrl(32) ?? asset('favicon.svg');
                $currentTile = $branding->faviconUrl(180) ?? asset('icons/sofor-icon.svg');
            @endphp

            <div class="mt-5 grid gap-4 rounded-3xl border border-line bg-canvas p-4">
                <p class="eyebrow text-faint" x-text="preview ? 'Preview of the new favicon' : 'Current favicon'">Current favicon</p>

                {{-- A browser tab --}}
                <div class="flex w-full max-w-[16rem] items-center gap-2 rounded-t-xl border border-b-0 border-line bg-surface px-3 py-2">
                    <img :src="preview || '{{ $currentTab }}'" src="{{ $currentTab }}" alt="" class="size-4 shrink-0 object-contain">
                    <span class="min-w-0 truncate text-[11px] text-ink">Sofor - Split every trip cost</span>
                    <x-icon name="close" class="ml-auto size-3 shrink-0 text-faint" />
                </div>

                {{-- A phone home screen --}}
                <div class="flex items-center gap-3">
                    <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-line bg-white shadow-soft">
                        <img :src="preview || '{{ $currentTile }}'" src="{{ $currentTile }}" alt="" @load="preview && measure($event)"
                             class="size-full object-contain">
                    </span>
                    <span class="text-[11px] text-muted">Home screen icon</span>
                </div>
            </div>

            <p x-show="notSquare" x-cloak class="mt-3 flex items-start gap-1.5 text-xs font-medium text-warning">
                <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                This image is not square, so it will be centred with empty space around it.
            </p>

            <form method="POST" action="{{ route('admin.settings.favicon.update') }}" enctype="multipart/form-data"
                  class="mt-5" @submit="saving = true">
                @csrf

                <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp" class="sr-only"
                       x-ref="file" @change="pick($event)" tabindex="-1" aria-hidden="true">

                <div x-show="preview" x-cloak class="flex flex-wrap items-center gap-2">
                    <x-btn type="submit" size="sm" icon="check" x-bind:disabled="saving">
                        <span x-text="saving ? 'Saving...' : 'Use this favicon'">Use this favicon</span>
                    </x-btn>
                    <x-btn type="button" variant="ghost" size="sm" @click="cancel()" x-bind:disabled="saving">Cancel</x-btn>
                </div>

                <div x-show="! preview" class="flex flex-wrap items-center gap-2">
                    <x-btn type="button" variant="secondary" size="sm" icon="share" @click="$refs.file.click()">
                        {{ $branding->hasFavicon() ? 'Upload a new favicon' : 'Upload a favicon' }}
                    </x-btn>
                    @if ($branding->hasFavicon())
                        <x-btn type="submit" variant="ghost" size="sm" icon="refresh" form="reset-favicon">Reset to default</x-btn>
                    @endif
                </div>
            </form>

            @if ($branding->hasFavicon())
                <form id="reset-favicon" method="POST" action="{{ route('admin.settings.favicon.destroy') }}" class="hidden"
                      onsubmit="return confirm('Go back to the built in Sofor icon?')">
                    @csrf
                    @method('DELETE')
                </form>
            @endif

            <p x-show="error" x-cloak x-text="error" class="mt-3 text-xs font-medium text-negative"></p>
            @error('favicon')
                <p x-show="! error && ! preview" class="mt-3 flex items-center gap-1.5 text-xs font-medium text-negative">
                    <x-icon name="alert" class="size-3.5" /> {{ $message }}
                </p>
            @enderror

            <p class="mt-3 text-[11px] leading-relaxed text-faint">
                A square image works best - 512 x 512 is ideal. Sofor makes the tab, home screen and app icon sizes from it.
                At least 48 x 48, PNG, JPG or WebP, up to 2 MB.
            </p>
        </x-card>
    </div>

    <p class="mt-6 flex items-start gap-2 rounded-2xl border border-line bg-surface p-3.5 text-[11px] leading-relaxed text-muted">
        <x-icon name="info" class="mt-0.5 size-3.5 shrink-0 text-brand" />
        Changes apply to every page straight away. Browsers hold on to favicons, so a hard refresh may be needed to see a new one in your own tab.
    </p>
</x-layouts.admin>
