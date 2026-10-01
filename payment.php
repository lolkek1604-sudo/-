<?php
require_once __DIR__ . '/includes/portal.php';
$GLOBALS['portal_extra_js'] = '/assets/js/dashboard.js';
$u = portal_head('payment', 'Оплата');
ensure_stage1($u['id']);
$s1 = payment_view(get_payment($u['id'], 1));
$s2 = payment_view(get_payment($u['id'], 2));

// gate: contract must be accepted before payment
$appRow = db()->prepare("SELECT contract_accepted_at FROM applications WHERE user_id=? ORDER BY id DESC LIMIT 1");
$appRow->execute([$u['id']]);
$appRow = $appRow->fetch();
if (!$appRow || empty($appRow['contract_accepted_at'])) {
    echo '<h1 class="page-h1" data-i18n="dash.pay.title">Оплата</h1>';
    echo '<div class="panel" style="text-align:center;padding:44px">'
       . '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#8595a4" stroke-width="1.5" style="margin-bottom:12px"><path d="M7 3h10v18l-5-3-5 3z"/><path d="M9 8h6M9 12h6"/></svg>'
       . '<p class="muted" style="margin-bottom:18px" data-i18n="pay.need.contract">Перед оплатой ознакомьтесь с договором и примите его условия.</p>'
       . '<a class="btn btn-primary" href="/contract.php" data-i18n="pay.go.contract">Открыть договор</a></div>';
    portal_foot();
    exit;
}

$rate = (float)setting('usd_uzs_rate', 12950);
$proj2 = usd_to_uzs((float)setting('price2_usd', 350), $rate);
$total = ($s1 ? $s1['amount_uzs'] : 0) + ($s2 ? $s2['amount_uzs'] : $proj2);

// history + sums
$hist = db()->prepare("SELECT * FROM payments WHERE user_id=? ORDER BY id DESC");
$hist->execute([$u['id']]);
$hist = $hist->fetchAll();
$paid = 0; $pending = 0;
foreach ([$s1, $s2] as $sv) {
    if (!$sv) continue;
    if ($sv['status'] === 'approved') $paid += $sv['amount_uzs'];
    // "ожидает оплаты" = только то, что сейчас реально нужно оплатить (не заблокированный этап)
    elseif (!$sv['locked'] && in_array($sv['status'], ['awaiting_payment','awaiting_review','rejected'], true)) $pending += $sv['amount_uzs'];
}

function bank_logo_url($b) {
    if (!empty($b['logo_filename'])) return '/data/uploads/' . rawurlencode($b['logo_filename']);
    return '/assets/img/banks/' . rawurlencode($b['code']) . '.svg';
}
function pay_badge($view) {
    if (!$view) return ['locked', 'pay.locked.badge'];
    if ($view['locked']) return ['locked', 'pay.locked.badge'];
    switch ($view['status']) {
        case 'approved': return ['ok', 'pay.paid.badge'];
        case 'awaiting_review': return ['await', 'pay.await.appr'];
        case 'rejected': return ['no', 'pay.rejected.badge'];
        default: return ['pay', 'pay.pay'];
    }
}
// which stage is payable right now
$active = 0;
if ($s1 && in_array($s1['status'], ['awaiting_payment','rejected'], true) && !$s1['locked']) $active = 1;
elseif ($s2 && in_array($s2['status'], ['awaiting_payment','rejected'], true) && !$s2['locked']) $active = 2;
$activeView = $active === 1 ? $s1 : ($active === 2 ? $s2 : null);

function fmt_sum($n){ return number_format((int)$n, 0, '.', ' '); }

function stage_card($view, $num, $active) {
    [$cls,$badge] = pay_badge($view);
    $isActive = ($active === $num);
    $descKey = $num === 1 ? 'pay.first.desc' : 'pay.second.desc';
    $titleKey = $num === 1 ? 'pay.first' : 'pay.second';
    $iconBg = $isActive ? '#fff3e6' : '#eef2f7';
    ob_start(); ?>
    <div class="stage-card <?= $isActive ? 'active' : '' ?> <?= (!$view || $view['locked']) ? 'off' : '' ?>">
      <div class="sc-top">
        <span class="sc-ic" style="background:<?= $iconBg ?>">
          <?php if ($isActive): ?><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
          <?php elseif ($view && $view['status']==='approved'): ?><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#137a45" stroke-width="1.8"><path d="m5 12 5 5L19 7"/></svg>
          <?php else: ?><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8595a4" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><?php endif; ?>
        </span>
        <div>
          <div class="sc-title" data-i18n="<?= $titleKey ?>"><?= $num===1?'Первый платёж':'Второй платёж' ?></div>
          <?php if ($view && !$view['locked']): ?>
            <div class="sc-amount"><?= fmt_sum($view['amount_uzs']) ?> <span data-i18n="cur.uzs">сум</span></div>
          <?php endif; ?>
        </div>
      </div>
      <p class="sc-desc" data-i18n="<?= $descKey ?>"><?= $num===1?'Для начала оформления визы необходим первоначальный взнос.':'Доступен после подтверждения первого платежа.' ?></p>
      <?php if ($view && $view['locked']): ?>
        <span class="tag locked" data-i18n="pay.locked.badge">Заблокировано</span>
      <?php elseif ($isActive): ?>
        <a href="#payflow" class="btn btn-primary" style="padding:.55rem 1.1rem" data-i18n="pay.pay">Оплатить</a>
      <?php else: ?>
        <span class="tag <?= $cls ?>" data-i18n="<?= $badge ?>">—</span>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}

function render_flow($view, $stage) {
    if (!$view) return '';
    $st = $view['status'];
    $configured = setting('payram_enabled','0') === '1' && setting('payram_base_url','') !== '' && setting('payram_api_key','') !== '';
    $eurRate = max(0.0001, (float)setting('payram_eur_usd_rate','1.08'));
    $eurEquivalent = (float)$view['amount_usd'] / $eurRate;
    ob_start(); ?>
    <div class="panel payram-panel" id="payflow" data-stage="<?= $stage ?>">
      <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
        <div>
          <h2 style="margin-bottom:6px"><span data-i18n="<?= $stage==1?'pay.first':'pay.second' ?>"><?= $stage==1?'Первый платёж':'Второй платёж' ?></span> — $<?= e(number_format((float)$view['amount_usd'],2,'.','')) ?> <span style="font-size:.82em;color:var(--muted)">≈ €<?= e(number_format($eurEquivalent,2,'.','')) ?></span></h2>
          <p class="p-sub">Оплата создаётся защищённо на сервере.</p>
        </div>
        <span class="tag <?= $st==='approved'?'ok':($st==='rejected'?'no':'pay') ?>"><?= $st==='approved'?'Оплачено':($st==='rejected'?'Отклонено':'Ожидает оплаты') ?></span>
      </div>
      <?php if ($st === 'rejected'): ?>
        <div class="result-box no" style="margin:16px 0">
          <div><h3>Предыдущий платёж отменён</h3><p>Создайте новый платёж ниже.</p></div>
        </div>
      <?php endif; ?>
      <?php if (!$configured): ?>
        <div class="result-box no" style="margin-top:16px"><div><h3>PayRam ещё не настроен</h3><p>Администратору нужно указать PayRam URL и API key в разделе настроек.</p></div></div>
      <?php elseif ($st === 'approved'): ?>
        <div class="result-box ok" style="margin-top:16px"><div><h3>Оплата подтверждена</h3><p>PayRam сообщил об успешном зачислении.</p></div></div>
      <?php else: ?>
        <div class="payram-status" data-payram-status style="margin-top:14px"></div>
        <div class="payram-widget-wrap" style="margin-top:18px">
          <button type="button" class="btn btn-primary" data-payram-start data-stage="<?= $stage ?>" style="width:100%;padding:14px 20px;font-size:16px">Оплатить картой</button>
          <div class="payram-frame-wrap" data-payram-frame hidden></div>
        </div>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}

$STATUS_TAG = ['awaiting_payment'=>['pay','pay.pay'],'awaiting_review'=>['await','pay.await.appr'],'approved'=>['ok','pay.paid.badge'],'rejected'=>['no','pay.rejected.badge'],'locked'=>['locked','pay.locked.badge']];
?>
<h1 class="page-h1" data-i18n="dash.pay.title">Оплата</h1>
<p class="page-sub">Управляйте платежами и просматривайте историю транзакций</p>

<div class="panel">
  <h2 style="margin-bottom:16px" data-i18n="pay.section">Платежи</h2>
  <div class="two-col">
    <?= stage_card($s1, 1, $active) ?>
    <?= stage_card($s2, 2, $active) ?>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi green"><div class="kh" data-i18n="pay.stat.paid">Всего оплачено</div><div class="kn"><?= fmt_sum($paid) ?> <span style="font-size:1rem;font-weight:600" data-i18n="cur.uzs">сум</span></div></div>
  <div class="kpi amber"><div class="kh" data-i18n="pay.stat.pending">Ожидает оплаты</div><div class="kn"><?= fmt_sum($pending) ?> <span style="font-size:1rem;font-weight:600" data-i18n="cur.uzs">сум</span></div></div>
  <div class="kpi blue"><div class="kh" data-i18n="pay.stat.total">Всего к оплате</div><div class="kn"><?= fmt_sum($total) ?> <span style="font-size:1rem;font-weight:600" data-i18n="cur.uzs">сум</span></div></div>
</div>

<?php if ($activeView) echo render_flow($activeView, $active); ?>

<div class="panel">
  <h2 data-i18n="pay.history">История платежей</h2>
  <p class="p-sub" data-i18n="pay.history.sub">Все ваши транзакции</p>
  <?php if (!$hist || !array_filter($hist, fn($p)=>$p['status']!=='awaiting_payment' && $p['status']!=='locked')): ?>
    <div style="text-align:center;padding:40px;color:var(--muted-2)">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" style="margin-bottom:8px"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
      <p data-i18n="pay.history.empty">История платежей пуста</p>
    </div>
  <?php else: ?>
    <div style="overflow-x:auto"><table class="adm-table">
      <tr><th data-i18n="pay.h.stage">Этап</th><th data-i18n="pay.h.amount">Сумма</th><th data-i18n="pay.h.status">Статус</th><th data-i18n="pay.h.date">Дата</th></tr>
      <?php foreach ($hist as $p): if (in_array($p['status'], ['awaiting_payment','locked'], true)) continue; $t=$STATUS_TAG[$p['status']]??['none','']; ?>
        <tr>
          <td><?= (int)$p['stage'] ?></td>
          <td><?= fmt_sum($p['amount_uzs']) ?> сум</td>
          <td><span class="tag <?= $t[0] ?>" data-i18n="<?= $t[1] ?>">—</span></td>
          <td><?= e(date('d.m.Y H:i', strtotime($p['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>
<?php portal_foot();
