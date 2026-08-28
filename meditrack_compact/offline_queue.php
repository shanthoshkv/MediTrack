<?php
require_once 'core.php';
header('Content-Type: application/json');

$l = location_cols();
$readerid = $_POST['readerid'] ?? getv('readerid');
$apikey   = $_POST['apikey']   ?? getv('apikey');

if (!$readerid || !$apikey) exit(json_encode(['ok'=>false,'msg'=>'Missing credentials']));

$reader = one("SELECT * FROM locations
               WHERE `{$l['readerid']}`='".$db->real_escape_string($readerid)."'
               AND `{$l['apikey']}`='".$db->real_escape_string($apikey)."'
               AND `{$l['isactive']}`=1 LIMIT 1");
if (!$reader) { http_response_code(403); exit(json_encode(['ok'=>false,'msg'=>'Unauthorized'])); }

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) exit(json_encode(['ok'=>false,'msg'=>'Invalid JSON']));

$n = 0;
foreach ($body as $scan) {
    $uid = strtoupper($db->real_escape_string($scan['uid'] ?? ''));
    $qat = $db->real_escape_string($scan['queued_at'] ?? date('Y-m-d H:i:s'));
    if (!$uid) continue;
    $db->query("INSERT INTO offline_scan_queue(readerid,uid,queued_at)
                VALUES('".$db->real_escape_string($readerid)."','{$uid}','{$qat}')");
    $n++;
}
echo json_encode(['ok'=>true,'queued'=>$n]);