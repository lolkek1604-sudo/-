<?php
require_once __DIR__ . '/../includes/payram.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['message'=>'method not allowed'],405);
if (!payram_webhook_valid()) json_out(['message'=>'invalid webhook authentication'],401);
$payload=body_json();
$ref=trim((string)($payload['reference_id']??''));
$status=strtoupper(trim((string)($payload['status']??'')));
if($ref===''||$status==='') json_out(['message'=>'missing reference_id or status'],422);
$eventKey=$ref.':'.$status.':'.sha1(json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
try {
    $ins=db()->prepare("INSERT INTO payram_events(event_key,reference_id,status,payload_json) VALUES(?,?,?,?)");
    $ins->execute([$eventKey,$ref,$status,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
} catch(Throwable $e) {
    if(strpos($e->getMessage(),'UNIQUE')!==false) json_out(['message'=>'duplicate']);
    throw $e;
}

$st=db()->prepare('SELECT id FROM payments WHERE payram_reference=? LIMIT 1');
$st->execute([$ref]);
$id=$st->fetchColumn();

// Official widget creates the PayRam reference in the browser. Before the first
// webhook arrives we therefore match the event by our deterministic customerID.
if (!$id && !empty($payload['customer_id'])) {
    $cid = (string)$payload['customer_id'];
    if (preg_match('/^gtd_user_(\d+)_stage_(1|2)$/', $cid, $m)) {
        $uid=(int)$m[1]; $stage=(int)$m[2];
        $q=db()->prepare('SELECT id FROM payments WHERE user_id=? AND stage=? ORDER BY id DESC LIMIT 1');
        $q->execute([$uid,$stage]); $id=$q->fetchColumn();
    }
}
if(!$id) json_out(['message'=>'received','matched'=>false]);
payram_apply_status((int)$id,$ref,$status,$payload);
json_out(['message'=>'Webhook received successfully']);
