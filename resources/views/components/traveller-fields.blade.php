{{--
    The checkpoint-list fields. Several of these forms can share one page (a
    traveller's own, and the leader's for travellers without an account), so
    each carries a key: old input and errors only show on the form that was
    sent.
--}}
@props(['values' => [], 'key' => 'me'])

@php
    $sent = old('form') === $key;
    $value = fn (string $field) => $sent ? old($field) : ($values[$field] ?? null);
    $error = fn (string $field) => $sent ? $errors->first($field) : null;
    $id = fn (string $field) => 'tf-'.$key.'-'.$field;
    $field = 'form-field';
@endphp

<input type="hidden" name="form" value="{{ $key }}">

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="{{ $id('full_name') }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">{{ __('Full name, as on the ID') }} <span class="text-negative">*</span></label>
        <input id="{{ $id('full_name') }}" name="full_name" value="{{ $value('full_name') }}" maxlength="80" required autocomplete="name"
               class="{{ $field }} {{ $error('full_name') ? 'form-field-error' : '' }}" placeholder="{{ __('In English, e.g. Md. Rifat Hasan') }}">
        @if ($error('full_name'))<p class="mt-1.5 text-xs font-medium text-negative">{{ $error('full_name') }}</p>@endif
    </div>

    <div>
        <label for="{{ $id('id_type') }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">{{ __('ID type') }} <span class="text-negative">*</span></label>
        <select id="{{ $id('id_type') }}" name="id_type" class="{{ $field }}">
            @foreach (\App\Models\TravellerDetail::ID_TYPES as $type => $label)
                <option value="{{ $type }}" @selected(($value('id_type') ?? 'nid') === $type)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="{{ $id('id_number') }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">{{ __('ID number') }} <span class="text-negative">*</span></label>
        <input id="{{ $id('id_number') }}" name="id_number" value="{{ $value('id_number') }}" maxlength="30" required inputmode="text" autocomplete="off" spellcheck="false"
               class="{{ $field }} font-mono {{ $error('id_number') ? 'form-field-error' : '' }}" placeholder="{{ __('10, 13 or 17 digits for an NID') }}">
        @if ($error('id_number'))<p class="mt-1.5 text-xs font-medium text-negative">{{ $error('id_number') }}</p>@endif
    </div>

    <div>
        <label for="{{ $id('date_of_birth') }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">{{ __('Date of birth') }} <span class="text-negative">*</span></label>
        <input id="{{ $id('date_of_birth') }}" name="date_of_birth" type="date" value="{{ $value('date_of_birth') }}" required max="{{ now()->subDay()->toDateString() }}" autocomplete="bday"
               class="{{ $field }} {{ $error('date_of_birth') ? 'form-field-error' : '' }}">
        @if ($error('date_of_birth'))<p class="mt-1.5 text-xs font-medium text-negative">{{ $error('date_of_birth') }}</p>@endif
    </div>

    <div>
        <label for="{{ $id('gender') }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">{{ __('Gender') }}</label>
        <select id="{{ $id('gender') }}" name="gender" class="{{ $field }}">
            <option value="">{{ __('Prefer not to say') }}</option>
            @foreach (\App\Models\TravellerDetail::GENDERS as $gender => $label)
                <option value="{{ $gender }}" @selected($value('gender') === $gender)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="{{ $id('phone') }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">{{ __('Mobile') }}</label>
        <input id="{{ $id('phone') }}" name="phone" type="tel" value="{{ $value('phone') }}" maxlength="24" autocomplete="tel" inputmode="tel"
               class="{{ $field }} {{ $error('phone') ? 'form-field-error' : '' }}" placeholder="{{ __('01XXXXXXXXX') }}">
        @if ($error('phone'))<p class="mt-1.5 text-xs font-medium text-negative">{{ $error('phone') }}</p>@endif
    </div>

    <div>
        <label for="{{ $id('blood_group') }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">{{ __('Blood group') }}</label>
        <select id="{{ $id('blood_group') }}" name="blood_group" class="{{ $field }}">
            <option value="">{{ __('Not sure') }}</option>
            @foreach (\App\Models\TravellerDetail::BLOOD_GROUPS as $group)
                <option value="{{ $group }}" @selected($value('blood_group') === $group)>{{ $group }}</option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2">
        <label for="{{ $id('address') }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">{{ __('Home address') }}</label>
        <input id="{{ $id('address') }}" name="address" value="{{ $value('address') }}" maxlength="160" autocomplete="street-address"
               class="{{ $field }}" placeholder="{{ __('Area, district') }}">
    </div>

    <div>
        <label for="{{ $id('emergency_name') }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">{{ __('Emergency contact') }}</label>
        <input id="{{ $id('emergency_name') }}" name="emergency_name" value="{{ $value('emergency_name') }}" maxlength="80"
               class="{{ $field }}" placeholder="{{ __('e.g. Father, Abdul Karim') }}">
    </div>

    <div>
        <label for="{{ $id('emergency_phone') }}" class="mb-1.5 flex items-center gap-1 text-[13px] font-medium text-muted">{{ __('Their mobile') }}</label>
        <input id="{{ $id('emergency_phone') }}" name="emergency_phone" type="tel" value="{{ $value('emergency_phone') }}" maxlength="24" inputmode="tel"
               class="{{ $field }} {{ $error('emergency_phone') ? 'form-field-error' : '' }}" placeholder="{{ __('01XXXXXXXXX') }}">
        @if ($error('emergency_phone'))<p class="mt-1.5 text-xs font-medium text-negative">{{ $error('emergency_phone') }}</p>@endif
    </div>
</div>
