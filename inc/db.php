<?php
/**
 * SQLite connection + first-run schema/seed.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/settings_schema.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $fresh = !is_file(DB_FILE);
    if (!is_dir(dirname(DB_FILE))) {
        mkdir(dirname(DB_FILE), 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    migrate($pdo);
    if ($fresh) {
        seed($pdo);
    }
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS admins (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS featured (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            title      TEXT NOT NULL,
            brand      TEXT NOT NULL DEFAULT '',
            deal_tag   TEXT NOT NULL DEFAULT '',
            image      TEXT NOT NULL DEFAULT '',
            link       TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active  INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS brands (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL,
            sign_side  TEXT NOT NULL DEFAULT 'auto',
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active  INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS products (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            brand_id   INTEGER NOT NULL REFERENCES brands(id) ON DELETE CASCADE,
            name       TEXT NOT NULL,
            image      TEXT NOT NULL DEFAULT '',
            link       TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active  INTEGER NOT NULL DEFAULT 1
        );
        CREATE INDEX IF NOT EXISTS idx_products_brand ON products(brand_id, sort_order);
    ");

    // Make sure every known setting exists (new settings added later get their default).
    $insert = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach (settings_schema() as $group) {
        foreach ($group['fields'] as $key => $field) {
            $insert->execute([$key, $field['default']]);
        }
    }
}

function seed(PDO $pdo): void
{
    $pdo->beginTransaction();

    $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')
        ->execute([DEFAULT_ADMIN_USER, password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT)]);

    // Countdown starts 2h20m from install and repeats every 3 hours.
    $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'countdown_end'")
        ->execute([date('Y-m-d\TH:i', time() + 2 * 3600 + 20 * 60)]);

    $fk = 'https://www.flipkart.com/search?q=';
    $featured = [
        ['Sneakers', 'Adidas Originals', 'deal tag', 'uploads/feat-sneakers.png', $fk . 'adidas+originals+sneakers'],
        ['Watches', 'Fastrack', 'deal tag', 'uploads/feat-watches.png', $fk . 'fastrack+watches+women'],
        ['Apparels', 'The Souled Store', 'deal tag', 'uploads/feat-apparels.png', $fk . 'the+souled+store+t+shirt'],
    ];
    $stmt = $pdo->prepare('INSERT INTO featured (title, brand, deal_tag, image, link, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($featured as $i => $row) {
        $stmt->execute([...$row, $i + 1]);
    }

    $brands = [
        'Kay Beauty' => [
            ['Illuminating Primer', 'uploads/kay-primer.webp', $fk . 'kay+beauty+primer'],
            ['Foundation', 'uploads/kay-foundation.webp', $fk . 'kay+beauty+foundation'],
            ['Blush', 'uploads/kay-blush.webp', $fk . 'kay+beauty+blush'],
        ],
        'Nike' => [
            ['Air Force', 'uploads/nike-airforce.webp', $fk . 'nike+air+force'],
            ['Dunk Low', 'uploads/nike-dunk.webp', $fk . 'nike+dunk+low'],
            ['Air Jordan', 'uploads/nike-jordan.webp', $fk . 'nike+air+jordan'],
        ],
        'H&M' => [
            ['Topwear', 'uploads/hm-topwear.webp', $fk . 'h%26m+women+topwear'],
            ['Bottoms', 'uploads/hm-bottoms.webp', $fk . 'h%26m+women+jeans'],
            ['Dresses', 'uploads/hm-dresses.webp', $fk . 'h%26m+women+dresses'],
        ],
    ];
    $brandStmt = $pdo->prepare('INSERT INTO brands (name, sort_order) VALUES (?, ?)');
    $prodStmt  = $pdo->prepare('INSERT INTO products (brand_id, name, image, link, sort_order) VALUES (?, ?, ?, ?, ?)');
    $order = 0;
    foreach ($brands as $name => $products) {
        $brandStmt->execute([$name, ++$order]);
        $brandId = (int) $pdo->lastInsertId();
        foreach ($products as $i => $p) {
            $prodStmt->execute([$brandId, $p[0], $p[1], $p[2], $i + 1]);
        }
    }

    $pdo->commit();
}
