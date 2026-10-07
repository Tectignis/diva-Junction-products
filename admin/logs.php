<?php
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/geofence.php';
$admin = require_admin();

const PER_PAGE = 50;

$tab = ($_GET['tab'] ?? '') === 'audit' ? 'audit' : 'access';
$filter = in_array($_GET['result'] ?? '', ['allowed', 'denied'], true) ? $_GET['result'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * PER_PAGE;

if ($tab === 'access') {
    $where = $filter ? 'WHERE result = ' . db()->quote($filter) : '';
    $total = (int) db()->query("SELECT COUNT(*) FROM location_access_logs $where")->fetchColumn();
    $rows = db()->query("SELECT * FROM location_access_logs $where ORDER BY id DESC LIMIT " . PER_PAGE . " OFFSET $offset")->fetchAll();
} else {
    $total = (int) db()->query('SELECT COUNT(*) FROM admin_audit_logs')->fetchColumn();
    $rows = db()->query('SELECT * FROM admin_audit_logs ORDER BY id DESC LIMIT ' . PER_PAGE . " OFFSET $offset")->fetchAll();
}
$pages = max(1, (int) ceil($total / PER_PAGE));

/** "iPhone · Safari" style summary of a user agent. */
function device(string $ua): string
{
    $os = match (true) {
        str_contains($ua, 'iPhone') => 'iPhone',
        str_contains($ua, 'iPad') => 'iPad',
        str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'Windows') => 'Windows',
        str_contains($ua, 'Mac OS') => 'Mac',
        str_contains($ua, 'Linux') => 'Linux',
        default => 'Other',
    };
    $browser = match (true) {
        str_contains($ua, 'SamsungBrowser') => 'Samsung',
        str_contains($ua, 'Edg/') => 'Edge',
        str_contains($ua, 'CriOS'), str_contains($ua, 'Chrome') => 'Chrome',
        str_contains($ua, 'FxiOS'), str_contains($ua, 'Firefox') => 'Firefox',
        str_contains($ua, 'Safari') => 'Safari',
        default => '',
    };
    return $browser ? "$os · $browser" : $os;
}

/** Old → new list for an audit row. */
function changes(?string $old, ?string $new): string
{
    $o = $old !== null ? json_decode($old, true) : null;
    $n = $new !== null ? json_decode($new, true) : null;
    $show = fn($v) => is_bool($v) ? ($v ? 'on' : 'off') : (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) ($v ?? '—'));
    if (is_array($n) || is_array($o)) {
        $keys = array_unique(array_merge(array_keys((array) $o), array_keys((array) $n)));
        $out = '';
        foreach ($keys as $k) {
            $before = is_array($o) && array_key_exists($k, $o) ? $show($o[$k]) : null;
            $after = is_array($n) && array_key_exists($k, $n) ? $show($n[$k]) : null;
            if ($before !== null && $after !== null && $before === $after) {
                continue;
            }
            $out .= '<li><span class="k">' . e(str_replace('_', ' ', (string) $k)) . '</span> '
                . ($before !== null ? '<del>' . e(mb_strimwidth($before, 0, 80, '…')) . '</del> ' : '')
                . ($after !== null ? '<ins>' . e(mb_strimwidth($after, 0, 80, '…')) . '</ins>' : '') . '</li>';
        }
        return $out ? '<ul class="diff">' . $out . '</ul>' : '';
    }
    return e(trim(($old ?? '') . ' → ' . ($new ?? ''), ' →'));
}

function page_url(array $params): string
{
    return 'logs.php?' . http_build_query(array_filter(array_merge(
        ['tab' => $_GET['tab'] ?? null, 'result' => $_GET['result'] ?? null, 'page' => null], $params)));
}

admin_header('Logs', 'logs', $admin);
?>
<div class="toolbar">
    <div class="filter">
        <a class="chip<?= $tab === 'access' && !$filter ? ' is-active' : '' ?>" href="logs.php">Location checks</a>
        <a class="chip<?= $filter === 'allowed' ? ' is-active' : '' ?>" href="logs.php?result=allowed">Allowed</a>
        <a class="chip<?= $filter === 'denied' ? ' is-active' : '' ?>" href="logs.php?result=denied">Blocked</a>
        <a class="chip<?= $tab === 'audit' ? ' is-active' : '' ?>" href="logs.php?tab=audit">Admin changes</a>
    </div>
    <span class="muted"><?= $total ?> record<?= $total === 1 ? '' : 's' ?></span>
</div>

<?php if ($tab === 'access'): ?>
<p class="muted small">Every location check made on the location screen. Coordinates are rounded to about 11 m; visitor IPs are stored only as a hash. Records older than <?= (int) geofence_config()['log_retention_days'] ?> days are deleted automatically.</p>
<?php endif; ?>

<?php if (!$rows): ?>
<div class="card empty"><?= $tab === 'access' ? 'No location checks yet. They appear once the location lock is ON and visitors try to open the site.' : 'No admin changes recorded yet.' ?></div>
<?php elseif ($tab === 'access'): ?>
<div class="card table-card">
    <table class="log-table">
        <thead><tr><th>Time</th><th>Result</th><th>Distance</th><th>Accuracy</th><th>Position</th><th>Visitor</th><th>Device</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td class="nowrap"><?= e(date('d M, H:i:s', (int) $r['created_at'])) ?></td>
            <td><span class="result <?= $r['result'] === 'allowed' ? 'is-ok' : 'is-bad' ?>"><?= $r['result'] === 'allowed' ? 'Allowed' : 'Blocked' ?></span>
                <?php if ($r['reason'] !== ''): ?><small class="muted"><?= e(GEO_REASONS[$r['reason']] ?? $r['reason']) ?></small><?php endif; ?></td>
            <td class="nowrap"><?= $r['distance_meters'] !== null ? e(format_distance((float) $r['distance_meters'])) : '—' ?></td>
            <td class="nowrap"><?= $r['accuracy'] !== null ? '±' . e(format_distance((float) $r['accuracy'])) : '—' ?></td>
            <td class="nowrap"><?php if ($r['latitude'] !== null): ?><a href="https://www.google.com/maps?q=<?= e($r['latitude'] . ',' . $r['longitude']) ?>" target="_blank" rel="noopener"><?= e($r['latitude'] . ', ' . $r['longitude']) ?></a><?php else: ?>—<?php endif; ?></td>
            <td><code><?= e($r['session_ref']) ?></code></td>
            <td class="nowrap" title="<?= e($r['user_agent']) ?>"><?= e(device($r['user_agent'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="card table-card">
    <table class="log-table">
        <thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Changes</th><th>IP address</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td class="nowrap"><?= e(date('d M, H:i:s', (int) $r['created_at'])) ?></td>
            <td><?= e($r['admin_name']) ?></td>
            <td><code><?= e($r['action']) ?></code></td>
            <td><?= changes($r['old_value'], $r['new_value']) ?></td>
            <td class="nowrap"><?= e($r['ip_address']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= e(page_url(['page' => $page - 1])) ?>">← Newer</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= e(page_url(['page' => $page + 1])) ?>">Older →</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php admin_footer(); ?>
