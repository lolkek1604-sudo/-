<?php
require_once __DIR__ . '/../includes/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false], 405);

$u = current_user();
if (!$u) json_out(['ok' => false, 'error' => 'auth'], 401);

// questionnaire answers (all non-file fields)
$fields = ['q1','q1_detail','q2','q2_detail','q3','q3_detail','crime','crime_detail','marital','deport','deport_detail','job','passport','dob'];
$answers = [];
foreach ($fields as $f) { $answers[$f] = trim($_POST[$f] ?? ''); }

$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare("INSERT INTO applications (user_id,data_json) VALUES (?,?)");
    $st->execute([$u['id'], json_encode($answers, JSON_UNESCAPED_UNICODE)]);
    $appId = $pdo->lastInsertId();

    $docKinds = ['passport','intl','photo','diploma','work'];
    $insDoc = $pdo->prepare("INSERT INTO documents (user_id,application_id,kind,filename,orig_name) VALUES (?,?,?,?,?)");
    foreach ($docKinds as $k) {
        if (!empty($_FILES[$k]) && $_FILES[$k]['error'] === UPLOAD_ERR_OK) {
            $saved = save_upload($k, 'doc_' . $k);
            if ($saved) $insDoc->execute([$u['id'], $appId, $k, $saved['filename'], $saved['orig']]);
        }
    }

    // create stage-1 payment (awaiting payment)
    $rate = (float)setting('usd_uzs_rate', 12950);
    $p1 = (float)setting('price1_usd', 205);
    $has = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id=? AND stage=1");
    $has->execute([$u['id']]);
    if (!$has->fetchColumn()) {
        $insP = $pdo->prepare("INSERT INTO payments (user_id,stage,amount_usd,amount_uzs,rate,status) VALUES (?,?,?,?,?,'awaiting_payment')");
        $insP->execute([$u['id'], 1, $p1, usd_to_uzs($p1, $rate), $rate]);
    }
    $pdo->commit();
} catch (Exception $ex) {
    $pdo->rollBack();
    error_log('onboarding: ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'server'], 500);
}

// notify admin (best-effort)
tg_send_message("📝 Новая анкета\nКлиент: <b>" . e($u['name']) . "</b>\nEmail: " . e($u['email']) . "\nТелефон: " . e($u['phone']));

json_out(['ok' => true]);
