<?php
require_once __DIR__ . '/../inc/admin.php';
$admin = require_admin();

$schema = settings_schema();
$values = settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $errors = [];
    $save = [];

    foreach ($schema as $group) {
        foreach ($group['fields'] as $key => $field) {
            $old = $values[$key] ?? $field['default'];

            if ($field['type'] === 'image') {
                if (!empty($_POST[$key . '_reset'])) {
                    delete_upload($old);
                    $save[$key] = $field['default'];
                    continue;
                }
                try {
                    $new = upload_image($key);
                } catch (RuntimeException $ex) {
                    $errors[] = $field['label'] . ': ' . $ex->getMessage();
                    continue;
                }
                if ($new !== null) {
                    delete_upload($old);
                    $save[$key] = $new;
                }
                continue;
            }

            $value = str_replace("\r\n", "\n", post($key));
            if ($field['type'] === 'url' && ($err = link_error($value))) {
                $errors[] = $field['label'] . ': ' . $err;
                continue;
            }
            if ($field['type'] === 'number') {
                $value = (string) max(0, (float) $value);
            }
            if ($field['type'] === 'datetime' && $value !== '' && strtotime($value) === false) {
                $errors[] = $field['label'] . ': invalid date.';
                continue;
            }
            $save[$key] = $value;
        }
    }

    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    foreach ($save as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    foreach ($errors as $err) {
        flash($err, 'error');
    }
    flash($errors ? 'Other changes were saved.' : 'Site content saved.', $errors ? 'warning' : 'success');
    redirect('settings.php');
}

admin_header('Site content', 'settings', $admin);
?>
<form method="post" enctype="multipart/form-data" class="settings-form">
    <?= csrf_field() ?>
    <nav class="tabs" aria-label="Sections">
        <?php foreach ($schema as $id => $group): ?>
        <a href="#<?= e($id) ?>"><?= e($group['title']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php foreach ($schema as $id => $group): ?>
    <section class="card" id="<?= e($id) ?>">
        <h2><?= e($group['title']) ?></h2>
        <div class="grid-2">
        <?php foreach ($group['fields'] as $key => $field):
            $value = $values[$key] ?? $field['default'];
            $opt = ['help' => $field['help'] ?? ''];
            switch ($field['type']) {
                case 'textarea':
                    echo field_textarea($key, $field['label'], $value, $opt);
                    break;
                case 'image':
                    echo field_image($key, $field['label'], $value, $opt + ['resettable' => true, 'default' => $field['default']]);
                    break;
                case 'datetime':
                    echo field_text($key, $field['label'], $value ? date('Y-m-d\TH:i', strtotime($value)) : '', $opt + ['type' => 'datetime-local']);
                    break;
                case 'number':
                    echo field_text($key, $field['label'], $value, $opt + ['type' => 'number', 'min' => 0, 'step' => '0.5']);
                    break;
                default:
                    echo field_text($key, $field['label'], $value, $opt + ['maxlength' => 300]);
            }
        endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <div class="savebar">
        <span class="muted">Changes go live as soon as you save.</span>
        <button type="submit" class="btn btn-primary">Save changes</button>
    </div>
</form>
<?php admin_footer(); ?>
