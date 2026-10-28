@props(['name' => '', 'gradient' => null, 'size' => 'md', 'ring' => true, 'emoji' => null, 'src' => null])

@php
    $sizes = [
        'xs' => 'size-7 text-[10px] rounded-lg',
        'sm' => 'size-9 text-xs rounded-xl',
        'md' => 'size-11 text-sm rounded-2xl',
        'lg' => 'size-14 text-base rounded-2xl',
        'xl' => 'size-20 text-2xl rounded-4xl',
    ];

    $initials = collect(preg_split('/\s+/', trim((string) $name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $gradient ??= \App\Models\User::GRADIENTS[(is_string($name) ? (crc32($name) % count(\App\Models\User::GRADIENTS)) : 0) % count(\App\Models\User::GRADIENTS)];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center font-bold text-white shadow-soft '.($src ? 'relative overflow-hidden ' : '').($ring ? 'ring-2 ring-surface ' : '').($sizes[$size] ?? $sizes['md'])]) }}
      style="background-image: {{ $gradient }}">
    {{ $emoji ?: ($initials !== '' ? $initials : '?') }}

    {{-- The photo sits over the initials, which show again if it fails to load. --}}
    @if ($src)
        <img src="{{ $src }}" alt="" loading="lazy" decoding="async" onerror="this.remove()"
             class="absolute inset-0 size-full object-cover">
    @endif
</span>
