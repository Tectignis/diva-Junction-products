<?php
/**
 * Location check screen, shown by geofence_gate() instead of a public page.
 * @var array $cfg active geofence configuration
 */

$pageTitle = setting('geo_title') . ' · ' . setting('site_title');
$bodyClass = 'geo';
$bodyStyle = "--landing-bg: url('" . asset(setting('landing_bg')) . "')";
$radius    = format_distance($cfg['radius_meters']);
$place     = $cfg['location_name'] !== '' ? $cfg['location_name'] : 'Diva Junction';
$support   = setting_raw('support_link') !== '' ? safe_link(setting_raw('support_link')) : '';

/** Try again + support buttons shown under every problem message. */
$actions = function (string $retry = 'Try Again') use ($support): string {
    $html = '<div class="geo-actions"><button type="button" class="geo-btn" data-action="locate">' . e($retry) . '</button>';
    if ($support !== '') {
        $html .= '<a class="geo-btn geo-btn-ghost" href="' . e($support) . '"' . (is_external($support) ? ' target="_blank" rel="noopener"' : '') . '>'
            . e(setting('support_text')) . '</a>';
    }
    return $html . '</div>';
};

require __DIR__ . '/../head.php';
?>
<main class="geo-screen" data-geo data-api="<?= e(base_url('api/geofence/validate')) ?>" data-accuracy-limit="<?= (int) $cfg['accuracy_limit_meters'] ?>">
    <div class="geo-card">
        <img class="geo-logo" src="<?= e(asset(setting('logo'))) ?>" alt="Diva Junction">

        <div class="geo-panels" aria-live="polite">
            <section class="geo-panel" data-panel="intro">
                <span class="geo-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 22s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12z"/><circle cx="12" cy="10" r="2.6"/></svg></span>
                <h1><?= e(setting('geo_title')) ?></h1>
                <p><?= e_lines(setting('geo_message')) ?></p>
                <p class="geo-hint">The deals open when you are within <strong><?= e($radius) ?></strong> of <strong><?= e($place) ?></strong>.</p>
                <div class="geo-actions">
                    <button type="button" class="geo-btn" data-action="locate">Enable Location</button>
                </div>
                <noscript><p class="geo-note">Please turn on JavaScript in your browser to check your location.</p></noscript>
            </section>

            <section class="geo-panel" data-panel="locating" hidden>
                <span class="geo-spinner" aria-hidden="true"></span>
                <h2>Checking your location…</h2>
                <p>This takes a few seconds. If your browser asks, tap <strong>Allow</strong>.</p>
            </section>

            <section class="geo-panel" data-panel="granted" hidden>
                <span class="geo-icon is-ok" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg></span>
                <h2>You're in!</h2>
                <p>Opening the deals…</p>
            </section>

            <section class="geo-panel" data-panel="denied" hidden>
                <span class="geo-icon is-warn" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 22s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12z"/><path d="M4 4l16 16"/></svg></span>
                <h2>Location permission is off</h2>
                <p>We need your location to open the deals. Allow it, then tap Try Again:</p>
                <ul class="geo-steps">
                    <li><strong>Android / Chrome:</strong> tap the icon left of the address bar → Permissions → Location → Allow.</li>
                    <li><strong>iPhone / Safari:</strong> Settings → Privacy &amp; Security → Location Services → Safari Websites → While Using the App. Then tap <em>aA</em> in the address bar → Website Settings → Location → Allow.</li>
                </ul>
                <?= $actions() ?>
            </section>

            <section class="geo-panel" data-panel="unavailable" hidden>
                <span class="geo-icon is-warn" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></svg></span>
                <h2>We couldn't find your location</h2>
                <p>Turn on Location (GPS) on your phone, then try again.</p>
                <?= $actions() ?>
            </section>

            <section class="geo-panel" data-panel="timeout" hidden>
                <span class="geo-icon is-warn" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                <h2>Finding your location took too long</h2>
                <p>Step closer to an open area or a window, check that Location is on, and try again.</p>
                <?= $actions() ?>
            </section>

            <section class="geo-panel" data-panel="accuracy" hidden>
                <span class="geo-icon is-warn" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 1v3M12 20v3M1 12h3M20 12h3"/></svg></span>
                <h2>We need a more precise location</h2>
                <p>Your location is only accurate to about <strong data-var="accuracy"></strong>; we need <strong data-var="limit"></strong> or better. Turn on <em>Precise location</em> and Wi-Fi, then try again.</p>
                <?= $actions() ?>
            </section>

            <section class="geo-panel" data-panel="outside" hidden>
                <span class="geo-icon is-warn" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 22s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12z"/><circle cx="12" cy="10" r="2.6"/></svg></span>
                <h2>You're outside the Diva Junction zone</h2>
                <p>You seem to be about <strong data-var="distance"></strong> away. This site opens only within <strong><?= e($radius) ?></strong> of <strong><?= e($place) ?></strong>.</p>
                <?= $actions() ?>
            </section>

            <section class="geo-panel" data-panel="rate" hidden>
                <span class="geo-icon is-warn" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                <h2>Too many attempts</h2>
                <p>Please wait a few minutes, then try again.</p>
                <?= $actions() ?>
            </section>

            <section class="geo-panel" data-panel="error" hidden>
                <span class="geo-icon is-warn" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></svg></span>
                <h2>Something went wrong</h2>
                <p>We couldn't check your location just now. Please check your connection and try again.</p>
                <?= $actions() ?>
            </section>

            <section class="geo-panel" data-panel="insecure" hidden>
                <span class="geo-icon is-warn" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span>
                <h2>Secure connection needed</h2>
                <p>Browsers share location only over a secure (https://) connection. Please open the https:// address of this site.</p>
            </section>

            <section class="geo-panel" data-panel="unsupported" hidden>
                <span class="geo-icon is-warn" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></svg></span>
                <h2>This browser can't share your location</h2>
                <p>Please open this page in Chrome or Safari on your phone.</p>
                <?= $actions() ?>
            </section>
        </div>

        <p class="geo-privacy"><?= e(setting('geo_privacy')) ?> Rounded check records are deleted after <?= (int) $cfg['log_retention_days'] ?> day<?= $cfg['log_retention_days'] == 1 ? '' : 's' ?>.</p>
    </div>
</main>
<script src="<?= e(asset('assets/js/geofence.js')) ?>" defer></script>
</body>
</html>
