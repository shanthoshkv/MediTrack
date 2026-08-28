<?php
require_once 'core.php';

$tok = token_cols();
$v = vitals_cols();
$s = schedule_cols();
$i = item_cols();
$ivc = iv_cols();

$token = getv('token');
if (!$token) die('Missing token');

$row = one(
    "SELECT
        ct.*,
        c.fullname AS caretaker_name,
        p.fullname AS patient_name
     FROM caretaker_tokens ct
     JOIN caretakers c ON c.id = ct.`{$tok['caretakerid']}`
     JOIN patients p ON p.id = ct.`{$tok['patientid']}`
     WHERE ct.token='" . $db->real_escape_string($token) . "'
       AND ct.`{$tok['isactive']}`=1
       AND ct.`{$tok['expiresat']}` > NOW()
     LIMIT 1"
);
if (!$row) die('Invalid or expired token');

$vitals = all_rows(
    "SELECT *
     FROM patientvitals
     WHERE `{$v['patientid']}`=" . (int)$row[$tok['patientid']] . "
     ORDER BY `{$v['recordedat']}` DESC
     LIMIT 5"
);

$meds = all_rows(
    "SELECT
        ms.*,
        i.`{$i['itemname']}` AS item_name
     FROM medicationschedule ms
     JOIN items i ON i.id=ms.`{$s['itemid']}`
     WHERE ms.`{$s['patientid']}`=" . (int)$row[$tok['patientid']] . "
     ORDER BY ms.`{$s['scheduledtime']}` DESC
     LIMIT 10"
);

$ivs = all_rows(
    "SELECT *
     FROM iv_drips
     WHERE `{$ivc['patientid']}`=" . (int)$row[$tok['patientid']] . "
     ORDER BY id DESC
     LIMIT 5"
);

render_header('Caretaker Portal', 'portal', false);
?>
<div style="max-width:1100px;margin:0 auto">
    <div class="card">
        <h3><i class="fas fa-user-group"></i> Caretaker Access</h3>
        <div class="muted">Caretaker: <?= e($row['caretaker_name']) ?></div>
        <div style="font-size:22px;font-weight:800;margin-top:10px"><?= e($row['patient_name']) ?></div>
        <div class="muted" style="margin-top:6px">Token valid till <?= e($row[$tok['expiresat']]) ?></div>
    </div>

    <div class="grid g2" style="margin-top:18px">
        <div class="card">
            <h3>Recent Vitals</h3>
            <table class="table">
                <tr><th>Time</th><th>Temp</th><th>BP</th><th>Pulse</th><th>SpO2</th></tr>
                <?php foreach ($vitals as $x): ?>
                <tr>
                    <td><?= e($x[$v['recordedat']]) ?></td>
                    <td><?= e($x['temperature']) ?></td>
                    <td><?= e($x['systolic_bp']) ?>/<?= e($x['diastolic_bp']) ?></td>
                    <td><?= e($x['pulse_rate']) ?></td>
                    <td><?= e($x['spo2']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <h3>Medication Updates</h3>
            <table class="table">
                <tr><th>Time</th><th>Medicine</th><th>Status</th><th>Compliance</th></tr>
                <?php foreach ($meds as $x): ?>
                <tr>
                    <td><?= e($x[$s['scheduledtime']]) ?></td>
                    <td><?= e($x['item_name']) ?></td>
                    <td><?= e($x['status']) ?></td>
                    <td><?= e($x[$s['compliancestatus']] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <h3>IV Drips</h3>
        <table class="table">
            <tr><th>Fluid</th><th>Remaining</th><th>Rate</th><th>ETA</th><th>Status</th></tr>
            <?php foreach ($ivs as $x): ?>
            <tr>
                <td><?= e($x[$ivc['fluidname']]) ?></td>
                <td><?= e($x[$ivc['remainingml']]) ?> ml</td>
                <td><?= e($x[$ivc['flowratemlhr']]) ?> ml/hr</td>
                <td><?= e($x[$ivc['etaend']]) ?></td>
                <td><?= e($x['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php render_footer(false); ?>