<?php
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/geofence.php';
$admin = require_admin();

$count = fn(string $sql) => (int) db()->query($sql)->fetchColumn();
$stats = [
    ['Sections', $count('SELECT COUNT(*) FROM sections WHERE is_active = 1'), $count('SELECT COUNT(*) FROM sections'), 'sections.php', 'layers'],
    ['Tiles', $count('SELECT COUNT(*) FROM tiles WHERE is_active = 1'), $count('SELECT COUNT(*) FROM tiles'), 'tiles.php', 'tile'],
];
$geo = geofence_config();
$checks = db()->prepare("SELECT SUM(result = 'allowed'), SUM(result = 'denied') FROM location_access_logs WHERE created_at > ?");
$checks->execute([time() - 86400]);
[$allowed, $denied] = array_map('intval', $checks->fetch(PDO::FETCH_NUM));

admin_header('Dashboard', 'dashboard', $admin);
?>
<section class="stats">
    <a class="stat card geo-stat<?= $geo['enabled'] ? ' is-on' : '' ?>" href="location.php">
        <span class="stat-icon"><?= icon('pin') ?></span>
        <span class="stat-num"><?= $geo['enabled'] ? 'ON' : 'OFF' ?></span>
        <span class="stat-label">Location lock<?= $geo['enabled']
            ? ' · ' . e(format_distance($geo['radius_meters'])) . ' around ' . e($geo['location_name'] ?: 'the pin')
            : ' · site open to everyone' ?></span>
    </a>
    <a class="stat card" href="logs.php">
        <span class="stat-icon"><?= icon('list') ?></span>
        <span class="stat-num"><?= $allowed ?> / <?= $denied ?></span>
        <span class="stat-label">Location checks allowed / blocked (24 h)</span>
    </a>
    <?php foreach ($stats as [$label, $live, $total, $href, $ic]): ?>
    <a class="stat card" href="<?= $href ?>">
        <span class="stat-icon"><?= icon($ic) ?></span>
        <span class="stat-num"><?= $live ?></span>
        <span class="stat-label"><?= e($label) ?> live<?= $total > $live ? ' · ' . ($total - $live) . ' hidden' : '' ?></span>
    </a>
    <?php endforeach; ?>
</section>

<section class="card">
    <h2>Quick actions</h2>
    <div class="quick">
        <a class="btn btn-primary" href="tiles.php?action=new"><?= icon('plus') ?>Add tile</a>
        <a class="btn btn-primary" href="sections.php?action=new"><?= icon('plus') ?>Add section</a>
        <a class="btn" href="location.php"><?= icon('pin') ?>Location lock</a>
        <a class="btn" href="settings.php"><?= icon('type') ?>Edit texts &amp; artwork</a>
    </div>
</section>

<section class="previews">
    <a class="card preview" href="<?= e(base_url('index.php')) ?>" target="_blank" rel="noopener">
        <img src="<?= e(asset(setting('landing_bg'))) ?>" alt="">
        <span><strong>Landing page</strong><small><?= e(setting('landing_cta_text')) ?> → <?= e(setting('landing_cta_link')) ?></small></span>
    </a>
    <a class="card preview" href="<?= e(base_url('shop.php')) ?>" target="_blank" rel="noopener">
        <img src="<?= e(asset(setting('hero_image'))) ?>" alt="">
        <span><strong>Deals page</strong><small>Header, sections of tiles &amp; bottom banner</small></span>
    </a>
</section>
<?php admin_footer(); ?>
