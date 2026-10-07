<?php
require_once __DIR__ . '/../inc/admin.php';
$admin = require_admin();

$schema = settings_schema();
$values = settings();

// The open tab: ?tab=… on GET, the hidden field on POST (kept across the save redirect).
$tab = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('tab') : ($_GET['tab'] ?? '');
if (!is_string($tab) || !isset($schema[$tab])) {
    $tab = array_key_first($schema);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $errors = [];
    $errorTab = null;
    $save = [];

    foreach ($schema as $groupId => $group) {
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
                    $errorTab ??= $groupId;
                    continue;
                }
                if ($new !== null) {
                    delete_upload($old);
                    $save[$key] = $new;
                }
                continue;
            }

            if ($field['type'] === 'bool') {
                $save[$key] = isset($_POST[$key]) ? '1' : '0';
                continue;
            }
            $value = str_replace("\r\n", "\n", post($key));
            if ($field['type'] === 'url' && ($err = link_error($value))) {
                $errors[] = $field['label'] . ': ' . $err;
                $errorTab ??= $groupId;
                continue;
            }
            if ($field['type'] === 'number') {
                $value = (string) max(0, (float) $value);
            }
            if ($field['type'] === 'datetime' && $value !== '' && strtotime($value) === false) {
                $errors[] = $field['label'] . ': invalid date.';
                $errorTab ??= $groupId;
                continue;
            }
            $save[$key] = $value;
        }
    }

    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $before = $after = [];
    foreach ($save as $key => $value) {
        $stmt->execute([$key, $value]);
        $old = $values[$key] ?? '';
        if ($old !== $value) {
            $before[$key] = $old;
            $after[$key] = $value;
        }
    }
    if ($after) {
        audit_log($admin, 'settings.updated', $before, $after);
    }
    foreach ($errors as $err) {
        flash($err, 'error');
    }
    flash($errors ? 'Other changes were saved.' : 'Site content saved.', $errors ? 'warning' : 'success');
    redirect('settings.php?tab=' . urlencode($errorTab ?? $tab));
}

admin_header('Site content', 'settings', $admin);
?>
<form method="post" enctype="multipart/form-data" class="settings-form" data-settings>
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e($tab) ?>">

    <nav class="settings-nav" aria-label="Site content sections">
        <?php $heading = null;
        foreach ($schema as $id => $group):
            if ($group['nav'] !== $heading): $heading = $group['nav']; ?>
        <span class="settings-nav-heading"><?= e($heading) ?></span>
        <?php endif; ?>
        <a href="?tab=<?= e($id) ?>" data-tab="<?= e($id) ?>"<?= $id === $tab ? ' aria-current="true"' : '' ?>>
            <?= icon($group['icon']) ?><span><?= e($group['tab']) ?></span><i class="dirty-dot" title="Unsaved changes"></i>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="settings-panels">
        <?php foreach ($schema as $id => $group): ?>
        <section class="card settings-panel" id="panel-<?= e($id) ?>" data-panel="<?= e($id) ?>" aria-labelledby="title-<?= e($id) ?>"<?= $id === $tab ? '' : ' hidden' ?>>
            <header class="panel-head">
                <span class="panel-icon"><?= icon($group['icon']) ?></span>
                <div class="panel-title">
                    <small><?= e($group['nav']) ?></small>
                    <h2 id="title-<?= e($id) ?>"><?= e($group['tab']) ?></h2>
                    <p class="muted"><?= e($group['intro']) ?></p>
                </div>
                <?php if ($group['page'] !== ''): ?>
                <a class="btn btn-sm" href="<?= e(base_url($group['page'])) ?>" target="_blank" rel="noopener"><?= icon('external') ?>View page</a>
                <?php endif; ?>
            </header>
            <div class="grid-2">
            <?php foreach ($group['fields'] as $key => $field):
                $value = $values[$key] ?? $field['default'];
                $opt = ['help' => $field['help'] ?? ''];
                switch ($field['type']) {
                    case 'textarea':
                        echo field_textarea($key, $field['label'], $value, $opt);
                        break;
                    case 'image':
                        echo field_image($key, $field['label'], $value, $opt + ['resettable' => true, 'default' => $field['default'], 'wide' => !empty($field['wide'])]);
                        break;
                    case 'datetime':
                        echo field_text($key, $field['label'], $value ? date('Y-m-d\TH:i', strtotime($value)) : '', $opt + ['type' => 'datetime-local']);
                        break;
                    case 'bool':
                        echo '<div class="field field-bool">' . field_check($key, $field['label'], $value === '1') . '</div>';
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

        <div class="savebar" data-savebar>
            <span class="muted" data-save-status>All tabs are saved together. Changes go live as soon as you save.</span>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </div>
</form>
<?php admin_footer(); ?>
