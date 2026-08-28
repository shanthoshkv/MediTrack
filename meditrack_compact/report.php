<?php
require_once 'core.php';
require_login();

$type = getv('type', 'summary');

$p = patient_cols();
$i = item_cols();
$s = schedule_cols();
$v = vitals_cols();
$t = task_cols();
$a = alert_cols();

render_header('Report', 'reports');

if ($type === 'patient') {
    $patientId = (int)getv('patient_id', 0);

    $patient = one("SELECT * FROM patients WHERE id={$patientId} LIMIT 1");
    if (!$patient) {
        echo '<div class="banner">Patient not found</div>';
        render_footer();
        exit;
    }

    $vitals = all_rows("SELECT * FROM patientvitals WHERE `{$v['patientid']}`={$patientId} ORDER BY `{$v['recordedat']}` DESC LIMIT 20");
    $tasks = all_rows("SELECT * FROM workflowtasks WHERE `{$t['patientid']}`={$patientId} ORDER BY `{$t['createdat']}` DESC LIMIT 20");
    $meds = all_rows(
        "SELECT ms.*, i.`{$i['itemname']}` AS item_name
         FROM medicationschedule ms
         JOIN items i ON i.id=ms.`{$s['itemid']}`
         WHERE ms.`{$s['patientid']}`={$patientId}
         ORDER BY ms.`{$s['scheduledtime']}` DESC
         LIMIT 20"
    );

    echo '<div class="card">';
    echo '<h3>Patient Report</h3>';
    echo '<div style="font-size:22px;font-weight:800">' . e($patient['fullname']) . '</div>';
    echo '<div class="muted" style="margin-top:6px">Code: ' . e($patient[$p['patientcode']] ?? '') . '</div>';
    echo '</div>';

    echo '<div class="grid g2" style="margin-top:18px">';

    echo '<div class="card"><h3>Vitals</h3><table class="table"><tr><th>Time</th><th>Temp</th><th>BP</th><th>Pulse</th><th>SpO2</th></tr>';
    foreach ($vitals as $x) {
        echo '<tr>';
        echo '<td>' . e($x[$v['recordedat']]) . '</td>';
        echo '<td>' . e($x['temperature']) . '</td>';
        echo '<td>' . e($x['systolic_bp']) . '/' . e($x['diastolic_bp']) . '</td>';
        echo '<td>' . e($x['pulse_rate']) . '</td>';
        echo '<td>' . e($x['spo2']) . '</td>';
        echo '</tr>';
    }
    echo '</table></div>';

    echo '<div class="card"><h3>Workflow Tasks</h3><table class="table"><tr><th>Time</th><th>Type</th><th>Title</th><th>Status</th><th>Priority</th></tr>';
    foreach ($tasks as $x) {
        echo '<tr>';
        echo '<td>' . e($x[$t['createdat']]) . '</td>';
        echo '<td>' . e($x[$t['tasktype']]) . '</td>';
        echo '<td>' . e($x['title']) . '</td>';
        echo '<td>' . e($x['status']) . '</td>';
        echo '<td>' . e($x['priority']) . '</td>';
        echo '</tr>';
    }
    echo '</table></div>';

    echo '</div>';

    echo '<div class="card" style="margin-top:18px"><h3>Medication History</h3><table class="table"><tr><th>Time</th><th>Medicine</th><th>Status</th><th>Compliance</th></tr>';
    foreach ($meds as $x) {
        echo '<tr>';
        echo '<td>' . e($x[$s['scheduledtime']]) . '</td>';
        echo '<td>' . e($x['item_name']) . '</td>';
        echo '<td>' . e($x['status']) . '</td>';
        echo '<td>' . e($x[$s['compliancestatus']] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</table></div>';

    render_footer();
    exit;
}

$k = one(
    "SELECT
        (SELECT COUNT(*) FROM patients WHERE status<>'discharged') AS patients,
        (SELECT COUNT(*) FROM items) AS items,
        (SELECT COUNT(*) FROM alerts WHERE `{$a['isresolved']}`=0) AS alerts,
        (SELECT COUNT(*) FROM workflowtasks WHERE status<>'done') AS tasks"
);

$recentAlerts = all_rows(
    "SELECT id, severity, message, `{$a['createdat']}` AS created_at
     FROM alerts
     ORDER BY `{$a['createdat']}` DESC
     LIMIT 20"
);

?>
<div class="grid g4">
    <div class="card kpi"><div class="label">Patients</div><div class="value"><?= e($k['patients']) ?></div></div>
    <div class="card kpi"><div class="label">Items</div><div class="value"><?= e($k['items']) ?></div></div>
    <div class="card kpi"><div class="label">Open Alerts</div><div class="value"><?= e($k['alerts']) ?></div></div>
    <div class="card kpi"><div class="label">Open Tasks</div><div class="value"><?= e($k['tasks']) ?></div></div>
</div>

<div class="card" style="margin-top:18px">
    <h3>Recent Alerts</h3>
    <table class="table">
        <tr><th>Time</th><th>Severity</th><th>Message</th></tr>
        <?php foreach ($recentAlerts as $x): ?>
        <tr>
            <td><?= e($x['created_at']) ?></td>
            <td><?= e($x['severity']) ?></td>
            <td><?= e($x['message']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php render_footer(); ?>