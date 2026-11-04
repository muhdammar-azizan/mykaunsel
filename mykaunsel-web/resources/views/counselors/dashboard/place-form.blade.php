@php
    $editing = $place !== null;
    $card = 'rounded-xl border border-[#E4E2DE] bg-white p-6';
    $input = 'mt-1 block w-full min-h-[44px] rounded-md border border-brand-ink/20 px-3 text-[14px] focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/20';
    $label = 'block text-[13px] font-semibold text-brand-ink/70';
    $hint = 'mt-1 text-[12.5px] text-brand-ink/60';
    $secondary = 'inline-flex min-h-[44px] items-center justify-center rounded-md border border-brand-ink/25 bg-white px-4 text-[14px] font-semibold text-brand-ink transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue';
    $value = fn (string $key) => old($key, $place[$key] ?? '');
    $lat = old('latitude', $place['latitude'] ?? null);
    $lng = old('longitude', $place['longitude'] ?? null);
@endphp

<x-portal-layout :title="$editing ? 'Change this place' : 'Add a place'">
    <style>
        #pl-status[data-kind="ok"] { color: #14532D; }
        #pl-status[data-kind="warn"] { color: #6B4C0C; }
        #pl-status[data-kind="error"] { color: #B91C1C; }
        #pl-map { z-index: 0; }
    </style>

    <p><a href="{{ route('counselor.profile') }}#my-places" class="text-[14px] font-semibold text-brand-blue hover:underline">&larr; My places</a></p>
    <h1 class="mt-2 text-[24px] font-bold leading-tight tracking-tight">{{ $editing ? 'Change this place' : 'Add a place' }}</h1>
    <p class="mt-1 max-w-3xl text-[14px] text-brand-ink/65">Where you see clients in person for your independent practice. Fill in the address and the pin is placed on the map for you; then move it until it sits exactly on the entrance, so clients who book can find you.</p>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form id="pl-form" method="POST" action="{{ $editing ? route('counselor.places.update', $place['id']) : route('counselor.places.store') }}" class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] xl:items-start">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" id="pl-lat" name="latitude" value="{{ $lat }}">
        <input type="hidden" id="pl-lng" name="longitude" value="{{ $lng }}">

        {{-- ---- The address ------------------------------------------------------------------------------------------------------------- --}}
        <section class="{{ $card }} space-y-4" aria-labelledby="address-title">
            <h2 id="address-title" class="text-[18px] font-bold tracking-tight">Address</h2>

            <div>
                <label for="pl-name" class="{{ $label }}">Name of the place</label>
                <input id="pl-name" name="name" type="text" maxlength="100" required value="{{ $value('name') }}" placeholder="Clinic Aman, Kuantan" class="{{ $input }}">
                <p class="{{ $hint }}">Clients see this name and the city before they book. Do not use your home address as the name.</p>
            </div>

            <div>
                <label for="pl-address" class="{{ $label }}">Street, building and unit</label>
                <input id="pl-address" name="address" type="text" maxlength="255" required value="{{ $value('address') }}" placeholder="12, Jalan Mawar, Taman Indah" class="{{ $input }}" autocomplete="street-address">
                <p class="{{ $hint }}">Only a client who has booked a session there sees the full address.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="pl-postcode" class="{{ $label }}">Postcode</label>
                    <input id="pl-postcode" name="postcode" type="text" inputmode="numeric" maxlength="5" pattern="\d{5}" required value="{{ $value('postcode') }}" class="{{ $input }}" autocomplete="postal-code">
                </div>
                <div>
                    <label for="pl-city" class="{{ $label }}">City</label>
                    <input id="pl-city" name="city" type="text" maxlength="100" required value="{{ $value('city') }}" class="{{ $input }}" autocomplete="address-level2">
                </div>
            </div>

            <div>
                <label for="pl-state" class="{{ $label }}">State</label>
                <select id="pl-state" name="state" required class="{{ $input }}" autocomplete="address-level1">
                    <option value="">Choose a state</option>
                    @foreach ($states as $state)
                        <option value="{{ $state }}" @selected($value('state') === $state)>{{ $state }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="pl-description" class="{{ $label }}">Directions for clients who booked (optional)</label>
                <textarea id="pl-description" name="description" rows="3" maxlength="500" class="{{ $input }}" placeholder="Second floor, the lift is on the left.">{{ $value('description') }}</textarea>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-[#EEEDEA] pt-4">
                <button type="submit" class="inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-6 text-[14px] font-semibold text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2">{{ $editing ? 'Save the place' : 'Add the place' }}</button>
                <a href="{{ route('counselor.profile') }}#my-places" class="{{ $secondary }}">Cancel</a>
                <p id="pl-pin-error" role="alert" hidden class="text-[13.5px] font-semibold text-brand-red">Place the pin on the map first: use "Find on map", tap the map, or drag the pin.</p>
            </div>
        </section>

        {{-- ---- The map ----------------------------------------------------------------------------------------------------------------- --}}
        <section class="{{ $card }} xl:sticky xl:top-0" aria-labelledby="map-title"
                 id="place-picker"
                 @if ($lat !== null && $lat !== '' && $lng !== null && $lng !== '') data-lat="{{ $lat }}" data-lng="{{ $lng }}" @endif
                 data-text-outside="The pin has to be inside Malaysia."
                 data-text-pin-title="Drag me to the entrance"
                 data-text-moved="Pin moved. Keep going until it sits exactly on the entrance."
                 data-text-saved="This is the pin you saved. Move it if it is not exactly right."
                 data-text-searching="Looking for the address on the map…"
                 data-text-found-exact="Pin placed at the address. Zoom in and drag it onto the exact entrance."
                 data-text-found-approx="We could only find the area, not the exact address. Zoom in, then drag the pin to the right place."
                 data-text-not-found="We could not find this address. Tap the map where the place is, or press &quot;Use my current location&quot; if you are there."
                 data-text-locating="Finding where you are…"
                 data-text-located="Pin placed where you are. Move it if the entrance is somewhere else."
                 data-text-locate-failed="We could not get your position. Tap the map where the place is instead."
                 data-text-need-pin-first="Place the pin first, then move it with these buttons.">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="map-title" class="text-[18px] font-bold tracking-tight">Pin the exact spot</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="pl-find" class="{{ $secondary }}">Find on map</button>
                    <button type="button" id="pl-locate" class="{{ $secondary }}">Use my current location</button>
                </div>
            </div>

            <p id="pl-status" role="status" aria-live="polite" data-kind="info" class="mt-3 min-h-[20px] text-[13.5px] font-semibold text-brand-ink/75">Fill in the address, then press "Find on map". You can also tap the map.</p>

            <div id="pl-map" class="mt-3 h-[360px] w-full overflow-hidden rounded-xl border border-[#E4E2DE] xl:h-[460px]" role="application" aria-label="Map. Tap to place the pin, or drag the pin. The buttons below move it a few metres at a time."></div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2" role="group" aria-label="Move the pin a few metres">
                    <button type="button" data-nudge="n" class="{{ $secondary }} !px-3" aria-label="Move the pin north">&uarr;</button>
                    <button type="button" data-nudge="s" class="{{ $secondary }} !px-3" aria-label="Move the pin south">&darr;</button>
                    <button type="button" data-nudge="w" class="{{ $secondary }} !px-3" aria-label="Move the pin west">&larr;</button>
                    <button type="button" data-nudge="e" class="{{ $secondary }} !px-3" aria-label="Move the pin east">&rarr;</button>
                </div>
                <p class="text-[12.5px] text-brand-ink/60">Pin: <span id="pl-coords" class="font-semibold">@if ($lat !== null && $lat !== '' && $lng !== null && $lng !== '') {{ number_format((float) $lat, 5) }}, {{ number_format((float) $lng, 5) }} @else not set yet @endif</span></p>
            </div>

            <p class="mt-3 text-[12.5px] leading-relaxed text-brand-ink/60">To place the pin, the address you type is sent to OpenStreetMap's search service, and the map is loaded from OpenStreetMap. Clients who have not booked never see the pin or the full address.</p>
        </section>
    </form>

    @vite('resources/js/place-picker.js')
</x-portal-layout>
