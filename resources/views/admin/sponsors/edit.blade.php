<x-layouts.admin :title="'Edit '.$sponsor->name" :active="'sponsors'" heading="Edit sponsor" :subtitle="$sponsor->name">

    <div class="mx-auto flex max-w-xl flex-col gap-4">
        <a href="{{ route('admin.sponsors.index') }}" class="inline-flex items-center gap-1.5 self-start text-[12.5px] font-semibold text-muted transition hover:text-ink">
            <x-icon name="arrow-left" class="size-3.5" /> All sponsors
        </a>

        <x-card>
            @include('admin.sponsors._form', ['sponsor' => $sponsor])
        </x-card>
    </div>
</x-layouts.admin>
