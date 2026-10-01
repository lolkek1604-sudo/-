<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false], 405);

$d = body_json();
$email = strtolower(trim($d['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'errors' => ['email' => 1]], 422);

$st = db()->prepare("SELECT * FROM users WHERE email=?");
$st->execute([$email]);
$u = $st->fetch();

// always respond ok (do not reveal whether the email exists)
if ($u) {
    $token = bin2hex(random_bytes(24));
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    db()->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?")->execute([$token, $expires, $u['id']]);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/reset-password.php?token=' . $token;
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;background:#fff;border:1px solid #e5eaf0;border-radius:14px;padding:30px">'
        . '<h2 style="color:#16324f;margin:0 0 12px">Восстановление пароля</h2>'
        . "<p style=\"color:#334;font-size:15px\">Здравствуйте, " . htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') . "!</p>"
        . '<p style="color:#334;font-size:15px">Вы запросили сброс пароля. Нажмите кнопку ниже, чтобы задать новый пароль. Ссылка действительна 1 час.</p>'
        . "<p style=\"text-align:center;margin:26px 0\"><a href=\"{$link}\" style=\"background:#dc2828;color:#fff;text-decoration:none;padding:13px 28px;border-radius:10px;font-weight:700;display:inline-block\">Сбросить пароль</a></p>"
        . '<p style="color:#8595a4;font-size:13px">Если вы не запрашивали сброс — просто проигнорируйте это письмо.</p></div>';
    $err = null;
    send_mail($email, 'Восстановление пароля — Gateway to Dreams', $html, $err);
    if ($err) error_log('reset mail: ' . $err);
}
json_out(['ok' => true]);
