<?php
require_once __DIR__ . '/inc/helpers.php';

$featured = db()->query('SELECT * FROM featured WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$brands   = db()->query('SELECT * FROM brands WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$productsByBrand = [];
foreach (db()->query('SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order, id') as $p) {
    $productsByBrand[$p['brand_id']][] = $p;
}

$pageTitle = setting('site_title');
$bodyClass = 'shop';
$bodyStyle = '';
$heroLink  = safe_link(setting('hero_cta_link'));
$fkLink    = safe_link(setting('flipkart_link'));

/** target/rel attributes for outbound links */
function link_attrs(string $url): string
{
    return is_external($url) ? ' target="_blank" rel="noopener"' : '';
}

/** Shrink long station names so they fit the sign bar. */
function station_size(string $name): string
{
    $len = mb_strlen($name);
    return $len <= 10 ? '' : ($len <= 14 ? ' is-long' : ' is-xlong');
}

require __DIR__ . '/inc/head.php';
?>
<div class="page">

    <!-- ============================== HERO -->
    <header class="hero">
        <img class="deco" src="<?= e(asset('assets/img/cloud-1.png')) ?>" alt="" style="--x:0;--y:298;--w:354">
        <img class="deco" src="<?= e(asset('assets/img/bird-1.png')) ?>" alt="" style="--x:64;--y:244;--w:93">
        <img class="deco" src="<?= e(asset('assets/img/bird-2.png')) ?>" alt="" style="--x:977;--y:822;--w:65">

        <a class="corner-icon" href="<?= e($fkLink) ?>"<?= link_attrs($fkLink) ?>>
            <img src="<?= e(asset(setting('flipkart_icon'))) ?>" alt="Flipkart">
        </a>

        <div class="hero-card">
            <img class="hero-art" src="<?= e(asset(setting('hero_image'))) ?>" alt="" fetchpriority="high">
            <div class="hero-copy">
                <h1>
                    <span class="hero-line"><?= e_lines(setting('hero_heading')) ?></span>
                    <em class="hero-line"><?= e_lines(setting('hero_highlight')) ?></em>
                </h1>
                <a class="pill pill-blue" href="<?= e($heroLink) ?>"<?= link_attrs($heroLink) ?>><?= e(setting('hero_cta_text')) ?></a>
            </div>
        </div>

        <img class="hero-logo" src="<?= e(asset(setting('logo'))) ?>" alt="Diva Junction">
    </header>

    <?php if ($featured): ?>
    <!-- ============================== FEATURED -->
    <section class="featured" id="featured" aria-labelledby="featured-title">
        <h2 class="featured-band" id="featured-title"><?= e(setting('featured_title')) ?></h2>
        <div class="featured-track">
            <?php foreach ($featured as $f): $link = safe_link($f['link']); ?>
            <a class="feat" href="<?= e($link) ?>"<?= link_attrs($link) ?>>
                <span class="feat-card">
                    <?php if ($f['image']): ?><img src="<?= e(asset($f['image'])) ?>" alt="" loading="lazy"><?php endif; ?>
                </span>
                <span class="feat-title"><?= e($f['title']) ?></span>
                <span class="feat-brand"><?= e($f['brand']) ?></span>
                <?php if ($f['deal_tag'] !== ''): ?><span class="feat-tag"><?= e($f['deal_tag']) ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="featured-rail"></div>
    </section>
    <?php endif; ?>

    <?php if ($brands): ?>
    <!-- ============================== SHOP BY BRANDS -->
    <section class="brands" aria-labelledby="brands-title">
        <img class="deco" src="<?= e(asset('assets/img/cloud-2.png')) ?>" alt="" style="--x:0;--y:88;--w:253">
        <img class="deco" src="<?= e(asset('assets/img/bird-3.png')) ?>" alt="" style="--x:41;--y:137;--w:71">

        <h2 class="section-title" id="brands-title"><?= e(setting('brands_title')) ?></h2>

        <?php foreach ($brands as $i => $b):
            $side = $b['sign_side'] === 'auto' ? ($i % 2 === 0 ? 'left' : 'right') : $b['sign_side'];
            $items = $productsByBrand[$b['id']] ?? [];
        ?>
        <article class="brand-row is-<?= $side ?>" aria-label="<?= e($b['name']) ?>">
            <?php if ($side === 'right' && $i > 0): ?>
            <img class="deco" src="<?= e(asset('assets/img/cloud-3.png')) ?>" alt="" style="--x:937;--y:-7;--w:143">
            <?php elseif ($side === 'left' && $i > 0): ?>
            <img class="deco" src="<?= e(asset('assets/img/cloud-2.png')) ?>" alt="" style="--x:-110;--y:-13;--w:253">
            <?php endif; ?>

            <div class="brand-panel">
                <div class="brand-track">
                    <?php foreach ($items as $p): $link = safe_link($p['link']); ?>
                    <a class="product" href="<?= e($link) ?>"<?= link_attrs($link) ?>>
                        <span class="product-card">
                            <?php if ($p['image']): ?><img src="<?= e(asset($p['image'])) ?>" alt="" loading="lazy"><?php endif; ?>
                        </span>
                        <span class="product-name"><?= e($p['name']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="station" aria-hidden="true">
                <span class="station-pole"></span>
                <span class="station-diamond"></span>
                <span class="station-ring"></span>
                <span class="station-name<?= station_size($b['name']) ?>"><?= e($b['name']) ?></span>
            </div>
        </article>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- ============================== COUNTDOWN -->
    <section class="countdown" aria-live="off">
        <p class="countdown-label"><?= e(setting('countdown_label')) ?></p>
        <div class="countdown-board" data-target="<?= countdown_target() * 1000 ?>"
             data-repeat="<?= (float) setting('countdown_repeat_hours', '0') * 3600 * 1000 ?>"
             role="timer" aria-label="Time until the next deals">
            <svg class="dot-matrix" viewBox="0 0 39.6 12" aria-hidden="true"></svg>
        </div>
    </section>

</div>
<script src="<?= e(asset('assets/js/countdown.js')) ?>" defer></script>
</body>
</html>
