<?php
require_once __DIR__ . '/../inc/admin.php';
$admin = require_admin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = post('username');
    $current  = (string) ($_POST['current_password'] ?? '');
    $new      = (string) ($_POST['new_password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$admin['id']]);
    if (!password_verify($current, (string) $stmt->fetchColumn())) {
        $errors[] = 'Your current password is not correct.';
    }
    if (!preg_match('/^[A-Za-z0-9_.@-]{3,40}$/', $username)) {
        $errors[] = 'Username: 3–40 letters, numbers or _ . @ -';
    }
    if ($new !== '' && strlen($new) < 8) {
        $errors[] = 'The new password must be at least 8 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }
    if (!$errors) {
        $taken = db()->prepare('SELECT 1 FROM admins WHERE username = ? AND id <> ?');
        $taken->execute([$username, $admin['id']]);
        if ($taken->fetchColumn()) {
            $errors[] = 'That username is already taken.';
        }
    }
    if (!$errors) {
        db()->prepare('UPDATE admins SET username = ? WHERE id = ?')->execute([$username, $admin['id']]);
        if ($new !== '') {
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
        }
        flash($new !== '' ? 'Account and password updated.' : 'Account updated.');
        redirect('account.php');
    }
    $admin['username'] = $username;
}

admin_header('Account', 'account', $admin);
foreach ($errors as $err): ?>
<div class="flash flash-error" role="alert"><?= e($err) ?></div>
<?php endforeach; ?>
<?php if (password_verify(DEFAULT_ADMIN_PASS, (string) db()->query('SELECT password_hash FROM admins WHERE id = ' . (int) $admin['id'])->fetchColumn())): ?>
<div class="flash flash-warning">You are still using the default password. Please change it below.</div>
<?php endif; ?>
<form method="post" class="card form-card narrow" autocomplete="off">
    <?= csrf_field() ?>
    <?= field_text('username', 'Username', $admin['username'], ['required' => true, 'autocomplete' => 'username']) ?>
    <?= field_text('new_password', 'New password', '', ['type' => 'password', 'autocomplete' => 'new-password', 'help' => 'Leave empty to keep the current password. Minimum 8 characters.']) ?>
    <?= field_text('confirm_password', 'Repeat new password', '', ['type' => 'password', 'autocomplete' => 'new-password']) ?>
    <hr>
    <?= field_text('current_password', 'Current password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'help' => 'Required to save any change.']) ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save account</button>
    </div>
</form>
<?php admin_footer(); ?>
