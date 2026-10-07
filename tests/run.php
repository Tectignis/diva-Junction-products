<?php
/**
 * Automated checks for the distance and authorization logic.
 *
 *   php tests/run.php                                   unit tests (temporary database)
 *   php tests/run.php --http=http://localhost/diva/     + black-box checks against a running site
 *
 * Exit code 0 = all passed.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

// Windows keeps the open database locked until exit, so tidy up earlier runs first.
array_map('unlink', glob(sys_get_temp_dir() . '/diva-test-*.sqlite*') ?: []);
define('DB_FILE', sys_get_temp_dir() . '/diva-test-' . getmypid() . '.sqlite');
$_SERVER['SCRIPT_NAME'] = '/tests/run.php';
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');

require __DIR__ . '/../inc/geofence.php';

start_session(); // before any output

$passed = 0;
$failed = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo '.';
    } else {
        $failed[] = $name . ($detail !== '' ? " — $detail" : '');
        echo 'F';
    }
}

function near(?float $a, float $b, float $tolerance): bool
{
    return $a !== null && abs($a - $b) <= $tolerance;
}

/** A point $meters due north of ($lat, $lng). */
function north(float $lat, float $lng, float $meters): array
{
    return [$lat + rad2deg($meters / EARTH_RADIUS_M), $lng];
}

/* ===================================================================== distance */

check('distance: same point is 0', geo_distance(19.188, 73.043, 19.188, 73.043) == 0.0);
check('distance: 1° of latitude ≈ 111.195 km', near(geo_distance(0, 0, 1, 0), 111194.9, 1));
check('distance: London → Paris ≈ 343.6 km', near(geo_distance(51.5074, -0.1278, 48.8566, 2.3522), 343556, 1000));
check('distance: symmetric', geo_distance(19.1, 73.0, 19.3, 72.8) === geo_distance(19.3, 72.8, 19.1, 73.0));
check('distance: across the 180° meridian', near(geo_distance(0, 179.9, 0, -179.9), 22239, 5));
check('distance: 500 m north measures 500 m', near(geo_distance(19.188, 73.043, ...north(19.188, 73.043, 500)), 500, 0.01));

/* ===================================================================== input parsing */

check('number: numeric string', geo_number(' 19.5 ') === 19.5);
check('number: int', geo_number(73) === 73.0);
check('number: rejects text', geo_number('abc') === null);
check('number: rejects arrays', geo_number([1]) === null);
check('number: rejects bool', geo_number(true) === null);
check('number: rejects INF', geo_number(INF) === null);
check('lat range', geo_valid_lat(90.0) && geo_valid_lat(-90.0) && !geo_valid_lat(90.0001) && !geo_valid_lat(null));
check('lng range', geo_valid_lng(180.0) && geo_valid_lng(-180.0) && !geo_valid_lng(-180.5));
check('bool parsing', geo_bool('1') && geo_bool(true) && geo_bool('on') && !geo_bool('0') && !geo_bool(false) && !geo_bool(''));

/* ===================================================================== decision */

$cfg = [
    'enabled' => true, 'latitude' => 19.188, 'longitude' => 73.043,
    'radius_meters' => 500, 'accuracy_limit_meters' => 100,
];
$eval = fn(array $c, ?float $lat, ?float $lng, ?float $acc) => geofence_evaluate($c, $lat, $lng, $acc);

$r = $eval(['enabled' => false] + $cfg, 0.0, 0.0, 9999.0);
check('restriction OFF → allowed from anywhere', $r['allowed'] === true && $r['reason'] === null);

$r = $eval($cfg, ...[...north(19.188, 73.043, 120), 20.0]);
check('inside 500 m → allowed', $r['allowed'] && near($r['distance_meters'], 120, 0.2));

$r = $eval($cfg, ...[...north(19.188, 73.043, 499.9), 10.0]);
check('just inside the boundary (499.9 m) → allowed', $r['allowed'] === true, json_encode($r));

$r = $eval($cfg, ...[...north(19.188, 73.043, 500.1), 10.0]);
check('just outside the boundary (500.1 m) → outside_radius', !$r['allowed'] && $r['reason'] === 'outside_radius', json_encode($r));

$exact = north(19.188, 73.043, 500);
$r = $eval(['radius_meters' => (int) ceil(geo_distance(19.188, 73.043, ...$exact))] + $cfg, $exact[0], $exact[1], 5.0);
check('distance equal to the radius → allowed (<=)', $r['allowed'] === true, json_encode($r));

$r = $eval($cfg, 19.0760, 72.8777, 20.0);
check('far away → outside_radius', !$r['allowed'] && $r['reason'] === 'outside_radius' && $r['distance_meters'] > 20000);

$r = $eval($cfg, ...[...north(19.188, 73.043, 100), 350.0]);
check('inside but poor accuracy → poor_accuracy', !$r['allowed'] && $r['reason'] === 'poor_accuracy');

$r = $eval($cfg, ...[...north(19.188, 73.043, 5000), 1000.0]);
check('poor accuracy but clearly outside → outside_radius', !$r['allowed'] && $r['reason'] === 'outside_radius');

$r = $eval($cfg, ...[...north(19.188, 73.043, 100), null]);
check('missing accuracy → poor_accuracy (no decision)', !$r['allowed'] && $r['reason'] === 'poor_accuracy');

$r = $eval($cfg, ...[...north(19.188, 73.043, 100), 100.0]);
check('accuracy exactly at the limit → allowed', $r['allowed'] === true);

$r = $eval($cfg, 91.0, 73.0, 10.0);
check('invalid latitude → invalid_coordinates', !$r['allowed'] && $r['reason'] === 'invalid_coordinates');

$r = $eval($cfg, 19.188, 73.043, -5.0);
check('negative accuracy → invalid_coordinates', !$r['allowed'] && $r['reason'] === 'invalid_coordinates');

$r = $eval(['latitude' => null] + $cfg, 19.188, 73.043, 5.0);
check('centre not configured → blocked (fails closed)', !$r['allowed'] && $r['reason'] === 'not_configured');

/* ===================================================================== Google Maps links */

check('maps: pin (!3d!4d) wins over map centre', maps_coordinates('https://www.google.com/maps/place/X/@19.18,73.03,17z/data=!3m1!4b1!8m2!3d19.18827!4d73.04254') === [19.18827, 73.04254]);
check('maps: @lat,lng', maps_coordinates('https://www.google.com/maps/@19.1863,73.0423,15z') === [19.1863, 73.0423]);
check('maps: ?q=lat,lng', maps_coordinates('https://maps.google.com/?q=19.1863,73.0423') === [19.1863, 73.0423]);
check('maps: plain "lat, lng"', maps_coordinates(' 19.1863, 73.0423 ') === [19.1863, 73.0423]);
check('maps: out-of-range pair is ignored', maps_coordinates('120.5, 73.0') === null);
check('maps: non-Google link is never fetched', maps_coordinates('https://example.com/no-coordinates') === null);

/* ===================================================================== settings (admin) */

$admin = ['id' => 1, 'username' => 'admin'];
$base = ['location_name' => 'Test', 'latitude' => '', 'longitude' => '', 'radius_meters' => '500',
    'accuracy_limit_meters' => '100', 'session_minutes' => '30', 'log_retention_days' => '30', 'maps_url' => ''];
$auditCount = fn() => (int) db()->query('SELECT COUNT(*) FROM admin_audit_logs')->fetchColumn();

check('defaults: OFF with a 500 m radius', geofence_config(true)['enabled'] === false && geofence_config()['radius_meters'] === 500);

$errors = geofence_save(['enabled' => '1'] + $base, $admin);
check('save: cannot turn ON without coordinates', isset($errors['enabled']));

$errors = geofence_save(['latitude' => '95', 'longitude' => 'east'] + $base, $admin);
check('save: rejects invalid coordinates', isset($errors['latitude'], $errors['longitude']));

$errors = geofence_save(['radius_meters' => '5'] + $base, $admin);
check('save: rejects a radius below 10 m', isset($errors['radius_meters']));

$errors = geofence_save(['radius_meters' => '500.5'] + $base, $admin);
check('save: rejects a fractional radius', isset($errors['radius_meters']));

$errors = geofence_save(['maps_url' => 'javascript:alert(1)'] + $base, $admin);
check('save: rejects a non-http Maps link', isset($errors['maps_url']));
check('save: nothing stored after errors', geofence_config(true)['location_name'] === '' && $auditCount() === 0);

$errors = geofence_save(['enabled' => '1', 'latitude' => '19.1880', 'longitude' => '73.0430'] + $base, $admin);
$saved = geofence_config(true);
check('save: valid settings are stored', !$errors && $saved['enabled'] && $saved['latitude'] === 19.188 && $saved['radius_meters'] === 500, json_encode($errors));
$audit = db()->query('SELECT * FROM admin_audit_logs ORDER BY id DESC LIMIT 1')->fetch();
check('audit: change recorded with admin, old and new values', $audit && $audit['action'] === 'geofence.enabled' && $audit['admin_name'] === 'admin'
    && json_decode($audit['old_value'], true)['enabled'] === false && json_decode($audit['new_value'], true)['enabled'] === true
    && $audit['ip_address'] === '203.0.113.7');

$before = $auditCount();
geofence_save(['enabled' => '1', 'latitude' => '19.1880', 'longitude' => '73.0430'] + $base, $admin);
check('audit: saving unchanged settings adds no entry', $auditCount() === $before);

/* ===================================================================== visitor pass */

$_SESSION = [];
check('pass: none by default', !geofence_has_pass($saved));
geofence_grant($saved);
check('pass: granted after a successful check', geofence_has_pass($saved));

sleep(1); // updated_at has 1 s resolution
geofence_save(['radius_meters' => '600'] + ['enabled' => '1', 'latitude' => '19.1880', 'longitude' => '73.0430'] + $base, $admin);
$changed = geofence_config(true);
check('pass: radius change → new radius is used', $changed['radius_meters'] === 600);
check('pass: any settings change invalidates earlier passes', !geofence_has_pass($changed));

geofence_grant($changed);
$_SESSION['geo_pass']['exp'] = time() - 1;
check('pass: expires after the re-check period', !geofence_has_pass($changed));

geofence_grant($changed); // a visitor approved while ON
$pin = ['latitude' => '19.1880', 'longitude' => '73.0430', 'radius_meters' => '600'];
sleep(1);
geofence_save(['enabled' => '0'] + $pin + $base, $admin);
$off = geofence_config(true);
sleep(1);
geofence_save(['enabled' => '1'] + $pin + $base, $admin);
$on = geofence_config(true);
check('OFF → ON: restriction restored, earlier approvals no longer count',
    !$off['enabled'] && $on['enabled'] && geofence_version($off) !== geofence_version($on) && !geofence_has_pass($on));

/* ===================================================================== logging, retention, rate limit */

$_SESSION = [];
geofence_log(geofence_evaluate($on, 19.188123456, 73.043987654, 12.34), 19.188123456, 73.043987654, 12.34);
$row = db()->query('SELECT * FROM location_access_logs ORDER BY id DESC LIMIT 1')->fetch();
check('log: coordinates rounded to 4 decimals', (float) $row['latitude'] === 19.1881 && (float) $row['longitude'] === 73.044);
check('log: IP stored only as a hash', $row['ip_hash'] !== '' && !str_contains($row['ip_hash'], '203.0.113.7') && strlen($row['ip_hash']) === 24);

db()->prepare('INSERT INTO location_access_logs (result, created_at) VALUES (?, ?)')->execute(['denied', time() - 31 * 86400]);
geofence_purge_logs(['log_retention_days' => 30]);
check('retention: old checks deleted, recent kept',
    (int) db()->query('SELECT COUNT(*) FROM location_access_logs WHERE created_at < ' . (time() - 30 * 86400))->fetchColumn() === 0
    && (int) db()->query('SELECT COUNT(*) FROM location_access_logs')->fetchColumn() === 1);

$_SESSION = [];
$limited = false;
for ($i = 0; $i < GEO_RATE_LIMIT; $i++) {
    $limited = $limited || geofence_rate_limited();
}
check('rate limit: ' . GEO_RATE_LIMIT . ' checks per session are fine', !$limited);
check('rate limit: the next one is refused', geofence_rate_limited());

$_SESSION = [];
$ins = db()->prepare('INSERT INTO location_access_logs (result, ip_hash, created_at) VALUES (?, ?, ?)');
for ($i = 0; $i < GEO_RATE_LIMIT_IP; $i++) {
    $ins->execute(['denied', geo_ip_hash(), time()]);
}
check('rate limit: per IP across sessions', geofence_rate_limited());

/* ===================================================================== HTTP (optional) */

$http = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--http=')) {
        $http = rtrim(substr($arg, 7), '/') . '/';
    }
}

function request(string $method, string $url, ?string $body = null, array $headers = []): array
{
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'content' => $body ?? '', 'ignore_errors' => true,
        'follow_location' => 0, 'header' => implode("\r\n", $headers), 'timeout' => 10,
    ]]);
    $content = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m)) {
            $status = (int) $m[1];
        }
    }
    return [$status, (string) $content, $http_response_header ?? []];
}

if ($http) {
    [$s, $body] = request('GET', $http . 'api/geofence/status');
    check('http: GET status → 200 JSON', $s === 200 && array_key_exists('enabled', (array) json_decode($body, true)), "$s $body");
    $locked = (bool) (json_decode($body, true)['enabled'] ?? false);
    [$s] = request('GET', $http);
    check('http: landing page always opens → 200', $s === 200, (string) $s);
    [$s, $page, $h] = request('GET', $http . 'shop.php');
    check($locked ? 'http: deals page without a pass → landing page, nothing of it sent' : 'http: deals page opens while the lock is OFF',
        $locked ? $s === 302 && (bool) preg_grep('#^Location: .*index\.php#i', $h) && !str_contains($page, 'store-section') : $s === 200,
        (string) $s);
    [$s] = request('GET', $http . 'api/admin/geofence');
    check('http: admin settings without login → 401', $s === 401, (string) $s);
    [$s] = request('PUT', $http . 'api/admin/geofence', '{"enabled":false}', ['Content-Type: application/json']);
    check('http: admin update without login → 401', $s === 401, (string) $s);
    [$s] = request('GET', $http . 'api/admin/geofence/logs');
    check('http: admin logs without login → 401', $s === 401, (string) $s);
    [$s] = request('POST', $http . 'api/geofence/validate', 'latitude=1&longitude=1&accuracy=1', ['Content-Type: application/x-www-form-urlencoded']);
    check('http: validate rejects form posts → 415', $s === 415, (string) $s);
    [$s] = request('GET', $http . 'api/geofence/validate');
    check('http: validate needs POST → 405', $s === 405, (string) $s);
    [$s, , $h] = request('GET', $http . 'admin/location.php');
    check('http: admin page without login → redirect to sign-in', $s === 302 && (bool) preg_grep('#^Location: .*login\.php#i', $h), (string) $s);
    [$s] = request('GET', $http . 'data/diva.sqlite');
    check('http: database file not downloadable', $s === 403 || $s === 404, (string) $s);
    [$s] = request('GET', $http . 'inc/geofence.php');
    check('http: inc/ not reachable', $s === 403 || $s === 404, (string) $s);
}

/* ===================================================================== result */

echo "\n\n$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) {
    echo "  ✗ $f\n";
}
@unlink(DB_FILE);
exit($failed ? 1 : 0);
