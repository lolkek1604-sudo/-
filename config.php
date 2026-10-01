<?php
/* Gateway to Dreams — global config.
   PHP 7.4+ / 8.x, SQLite via PDO. No external services required. */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('UPLOAD_DIR', DATA_DIR . '/uploads');
define('DB_PATH', DATA_DIR . '/app.db');

// ensure writable dirs exist
foreach ([DATA_DIR, UPLOAD_DIR] as $d) {
    if (!is_dir($d)) { @mkdir($d, 0775, true); }
}

date_default_timezone_set('Asia/Tashkent');

// error handling: log, do not leak to users
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DATA_DIR . '/php-error.log');

define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_UPLOAD_EXT', 'jpg,jpeg,png,webp,pdf');
define('APP_VER', '15'); // bump on each release to bust browser cache for CSS/JS

/* Friendly fatal-error page instead of a blank white screen. */
function app_fatal($title, $hint = '', $detail = '') {
    if (!headers_sent()) http_response_code(500);
    $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'server', 'message' => $title], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $h = htmlspecialchars($hint, ENT_QUOTES, 'UTF-8');
    $d = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
    echo "<!doctype html><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
       . "<div style=\"max-width:640px;margin:12vh auto;font-family:system-ui,Arial,sans-serif;background:#fff;"
       . "border:1px solid #e5eaf0;border-radius:16px;padding:32px;box-shadow:0 18px 50px rgba(13,33,53,.14)\">"
       . "<h1 style='font-size:1.3rem;color:#b01717;margin:0 0 12px'>⚠️ Проблема при запуске</h1>"
       . "<p style='color:#0f1e2e;font-size:1.05rem;margin:0 0 10px'><b>{$t}</b></p>"
       . ($h ? "<p style='color:#5b6b7d;margin:0 0 10px'>{$h}</p>" : "")
       . ($d ? "<pre style='background:#f5f7fa;padding:12px;border-radius:8px;font-size:.8rem;overflow:auto;color:#5b6b7d'>{$d}</pre>" : "")
       . "<p style='color:#8595a4;font-size:.85rem;margin:14px 0 0'>Откройте <code>/setup-check.php</code> для диагностики.</p>"
       . "</div>";
    exit;
}

// verify data directory is writable (SQLite + uploads need this)
if (!is_dir(DATA_DIR) || !is_writable(DATA_DIR)) {
    app_fatal(
        'Папке «data» не хватает прав на запись.',
        'Дайте права на запись: в терминале  chmod -R 775 data  (или 777), либо через файловый менеджер FASTPANEL выставьте права 775 на папку data и data/uploads. Владелец должен совпадать с пользователем сайта.',
        'Путь: ' . DATA_DIR
    );
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/telegram.php';
