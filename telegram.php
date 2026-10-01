<?php
/* Minimal Telegram Bot API client (uses cURL). Supports two bots via optional $token. */

function tg_api($method, $params = [], $multipart = false, $token = null) {
    $token = trim((string)($token !== null ? $token : setting('bot_token')));
    if ($token === '') return ['ok' => false, 'error' => 'no_token'];
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_POST, true);
    if ($multipart) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params); // array => multipart/form-data
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
    }
    $res = curl_exec($ch);
    if ($res === false) { $err = curl_error($ch); curl_close($ch); return ['ok' => false, 'error' => $err]; }
    curl_close($ch);
    $d = json_decode($res, true);
    return is_array($d) ? $d : ['ok' => false, 'error' => 'bad_response'];
}

function tg_send_message($text, $chatId = null, $keyboard = null, $token = null) {
    $chatId = $chatId ?: setting('admin_chat_id');
    if (!$chatId) return ['ok' => false, 'error' => 'no_chat'];
    $p = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $p['reply_markup'] = json_encode($keyboard);
    return tg_api('sendMessage', $p, false, $token);
}

/* Send the payment screenshot with approve/reject buttons to a chat, via a given bot token. */
function tg_send_payment($paymentId, $caption, $photoPath, $chatId = null, $token = null) {
    $chatId = $chatId ?: setting('admin_chat_id');
    if (!$chatId) return ['ok' => false, 'error' => 'no_chat'];
    $kb = ['inline_keyboard' => [[
        ['text' => '✅ Подтвердить', 'callback_data' => "pay:ok:{$paymentId}"],
        ['text' => '❌ Отклонить',  'callback_data' => "pay:no:{$paymentId}"],
    ]]];
    $ext = strtolower(pathinfo($photoPath, PATHINFO_EXTENSION));
    if (is_file($photoPath) && in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        $p = ['chat_id' => $chatId, 'caption' => $caption, 'parse_mode' => 'HTML',
              'reply_markup' => json_encode($kb), 'photo' => new CURLFile($photoPath)];
        return tg_api('sendPhoto', $p, true, $token);
    }
    if (is_file($photoPath)) {
        $p = ['chat_id' => $chatId, 'caption' => $caption, 'parse_mode' => 'HTML',
              'reply_markup' => json_encode($kb), 'document' => new CURLFile($photoPath)];
        return tg_api('sendDocument', $p, true, $token);
    }
    return tg_send_message($caption, $chatId, $kb, $token);
}

function tg_answer_callback($callbackId, $text = '', $token = null) {
    return tg_api('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text], false, $token);
}

function tg_edit_caption($chatId, $messageId, $caption, $token = null) {
    return tg_api('editMessageCaption', [
        'chat_id' => $chatId, 'message_id' => $messageId,
        'caption' => $caption, 'parse_mode' => 'HTML',
    ], false, $token);
}
