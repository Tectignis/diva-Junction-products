<?php
require_once __DIR__ . '/../inc/admin.php';
$admin = require_admin();

const SECTION_LAYOUTS = [
    'carousel' => 'Swipeable row (like “Featured”)',
    'grid'     => 'Grid — 2 columns on phones, 4 on desktop',
];

function find_section(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM sections WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$form = null;
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'save') {
        $row = $id ? find_section($id) : null;
        $form = [
            'id'        => $row ? $id : 0,
            'title'     => post('title'),
            'layout'    => array_key_exists(post('layout'), SECTION_LAYOUTS) ? post('layout') : 'grid',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($form['title'] === '') {
            $formErrors[] = 'Section title is required.';
        }
        if (!$formErrors) {
            if ($row) {
                db()->prepare('UPDATE sections SET title = ?, layout = ?, is_active = ? WHERE id = ?')
                    ->execute([$form['title'], $form['layout'], $form['is_active'], $id]);
                audit_log($admin, 'section.updated', array_intersect_key($row, $form), $form);
                flash('Section updated.');
                redirect('sections.php');
            }
            db()->prepare('INSERT INTO sections (title, layout, is_active, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([$form['title'], $form['layout'], $form['is_active'], next_sort_order('sections')]);
            $newId = (int) db()->lastInsertId();
            audit_log($admin, 'section.created', null, ['id' => $newId] + $form);
            flash('Section added — now add its tiles.');
            redirect('tiles.php?section=' . $newId);
        }
    } else {
        if ($action === 'delete' && ($row = find_section($id))) {
            $images = db()->prepare('SELECT image FROM tiles WHERE section_id = ?');
            $images->execute([$id]);
            $files = $images->fetchAll(PDO::FETCH_COLUMN);
            db()->prepare('DELETE FROM sections WHERE id = ?')->execute([$id]); // tiles cascade
            array_map('delete_upload', $files);
            audit_log($admin, 'section.deleted', $row);
            flash('Section “' . $row['title'] . '” and its tiles were deleted.');
        } elseif ($action === 'toggle' && ($row = find_section($id))) {
            db()->prepare('UPDATE sections SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            audit_log($admin, $row['is_active'] ? 'section.hidden' : 'section.shown', null, ['id' => $id, 'title' => $row['title']]);
            flash($row['is_active'] ? '“' . $row['title'] . '” is now hidden.' : '“' . $row['title'] . '” is live again.');
        } elseif ($action === 'move') {
            move_row('sections', $id, post('dir'));
        }
        redirect('sections.php');
    }
}

$action = $_GET['action'] ?? '';
if (!$form && $action === 'edit') {
    $form = find_section((int) ($_GET['id'] ?? 0));
    if (!$form) {
        flash('That section no longer exists.', 'error');
        redirect('sections.php');
    }
}
if (!$form && $action === 'new') {
    $form = ['id' => 0, 'title' => '', 'layout' => 'grid', 'is_active' => 1];
}

if ($form) {
    admin_header($form['id'] ? 'Edit section' : 'Add section', 'sections', $admin);
    foreach ($formErrors as $err): ?>
    <div class="flash flash-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" class="card form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="grid-2">
            <?= field_text('title', 'Section title', $form['title'], ['required' => true, 'maxlength' => 40, 'placeholder' => 'Women']) ?>
            <label class="field"><span class="label">Layout</span>
                <select name="layout">
                    <?php foreach (SECTION_LAYOUTS as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $form['layout'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <?= field_check('is_active', 'Show on the site', (bool) $form['is_active']) ?>
        <div class="form-actions">
            <a class="btn" href="sections.php">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
    <?php
    admin_footer();
    exit;
}

$rows = db()->query('SELECT s.*, (SELECT COUNT(*) FROM tiles t WHERE t.section_id = s.id) AS tile_count,
        (SELECT image FROM tiles t WHERE t.section_id = s.id ORDER BY sort_order, id LIMIT 1) AS cover
    FROM sections s ORDER BY sort_order, id')->fetchAll();
admin_header('Sections', 'sections', $admin);
?>
<div class="toolbar">
    <p class="muted">The deals page shows these sections in order, each with its row or grid of tiles.</p>
    <a class="btn btn-primary" href="sections.php?action=new"><?= icon('plus') ?>Add section</a>
</div>

<?php if (!$rows): ?>
<div class="card empty">No sections yet. <a href="sections.php?action=new">Add the first one</a>.</div>
<?php else: ?>
<div class="card list">
    <?php foreach ($rows as $i => $r): ?>
    <div class="row<?= $r['is_active'] ? '' : ' is-hidden' ?>">
        <div class="thumb is-tile"><?php if ($r['cover']): ?><img src="<?= e(asset($r['cover'])) ?>" alt=""><?php endif; ?></div>
        <div class="row-main">
            <strong><?= e($r['title']) ?></strong>
            <span class="muted"><a href="tiles.php?section=<?= (int) $r['id'] ?>"><?= (int) $r['tile_count'] ?> tile<?= $r['tile_count'] == 1 ? '' : 's' ?></a> · <?= $r['layout'] === 'carousel' ? 'Swipeable row' : 'Grid' ?></span>
            <?php if (!$r['is_active']): ?><span class="badge">Hidden</span><?php endif; ?>
        </div>
        <div class="row-actions">
            <a class="btn btn-sm" href="tiles.php?section=<?= (int) $r['id'] ?>"><?= icon('tile') ?>Tiles</a>
            <?= $i > 0 ? action_button('move', (int) $r['id'], icon('up'), 'btn-icon', ['dir' => 'up']) : '<span class="btn-icon-spacer"></span>' ?>
            <?= $i < count($rows) - 1 ? action_button('move', (int) $r['id'], icon('down'), 'btn-icon', ['dir' => 'down']) : '<span class="btn-icon-spacer"></span>' ?>
            <?= action_button('toggle', (int) $r['id'], icon($r['is_active'] ? 'eye' : 'eye-off'), 'btn-icon') ?>
            <a class="btn btn-icon" href="sections.php?action=edit&amp;id=<?= (int) $r['id'] ?>" title="Edit" aria-label="Edit"><?= icon('edit') ?></a>
            <?= action_button('delete', (int) $r['id'], icon('trash'), 'btn-icon btn-danger') ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
