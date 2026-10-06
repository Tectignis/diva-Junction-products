<?php
/** @var string $title @var string $active @var array $admin */
$nav = [
    'dashboard' => ['index.php', 'Dashboard', 'grid'],
    'settings'  => ['settings.php', 'Site content', 'type'],
    'featured'  => ['featured.php', 'Featured', 'star'],
    'brands'    => ['brands.php', 'Brands', 'sign'],
    'products'  => ['products.php', 'Products', 'bag'],
    'account'   => ['account.php', 'Account', 'user'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · Diva Junction Admin</title>
    <link rel="icon" href="<?= e(asset(setting('logo'))) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin">
<input type="checkbox" id="nav-toggle" class="nav-toggle" hidden>
<aside class="sidebar">
    <a class="brand" href="index.php">
        <img src="<?= e(asset(setting('logo'))) ?>" alt="">
        <span><strong>Diva Junction</strong><small>Admin panel</small></span>
    </a>
    <nav>
        <?php foreach ($nav as $key => [$href, $label, $ic]): ?>
        <a href="<?= $href ?>" class="<?= $key === $active ? 'is-active' : '' ?>"><?= icon($ic) ?><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
        <a href="<?= e(base_url('index.php')) ?>" target="_blank" rel="noopener"><?= icon('external') ?>Landing page</a>
        <a href="<?= e(base_url('shop.php')) ?>" target="_blank" rel="noopener"><?= icon('external') ?>Product page</a>
        <form method="post" action="logout.php"><?= csrf_field() ?>
            <button type="submit"><?= icon('logout') ?>Log out <span class="who"><?= e($admin['username']) ?></span></button>
        </form>
    </div>
</aside>
<label for="nav-toggle" class="nav-scrim" aria-hidden="true"></label>
<div class="main">
    <header class="topbar">
        <label for="nav-toggle" class="menu-btn" aria-label="Menu"><?= icon('menu') ?></label>
        <h1><?= e($title) ?></h1>
    </header>
    <div class="content">
        <?php foreach (take_flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
