<?php
require_once 'core.php';
require_login();
header('Content-Type: application/json');

$sl = scanlog_cols();
$i  = item_cols();
$l  = location_cols();
$a  = alert_cols();

$k = one(
    "SELECT
        (SELECT COUNT(*) FROM patients WHERE status<>'discharged') AS patients,
        (SELECT COUNT(*) FROM items) AS items,
        (SELECT COUNT(*) FROM alerts WHERE `{$a['isresolved']}`=0) AS alerts,
        (SELECT COUNT(*) FROM workflowtasks WHERE status<>'done') AS tasks,
        (SELECT COUNT(*) FROM scanlogs WHERE DATE(`{$sl['scantime']}`)=CURDATE()) AS scans,
        (SELECT COUNT(*) FROM locations WHERE `{$l['readerid']}` IS NOT NULL
            AND (`{$l['lastheartbeat']}` IS NULL OR `{$l['lastheartbeat']}` < NOW() - INTERVAL 10 MINUTE)) AS offline"
);

$recentMoves = all_rows(
    "SELECT
        s.id,
        s.uid,
        s.`{$sl['actiontype']}` AS action_type,
        s.`{$sl['scantime']}` AS scan_time,
        p.fullname AS patient_name,
        i.`{$i['itemname']}` AS item_name,
        l1.`{$l['locationname']}` AS from_name,
        l2.`{$l['locationname']}` AS to_name,
        sm.fullname AS staff_name,
        sm.role AS staff_role
     FROM scanlogs s
     LEFT JOIN patients p ON p.id = s.`{$sl['patientid']}`
     LEFT JOIN items i ON i.id = s.`{$sl['itemid']}`
     LEFT JOIN locations l1 ON l1.id = s.`{$sl['fromlocationid']}`
     LEFT JOIN locations l2 ON l2.id = s.`{$sl['tolocationid']}`
     LEFT JOIN staffmembers sm ON sm.id = s.`{$sl['staffid']}`
     ORDER BY s.id DESC
     LIMIT 10"
);

$alerts = all_rows(
    "SELECT id, severity, `{$a['type']}` AS alert_type, message, `{$a['createdat']}` AS created_at, `{$a['isresolved']}` AS is_resolved
     FROM alerts
     ORDER BY `{$a['isresolved']}`, id DESC
     LIMIT 10"
);

echo json_encode([
    'ok'          => true,
    'kpi'         => $k,
    'moves'       => $recentMoves,
    'alerts'      => $alerts,
    'server_time' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);