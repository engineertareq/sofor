@props(['name', 'options' => [], 'value' => null, 'placeholder' => null])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $errors->has($name);
    $selected = old($name, $value);
@endphp

<div class="relative">
    <select id="{{ $id }}" name="{{ $name }}"
            {{ $attributes->except(['id', 'class'])->merge(['class' => 'form-field appearance-none pr-10 '.($hasError ? 'form-field-error' : '')]) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>

    <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-faint">
        <x-icon name="chevron-down" class="size-4" />
    </span>
</div>
