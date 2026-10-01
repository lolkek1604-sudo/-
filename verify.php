<?php
require_once __DIR__ . '/../includes/config.php';
$token = $_GET['token'] ?? '';

$page = function ($title, $msg, $ok, $btn = null) {
    $c = $ok ? '#137a45' : '#c02626';
    echo "<!doctype html><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
    echo "<div style=\"max-width:460px;margin:12vh auto;font-family:system-ui,Arial,sans-serif;background:#fff;border:1px solid #e5eaf0;border-radius:16px;padding:34px;text-align:center;box-shadow:0 18px 50px rgba(13,33,53,.12)\">";
    echo "<h1 style='color:{$c};font-size:1.4rem;margin:0 0 10px'>" . htmlspecialchars($title) . "</h1>";
    echo "<p style='color:#5b6b7d'>" . htmlspecialchars($msg) . "</p>";
    if ($btn) echo "<p style='margin-top:20px'><a href='{$btn[1]}' style='background:#dc2828;color:#fff;text-decoration:none;padding:12px 26px;border-radius:10px;font-weight:700;display:inline-block'>" . htmlspecialchars($btn[0]) . "</a></p>";
    echo "</div>";
    exit;
};

if ($token === '') $page('Ссылка недействительна', 'Токен подтверждения отсутствует.', false);

$st = db()->prepare("SELECT * FROM users WHERE verify_token=? AND verify_token<>''");
$st->execute([$token]);
$u = $st->fetch();
if (!$u) $page('Ссылка недействительна или уже использована', 'Возможно, email уже подтверждён — попробуйте войти.', false, ['Войти', '/auth/login.html']);

db()->prepare("UPDATE users SET verified=1, verify_token='' WHERE id=?")->execute([$u['id']]);
login_user($u['id']);
$page('Email подтверждён ✓', 'Спасибо! Ваш адрес подтверждён. Продолжите заполнение анкеты.', true, ['Продолжить', '/application.php']);
