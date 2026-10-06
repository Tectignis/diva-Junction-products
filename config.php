<?php
/**
 * Diva Junction — global configuration.
 */

define('APP_ROOT', __DIR__);
define('DB_FILE', APP_ROOT . '/data/diva.sqlite');
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('MAX_UPLOAD_BYTES', 4 * 1024 * 1024); // 4 MB

// Credentials used only when the database is created for the first time.
// Change the password from Admin → Account right after the first login.
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123');

date_default_timezone_set('Asia/Kolkata');
