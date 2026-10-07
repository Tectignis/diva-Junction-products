<?php
/**
 * JSON API.
 *
 *   GET  api/geofence/status        public    restriction state for this visitor
 *   POST api/geofence/validate      public    check a position (rate-limited)
 *   GET  api/admin/geofence         admin     current settings
 *   PUT  api/admin/geofence         admin     update settings (X-CSRF-Token header)
 *   GET  api/admin/geofence/logs    admin     recent access and audit logs
 *
 * Pretty URLs come from api/.htaccess; without mod_rewrite use api/index.php?route=geofence/status.
 */

require_once __DIR__ . '/../inc/geofence.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function json_out(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

/** JSON request body; only application/json is accepted (blocks cross-site form posts). */
function json_body(): array
{
    if (!str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        json_out(415, ['error' => 'unsupported_media_type', 'message' => 'Send a JSON body (Content-Type: application/json).']);
    }
    $data = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($data)) {
        json_out(400, ['error' => 'invalid_json']);
    }
    return $data;
}

function allow_methods(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        json_out(405, ['error' => 'method_not_allowed']);
    }
}

function api_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        json_out(401, ['error' => 'unauthenticated', 'message' => 'Sign in to the admin panel first.']);
    }
    return $admin;
}

$route = trim((string) ($_GET['route'] ?? $_SERVER['PATH_INFO'] ?? ''), '/');

try {
    switch ($route) {
        case 'geofence/status':
            allow_methods('GET');
            $cfg = geofence_config();
            json_out(200, [
                'enabled'               => $cfg['enabled'],
                'location_name'         => $cfg['location_name'],
                'radius_meters'         => $cfg['radius_meters'],
                'accuracy_limit_meters' => $cfg['accuracy_limit_meters'],
                'access_granted'        => !$cfg['enabled'] || geofence_has_pass($cfg),
            ]);

        case 'geofence/validate':
            allow_methods('POST');
            $body = json_body();
            $cfg = geofence_config();
            if (!$cfg['enabled']) {
                json_out(200, ['allowed' => true, 'restriction' => 'off', 'radius_meters' => $cfg['radius_meters']]);
            }
            if (geofence_rate_limited()) {
                header('Retry-After: ' . GEO_RATE_WINDOW);
                json_out(429, ['allowed' => false, 'reason' => 'rate_limited']);
            }

            $lat = geo_number($body['latitude'] ?? null);
            $lng = geo_number($body['longitude'] ?? null);
            $accuracy = geo_number($body['accuracy'] ?? null);
            $valid = geo_valid_lat($lat) && geo_valid_lng($lng) && $accuracy !== null && $accuracy >= 0 && $accuracy <= 100000;

            $result = $valid
                ? geofence_evaluate($cfg, $lat, $lng, $accuracy)
                : ['allowed' => false, 'reason' => 'invalid_coordinates', 'distance_meters' => null, 'radius_meters' => $cfg['radius_meters']];
            geofence_log($result, $valid ? $lat : null, $valid ? $lng : null, $valid ? $accuracy : null);
            geofence_purge_logs($cfg);

            if ($result['allowed']) {
                geofence_grant($cfg);
                json_out(200, ['allowed' => true, 'distance_meters' => $result['distance_meters'], 'radius_meters' => $result['radius_meters']]);
            }
            json_out($valid ? 200 : 422, [
                'allowed'               => false,
                'reason'                => $result['reason'],
                'distance_meters'       => $result['distance_meters'],
                'radius_meters'         => $result['radius_meters'],
                'accuracy_limit_meters' => $cfg['accuracy_limit_meters'],
            ]);

        case 'admin/geofence':
            allow_methods('GET', 'PUT');
            $admin = api_admin();
            if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
                if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
                    json_out(403, ['error' => 'csrf_mismatch', 'message' => 'Send the admin CSRF token in the X-CSRF-Token header.']);
                }
                $errors = geofence_save(json_body(), $admin);
                if ($errors) {
                    json_out(422, ['error' => 'validation_failed', 'errors' => $errors]);
                }
            }
            json_out(200, ['geofence' => geofence_public_config(geofence_config(true))]);

        case 'admin/geofence/logs':
            allow_methods('GET');
            api_admin();
            $limit = min(500, max(1, (int) ($_GET['limit'] ?? 100)));
            $access = db()->query("SELECT id, session_ref, latitude, longitude, accuracy, distance_meters, result, reason, ip_hash, user_agent, created_at
                FROM location_access_logs ORDER BY id DESC LIMIT $limit")->fetchAll();
            $audit = db()->query("SELECT id, admin_id, admin_name, action, old_value, new_value, ip_address, created_at
                FROM admin_audit_logs ORDER BY id DESC LIMIT $limit")->fetchAll();
            $iso = function (array $rows): array {
                foreach ($rows as &$r) {
                    $r['created_at'] = date(DATE_ATOM, (int) $r['created_at']);
                }
                return $rows;
            };
            json_out(200, ['access_logs' => $iso($access), 'audit_logs' => $iso($audit)]);

        default:
            json_out(404, ['error' => 'not_found']);
    }
} catch (Throwable $ex) {
    error_log('Diva Junction API: ' . $ex->getMessage());
    json_out(503, ['error' => 'unavailable', 'message' => 'Please try again in a moment.']);
}
