<?php
/* Client cabinet layout (sidebar portal). */
require_once __DIR__ . '/payments.php';

function portal_head($active, $title) {
    require_user_redirect('/auth/login.html');
    $u = current_user();
    $pnav = [
        'dashboard'   => ['portal.home', 'Главная',    '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
        'contract'    => ['portal.contract', 'Договор',   '<path d="M7 3h10v18l-5-3-5 3z"/><path d="M9 8h6M9 12h6"/>'],
        'application' => ['portal.application', 'Моя заявка', '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/>'],
        'payment'     => ['portal.payment', 'Оплата',    '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>'],
        'profile'     => ['portal.profile', 'Профиль',   '<circle cx="12" cy="8" r="3.2"/><path d="M5 20c0-3.6 3-6.5 7-6.5s7 2.9 7 6.5"/>'],
        'support'     => ['portal.support', 'Поддержка', '<circle cx="12" cy="12" r="9"/><path d="M12 17h.01M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.8.4-1 .9-1 1.7"/>'],
    ];
    $initial = mb_strtoupper(mb_substr($u['name'], 0, 1));
    ?><!DOCTYPE html><html lang="ru"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — <?= e(setting('site_name','Gateway to Dreams')) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= APP_VER ?>">
    </head><body>
    <div class="app-shell">
      <aside class="app-side">
        <div class="side-brand">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M5 3v18" stroke="#dc2828" stroke-width="2.4" stroke-linecap="round"/><path d="M5 4c3-1.6 6 1.6 9 0s5-1.4 5-1.4v9s-2 .8-5 1.4-6-1.4-9 0V4Z" fill="#fff"/></svg>
          <?= e(setting('site_name','Gateway to Dreams')) ?>
        </div>
        <div class="side-sub" data-i18n="portal.subtitle">Личный кабинет</div>
        <div class="side-lang">
          <div class="lang" style="width:100%">
            <button class="lang-btn" id="langBtn" style="width:100%;background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.15);color:#fff">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#c3d3e3" stroke-width="1.6"/><path d="M3 12h18M12 3c2.5 2.5 2.5 15 0 18M12 3c-2.5 2.5-2.5 15 0 18" stroke="#c3d3e3" stroke-width="1.4"/></svg>
              <span id="langLabel">Русский</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" style="margin-left:auto"><path d="m6 9 6 6 6-6" stroke="#c3d3e3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="lang-menu" id="langMenu">
              <button data-lang="ru" class="active"><svg class="flag" viewBox="0 0 3 2"><rect width="3" height="2" fill="#fff"/><rect y=".667" width="3" height=".667" fill="#0039a6"/><rect y="1.333" width="3" height=".667" fill="#d52b1e"/></svg>Русский</button>
              <button data-lang="uz"><svg class="flag" viewBox="0 0 3 2"><rect width="3" height="2" fill="#fff"/><rect width="3" height=".62" fill="#0099b5"/><rect y="1.38" width="3" height=".62" fill="#1eb53a"/><rect y=".6" width="3" height=".12" fill="#ce1126"/><rect y="1.28" width="3" height=".12" fill="#ce1126"/></svg>O‘zbekcha</button>
            </div>
          </div>
        </div>
        <nav class="side-nav">
        <?php foreach ($pnav as $k => $it): ?>
          <a href="/<?= $k === 'dashboard' ? 'dashboard' : $k ?>.php" class="<?= $active === $k ? 'active' : '' ?>">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $it[2] ?></svg>
            <span data-i18n="<?= $it[0] ?>"><?= e($it[1]) ?></span>
          </a>
        <?php endforeach; ?>
        </nav>
        <div class="side-user">
          <div class="su-row">
            <span class="av"><?= e($initial) ?></span>
            <span style="min-width:0"><span class="su-name"><?= e($u['name']) ?></span><br><span class="su-mail"><?= e($u['email']) ?></span></span>
          </div>
          <a class="su-out" href="/api/logout.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12H3m0 0 4-4m-4 4 4 4"/><path d="M9 5V4a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2h-7a2 2 0 0 1-2-2v-1"/></svg> <span data-i18n="portal.logout">Выйти</span></a>
        </div>
      </aside>
      <main class="app-main">
<?php
    return $u;
}

function portal_foot() { ?>
      </main>
    </div>
    <script src="/assets/js/i18n.js?v=<?= APP_VER ?>"></script>
    <script src="/assets/js/app.js?v=<?= APP_VER ?>"></script>
    <?php if (!empty($GLOBALS['portal_extra_js'])) echo '<script src="'.$GLOBALS['portal_extra_js'].'?v='.APP_VER.'"></script>'; ?>
    </body></html>
<?php }
