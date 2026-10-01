<?php
require_once __DIR__ . '/../includes/payments.php';

function flash_set($type, $msg) { $_SESSION['flash'] = ['t' => $type, 'm' => $msg]; }
function flash_get() { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

function admin_head($active, $title) {
    $ic = [
        'dashboard'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'applications' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/>',
        'users'        => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 5.5a3 3 0 0 1 0 5.8"/>',
        'payments'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
        'banks'        => '<path d="M4 10h16M5 10 12 4l7 6M6 10v8m4-8v8m4-8v8m4-8v8M4 20h16"/>',
        'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.3 1a7 7 0 0 0-1.7-1l-.3-2.5H9.4l-.3 2.5a7 7 0 0 0-1.7 1l-2.3-1-2 3.4L5 11a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 1.7 1l.3 2.5h5.2l.3-2.5a7 7 0 0 0 1.7-1l2.3 1 2-3.4-2-1.5a7 7 0 0 0 .1-1Z"/>',
    ];
    $nav = [
        'dashboard'    => 'Обзор',
        'applications' => 'Анкеты',
        'users'        => 'Клиенты',
        'payments'     => 'Оплаты',
        'banks'        => 'Банки',
        'settings'     => 'Настройки',
    ];
    $pending = (int)db()->query("SELECT COUNT(*) FROM payments WHERE status='awaiting_review'")->fetchColumn();
    ?><!DOCTYPE html><html lang="ru"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — Админка</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= APP_VER ?>">
    </head><body>
    <div class="app-shell">
      <aside class="app-side">
        <div class="side-brand">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M5 3v18" stroke="#dc2828" stroke-width="2.4" stroke-linecap="round"/><path d="M5 4c3-1.6 6 1.6 9 0s5-1.4 5-1.4v9s-2 .8-5 1.4-6-1.4-9 0V4Z" fill="#fff"/></svg>
          <?= e(setting('site_name', 'Gateway to Dreams')) ?>
        </div>
        <div class="side-sub"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 3 5 6v5c0 4.4 3 7.8 7 9 4-1.2 7-4.6 7-9V6l-7-3Z" stroke="#8fa8c1" stroke-width="1.8"/></svg> Панель управления</div>
        <nav class="side-nav">
        <?php foreach ($nav as $k => $label): ?>
            <a href="/admin/<?= $k ?>.php" class="<?= $active === $k ? 'active' : '' ?>">
              <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $ic[$k] ?></svg>
              <?= e($label) ?>
              <?php if ($k === 'payments' && $pending): ?><span class="side-badge"><?= $pending ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        </nav>
        <div class="side-user">
          <a class="su-out" href="/admin/logout.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12H3m0 0 4-4m-4 4 4 4"/><path d="M9 5V4a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2h-7a2 2 0 0 1-2-2v-1"/></svg> Выйти</a>
        </div>
      </aside>
      <main class="app-main">
    <?php if ($f = flash_get()): ?>
        <div class="flash <?= $f['t'] === 'ok' ? 'ok' : 'err' ?>"><?= e($f['m']) ?></div>
    <?php endif;
}

function admin_foot() { ?>
      </main>
    </div></body></html>
<?php }
