{{-- The console's sections, grouped: the sidebar on a laptop and the slide-in
     menu on a phone. A tap also closes that menu (`menu` in layouts/admin). --}}
@props(['groups', 'active' => null])

<nav {{ $attributes->merge(['class' => 'flex flex-col gap-4']) }}>
    @foreach ($groups as $group)
        <div>
            <p class="eyebrow px-2.5 pb-1.5">{{ $group['label'] }}</p>
            <div class="flex flex-col gap-0.5">
                @foreach ($group['items'] as $item)
                    @php $on = $active === $item['key']; @endphp
                    <a href="{{ $item['href'] }}" @click="menu = false" @if ($on) aria-current="page" @endif
                       @class([
                           'flex items-center gap-2.5 rounded-xl px-2.5 py-2 text-[13px] font-semibold transition',
                           'bg-brand text-brand-ink' => $on,
                           'text-muted hover:bg-surface-2 hover:text-ink' => ! $on,
                       ])>
                        <x-icon :name="$item['icon']" class="size-4 shrink-0" />
                        <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                        @if (($item['badge'] ?? 0) > 0)
                            <span @class([
                                'num rounded-full px-1.5 text-[10px] font-bold leading-4',
                                'bg-white/20 text-white' => $on,
                                'bg-negative text-white' => ! $on,
                            ])>{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</nav>
