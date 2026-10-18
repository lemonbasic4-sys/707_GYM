<?php
/**
 * 707 GYM - Application Configuration
 * Central configuration constants for the entire application.
 */

// ─── Environment ─────────────────────────────────────────────────────────────
define('APP_NAME',    '707 GYM');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     'development'); // 'production' in live
define('ADMIN_DISPLAY_NAME', 'System Administrator');

// ─── Base Path ────────────────────────────────────────────────────────────────
// Treat 707_GYM as web root served from htdocs/707_GYM
define('BASE_PATH', rtrim(str_replace('\\', '/', dirname(__DIR__)), '/'));
define('BASE_URL',  '/707_GYM'); // Adjust if served from a different sub-path

// ─── Database Credentials ────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'gym_707');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
define('DATABASE_BACKUP_PATH', dirname(dirname(BASE_PATH)) . DIRECTORY_SEPARATOR . basename(BASE_PATH) . '_backups');

// ─── Timezone ────────────────────────────────────────────────────────────────
define('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set(APP_TIMEZONE);

// ─── Session ─────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 7200); // seconds (2 hours)
define('SESSION_NAME',     'gym707_session');

// ─── Uploads ─────────────────────────────────────────────────────────────────
define('UPLOAD_PATH',     BASE_PATH . '/uploads');
define('UPLOAD_URL',      BASE_URL  . '/uploads');
define('MAX_FILE_SIZE',   5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGES',  ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// ─── Pagination ───────────────────────────────────────────────────────────────
define('DEFAULT_PER_PAGE', 20);

// ─── Display errors (dev only) ───────────────────────────────────────────────
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(0);
}
