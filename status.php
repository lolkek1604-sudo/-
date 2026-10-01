<?php
require_once __DIR__ . '/../includes/payments.php';
$u = current_user();
if (!$u) json_out(['ok' => false, 'error' => 'auth'], 401);

$s1 = payment_view(get_payment($u['id'], 1));
$s2 = payment_view(get_payment($u['id'], 2));
json_out(['ok' => true, 'stage1' => $s1, 'stage2' => $s2, 'manager' => setting('manager_contact')]);
