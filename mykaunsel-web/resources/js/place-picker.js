// The map of "Add a place" / "Change this place" (resources/views/counselors/dashboard/place-form.blade.php).
// The counselor fills in the address; the pin is placed for them (the address is looked up on OpenStreetMap's search, Nominatim) and they can
// drag it, tap the map, use their current position or nudge it with buttons (the keyboard alternative to dragging) until it sits exactly on
// the entrance. A pin is REQUIRED: the form can not be sent until one has been set.
//
// All text shown comes from the page (data-text-* attributes), and is put in with textContent. The class names live in the Blade page.
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const root = document.getElementById('place-picker');

if (root) {
    const $ = (id) => document.getElementById(id);
    const form = $('pl-form');
    const street = $('pl-address');
    const postcode = $('pl-postcode');
    const city = $('pl-city');
    const state = $('pl-state');
    const latInput = $('pl-lat');
    const lngInput = $('pl-lng');
    const status = $('pl-status');
    const readout = $('pl-coords');
    const needPin = $('pl-pin-error');
    const text = (key) => root.dataset['text' + key] ?? '';

    // Malaysia, roughly (Langkawi to Sabah, Johor to Sarawak): a pin outside it is a mistake.
    const BOUNDS = { south: 0.8, north: 7.6, west: 99.0, east: 119.6 };
    const inMalaysia = (lat, lng) => lat >= BOUNDS.south && lat <= BOUNDS.north && lng >= BOUNDS.west && lng <= BOUNDS.east;
    const MALAYSIA_CENTER = [4.2105, 101.9758];
    const STATE_CENTERS = {
        'Johor': [1.4854, 103.7618], 'Kedah': [6.1184, 100.3685], 'Kelantan': [5.9804, 102.1526], 'Melaka': [2.1896, 102.2501],
        'Negeri Sembilan': [2.7258, 101.9424], 'Pahang': [3.8126, 103.3256], 'Perak': [4.5921, 101.0901], 'Perlis': [6.4449, 100.2048],
        'Pulau Pinang': [5.4141, 100.3288], 'Sabah': [5.9788, 116.0753], 'Sarawak': [1.5533, 110.3592], 'Selangor': [3.0738, 101.5183],
        'Terengganu': [5.3117, 103.1324], 'W.P. Kuala Lumpur': [3.1390, 101.6869], 'W.P. Labuan': [5.2831, 115.2308], 'W.P. Putrajaya': [2.9264, 101.6964],
    };

    let pinSet = false;
    let placedByHand = false;   // the counselor moved or tapped the pin: an address change must not take it away again
    let marker = null;
    let timer = null;
    let lookups = 0;

    const say = (message, kind = 'info') => {
        status.textContent = message;
        status.dataset.kind = kind;
    };

    const startAt = Number.isFinite(parseFloat(root.dataset.lat)) && Number.isFinite(parseFloat(root.dataset.lng))
        ? [parseFloat(root.dataset.lat), parseFloat(root.dataset.lng)] : null;

    const map = L.map($('pl-map'), { zoomControl: true, maxBounds: [[BOUNDS.south - 1, BOUNDS.west - 1], [BOUNDS.north + 1, BOUNDS.east + 1]], maxBoundsViscosity: 0.8, minZoom: 5 })
        .setView(startAt ?? (STATE_CENTERS[state.value] ?? MALAYSIA_CENTER), startAt ? 17 : (STATE_CENTERS[state.value] ? 10 : 6));

    // Plain OpenStreetMap tiles: the free, key-less option with complete coverage of Malaysia.
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    }).addTo(map);

    const icon = L.divIcon({
        className: '',
        html: '<svg width="34" height="46" viewBox="0 0 34 46" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="filter:drop-shadow(0 4px 6px rgba(27,42,78,.35))"><path d="M17 1C8.7 1 2 7.6 2 15.8 2 27 17 45 17 45s15-18 15-29.2C32 7.6 25.3 1 17 1z" fill="#0A4F88" stroke="#fff" stroke-width="2"/><circle cx="17" cy="16" r="6" fill="#fff"/></svg>',
        iconSize: [34, 46],
        iconAnchor: [17, 45],
    });

    const setPin = (lat, lng, { zoom = null, byHand = false } = {}) => {
        if (! inMalaysia(lat, lng)) {
            say(text('Outside'), 'error');

            return false;
        }

        if (! marker) {
            marker = L.marker([lat, lng], { icon, draggable: true, keyboard: false, title: text('PinTitle') }).addTo(map);
            marker.on('dragend', () => {
                const at = marker.getLatLng();

                if (setPin(at.lat, at.lng, { byHand: true })) {
                    say(text('Moved'), 'ok');
                }
            });
        } else {
            marker.setLatLng([lat, lng]);
        }

        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
        readout.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
        pinSet = true;
        placedByHand = placedByHand || byHand;
        needPin.hidden = true;

        if (zoom !== null) {
            map.setView([lat, lng], zoom);
        }

        return true;
    };

    if (startAt) {
        setPin(startAt[0], startAt[1], { byHand: true });
        say(text('Saved'), 'ok');
    }

    map.on('click', (e) => {
        if (setPin(e.latlng.lat, e.latlng.lng, { byHand: true })) {
            say(text('Moved'), 'ok');
        }
    });

    // ---- finding the address on the map ---------------------------------------------------------------------------------------------

    const search = async (params) => {
        const query = new URLSearchParams({ format: 'json', limit: '1', countrycodes: 'my', country: 'Malaysia', 'accept-language': 'en', ...params });

        try {
            const response = await fetch('https://nominatim.openstreetmap.org/search?' + query.toString());
            const results = await response.json();

            return results && results[0] ? { lat: parseFloat(results[0].lat), lng: parseFloat(results[0].lon) } : null;
        } catch (e) {
            return null;
        }
    };

    // From the most exact to the roughest: the street with its house number, the street without it, the street name alone, the postcode, the city.
    const attempts = () => {
        const full = street.value.trim();
        const c = city.value.trim();
        const s = state.value;
        const p = postcode.value.trim();
        const parts = full.split(',').map((x) => x.trim()).filter(Boolean);
        const list = [];

        if (full) list.push({ params: { street: full, city: c, state: s, postalcode: p }, zoom: 17, exact: true });
        if (parts.length > 1) list.push({ params: { street: parts.slice(1).join(', '), city: c, state: s }, zoom: 16, exact: true });
        if (parts.length > 2) list.push({ params: { street: parts[1], city: c, state: s }, zoom: 16, exact: true });
        if (p) list.push({ params: { postalcode: p }, zoom: 14, exact: false });
        if (c || s) list.push({ params: { city: c, state: s }, zoom: 13, exact: false });

        return list;
    };

    const find = async (force) => {
        if (! force && (placedByHand || street.value.trim().length < 3 || ! /^\d{5}$/.test(postcode.value.trim()) || ! state.value)) {
            return;
        }

        const mine = ++lookups;
        say(text('Searching'), 'info');

        for (const attempt of attempts()) {
            const found = await search(attempt.params);

            if (mine !== lookups) {
                return;   // a newer search started: this answer is old
            }

            if (found && setPin(found.lat, found.lng, { zoom: attempt.zoom })) {
                placedByHand = false;
                say(attempt.exact ? text('FoundExact') : text('FoundApprox'), attempt.exact ? 'ok' : 'warn');

                return;
            }
        }

        say(text('NotFound'), 'warn');
    };

    const later = () => {
        clearTimeout(timer);
        timer = setTimeout(() => find(false), 900);
    };

    [street, postcode, city].forEach((el) => el.addEventListener('blur', later));
    state.addEventListener('change', () => {
        if (! pinSet && STATE_CENTERS[state.value]) {
            map.setView(STATE_CENTERS[state.value], 10);
        }

        later();
    });

    $('pl-find').addEventListener('click', () => find(true));

    $('pl-locate').addEventListener('click', () => {
        if (! navigator.geolocation) {
            say(text('LocateFailed'), 'warn');

            return;
        }

        say(text('Locating'), 'info');
        navigator.geolocation.getCurrentPosition(
            (position) => {
                if (setPin(position.coords.latitude, position.coords.longitude, { zoom: 17, byHand: true })) {
                    say(text('Located'), 'ok');
                }
            },
            () => say(text('LocateFailed'), 'warn'),
            { enableHighAccuracy: true, timeout: 10000 },
        );
    });

    // The keyboard (and a steady hand on a phone): move the pin about 11 metres at a time.
    const STEP = 0.0001;
    document.querySelectorAll('[data-nudge]').forEach((button) => button.addEventListener('click', () => {
        if (! marker) {
            say(text('NeedPinFirst'), 'warn');

            return;
        }

        const at = marker.getLatLng();
        const [dy, dx] = { n: [STEP, 0], s: [-STEP, 0], e: [0, STEP], w: [0, -STEP] }[button.dataset.nudge];

        if (setPin(at.lat + dy, at.lng + dx, { byHand: true })) {
            map.panTo(marker.getLatLng());
            say(text('Moved'), 'ok');
        }
    }));

    form.addEventListener('submit', (event) => {
        if (! pinSet) {
            event.preventDefault();
            needPin.hidden = false;
            $('pl-map').scrollIntoView({ block: 'center' });
        }
    });

    // The map is drawn while its box may still be settling: measure again once the page is in place.
    setTimeout(() => map.invalidateSize(), 150);
}
