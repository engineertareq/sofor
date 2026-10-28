<x-card>
    @php
        $hasPhoto = $user->avatar_path !== null;
        // Photos from before the whole picture was kept can only be adjusted within their square.
        $squareOnly = $hasPhoto && $user->avatar_original_path === null;
    @endphp

    {{-- The photo: picking one opens the cropper first, and "Adjust" opens it again on
         the whole photo kept from the upload (resources/js/avatar-cropper.js). --}}
    <div class="flex items-center gap-4"
         x-data="avatarCropper(@js([
             'maxKilobytes' => \App\Services\AvatarService::MAX_KILOBYTES,
             'maxZoom' => \App\Services\AvatarService::MAX_ZOOM,
             'original' => $hasPhoto ? route('profile.avatar.original') : null,
             'saved' => $user->avatar_crop,
         ]))">
        <div class="relative shrink-0">
            @if ($hasPhoto)
                <button type="button" @click="adjust()" class="block rounded-4xl transition hover:opacity-90"
                        aria-label="{{ __('Adjust photo') }}">
                    <x-avatar :name="$user->name" :gradient="$user->avatarGradient()" :src="$user->avatarUrl()" size="xl" />
                </button>
            @else
                <x-avatar :name="$user->name" :gradient="$user->avatarGradient()" size="xl" />
            @endif

            <button type="button" @click="$refs.file.click()"
                    class="absolute -bottom-1.5 -right-1.5 flex size-8 items-center justify-center rounded-full border-[1.5px] border-line bg-surface text-ink shadow-soft transition hover:text-brand"
                    aria-label="{{ $hasPhoto ? __('Change profile photo') : __('Upload a profile photo') }}">
                <x-icon name="camera" class="size-4" />
            </button>
        </div>

        <div class="min-w-0">
            <h2 class="font-display text-lg font-semibold">{{ $user->name }}</h2>
            <p class="text-[12px] text-muted">{{ $user->handle() }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if ($user->isAdmin())
                    <x-badge tone="violet" icon="shield">{{ __('Administrator') }}</x-badge>
                @else
                    <x-badge tone="brand" icon="user">{{ __('Traveller') }}</x-badge>
                @endif
                <x-badge tone="slate" icon="clock">{{ __('Joined :date', ['date' => $user->created_at->translatedFormat('M Y')]) }}</x-badge>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-btn type="button" variant="secondary" size="xs" icon="camera" @click="$refs.file.click()">
                    {{ $hasPhoto ? __('Change photo') : __('Upload photo') }}
                </x-btn>

                @if ($hasPhoto)
                    <x-btn type="button" variant="secondary" size="xs" icon="crop" @click="adjust()">{{ __('Adjust') }}</x-btn>
                    <x-btn type="submit" variant="ghost" size="xs" icon="trash" form="remove-avatar">{{ __('Remove') }}</x-btn>
                @endif
            </div>

            {{-- A new photo goes up with the square chosen for it. --}}
            <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" x-ref="upload">
                @csrf
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only"
                       x-ref="file" @change="pick($event)" tabindex="-1" aria-hidden="true">
                <input type="hidden" name="crop_x"><input type="hidden" name="crop_y"><input type="hidden" name="crop_size">
            </form>

            @if ($hasPhoto)
                <form method="POST" action="{{ route('profile.avatar.crop') }}" x-ref="recrop" class="hidden">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="crop_x"><input type="hidden" name="crop_y"><input type="hidden" name="crop_size">
                </form>

                <form id="remove-avatar" method="POST" action="{{ route('profile.avatar.destroy') }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif

            <p x-show="error" x-cloak class="mt-2 flex items-center gap-1.5 text-xs font-medium text-negative">
                <x-icon name="alert" class="size-3.5 shrink-0" /> <span x-text="error"></span>
            </p>

            @if ($errors->hasAny(['avatar', 'crop_x', 'crop_y', 'crop_size']))
                <p x-show="! error" class="mt-2 flex items-center gap-1.5 text-xs font-medium text-negative">
                    <x-icon name="alert" class="size-3.5 shrink-0" /> {{ $errors->first('avatar') ?: __('That crop did not work. Try again.') }}
                </p>
            @endif

            <p class="mt-2 text-[11px] text-faint">{{ __('You place and zoom the photo before it is saved. JPG, PNG or WebP, up to 8 MB.') }}</p>
        </div>

        <template x-teleport="body">
            <x-sheet :title="__('Frame your photo')" :subtitle="__('Drag to move it. Pinch, scroll or use the slider to zoom.')" icon="crop" size="sm">
                <div class="mx-auto w-full max-w-[22rem]">
                    <div x-ref="stage" x-init="observe($el)" class="crop-stage"
                         :class="{ 'is-dragging': dragging, 'is-ready': ready }"
                         tabindex="0" role="group" aria-roledescription="{{ __('Photo cropper') }}"
                         aria-label="{{ __('Drag or use the arrow keys to move the photo; + and - zoom.') }}"
                         @pointerdown="down($event)" @pointermove="move($event)"
                         @pointerup="up($event)" @pointercancel="up($event)" @lostpointercapture="up($event)"
                         @wheel.prevent="wheel($event)" @keydown="key($event)">
                        <img :src="src" x-show="src" x-on:load="loaded($event)" x-on:error="broken()" :style="imageStyle()"
                             alt="" draggable="false" class="crop-image">

                        <div class="crop-frame" :style="`inset: ${inset}px`">
                            <span class="crop-grid"></span>
                        </div>

                        <div x-show="! ready && ! failed" class="absolute inset-0 grid place-items-center">
                            <span class="size-8 animate-spin rounded-full border-[3px] border-white/25 border-t-white"></span>
                        </div>
                        <p x-show="failed" x-cloak x-text="failed"
                           class="absolute inset-x-6 top-1/2 -translate-y-1/2 text-center text-[13px] font-medium text-white"></p>
                    </div>

                    {{-- Zoom --}}
                    <div class="mt-4 flex items-center gap-2.5" :class="{ 'pointer-events-none opacity-50': ! ready }">
                        <button type="button" @click="zoomBy(1 / 1.25)" :disabled="zoom <= 1.001"
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl text-muted transition hover:bg-surface-2 hover:text-ink disabled:opacity-40"
                                aria-label="{{ __('Zoom out') }}">
                            <x-icon name="minus" class="size-4.5" />
                        </button>
                        <input type="range" min="1" max="{{ \App\Services\AvatarService::MAX_ZOOM }}" step="0.01"
                               class="crop-zoom min-w-0 flex-1" aria-label="{{ __('Zoom') }}"
                               :value="zoom" @input="zoom = $event.target.value"
                               :style="`--fill: ${((zoom - 1) / {{ \App\Services\AvatarService::MAX_ZOOM - 1 }} * 100).toFixed(1)}%`">
                        <button type="button" @click="zoomBy(1.25)" :disabled="zoom >= {{ \App\Services\AvatarService::MAX_ZOOM }} - 0.001"
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl text-muted transition hover:bg-surface-2 hover:text-ink disabled:opacity-40"
                                aria-label="{{ __('Zoom in') }}">
                            <x-icon name="plus" class="size-4.5" />
                        </button>
                    </div>

                    {{-- How it will look, at the sizes Sofor shows it --}}
                    <div class="mt-3 flex items-center gap-3 rounded-2xl border border-line bg-surface-2 px-3.5 py-3">
                        <div class="flex shrink-0 items-end gap-2" aria-hidden="true">
                            <span class="crop-preview size-14 rounded-[1.15rem]"><img :src="src" x-show="ready" :style="previewStyle(56)" alt=""></span>
                            <span class="crop-preview size-9 rounded-xl"><img :src="src" x-show="ready" :style="previewStyle(36)" alt=""></span>
                            <span class="crop-preview size-7 rounded-full"><img :src="src" x-show="ready" :style="previewStyle(28)" alt=""></span>
                        </div>
                        <p class="min-w-0 flex-1 text-[11.5px] leading-snug text-muted">{{ __('How it looks on your profile and trips.') }}</p>
                        <button type="button" @click="recentre()" x-show="ready"
                                class="shrink-0 rounded-lg px-2 py-1 text-[12px] font-semibold text-brand transition hover:bg-brand-tint">
                            {{ __('Reset') }}
                        </button>
                    </div>

                    @if ($squareOnly)
                        <p x-show="mode === 'adjust'" class="mt-3 flex items-start gap-2 text-[11.5px] leading-relaxed text-muted">
                            <x-icon name="info" class="mt-0.5 size-3.5 shrink-0 text-brand" />
                            <span>
                                {{ __('This photo was saved before Sofor kept the whole picture, so only its square can be adjusted. To get back what was cut off, upload it again.') }}
                                <button type="button" @click="$refs.file.click()" class="font-semibold text-brand hover:underline">{{ __('Choose photo') }}</button>
                            </span>
                        </p>
                    @endif
                </div>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-btn type="button" variant="ghost" @click="open = false" x-bind:disabled="saving">{{ __('Cancel') }}</x-btn>
                        <x-btn type="button" icon="check" @click="save()" x-bind:disabled="! ready || saving">
                            <span x-text="saving ? @js(__('Saving...')) : @js(__('Save photo'))">{{ __('Save photo') }}</span>
                        </x-btn>
                    </div>
                </x-slot:footer>
            </x-sheet>
        </template>
    </div>

    <form method="POST" action="{{ route('profile.update') }}" class="mt-6 grid gap-4">
        @csrf
        @method('PUT')

        <x-field name="name" :label="__('Full name')" required>
            <x-input name="name" :value="old('name', $user->name)" required />
        </x-field>

        <x-field name="username" :label="__('Username')" required>
            <x-username-input :value="old('username', $user->username)" />
        </x-field>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                ['email', __('Email address'), 'email', 'mail', $user->email, $user->hasVerifiedEmail()],
                ['phone', __('Mobile number'), 'tel', 'phone', $user->phone, $user->hasVerifiedPhone()],
            ] as [$field, $label, $type, $icon, $current, $confirmed])
                <x-field :name="$field" :label="$label">
                    <x-input :name="$field" :type="$type" :icon="$icon" :value="old($field, $current)" />

                    {{-- Where the saved value stands; changing it means confirming it again. --}}
                    @if ($current !== null)
                        <p class="mt-1.5 flex items-center gap-1.5 text-[11.5px]">
                            @if ($confirmed)
                                <span class="inline-flex items-center gap-1 font-semibold text-positive">
                                    <x-icon name="verified" class="size-3.5" /> {{ __('Confirmed') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 font-semibold text-warning">
                                    <x-icon name="alert" class="size-3.5" /> {{ __('Not confirmed yet') }}
                                </span>
                                <span class="text-faint">&middot;</span>
                                <a href="{{ route('verification.notice') }}" class="font-semibold text-brand hover:underline">{{ __('Confirm it') }}</a>
                            @endif
                        </p>
                    @else
                        <p class="mt-1.5 text-[11.5px] text-faint">{{ $field === 'email' ? __('Optional') : __('Optional, e.g. 01712345678') }}</p>
                    @endif
                </x-field>
            @endforeach
        </div>

        <p class="flex items-start gap-2 rounded-2xl border border-line bg-surface-2 p-3.5 text-[11px] leading-relaxed text-muted">
            <x-icon name="info" class="mt-0.5 size-3.5 shrink-0 text-brand" />
            @if ($user->isAdmin())
                {{ __('Keep at least one of email or phone - it is how you sign in to the console and reset your password. A new one is confirmed with a link or a texted code.') }}
            @else
                {{ __('Keep at least one of email or phone. A new one is confirmed with a link or a texted code, and once it is confirmed, trip invites sent to it join your account automatically.') }}
            @endif
        </p>

        {{-- Admin accounts are console only and have no public profile. --}}
        @unless ($user->isAdmin())
            @php $isPublic = (bool) old('profile_public', $user->profile_public); @endphp

            <div x-data="{ on: {{ $isPublic ? 'true' : 'false' }} }"
                 class="flex items-start gap-3 rounded-2xl border border-line bg-surface-2 p-3.5">
                <input type="hidden" name="profile_public" value="{{ $isPublic ? 1 : 0 }}" :value="on ? 1 : 0">
                <button type="button" role="switch" class="switch mt-0.5" @click="on = ! on"
                        aria-labelledby="public-profile-label"
                        aria-checked="{{ $isPublic ? 'true' : 'false' }}" :aria-checked="on.toString()"
                        data-on="{{ $isPublic ? 'true' : 'false' }}" :data-on="on.toString()"></button>

                <div class="min-w-0 flex-1">
                    <p id="public-profile-label" class="text-[13px] font-semibold text-ink">{{ __('Public profile') }}</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed text-muted"
                       x-text="on ? @js(__('Anyone with the link sees your name, photo and travel map - never your email, phone or trips.')) : @js(__('Hidden. Visitors see that the profile is private.'))">
                        {{ $isPublic ? __('Anyone with the link sees your name, photo and travel map - never your email, phone or trips.') : __('Hidden. Visitors see that the profile is private.') }}
                    </p>
                    <a href="{{ $user->publicUrl() }}" target="_blank" rel="noopener"
                       class="mt-1.5 inline-flex max-w-full items-center gap-1 truncate text-[12px] font-semibold text-brand hover:underline">
                        <span translate="no">{{ request()->getHttpHost() }}/{{ '@'.$user->username }}</span>
                        <x-icon name="arrow-up-right" class="size-3.5 shrink-0" />
                    </a>
                </div>
            </div>
        @endunless

        <div class="flex justify-end">
            <x-btn type="submit" icon="check">{{ __('Save profile') }}</x-btn>
        </div>
    </form>
</x-card>
