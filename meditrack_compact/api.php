<?php
require_once 'core.php';

header('Content-Type: application/json');

$a = alert_cols();
$n = notification_cols();
$slog = scanlog_cols();
$l = location_cols();
$t = task_cols();
$p = patient_cols();
$i = item_cols();

require_login();
$action = getv('action');

if ($action === 'notifications') {
    $adminId = current_admin_id();

    $items = all_rows(
        "SELECT
            a.id,
            a.severity,
            a.`{$a['type']}` AS alert_type,
            a.message,
            a.`{$a['url']}` AS related_url,
            a.`{$a['createdat']}` AS created_at
         FROM alerts a
         WHERE a.`{$a['isresolved']}`=0
         ORDER BY a.`{$a['createdat']}` DESC
         LIMIT 8"
    );

    $countRow = one(
        "SELECT COUNT(*) AS c
         FROM alerts a
         LEFT JOIN notification_reads n
           ON n.`{$n['alertid']}`=a.id
          AND n.`{$n['adminid']}`={$adminId}
         WHERE a.`{$a['isresolved']}`=0
           AND n.id IS NULL"
    );
    $count = (int)($countRow['c'] ?? 0);

    foreach ($items as $it) {
        q("INSERT IGNORE INTO notification_reads(`{$n['alertid']}`, `{$n['adminid']}`, `{$n['readat']}`)
           VALUES(" . (int)$it['id'] . ", {$adminId}, NOW())");
    }

    echo json_encode(['count' => $count, 'items' => $items]);
    exit;
}

if ($action === 'movementreplay') {
    $pid = (int)getv('patientid', 0);
    $where = $pid ? "WHERE s.`{$slog['patientid']}`={$pid}" : "";

    $rows = all_rows(
        "SELECT
            s.id,
            s.uid,
            s.`{$slog['actiontype']}` AS action_type,
            s.`{$slog['scantime']}` AS scan_time,
            p.fullname AS patient_name,
            i.`{$i['itemname']}` AS item_name,
            lf.`{$l['locationname']}` AS from_name,
            lt.`{$l['locationname']}` AS to_name
         FROM scanlogs s
         LEFT JOIN patients p ON p.id=s.`{$slog['patientid']}`
         LEFT JOIN items i ON i.id=s.`{$slog['itemid']}`
         LEFT JOIN locations lf ON lf.id=s.`{$slog['fromlocationid']}`
         LEFT JOIN locations lt ON lt.id=s.`{$slog['tolocationid']}`
         {$where}
         ORDER BY s.id DESC
         LIMIT 150"
    );

    echo json_encode(['items' => $rows]);
    exit;
}

if ($action === 'workflow') {
    $rows = all_rows(
        "SELECT
            w.id,
            w.`{$t['tasktype']}` AS task_type,
            w.title,
            w.description,
            w.priority,
            w.status,
            w.`{$t['createdat']}` AS created_at,
            w.`{$t['completedat']}` AS completed_at,
            p.fullname AS patient_name,
            i.`{$i['itemname']}` AS item_name
         FROM workflowtasks w
         LEFT JOIN patients p ON p.id=w.`{$t['patientid']}`
         LEFT JOIN items i ON i.id=w.`{$t['itemid']}`
         ORDER BY FIELD(w.priority,'critical','high','medium','low'), w.`{$t['createdat']}` DESC
         LIMIT 120"
    );

    echo json_encode(['items' => $rows]);
    exit;
}

if ($action === 'zoneheatmap') {
    $rows = all_rows(
        "SELECT
            l.id,
            l.`{$l['locationname']}` AS location_name,
            SUM(HOUR(s.`{$slog['scantime']}`) BETWEEN 0 AND 5) AS h1,
            SUM(HOUR(s.`{$slog['scantime']}`) BETWEEN 6 AND 11) AS h2,
            SUM(HOUR(s.`{$slog['scantime']}`) BETWEEN 12 AND 17) AS h3,
            SUM(HOUR(s.`{$slog['scantime']}`) BETWEEN 18 AND 23) AS h4
         FROM locations l
         LEFT JOIN scanlogs s ON s.`{$slog['tolocationid']}`=l.id
         GROUP BY l.id, l.`{$l['locationname']}`
         ORDER BY l.`{$l['locationname']}`"
    );

    echo json_encode(['items' => $rows]);
    exit;
}

if ($action === 'audit_export') {
    require_login();
    $fmt  = getv('format','csv');
    $from = $db->real_escape_string(getv('from', date('Y-m-d',strtotime('-30 days'))));
    $to   = $db->real_escape_string(getv('to',   date('Y-m-d')));
    $rows = all_rows(
        "SELECT al.id, al.created_at, a.username, al.action, al.target_table,
                al.target_id, al.detail, al.ip
         FROM audit_logs al LEFT JOIN admins a ON a.id=al.admin_id
         WHERE DATE(al.created_at) BETWEEN '{$from}' AND '{$to}'
         ORDER BY al.created_at DESC"
    );
    if ($fmt === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_'.$from.'_'.$to.'.csv"');
        echo "ID,Time,Admin,Action,Table,Record ID,Detail,IP\n";
        foreach ($rows as $r) {
            echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',(string)$v).'"',
                [$r['id'],$r['created_at'],$r['username'],$r['action'],
                 $r['target_table'],$r['target_id'],$r['detail'],$r['ip']])) . "\n";
        }
        exit;
    }
    // PDF via printable HTML
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html><html><head><meta charset="utf-8">
    <title>Audit Log <?= e($from) ?> to <?= e($to) ?></title>
    <style>
    body{font-family:Arial,sans-serif;font-size:12px;color:#111;margin:20px}
    h2{color:#1e3a5f}p{color:#555}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th{background:#1e3a5f;color:#fff;padding:6px 8px;text-align:left}
    td{padding:5px 8px;border-bottom:1px solid #ddd}
    tr:nth-child(even) td{background:#f4f7fb}
    .no-print{margin-bottom:12px}
    @media print{.no-print{display:none}}
    </style></head><body>
    <div class="no-print"><button onclick="window.print()">🖨️ Print / Save as PDF</button></div>
    <h2>MediTrack — Audit Log</h2>
    <p>Period: <?= e($from) ?> to <?= e($to) ?> &nbsp;|&nbsp; Records: <?= count($rows) ?> &nbsp;|&nbsp; Generated: <?= date('Y-m-d H:i:s') ?></p>
    <table><tr><th>ID</th><th>Time</th><th>Admin</th><th>Action</th><th>Table</th><th>Record</th><th>Detail</th><th>IP</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['id']) ?></td><td><?= e($r['created_at']) ?></td><td><?= e($r['username']??'system') ?></td>
        <td><?= e($r['action']) ?></td><td><?= e($r['target_table']) ?></td><td><?= e($r['target_id']) ?></td>
        <td><?= e($r['detail']) ?></td><td><?= e($r['ip']) ?></td></tr>
    <?php endforeach; ?>
    </table></body></html><?php
    exit;
}

if ($action === 'backup') {
    require_login();
    global $DB_NAME, $DB_HOST, $DB_USER, $DB_PASS;
    $file = 'meditrack_backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    echo "-- MediTrack Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = all_rows("SHOW TABLES");
    foreach ($tables as $trow) {
        $t = array_values($trow)[0];
        $cr = one("SHOW CREATE TABLE `{$t}`");
        echo "DROP TABLE IF EXISTS `{$t}`;\n" . array_values($cr)[1] . ";\n\n";
        $data = all_rows("SELECT * FROM `{$t}`");
        foreach ($data as $row) {
            $vals = array_map(fn($v) => $v===null ? 'NULL' : "'".$db->real_escape_string((string)$v)."'", $row);
            echo "INSERT INTO `{$t}` VALUES(".implode(',',$vals).");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    audit('db_backup','',0,'Manual backup downloaded');
    exit;
}



if ($action === 'report_export') {
    $type = getv('type', 'summary');
    $pid  = (int)getv('patient_id', 0);
    header('Content-Type: text/html; charset=utf-8');
    $a2 = alert_cols(); $t2 = task_cols();
    ?><!DOCTYPE html><html><head><meta charset="utf-8">
    <title>MediTrack Report</title>
    <style>
    body{font-family:Arial,sans-serif;margin:40px;line-height:1.6}
    h1,h2{color:#1e3a5f}
    table{width:100%;border-collapse:collapse;margin:20px 0}
    th,td{border:1px solid #ddd;padding:10px;text-align:left}
    th{background:#1e3a5f;color:white}
    .header{text-align:center;border-bottom:3px solid #1e3a5f;padding-bottom:20px;margin-bottom:30px}
    .no-print{margin-bottom:12px}
    @media print{.no-print{display:none}}
    </style></head><body>
    <div class="no-print"><button onclick="window.print()">🖨️ Print / Save as PDF</button></div>
    <div class="header">
        <h1>MediTrack Hospital Management System</h1>
        <p><strong><?= date('d M Y, H:i') ?></strong></p>
    </div>
    <?php
    if ($type === 'patient' && $pid > 0) {
        $pat = one("SELECT * FROM patients WHERE id={$pid} LIMIT 1");
        $pp  = patient_cols(); $vv = vitals_cols(); $ss = schedule_cols(); $ii = item_cols();
        $pvitals = all_rows("SELECT * FROM patientvitals WHERE `{$vv['patientid']}`={$pid} ORDER BY `{$vv['recordedat']}` DESC LIMIT 20");
        $pmeds   = all_rows("SELECT ms.*, i.`{$ii['itemname']}` AS iname FROM medicationschedule ms JOIN items i ON i.id=ms.`{$ss['itemid']}` WHERE ms.`{$ss['patientid']}`={$pid} ORDER BY ms.`{$ss['scheduledtime']}` DESC LIMIT 20");
        echo "<h2>Patient Report: " . e($pat['fullname'] ?? 'Unknown') . "</h2>";
        echo "<p>Code: " . e($pat[$pp['patientcode']] ?? '') . " &nbsp;|&nbsp; Status: " . e($pat['status'] ?? '') . " &nbsp;|&nbsp; Diagnosis: " . e($pat['diagnosis'] ?? '') . "</p>";
        echo "<h3>Vitals</h3><table><tr><th>Time</th><th>Temp</th><th>BP</th><th>Pulse</th><th>SpO2</th></tr>";
        foreach ($pvitals as $x) echo "<tr><td>".e($x[$vv['recordedat']])."</td><td>".e($x['temperature'])."</td><td>".e($x['systolic_bp'])."/".e($x['diastolic_bp'])."</td><td>".e($x['pulse_rate'])."</td><td>".e($x['spo2'])."</td></tr>";
        echo "</table><h3>Medications</h3><table><tr><th>Time</th><th>Medicine</th><th>Status</th><th>Compliance</th></tr>";
        foreach ($pmeds as $x) echo "<tr><td>".e($x[$ss['scheduledtime']])."</td><td>".e($x['iname'])."</td><td>".e($x['status'])."</td><td>".e($x[$ss['compliancestatus']] ?? '')."</td></tr>";
        echo "</table>";
    } else {
        $kk = one("SELECT (SELECT COUNT(*) FROM patients WHERE status<>'discharged') AS patients,
                          (SELECT COUNT(*) FROM alerts WHERE `{$a2['isresolved']}`=0) AS alerts,
                          (SELECT COUNT(*) FROM workflowtasks WHERE status<>'done') AS tasks");
        echo "<h2>Hospital Summary Report</h2>";
        echo "<table><tr><th>Metric</th><th>Value</th></tr>";
        echo "<tr><td>Active Patients</td><td>".(int)$kk['patients']."</td></tr>";
        echo "<tr><td>Open Alerts</td><td>".(int)$kk['alerts']."</td></tr>";
        echo "<tr><td>Pending Tasks</td><td>".(int)$kk['tasks']."</td></tr>";
        echo "</table>";
    }
    echo '</body></html>';
    exit;
}
echo json_encode(['ok' => false, 'message' => 'Unknown action']);