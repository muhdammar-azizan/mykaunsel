{{-- Included with: prefix (unique ids), values (what to show in the fields), states. --}}
@php
    $values = $values ?? [];
    $input = 'mt-1 block w-full min-h-[44px] rounded-md border border-brand-ink/20 px-3 text-[14px] focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/20';
    $label = 'block text-[13px] font-semibold text-brand-ink/70';
    $v = fn (string $key) => $values[$key] ?? '';
@endphp

<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <label for="{{ $prefix }}-name" class="{{ $label }}">{{ __('Name of the place') }}</label>
        <input id="{{ $prefix }}-name" name="name" type="text" maxlength="100" required value="{{ $v('name') }}" placeholder="{{ __('Clinic Aman, Kuantan') }}" class="{{ $input }}">
        <p class="mt-1 text-[12.5px] text-brand-ink/60">{{ __('Clients see this name and the city before they book. Do not use your home address.') }}</p>
    </div>
    <div>
        <label for="{{ $prefix }}-city" class="{{ $label }}">{{ __('City') }}</label>
        <input id="{{ $prefix }}-city" name="city" type="text" maxlength="100" required value="{{ $v('city') }}" class="{{ $input }}">
    </div>
    <div class="sm:col-span-2">
        <label for="{{ $prefix }}-address" class="{{ $label }}">{{ __('Full address') }}</label>
        <input id="{{ $prefix }}-address" name="address" type="text" maxlength="255" required value="{{ $v('address') }}" class="{{ $input }}">
        <p class="mt-1 text-[12.5px] text-brand-ink/60">{{ __('Only a client who has booked a session there sees the full address.') }}</p>
    </div>
    <div>
        <label for="{{ $prefix }}-state" class="{{ $label }}">{{ __('State') }}</label>
        <select id="{{ $prefix }}-state" name="state" required class="{{ $input }}">
            <option value="">{{ __('Choose a state') }}</option>
            @foreach ($states as $state)
                <option value="{{ $state }}" @selected($v('state') === $state)>{{ $state }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $prefix }}-postcode" class="{{ $label }}">{{ __('Postcode') }}</label>
        <input id="{{ $prefix }}-postcode" name="postcode" type="text" inputmode="numeric" maxlength="5" pattern="\d{5}" required value="{{ $v('postcode') }}" class="{{ $input }}">
    </div>
    <div class="sm:col-span-2">
        <label for="{{ $prefix }}-description" class="{{ $label }}">{{ __('Directions for clients who booked (optional)') }}</label>
        <textarea id="{{ $prefix }}-description" name="description" rows="2" maxlength="500" class="{{ $input }}">{{ $v('description') }}</textarea>
    </div>
</div>
