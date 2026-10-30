{{-- `for` names the input's id when it is not the field's name: two forms on one page with the same field. --}}
@props(['name', 'label' => null, 'hint' => null, 'required' => false, 'for' => null])

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $for ?? $name }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">
            {{ \App\Support\Lang::t($label) }}
            @if ($required)
                <span class="text-negative">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="mt-1.5 text-xs text-faint">{{ \App\Support\Lang::t($hint) }}</p>
    @endif

    @error($name)
        <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-negative">
            <x-icon name="alert" class="size-3.5 shrink-0" />
            {{ $message }}
        </p>
    @enderror
</div>
