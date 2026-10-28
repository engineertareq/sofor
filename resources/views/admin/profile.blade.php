<x-layouts.admin title="Your account" :active="'profile'" heading="Your account"
                 subtitle="Your administrator details and password.">

    <div class="grid gap-6 xl:grid-cols-[1.4fr_1fr] xl:items-start">
        <div class="flex flex-col gap-6">
            @include('profile.partials.details')

            @include('profile.partials.password')
        </div>

        <x-card>
            <h2 class="flex items-center gap-2 font-display text-sm font-semibold">
                <x-icon name="shield" class="size-4 text-brand" /> Console only
            </h2>
            <p class="mt-2 text-[12px] leading-relaxed text-muted">
                Administrator accounts run Sofor from this console and cannot plan or join trips. To travel with
                friends, register a separate traveller account with a different email or phone.
            </p>
        </x-card>
    </div>
</x-layouts.admin>
