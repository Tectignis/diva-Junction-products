<?php
require_once __DIR__ . '/../inc/admin.php';

if (current_admin()) {
    redirect('index.php');
}

$error = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = post('username');
    $error = attempt_login($username, (string) ($_POST['password'] ?? ''));
    if ($error === null) {
        redirect('index.php');
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · Diva Junction Admin</title>
    <link rel="icon" href="<?= e(asset(setting('logo'))) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="login-page">
<main class="login-card">
    <img class="login-logo" src="<?= e(asset(setting('logo'))) ?>" alt="Diva Junction">
    <h1>Admin sign in</h1>
    <p class="muted">Manage the Diva Junction microsite.</p>

    <?php if ($error): ?>
    <div class="flash flash-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="stack">
        <?= csrf_field() ?>
        <?= field_text('username', 'Username', $username, ['required' => true, 'autocomplete' => 'username']) ?>
        <?= field_text('password', 'Password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>
    <a class="back-link" href="<?= e(base_url('index.php')) ?>">← Back to the site</a>
</main>
</body>
</html>
