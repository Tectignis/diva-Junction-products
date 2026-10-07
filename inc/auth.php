<?php
/**
 * Admin authentication.
 */

require_once __DIR__ . '/helpers.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 300;

function current_admin(): ?array
{
    start_session();
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect(base_url('admin/login.php'));
    }
    return $admin;
}

/** Returns an error message, or null on success. */
function attempt_login(string $username, string $password): ?string
{
    start_session();
    $lock = $_SESSION['login_lock_until'] ?? 0;
    if ($lock > time()) {
        return 'Too many attempts. Try again in ' . ceil(($lock - time()) / 60) . ' minute(s).';
    }

    $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
        if ($_SESSION['login_fails'] >= LOGIN_MAX_ATTEMPTS) {
            $_SESSION['login_lock_until'] = time() + LOGIN_LOCK_SECONDS;
            $_SESSION['login_fails'] = 0;
        }
        return 'Wrong username or password.';
    }

    session_regenerate_id(true);
    unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
    $_SESSION['admin_id'] = (int) $row['id'];

    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    audit_log($row, 'admin.login');
    return null;
}

/**
 * Record an admin change. Arrays are stored as JSON; pass only the values that changed.
 */
function audit_log(array $admin, string $action, mixed $old = null, mixed $new = null): void
{
    $encode = fn($v) => $v === null ? null : (is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    db()->prepare('INSERT INTO admin_audit_logs (admin_id, admin_name, action, old_value, new_value, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$admin['id'] ?? null, $admin['username'] ?? '', $action, $encode($old), $encode($new), client_ip(), time()]);
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    session_regenerate_id(true);
    session_destroy();
}
