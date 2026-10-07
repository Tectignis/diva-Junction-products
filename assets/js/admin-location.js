/* Diva Junction admin — Location lock page: map preview, Maps-link helper, "use my location". */
(function () {
    'use strict';

    var form = document.getElementById('geo-form');
    if (!form) return;

    var lat = form.elements.latitude;
    var lng = form.elements.longitude;
    var radius = form.elements.radius_meters;

    function num(input) {
        var v = parseFloat(String(input.value).trim().replace(',', '.'));
        return isFinite(v) ? v : null;
    }

    /* ---------------------------------------------------------------- ON / OFF text */
    var toggle = form.querySelector('[data-geo-toggle]');
    toggle.addEventListener('change', function () {
        var card = toggle.closest('.geo-switch-card');
        var note = card.querySelector('[data-on-text]');
        card.classList.toggle('is-on', toggle.checked);
        card.querySelector('.status-pill').textContent = toggle.checked ? 'ON' : 'OFF';
        note.textContent = note.getAttribute(toggle.checked ? 'data-on-text' : 'data-off-text')
            + ' (Not saved yet.)';
    });

    /* ---------------------------------------------------------------- map */
    var map = null;
    var circle = null;
    var dot = null;

    function draw(fit) {
        if (!map) return;
        var la = num(lat);
        var lo = num(lng);
        if (la === null || lo === null || Math.abs(la) > 90 || Math.abs(lo) > 180) {
            if (circle) {
                circle.remove();
                dot.remove();
                circle = dot = null;
            }
            return;
        }
        var centre = [la, lo];
        var r = num(radius) || 500;
        if (!circle) {
            circle = L.circle(centre, { radius: r, color: '#be1b8c', weight: 2, fillColor: '#be1b8c', fillOpacity: 0.12 }).addTo(map);
            dot = L.circleMarker(centre, { radius: 7, color: '#fff', weight: 3, fillColor: '#1642b9', fillOpacity: 1 }).addTo(map);
        } else {
            circle.setLatLng(centre).setRadius(r);
            dot.setLatLng(centre);
        }
        if (fit) map.fitBounds(circle.getBounds(), { padding: [28, 28], maxZoom: 17 });
    }

    var mapEl = document.getElementById('geo-map');
    if (mapEl && window.L) {
        map = L.map(mapEl, { scrollWheelZoom: false }).setView([19.0760, 72.8777], 10);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        map.on('click', function (e) {
            lat.value = e.latlng.lat.toFixed(6);
            lng.value = e.latlng.lng.toFixed(6);
            draw(false);
        });
        draw(true);
    } else if (mapEl) {
        mapEl.hidden = true;
    }

    [lat, lng].forEach(function (input) {
        input.addEventListener('change', function () { draw(true); });
    });
    radius.addEventListener('input', function () { draw(true); });

    /* ---------------------------------------------------------------- Google Maps link */
    var PATTERNS = [
        /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/,
        /[?&](?:q|query|ll|destination|center)=(-?\d+(?:\.\d+)?)(?:,|%2C)\s*(-?\d+(?:\.\d+)?)/i,
        /@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
        /^(-?\d+(?:\.\d+)?)\s*[,\s]\s*(-?\d+(?:\.\d+)?)$/
    ];

    function parse(text) {
        for (var i = 0; i < PATTERNS.length; i++) {
            var m = text.match(PATTERNS[i]);
            if (m && Math.abs(m[1]) <= 90 && Math.abs(m[2]) <= 180) return [Number(m[1]), Number(m[2])];
        }
        return null;
    }

    var resolveBtn = form.querySelector('[data-resolve]');
    var resolveMsg = form.querySelector('[data-resolve-msg]');

    function apply(coords, note) {
        lat.value = Number(coords[0]).toFixed(6);
        lng.value = Number(coords[1]).toFixed(6);
        resolveMsg.textContent = note;
        draw(true);
    }

    resolveBtn.addEventListener('click', function () {
        var url = form.elements.maps_url.value.trim();
        if (!url) {
            resolveMsg.textContent = 'Paste a Google Maps link (or "latitude, longitude") first.';
            return;
        }
        var local = parse(url);
        if (local) return apply(local, 'Coordinates found — check the pin on the map, then save.');

        var body = new FormData();
        body.append('action', 'resolve');
        body.append('csrf', form.elements.csrf.value);
        body.append('maps_url', url);
        resolveBtn.disabled = true;
        resolveMsg.textContent = 'Opening the link…';
        fetch('location.php', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (d) {
                if (typeof d.latitude === 'number') apply([d.latitude, d.longitude], 'Coordinates found — check the pin on the map, then save.');
                else resolveMsg.textContent = d.error || 'No coordinates found in that link.';
            })
            .catch(function () {
                resolveMsg.textContent = 'Could not open that link. Type the coordinates instead.';
            })
            .then(function () { resolveBtn.disabled = false; });
    });

    /* ---------------------------------------------------------------- "Use my current location" */
    document.querySelectorAll('[data-my-location]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var names = btn.getAttribute('data-my-location').split(',');
            var target = btn.form;
            var original = btn.innerHTML;
            function done(text) {
                btn.textContent = text;
                setTimeout(function () {
                    btn.innerHTML = original;
                    btn.disabled = false;
                }, text ? 2500 : 0);
            }
            if (!navigator.geolocation || !window.isSecureContext) return done('Location needs https');
            btn.disabled = true;
            btn.textContent = 'Locating…';
            navigator.geolocation.getCurrentPosition(function (pos) {
                target.elements[names[0]].value = pos.coords.latitude.toFixed(6);
                target.elements[names[1]].value = pos.coords.longitude.toFixed(6);
                if (names[2]) target.elements[names[2]].value = Math.round(pos.coords.accuracy);
                if (target === form) draw(true);
                done('Done — ±' + Math.round(pos.coords.accuracy) + ' m');
            }, function (err) {
                done(err.code === 1 ? 'Permission denied' : 'Location unavailable');
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        });
    });
})();
