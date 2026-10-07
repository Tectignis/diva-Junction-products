<?php
/**
 * Shown when the location settings cannot be read. Deliberately uses no database
 * access, so it renders even when SQLite is unavailable.
 */
$h = fn(string $path) => htmlspecialchars(base_url($path), ENT_QUOTES);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Back in a moment · Diva Junction</title>
    <link rel="stylesheet" href="<?= $h('assets/css/site.css') ?>">
</head>
<body class="landing">
<div class="flow" data-screen="error">
    <main class="check">
        <section class="check-card is-result">
            <img class="check-sign" src="<?= $h('assets/img/sign-fail.webp') ?>" alt="Oh no, Diva!" width="556" height="329">
            <h1 class="check-lines"><span class="line-sm">We'll be</span><span class="line-xl">Right Back!</span></h1>
            <p class="check-msg">Diva Junction is taking a short break. Please try again in a minute.</p>
            <a class="pill-btn" href="">Try Again</a>
        </section>
    </main>
</div>
</body>
</html>
