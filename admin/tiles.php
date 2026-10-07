<?php
require_once __DIR__ . '/../inc/admin.php';
$admin = require_admin();

function find_tile(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM tiles WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$sections = db()->query('SELECT id, title, layout, is_active FROM sections ORDER BY sort_order, id')->fetchAll(PDO::FETCH_UNIQUE);
$sectionFilter = (int) ($_GET['section'] ?? 0);
if ($sectionFilter && !isset($sections[$sectionFilter])) {
    $sectionFilter = 0;
}
$listUrl = 'tiles.php' . ($sectionFilter ? '?section=' . $sectionFilter : '');

$form = null;
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'save') {
        $row = $id ? find_tile($id) : null;
        $form = [
            'id'         => $row ? $id : 0,
            'section_id' => (int) post('section_id'),
            'title'      => post('title'),
            'tag'        => post('tag'),
            'link'       => post('link'),
            'image'      => $row['image'] ?? '',
            'is_active'  => isset($_POST['is_active']) ? 1 : 0,
        ];
        if (!isset($sections[$form['section_id']])) {
            $formErrors[] = 'Choose a section.';
        }
        if ($form['title'] === '') {
            $formErrors[] = 'Title is required.';
        }
        if ($err = link_error($form['link'])) {
            $formErrors[] = $err;
        }
        if (!$formErrors) {
            try {
                if ($new = upload_image('image')) {
                    delete_upload($form['image']);
                    $form['image'] = $new;
                }
            } catch (RuntimeException $ex) {
                $formErrors[] = $ex->getMessage();
            }
        }
        if (!$formErrors) {
            $values = [$form['section_id'], $form['title'], $form['tag'], $form['link'], $form['image'], $form['is_active']];
            if ($row) {
                $sort = (int) $row['section_id'] === $form['section_id']
                    ? (int) $row['sort_order']
                    : next_sort_order('tiles', 'section_id = ?', [$form['section_id']]);
                db()->prepare('UPDATE tiles SET section_id = ?, title = ?, tag = ?, link = ?, image = ?, is_active = ?, sort_order = ? WHERE id = ?')
                    ->execute([...$values, $sort, $id]);
                audit_log($admin, 'tile.updated', array_intersect_key($row, $form), $form);
                flash('Tile updated.');
            } else {
                db()->prepare('INSERT INTO tiles (section_id, title, tag, link, image, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([...$values, next_sort_order('tiles', 'section_id = ?', [$form['section_id']])]);
                audit_log($admin, 'tile.created', null, ['id' => (int) db()->lastInsertId()] + $form);
                flash('Tile added.');
            }
            redirect('tiles.php?section=' . $form['section_id']);
        }
    } else {
        $row = find_tile($id);
        if ($row && $action === 'delete') {
            db()->prepare('DELETE FROM tiles WHERE id = ?')->execute([$id]);
            delete_upload($row['image']);
            audit_log($admin, 'tile.deleted', $row);
            flash('Tile deleted.');
        } elseif ($row && $action === 'toggle') {
            db()->prepare('UPDATE tiles SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            audit_log($admin, $row['is_active'] ? 'tile.hidden' : 'tile.shown', null, ['id' => $id, 'title' => $row['title']]);
            flash($row['is_active'] ? '“' . $row['title'] . '” is now hidden.' : '“' . $row['title'] . '” is live again.');
        } elseif ($row && $action === 'move') {
            move_row('tiles', $id, post('dir'), 'section_id = ?', [$row['section_id']]);
        }
        redirect($listUrl);
    }
}

$action = $_GET['action'] ?? '';
if (!$form && $action === 'edit') {
    $form = find_tile((int) ($_GET['id'] ?? 0));
    if (!$form) {
        flash('That tile no longer exists.', 'error');
        redirect($listUrl);
    }
}
if (!$form && $action === 'new') {
    if (!$sections) {
        flash('Add a section first — tiles belong to a section.', 'warning');
        redirect('sections.php?action=new');
    }
    $form = ['id' => 0, 'section_id' => $sectionFilter ?: (int) array_key_first($sections), 'title' => '', 'tag' => '', 'link' => '', 'image' => '', 'is_active' => 1];
}

if ($form) {
    admin_header($form['id'] ? 'Edit tile' : 'Add tile', 'tiles', $admin);
    foreach ($formErrors as $err): ?>
    <div class="flash flash-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="card form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="grid-2">
            <label class="field"><span class="label">Section</span>
                <select name="section_id" required>
                    <?php foreach ($sections as $sid => $s): ?>
                    <option value="<?= (int) $sid ?>"<?= (int) $form['section_id'] === (int) $sid ? ' selected' : '' ?>><?= e($s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?= field_text('title', 'Title', $form['title'], ['required' => true, 'maxlength' => 40, 'placeholder' => 'Scarf Dress', 'help' => 'Shown in capitals under the photo.']) ?>
            <?= field_text('tag', 'Hashtag line (optional)', $form['tag'], ['maxlength' => 30, 'placeholder' => '#Quiet Luxury']) ?>
            <?= field_text('link', 'Link', $form['link'], ['placeholder' => 'https://www.flipkart.com/…']) ?>
        </div>
        <?= field_image('image', 'Photo', $form['image'], ['tile' => true, 'help' => 'Portrait photo, about 636 × 768 (or 424 × 512). It fills the top of the 424 × 640 tile; the caption bar is added automatically.']) ?>
        <?= field_check('is_active', 'Show on the site', (bool) $form['is_active']) ?>
        <div class="form-actions">
            <a class="btn" href="tiles.php?section=<?= (int) $form['section_id'] ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
    <?php
    admin_footer();
    exit;
}

$sql = 'SELECT t.* FROM tiles t JOIN sections s ON s.id = t.section_id'
    . ($sectionFilter ? ' WHERE t.section_id = ' . $sectionFilter : '')
    . ' ORDER BY s.sort_order, s.id, t.sort_order, t.id';
$rows = db()->query($sql)->fetchAll();
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['section_id']][] = $r;
}

admin_header($sectionFilter ? 'Tiles · ' . $sections[$sectionFilter]['title'] : 'Tiles', 'tiles', $admin);
?>
<div class="toolbar">
    <div class="filter">
        <a class="chip<?= $sectionFilter ? '' : ' is-active' ?>" href="tiles.php">All</a>
        <?php foreach ($sections as $sid => $s): ?>
        <a class="chip<?= $sectionFilter === (int) $sid ? ' is-active' : '' ?>" href="tiles.php?section=<?= (int) $sid ?>"><?= e($s['title']) ?></a>
        <?php endforeach; ?>
    </div>
    <a class="btn btn-primary" href="tiles.php?action=new<?= $sectionFilter ? '&amp;section=' . $sectionFilter : '' ?>"><?= icon('plus') ?>Add tile</a>
</div>

<?php if (!$rows): ?>
<div class="card empty">No tiles here yet. <a href="tiles.php?action=new<?= $sectionFilter ? '&amp;section=' . $sectionFilter : '' ?>">Add one</a>.</div>
<?php endif; ?>

<?php foreach ($grouped as $sid => $items): ?>
<section class="card list">
    <h2 class="list-title"><?= e($sections[$sid]['title']) ?> <span class="muted"><?= count($items) ?> tile<?= count($items) === 1 ? '' : 's' ?> · <?= $sections[$sid]['layout'] === 'carousel' ? 'swipeable row' : 'grid' ?></span></h2>
    <?php foreach ($items as $i => $r): ?>
    <div class="row<?= $r['is_active'] ? '' : ' is-hidden' ?>">
        <div class="thumb is-tile"><?php if ($r['image']): ?><img src="<?= e(asset($r['image'])) ?>" alt=""><?php endif; ?></div>
        <div class="row-main">
            <strong><?= e($r['title']) ?><?= $r['tag'] !== '' ? ' <span class="muted">' . e($r['tag']) . '</span>' : '' ?></strong>
            <span class="muted ellipsis"><?= e($r['link'] ?: 'No link') ?></span>
            <?php if (!$r['is_active']): ?><span class="badge">Hidden</span><?php endif; ?>
        </div>
        <div class="row-actions">
            <?= $i > 0 ? action_button('move', (int) $r['id'], icon('up'), 'btn-icon', ['dir' => 'up']) : '<span class="btn-icon-spacer"></span>' ?>
            <?= $i < count($items) - 1 ? action_button('move', (int) $r['id'], icon('down'), 'btn-icon', ['dir' => 'down']) : '<span class="btn-icon-spacer"></span>' ?>
            <?= action_button('toggle', (int) $r['id'], icon($r['is_active'] ? 'eye' : 'eye-off'), 'btn-icon') ?>
            <a class="btn btn-icon" href="tiles.php?action=edit&amp;id=<?= (int) $r['id'] ?>" title="Edit" aria-label="Edit"><?= icon('edit') ?></a>
            <?= action_button('delete', (int) $r['id'], icon('trash'), 'btn-icon btn-danger') ?>
        </div>
    </div>
    <?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php admin_footer(); ?>
