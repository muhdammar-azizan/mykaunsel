import './bootstrap';

import Alpine from 'alpinejs';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.Alpine = Alpine;

Alpine.start();

const MALAYSIA_CENTER = [4.2105, 101.9758];

const STATE_CENTERS = {
    'Johor': [1.4854, 103.7618],
    'Kedah': [6.1184, 100.3685],
    'Kelantan': [5.9804, 102.1526],
    'Melaka': [2.1896, 102.2501],
    'Negeri Sembilan': [2.7258, 101.9424],
    'Pahang': [3.8126, 103.3256],
    'Perak': [4.5921, 101.0901],
    'Perlis': [6.4449, 100.2048],
    'Pulau Pinang': [5.4141, 100.3288],
    'Sabah': [5.9788, 116.0753],
    'Sarawak': [1.5533, 110.3592],
    'Selangor': [3.0738, 101.5183],
    'Terengganu': [5.3117, 103.1324],
    'W.P. Kuala Lumpur': [3.1390, 101.6869],
    'W.P. Labuan': [5.2831, 115.2308],
    'W.P. Putrajaya': [2.9264, 101.6964],
};

function teardropIcon() {
    return L.divIcon({
        className: 'mk-map-pin',
        html: '<svg width="34" height="46" viewBox="0 0 34 46" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter:drop-shadow(0 6px 8px rgba(14,42,51,.28))"><path d="M17 45C17 45 31 28.5 31 17C31 8.16 24.28 1 17 1C9.72 1 3 8.16 3 17C3 28.5 17 45 17 45Z" fill="#0F6B7D" stroke="#FAF8F5" stroke-width="2"/><circle cx="17" cy="17" r="6.5" fill="#FAF8F5"/></svg>',
        iconSize: [34, 46],
        iconAnchor: [17, 46],
    });
}

/**
 * Wires a real, interactive OpenStreetMap (via Leaflet) into an address form:
 * the pin is draggable/clickable, and typed addresses are geocoded (via the
 * free Nominatim API) to move the pin near the right spot automatically.
 *
 * config: { addrId, postcodeId, cityId, stateId, wrapId, canvasId, coordsId, latId, lngId }
 */
window.MyKaunselLocationPicker = function (config) {
    var addr = document.getElementById(config.addrId);
    var postcode = document.getElementById(config.postcodeId);
    var cityEl = config.cityId ? document.getElementById(config.cityId) : null;
    var stateEl = config.stateId ? document.getElementById(config.stateId) : null;
    var wrap = document.getElementById(config.wrapId);
    var canvas = document.getElementById(config.canvasId);
    var coordsEl = document.getElementById(config.coordsId);
    var latInput = document.getElementById(config.latId);
    var lngInput = document.getElementById(config.lngId);

    if (!addr || !postcode || !wrap || !canvas) return;

    var map = null;
    var marker = null;
    var geocodeTimer = null;

    function setCoords(lat, lng) {
        coordsEl.textContent = lat.toFixed(4) + '° N, ' + lng.toFixed(4) + '° E';
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
    }

    function placeMarker(lat, lng) {
        marker.setLatLng([lat, lng]);
        setCoords(lat, lng);
    }

    function initMapIfNeeded() {
        if (map) return;

        var startCenter = MALAYSIA_CENTER;
        var startZoom = 6;
        if (stateEl && stateEl.value && STATE_CENTERS[stateEl.value]) {
            startCenter = STATE_CENTERS[stateEl.value];
            startZoom = 12;
        }

        map = L.map(canvas, { zoomControl: true }).setView(startCenter, startZoom);
        // Plain OpenStreetMap tiles: the only fully free, no-API-key option
        // with reliable, complete coverage of Malaysia. CARTO's free anonymous
        // tiles now require a key, and ESRI's free street tiles have real
        // gaps in Malaysian coverage — both were tried and ruled out.
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        }).addTo(map);

        marker = L.marker(startCenter, { icon: teardropIcon(), draggable: true }).addTo(map);
        marker.on('dragend', function () {
            var pos = marker.getLatLng();
            setCoords(pos.lat, pos.lng);
        });
        map.on('click', function (e) {
            placeMarker(e.latlng.lat, e.latlng.lng);
        });

        setCoords(startCenter[0], startCenter[1]);

        // Leaflet needs a visible container with a final size to measure
        // itself correctly; this block only becomes visible right before
        // we init, so re-measure on the next tick.
        setTimeout(function () { map.invalidateSize(); }, 50);
    }

    function checkAddressFilled() {
        var filled = addr.value.trim().length > 2 && postcode.value.trim().length > 3;
        if (filled && wrap.hidden) {
            wrap.hidden = false;
            initMapIfNeeded();
        } else if (!filled) {
            wrap.hidden = true;
        }
    }

    function geocodeStructured(params, zoom) {
        var query = new URLSearchParams(Object.assign({
            format: 'json',
            limit: '1',
            countrycodes: 'my',
            country: 'Malaysia',
        }, params));

        return fetch('https://nominatim.openstreetmap.org/search?' + query.toString())
            .then(function (r) { return r.json(); })
            .then(function (results) {
                if (results && results[0]) {
                    var lat = parseFloat(results[0].lat);
                    var lng = parseFloat(results[0].lon);
                    map.setView([lat, lng], zoom);
                    placeMarker(lat, lng);
                    return true;
                }
                return false;
            })
            .catch(function () { return false; });
    }

    function geocode() {
        if (!map) return;

        var fullAddr = addr.value.trim();
        var city = cityEl ? cityEl.value.trim() : '';
        var state = stateEl ? stateEl.value : '';
        var postcodeVal = postcode.value.trim();

        // Malaysian addresses are usually typed as "<unit/house>, <street>,
        // <neighbourhood>" — Nominatim's structured search (separate
        // street/city/state fields) matches far more reliably than cramming
        // everything into one free-text string, but a leading house/unit
        // number in the street field alone usually breaks the match, so we
        // also try the address with that first segment dropped.
        var segments = fullAddr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var withoutHouseNumber = segments.length > 1 ? segments.slice(1).join(', ') : null;
        var streetNameOnly = segments.length > 1 ? segments[1] : null;

        var attempts = [];
        if (fullAddr) {
            attempts.push({ params: { street: fullAddr, city: city, state: state, postalcode: postcodeVal }, zoom: 17 });
        }
        if (withoutHouseNumber) {
            attempts.push({ params: { street: withoutHouseNumber, city: city, state: state }, zoom: 16 });
        }
        if (streetNameOnly && streetNameOnly !== withoutHouseNumber) {
            attempts.push({ params: { street: streetNameOnly, city: city, state: state }, zoom: 16 });
        }
        if (postcodeVal) {
            // A Malaysian postcode centroid is usually a fairly tight local
            // area — a much better fallback than the whole city.
            attempts.push({ params: { postalcode: postcodeVal }, zoom: 14 });
        }
        if (city || state) {
            attempts.push({ params: { city: city, state: state }, zoom: 13 });
        }

        (function tryNext(i) {
            if (i >= attempts.length) return;
            geocodeStructured(attempts[i].params, attempts[i].zoom).then(function (found) {
                if (!found) tryNext(i + 1);
            });
        })(0);
    }

    function scheduleGeocode() {
        clearTimeout(geocodeTimer);
        geocodeTimer = setTimeout(geocode, 900);
    }

    addr.addEventListener('input', checkAddressFilled);
    postcode.addEventListener('input', checkAddressFilled);
    addr.addEventListener('blur', scheduleGeocode);
    postcode.addEventListener('blur', scheduleGeocode);
    if (cityEl) cityEl.addEventListener('blur', scheduleGeocode);
    if (stateEl) stateEl.addEventListener('change', scheduleGeocode);
};
