{{-- A private profile: the same night sky as a public one, with the map kept out of sight. --}}
<x-layouts.site :title="'@'.$user->username" :card="false" :wide="true" :noindex="true">
    <section class="relative isolate mt-4 overflow-hidden bg-ink text-canvas">
        <div aria-hidden="true" class="absolute inset-0 -z-10 opacity-[0.12]"
             style="background-image: radial-gradient(circle, #fbf8f2 1px, transparent 1.3px); background-size: 22px 22px;"></div>
        <div aria-hidden="true" class="absolute -right-40 top-0 -z-10 size-[36rem] rounded-full opacity-40 blur-3xl"
             style="background: radial-gradient(circle, #0f6f6a, transparent 65%)"></div>

        <div class="mx-auto flex w-full max-w-xl flex-col items-center px-4 py-16 text-center sm:py-20">
            <div class="relative">
                <x-avatar :name="$user->name" :gradient="$user->avatarGradient()" size="xl" :ring="false"
                          class="size-28! rounded-full! text-3xl! shadow-none! opacity-90" />
                <span class="absolute -bottom-1 -right-1 flex size-11 items-center justify-center rounded-full border-[3px] border-ink bg-canvas text-ink">
                    <x-icon name="lock" class="size-5" />
                </span>
            </div>

            <p class="mt-6 font-semibold text-canvas/80" translate="no">{{ '@'.$user->username }}</p>
            <h1 class="mt-2 font-display text-[2rem] font-semibold leading-tight tracking-tight sm:text-[2.4rem]">{{ __('This profile is private') }}</h1>
            <p class="mt-3 max-w-sm text-[14px] leading-relaxed text-canvas/65">{{ __('This traveller keeps their travel map to themselves.') }}</p>

            <div class="mt-8 flex flex-wrap justify-center gap-2">
                <a href="{{ route('explorers') }}" class="inline-flex h-11 items-center gap-2 rounded-full bg-[#f6b23a] px-5 text-[13.5px] font-semibold text-ink transition hover:bg-[#ffc65a]">
                    <x-icon name="flag" class="size-4" /> {{ __('Meet the Explorers') }}
                </a>
                <a href="{{ route('map-maker') }}" class="inline-flex h-11 items-center gap-2 rounded-full border border-canvas/20 bg-canvas/5 px-5 text-[13.5px] font-semibold text-canvas transition hover:bg-canvas/10">
                    <x-icon name="globe" class="size-4" /> {{ __('Make your travel map') }}
                </a>
            </div>
        </div>
    </section>
</x-layouts.site>
