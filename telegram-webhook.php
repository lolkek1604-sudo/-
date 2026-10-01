<?php
require_once __DIR__ . '/../includes/payments.php';

// verify optional secret token (set together with the webhook)
$secret = (string)setting('webhook_secret', '');
if ($secret !== '') {
    $got = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals($secret, $got)) { http_response_code(403); exit; }
}

$update = json_decode(file_get_contents('php://input'), true);
if (!is_array($update)) { http_response_code(200); exit; }

$adminChat = (string)setting('admin_chat_id', '');

if (!empty($update['callback_query'])) {
    $cq = $update['callback_query'];
    $fromId = (string)($cq['from']['id'] ?? '');
    $data = (string)($cq['data'] ?? '');
    $msg = $cq['message'] ?? [];
    $chatId = (string)($msg['chat']['id'] ?? '');
    $messageId = $msg['message_id'] ?? null;

    // only the configured admin may act
    if ($adminChat !== '' && $fromId !== $adminChat) {
        tg_answer_callback($cq['id'], 'Нет доступа');
        http_response_code(200); exit;
    }

    if (preg_match('/^pay:(ok|no):(\d+)$/', $data, $m)) {
        $decision = $m[1];
        $payId = (int)$m[2];
        $st = db()->prepare("SELECT * FROM payments WHERE id=?");
        $st->execute([$payId]);
        $pay = $st->fetch();
        if (!$pay) { tg_answer_callback($cq['id'], 'Оплата не найдена'); http_response_code(200); exit; }

        if ($decision === 'ok') {
            db()->prepare("UPDATE payments SET status='approved', reviewed_at=datetime('now') WHERE id=?")->execute([$payId]);
            if ((int)$pay['stage'] === 1) schedule_stage2($pay['user_id']);
            $note = "\n\n✅ <b>Подтверждено</b>";
            tg_answer_callback($cq['id'], 'Оплата подтверждена ✅');
        } else {
            db()->prepare("UPDATE payments SET status='rejected', reviewed_at=datetime('now') WHERE id=?")->execute([$payId]);
            $note = "\n\n❌ <b>Отклонено</b>";
            tg_answer_callback($cq['id'], 'Оплата отклонена ❌');
        }
        // reflect decision in the admin chat message, remove buttons
        if ($chatId && $messageId) {
            $baseCaption = $msg['caption'] ?? ($msg['text'] ?? 'Оплата');
            tg_api('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => json_encode(['inline_keyboard' => []])]);
            tg_edit_caption($chatId, $messageId, $baseCaption . $note);
        }
    } else {
        tg_answer_callback($cq['id'], '');
    }
    http_response_code(200); exit;
}

// /start etc. — capture chat id to help admin configure
if (!empty($update['message']['text'])) {
    $txt = trim($update['message']['text']);
    $chatId = $update['message']['chat']['id'] ?? '';
    if (strpos($txt, '/start') === 0 || strpos($txt, '/id') === 0) {
        tg_send_message("Ваш chat_id: <code>{$chatId}</code>\nВставьте его в админку (настройки бота).", $chatId);
    }
}
http_response_code(200);
