<?php
require_once __DIR__ . '/../inc/admin.php';
$admin = require_admin();

function find_product(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$brands = db()->query('SELECT id, name, is_active FROM brands ORDER BY sort_order, id')->fetchAll(PDO::FETCH_UNIQUE);
$brandFilter = (int) ($_GET['brand'] ?? 0);
if ($brandFilter && !isset($brands[$brandFilter])) {
    $brandFilter = 0;
}
$listUrl = 'products.php' . ($brandFilter ? '?brand=' . $brandFilter : '');

$form = null;
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'save') {
        $row = $id ? find_product($id) : null;
        $form = [
            'id'        => $row ? $id : 0,
            'brand_id'  => (int) post('brand_id'),
            'name'      => post('name'),
            'link'      => post('link'),
            'image'     => $row['image'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if (!isset($brands[$form['brand_id']])) {
            $formErrors[] = 'Choose a brand.';
        }
        if ($form['name'] === '') {
            $formErrors[] = 'Product name is required.';
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
            if ($row) {
                $sort = (int) $row['brand_id'] === $form['brand_id']
                    ? (int) $row['sort_order']
                    : next_sort_order('products', 'brand_id = ?', [$form['brand_id']]);
                db()->prepare('UPDATE products SET brand_id = ?, name = ?, link = ?, image = ?, is_active = ?, sort_order = ? WHERE id = ?')
                    ->execute([$form['brand_id'], $form['name'], $form['link'], $form['image'], $form['is_active'], $sort, $id]);
                flash('Product updated.');
            } else {
                db()->prepare('INSERT INTO products (brand_id, name, link, image, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$form['brand_id'], $form['name'], $form['link'], $form['image'], $form['is_active'],
                        next_sort_order('products', 'brand_id = ?', [$form['brand_id']])]);
                flash('Product added.');
            }
            redirect('products.php?brand=' . $form['brand_id']);
        }
    } else {
        $row = find_product($id);
        if ($row && $action === 'delete') {
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            delete_upload($row['image']);
            flash('Product deleted.');
        } elseif ($row && $action === 'toggle') {
            db()->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash($row['is_active'] ? '“' . $row['name'] . '” is now hidden.' : '“' . $row['name'] . '” is live again.');
        } elseif ($row && $action === 'move') {
            move_row('products', $id, post('dir'), 'brand_id = ?', [$row['brand_id']]);
        }
        redirect($listUrl);
    }
}

$action = $_GET['action'] ?? '';
if (!$form && $action === 'edit') {
    $form = find_product((int) ($_GET['id'] ?? 0));
    if (!$form) {
        flash('That product no longer exists.', 'error');
        redirect($listUrl);
    }
}
if (!$form && $action === 'new') {
    if (!$brands) {
        flash('Add a brand first — products belong to a brand.', 'warning');
        redirect('brands.php?action=new');
    }
    $form = ['id' => 0, 'brand_id' => $brandFilter ?: (int) array_key_first($brands), 'name' => '', 'link' => '', 'image' => '', 'is_active' => 1];
}

if ($form) {
    admin_header($form['id'] ? 'Edit product' : 'Add product', 'products', $admin);
    foreach ($formErrors as $err): ?>
    <div class="flash flash-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="card form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="grid-2">
            <label class="field"><span class="label">Brand</span>
                <select name="brand_id" required>
                    <?php foreach ($brands as $bid => $b): ?>
                    <option value="<?= (int) $bid ?>"<?= (int) $form['brand_id'] === (int) $bid ? ' selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?= field_text('name', 'Product name', $form['name'], ['required' => true, 'maxlength' => 40, 'placeholder' => 'Air Force']) ?>
        </div>
        <?= field_text('link', 'Link', $form['link'], ['placeholder' => 'https://www.flipkart.com/…']) ?>
        <?= field_image('image', 'Product photo', $form['image'], ['help' => 'Square, about 500 × 500, on a light background.']) ?>
        <?= field_check('is_active', 'Show on the site', (bool) $form['is_active']) ?>
        <div class="form-actions">
            <a class="btn" href="products.php?brand=<?= (int) $form['brand_id'] ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
    <?php
    admin_footer();
    exit;
}

$sql = 'SELECT p.*, b.name AS brand_name FROM products p JOIN brands b ON b.id = p.brand_id'
    . ($brandFilter ? ' WHERE p.brand_id = ' . $brandFilter : '')
    . ' ORDER BY b.sort_order, b.id, p.sort_order, p.id';
$rows = db()->query($sql)->fetchAll();
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['brand_id']][] = $r;
}

admin_header($brandFilter ? 'Products · ' . $brands[$brandFilter]['name'] : 'Products', 'products', $admin);
?>
<div class="toolbar">
    <div class="filter">
        <a class="chip<?= $brandFilter ? '' : ' is-active' ?>" href="products.php">All</a>
        <?php foreach ($brands as $bid => $b): ?>
        <a class="chip<?= $brandFilter === (int) $bid ? ' is-active' : '' ?>" href="products.php?brand=<?= (int) $bid ?>"><?= e($b['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <a class="btn btn-primary" href="products.php?action=new<?= $brandFilter ? '&amp;brand=' . $brandFilter : '' ?>"><?= icon('plus') ?>Add product</a>
</div>

<?php if (!$rows): ?>
<div class="card empty">No products here yet. <a href="products.php?action=new<?= $brandFilter ? '&amp;brand=' . $brandFilter : '' ?>">Add one</a>.</div>
<?php endif; ?>

<?php foreach ($grouped as $bid => $items): ?>
<section class="card list">
    <h2 class="list-title"><?= e($brands[$bid]['name']) ?> <span class="muted"><?= count($items) ?> product<?= count($items) === 1 ? '' : 's' ?> · first ~3 visible, the rest scroll</span></h2>
    <?php foreach ($items as $i => $r): ?>
    <div class="row<?= $r['is_active'] ? '' : ' is-hidden' ?>">
        <div class="thumb"><?php if ($r['image']): ?><img src="<?= e(asset($r['image'])) ?>" alt=""><?php endif; ?></div>
        <div class="row-main">
            <strong><?= e($r['name']) ?></strong>
            <span class="muted ellipsis"><?= e($r['link'] ?: 'No link') ?></span>
            <?php if (!$r['is_active']): ?><span class="badge">Hidden</span><?php endif; ?>
        </div>
        <div class="row-actions">
            <?= $i > 0 ? action_button('move', (int) $r['id'], icon('up'), 'btn-icon', ['dir' => 'up']) : '<span class="btn-icon-spacer"></span>' ?>
            <?= $i < count($items) - 1 ? action_button('move', (int) $r['id'], icon('down'), 'btn-icon', ['dir' => 'down']) : '<span class="btn-icon-spacer"></span>' ?>
            <?= action_button('toggle', (int) $r['id'], icon($r['is_active'] ? 'eye' : 'eye-off'), 'btn-icon') ?>
            <a class="btn btn-icon" href="products.php?action=edit&amp;id=<?= (int) $r['id'] ?>" title="Edit" aria-label="Edit"><?= icon('edit') ?></a>
            <?= action_button('delete', (int) $r['id'], icon('trash'), 'btn-icon btn-danger') ?>
        </div>
    </div>
    <?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php admin_footer(); ?>
