@props(['name', 'value' => null, 'rows' => 3])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $errors->has($name);
@endphp

<textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
          {{ $attributes->except(['id', 'class'])->merge(['class' => 'form-field resize-none '.($hasError ? 'form-field-error' : '')]) }}>{{ old($name, $value) }}</textarea>
