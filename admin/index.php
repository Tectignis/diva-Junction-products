<?php
require_once __DIR__ . '/../inc/admin.php';
$admin = require_admin();

$count = fn(string $sql) => (int) db()->query($sql)->fetchColumn();
$stats = [
    ['Featured items', $count('SELECT COUNT(*) FROM featured WHERE is_active = 1'), $count('SELECT COUNT(*) FROM featured'), 'featured.php', 'star'],
    ['Brands', $count('SELECT COUNT(*) FROM brands WHERE is_active = 1'), $count('SELECT COUNT(*) FROM brands'), 'brands.php', 'sign'],
    ['Products', $count('SELECT COUNT(*) FROM products WHERE is_active = 1'), $count('SELECT COUNT(*) FROM products'), 'products.php', 'bag'],
];
$target = countdown_target();
$left = max(0, $target - time());

admin_header('Dashboard', 'dashboard', $admin);
?>
<section class="stats">
    <?php foreach ($stats as [$label, $live, $total, $href, $ic]): ?>
    <a class="stat card" href="<?= $href ?>">
        <span class="stat-icon"><?= icon($ic) ?></span>
        <span class="stat-num"><?= $live ?></span>
        <span class="stat-label"><?= e($label) ?> live<?= $total > $live ? ' · ' . ($total - $live) . ' hidden' : '' ?></span>
    </a>
    <?php endforeach; ?>
    <a class="stat card" href="settings.php#sections">
        <span class="stat-icon"><?= icon('clock') ?></span>
        <span class="stat-num"><?= sprintf('%02d:%02d', intdiv($left, 3600), intdiv($left % 3600, 60)) ?></span>
        <span class="stat-label">Next deals at <?= e(date('d M, H:i', $target)) ?></span>
    </a>
</section>

<section class="card">
    <h2>Quick actions</h2>
    <div class="quick">
        <a class="btn btn-primary" href="featured.php?action=new"><?= icon('plus') ?>Add featured item</a>
        <a class="btn btn-primary" href="brands.php?action=new"><?= icon('plus') ?>Add brand</a>
        <a class="btn btn-primary" href="products.php?action=new"><?= icon('plus') ?>Add product</a>
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
        <span><strong>Product page</strong><small>Featured, brands &amp; countdown</small></span>
    </a>
</section>
<?php admin_footer(); ?>
