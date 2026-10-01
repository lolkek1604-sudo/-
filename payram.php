<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/payments.php';

if (!function_exists('str_starts_with')) {
    function str_starts_with($h, $n) { return $n === '' || strncmp($h, $n, strlen($n)) === 0; }
}

function payram_base_url() {
    return rtrim((string)setting('payram_base_url', ''), '/');
}
function payram_api_key() {
    return trim((string)setting('payram_api_key', ''));
}
function payram_webhook_secret() {
    return trim((string)setting('payram_webhook_secret', ''));
}
function payram_ready() {
    return payram_base_url() !== '' && payram_api_key() !== '';
}

function payram_request($method, $path, $payload = null) {
    if (!function_exists('curl_init')) {
    return [
        'ok' => false,
        'status' => 500,
        'data' => null,
        'raw' => '',
        'error' => 'PHP cURL extension is not installed'
    ];
}
    $base = payram_base_url();
    $key = payram_api_key();
    if ($base === '' || $key === '') return ['ok'=>false, 'error'=>'not_configured'];
    $url = $base . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    if (!$ch) return ['ok'=>false, 'error'=>'curl_init'];
    $headers = ['Accept: application/json', 'API-Key: ' . $key];
    $opts = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_HTTPHEADER=>$headers, CURLOPT_CUSTOMREQUEST=>strtoupper($method)];
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string)$raw, true);
    return ['ok'=>$errno===0 && $code>=200 && $code<300, 'status'=>$code, 'data'=>is_array($json)?$json:null, 'raw'=>$raw, 'error'=>$errno ? $err : null];
}

function payram_create_payment($email, $customerId, $amountUsd) {
    return payram_request('POST', '/api/v1/payment', [
        'customerEmail' => $email,
        'customerID' => (string)$customerId,
        'amountInUSD' => round((float)$amountUsd, 2),
    ]);
}
function payram_get_payment($referenceId) {
    return payram_request('GET', '/api/v1/payment/reference/' . rawurlencode($referenceId));
}
function payram_webhook_valid() {
    // PayRam Core 3.8.x sends the webhook shared secret in the API-Key header.
    // Optional HMAC compatibility is kept for deployments/proxies that provide
    // X-PayRam-Signature: sha256=<hex>.
    $secret = payram_webhook_secret();
    if ($secret === '') return false;

    $sig = trim((string)($_SERVER['HTTP_X_PAYRAM_SIGNATURE'] ?? ''));
    if ($sig !== '') {
        $raw = file_get_contents('php://input');
        $GLOBALS['payram_raw_body'] = (string)$raw;
        if (str_starts_with($sig, 'sha256=')) $sig = substr($sig, 7);
        $expected = hash_hmac('sha256', (string)$raw, $secret);
        return $sig !== '' && hash_equals($expected, $sig);
    }

    $received = (string)($_SERVER['HTTP_API_KEY'] ?? '');
    return $received !== '' && hash_equals($secret, $received);
}

function payram_apply_status($paymentId, $referenceId, $status, $payload = []) {
    $status = strtoupper((string)$status);
    $p = db()->prepare('SELECT * FROM payments WHERE id=? LIMIT 1');
    $p->execute([(int)$paymentId]);
    $pay = $p->fetch();
    if (!$pay) return false;

    $local = 'awaiting_payment';
    if ($status === 'FILLED' || $status === 'OVER_FILLED') $local = 'approved';
    elseif ($status === 'CANCELLED') $local = 'rejected';
    elseif (in_array($status, ['OPEN','PARTIALLY_FILLED','VERIFYING','UNDEFINED'], true)) $local = 'awaiting_payment';

    $filled = null;
    foreach (['filled_amount_in_usd','filled_amount','amount'] as $k) {
        if (isset($payload[$k]) && is_numeric($payload[$k])) { $filled = (float)$payload[$k]; break; }
    }
    $st = db()->prepare("UPDATE payments SET status=?, payram_status=?, payram_filled_usd=COALESCE(?,payram_filled_usd), reviewed_at=CASE WHEN ? IN ('approved','rejected') THEN datetime('now') ELSE reviewed_at END WHERE id=? AND (payram_reference=? OR id=?)");
    $st->execute([$local, $status, $filled, $local, (int)$paymentId, (string)$referenceId, (int)$paymentId]);

    if ($local === 'approved' && (int)$pay['stage'] === 1) schedule_stage2((int)$pay['user_id']);
    return true;
}
