<?php
/* Shared helpers: JSON, auth session, uploads, formatting, CSRF. */

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body_json() {
    $raw = array_key_exists('payram_raw_body', $GLOBALS) ? (string)$GLOBALS['payram_raw_body'] : file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---- client auth ---- */
function current_user() {
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $st = db()->prepare("SELECT * FROM users WHERE id=?");
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
    }
    return $u;
}
function require_user_redirect($to = '/auth/login.html') {
    if (!current_user()) { header('Location: ' . $to); exit; }
}
function login_user($id) { $_SESSION['uid'] = (int)$id; }
function logout_user() { unset($_SESSION['uid']); }

/* ---- admin auth ---- */
function admin_logged_in() { return !empty($_SESSION['admin']); }
function require_admin() { if (!admin_logged_in()) { header('Location: /admin/index.php'); exit; } }

/* ---- CSRF ---- */
function csrf_token() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function csrf_check($t) { return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$t); }

/* ---- uploads ---- */
function save_upload($fileField, $prefix = 'doc') {
    if (empty($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$fileField];
    if ($f['size'] > MAX_UPLOAD_BYTES) return null;
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = explode(',', ALLOWED_UPLOAD_EXT);
    if (!in_array($ext, $allowed, true)) return null;
    $name = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) return null;
    @chmod($dest, 0644);
    return ['filename' => $name, 'orig' => $f['name']];
}

/* Bump when bank requisites change — clients get a "reload" banner site-wide. */
function bump_banks_version() { setting_set('banks_version', (string)time()); }

/* ---- currency ---- */
function usd_to_uzs($usd, $rate) { return (int)round($usd * $rate); }
function fmt_uzs($n) { return number_format((int)$n, 0, '.', ' ') . ' сум'; }
function fmt_usd($n) { return '$' . rtrim(rtrim(number_format((float)$n, 2, '.', ' '), '0'), '.'); }
