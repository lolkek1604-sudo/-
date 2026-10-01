<?php
require_once __DIR__ . '/../includes/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false], 405);

$d = body_json();
$email = strtolower(trim($d['email'] ?? ''));
$pass  = (string)($d['password'] ?? '');

$st = db()->prepare("SELECT * FROM users WHERE email=?");
$st->execute([$email]);
$u = $st->fetch();
if (!$u || !password_verify($pass, $u['password_hash'])) {
    json_out(['ok' => false, 'error' => 'invalid'], 401);
}
if (setting('require_email_verify', '0') === '1' && (int)($u['verified'] ?? 1) === 0) {
    json_out(['ok' => false, 'error' => 'unverified'], 403);
}
login_user($u['id']);
json_out(['ok' => true]);
