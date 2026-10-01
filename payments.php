<?php
/* Payment state machine helpers. */
require_once __DIR__ . '/config.php';

function get_payment($userId, $stage) {
    $st = db()->prepare("SELECT * FROM payments WHERE user_id=? AND stage=? ORDER BY id DESC LIMIT 1");
    $st->execute([$userId, $stage]);
    return $st->fetch() ?: null;
}

/* Ensure a stage-1 payment row exists (created at onboarding, but be safe). */
function ensure_stage1($userId) {
    $p = get_payment($userId, 1);
    if ($p) return $p;
    $rate = (float)setting('usd_uzs_rate', 12950);
    $usd = (float)setting('price1_usd', 205);
    $st = db()->prepare("INSERT INTO payments (user_id,stage,amount_usd,amount_uzs,rate,status) VALUES (?,?,?,?,?,'awaiting_payment')");
    $st->execute([$userId, 1, $usd, usd_to_uzs($usd, $rate), $rate]);
    return get_payment($userId, 1);
}

/* Called when stage-1 gets approved: schedule stage-2 with a delay. */
function schedule_stage2($userId) {
    if (get_payment($userId, 2)) return;
    $delay = (int)setting('stage2_delay_days', 5);
    $rate = (float)setting('usd_uzs_rate', 12950);
    $usd = (float)setting('price2_usd', 350);
    $availAt = date('Y-m-d H:i:s', time() + $delay * 86400);
    $st = db()->prepare("INSERT INTO payments (user_id,stage,amount_usd,amount_uzs,rate,status,available_at) VALUES (?,?,?,?,?,'locked',?)");
    $st->execute([$userId, 2, $usd, usd_to_uzs($usd, $rate), $rate, $availAt]);
}

/* Public view model for the client dashboard. */
function payment_view($p) {
    if (!$p) return null;
    $rate = (float)setting('usd_uzs_rate', 12950);
    $uzs = usd_to_uzs((float)$p['amount_usd'], $rate);
    $status = $p['status'];
    $locked = false;
    $unlockInDays = 0;
    if ($p['stage'] == 2 && $status === 'locked' && $p['available_at']) {
        $avail = strtotime($p['available_at']);
        if (time() < $avail) {
            $locked = true;
            $unlockInDays = (int)ceil(($avail - time()) / 86400);
        } else {
            $status = 'awaiting_payment'; // unlocked now
        }
    }
    return [
        'id' => (int)$p['id'],
        'stage' => (int)$p['stage'],
        'amount_usd' => (float)$p['amount_usd'],
        'amount_uzs' => $uzs,
        'status' => $status,
        'locked' => $locked,
        'unlock_in_days' => $unlockInDays,
        'available_at' => $p['available_at'],
        'bank_name' => $p['bank_name'],
    ];
}

function enabled_banks() {
    $rows = db()->query("SELECT * FROM banks WHERE enabled=1 ORDER BY sort, id")->fetchAll();
    return $rows;
}
