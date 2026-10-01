<?php
require_once __DIR__ . '/includes/portal.php';
$pdo = db();
// need $u before head for badge; portal_head returns user
$GLOBALS['portal_extra_js'] = '/assets/js/onboarding.js';
$u = portal_head('application', 'Моя заявка');

$st = $pdo->prepare("SELECT * FROM applications WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->execute([$u['id']]);
$app = $st->fetch();
$done = (bool)$app;

$YN = fn($v) => $v === 'yes' ? 'Да' : ($v === 'no' ? 'Нет' : '—');
$MARITAL = ['single'=>'Холост / Не замужем','married'=>'Женат / Замужем','divorced'=>'Разведён(а)','widowed'=>'Вдовец / Вдова'];
$DOCLABEL = ['passport'=>'Внутренний паспорт (UZ)','intl'=>'Заграничный паспорт','photo'=>'Портретное фото','diploma'=>'Дипломы / образование','work'=>'Трудовая книжка'];
?>
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
  <div>
    <h1 class="page-h1" data-i18n="appl.q.title">Анкета на визу</h1>
    <p class="page-sub" data-i18n="appl.q.sub">Заполните все вопросы точно. Информация будет проверена перед отправкой.</p>
  </div>
  <span class="tag <?= $done ? 'ok' : 'amber' ?>" style="margin-top:6px" data-i18n="<?= $done ? 'appl.badge.complete' : 'appl.badge.incomplete' ?>"><?= $done ? 'Завершена' : 'Не завершена' ?></span>
</div>

<?php if ($done):
  $ans = json_decode($app['data_json'], true) ?: [];
  $docs = $pdo->prepare("SELECT * FROM documents WHERE application_id=? ORDER BY id");
  $docs->execute([$app['id']]);
  $docs = $docs->fetchAll();
?>
  <div class="panel">
    <h2 data-i18n="appl.answers">Ответы анкеты</h2>
    <table class="adm-table">
      <tr><td>Полное имя</td><td><b><?= e($u['name']) ?></b></td></tr>
      <tr><td>Подавали ранее заявление на визу?</td><td><b><?= $YN($ans['q1']??'') ?></b><?= !empty($ans['q1_detail'])?' — '.e($ans['q1_detail']):'' ?></td></tr>
      <?php if (($ans['q1']??'')==='yes'): ?>
      <tr><td>Отказ в визе США?</td><td><b><?= $YN($ans['q2']??'') ?></b><?= !empty($ans['q2_detail'])?' — '.e($ans['q2_detail']):'' ?></td></tr>
      <tr><td>Была ли виза США ранее?</td><td><b><?= $YN($ans['q3']??'') ?></b><?= !empty($ans['q3_detail'])?' — '.e($ans['q3_detail']):'' ?></td></tr>
      <?php endif; ?>
      <tr><td>Судимость?</td><td><b><?= $YN($ans['crime']??'') ?></b><?= !empty($ans['crime_detail'])?' — '.e($ans['crime_detail']):'' ?></td></tr>
      <tr><td>Семейное положение</td><td><b><?= e($MARITAL[$ans['marital']??'']??'—') ?></b></td></tr>
      <tr><td>Депортации / нарушения визового режима?</td><td><b><?= $YN($ans['deport']??'') ?></b><?= !empty($ans['deport_detail'])?' — '.e($ans['deport_detail']):'' ?></td></tr>
      <tr><td>Желаемая должность / сфера</td><td><b><?= e($ans['job']??'—') ?></b></td></tr>
    </table>
  </div>
  <div class="panel">
    <h2 data-i18n="appl.docs">Документы</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px">
      <?php foreach ($docs as $d): $url='/data/uploads/'.rawurlencode($d['filename']); $img=preg_match('/\.(jpe?g|png|webp)$/i',$d['filename']); ?>
        <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden">
          <a href="<?= e($url) ?>" target="_blank">
            <?php if ($img): ?><img src="<?= e($url) ?>" style="width:100%;height:120px;object-fit:cover">
            <?php else: ?><div style="height:120px;display:grid;place-items:center;background:#f5f7fa;font-weight:700;color:#5b6b7d">PDF</div><?php endif; ?>
          </a>
          <div style="padding:8px 10px;font-size:.84rem;font-weight:600"><?= e($DOCLABEL[$d['kind']]??$d['kind']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$docs): ?><p class="muted">Документы не загружены.</p><?php endif; ?>
    </div>
  </div>

<?php else: /* ===== fillable questionnaire (inside portal) ===== */ ?>

<div class="ob-error" id="obError" data-i18n="ob.err.required">Пожалуйста, заполните обязательные поля и загрузите документы.</div>

<div class="panel">
  <div class="field" style="margin:0">
    <label data-i18n="auth.name">Полное имя</label>
    <input type="text" value="<?= e($u['name']) ?>" disabled style="opacity:.8">
  </div>
</div>

<form id="obForm" novalidate>
  <div class="panel">
    <h2 style="text-transform:uppercase;letter-spacing:.08em;color:var(--red);font-size:.82rem" data-i18n="ob.sect.history">Визовая история</h2>
    <div class="q" data-required-yn="q1">
      <span class="q-label" data-i18n="ob.q1">Подавали ли вы ранее заявление на получение визы?</span>
      <div class="yn" data-yn="q1">
        <label><input type="radio" name="q1" value="yes"><span class="opt" data-i18n="ob.yes">Да</span></label>
        <label><input type="radio" name="q1" value="no"><span class="opt" data-i18n="ob.no">Нет</span></label>
      </div>
      <div class="q-detail hide" data-show-when="q1=yes">
        <label data-i18n="ob.q1.detail">Укажите страну и тип визы</label>
        <input type="text" name="q1_detail" data-i18n-ph="ob.q1.detail.ph" placeholder="Например: Германия, туристическая (C)">
      </div>
    </div>
    <div class="hide" data-show-when="q1=yes" data-block="q1yes">
      <div class="q" data-required-yn="q2">
        <span class="q-label" data-i18n="ob.q2">Получали ли вы когда-либо отказ в выдаче визы США?</span>
        <div class="yn" data-yn="q2">
          <label><input type="radio" name="q2" value="yes"><span class="opt" data-i18n="ob.yes">Да</span></label>
          <label><input type="radio" name="q2" value="no"><span class="opt" data-i18n="ob.no">Нет</span></label>
        </div>
        <div class="q-detail hide" data-show-when="q2=yes">
          <label data-i18n="ob.q2.detail">Укажите дату отказа</label>
          <input type="text" name="q2_detail" data-i18n-ph="ob.q2.detail.ph" placeholder="Например: 03.2023">
        </div>
      </div>
      <div class="q" data-required-yn="q3">
        <span class="q-label" data-i18n="ob.q3">Была ли у вас ранее виза США?</span>
        <div class="yn" data-yn="q3">
          <label><input type="radio" name="q3" value="yes"><span class="opt" data-i18n="ob.yes">Да</span></label>
          <label><input type="radio" name="q3" value="no"><span class="opt" data-i18n="ob.no">Нет</span></label>
        </div>
        <div class="q-detail hide" data-show-when="q3=yes">
          <label data-i18n="ob.q3.detail">Укажите тип визы и цель поездки</label>
          <input type="text" name="q3_detail" data-i18n-ph="ob.q3.detail.ph" placeholder="Например: B1/B2, туризм">
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2 style="text-transform:uppercase;letter-spacing:.08em;color:var(--red);font-size:.82rem" data-i18n="ob.sect.docs">Документы</h2>
    <div class="q"><span class="q-label" data-i18n="ob.up.passport">Фотография внутреннего паспорта (Узбекистан)</span><div data-upload="passport"></div></div>
    <div class="q"><span class="q-label" data-i18n="ob.up.intl">Фотография действующего заграничного паспорта</span><div data-upload="intl"></div></div>
    <div class="q"><span class="q-label" data-i18n="ob.up.photo">Ваша фотография (портретное фото)</span><div data-upload="photo"></div></div>
    <div class="q"><span class="q-label" data-i18n="ob.up.diploma">Фотографии дипломов и/или иных документов об образовании</span><div data-upload="diploma"></div></div>
    <div class="q"><span class="q-label" data-i18n="ob.up.work">Фотография трудовой книжки или выписки о трудовой деятельности</span><div data-upload="work"></div></div>
  </div>

  <div class="panel">
    <h2 style="text-transform:uppercase;letter-spacing:.08em;color:var(--red);font-size:.82rem" data-i18n="ob.sect.personal">Личные данные</h2>
    <div class="q" data-required-yn="crime">
      <span class="q-label" data-i18n="ob.q.crime">Есть ли у вас судимость?</span>
      <div class="yn" data-yn="crime">
        <label><input type="radio" name="crime" value="yes"><span class="opt" data-i18n="ob.yes">Да</span></label>
        <label><input type="radio" name="crime" value="no"><span class="opt" data-i18n="ob.no">Нет</span></label>
      </div>
      <div class="q-detail hide" data-show-when="crime=yes">
        <label data-i18n="ob.q.crime.detail">Укажите подробности</label>
        <textarea name="crime_detail" data-i18n-ph="ob.detail.ph" placeholder="Опишите подробнее"></textarea>
      </div>
    </div>
    <div class="q" data-required-radio="marital">
      <span class="q-label" data-i18n="ob.q.marital">Укажите ваше семейное положение</span>
      <div class="radio-list" data-radio="marital">
        <label><input type="radio" name="marital" value="single"><span class="opt" data-i18n="ob.marital.single">Холост / Не замужем</span></label>
        <label><input type="radio" name="marital" value="married"><span class="opt" data-i18n="ob.marital.married">Женат / Замужем</span></label>
        <label><input type="radio" name="marital" value="divorced"><span class="opt" data-i18n="ob.marital.divorced">Разведён(а)</span></label>
        <label><input type="radio" name="marital" value="widowed"><span class="opt" data-i18n="ob.marital.widowed">Вдовец / Вдова</span></label>
      </div>
    </div>
    <div class="q" data-required-yn="deport">
      <span class="q-label" data-i18n="ob.q.deport">Были ли ранее депортации, нарушения визового режима или превышение срока пребывания за границей?</span>
      <div class="yn" data-yn="deport">
        <label><input type="radio" name="deport" value="yes"><span class="opt" data-i18n="ob.yes">Да</span></label>
        <label><input type="radio" name="deport" value="no"><span class="opt" data-i18n="ob.no">Нет</span></label>
      </div>
      <div class="q-detail hide" data-show-when="deport=yes">
        <label data-i18n="ob.q.deport.detail">Укажите подробности</label>
        <textarea name="deport_detail" data-i18n-ph="ob.detail.ph" placeholder="Опишите подробнее"></textarea>
      </div>
    </div>
    <div class="q" data-required-text="job">
      <span class="q-label" data-i18n="ob.q.job">Желаемая должность или сфера работы?</span>
      <div class="q-detail" style="margin-top:0">
        <input type="text" name="job" data-i18n-ph="ob.q.job.ph" placeholder="Например: строительство, водитель, гостеприимство">
      </div>
    </div>
  </div>

  <div class="panel">
    <h2 style="text-transform:uppercase;letter-spacing:.08em;color:var(--red);font-size:.82rem" data-i18n="ob.sect.contract">Данные для договора</h2>
    <div class="q" data-required-text="passport" style="border-top:none;padding-top:4px">
      <span class="q-label" data-i18n="ob.passport">Серия и номер паспорта</span>
      <div class="q-detail" style="margin-top:0">
        <input type="text" name="passport" data-i18n-ph="ob.passport.ph" placeholder="Например: AA1234567">
      </div>
    </div>
    <div class="q" data-required-text="dob">
      <span class="q-label" data-i18n="ob.dob">Дата рождения</span>
      <div class="q-detail" style="margin-top:0">
        <input type="date" name="dob">
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary btn-lg btn-block" data-i18n="ob.submit">Отправить анкету</button>
</form>

<?php endif;
portal_foot();
