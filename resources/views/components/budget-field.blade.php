{{--
    The trip budget: one amount, per person (the default - it grows with the
    group) or for the whole group, plus what trips to the same place usually
    cost. Only the active input is enabled, so only one is submitted.
--}}
@props(['trip' => null, 'estimate' => true])

@php
    $mode = old('budget_mode', $trip && (float) $trip->budget > 0 && ! (float) $trip->budget_per_person ? 'group' : 'person');
    $amount = $mode === 'group'
        ? old('budget', $trip?->budget !== null ? (float) $trip->budget : '')
        : old('budget_per_person', $trip?->budget_per_person !== null ? (float) $trip->budget_per_person : '');
    $hasError = $errors->has('budget') || $errors->has('budget_per_person');
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}
     x-data="budgetPlanner({ mode: @js($mode), amount: @js((string) $amount), url: @js($estimate ? route('trips.estimate') : null) })">
    <div>
        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
            <label for="budget-amount" class="text-[13px] font-medium text-muted">{{ __('Budget') }} <span class="text-faint">{{ __('(optional)') }}</span></label>

            <div class="inline-flex rounded-full border border-line bg-surface-2 p-0.5 text-[11px] font-semibold" role="radiogroup" aria-label="{{ __('Budget is for') }}">
                <input type="hidden" name="budget_mode" :value="mode">
                <button type="button" role="radio" :aria-checked="mode === 'person'" @click="mode = 'person'"
                        :class="mode === 'person' ? 'bg-surface text-ink shadow-soft' : 'text-muted hover:text-ink'"
                        class="rounded-full px-3 py-1 transition">{{ __('Per person') }}</button>
                <button type="button" role="radio" :aria-checked="mode === 'group'" @click="mode = 'group'"
                        :class="mode === 'group' ? 'bg-surface text-ink shadow-soft' : 'text-muted hover:text-ink'"
                        class="rounded-full px-3 py-1 transition">{{ __('Whole group') }}</button>
            </div>
        </div>

        <div class="relative">
            <input id="budget-amount" x-ref="amount" type="number" step="0.01" min="0" inputmode="decimal"
                   x-model="amount" :name="mode === 'person' ? 'budget_per_person' : 'budget'"
                   :placeholder="mode === 'person' ? '3000' : '15000'"
                   @if ($hasError) aria-invalid="true" @endif
                   class="form-field pr-24 {{ $hasError ? 'form-field-error' : '' }}">
            <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-faint"
                  x-text="mode === 'person' ? @js(__('a person')) : @js(__('in total'))">{{ __('a person') }}</span>
        </div>

        <p class="mt-1.5 text-xs text-faint" x-show="mode === 'person'">{{ __('Grows with the group. Everyone sees their share against it, and Sofor warns at 80% and 100%.') }}</p>
        <p class="mt-1.5 text-xs text-faint" x-show="mode === 'group'" x-cloak>{{ __('One fixed amount for the whole trip. Sofor warns the group at 80% and 100%.') }}</p>

        @foreach (['budget', 'budget_per_person'] as $field)
            @error($field)
                <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-negative">
                    <x-icon name="alert" class="size-3.5 shrink-0" /> {{ $message }}
                </p>
            @enderror
        @endforeach
    </div>

    @if ($estimate)
        <div x-show="estimate" x-cloak x-transition.opacity
             class="rounded-3xl border border-info/25 bg-info-tint/60 p-4" aria-live="polite">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-info">{{ __('What trips there cost on Sofor') }}</p>
                    <p class="mt-1 font-display text-lg font-semibold text-ink">
                        {{ __('About') }} <span x-text="money(estimate?.per_person)"></span>
                        <span class="text-[13px] font-medium text-muted">{{ __('a person') }}</span>
                    </p>
                    <p class="text-[11px] text-muted"
                       x-text="(estimate?.days === 1
                                    ? @js(__('Usually :low–:high for 1 day · from :trips finished trips'))
                                    : (estimate?.days
                                        ? @js(__('Usually :low–:high for :days days · from :trips finished trips'))
                                        : @js(__('Usually :low–:high · from :trips finished trips'))))
                                .replace(':low', money(estimate?.low)).replace(':high', money(estimate?.high))
                                .replace(':days', window.SoforNum(estimate?.days)).replace(':trips', window.SoforNum(estimate?.trips))"></p>
                </div>

                <button type="button" @click="use()"
                        class="shrink-0 rounded-full bg-info px-3.5 py-1.5 text-[12px] font-semibold text-white transition hover:opacity-90">
                    {{ __('Use as budget') }}
                </button>
            </div>

            <ul class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <template x-for="row in (estimate?.categories ?? [])" :key="row.label">
                    <li class="rounded-2xl bg-surface/80 px-3 py-2">
                        <p class="truncate text-[11px] text-muted"><span x-html="row.icon"></span> <span x-text="row.label"></span></p>
                        <p class="num text-[13px] font-semibold text-ink" x-text="money(row.amount)"></p>
                    </li>
                </template>
            </ul>
        </div>
    @endif
</div>
