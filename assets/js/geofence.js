/* Diva Junction — location check. The browser only reports its position; the server decides. */
(function () {
    'use strict';

    var root = document.querySelector('[data-geo]');
    if (!root) return;

    var api = root.getAttribute('data-api');
    var limit = Number(root.getAttribute('data-accuracy-limit')) || 100;
    var SETTLE_MS = 8000;   // after a rough first fix, wait this long for a sharper one
    var GIVE_UP_MS = 25000; // no usable fix at all by then → timeout
    var busy = false;

    function show(state, vars) {
        Object.keys(vars || {}).forEach(function (key) {
            root.querySelectorAll('[data-var="' + key + '"]').forEach(function (el) {
                el.textContent = vars[key];
            });
        });
        root.querySelectorAll('[data-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-panel') !== state;
        });
        root.setAttribute('data-state', state);
    }

    function distance(m) {
        return m < 1000 ? Math.round(m) + ' m' : (m / 1000).toFixed(1).replace(/\.0$/, '') + ' km';
    }

    function locate() {
        if (busy) return;
        if (!window.isSecureContext) return show('insecure');
        if (!('geolocation' in navigator)) return show('unsupported');

        busy = true;
        show('locating');

        var best = null;
        var finished = false;
        var settleTimer = null;
        var giveUpTimer = setTimeout(function () { finish({ code: 3 }); }, GIVE_UP_MS);
        var watchId = navigator.geolocation.watchPosition(onPosition, onError, {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 20000
        });

        function onPosition(pos) {
            if (!best || pos.coords.accuracy < best.coords.accuracy) best = pos;
            if (best.coords.accuracy <= limit) return finish();
            if (!settleTimer) settleTimer = setTimeout(finish, SETTLE_MS);
        }

        function onError(err) {
            // With a fix in hand, ignore later hiccups and let the settle timer send it.
            if (err.code === 1 || !best) finish(err);
        }

        function finish(err) {
            if (finished) return;
            finished = true;
            navigator.geolocation.clearWatch(watchId);
            clearTimeout(settleTimer);
            clearTimeout(giveUpTimer);
            if (best && !(err && err.code === 1)) return send(best.coords);
            busy = false;
            show(!err || err.code === 3 ? 'timeout' : err.code === 1 ? 'denied' : 'unavailable');
        }
    }

    function send(coords) {
        fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ latitude: coords.latitude, longitude: coords.longitude, accuracy: coords.accuracy })
        })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    return { status: res.status, data: data };
                });
            })
            .then(function (res) {
                busy = false;
                var d = res.data;
                if (d.allowed) {
                    show('granted');
                    window.location.reload();
                } else if (res.status === 429 || d.reason === 'rate_limited') {
                    show('rate');
                } else if (d.reason === 'outside_radius') {
                    show('outside', { distance: distance(d.distance_meters) });
                } else if (d.reason === 'poor_accuracy') {
                    show('accuracy', { accuracy: distance(coords.accuracy), limit: distance(d.accuracy_limit_meters || limit) });
                } else {
                    show('error');
                }
            })
            .catch(function () {
                busy = false;
                show('error');
            });
    }

    root.addEventListener('click', function (event) {
        if (event.target.closest('[data-action="locate"]')) locate();
    });

    // Permission already granted (e.g. a returning visitor): check straight away.
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then(function (status) {
            if (status.state === 'granted') locate();
            else if (status.state === 'denied') show('denied');
        }).catch(function () { /* not supported — wait for the button */ });
    }
})();
