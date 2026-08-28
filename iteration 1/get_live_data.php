<?php
require_once 'core.php';
header('Content-Type: application/json; charset=UTF-8');

$d = [];
$row = db()->query("SELECT
    COUNT(*) AS total_items,
    COALESCE(SUM(status='in_stock'),0) AS in_stock,
    COALESCE(SUM(status='checked_out'),0) AS checked_out,
    COALESCE(SUM(lifecycle='expired'),0) AS expired,
    COALESCE(SUM(lifecycle='expiring_soon'),0) AS expiring_soon,
    COALESCE(SUM(status='missing'),0) AS missing
    FROM items")->fetch_assoc();

$d = array_map('intval', $row);
$d['today_scans']    = (int)db()->query("SELECT COUNT(*) AS c FROM scan_logs WHERE DATE(scan_time)=CURDATE()")->fetch_assoc()['c'];
$d['unknown_today']  = (int)db()->query("SELECT COUNT(*) AS c FROM scan_logs WHERE DATE(scan_time)=CURDATE() AND item_id IS NULL")->fetch_assoc()['c'];
$d['open_alerts']    = (int)db()->query("SELECT COUNT(*) AS c FROM alerts WHERE is_resolved=0")->fetch_assoc()['c'];
$d['offline_readers']= (int)db()->query("SELECT COUNT(*) AS c FROM locations WHERE reader_id IS NOT NULL AND (last_heartbeat IS NULL OR last_heartbeat < NOW() - INTERVAL 120 SECOND)")->fetch_assoc()['c'];

$d['logs'] = [];
$logs = db()->query("SELECT s.scan_time, s.uid, s.action_type, s.reader_id, i.item_name, l.location_name
    FROM scan_logs s
    LEFT JOIN items i ON s.item_id=i.id
    LEFT JOIN locations l ON s.to_location_id=l.id
    ORDER BY s.id DESC LIMIT 20");
while ($lg = $logs->fetch_assoc()) $d['logs'][] = $lg;

$d['zone_items'] = [];
$items = db()->query("SELECT i.item_name, i.status, i.lifecycle, l.location_name, i.last_seen
    FROM items i LEFT JOIN locations l ON i.location_id=l.id
    WHERE i.status IN ('in_stock','checked_out') ORDER BY i.last_seen DESC LIMIT 30");
while ($it = $items->fetch_assoc()) $d['zone_items'][] = $it;

$d['readers'] = [];
$readers = db()->query("SELECT reader_id, location_name, zone, last_heartbeat FROM locations WHERE reader_id IS NOT NULL");
while ($rd = $readers->fetch_assoc()) {
    $rd['is_offline'] = !$rd['last_heartbeat'] || strtotime($rd['last_heartbeat']) < (time()-120);
    $d['readers'][] = $rd;
}

$d['server_time'] = date('d M Y, H:i:s');
echo json_encode($d, JSON_UNESCAPED_UNICODE);
?>
