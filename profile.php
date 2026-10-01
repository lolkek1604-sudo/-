<?php
require_once __DIR__ . '/includes/portal.php';
$u = portal_head('profile', 'Профиль');
$pdo = db();
$msg = null; $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $act = $_POST['act'] ?? '';
    if ($act === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $digits = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        if (strpos($digits, '998') === 0) $digits = substr($digits, 3);
        if (mb_strlen($name) >= 2 && strlen($digits) === 9) {
            $pdo->prepare("UPDATE users SET name=?, phone=? WHERE id=?")->execute([$name, '+998'.$digits, $u['id']]);
            $msg = 'Данные сохранены.';
            $u['name'] = $name; $u['phone'] = '+998'.$digits;
        } else { $err = 'Проверьте имя и телефон (+998 и 9 цифр).'; }
    } elseif ($act === 'password') {
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) >= 8) {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
            $msg = 'Пароль обновлён.';
        } else { $err = 'Пароль должен быть не короче 8 символов.'; }
    }
}
?>
<h1 class="page-h1" data-i18n="prof.title">Профиль</h1>
<p class="page-sub" data-i18n="prof.sub">Ваши личные данные и безопасность.</p>

<?php if ($msg): ?><div class="flash ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>

<div class="two-col">
  <form class="panel" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="profile">
    <h2 data-i18n="prof.data">Личные данные</h2>
    <div class="field"><label data-i18n="auth.name">Полное имя</label><input type="text" name="name" value="<?= e($u['name']) ?>"></div>
    <div class="field"><label data-i18n="auth.email">Email</label><input type="email" value="<?= e($u['email']) ?>" disabled style="opacity:.7"></div>
    <div class="field"><label data-i18n="auth.phone">Номер телефона</label><input type="tel" name="phone" value="<?= e($u['phone']) ?>"></div>
    <button class="btn btn-primary" type="submit" data-i18n="prof.save">Сохранить изменения</button>
  </form>

  <form class="panel" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="password">
    <h2 data-i18n="prof.changepw">Смена пароля</h2>
    <div class="field"><label data-i18n="prof.newpw">Новый пароль</label><input type="password" name="password" data-i18n-ph="prof.newpw.ph" placeholder="Минимум 8 символов"></div>
    <button class="btn btn-navy" type="submit" data-i18n="prof.savepw">Обновить пароль</button>
  </form>
</div>
<?php portal_foot();
