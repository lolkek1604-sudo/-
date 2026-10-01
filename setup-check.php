<?php
/* Diagnostic page — open /setup-check.php to verify the server is ready.
   Safe to leave in place; delete after setup if you prefer. */
header('Content-Type: text/html; charset=utf-8');

define('DATA_DIR', __DIR__ . '/data');
define('UPLOAD_DIR', DATA_DIR . '/uploads');
@mkdir(DATA_DIR, 0775, true);
@mkdir(UPLOAD_DIR, 0775, true);

function row($label, $ok, $detail = '') {
    $c = $ok ? '#137a45' : '#c02626';
    $ic = $ok ? '✅' : '❌';
    echo "<tr><td style='padding:10px 12px;border-bottom:1px solid #eef2f7'>{$label}</td>"
       . "<td style='padding:10px 12px;border-bottom:1px solid #eef2f7;color:{$c};font-weight:700'>{$ic} "
       . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . "</td></tr>";
}

$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
$sqliteOk = extension_loaded('pdo_sqlite');
$curlOk = extension_loaded('curl');
$mbOk = extension_loaded('mbstring');
$dataWritable = is_dir(DATA_DIR) && is_writable(DATA_DIR);
$uploadWritable = is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR);

$dbTest = 'не проверялось';
$dbOk = false;
if ($sqliteOk && $dataWritable) {
    try {
        $p = new PDO('sqlite:' . DATA_DIR . '/_check.db');
        $p->exec('CREATE TABLE IF NOT EXISTS t(x)');
        $p = null;
        @unlink(DATA_DIR . '/_check.db');
        $dbOk = true; $dbTest = 'запись в базу работает';
    } catch (Throwable $e) { $dbTest = $e->getMessage(); }
}

$allOk = $phpOk && $sqliteOk && $curlOk && $dataWritable && $uploadWritable && $dbOk;
?><!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<div style="max-width:680px;margin:6vh auto;font-family:system-ui,Arial,sans-serif;background:#fff;border:1px solid #e5eaf0;border-radius:16px;padding:30px;box-shadow:0 18px 50px rgba(13,33,53,.12)">
  <h1 style="font-size:1.4rem;margin:0 0 6px">Диагностика Gateway to Dreams</h1>
  <p style="color:<?= $allOk ? '#137a45' : '#c02626' ?>;font-weight:700;margin:0 0 18px">
    <?= $allOk ? '✅ Сервер готов — сайт должен работать.' : '❌ Есть проблемы — см. ниже.' ?>
  </p>
  <table style="width:100%;border-collapse:collapse;font-size:.95rem">
    <?php
    row('PHP версия', $phpOk, PHP_VERSION . ($phpOk ? '' : ' — нужна 7.4+'));
    row('Расширение pdo_sqlite', $sqliteOk, $sqliteOk ? 'есть' : 'НЕ подключено — включите в настройках PHP');
    row('Расширение curl (для Telegram)', $curlOk, $curlOk ? 'есть' : 'НЕ подключено');
    row('Расширение mbstring', $mbOk, $mbOk ? 'есть' : 'желательно подключить');
    row('Папка data доступна для записи', $dataWritable, $dataWritable ? 'да' : 'НЕТ — chmod -R 775 data');
    row('Папка data/uploads для записи', $uploadWritable, $uploadWritable ? 'да' : 'НЕТ — chmod -R 775 data');
    row('Проверка записи в базу', $dbOk, $dbTest);
    ?>
  </table>
  <?php if (!$allOk): ?>
  <div style="background:#fff6e6;border:1px solid #f0dcb0;border-radius:10px;padding:14px;margin-top:18px;color:#8a6414;font-size:.92rem">
    Исправьте пункты с ❌. Чаще всего достаточно дать права на запись папке <code>data</code>
    (в FASTPANEL: файловый менеджер → data → права 775/777) и включить <code>pdo_sqlite</code> в настройках PHP сайта.
  </div>
  <?php else: ?>
  <p style="color:#8595a4;font-size:.9rem;margin-top:18px">Можно удалить этот файл (setup-check.php) после настройки.</p>
  <?php endif; ?>
</div>
