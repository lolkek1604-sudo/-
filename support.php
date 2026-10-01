<?php
require_once __DIR__ . '/includes/portal.php';
$u = portal_head('support', 'Поддержка');
$email = setting('support_email', 'support@gatewaytodreams.info');
$tg = setting('manager_contact', '@GatewaySupport');
?>
<h1 class="page-h1" data-i18n="sup.title">Центр поддержки</h1>
<p class="page-sub" data-i18n="sup.sub">Получите помощь по заявке или свяжитесь с нашей командой.</p>

<div class="two-col">
  <div class="panel" style="text-align:center;padding:34px">
    <div style="width:60px;height:60px;border-radius:16px;background:#eef3fb;display:grid;place-items:center;margin:0 auto 16px">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#16324f" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
    </div>
    <h2 data-i18n="sup.email.t">Поддержка по email</h2>
    <p class="muted" style="margin:6px 0 16px" data-i18n="sup.email.s">Напишите нам — ответим в течение 24 часов</p>
    <a class="btn btn-ghost btn-block" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
  </div>
  <div class="panel" style="text-align:center;padding:34px">
    <div style="width:60px;height:60px;border-radius:16px;background:#e8f4fb;display:grid;place-items:center;margin:0 auto 16px">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#0088cc" stroke-width="1.7"><path d="M21 5 3 12l6 2 2 6 3-4 4 3z"/></svg>
    </div>
    <h2 data-i18n="sup.tg.t">Telegram</h2>
    <p class="muted" style="margin:6px 0 16px" data-i18n="sup.tg.s">Напишите нам в Telegram для быстрого ответа</p>
    <a class="btn btn-ghost btn-block" href="https://t.me/<?= e(ltrim($tg,'@')) ?>" target="_blank"><?= e($tg) ?></a>
  </div>
</div>

<div class="panel">
  <h2 data-i18n="faq.title">Часто задаваемые вопросы</h2>
  <div style="margin-top:8px">
    <div style="padding:14px 0;border-top:1px solid var(--soft-2)"><b data-i18n="faq.q2">Сколько времени занимает процесс?</b><p class="muted" style="margin-top:4px" data-i18n="faq.a2"></p></div>
    <div style="padding:14px 0;border-top:1px solid var(--soft-2)"><b data-i18n="faq.q3">Какие документы нужны?</b><p class="muted" style="margin-top:4px" data-i18n="faq.a3"></p></div>
    <div style="padding:14px 0;border-top:1px solid var(--soft-2)"><b data-i18n="faq.q5">Могу ли я отслеживать статус заявки?</b><p class="muted" style="margin-top:4px" data-i18n="faq.a5"></p></div>
  </div>
</div>
<?php portal_foot();
