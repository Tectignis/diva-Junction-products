<?php
/**
 * SQLite connection, schema migrations and first-run seed.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/settings_schema.php';

/** Bump when migrate() gains a new versioned step. */
const SCHEMA_VERSION = 3;

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
    $pdo->exec('PRAGMA busy_timeout = 5000');

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
        CREATE TABLE IF NOT EXISTS sections (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            title      TEXT NOT NULL,
            layout     TEXT NOT NULL DEFAULT 'grid',
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active  INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS tiles (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            section_id INTEGER NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
            title      TEXT NOT NULL,
            tag        TEXT NOT NULL DEFAULT '',
            image      TEXT NOT NULL DEFAULT '',
            link       TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active  INTEGER NOT NULL DEFAULT 1
        );
        CREATE INDEX IF NOT EXISTS idx_tiles_section ON tiles(section_id, sort_order);

        CREATE TABLE IF NOT EXISTS geofence_settings (
            id                    INTEGER PRIMARY KEY CHECK (id = 1),
            enabled               INTEGER NOT NULL DEFAULT 0,
            location_name         TEXT NOT NULL DEFAULT '',
            latitude              REAL,
            longitude             REAL,
            radius_meters         INTEGER NOT NULL DEFAULT 500,
            accuracy_limit_meters INTEGER NOT NULL DEFAULT 100,
            session_minutes       INTEGER NOT NULL DEFAULT 30,
            log_retention_days    INTEGER NOT NULL DEFAULT 30,
            maps_url              TEXT NOT NULL DEFAULT '',
            updated_by            INTEGER,
            updated_at            INTEGER
        );
        INSERT OR IGNORE INTO geofence_settings (id) VALUES (1);

        CREATE TABLE IF NOT EXISTS location_access_logs (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            session_ref     TEXT NOT NULL DEFAULT '',
            latitude        REAL,
            longitude       REAL,
            accuracy        REAL,
            distance_meters REAL,
            result          TEXT NOT NULL,
            reason          TEXT NOT NULL DEFAULT '',
            ip_hash         TEXT NOT NULL DEFAULT '',
            user_agent      TEXT NOT NULL DEFAULT '',
            created_at      INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_access_created ON location_access_logs(created_at);
        CREATE INDEX IF NOT EXISTS idx_access_ip ON location_access_logs(ip_hash, created_at);

        CREATE TABLE IF NOT EXISTS admin_audit_logs (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id   INTEGER,
            admin_name TEXT NOT NULL DEFAULT '',
            action     TEXT NOT NULL,
            old_value  TEXT,
            new_value  TEXT,
            ip_address TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_audit_created ON admin_audit_logs(created_at);
    ");

    // Make sure every known setting exists (new settings added later get their default).
    $insert = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach (settings_schema() as $group) {
        foreach ($group['fields'] as $key => $field) {
            $insert->execute([$key, $field['default']]);
        }
    }
    // Private key for hashing visitor IPs in the access log (never shown in the admin).
    $insert->execute(['app_secret', bin2hex(random_bytes(32))]);

    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version < 2) {
        // v2: the product page became a Flipkart-style store (sections of tiles).
        // The old featured/brands/products tables are no longer read.
        $pdo->exec("DELETE FROM settings WHERE key IN ('featured_title', 'brands_title')");
        $pdo->exec("UPDATE settings SET value = '#deals' WHERE key = 'hero_cta_link' AND value = '#featured'");
        if (!(int) $pdo->query('SELECT COUNT(*) FROM sections')->fetchColumn()) {
            seed_store($pdo);
        }
    }
    if ($version < 3) {
        migrate_legacy_geofence($pdo);
    }
    if ($version < SCHEMA_VERSION) {
        $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
    }
}

/**
 * v3: the first live build kept the location lock as geofence_* key/value settings,
 * an "Explore More" link and its own geofence_audit_log table. Move those values
 * into geofence_settings / banner_link / admin_audit_logs, then drop the old keys.
 */
function migrate_legacy_geofence(PDO $pdo): void
{
    $legacy = $pdo->query("SELECT key, value FROM settings WHERE key LIKE 'geofence\\_%' ESCAPE '\\' OR key = 'explore_more_link'")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    $pdo->beginTransaction();
    $row = $pdo->query('SELECT latitude, updated_at FROM geofence_settings WHERE id = 1')->fetch();
    $lat = $legacy['geofence_latitude'] ?? '';
    $lng = $legacy['geofence_longitude'] ?? '';
    // Only fill a lock that was never configured in the new admin.
    if ($row && $row['latitude'] === null && $row['updated_at'] === null && is_numeric($lat) && is_numeric($lng)) {
        $clamp = fn($v, int $min, int $max, int $def) => is_numeric($v) ? max($min, min($max, (int) $v)) : $def;
        $pdo->prepare('UPDATE geofence_settings SET enabled = ?, location_name = ?, latitude = ?, longitude = ?,
                radius_meters = ?, accuracy_limit_meters = ? WHERE id = 1')
            ->execute([
                ($legacy['geofence_enabled'] ?? '0') === '1' ? 1 : 0,
                mb_substr(trim((string) ($legacy['geofence_location_name'] ?? '')), 0, 120),
                round((float) $lat, 7),
                round((float) $lng, 7),
                $clamp($legacy['geofence_radius_meters'] ?? null, 10, 50000, 500),
                $clamp($legacy['geofence_accuracy_limit_meters'] ?? null, 5, 5000, 100),
            ]);
    }

    if (trim($legacy['explore_more_link'] ?? '') !== '') {
        $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'banner_link'")->execute([trim($legacy['explore_more_link'])]);
    }

    $hasOldLog = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'geofence_audit_log'")->fetchColumn();
    if ($hasOldLog) {
        $fields = [
            'Location Restriction' => 'enabled',
            'Location Name'        => 'location_name',
            'Latitude'             => 'latitude',
            'Longitude'            => 'longitude',
            'Allowed Radius'       => 'radius_meters',
            'GPS Accuracy Limit'   => 'accuracy_limit_meters',
        ];
        $admins = $pdo->query('SELECT username, id FROM admins')->fetchAll(PDO::FETCH_KEY_PAIR);
        $insert = $pdo->prepare('INSERT INTO admin_audit_logs (admin_id, admin_name, action, old_value, new_value, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($pdo->query('SELECT * FROM geofence_audit_log ORDER BY id') as $log) {
            $field = $fields[$log['field']] ?? $log['field'];
            $action = $field === 'enabled' ? ($log['new_value'] === '1' ? 'geofence.enabled' : 'geofence.disabled') : 'geofence.updated';
            $encode = fn($v) => json_encode([$field => $field === 'enabled' ? $v === '1' : $v], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $insert->execute([
                $admins[$log['admin']] ?? null, $log['admin'], $action,
                $encode($log['old_value']), $encode($log['new_value']),
                $log['ip_address'], strtotime($log['created_at'] . ' UTC') ?: time(), // CURRENT_TIMESTAMP is UTC
            ]);
        }
        $pdo->exec('DROP TABLE geofence_audit_log');
    }

    $pdo->exec("DELETE FROM settings WHERE key LIKE 'geofence\\_%' ESCAPE '\\' OR key = 'explore_more_link'");
    $pdo->commit();
}

function seed(PDO $pdo): void
{
    $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')
        ->execute([DEFAULT_ADMIN_USER, password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT)]);

    // Countdown starts 2h20m from install and repeats every 3 hours.
    $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'countdown_end'")
        ->execute([date('Y-m-d\TH:i', time() + 2 * 3600 + 20 * 60)]);
}

/** Store content from the client's "Diva Jn Deals Store Wireframe" sheet. */
function seed_store(PDO $pdo): void
{
    $fk = 'https://www.flipkart.com/';
    $sections = [
        ['Featured', 'carousel', [
            ['Scarf Dress', '#Quiet Luxury', 'feat-scarf-dress.webp', $fk . 'bebe-women-gown-brown-maxi-full-length-dress/p/itm0d6e02a20ea70?pid=DREHHEU7ACMKC7JY&lid=LSTDREHHEU7ACMKC7JYCJXC21&marketplace=FLIPKART'],
            ["L'Oréal Lipstick", '#Matte', 'feat-loreal-lipstick.webp', $fk . 'product/p/item?pid=LSKHEAY6GE2M8GJY'],
            ['Nike W Court Vision', '#Chunky Sneaker', 'feat-nike-court-vision.webp', $fk . 'nike-promina-walking-shoes-women/p/itm34858d9df8fc6?pid=SHOHG62ZPZHATGGR&lid=LSTSHOHQFZF2MGJ8KFTXQ4RWH&marketplace=FLIPKART'],
            ['Moxie Hair Conditioner', '#Indie Favourites', 'feat-moxie-conditioner.webp', $fk . 'product/p/item?pid=CNDGZH6YWHYBYZGE'],
            ['Maybelline Mascara', '#Big Lash Energy', 'feat-maybelline-mascara.webp', $fk . 'product/p/item?pid=MCRGFU8Y8NTFMYSP'],
            ['Watches', '#Vintage Watch', 'feat-vintage-watch.webp', $fk . 'carlton-london-cecil-analog-watch-women/p/itmf7e137ab590b0?pid=WATHK7CUDQAMB73X&lid=LSTWATHK7CUDQAMB73XSG4AZQ&marketplace=FLIPKART'],
            ['Etude Lip Tint', '#K Beauty', 'feat-etude-lip-tint.webp', $fk . 'product/p/item?pid=LSQHZV4AZ3UKANH8'],
            ['Guess Handbag', '#Global Fav', 'feat-guess-handbag.webp', $fk . 'guess-women-red-shoulder-bag/p/itmba456f5cafd2c?pid=HMBHFGC7J9AN8CV3&lid=LSTHMBHFGC7J9AN8CV3FRFOS4&marketplace=FLIPKART'],
        ]],
        ['Women', 'grid', [
            ['Sneakers', '', 'tile-sneakers.webp', $fk . 'womens-footwear/~cs-dzpg68a9yq/pr?sid=osp,iko,sx7&p%5B%5D=facets.ideal_for%255B%255D%3DUnisex&p%5B%5D=facets.ideal_for%255B%255D%3DWomen&sort=price_desc&param=2378582&BU=LifeStyle'],
            ['K Beauty', '', 'tile-k-beauty.webp', $fk . 'g9b/~cs-otv3sj9qx2/pr?sid=g9b&collection-tab-name=K-Beauty'],
            ['Makeup', '', 'tile-makeup.webp', $fk . 'beauty-and-grooming/~cs-xbibpw8w7a/pr?sid=g9b&collection-tab-name=Makeup'],
            ['Haircare', '', 'tile-haircare.webp', $fk . 'g9b/~cs-o1bk5a4pf0/pr?sid=g9b&collection-tab-name=Haircare'],
            ['Casual Footwear', '', 'tile-casual-footwear.webp', $fk . 'gnist-women-heels/p/itmfac1176c3ba7f?pid=SNDHMJYYZREHF4AX&lid=LSTSNDHMJYYZREHF4AX7SPUYY&marketplace=FLIPKART'],
            ['Handbags', '', 'tile-handbags.webp', $fk . 'bags-wallets-belts/bags/~cs-j7ondzkjn6/pr?sid=reh%2Cihu&otracker=categorytree&p%5B%5D=facets.brand%255B%255D%3DZOUK&p%5B%5D=facets.brand%255B%255D%3DLAVIE&p%5B%5D=facets.brand%255B%255D%3DFastrack&p%5B%5D=facets.brand%255B%255D%3DMiraggio&p%5B%5D=facets.brand%255B%255D%3DLINO%2BPERROS&p%5B%5D=facets.brand%255B%255D%3DMOCHI&p%5B%5D=facets.brand%255B%255D%3DCaprese&p%5B%5D=facets.brand%255B%255D%3DAllen%2BSolly&p%5B%5D=facets.brand%255B%255D%3DVAN%2BHEUSEN&p%5B%5D=facets.brand%255B%255D%3DMast%2B%2526%2BHarbour'],
            ['Watches', '', 'tile-watches.webp', $fk . 'watches/~cs-v5ufkn30xb/pr?sid=r18,f13&collection-tab-name=Women%20watches'],
            ['Skincare', '', 'tile-skincare.webp', $fk . 'g9b/~cs-mbciytb35u/pr?sid=g9b&collection-tab-name=Skincare'],
            ['Sunglasses', '', 'tile-sunglasses.webp', $fk . 'sunglasses/~cs-wevb18594i/pr?sid=26x&collection-tab-name=Women%20Sunglasses'],
            ['Topwear and Dresses', '', 'tile-topwear-dresses.webp', $fk . 'clothing-and-accessories/~cs-893239wgov/pr?sid=clo&collection-tab-name=Dresses%2Ctops%2Ct-shirts&p%5B%5D=facets.ideal_for%255B%255D%3DWomen&p%5B%5D=facets.brand%255B%255D%3DMiss%2BChase&p%5B%5D=facets.brand%255B%255D%3DTokyo%2BTalkies&p%5B%5D=facets.brand%255B%255D%3DSASSAFRAS&p%5B%5D=facets.brand%255B%255D%3DZUMMER&p%5B%5D=facets.brand%255B%255D%3DSTREET9&p%5B%5D=facets.brand%255B%255D%3DATHENA&p%5B%5D=facets.brand%255B%255D%3DBEWAKOOF&p%5B%5D=facets.brand%255B%255D%3DSTYLESTONE&p%5B%5D=facets.brand%255B%255D%3DThe%2BSouled%2BStore&p%5B%5D=facets.brand%255B%255D%3DPINACOLADA&p%5B%5D=facets.brand%255B%255D%3DRARE&p%5B%5D=facets.brand%255B%255D%3DOUTZIDR&p%5B%5D=facets.brand%255B%255D%3DGlobus&p%5B%5D=facets.brand%255B%255D%3DJUNEBERRY&p%5B%5D=facets.brand%255B%255D%3DSHOWOFFFF&p%5B%5D=facets.brand%255B%255D%3DStyle%2BQuotient&p%5B%5D=facets.brand%255B%255D%3DFABLE%2BSTREET&p%5B%5D=facets.brand%255B%255D%3DONLY&sort=recency_desc'],
            ['Bottomwear', '', 'tile-bottomwear.webp', $fk . 'clothing-and-accessories/bottomwear/~cs-cgdk1osh4t/pr?sid=clo%2Cvua&collection-tab-name=Jeans%2Ctrousers%2CSkirts&p%5B%5D=facets.ideal_for%255B%255D%3DWomen&p%5B%5D=facets.brand%255B%255D%3DMiss%2BChase&p%5B%5D=facets.brand%255B%255D%3DLEVI%2527S&p%5B%5D=facets.brand%255B%255D%3DTokyo%2BTalkies&p%5B%5D=facets.brand%255B%255D%3DSASSAFRAS&p%5B%5D=facets.brand%255B%255D%3DDOLCE%2BCRUDO&p%5B%5D=facets.brand%255B%255D%3DFLYING%2BMACHINE&p%5B%5D=facets.brand%255B%255D%3DMAX&p%5B%5D=facets.brand%255B%255D%3DUrbano%2BFashion&p%5B%5D=facets.brand%255B%255D%3DPepe%2BJeans&p%5B%5D=facets.brand%255B%255D%3DAllen%2BSolly&p%5B%5D=facets.brand%255B%255D%3DSTREET9&p%5B%5D=facets.brand%255B%255D%3DKILLER&p%5B%5D=facets.brand%255B%255D%3DFreakins&p%5B%5D=facets.brand%255B%255D%3DATHENA&p%5B%5D=facets.brand%255B%255D%3DStyle%2BQuotient&p%5B%5D=facets.brand%255B%255D%3DONLY&p%5B%5D=facets.brand%255B%255D%3DFABLE%2BSTREET&sort=recency_desc'],
            ['Korean Fashion', '', 'tile-korean-fashion.webp', $fk . 'korean-store?param=5632790000&ctx=eyJjYXJkQ29udGV4dCI6eyJhdHRyaWJ1dGVzIjp7InRpdGxlIjp7Im11bHRpVmFsdWVkQXR0cmlidXRlIjp7ImtleSI6InRpdGxlIiwiaW5mZXJlbmNlVHlwZSI6IlRJVExFIiwidmFsdWVzIjpbIktvcmVhbiBTdG9yZSJdLCJ2YWx1ZVR5cGUiOiJNVUxUSV9WQUxVRUQifX19fX0%3D'],
            ['Fragrance', '', 'tile-fragrance.webp', $fk . 'g9b/~cs-uyxou2u4uu/pr?sid=g9b&collection-tab-name=Fragrances'],
        ]],
    ];

    $sectionStmt = $pdo->prepare('INSERT INTO sections (title, layout, sort_order) VALUES (?, ?, ?)');
    $tileStmt = $pdo->prepare('INSERT INTO tiles (section_id, title, tag, image, link, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    $pdo->beginTransaction();
    foreach ($sections as $s => [$title, $layout, $tiles]) {
        $sectionStmt->execute([$title, $layout, $s + 1]);
        $sectionId = (int) $pdo->lastInsertId();
        foreach ($tiles as $t => [$name, $tag, $image, $link]) {
            $tileStmt->execute([$sectionId, $name, $tag, 'uploads/' . $image, $link, $t + 1]);
        }
    }
    $pdo->commit();
}
