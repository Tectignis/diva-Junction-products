<?php
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/geofence.php';
$admin = require_admin();

/** Coordinate as typed in the form (no float noise). */
function coord(?float $value): string
{
    return $value === null ? '' : rtrim(rtrim(sprintf('%.7F', $value), '0'), '.');
}

$cfg = geofence_config(true);
$form = $cfg;
$form['latitude'] = coord($cfg['latitude']);
$form['longitude'] = coord($cfg['longitude']);
$errors = [];
$test = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    if ($action === 'resolve') {
        // AJAX helper for the "Find coordinates" button.
        header('Content-Type: application/json; charset=utf-8');
        $coords = maps_coordinates(post('maps_url'));
        echo json_encode($coords
            ? ['latitude' => $coords[0], 'longitude' => $coords[1]]
            : ['error' => 'No coordinates found in that link. In Google Maps, right-click the pin and click the numbers to copy them, then paste them here.']);
        exit;
    }

    if ($action === 'save') {
        $input = [
            'enabled'               => isset($_POST['enabled']) ? '1' : '0',
            'location_name'         => post('location_name'),
            'maps_url'              => post('maps_url'),
            'latitude'              => post('latitude'),
            'longitude'             => post('longitude'),
            'radius_meters'         => post('radius_meters'),
            'accuracy_limit_meters' => post('accuracy_limit_meters'),
            'session_minutes'       => post('session_minutes'),
            'log_retention_days'    => post('log_retention_days'),
        ];
        $errors = geofence_save($input, $admin);
        if (!$errors) {
            $cfg = geofence_config();
            flash($cfg['enabled']
                ? 'Saved. Location lock is ON — the site opens only within ' . format_distance($cfg['radius_meters']) . ' of ' . ($cfg['location_name'] ?: 'the pin') . '.'
                : 'Saved. Location lock is OFF — the site is open to everyone.');
            redirect('location.php');
        }
        $form = ['enabled' => $input['enabled'] === '1'] + $input + $form;
    }

    if ($action === 'test') {
        $test = [
            'latitude'  => post('test_latitude'),
            'longitude' => post('test_longitude'),
            'accuracy'  => post('test_accuracy'),
        ];
        $lat = geo_number($test['latitude']);
        $lng = geo_number($test['longitude']);
        $acc = $test['accuracy'] === '' ? 0.0 : geo_number($test['accuracy']);
        // Always test against the saved settings, as if the lock were ON.
        $test['result'] = geofence_evaluate(['enabled' => true] + $cfg, $lat, $lng, $acc);
    }
}

$since = time() - 86400;
$stats = db()->prepare("SELECT
        SUM(result = 'allowed') AS allowed,
        SUM(reason = 'outside_radius') AS outside,
        SUM(reason = 'poor_accuracy') AS accuracy,
        COUNT(DISTINCT session_ref) AS visitors
    FROM location_access_logs WHERE created_at > ?");
$stats->execute([$since]);
$stats = array_map('intval', $stats->fetch());

$updatedBy = geofence_public_config($cfg)['updated_by'];

admin_header('Location lock', 'location', $admin);
foreach ($errors as $err): ?>
<div class="flash flash-error" role="alert"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="geo-admin" id="geo-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <section class="card geo-switch-card<?= $form['enabled'] ? ' is-on' : '' ?>">
        <label class="geo-switch">
            <input type="checkbox" name="enabled" value="1"<?= $form['enabled'] ? ' checked' : '' ?> data-geo-toggle>
            <span class="geo-switch-track" aria-hidden="true"></span>
            <span class="geo-switch-text">
                <strong>Location restriction <span class="status-pill" data-on="ON" data-off="OFF"><?= $cfg['enabled'] ? 'ON' : 'OFF' ?></span></strong>
                <small data-on-text="Visitors must be within the radius below to open the site. The admin panel always works from anywhere."
                       data-off-text="Anyone, anywhere can open the site. Turn on to limit it to the area below."><?= $form['enabled']
                    ? 'Visitors must be within the radius below to open the site. The admin panel always works from anywhere.'
                    : 'Anyone, anywhere can open the site. Turn on to limit it to the area below.' ?></small>
            </span>
        </label>
        <?php if ($cfg['updated_at']): ?>
        <p class="muted small">Last changed <?= e(date('d M Y, H:i', $cfg['updated_at'])) ?><?= $updatedBy ? ' by ' . e($updatedBy) : '' ?>. Saving applies immediately; earlier visitor approvals stop working.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Allowed area</h2>
        <div class="grid-2">
            <?= field_text('location_name', 'Location name', $form['location_name'], ['maxlength' => 120, 'placeholder' => 'Diva Junction activation, Diva station', 'help' => 'Shown to visitors on the location screen.']) ?>
            <div class="field">
                <span class="label">Google Maps link (optional)</span>
                <div class="input-row">
                    <input type="url" name="maps_url" value="<?= e($form['maps_url']) ?>" placeholder="https://maps.app.goo.gl/…" data-maps-url>
                    <button type="button" class="btn" data-resolve><?= icon('target') ?>Find coordinates</button>
                </div>
                <small class="help" data-resolve-msg>Paste the client's link and click Find coordinates — or click the map, or type them below.</small>
            </div>
            <?= field_text('latitude', 'Latitude', $form['latitude'], ['placeholder' => '19.1863', 'help' => 'Between -90 and 90.']) ?>
            <?= field_text('longitude', 'Longitude', $form['longitude'], ['placeholder' => '73.0423', 'help' => 'Between -180 and 180.']) ?>
        </div>

        <div class="geo-map" id="geo-map" data-radius-input="radius_meters" aria-label="Map of the allowed area"></div>
        <div class="map-actions">
            <button type="button" class="btn btn-sm" data-my-location="latitude,longitude"><?= icon('pin') ?>Use my current location</button>
            <span class="muted small">Click the map to move the centre. The circle shows the allowed radius.</span>
        </div>

        <div class="grid-2">
            <?= field_text('radius_meters', 'Radius (metres)', (string) $form['radius_meters'], ['type' => 'number', 'min' => GEO_LIMITS['radius_meters'][0], 'max' => GEO_LIMITS['radius_meters'][1], 'step' => 1, 'help' => 'Default 500 m. Visitors exactly on the edge are allowed.']) ?>
            <?= field_text('accuracy_limit_meters', 'GPS accuracy needed (metres)', (string) $form['accuracy_limit_meters'], ['type' => 'number', 'min' => GEO_LIMITS['accuracy_limit_meters'][0], 'max' => GEO_LIMITS['accuracy_limit_meters'][1], 'step' => 1, 'help' => 'Fixes less precise than this are asked to retry. Phones outdoors: 5–30 m; indoors: 20–100 m.']) ?>
            <?= field_text('session_minutes', 'Re-check visitors after (minutes)', (string) $form['session_minutes'], ['type' => 'number', 'min' => GEO_LIMITS['session_minutes'][0], 'max' => GEO_LIMITS['session_minutes'][1], 'step' => 1, 'help' => 'How long one successful check keeps the site open for that visitor.']) ?>
            <?= field_text('log_retention_days', 'Keep location checks for (days)', (string) $form['log_retention_days'], ['type' => 'number', 'min' => GEO_LIMITS['log_retention_days'][0], 'max' => GEO_LIMITS['log_retention_days'][1], 'step' => 1, 'help' => 'Older records are deleted automatically. Coordinates are stored rounded to about 11 m.']) ?>
        </div>
    </section>

    <div class="savebar">
        <span class="muted">Changes apply as soon as you save.</span>
        <button type="submit" class="btn btn-primary">Save settings</button>
    </div>
</form>

<section class="card" id="test">
    <h2>Test a location</h2>
    <p class="muted">Check any coordinate against the <strong>saved</strong> settings (as if the lock were ON). Nothing is logged.</p>
    <form method="post" action="location.php#test">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="test">
        <div class="grid-3">
            <?= field_text('test_latitude', 'Latitude', $test['latitude'] ?? '', ['required' => true]) ?>
            <?= field_text('test_longitude', 'Longitude', $test['longitude'] ?? '', ['required' => true]) ?>
            <?= field_text('test_accuracy', 'Accuracy (m, optional)', $test['accuracy'] ?? '', ['placeholder' => '0']) ?>
        </div>
        <div class="form-actions start">
            <button type="button" class="btn" data-my-location="test_latitude,test_longitude,test_accuracy"><?= icon('pin') ?>Use my current location</button>
            <button type="submit" class="btn btn-primary">Test</button>
        </div>
    </form>
    <?php if ($test):
        $r = $test['result']; ?>
    <div class="test-result <?= $r['allowed'] ? 'is-ok' : 'is-bad' ?>" role="status">
        <strong><?= $r['allowed'] ? 'Allowed' : 'Blocked' ?></strong>
        <span>
            <?php if ($r['distance_meters'] !== null): ?>
            <?= e(format_distance($r['distance_meters'])) ?> from the centre (radius <?= e(format_distance($r['radius_meters'])) ?>).
            <?php endif; ?>
            <?= $r['reason'] ? e(GEO_REASONS[$r['reason']] ?? $r['reason']) . '.' : '' ?>
        </span>
    </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Last 24 hours</h2>
    <div class="mini-stats">
        <div><strong><?= $stats['visitors'] ?></strong><span>visitors checked</span></div>
        <div><strong><?= $stats['allowed'] ?></strong><span>allowed</span></div>
        <div><strong><?= $stats['outside'] ?></strong><span>outside the area</span></div>
        <div><strong><?= $stats['accuracy'] ?></strong><span>asked for better GPS</span></div>
    </div>
    <p><a href="logs.php">See all location checks and admin changes →</a></p>
</section>

<link rel="stylesheet" href="<?= e(asset('assets/vendor/leaflet/leaflet.css')) ?>">
<script src="<?= e(asset('assets/vendor/leaflet/leaflet.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/admin-location.js')) ?>" defer></script>
<?php admin_footer(); ?>
