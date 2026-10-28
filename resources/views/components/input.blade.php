@props(['name', 'type' => 'text', 'value' => null, 'icon' => null, 'suffix' => null])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $errors->has($name);
@endphp

<div class="relative">
    @if ($icon)
        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-faint">
            <x-icon :name="$icon" class="size-4" />
        </span>
    @endif

    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           value="{{ old($name, $value) }}"
           @if ($hasError) aria-invalid="true" @endif
           {{ $attributes->except(['id', 'class'])->merge(['class' => 'form-field '.($icon ? 'pl-10 ' : '').($suffix ? 'pr-12 ' : '').($hasError ? 'form-field-error' : '')]) }} />

    @if ($suffix)
        <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-faint">
            {{ $suffix }}
        </span>
    @endif
</div>
