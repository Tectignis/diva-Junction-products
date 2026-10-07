<?php
/**
 * Diva Junction — global configuration.
 */

define('APP_ROOT', __DIR__);
defined('DB_FILE') || define('DB_FILE', APP_ROOT . '/data/diva.sqlite'); // tests point this at a temp file
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('MAX_UPLOAD_BYTES', 4 * 1024 * 1024); // 4 MB

// Credentials used only when the database is created for the first time.
// Change the password from Admin → Account right after the first login.
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123');

// Public location checks allowed per window: per browser session, and per IP address
// (the IP limit is higher because visitors on the venue Wi-Fi or a mobile network share IPs).
define('GEO_RATE_WINDOW', 600); // seconds
define('GEO_RATE_LIMIT', 10);
define('GEO_RATE_LIMIT_IP', 150);

date_default_timezone_set('Asia/Kolkata');
