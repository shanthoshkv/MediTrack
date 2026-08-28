<?php
require_once 'core.php';
if (session_status()===PHP_SESSION_NONE) session_start();
if (empty($_SESSION['ct_account_id'])) { header('Location: caretaker_login.php'); exit; }

$pid  = (int)$_SESSION['ct_patient_id'];
$pname = $_SESSION['ct_patient_name'];

$v   = vitals_cols();
$s   = schedule_cols();
$i   = item_cols();
$ivc = iv_cols();
$sl  = scanlog_cols();   // ← ADD
$l   = location_cols();  // ← ADD

$patient = one("SELECT * FROM patients WHERE id={$pid} LIMIT 1");
$vitals  = all_rows("SELECT * FROM patientvitals WHERE `{$v['patientid']}`={$pid} ORDER BY `{$v['recordedat']}` DESC LIMIT 10");
$meds    = all_rows("SELECT ms.*, i.`{$i['itemname']}` AS iname FROM medicationschedule ms
                     JOIN items i ON i.id=ms.`{$s['itemid']}`
                     WHERE ms.`{$s['patientid']}`={$pid}
                     ORDER BY ms.`{$s['scheduledtime']}` DESC LIMIT 15");
$ivs     = all_rows("SELECT * FROM iv_drips WHERE `{$ivc['patientid']}`={$pid} ORDER BY id DESC LIMIT 5");
$alerts  = all_rows("SELECT * FROM alerts WHERE is_resolved=0 ORDER BY id DESC LIMIT 5");
?><!DOCTYPE html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Caretaker Portal — <?= e($pname) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#050c14;color:#edf3ff;font-family:Arial,sans-serif;padding:16px;max-width:900px;margin:0 auto}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid #1a2636;margin-bottom:20px}
.logo{font-size:18px;font-weight:700;color:#56a8ff}
.logout{color:#fb7185;font-size:13px;text-decoration:none}
.card{background:#0b1017;border:1px solid #1a2636;border-radius:14px;padding:18px;margin-bottom:16px}
h3{font-size:15px;margin-bottom:12px;color:#29d3ff}
table{width:100%;border-collapse:collapse;font-size:13px}
th{color:#90a4c2;padding:6px 8px;text-align:left;border-bottom:1px solid #1a2636}
td{padding:7px 8px;border-bottom:1px solid #101722}
.pill{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700}
.ok{background:rgba(52,211,153,.15);color:#34d399}
.warn{background:rgba(251,191,36,.15);color:#fbbf24}
.danger{background:rgba(251,113,133,.15);color:#fb7185}
.grid{display:grid;gap:16px;grid-template-columns:1fr 1fr}
@media(max-width:600px){.grid{grid-template-columns:1fr}}
.info-row{font-size:13px;color:#90a4c2;margin-bottom:4px}
.info-row span{color:#edf3ff;margin-left:6px}
</style></head><body>
<div class="topbar">
    <div class="logo">🏥 MediTrack</div>
    <a href="caretaker_logout.php" class="logout">Logout</a>
</div>

<div class="card">
    <h3>Patient: <?= e($pname) ?></h3>
    <div class="info-row">Status: <span><span class="pill <?= $patient['status']==='critical'?'danger':'ok' ?>"><?= e($patient['status']) ?></span></span></div>
    <div class="info-row">Ward / Bed: <span><?= e($patient['ward_id']??'-') ?> / <?= e($patient['bed_no']??'-') ?></span></div>
    <div class="info-row">Diagnosis: <span><?= e($patient['diagnosis']??'-') ?></span></div>
</div>

<div class="grid">
<div class="card">
    <h3>Recent Vitals</h3>
    <table><tr><th>Time</th><th>Temp</th><th>BP</th><th>Pulse</th><th>SpO2</th></tr>
    <?php foreach ($vitals as $x): ?>
    <tr><td><?= e(ago($x[$v['recordedat']])) ?></td><td><?= e($x['temperature']) ?></td>
        <td><?= e($x['systolic_bp']) ?>/<?= e($x['diastolic_bp']) ?></td>
        <td><?= e($x['pulse_rate']) ?></td><td><?= e($x['spo2']) ?></td></tr>
    <?php endforeach; ?>
    </table>
</div>
<div class="card">
    <h3>Medications</h3>
    <table><tr><th>Time</th><th>Medicine</th><th>Status</th><th>Compliance</th></tr>
    <?php foreach ($meds as $x): ?>
    <tr><td><?= e($x[$s['scheduledtime']]) ?></td><td><?= e($x['iname']) ?></td>
        <td><?= e($x['status']) ?></td><td><?= e($x[$s['compliancestatus']]??'-') ?></td></tr>
    <?php endforeach; ?>
    </table>
</div>
</div>

<div class="card">
    <h3>IV Drips</h3>
    <table><tr><th>Fluid</th><th>Remaining</th><th>Rate</th><th>ETA</th><th>Status</th></tr>
    <?php foreach ($ivs as $x): ?>
    <tr><td><?= e($x[$ivc['fluidname']]) ?></td><td><?= e($x[$ivc['remainingml']]) ?> ml</td>
        <td><?= e($x[$ivc['flowratemlhr']]) ?> ml/hr</td><td><?= e($x[$ivc['etaend']]) ?></td>
        <td><span class="pill <?= $x['status']==='running'?'ok':'warn' ?>"><?= e($x['status']) ?></span></td></tr>
    <?php endforeach; ?>
    </table>
</div>

<?php
$sl  = scanlog_cols();
$l   = location_cols();
$moves = all_rows(
    "SELECT s.*, 
        l1.`{$l['locationname']}` AS from_name,
        l2.`{$l['locationname']}` AS to_name,
        s.`{$sl['actiontype']}` AS action_type,
        s.`{$sl['scantime']}` AS scan_time,
        sm.fullname AS staff_name
     FROM scanlogs s
     LEFT JOIN locations l1 ON l1.id = s.`{$sl['fromlocationid']}`
     LEFT JOIN locations l2 ON l2.id = s.`{$sl['tolocationid']}`
     LEFT JOIN staffmembers sm ON sm.id = s.`{$sl['staffid']}`
     WHERE s.`{$sl['patientid']}`={$pid}
     ORDER BY s.id DESC LIMIT 20"
);

$medlogs = all_rows(
    "SELECT ms.*, i.`{$i['itemname']}` AS iname, ms.`{$s['scheduledtime']}` AS stime,
            ms.`{$s['compliancestatus']}` AS comp, ms.`{$s['verifiedat']}` AS vat
     FROM medicationschedule ms
     JOIN items i ON i.id=ms.`{$s['itemid']}`
     WHERE ms.`{$s['patientid']}`={$pid}
     ORDER BY ms.`{$s['scheduledtime']}` DESC LIMIT 20"
);
?>

<div class="card">
    <h3>Patient Movement History</h3>
    <?php if (empty($moves)): ?>
        <div style="color:#5e728f;font-size:13px">No movement recorded yet.</div>
    <?php else: ?>
    <table>
        <tr><th>Time</th><th>Action</th><th>From</th><th>To</th><th>Staff</th></tr>
        <?php foreach ($moves as $m): ?>
        <tr>
            <td><?= e(ago($m['scan_time'])) ?></td>
            <td><span class="pill <?= $m['action_type']==='medverify'?'ok':($m['action_type']==='patientscan'?'warn':'') ?>">
                <?= e($m['action_type']) ?>
            </span></td>
            <td><?= e($m['from_name'] ?: '—') ?></td>
            <td><?= e($m['to_name'] ?: '—') ?></td>
            <td><?= e($m['staff_name'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Medication Administration Log</h3>
    <?php if (empty($medlogs)): ?>
        <div style="color:#5e728f;font-size:13px">No medication records yet.</div>
    <?php else: ?>
    <table>
        <tr><th>Scheduled</th><th>Medicine</th><th>Status</th><th>Compliance</th><th>Verified At</th></tr>
        <?php foreach ($medlogs as $m): 
            $cls = $m['status']==='administered' ? 'ok' : ($m['status']==='missed' ? 'danger' : 'warn');
        ?>
        <tr>
            <td><?= e($m['stime']) ?></td>
            <td><?= e($m['iname']) ?></td>
            <td><span class="pill <?= $cls ?>"><?= e($m['status']) ?></span></td>
            <td><?= e($m['comp'] ?: '—') ?></td>
            <td><?= e($m['vat'] ? ago($m['vat']) : '—') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php
$nurseVisits = all_rows(
    "SELECT s.`{$sl['scantime']}` AS scan_time,
            s.`{$sl['actiontype']}` AS action_type,
            sm.fullname AS nurse_name,
            sm.role AS nurse_role,
            l.`{$l['locationname']}` AS location_name,
            i.`{$i['itemname']}` AS medicine_name
     FROM scanlogs s
     JOIN staffmembers sm ON sm.id = s.`{$sl['staffid']}`
     LEFT JOIN locations l ON l.id = s.`{$sl['tolocationid']}`
     LEFT JOIN items i ON i.id = s.`{$sl['itemid']}`
     WHERE (
         s.`{$sl['patientid']}` = {$pid}
         OR (
             s.`{$sl['actiontype']}` = 'staffscan'
             AND s.`{$sl['tolocationid']}` = {$patient['ward_id']}
         )
     )
     ORDER BY s.id DESC LIMIT 20"
);
?>
<div class="card">
    <h3>🏥 Nurse & Staff Visits</h3>
    <?php if (empty($nurseVisits)): ?>
        <div style="color:#5e728f;font-size:13px;padding:10px 0">No staff visits recorded yet.</div>
    <?php else: ?>
    <table>
        <tr><th>Time</th><th>Nurse</th><th>Role</th><th>Location</th><th>Activity</th><th>Medicine</th></tr>
        <?php foreach ($nurseVisits as $nv):
            $act = $nv['action_type'];
            $cls = $act === 'medverify' ? 'ok' : ($act === 'staffscan' ? 'info' : 'warn');
            $label = $act === 'medverify' ? '💊 Medication Given' : ($act === 'staffscan' ? '👁 Ward Round' : $act);
        ?>
        <tr>
            <td><?= e(ago($nv['scan_time'])) ?></td>
            <td style="font-weight:600"><?= e($nv['nurse_name']) ?></td>
            <td><?= e($nv['nurse_role']) ?></td>
            <td><?= e($nv['location_name'] ?: '—') ?></td>
            <td><span class="pill <?= $cls ?>"><?= $label ?></span></td>
            <td><?= e($nv['medicine_name'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<p style="text-align:center;color:#5e728f;font-size:12px;margin-top:20px">
    Auto-refreshes every 30s &nbsp;|&nbsp; MediTrack Patient Portal
</p>


<script>setTimeout(()=>location.reload(), 30000);</script>
</body></html>