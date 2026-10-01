<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/payram.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out([
        'ok' => false,
        'error' => 'method_not_allowed'
    ], 405);
}

$u = current_user();

if (!$u) {
    json_out([
        'ok' => false,
        'error' => 'auth'
    ], 401);
}

if (
    setting('payram_enabled', '0') !== '1' ||
    !payram_ready()
) {
    json_out([
        'ok' => false,
        'error' => 'payram_not_configured'
    ], 503);
}

$stage = (int)($_POST['stage'] ?? 1);

if (!in_array($stage, [1, 2], true)) {
    json_out([
        'ok' => false,
        'error' => 'stage'
    ], 422);
}

$p = get_payment($u['id'], $stage);

if (!$p) {
    json_out([
        'ok' => false,
        'error' => 'no_payment'
    ], 404);
}

$v = payment_view($p);

if ($v['locked']) {
    json_out([
        'ok' => false,
        'error' => 'locked'
    ], 403);
}

if (!in_array($v['status'], ['awaiting_payment', 'rejected'], true)) {
    json_out([
        'ok' => false,
        'error' => 'not_payable',
        'status' => $v['status']
    ], 409);
}

/*
 * Создаём настоящий платёж в PayRam.
 *
 * API-Key остаётся только на сервере.
 */

$result = payram_create_payment(
    $u['email'],
    'gtd_user_' . $u['id'] . '_stage_' . $stage,
    (float)$p['amount_usd']
);

if (!$result['ok']) {

    $message = 'PayRam API error';

    if (!empty($result['data']['message'])) {
        $message = (string)$result['data']['message'];
    } elseif (!empty($result['data']['error'])) {
        $message = (string)$result['data']['error'];
    } elseif (!empty($result['raw'])) {
        $message = trim(substr((string)$result['raw'], 0, 500));
    } elseif (!empty($result['error'])) {
        $message = (string)$result['error'];
    }

    json_out([
        'ok' => false,
        'error' => 'payram_api',
        'message' => $message,
        'http_status' => (int)($result['status'] ?? 0)
    ], 502);
}

$data = $result['data'];

if (!is_array($data)) {
    json_out([
        'ok' => false,
        'error' => 'payram_invalid_response',
        'message' => 'PayRam вернул некорректный ответ'
    ], 502);
}

$reference = trim((string)(
    $data['reference_id']
    ?? $data['referenceId']
    ?? ''
));

$url = trim((string)(
    $data['url']
    ?? $data['payment_url']
    ?? $data['paymentUrl']
    ?? ''
));

if ($reference === '') {
    json_out([
        'ok' => false,
        'error' => 'payram_no_reference',
        'message' => 'PayRam не вернул reference_id',
        'response' => $data
    ], 502);
}

if ($url === '') {
    json_out([
        'ok' => false,
        'error' => 'payram_no_url',
        'message' => 'PayRam не вернул URL платежа',
        'reference_id' => $reference,
        'response' => $data
    ], 502);
}

/*
 * Сохраняем настоящий PayRam reference и URL.
 */

$st = db()->prepare("
    UPDATE payments
    SET
        payram_reference = ?,
        payram_url = ?,
        payram_status = 'OPEN',
        payram_filled_usd = NULL,
        payram_created_at = datetime('now'),
        status = 'awaiting_payment'
    WHERE id = ?
");

$st->execute([
    $reference,
    $url,
    (int)$p['id']
]);

json_out([
    'ok' => true,
    'reference_id' => $reference,
    'url' => $url,
    'mode' => setting('payram_checkout_mode', 'iframe') === 'redirect' ? 'redirect' : 'iframe',
    'amount_usd' => (float)$p['amount_usd'],
    'customer_email' => $u['email'],
    'customer_id' => 'gtd_user_' . $u['id'] . '_stage_' . $stage
]);