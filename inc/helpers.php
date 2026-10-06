<?php
/**
 * Shared helpers for the public site and the admin panel.
 */

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape text and keep its line breaks. */
function e_lines(?string $value): string
{
    return nl2br(e(trim((string) $value)), false);
}

/** URL path of the site root, e.g. "/diva/" — works from / and /admin/. */
function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        if (str_ends_with($dir, '/admin')) {
            $dir = substr($dir, 0, -strlen('/admin'));
        }
        $base = rtrim($dir, '/') . '/';
    }
    return $base . ltrim($path, '/');
}

/** Public URL for a stored file path, with a cache-busting version. */
function asset(string $path): string
{
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return base_url($path) . $v;
}

/** Only allow http(s), relative paths and #anchors as link targets. */
function safe_link(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '#';
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    // Relative paths and anchors are fine; any other scheme (javascript:, data:, …) is not.
    return preg_match('#^[^:/?\#]*:#', $url) ? '#' : $url;
}

function is_external(string $url): bool
{
    return (bool) preg_match('#^https?://#i', $url);
}

function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache;
}

function setting(string $key, string $fallback = ''): string
{
    $all = settings();
    if (isset($all[$key]) && $all[$key] !== '') {
        return $all[$key];
    }
    $field = settings_field($key);
    return $field['default'] ?? $fallback;
}

/** Next countdown target as a Unix timestamp (rolls forward when repeating). */
function countdown_target(): int
{
    $end = strtotime(setting('countdown_end')) ?: time();
    $repeat = max(0, (float) setting('countdown_repeat_hours', '0')) * 3600;
    if ($repeat > 0 && $end <= time()) {
        $cycles = (int) floor((time() - $end) / $repeat) + 1;
        $end += (int) ($cycles * $repeat);
    }
    return $end;
}

/* ------------------------------------------------------------------ session, CSRF, flash */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('diva_admin');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'path'     => base_url(),
    ]);
    session_start();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    start_session();
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(403);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function flash(string $message, string $type = 'success'): void
{
    start_session();
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    start_session();
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/* ------------------------------------------------------------------ uploads */

/**
 * Store an uploaded image and return its path relative to the site root
 * ("uploads/abc.webp"), or null when no file was sent.
 *
 * @throws RuntimeException with a user-facing message on invalid uploads.
 */
function upload_image(string $field): ?string
{
    $file = $_FILES[$field] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'The image is too large.'
            : 'The image could not be uploaded (error ' . (int) $file['error'] . ').');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('The image is too large (max ' . round(MAX_UPLOAD_BYTES / 1048576) . ' MB).');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Please upload a JPG, PNG, WebP or GIF image.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    return 'uploads/' . $name;
}

/** Delete a previously uploaded file (never touches files outside uploads/). */
function delete_upload(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/')) {
        return;
    }
    $real = realpath(APP_ROOT . '/' . $path);
    $root = realpath(UPLOAD_DIR);
    if ($real && $root && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_file($real)) {
        @unlink($real);
    }
}
