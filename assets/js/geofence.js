/* Diva Junction — location check on the landing page.
   Get Started → "Are you really on Diva Junction?" (the browser asks for the location)
   → Checking… → success or "Oh no, Diva!". The browser only reports its position;
   the server decides and grants the pass that opens the deals page. */
(function () {
    'use strict';

    var flow = document.querySelector('[data-flow]');
    if (!flow) return;

    var api = flow.getAttribute('data-api');
    var limit = Number(flow.getAttribute('data-accuracy-limit')) || 100;
    var ASK_MS = 1600;      // the question stays at least this long
    var CHECK_MS = 1200;    // "Checking…" stays at least this long
    var SETTLE_MS = 8000;   // after a rough first fix, wait this long for a sharper one
    var GIVE_UP_MS = 60000; // safety net; the browser's own timeout (20 s after permission) normally fires first
    var busy = false;
    var queue = Promise.resolve();

    function distance(m) {
        return m < 1000 ? Math.round(m) + ' m' : (m / 1000).toFixed(1).replace(/\.0$/, '') + ' km';
    }

    function show(screen, vars) {
        Object.keys(vars || {}).forEach(function (key) {
            flow.querySelectorAll('[data-var="' + key + '"]').forEach(function (el) {
                el.textContent = vars[key];
            });
        });
        flow.setAttribute('data-screen', screen);
        flow.querySelectorAll('[data-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-panel') !== screen;
        });
        var heading = flow.querySelector('[data-panel="' + screen + '"] [tabindex="-1"]') ||
            (screen === 'ask' && document.getElementById('ask-title'));
        if (heading) heading.focus({ preventScroll: true });
    }

    /** Show a screen once the previous one has been up for its minimum time. */
    function step(screen, vars, holdMs) {
        queue = queue.then(function () {
            show(screen, vars);
            return new Promise(function (resolve) { setTimeout(resolve, holdMs || 0); });
        });
    }

    function begin(withQuestion) {
        if (busy) return;
        if (!window.isSecureContext) return step('insecure');
        if (!('geolocation' in navigator)) return step('unsupported');
        busy = true;

        var checking = false;
        function toChecking() {
            if (!checking) {
                checking = true;
                step('checking', null, CHECK_MS);
            }
        }

        if (withQuestion) step('ask', null, ASK_MS);
        else toChecking();

        locate(toChecking, function (coords) {
            toChecking();
            send(coords);
        }, function (screen) {
            busy = false;
            step(screen);
        });
    }

    /** Watch the position until it is precise enough (or SETTLE_MS after the first fix). */
    function locate(onFirstFix, onDone, onFail) {
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
            if (!best) onFirstFix();
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
            if (best && !(err && err.code === 1)) return onDone(best.coords);
            onFail(!err || err.code === 3 ? 'timeout' : err.code === 1 ? 'denied' : 'unavailable');
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
                    step('success');
                } else if (res.status === 429 || d.reason === 'rate_limited') {
                    step('rate');
                } else if (d.reason === 'outside_radius') {
                    step('outside');
                } else if (d.reason === 'poor_accuracy') {
                    step('accuracy', { accuracy: distance(coords.accuracy), limit: distance(d.accuracy_limit_meters || limit) });
                } else {
                    step('error');
                }
            })
            .catch(function () {
                busy = false;
                step('error');
            });
    }

    flow.addEventListener('click', function (event) {
        if (event.target.closest('[data-action="start"]') && flow.hasAttribute('data-locked')) {
            event.preventDefault();
            begin(true);
        } else if (event.target.closest('[data-action="retry"]')) {
            event.preventDefault();
            begin(false);
        }
    });
})();
