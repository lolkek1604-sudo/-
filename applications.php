<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$YN = fn($v) => $v === 'yes' ? 'Да' : ($v === 'no' ? 'Нет' : '—');
$MARITAL = ['single'=>'Холост / Не замужем','married'=>'Женат / Замужем','divorced'=>'Разведён(а)','widowed'=>'Вдовец / Вдова'];
$DOCLABEL = ['passport'=>'Внутренний паспорт (UZ)','intl'=>'Заграничный паспорт','photo'=>'Портретное фото','diploma'=>'Дипломы / образование','work'=>'Трудовая книжка'];

if ($id) {
    $st = $pdo->prepare("SELECT a.*, u.name, u.email, u.phone FROM applications a JOIN users u ON u.id=a.user_id WHERE a.id=?");
    $st->execute([$id]);
    $app = $st->fetch();
    if (!$app) { admin_head('applications','Анкета'); echo '<p>Не найдено.</p>'; admin_foot(); exit; }
    $ans = json_decode($app['data_json'], true) ?: [];
    $docs = $pdo->prepare("SELECT * FROM documents WHERE application_id=? ORDER BY id");
    $docs->execute([$id]);
    $docs = $docs->fetchAll();

    admin_head('applications', 'Анкета #' . $id);
    ?>
    <a href="/admin/applications.php" class="muted">← ко всем анкетам</a>
    <h1 style="margin-top:10px">Анкета #<?= $id ?> — <?= e($app['name']) ?></h1>
    <div class="adm-card">
      <h2>Клиент</h2>
      <p><b><?= e($app['name']) ?></b> · <?= e($app['email']) ?> · <?= e($app['phone']) ?></p>
      <p class="muted" style="margin-top:6px">Отправлено: <?= e($app['created_at']) ?></p>
    </div>
    <div class="adm-card">
      <h2>Ответы</h2>
      <table class="adm-table">
        <tr><td>Подавали ранее заявление на визу?</td><td><b><?= $YN($ans['q1']??'') ?></b><?= !empty($ans['q1_detail'])?' — '.e($ans['q1_detail']):'' ?></td></tr>
        <?php if (($ans['q1']??'')==='yes'): ?>
        <tr><td>Отказ в визе США?</td><td><b><?= $YN($ans['q2']??'') ?></b><?= !empty($ans['q2_detail'])?' — '.e($ans['q2_detail']):'' ?></td></tr>
        <tr><td>Была ли виза США ранее?</td><td><b><?= $YN($ans['q3']??'') ?></b><?= !empty($ans['q3_detail'])?' — '.e($ans['q3_detail']):'' ?></td></tr>
        <?php endif; ?>
        <tr><td>Судимость?</td><td><b><?= $YN($ans['crime']??'') ?></b><?= !empty($ans['crime_detail'])?' — '.e($ans['crime_detail']):'' ?></td></tr>
        <tr><td>Семейное положение</td><td><b><?= e($MARITAL[$ans['marital']??'']??'—') ?></b></td></tr>
        <tr><td>Депортации / нарушения визового режима?</td><td><b><?= $YN($ans['deport']??'') ?></b><?= !empty($ans['deport_detail'])?' — '.e($ans['deport_detail']):'' ?></td></tr>
        <tr><td>Желаемая должность / сфера</td><td><b><?= e($ans['job']??'—') ?></b></td></tr>
        <tr><td>Серия и номер паспорта</td><td><b><?= e($ans['passport']??'—') ?></b></td></tr>
        <tr><td>Дата рождения</td><td><b><?= !empty($ans['dob']) ? e(date('d.m.Y', strtotime($ans['dob']))) : '—' ?></b></td></tr>
        <tr><td>Договор</td><td><?php if (!empty($app['contract_accepted_at'])): ?><span class="tag ok">Принят <?= e(date('d.m.Y H:i', strtotime($app['contract_accepted_at']))) ?></span><?php else: ?><span class="tag amber">Не принят</span><?php endif; ?></td></tr>
      </table>
    </div>
    <div class="adm-card">
      <h2>Документы</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px">
        <?php foreach ($docs as $d): $u='/data/uploads/'.rawurlencode($d['filename']); $img=preg_match('/\.(jpe?g|png|webp)$/i',$d['filename']); ?>
          <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden">
            <a href="<?= e($u) ?>" target="_blank">
              <?php if ($img): ?><img src="<?= e($u) ?>" style="width:100%;height:130px;object-fit:cover">
              <?php else: ?><div style="height:130px;display:grid;place-items:center;background:#f5f7fa">PDF</div><?php endif; ?>
            </a>
            <div style="padding:8px 10px;font-size:.85rem;font-weight:600"><?= e($DOCLABEL[$d['kind']]??$d['kind']) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$docs): ?><p class="muted">Документы не загружены.</p><?php endif; ?>
      </div>
    </div>
    <?php
    admin_foot();
    exit;
}

// ---- list ----
$rows = $pdo->query("SELECT a.id,a.created_at,u.name,u.email,u.phone,
    (SELECT COUNT(*) FROM documents d WHERE d.application_id=a.id) AS docs
    FROM applications a JOIN users u ON u.id=a.user_id ORDER BY a.id DESC")->fetchAll();
admin_head('applications', 'Анкеты');
?>
<h1>Анкеты</h1>
<div class="adm-card">
<?php if (!$rows): ?><p class="muted">Пока нет анкет.</p><?php else: ?>
<div style="overflow-x:auto"><table class="adm-table">
  <tr><th>#</th><th>Клиент</th><th>Контакты</th><th>Документов</th><th>Дата</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)$r['id'] ?></td>
      <td><?= e($r['name']) ?></td>
      <td><?= e($r['email']) ?><br><small class="muted"><?= e($r['phone']) ?></small></td>
      <td><?= (int)$r['docs'] ?></td>
      <td><?= e($r['created_at']) ?></td>
      <td><a class="adm-btn sm" href="/admin/applications.php?id=<?= (int)$r['id'] ?>">Открыть</a></td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
</div>
<?php admin_foot();
