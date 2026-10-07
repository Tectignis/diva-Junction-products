<?php
/**
 * Shown when the location settings cannot be read. Deliberately uses no database
 * access, so it renders even when SQLite is unavailable.
 */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Back in a moment · Diva Junction</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(base_url('assets/css/site.css'), ENT_QUOTES) ?>">
</head>
<body class="geo">
<main class="geo-screen">
    <div class="geo-card">
        <img class="geo-logo" src="<?= htmlspecialchars(base_url('assets/img/logo.png'), ENT_QUOTES) ?>" alt="Diva Junction">
        <section class="geo-panel">
            <h1>We'll be right back</h1>
            <p>Diva Junction is temporarily unavailable. Please try again in a minute.</p>
            <div class="geo-actions"><a class="geo-btn" href="">Try Again</a></div>
        </section>
    </div>
</main>
</body>
</html>
