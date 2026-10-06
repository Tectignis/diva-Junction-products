<?php
/**
 * Admin-only helpers: layout, form fields and list ordering.
 */

require_once __DIR__ . '/auth.php';

function admin_header(string $title, string $active, array $admin): void
{
    require __DIR__ . '/../admin/partials/header.php';
}

function admin_footer(): void
{
    require __DIR__ . '/../admin/partials/footer.php';
}

/** Read a trimmed string from POST. */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Validate a link field; returns an error message or null. */
function link_error(string $url): ?string
{
    if ($url === '' || safe_link($url) === $url) {
        return null;
    }
    return 'Links must start with http://, https://, / or # (or be a page like shop.php).';
}

/**
 * Move a row one step up/down inside its list by swapping sort_order.
 * $scope limits the list (e.g. products of one brand).
 */
function move_row(string $table, int $id, string $dir, string $scopeSql = '', array $scopeArgs = []): void
{
    $allowed = ['featured', 'brands', 'products'];
    if (!in_array($table, $allowed, true)) {
        return;
    }
    $where = $scopeSql ? "WHERE $scopeSql" : '';
    $stmt = db()->prepare("SELECT id FROM $table $where ORDER BY sort_order, id");
    $stmt->execute($scopeArgs);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $pos = array_search($id, array_map('intval', $ids), true);
    if ($pos === false) {
        return;
    }
    $swap = $dir === 'up' ? $pos - 1 : $pos + 1;
    if ($swap < 0 || $swap >= count($ids)) {
        return;
    }
    [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

    $update = db()->prepare("UPDATE $table SET sort_order = ? WHERE id = ?");
    db()->beginTransaction();
    foreach ($ids as $i => $rowId) {
        $update->execute([$i + 1, $rowId]);
    }
    db()->commit();
}

function next_sort_order(string $table, string $scopeSql = '', array $scopeArgs = []): int
{
    $where = $scopeSql ? "WHERE $scopeSql" : '';
    $stmt = db()->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM $table $where");
    $stmt->execute($scopeArgs);
    return (int) $stmt->fetchColumn();
}

/** Inline stroke icon (24×24 grid). */
function icon(string $name): string
{
    static $paths = [
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'type'     => '<path d="M4 7V5h16v2M9 19h6M12 5v14"/>',
        'star'     => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        'sign'     => '<path d="M12 2 21 11 12 20 3 11z"/><circle cx="12" cy="11" r="3.2"/><path d="M12 20v2"/>',
        'bag'      => '<path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'logout'   => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 16l-4-4 4-4M6 12h10"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'up'       => '<path d="m6 15 6-6 6 6"/>',
        'down'     => '<path d="m6 9 6 6 6-6"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'  => '<path d="M3 3l18 18M10.6 5.1A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

/* ------------------------------------------------------------------ form fields */

function field_text(string $name, string $label, ?string $value, array $opt = []): string
{
    $type = $opt['type'] ?? 'text';
    $attrs = '';
    foreach (['placeholder', 'maxlength', 'min', 'max', 'step', 'autocomplete'] as $a) {
        if (isset($opt[$a])) {
            $attrs .= ' ' . $a . '="' . e((string) $opt[$a]) . '"';
        }
    }
    if (!empty($opt['required'])) {
        $attrs .= ' required';
    }
    $help = !empty($opt['help']) ? '<small class="help">' . e($opt['help']) . '</small>' : '';
    return '<label class="field"><span class="label">' . e($label) . '</span>'
        . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $attrs . '>'
        . $help . '</label>';
}

function field_textarea(string $name, string $label, ?string $value, array $opt = []): string
{
    $help = !empty($opt['help']) ? '<small class="help">' . e($opt['help']) . '</small>' : '';
    $rows = (int) ($opt['rows'] ?? 3);
    return '<label class="field"><span class="label">' . e($label) . '</span>'
        . '<textarea name="' . e($name) . '" rows="' . $rows . '">' . e($value) . '</textarea>'
        . $help . '</label>';
}

function field_image(string $name, string $label, ?string $current, array $opt = []): string
{
    $help = !empty($opt['help']) ? '<small class="help">' . e($opt['help']) . '</small>' : '';
    $preview = $current
        ? '<img src="' . e(asset($current)) . '" alt="">'
        : '<span class="image-empty">No image</span>';
    $reset = !empty($opt['resettable']) && $current !== ($opt['default'] ?? null)
        ? '<label class="check"><input type="checkbox" name="' . e($name) . '_reset" value="1"> Restore original artwork</label>'
        : '';
    $class = 'image-preview' . (!empty($opt['dark']) ? ' is-dark' : '');
    return '<div class="field field-image"><span class="label">' . e($label) . '</span>'
        . '<div class="image-row"><div class="' . $class . '" data-preview>' . $preview . '</div>'
        . '<div class="image-input"><input type="file" name="' . e($name) . '" accept="image/png,image/jpeg,image/webp,image/gif" data-preview-input>'
        . $help . $reset . '</div></div></div>';
}

function field_check(string $name, string $label, bool $checked): string
{
    return '<label class="check switch"><input type="checkbox" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '><span></span>' . e($label) . '</label>';
}

/** Small POST button form (toggle / move / delete). */
function action_button(string $action, int $id, string $label, string $class = 'btn-ghost', array $extra = []): string
{
    $hidden = '';
    foreach ($extra as $k => $v) {
        $hidden .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }
    $confirm = $action === 'delete' ? ' data-confirm="Click again to delete"' : '';
    $title = trim(strip_tags($label)) ?: match ($action) {
        'move'   => ($extra['dir'] ?? '') === 'up' ? 'Move up' : 'Move down',
        'toggle' => 'Show / hide on the site',
        'delete' => 'Delete',
        default  => ucfirst($action),
    };
    return '<form method="post" class="inline">' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">' . $hidden
        . '<button type="submit" class="btn ' . e($class) . '"' . $confirm . ' title="' . e($title) . '" aria-label="' . e($title) . '">' . $label . '</button></form>';
}
