<?php
/* Webhook for the SECOND bot (payments group):
   - approve/reject payments
   - in-bot admin panel to change bank requisites (restricted to tg_admin_ids) */
require_once __DIR__ . '/../includes/payments.php';

$bot2 = trim((string)setting('bot2_token'));
$secret = (string)setting('webhook2_secret', '');
if ($secret !== '') {
    $got = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals($secret, $got)) { http_response_code(403); exit; }
}

$update = json_decode(file_get_contents('php://input'), true);
if (!is_array($update)) { http_response_code(200); exit; }

$allowedChat = trim((string)setting('bot2_chat_id', ''));
$adminIds = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string)setting('tg_admin_ids', '')))));

function is_tg_admin($uid) {
    global $adminIds;
    return $adminIds && in_array((string)$uid, $adminIds, true);
}
function st_get($uid) {
    $s = db()->prepare("SELECT state FROM bot_state WHERE chat_id=?");
    $s->execute([(string)$uid]);
    $v = $s->fetchColumn();
    return $v ? json_decode($v, true) : null;
}
function st_set($uid, $a) {
    db()->prepare("INSERT INTO bot_state (chat_id,state,updated_at) VALUES (?,?,datetime('now'))
        ON CONFLICT(chat_id) DO UPDATE SET state=excluded.state, updated_at=excluded.updated_at")
        ->execute([(string)$uid, json_encode($a, JSON_UNESCAPED_UNICODE)]);
}
function st_clear($uid) { db()->prepare("DELETE FROM bot_state WHERE chat_id=?")->execute([(string)$uid]); }

function banks_kb() {
    $rows = db()->query("SELECT id,name,enabled FROM banks ORDER BY sort,id")->fetchAll();
    $kb = [];
    foreach ($rows as $b) {
        $kb[] = [['text' => ($b['enabled'] ? '✅ ' : '⬜ ') . $b['name'], 'callback_data' => 'adm:bank:' . $b['id']]];
    }
    return ['inline_keyboard' => $kb];
}
function bank_kb($id) {
    return ['inline_keyboard' => [
        [['text' => '💳 Номер карты', 'callback_data' => 'adm:card:' . $id],
         ['text' => '👤 Получатель', 'callback_data' => 'adm:holder:' . $id]],
        [['text' => '🔄 Включить / выключить', 'callback_data' => 'adm:toggle:' . $id]],
        [['text' => '⬅️ К списку банков', 'callback_data' => 'adm:list']],
    ]];
}
function bank_text($id) {
    $s = db()->prepare("SELECT * FROM banks WHERE id=?");
    $s->execute([$id]);
    $b = $s->fetch();
    if (!$b) return 'Банк не найден';
    return "<b>" . htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') . "</b>\n"
        . "Статус: " . ($b['enabled'] ? 'показывается на сайте ✅' : 'скрыт ⬜') . "\n"
        . "Карта: <code>" . htmlspecialchars($b['card_number'] ?: '—', ENT_QUOTES, 'UTF-8') . "</code>\n"
        . "Получатель: " . htmlspecialchars($b['card_holder'] ?: '—', ENT_QUOTES, 'UTF-8');
}

/* ---------------- callbacks ---------------- */
if (!empty($update['callback_query'])) {
    $cq = $update['callback_query'];
    $data = (string)($cq['data'] ?? '');
    $fromId = (string)($cq['from']['id'] ?? '');
    $msg = $cq['message'] ?? [];
    $chatId = (string)($msg['chat']['id'] ?? '');
    $messageId = $msg['message_id'] ?? null;

    // ---- payments approve/reject ----
    if (preg_match('/^pay:(ok|no):(\d+)$/', $data, $m)) {
        // allow inside the configured group, or for a listed telegram admin
        $ok = ($allowedChat === '' || $chatId === $allowedChat || is_tg_admin($fromId));
        if (!$ok) { tg_answer_callback($cq['id'], 'Нет доступа', $bot2); http_response_code(200); exit; }

        $payId = (int)$m[2];
        $st = db()->prepare("SELECT * FROM payments WHERE id=?");
        $st->execute([$payId]);
        $pay = $st->fetch();
        if (!$pay) { tg_answer_callback($cq['id'], 'Оплата не найдена', $bot2); http_response_code(200); exit; }
        if (in_array($pay['status'], ['approved', 'rejected'], true)) {
            tg_answer_callback($cq['id'], 'Уже обработано', $bot2); http_response_code(200); exit;
        }

        if ($m[1] === 'ok') {
            db()->prepare("UPDATE payments SET status='approved', reviewed_at=datetime('now') WHERE id=?")->execute([$payId]);
            if ((int)$pay['stage'] === 1) schedule_stage2($pay['user_id']);
            $note = "\n\n✅ <b>Подтверждено</b>";
            tg_answer_callback($cq['id'], 'Оплата подтверждена ✅', $bot2);
        } else {
            db()->prepare("UPDATE payments SET status='rejected', reviewed_at=datetime('now') WHERE id=?")->execute([$payId]);
            $note = "\n\n❌ <b>Отклонено</b>";
            tg_answer_callback($cq['id'], 'Оплата отклонена ❌', $bot2);
        }
        if ($chatId && $messageId) {
            $base = $msg['caption'] ?? ($msg['text'] ?? 'Оплата');
            tg_api('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => json_encode(['inline_keyboard' => []])], false, $bot2);
            tg_edit_caption($chatId, $messageId, $base . $note, $bot2);
        }
        http_response_code(200); exit;
    }

    // ---- in-bot admin panel ----
    if (strpos($data, 'adm:') === 0) {
        if (!is_tg_admin($fromId)) { tg_answer_callback($cq['id'], 'Нет доступа к админке', $bot2); http_response_code(200); exit; }

        if ($data === 'adm:list') {
            tg_api('editMessageText', ['chat_id' => $chatId, 'message_id' => $messageId,
                'text' => '🏦 <b>Реквизиты банков</b>' . "\n" . 'Выберите банк:', 'parse_mode' => 'HTML',
                'reply_markup' => json_encode(banks_kb())], false, $bot2);
            tg_answer_callback($cq['id'], '', $bot2);

        } elseif (preg_match('/^adm:bank:(\d+)$/', $data, $m)) {
            tg_api('editMessageText', ['chat_id' => $chatId, 'message_id' => $messageId,
                'text' => bank_text((int)$m[1]), 'parse_mode' => 'HTML',
                'reply_markup' => json_encode(bank_kb((int)$m[1]))], false, $bot2);
            tg_answer_callback($cq['id'], '', $bot2);

        } elseif (preg_match('/^adm:(card|holder):(\d+)$/', $data, $m)) {
            st_set($fromId, ['step' => $m[1], 'bank_id' => (int)$m[2], 'chat' => $chatId]);
            $what = $m[1] === 'card' ? 'новый номер карты' : 'нового получателя';
            tg_send_message("✏️ Отправьте следующим сообщением {$what}:", $chatId, null, $bot2);
            tg_answer_callback($cq['id'], '', $bot2);

        } elseif (preg_match('/^adm:toggle:(\d+)$/', $data, $m)) {
            $id = (int)$m[1];
            db()->prepare("UPDATE banks SET enabled = CASE enabled WHEN 1 THEN 0 ELSE 1 END WHERE id=?")->execute([$id]);
            bump_banks_version();
            tg_api('editMessageText', ['chat_id' => $chatId, 'message_id' => $messageId,
                'text' => bank_text($id), 'parse_mode' => 'HTML',
                'reply_markup' => json_encode(bank_kb($id))], false, $bot2);
            tg_answer_callback($cq['id'], 'Готово. Клиентам показана плашка обновления.', $bot2);
        } else {
            tg_answer_callback($cq['id'], '', $bot2);
        }
        http_response_code(200); exit;
    }

    tg_answer_callback($cq['id'], '', $bot2);
    http_response_code(200); exit;
}

/* ---------------- messages ---------------- */
if (!empty($update['message'])) {
    $mm = $update['message'];
    $txt = trim((string)($mm['text'] ?? ''));
    $chatId = (string)($mm['chat']['id'] ?? '');
    $fromId = (string)($mm['from']['id'] ?? '');

    if (strpos($txt, '/id') === 0) {
        tg_send_message("ID этого чата: <code>{$chatId}</code>\nВаш Telegram ID: <code>{$fromId}</code>\n\nВставьте их в админке сайта (второй бот).", $chatId, null, $bot2);
        http_response_code(200); exit;
    }

    // /start и /admin — сразу открывают панель у админов
    if (strpos($txt, '/start') === 0 || strpos($txt, '/admin') === 0) {
        if (is_tg_admin($fromId)) {
            tg_send_message('🏦 <b>Реквизиты банков</b>' . "\n" . 'Выберите банк, чтобы изменить реквизиты:', $chatId, banks_kb(), $bot2);
        } else {
            tg_send_message("👋 Бот платежей.\n\nВаш Telegram ID: <code>{$fromId}</code>\nID этого чата: <code>{$chatId}</code>\n\nЧтобы открыть админку (/admin), добавьте ваш Telegram ID в админке сайта → Настройки → Второй бот.", $chatId, null, $bot2);
        }
        http_response_code(200); exit;
    }

    // state-driven input (changing card number / holder)
    $st = st_get($fromId);
    if ($st && is_tg_admin($fromId) && $txt !== '') {
        $bankId = (int)($st['bank_id'] ?? 0);
        if ($st['step'] === 'card') {
            db()->prepare("UPDATE banks SET card_number=? WHERE id=?")->execute([$txt, $bankId]);
        } elseif ($st['step'] === 'holder') {
            db()->prepare("UPDATE banks SET card_holder=? WHERE id=?")->execute([$txt, $bankId]);
        }
        st_clear($fromId);
        bump_banks_version();
        tg_send_message("✅ Сохранено. Клиентам на сайте показана плашка «обновить экран».\n\n" . bank_text($bankId), $chatId, bank_kb($bankId), $bot2);
        http_response_code(200); exit;
    }
}
http_response_code(200);
