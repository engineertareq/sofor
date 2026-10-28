@props(['name' => 'code', 'length' => 6, 'autoSubmit' => false])

{{-- One real input, so phones can fill the code straight from the text
     message (autocomplete="one-time-code"); the six boxes only draw it. --}}
<div x-data="codeInput({{ (int) $length }}, {{ $autoSubmit ? 'true' : 'false' }})" class="relative">
    <input id="{{ $name }}" name="{{ $name }}" type="text" inputmode="numeric" autocomplete="one-time-code"
           maxlength="{{ $length }}" pattern="\d{{ '{'.$length.'}' }}" required
           value="" x-ref="field" @input="typed($event)" @focus="focused = true" @blur="focused = false"
           aria-label="{{ __(':length digit code', ['length' => $length]) }}" @error($name) aria-invalid="true" @enderror
           class="absolute inset-0 z-10 h-full w-full cursor-text rounded-2xl opacity-0">

    <div class="grid gap-2 sm:gap-2.5" style="grid-template-columns: repeat({{ (int) $length }}, minmax(0, 1fr))" aria-hidden="true">
        @for ($i = 0; $i < $length; $i++)
            <span class="flex h-14 items-center justify-center rounded-2xl border-[1.5px] bg-surface font-mono text-[1.6rem] font-bold text-ink transition sm:h-16"
                  :class="slotClass({{ $i }})" x-text="code[{{ $i }}] ?? ''"></span>
        @endfor
    </div>
</div>

@error($name)
    <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-negative">
        <x-icon name="alert" class="size-3.5 shrink-0" /> {{ $message }}
    </p>
@enderror
