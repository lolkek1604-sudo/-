<?php
/* Ultra-light version endpoint — polled every few seconds by every visitor.
   Deliberately avoids config.php: no session_start (session locking would
   serialise requests), no full settings load. One tiny read, that's it. */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$dbPath = __DIR__ . '/../data/app.db';
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $st = $pdo->query("SELECT value FROM settings WHERE key='banks_version'");
    $v = $st ? $st->fetchColumn() : false;
    echo json_encode(['v' => (string)($v !== false ? $v : '1')]);
} catch (Throwable $e) {
    echo json_encode(['v' => '1']);
}
