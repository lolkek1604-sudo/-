<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('err','Сессия истекла.'); header('Location: /admin/banks.php'); exit; }
    $enabled = $_POST['enabled'] ?? [];       // bankId => on
    $cards   = $_POST['card'] ?? [];
    $holders = $_POST['holder'] ?? [];
    $extras  = $_POST['extra'] ?? [];

    $all = $pdo->query("SELECT id, code FROM banks")->fetchAll();
    $up = $pdo->prepare("UPDATE banks SET enabled=?, card_number=?, card_holder=?, extra=? WHERE id=?");
    foreach ($all as $b) {
        $bid = (int)$b['id'];
        $en = !empty($enabled[$bid]) ? 1 : 0;
        $up->execute([$en, trim($cards[$bid] ?? ''), trim($holders[$bid] ?? ''), trim($extras[$bid] ?? ''), $bid]);

        // optional logo upload
        if (!empty($_FILES['logo']['name'][$bid]) && $_FILES['logo']['error'][$bid] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['logo']['tmp_name'][$bid];
            $ext = strtolower(pathinfo($_FILES['logo']['name'][$bid], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','svg'], true) && $_FILES['logo']['size'][$bid] <= MAX_UPLOAD_BYTES) {
                $fn = 'bank_' . $b['code'] . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($tmp, UPLOAD_DIR . '/' . $fn)) {
                    @chmod(UPLOAD_DIR . '/' . $fn, 0644);
                    $pdo->prepare("UPDATE banks SET logo_filename=? WHERE id=?")->execute([$fn, $bid]);
                }
            }
        }
    }
    bump_banks_version(); // клиентам покажется плашка «обновить экран»
    flash_set('ok', 'Банки сохранены. Клиентам показана плашка обновления.');
    header('Location: /admin/banks.php'); exit;
}

$banks = $pdo->query("SELECT * FROM banks ORDER BY sort, id")->fetchAll();
function blogo($b){ return !empty($b['logo_filename']) ? '/data/uploads/'.rawurlencode($b['logo_filename']) : '/assets/img/banks/'.rawurlencode($b['code']).'.svg'; }

admin_head('banks', 'Банки');
?>
<h1>Банки и реквизиты</h1>
<p class="muted" style="margin:-8px 0 20px">Отметьте банки, которые показывать на сайте, и укажите реквизиты для перевода. Логотип можно заменить своим файлом (PNG/JPG/SVG).</p>
<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <?php foreach ($banks as $b): $bid=(int)$b['id']; ?>
    <div class="bank-admin <?= $b['enabled']?'':'off' ?>">
      <div class="blogo">
        <img src="<?= e(blogo($b)) ?>" alt="<?= e($b['name']) ?>">
        <div class="adm-field" style="margin-top:8px">
          <label style="font-size:.8rem">Свой логотип</label>
          <input type="file" name="logo[<?= $bid ?>]" accept="image/*">
        </div>
      </div>
      <div class="bfields">
        <label class="switch" style="margin-bottom:12px">
          <input type="checkbox" name="enabled[<?= $bid ?>]" <?= $b['enabled']?'checked':'' ?>>
          Показывать «<?= e($b['name']) ?>» на сайте
        </label>
        <div class="adm-field"><label>Номер карты</label><input type="text" name="card[<?= $bid ?>]" value="<?= e($b['card_number']) ?>" placeholder="8600 1234 5678 9012"></div>
        <div class="adm-field"><label>Получатель</label><input type="text" name="holder[<?= $bid ?>]" value="<?= e($b['card_holder']) ?>" placeholder="ISM FAMILIYA"></div>
        <div class="adm-field"><label>Примечание (необязательно)</label><input type="text" name="extra[<?= $bid ?>]" value="<?= e($b['extra']) ?>" placeholder="Напр.: назначение перевода"></div>
      </div>
    </div>
  <?php endforeach; ?>
  <button class="adm-btn" type="submit">Сохранить</button>
</form>
<?php admin_foot();
