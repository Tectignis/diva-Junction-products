<?php
require_once __DIR__ . '/inc/geofence.php';
geofence_gate();

$sections = db()->query('SELECT * FROM sections WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$tilesBySection = [];
foreach (db()->query('SELECT * FROM tiles WHERE is_active = 1 ORDER BY sort_order, id') as $t) {
    $tilesBySection[$t['section_id']][] = $t;
}
$sections = array_filter($sections, fn($s) => !empty($tilesBySection[$s['id']]));

$pageTitle   = setting('site_title');
$bodyClass   = 'store';
$bodyStyle   = '';
$bannerLink  = safe_link(setting('banner_link'));
$bannerImage = setting_raw('banner_image');

/** target/rel attributes for outbound links */
function link_attrs(string $url): string
{
    return is_external($url) ? ' target="_blank" rel="noopener"' : '';
}

require __DIR__ . '/inc/head.php';
?>
<!-- ============================== HEADER (full-width banner) -->
<header class="store-hero">
    <h1><img src="<?= e(asset(setting('hero_banner'))) ?>" alt="Flipkart The Big Billion Days — Diva Junction" width="1440" height="480" fetchpriority="high"></h1>
</header>

<div class="store-page">

    <!-- ============================== SECTIONS OF TILES -->
    <main id="deals">
        <?php foreach ($sections as $s):
            $tiles = $tilesBySection[$s['id']];
            $carousel = $s['layout'] === 'carousel';
        ?>
        <section class="store-section is-<?= $carousel ? 'carousel' : 'grid' ?>" aria-labelledby="section-<?= (int) $s['id'] ?>"<?= $carousel ? ' data-carousel' : '' ?>>
            <div class="section-head">
                <h2 id="section-<?= (int) $s['id'] ?>"><?= e($s['title']) ?></h2>
                <?php if ($carousel): ?>
                <div class="carousel-nav">
                    <button type="button" class="carousel-btn" data-dir="-1" aria-label="Previous" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 6-6 6 6 6"/></svg></button>
                    <button type="button" class="carousel-btn" data-dir="1" aria-label="Next"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.5 6 6 6-6 6"/></svg></button>
                </div>
                <?php endif; ?>
            </div>
            <div class="tiles">
                <?php foreach (array_chunk($tiles, 4) as $row): ?>
                <div class="tile-row">
                <?php foreach ($row as $t): $link = safe_link($t['link']); ?>
                <a class="tile" href="<?= e($link) ?>"<?= link_attrs($link) ?>>
                    <span class="tile-photo">
                        <?php if ($t['image']): ?><img src="<?= e(asset($t['image'])) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
                    </span>
                    <span class="tile-cap">
                        <span class="tile-text">
                            <span class="tile-title"><?= e($t['title']) ?></span>
                            <?php if ($t['tag'] !== ''): ?><span class="tile-tag"><?= e($t['tag']) ?></span><?php endif; ?>
                        </span>
                        <span class="tile-go" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m9.5 6 6 6-6 6"/></svg></span>
                    </span>
                </a>
                <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </main>

    <?php if (setting('countdown_enabled') === '1'): ?>
    <!-- ============================== COUNTDOWN -->
    <section class="countdown" aria-live="off">
        <p class="countdown-label"><?= e(setting('countdown_label')) ?></p>
        <div class="countdown-board" data-target="<?= countdown_target() * 1000 ?>"
             data-repeat="<?= (float) setting('countdown_repeat_hours', '0') * 3600 * 1000 ?>"
             role="timer" aria-label="Time until the next deals">
            <svg class="dot-matrix" viewBox="0 0 39.6 12" aria-hidden="true"></svg>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================== BOTTOM BANNER -->
    <a class="deals-banner<?= $bannerImage !== '' ? ' is-image' : '' ?>" href="<?= e($bannerLink) ?>"<?= link_attrs($bannerLink) ?>>
        <?php if ($bannerImage !== ''): ?>
        <img src="<?= e(asset($bannerImage)) ?>" alt="<?= e(setting('banner_kicker') . ' ' . setting('banner_title')) ?>" loading="lazy">
        <?php else: ?>
        <img class="deco" src="<?= e(asset('assets/img/cloud-3.png')) ?>" alt="" style="--x:86;--y:4;--w:16">
        <img class="deals-logo" src="<?= e(asset(setting('logo'))) ?>" alt="" loading="lazy">
        <span class="deals-copy">
            <span class="deals-kicker"><?= e(setting('banner_kicker')) ?></span>
            <span class="deals-title"><?= e(setting('banner_title')) ?></span>
            <span class="deals-cta"><?= e(setting('banner_cta_text')) ?></span>
        </span>
        <?php endif; ?>
    </a>

    <?php if (setting_raw('disclaimer') !== ''): ?>
    <footer class="disclaimer"><p><?= e_lines(setting_raw('disclaimer')) ?></p></footer>
    <?php endif; ?>

</div>
<?php require __DIR__ . '/inc/preview_notice.php'; ?>
<script src="<?= e(asset('assets/js/store.js')) ?>" defer></script>
<?php if (setting('countdown_enabled') === '1'): ?>
<script src="<?= e(asset('assets/js/countdown.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
