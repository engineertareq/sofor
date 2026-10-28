@php
    // Every 4xx without a page of its own (404 has one).
    $status = $exception->getStatusCode();
    $detail = trim((string) $exception->getMessage());

    [$title, $body] = match ($status) {
        401 => [__('Please sign in first'), __('You need to be signed in to see this page.')],
        403 => [__('This page is not for you'), $detail !== '' ? $detail : __('Your account does not have access to this page.')],
        405 => [__('That did not work'), __('This page cannot be opened that way. Go back and try the button again.')],
        419 => [__('This page expired'), __('The form was open for a long time, so it was reset for your safety. Go back, reload the page and try again.')],
        429 => [__('Slow down a little'), __('That was a lot of requests in a short time. Wait a minute, then try again.')],
        default => [__('Something is not right'), __('That request could not be completed.')],
    };

    $user = auth()->user();
    [$homeUrl, $homeLabel] = match (true) {
        $user?->isAdmin() === true => [route('admin.dashboard'), __('Go to the console')],
        $user !== null => [route('dashboard'), __('Back to my trips')],
        default => [route('home'), __('Go to the home page')],
    };
@endphp

<x-layouts.error :title="$title">
    <span class="flex size-16 items-center justify-center rounded-3xl border-[1.5px] border-line bg-surface text-warning shadow-soft">
        <x-icon :name="$status === 403 ? 'lock' : 'alert'" class="size-7" />
    </span>

    <p class="eyebrow mt-7 text-faint">{{ __('Error :status', ['status' => $status]) }}</p>
    <h1 class="mt-3 font-display text-[1.8rem] font-semibold leading-tight tracking-tight sm:text-4xl">{{ $title }}</h1>
    <p class="mt-4 max-w-md text-[14px] leading-relaxed text-muted">{{ $body }}</p>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        @if ($status === 401 && $user === null)
            <x-btn :href="route('login')" icon="user">{{ __('Sign in') }}</x-btn>
        @else
            <x-btn :href="$homeUrl" icon="home">{{ $homeLabel }}</x-btn>
        @endif
        <x-btn type="button" variant="outline" icon="arrow-left"
               onclick="document.referrer.indexOf(window.location.origin) === 0 ? history.back() : window.location.assign('{{ $homeUrl }}')">
            {{ __('Go back') }}
        </x-btn>
    </div>
</x-layouts.error>
