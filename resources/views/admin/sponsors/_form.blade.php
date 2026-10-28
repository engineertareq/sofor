@php
    use App\Models\Sponsor;
    use App\Services\SponsorLogos;

    /** @var Sponsor|null $sponsor */
    $sponsor ??= null;
    $editing = $sponsor !== null;

    $currentTier = old('tier', $sponsor?->tier ?? 'silver');
    $isActive = (bool) old('is_active', $sponsor?->is_active ?? true);

    // What each tier gets, so the choice is made knowing the difference.
    $tierNotes = [
        'platinum' => 'A featured card at the top of the sponsors page, and first in the home page carousel.',
        'gold' => 'A large tile with its tagline on the sponsors page, ahead of Silver in the carousel.',
        'silver' => 'A logo in the sponsors wall and in the home page carousel.',
    ];
@endphp

<form method="POST" action="{{ $editing ? route('admin.sponsors.update', $sponsor) : route('admin.sponsors.store') }}"
      enctype="multipart/form-data" class="flex flex-col gap-5"
      x-data="{
          tier: @js($currentTier),
          active: @js($isActive),
          tagline: @js((string) old('tagline', $sponsor?->tagline ?? '')),
          notes: @js($tierNotes),
          saving: false,
      }"
      @submit="saving = true">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    {{-- ===== Logo ===== --}}
    <div x-data="imagePicker({{ SponsorLogos::MAX_KILOBYTES }})">
        <p class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">
            Logo @unless ($editing)<span class="text-negative">*</span>@endunless
        </p>

        <button type="button" @click="$refs.file.click()"
                class="group relative flex h-36 w-full flex-col items-center justify-center gap-2 overflow-hidden rounded-3xl border-[1.5px] border-dashed border-line-strong bg-surface p-4 transition hover:border-brand/50 hover:bg-brand-tint/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40"
                aria-label="{{ $editing ? 'Change the logo' : 'Choose a logo' }}">
            <template x-if="preview">
                <img :src="preview" alt="New logo preview" class="max-h-16 max-w-[75%] object-contain">
            </template>

            @if ($editing)
                <img x-show="! preview" src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }} logo" class="max-h-16 max-w-[75%] object-contain">
            @else
                <span x-show="! preview" class="flex flex-col items-center gap-2 text-center">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-brand-tint text-brand transition group-hover:scale-105">
                        <x-icon name="camera" class="size-5" />
                    </span>
                    <span class="text-[13px] font-semibold text-ink">Choose a logo</span>
                    <span class="text-[11px] text-faint">PNG, JPG or WebP - up to 4 MB</span>
                </span>
            @endif

            <span @if (! $editing) x-show="preview" x-cloak @endif
                  class="absolute bottom-2.5 right-2.5 inline-flex items-center gap-1 rounded-full bg-ink/85 px-2.5 py-1 text-[10.5px] font-semibold text-white">
                <x-icon name="refresh" class="size-3" /> Change
            </span>
        </button>

        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="sr-only"
               x-ref="file" @change="pick($event)" tabindex="-1" aria-hidden="true">

        <p x-show="error" x-cloak x-text="error" class="mt-1.5 text-xs font-medium text-negative"></p>
        @error('logo')
            <p x-show="! error && ! preview" class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-negative">
                <x-icon name="alert" class="size-3.5 shrink-0" /> {{ $message }}
            </p>
        @enderror
        <p class="mt-1.5 text-[11px] leading-relaxed text-faint">
            A transparent PNG looks best, and wide wordmarks are fine. Empty transparent edges are trimmed for you.
        </p>
    </div>

    {{-- ===== Details ===== --}}
    <x-field name="name" label="Sponsor name" required>
        <x-input name="name" :value="$sponsor?->name" maxlength="80" placeholder="e.g. Meghna Coaches" required />
    </x-field>

    <x-field name="website" label="Website" hint="Visitors who click the logo go here.">
        <x-input name="website" :value="$sponsor?->website" icon="link" maxlength="255" placeholder="example.com" inputmode="url" />
    </x-field>

    <fieldset>
        <legend class="mb-1.5 text-[13px] font-medium text-muted">Tier</legend>
        <div class="grid grid-cols-1 gap-2 min-[360px]:grid-cols-3">
            @foreach (Sponsor::TIERS as $value => $label)
                <label class="relative flex cursor-pointer flex-col items-center gap-1.5 rounded-2xl border-[1.5px] px-2 py-3 text-center transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/40"
                       :class="tier === @js($value) ? 'border-brand bg-brand-tint/50' : 'border-line bg-surface hover:border-line-strong'">
                    <input type="radio" name="tier" value="{{ $value }}" x-model="tier" class="sr-only" @checked($currentTier === $value)>
                    <x-sponsor-tier :tier="$value" size="sm" />
                </label>
            @endforeach
        </div>
        <p class="mt-1.5 min-h-[2.4em] text-xs leading-relaxed text-faint" x-text="notes[tier]">{{ $tierNotes[$currentTier] ?? '' }}</p>
        @error('tier')
            <p class="mt-1 text-xs font-medium text-negative">{{ $message }}</p>
        @enderror
    </fieldset>

    <x-field name="tagline" label="Tagline" hint="Optional. Shown under Platinum and Gold logos on the sponsors page.">
        <div class="relative">
            <x-textarea name="tagline" :value="$sponsor?->tagline" rows="2" maxlength="140" x-model="tagline"
                        placeholder="e.g. Comfortable coaches to every corner of Bangladesh" />
            <span class="num pointer-events-none absolute bottom-2 right-3 text-[10px] text-faint" x-text="tagline.length + '/140'"></span>
        </div>
    </x-field>

    {{-- ===== Visibility ===== --}}
    <div class="flex items-start gap-3 rounded-2xl border border-line bg-surface-2 p-3.5">
        <input type="hidden" name="is_active" value="{{ $isActive ? 1 : 0 }}" :value="active ? 1 : 0">
        <button type="button" role="switch" class="switch mt-0.5" @click="active = ! active"
                aria-labelledby="sponsor-visible-label"
                aria-checked="{{ $isActive ? 'true' : 'false' }}" :aria-checked="active.toString()"
                data-on="{{ $isActive ? 'true' : 'false' }}" :data-on="active.toString()"></button>
        <div class="min-w-0 flex-1">
            <p id="sponsor-visible-label" class="text-[13px] font-semibold text-ink">Show on the site</p>
            <p class="mt-0.5 text-[11px] leading-relaxed text-muted"
               x-text="active ? 'Live in the home page carousel and on the sponsors page.' : 'Hidden. Kept here so you can switch it back on later.'">
                {{ $isActive ? 'Live in the home page carousel and on the sponsors page.' : 'Hidden. Kept here so you can switch it back on later.' }}
            </p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <x-btn type="submit" :icon="$editing ? 'check' : 'plus'" x-bind:disabled="saving">
            <span x-text="saving ? 'Saving...' : @js($editing ? 'Save changes' : 'Add sponsor')">{{ $editing ? 'Save changes' : 'Add sponsor' }}</span>
        </x-btn>
        @if ($editing)
            <x-btn :href="route('admin.sponsors.index')" variant="ghost">Cancel</x-btn>
        @endif
    </div>
</form>
