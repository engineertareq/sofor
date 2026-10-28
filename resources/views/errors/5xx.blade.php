{{-- Every 5xx without a page of its own (503 has one). Never shows the
     exception message: on our side it can contain internals. --}}
<x-layouts.error title="{{ __('Something went wrong') }}">
    <span class="flex size-16 items-center justify-center rounded-3xl border-[1.5px] border-line bg-surface text-negative shadow-soft">
        <x-icon name="alert" class="size-7" />
    </span>

    <p class="eyebrow mt-7 text-faint">{{ __('Error :get_status_code', ['get_status_code' => $exception->getStatusCode()]) }}</p>
    <h1 class="mt-3 font-display text-[1.8rem] font-semibold leading-tight tracking-tight sm:text-4xl">{{ __('Something broke on our side') }}</h1>
    <p class="mt-4 max-w-md text-[14px] leading-relaxed text-muted">
        {{ __('It is not you. Please try again in a moment - your trips and balances are safe. If it keeps happening, call us on +880 1922-992279.') }}
    </p>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <x-btn type="button" icon="refresh" onclick="window.location.reload()">{{ __('Try again') }}</x-btn>
        <x-btn :href="route('home')" variant="outline" icon="home">{{ __('Go to the home page') }}</x-btn>
    </div>
</x-layouts.error>
