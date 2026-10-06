<?php
require_once __DIR__ . '/../inc/admin.php';
$admin = require_admin();

function find_featured(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM featured WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$form = null;       // row being edited / created
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'save') {
        $row = $id ? find_featured($id) : null;
        $form = [
            'id'        => $row ? $id : 0,
            'title'     => post('title'),
            'brand'     => post('brand'),
            'deal_tag'  => post('deal_tag'),
            'link'      => post('link'),
            'image'     => $row['image'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($form['title'] === '') {
            $formErrors[] = 'Category title is required.';
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
            $values = [$form['title'], $form['brand'], $form['deal_tag'], $form['link'], $form['image'], $form['is_active']];
            if ($row) {
                db()->prepare('UPDATE featured SET title = ?, brand = ?, deal_tag = ?, link = ?, image = ?, is_active = ? WHERE id = ?')
                    ->execute([...$values, $id]);
                flash('Featured item updated.');
            } else {
                db()->prepare('INSERT INTO featured (title, brand, deal_tag, link, image, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([...$values, next_sort_order('featured')]);
                flash('Featured item added.');
            }
            redirect('featured.php');
        }
    } else {
        if ($action === 'delete' && ($row = find_featured($id))) {
            db()->prepare('DELETE FROM featured WHERE id = ?')->execute([$id]);
            delete_upload($row['image']);
            flash('Featured item deleted.');
        } elseif ($action === 'toggle' && ($row = find_featured($id))) {
            db()->prepare('UPDATE featured SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash($row['is_active'] ? '“' . $row['title'] . '” is now hidden.' : '“' . $row['title'] . '” is live again.');
        } elseif ($action === 'move') {
            move_row('featured', $id, post('dir'));
        }
        redirect('featured.php');
    }
}

$action = $_GET['action'] ?? '';
if (!$form && $action === 'edit') {
    $form = find_featured((int) ($_GET['id'] ?? 0));
    if (!$form) {
        flash('That item no longer exists.', 'error');
        redirect('featured.php');
    }
}
if (!$form && $action === 'new') {
    $form = ['id' => 0, 'title' => '', 'brand' => '', 'deal_tag' => 'deal tag', 'link' => '', 'image' => '', 'is_active' => 1];
}

if ($form) {
    admin_header($form['id'] ? 'Edit featured item' : 'Add featured item', 'featured', $admin);
    foreach ($formErrors as $err): ?>
    <div class="flash flash-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="card form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="grid-2">
            <?= field_text('title', 'Category (blue label)', $form['title'], ['required' => true, 'maxlength' => 40, 'placeholder' => 'Sneakers']) ?>
            <?= field_text('brand', 'Brand (on the pink band)', $form['brand'], ['maxlength' => 40, 'placeholder' => 'Adidas Originals']) ?>
            <?= field_text('deal_tag', 'Deal tag (pill)', $form['deal_tag'], ['maxlength' => 30, 'placeholder' => 'Min. 40% off', 'help' => 'Leave empty to hide the pill.']) ?>
            <?= field_text('link', 'Link', $form['link'], ['placeholder' => 'https://www.flipkart.com/…']) ?>
        </div>
        <?= field_image('image', 'Product image', $form['image'], ['dark' => true, 'help' => 'Square, about 600 × 600. A transparent PNG looks best — it sits on the blue card.']) ?>
        <?= field_check('is_active', 'Show on the site', (bool) $form['is_active']) ?>
        <div class="form-actions">
            <a class="btn" href="featured.php">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
    <?php
    admin_footer();
    exit;
}

$rows = db()->query('SELECT * FROM featured ORDER BY sort_order, id')->fetchAll();
admin_header('Featured', 'featured', $admin);
?>
<div class="toolbar">
    <p class="muted">Cards in the pink “Featured” band. Three fit on screen; more become swipeable.</p>
    <a class="btn btn-primary" href="featured.php?action=new"><?= icon('plus') ?>Add item</a>
</div>

<?php if (!$rows): ?>
<div class="card empty">No featured items yet. <a href="featured.php?action=new">Add the first one</a>.</div>
<?php else: ?>
<div class="card list">
    <?php foreach ($rows as $i => $r): ?>
    <div class="row<?= $r['is_active'] ? '' : ' is-hidden' ?>">
        <div class="thumb is-blue"><?php if ($r['image']): ?><img src="<?= e(asset($r['image'])) ?>" alt=""><?php endif; ?></div>
        <div class="row-main">
            <strong><?= e($r['title']) ?></strong>
            <span class="muted"><?= e($r['brand']) ?><?= $r['deal_tag'] !== '' ? ' · ' . e($r['deal_tag']) : '' ?></span>
            <?php if (!$r['is_active']): ?><span class="badge">Hidden</span><?php endif; ?>
        </div>
        <div class="row-actions">
            <?= $i > 0 ? action_button('move', (int) $r['id'], icon('up'), 'btn-icon', ['dir' => 'up']) : '<span class="btn-icon-spacer"></span>' ?>
            <?= $i < count($rows) - 1 ? action_button('move', (int) $r['id'], icon('down'), 'btn-icon', ['dir' => 'down']) : '<span class="btn-icon-spacer"></span>' ?>
            <?= action_button('toggle', (int) $r['id'], icon($r['is_active'] ? 'eye' : 'eye-off'), 'btn-icon') ?>
            <a class="btn btn-icon" href="featured.php?action=edit&amp;id=<?= (int) $r['id'] ?>" title="Edit"><?= icon('edit') ?></a>
            <?= action_button('delete', (int) $r['id'], icon('trash'), 'btn-icon btn-danger') ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
