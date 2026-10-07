<?php
/**
 * Location restriction ("geofence") for the public site.
 *
 * The browser only supplies its GPS position; every allow/deny decision is
 * made here, and a page is served only after this server approved the visitor.
 */

require_once __DIR__ . '/auth.php';

const EARTH_RADIUS_M = 6371000.0;

/** Visitor-facing explanation for each denial reason (also used in the admin logs). */
const GEO_REASONS = [
    'outside_radius'      => 'Outside the allowed area',
    'poor_accuracy'       => 'Location not precise enough',
    'invalid_coordinates' => 'Invalid coordinates',
    'not_configured'      => 'Location not configured',
];

/** Great-circle distance in metres (Haversine). */
function geo_distance(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $dPhi = $phi2 - $phi1;
    $dLambda = deg2rad($lng2 - $lng1);
    $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
    return EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/** A finite number from user input (int, float or numeric string), else null. */
function geo_number(mixed $value): ?float
{
    if (is_string($value)) {
        $value = trim($value);
    }
    if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
        return null;
    }
    $n = (float) $value;
    return is_finite($n) ? $n : null;
}

function geo_bool(mixed $value): bool
{
    return in_array(is_string($value) ? strtolower(trim($value)) : $value, [true, 1, '1', 'true', 'on', 'yes'], true);
}

function geo_valid_lat(?float $lat): bool
{
    return $lat !== null && $lat >= -90 && $lat <= 90;
}

function geo_valid_lng(?float $lng): bool
{
    return $lng !== null && $lng >= -180 && $lng <= 180;
}

/** Active configuration (one row). Throws when the database cannot be read. */
function geofence_config(bool $reload = false): array
{
    static $cfg = null;
    if ($cfg === null || $reload) {
        $row = db()->query('SELECT * FROM geofence_settings WHERE id = 1')->fetch();
        if (!$row) {
            throw new RuntimeException('Geofence settings are missing.');
        }
        $cfg = [
            'enabled'               => (bool) $row['enabled'],
            'location_name'         => (string) $row['location_name'],
            'latitude'              => $row['latitude'] === null ? null : (float) $row['latitude'],
            'longitude'             => $row['longitude'] === null ? null : (float) $row['longitude'],
            'radius_meters'         => (int) $row['radius_meters'],
            'accuracy_limit_meters' => (int) $row['accuracy_limit_meters'],
            'session_minutes'       => (int) $row['session_minutes'],
            'log_retention_days'    => (int) $row['log_retention_days'],
            'maps_url'              => (string) $row['maps_url'],
            'updated_by'            => $row['updated_by'] === null ? null : (int) $row['updated_by'],
            'updated_at'            => $row['updated_at'] === null ? null : (int) $row['updated_at'],
        ];
    }
    return $cfg;
}

/**
 * Decide whether a position is allowed under $cfg.
 *
 * Allowed when the distance to the centre is <= the radius. A fix whose accuracy
 * is worse than the configured limit is refused with "poor_accuracy" (the visitor
 * is asked to retry) — unless it is outside the radius even after allowing for
 * that error margin, which is a clear "outside_radius".
 *
 * @return array{allowed: bool, reason: ?string, distance_meters: ?float, radius_meters: int}
 */
function geofence_evaluate(array $cfg, ?float $lat, ?float $lng, ?float $accuracy): array
{
    $result = ['allowed' => false, 'reason' => null, 'distance_meters' => null, 'radius_meters' => (int) $cfg['radius_meters']];
    if (!$cfg['enabled']) {
        $result['allowed'] = true;
        return $result;
    }
    if (!geo_valid_lat($cfg['latitude']) || !geo_valid_lng($cfg['longitude'])) {
        $result['reason'] = 'not_configured'; // fail closed
        return $result;
    }
    if (!geo_valid_lat($lat) || !geo_valid_lng($lng) || ($accuracy !== null && $accuracy < 0)) {
        $result['reason'] = 'invalid_coordinates';
        return $result;
    }

    $distance = geo_distance($cfg['latitude'], $cfg['longitude'], $lat, $lng);
    $result['distance_meters'] = round($distance, 1);
    $radius = $result['radius_meters'];
    $error = $accuracy ?? INF;

    if ($error > $cfg['accuracy_limit_meters']) {
        $result['reason'] = $distance - $error > $radius ? 'outside_radius' : 'poor_accuracy';
    } elseif ($distance <= $radius) {
        $result['allowed'] = true;
    } else {
        $result['reason'] = 'outside_radius';
    }
    return $result;
}

/* ------------------------------------------------------------------ visitor pass */

/** Changes whenever the configuration is saved, so earlier passes stop working. */
function geofence_version(array $cfg): string
{
    return substr(hash('sha256', implode('|', [
        (int) $cfg['enabled'], $cfg['latitude'], $cfg['longitude'],
        $cfg['radius_meters'], $cfg['accuracy_limit_meters'], $cfg['updated_at'],
    ])), 0, 16);
}

function geofence_has_pass(array $cfg): bool
{
    start_session();
    $pass = $_SESSION['geo_pass'] ?? null;
    return is_array($pass)
        && hash_equals(geofence_version($cfg), (string) ($pass['v'] ?? ''))
        && (int) ($pass['exp'] ?? 0) > time();
}

function geofence_grant(array $cfg): void
{
    start_session();
    $_SESSION['geo_pass'] = ['v' => geofence_version($cfg), 'exp' => time() + max(1, $cfg['session_minutes']) * 60];
}

/**
 * Call at the top of every public page, before any output. When the restriction
 * is ON and this visitor has no valid pass, the location screen is shown instead.
 * Signed-in admins may preview the site from anywhere.
 */
function geofence_gate(): void
{
    try {
        $cfg = geofence_config();
        if (!$cfg['enabled']) {
            return;
        }
        header('Cache-Control: no-store, private');
        if (current_admin()) {
            $GLOBALS['geo_admin_preview'] = true;
            return;
        }
        if (geofence_has_pass($cfg)) {
            return;
        }
    } catch (Throwable $ex) {
        // Never fall back to an open site when the restriction state is unknown.
        error_log('Diva Junction geofence: ' . $ex->getMessage());
        http_response_code(503);
        header('Retry-After: 60');
        require __DIR__ . '/views/unavailable.php';
        exit;
    }

    http_response_code(403);
    require __DIR__ . '/views/location.php';
    exit;
}

/* ------------------------------------------------------------------ access log + rate limit */

function geo_ip_hash(): string
{
    return substr(hash_hmac('sha256', client_ip(), setting_raw('app_secret')), 0, 24);
}

/** True when this visitor (session or IP) made too many location checks recently. */
function geofence_rate_limited(): bool
{
    start_session();
    $since = time() - GEO_RATE_WINDOW;
    $recent = array_filter($_SESSION['geo_checks'] ?? [], fn($t) => is_int($t) && $t > $since);
    if (count($recent) >= GEO_RATE_LIMIT) {
        return true;
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM location_access_logs WHERE ip_hash = ? AND created_at > ?');
    $stmt->execute([geo_ip_hash(), $since]);
    if ((int) $stmt->fetchColumn() >= GEO_RATE_LIMIT_IP) {
        return true;
    }
    $recent[] = time();
    $_SESSION['geo_checks'] = array_values($recent);
    return false;
}

/** Store one check. Coordinates are rounded to 4 decimals (about 11 m). */
function geofence_log(array $result, ?float $lat, ?float $lng, ?float $accuracy): void
{
    start_session();
    db()->prepare('INSERT INTO location_access_logs
            (session_ref, latitude, longitude, accuracy, distance_meters, result, reason, ip_hash, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            substr(hash('sha256', session_id()), 0, 12),
            $lat === null ? null : round($lat, 4),
            $lng === null ? null : round($lng, 4),
            $accuracy === null ? null : round($accuracy, 1),
            $result['distance_meters'],
            $result['allowed'] ? 'allowed' : 'denied',
            (string) $result['reason'],
            geo_ip_hash(),
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            time(),
        ]);
}

/** Delete access logs older than the retention period. */
function geofence_purge_logs(array $cfg): void
{
    db()->prepare('DELETE FROM location_access_logs WHERE created_at < ?')
        ->execute([time() - max(1, $cfg['log_retention_days']) * 86400]);
}

/* ------------------------------------------------------------------ admin */

/** Field limits shared by the admin form, the admin API and the tests. */
const GEO_LIMITS = [
    'radius_meters'         => [10, 50000],
    'accuracy_limit_meters' => [5, 5000],
    'session_minutes'       => [1, 1440],
    'log_retention_days'    => [1, 365],
];

/**
 * Validate and store geofence settings. Keys missing from $input keep their value.
 * Returns field => message for every problem (nothing is saved then).
 */
function geofence_save(array $input, array $admin): array
{
    $cfg = geofence_config(true);
    $has = fn(string $key) => array_key_exists($key, $input);
    $errors = [];

    $new = $cfg;
    if ($has('enabled')) {
        $new['enabled'] = geo_bool($input['enabled']);
    }
    if ($has('location_name')) {
        $new['location_name'] = trim((string) $input['location_name']);
        if (mb_strlen($new['location_name']) > 120) {
            $errors['location_name'] = 'Location name: 120 characters at most.';
        }
    }
    foreach (['latitude' => 'geo_valid_lat', 'longitude' => 'geo_valid_lng'] as $key => $valid) {
        if (!$has($key)) {
            continue;
        }
        $raw = $input[$key];
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            $new[$key] = null;
            continue;
        }
        $n = geo_number($raw);
        if (!$valid($n)) {
            $errors[$key] = $key === 'latitude'
                ? 'Latitude must be a number between -90 and 90.'
                : 'Longitude must be a number between -180 and 180.';
            continue;
        }
        $new[$key] = round($n, 7);
    }
    foreach (GEO_LIMITS as $key => [$min, $max]) {
        if (!$has($key)) {
            continue;
        }
        $n = geo_number($input[$key]);
        if ($n === null || $n != floor($n) || $n < $min || $n > $max) {
            $errors[$key] = ucfirst(str_replace('_', ' ', $key)) . ": a whole number from $min to $max.";
            continue;
        }
        $new[$key] = (int) $n;
    }
    if ($has('maps_url')) {
        $new['maps_url'] = trim((string) $input['maps_url']);
        if ($new['maps_url'] !== '' && (!preg_match('#^https?://#i', $new['maps_url']) || strlen($new['maps_url']) > 2000)) {
            $errors['maps_url'] = 'Google Maps link must be a full https:// address.';
        }
    }
    if ($new['enabled'] && !isset($errors['latitude']) && !isset($errors['longitude'])
        && ($new['latitude'] === null || $new['longitude'] === null)) {
        $errors['enabled'] = 'Set the latitude and longitude before turning the restriction on.';
    }
    if ($errors) {
        return $errors;
    }

    $fields = ['enabled', 'location_name', 'latitude', 'longitude', 'radius_meters', 'accuracy_limit_meters', 'session_minutes', 'log_retention_days', 'maps_url'];
    $old = $changed = [];
    foreach ($fields as $key) {
        if ($cfg[$key] !== $new[$key]) {
            $old[$key] = $cfg[$key];
            $changed[$key] = $new[$key];
        }
    }
    if (!$changed) {
        return [];
    }

    db()->prepare('UPDATE geofence_settings SET enabled = ?, location_name = ?, latitude = ?, longitude = ?, radius_meters = ?,
            accuracy_limit_meters = ?, session_minutes = ?, log_retention_days = ?, maps_url = ?, updated_by = ?, updated_at = ? WHERE id = 1')
        ->execute([
            (int) $new['enabled'], $new['location_name'], $new['latitude'], $new['longitude'], $new['radius_meters'],
            $new['accuracy_limit_meters'], $new['session_minutes'], $new['log_retention_days'], $new['maps_url'],
            $admin['id'], time(),
        ]);
    $action = isset($changed['enabled']) ? ($new['enabled'] ? 'geofence.enabled' : 'geofence.disabled') : 'geofence.updated';
    audit_log($admin, $action, $old, $changed);
    geofence_config(true);
    return [];
}

/** Settings as returned by the admin API. */
function geofence_public_config(array $cfg): array
{
    $by = null;
    if ($cfg['updated_by']) {
        $stmt = db()->prepare('SELECT username FROM admins WHERE id = ?');
        $stmt->execute([$cfg['updated_by']]);
        $by = $stmt->fetchColumn() ?: null;
    }
    return array_merge($cfg, [
        'updated_by' => $by,
        'updated_at' => $cfg['updated_at'] ? date(DATE_ATOM, $cfg['updated_at']) : null,
    ]);
}

/**
 * Coordinates from a Google Maps link or a "lat, lng" pair. Short links
 * (maps.app.goo.gl) are followed to their full address first.
 *
 * @return array{0: float, 1: float}|null
 */
function maps_coordinates(string $text): ?array
{
    $text = trim($text);
    $patterns = [
        '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',            // the place pin
        '/[?&](?:q|query|ll|destination|center)=(-?\d+(?:\.\d+)?)(?:,|%2C)\s*(-?\d+(?:\.\d+)?)/i',
        '/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',                // map centre
        '/^(-?\d+(?:\.\d+)?)\s*[,\s]\s*(-?\d+(?:\.\d+)?)$/',     // plain "19.18, 73.04"
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $text, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (geo_valid_lat($lat) && geo_valid_lng($lng)) {
                return [$lat, $lng];
            }
        }
    }
    if (preg_match('#^https?://#i', $text) && ($full = maps_expand_link($text)) !== null && $full !== $text) {
        return maps_coordinates($full);
    }
    return null;
}

/** Follow a Google Maps short link (Google hosts only) and return where it points. */
function maps_expand_link(string $url): ?string
{
    for ($hop = 0; $hop < 5; $hop++) {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!preg_match('/^(maps\.app\.goo\.gl|goo\.gl|(www\.|maps\.)?google\.(com|co\.in))$/', $host)) {
            return $hop ? $url : null;
        }
        $headers = @get_headers($url, true, stream_context_create(['http' => [
            'method' => 'HEAD', 'follow_location' => 0, 'timeout' => 6,
            'header' => "User-Agent: Mozilla/5.0 (DivaJunction admin)\r\n",
        ]]));
        $next = $headers['Location'] ?? $headers['location'] ?? null;
        if (is_array($next)) {
            $next = end($next);
        }
        if (!$next) {
            return $url;
        }
        if (preg_match('/!3d-?\d|@-?\d|[?&](q|ll)=-?\d/', $next)) {
            return $next;
        }
        $url = str_starts_with($next, 'http') ? $next : 'https://' . $host . $next;
    }
    return $url;
}
