<?php
require_once __DIR__ . '/inc/helpers.php';

$pageTitle = setting('site_title');
$bodyClass = 'landing';
$bg        = asset(setting('landing_bg'));
$bodyStyle = "--landing-bg: url('" . $bg . "')";
$ctaLink   = safe_link(setting('landing_cta_link'));
$fkLink    = safe_link(setting('flipkart_link'));

require __DIR__ . '/inc/head.php';
?>
<main class="stage">
    <img class="stage-art" src="<?= e($bg) ?>" alt="" fetchpriority="high">

    <a class="corner-icon" href="<?= e($fkLink) ?>"<?= is_external($fkLink) ? ' target="_blank" rel="noopener"' : '' ?>>
        <img src="<?= e(asset(setting('flipkart_icon'))) ?>" alt="Flipkart">
    </a>

    <h1 class="board-welcome">
        <span><?= e(setting('landing_welcome')) ?></span>
        <img class="board-logo" src="<?= e(asset(setting('logo'))) ?>" alt="Diva Junction">
    </h1>

    <p class="board-tagline"><?= e_lines(setting('landing_tagline')) ?></p>

    <a class="landing-cta" href="<?= e($ctaLink) ?>"<?= is_external($ctaLink) ? ' target="_blank" rel="noopener"' : '' ?>>
        <?= e(setting('landing_cta_text')) ?>
    </a>
</main>
</body>
</html>
