<?php
require_once __DIR__ . '/../includes/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false], 405);

$d = body_json();
$name  = trim($d['name'] ?? '');
$email = strtolower(trim($d['email'] ?? ''));
$phone = trim($d['phone'] ?? '');
$pass  = (string)($d['password'] ?? '');

$digits = preg_replace('/\D/', '', $phone);
if (strpos($digits, '998') === 0) $digits = substr($digits, 3);

$errors = [];
if (mb_strlen($name) < 2) $errors['name'] = 1;
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 1;
if (strlen($digits) !== 9) $errors['phone'] = 1;
if (strlen($pass) < 8) $errors['password'] = 1;
if ($errors) json_out(['ok' => false, 'errors' => $errors], 422);

$phoneFull = '+998' . $digits;
$requireVerify = setting('require_email_verify', '0') === '1';
$verified = $requireVerify ? 0 : 1;
$token = $requireVerify ? bin2hex(random_bytes(20)) : '';

try {
    $st = db()->prepare("INSERT INTO users (name,email,phone,password_hash,verified,verify_token) VALUES (?,?,?,?,?,?)");
    $st->execute([$name, $email, $phoneFull, password_hash($pass, PASSWORD_DEFAULT), $verified, $token]);
} catch (PDOException $ex) {
    if ((int)$ex->getCode() === 23000 || strpos($ex->getMessage(), 'UNIQUE') !== false) {
        json_out(['ok' => false, 'errors' => ['email' => 'exists']], 409);
    }
    error_log('register: ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'server'], 500);
}
$uid = db()->lastInsertId();

if ($requireVerify) {
    require_once __DIR__ . '/../includes/mailer.php';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/api/verify.php?token=' . $token;
    $err = null;
    send_mail($email, 'Подтвердите email — Gateway to Dreams', verify_email_html($name, $link), $err);
    if ($err) error_log('verify mail: ' . $err);
    json_out(['ok' => true, 'verify' => true]);
}

login_user($uid);
json_out(['ok' => true]);
