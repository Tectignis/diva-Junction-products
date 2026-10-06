<?php
require_once __DIR__ . '/../inc/admin.php';
$admin = require_admin();

const SIGN_SIDES = ['auto' => 'Alternate automatically', 'left' => 'Sign on the left', 'right' => 'Sign on the right'];

function find_brand(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM brands WHERE id = ?');
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
        $row = $id ? find_brand($id) : null;
        $form = [
            'id'        => $row ? $id : 0,
            'name'      => post('name'),
            'sign_side' => array_key_exists(post('sign_side'), SIGN_SIDES) ? post('sign_side') : 'auto',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($form['name'] === '') {
            $formErrors[] = 'Brand name is required.';
        }
        if (!$formErrors) {
            if ($row) {
                db()->prepare('UPDATE brands SET name = ?, sign_side = ?, is_active = ? WHERE id = ?')
                    ->execute([$form['name'], $form['sign_side'], $form['is_active'], $id]);
                flash('Brand updated.');
                redirect('brands.php');
            }
            db()->prepare('INSERT INTO brands (name, sign_side, is_active, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([$form['name'], $form['sign_side'], $form['is_active'], next_sort_order('brands')]);
            flash('Brand added — now add its products.');
            redirect('products.php?brand=' . db()->lastInsertId());
        }
    } else {
        if ($action === 'delete' && ($row = find_brand($id))) {
            $images = db()->prepare('SELECT image FROM products WHERE brand_id = ?');
            $images->execute([$id]);
            $files = $images->fetchAll(PDO::FETCH_COLUMN);
            db()->prepare('DELETE FROM brands WHERE id = ?')->execute([$id]); // products cascade
            array_map('delete_upload', $files);
            flash('Brand “' . $row['name'] . '” and its products were deleted.');
        } elseif ($action === 'toggle' && ($row = find_brand($id))) {
            db()->prepare('UPDATE brands SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash($row['is_active'] ? '“' . $row['name'] . '” is now hidden.' : '“' . $row['name'] . '” is live again.');
        } elseif ($action === 'move') {
            move_row('brands', $id, post('dir'));
        }
        redirect('brands.php');
    }
}

$action = $_GET['action'] ?? '';
if (!$form && $action === 'edit') {
    $form = find_brand((int) ($_GET['id'] ?? 0));
    if (!$form) {
        flash('That brand no longer exists.', 'error');
        redirect('brands.php');
    }
}
if (!$form && $action === 'new') {
    $form = ['id' => 0, 'name' => '', 'sign_side' => 'auto', 'is_active' => 1];
}

if ($form) {
    admin_header($form['id'] ? 'Edit brand' : 'Add brand', 'brands', $admin);
    foreach ($formErrors as $err): ?>
    <div class="flash flash-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" class="card form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="grid-2">
            <?= field_text('name', 'Station name (brand)', $form['name'], ['required' => true, 'maxlength' => 24, 'placeholder' => 'Kay Beauty', 'help' => 'Shown on the station sign. Up to 10 characters fit at full size.']) ?>
            <label class="field"><span class="label">Sign position</span>
                <select name="sign_side">
                    <?php foreach (SIGN_SIDES as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $form['sign_side'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="station-preview" aria-hidden="true">
            <span class="sp-diamond"></span><span class="sp-ring"></span><span class="sp-name" data-mirror="name"><?= e($form['name'] ?: 'Brand') ?></span>
        </div>
        <?= field_check('is_active', 'Show on the site', (bool) $form['is_active']) ?>
        <div class="form-actions">
            <a class="btn" href="brands.php">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
    <?php
    admin_footer();
    exit;
}

$rows = db()->query('SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id) AS product_count FROM brands b ORDER BY sort_order, id')->fetchAll();
admin_header('Brands', 'brands', $admin);
?>
<div class="toolbar">
    <p class="muted">Each brand is a station on “Shop by Brands”, with its products beside the sign.</p>
    <a class="btn btn-primary" href="brands.php?action=new"><?= icon('plus') ?>Add brand</a>
</div>

<?php if (!$rows): ?>
<div class="card empty">No brands yet. <a href="brands.php?action=new">Add the first one</a>.</div>
<?php else: ?>
<div class="card list">
    <?php foreach ($rows as $i => $r): ?>
    <div class="row<?= $r['is_active'] ? '' : ' is-hidden' ?>">
        <div class="thumb station-thumb"><span><?= e($r['name']) ?></span></div>
        <div class="row-main">
            <strong><?= e($r['name']) ?></strong>
            <span class="muted"><a href="products.php?brand=<?= (int) $r['id'] ?>"><?= (int) $r['product_count'] ?> product<?= $r['product_count'] == 1 ? '' : 's' ?></a> · <?= e(SIGN_SIDES[$r['sign_side']] ?? '') ?></span>
            <?php if (!$r['is_active']): ?><span class="badge">Hidden</span><?php endif; ?>
        </div>
        <div class="row-actions">
            <a class="btn btn-sm" href="products.php?brand=<?= (int) $r['id'] ?>"><?= icon('bag') ?>Products</a>
            <?= $i > 0 ? action_button('move', (int) $r['id'], icon('up'), 'btn-icon', ['dir' => 'up']) : '<span class="btn-icon-spacer"></span>' ?>
            <?= $i < count($rows) - 1 ? action_button('move', (int) $r['id'], icon('down'), 'btn-icon', ['dir' => 'down']) : '<span class="btn-icon-spacer"></span>' ?>
            <?= action_button('toggle', (int) $r['id'], icon($r['is_active'] ? 'eye' : 'eye-off'), 'btn-icon') ?>
            <a class="btn btn-icon" href="brands.php?action=edit&amp;id=<?= (int) $r['id'] ?>" title="Edit" aria-label="Edit"><?= icon('edit') ?></a>
            <?= action_button('delete', (int) $r['id'], icon('trash'), 'btn-icon btn-danger') ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
