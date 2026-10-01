<?php
require_once __DIR__ . '/includes/config.php';
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = '';
$done = false;

$st = db()->prepare("SELECT * FROM users WHERE reset_token=? AND reset_token<>''");
$st->execute([$token]);
$u = $st->fetch();
$valid = $u && !empty($u['reset_expires']) && strtotime($u['reset_expires']) > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    if (strlen($pw) < 8) $error = 'Пароль должен быть не короче 8 символов.';
    elseif ($pw !== $pw2) $error = 'Пароли не совпадают.';
    else {
        db()->prepare("UPDATE users SET password_hash=?, reset_token='', reset_expires='' WHERE id=?")
            ->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
        $done = true;
    }
}
?><!DOCTYPE html><html lang="ru"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Сброс пароля — Gateway to Dreams</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= APP_VER ?>">
</head><body>
<div class="auth-wrap">
  <div class="auth-main">
    <div class="auth-card">
      <div class="auth-brand">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M5 3v18" stroke="#dc2828" stroke-width="2.4" stroke-linecap="round"/><path d="M5 4c3-1.6 6 1.6 9 0s5-1.4 5-1.4v9s-2 .8-5 1.4-6-1.4-9 0V4Z" fill="#16324f"/></svg>
        Gateway to Dreams
      </div>
      <?php if ($done): ?>
        <h1>Пароль изменён ✓</h1>
        <p class="sub">Теперь войдите с новым паролем.</p>
        <a class="btn btn-primary btn-lg btn-block" href="/auth/login.html">Войти</a>
      <?php elseif (!$valid): ?>
        <h1>Ссылка недействительна</h1>
        <p class="sub">Ссылка сброса устарела или уже использована. Запросите новую.</p>
        <a class="btn btn-primary btn-lg btn-block" href="/auth/forgot.html">Запросить сброс</a>
      <?php else: ?>
        <h1>Новый пароль</h1>
        <p class="sub">Задайте новый пароль для вашего аккаунта.</p>
        <?php if ($error): ?><div class="flash err" style="margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <div class="field"><label>Новый пароль</label><input type="password" name="password" placeholder="Минимум 8 символов" autocomplete="new-password"></div>
          <div class="field"><label>Повторите пароль</label><input type="password" name="password2" placeholder="Повторите пароль" autocomplete="new-password"></div>
          <button type="submit" class="btn btn-primary btn-lg btn-block">Сохранить пароль</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
</body></html>
