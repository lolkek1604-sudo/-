<?php
require_once __DIR__ . '/includes/portal.php';
$u = portal_head('dashboard', 'Главная');
ensure_stage1($u['id']);
$s1 = payment_view(get_payment($u['id'], 1));
$s2 = payment_view(get_payment($u['id'], 2));
$appRow = db()->prepare("SELECT id, contract_accepted_at FROM applications WHERE user_id=? ORDER BY id DESC LIMIT 1");
$appRow->execute([$u['id']]);
$appRow = $appRow->fetch();
$hasApp = (bool)$appRow;
$contractAccepted = $appRow && !empty($appRow['contract_accepted_at']);

// determine next-step message + button
$nextKey = 'home.next.pay1'; $btnKey = 'home.goto.pay'; $btnHref = '/payment.php'; $showBtn = true;
if (!$hasApp) {
    $nextKey = 'home.next.form'; $btnKey = 'home.goto.form'; $btnHref = '/application.php';
} elseif (!$contractAccepted) {
    $nextKey = 'home.next.contract'; $btnKey = 'home.goto.contract'; $btnHref = '/contract.php';
} elseif ($s1['status'] === 'awaiting_review') { $nextKey = 'home.next.review'; $showBtn = false; }
elseif ($s1['status'] === 'approved') {
    if (!$s2 || $s2['locked']) { $nextKey = 'home.next.wait2'; $showBtn = false; }
    elseif ($s2['status'] === 'awaiting_review') { $nextKey = 'home.next.review'; $showBtn = false; }
    elseif ($s2['status'] === 'approved') { $nextKey = 'home.next.done'; $showBtn = false; }
    else { $nextKey = 'home.next.pay2'; }
}

// 7-step journey timeline
$steps = [
    ['prog.reg',  'Регистрация'],
    ['prog.form', 'Анкета'],
    ['prog.pay1', 'Оплата за оформление полного пакета документов'],
    ['prog.prep', 'Подготовка документов'],
    ['prog.pay2', 'Оплата услуг юриста'],
    ['prog.i129', 'Подача заявки на I-129'],
    ['prog.docs', 'Получение документов'],
];
$completed = 1;                                             // Регистрация
if ($hasApp) $completed = 2;                                // Анкета
if ($s1 && $s1['status'] === 'approved') $completed = 3;    // Оплата за оформление
if ($s2 && !$s2['locked']) $completed = max($completed, 4); // Подготовка документов
if ($s2 && $s2['status'] === 'approved') $completed = 5;    // Оплата услуг юриста

// срок следующего платежа (когда откроется оплата услуг юриста)
$notes = [];
if ($s2 && $s2['locked'] && !empty($s2['available_at'])) {
    $notes[4] = date('d.m.Y', strtotime($s2['available_at']));
}
?>
<h1 class="page-h1"><span data-i18n="home.title">Добро пожаловать</span>, <?= e($u['name']) ?>!</h1>
<p class="page-sub" data-i18n="home.sub">Отслеживайте статус заявки и оплаты здесь.</p>

<div class="panel" style="border-left:4px solid var(--red)">
  <div class="p-sub" style="margin:0 0 6px;color:var(--red);font-weight:700;text-transform:uppercase;letter-spacing:.08em;font-size:.78rem" data-i18n="home.next">Следующий шаг</div>
  <p style="font-size:1.1rem;font-weight:600;margin-bottom:<?= $showBtn ? '16px' : '0' ?>" data-i18n="<?= $nextKey ?>">—</p>
  <?php if ($showBtn): ?>
    <a class="btn btn-primary" href="<?= $btnHref ?>" data-i18n="<?= $btnKey ?>">Далее</a>
  <?php endif; ?>
</div>

<div class="panel">
  <h2 data-i18n="prog.title">Ваш путь оформления</h2>
  <div class="p-sub" style="margin-bottom:18px"></div>
  <div class="timeline">
    <?php foreach ($steps as $i => $stp):
      $cls = $i < $completed ? 'done' : ($i === $completed ? 'active' : 'upcoming');
      $statusKey = $i < $completed ? 'prog.done' : ($i === $completed ? 'prog.active' : 'prog.wait');
    ?>
      <div class="tl-item <?= $cls ?>">
        <div class="tl-rail">
          <div class="tl-node"><?= $i < $completed ? '✓' : ($i + 1) ?></div>
          <div class="tl-line"></div>
        </div>
        <div class="tl-body">
          <div class="tl-label" data-i18n="<?= $stp[0] ?>"><?= e($stp[1]) ?></div>
          <div class="tl-status">
            <span data-i18n="<?= $statusKey ?>"></span><?php if (!empty($notes[$i])): ?> · <span data-i18n="prog.from">доступно с</span> <?= e($notes[$i]) ?><?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php portal_foot();
