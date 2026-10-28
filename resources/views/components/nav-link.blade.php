@props(['active' => false, 'href', 'icon', 'mobile' => false])

<a href="{{ $href }}"
   @class([
       'group flex items-center gap-3 rounded-2xl text-[13.5px] font-semibold transition duration-150',
       'px-3 py-2.5' => ! $mobile,
       'flex-col gap-1 px-3 py-2 text-[11px]' => $mobile,
       'bg-brand text-brand-ink' => $active,
       'text-muted hover:bg-surface-2 hover:text-ink' => ! $active,
   ])>
    <x-icon :name="$icon" class="{{ $mobile ? 'size-5' : 'size-4.5' }} shrink-0" />

    <span class="min-w-0 truncate">{{ $slot }}</span>
</a>
