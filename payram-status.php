<?php
require_once __DIR__ . '/../includes/payram.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_out(['ok'=>false],405);
$u=current_user(); if(!$u) json_out(['ok'=>false,'error'=>'auth'],401);
$stage=(int)($_GET['stage']??1); if(!in_array($stage,[1,2],true)) json_out(['ok'=>false,'error'=>'stage'],422);
$p=get_payment($u['id'],$stage); if(!$p) json_out(['ok'=>false,'error'=>'no_payment'],404);
if(empty($p['payram_reference'])) json_out(['ok'=>true,'status'=>$p['status'],'payram_status'=>$p['payram_status']]);
$res=payram_get_payment($p['payram_reference']);
if($res['ok'] && is_array($res['data'])) {
    $d=$res['data'];
    $st=(string)($d['status'] ?? $d['paymentState'] ?? '');
    if($st!=='') payram_apply_status((int)$p['id'],$p['payram_reference'],$st,$d);
    $p=get_payment($u['id'],$stage);
}
json_out(['ok'=>true,'status'=>$p['status'],'payram_status'=>$p['payram_status'],'reference_id'=>$p['payram_reference'],'filled_usd'=>$p['payram_filled_usd']]);
