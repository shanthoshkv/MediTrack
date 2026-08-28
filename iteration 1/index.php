<?php
require_once 'core.php';
refresh_lifecycles();

if (isset($_GET['resolve_alert'])) {
    $id = (int)$_GET['resolve_alert'];
    $note = db()->real_escape_string($_GET['note'] ?? 'Resolved via dashboard');
    db()->query("UPDATE alerts SET is_resolved=1, resolved_by='{$_SESSION['admin_name']}', resolved_at=NOW(), action_taken='$note' WHERE id=$id");
    sys_log('info','index.php',"Alert #$id resolved by {$_SESSION['admin_name']}");
    header("Location: index.php"); exit;
}

render_header('Hospital Dashboard');

// ---- KPIs: Assets ----
$kpi = db()->query("SELECT COUNT(*) AS total, COALESCE(SUM(status='in_stock'),0) AS in_stock, COALESCE(SUM(status='checked_out'),0) AS checked_out, COALESCE(SUM(status='under_repair'),0) AS under_repair, COALESCE(SUM(status='missing'),0) AS missing, COALESCE(SUM(lifecycle='expiring_soon'),0) AS expiring_soon, COALESCE(SUM(lifecycle='expired'),0) AS expired, COALESCE(SUM(quantity<=min_stock_level AND min_stock_level>0),0) AS low_stock, COALESCE(SUM(unit_cost*quantity),0) AS total_value FROM items")->fetch_assoc();

// ---- KPIs: Patients ----
$pat = db()->query("SELECT COALESCE(SUM(status IN ('admitted','icu','critical')),0) AS active, COALESCE(SUM(status='icu'),0) AS icu, COALESCE(SUM(status='critical'),0) AS critical, COALESCE(SUM(status='discharged' AND DATE(discharge_date)=CURDATE()),0) AS discharged_today, COALESCE(SUM(DATE(admission_date)=CURDATE()),0) AS admitted_today FROM patients")->fetch_assoc();

// ---- KPIs: Operations ----
$today_scans   = (int)db()->query("SELECT COUNT(*) as c FROM scan_logs WHERE DATE(scan_time)=CURDATE()")->fetch_assoc()['c'];
$open_alerts   = (int)db()->query("SELECT COUNT(*) as c FROM alerts WHERE is_resolved=0")->fetch_assoc()['c'];
$pending_appr  = (int)db()->query("SELECT COUNT(*) as c FROM approvals WHERE status='pending'")->fetch_assoc()['c'];
$offline_rdrs  = (int)db()->query("SELECT COUNT(*) as c FROM locations WHERE reader_id IS NOT NULL AND (last_heartbeat IS NULL OR last_heartbeat < NOW()-INTERVAL 120 SECOND)")->fetch_assoc()['c'];
$maint_overdue = (int)db()->query("SELECT COUNT(*) as c FROM maintenance_logs WHERE status='overdue'")->fetch_assoc()['c'];

// ---- Blood Bank Summary ----
$blood = db()->query("SELECT blood_group, SUM(status='available') as avail FROM blood_units GROUP BY blood_group ORDER BY blood_group")->fetch_all(MYSQLI_ASSOC);

// ---- Chart: Scans last 14 days ----
$chartDays=[];$chartScans=[];$chartTransfers=[];
for($i=13;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-$i days"));
    $chartDays[]=date('d M',strtotime($d));
    $row=db()->query("SELECT COUNT(*) as t, SUM(action_type='transfer') as tr FROM scan_logs WHERE DATE(scan_time)='$d'")->fetch_assoc();
    $chartScans[]=(int)$row['t']; $chartTransfers[]=(int)$row['tr'];
}

// ---- Chart: Assets by Category ----
$cats=[];$catCounts=[];
$catRes=db()->query("SELECT item_category, COUNT(*) as c FROM items GROUP BY item_category ORDER BY c DESC LIMIT 8");
while($r=$catRes->fetch_assoc()){$cats[]=$r['item_category'];$catCounts[]=(int)$r['c'];}

// ---- Chart: Status Breakdown ----
$statusLabels=['In Stock','Checked Out','Under Repair','Missing','Sterilizing','Quarantined'];
$statusCounts=[];
foreach(['in_stock','checked_out','under_repair','missing','sterilizing','quarantined'] as $s)
    $statusCounts[]=(int)db()->query("SELECT COUNT(*) as c FROM items WHERE status='$s'")->fetch_assoc()['c'];

// ---- Chart: Patient Status ----
$patLabels=['Admitted','ICU','Critical','Outpatient','Discharged'];
$patCounts=[];
foreach(['admitted','icu','critical','outpatient','discharged'] as $s)
    $patCounts[]=(int)db()->query("SELECT COUNT(*) as c FROM patients WHERE status='$s'")->fetch_assoc()['c'];

// ---- Recent Alerts ----
$alerts = db()->query("SELECT * FROM alerts WHERE is_resolved=0 ORDER BY created_at DESC LIMIT 6");
$approvals = db()->query("SELECT a.*, i.item_name FROM approvals a JOIN items i ON a.item_id=i.id WHERE a.status='pending' ORDER BY a.created_at DESC LIMIT 5");
$movements = db()->query("SELECT s.scan_time,s.action_type,s.uid,i.item_name,lf.location_name as from_loc,lt.location_name as to_loc FROM scan_logs s LEFT JOIN items i ON s.item_id=i.id LEFT JOIN locations lf ON s.from_location_id=lf.id LEFT JOIN locations lt ON s.to_location_id=lt.id ORDER BY s.id DESC LIMIT 8");
$active_patients = db()->query("SELECT p.*, l.location_name FROM patients p LEFT JOIN locations l ON p.ward_id=l.id WHERE p.status IN ('admitted','icu','critical') ORDER BY FIELD(p.status,'critical','icu','admitted') LIMIT 8");
?>

<!-- PATIENT CENSUS ROW -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
    <span style="font-size:10px;font-family:'DM Mono',monospace;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase">PATIENT CENSUS</span>
    <span style="flex:1;height:1px;background:var(--border)"></span>
</div>
<div class="grid-4" style="margin-bottom:8px">
    <div class="stat-card">
        <div class="stat-label">Active Inpatients</div>
        <div class="stat-value" style="color:var(--cyan)"><?= $pat['active'] ?></div>
        <div class="stat-sub"><?= $pat['admitted_today'] ?> admitted today</div>
        <i class="fas fa-hospital-user stat-icon" style="color:var(--cyan)"></i>
    </div>
    <div class="stat-card">
        <div class="stat-label">ICU Patients</div>
        <div class="stat-value" style="color:var(--yellow)"><?= $pat['icu'] ?></div>
        <div class="stat-sub"><?= $pat['critical'] ?> critical</div>
        <i class="fas fa-heartbeat stat-icon" style="color:var(--yellow)"></i>
    </div>
    <div class="stat-card">
        <div class="stat-label">Discharged Today</div>
        <div class="stat-value" style="color:var(--green)"><?= $pat['discharged_today'] ?></div>
        <div class="stat-sub">Bed turnover</div>
        <i class="fas fa-person-walking-arrow-right stat-icon" style="color:var(--green)"></i>
    </div>
    <div class="stat-card">
        <div class="stat-label">RFID Scans Today</div>
        <div class="stat-value" style="color:var(--accent)"><?= number_format($today_scans) ?></div>
        <div class="stat-sub"><?= $offline_rdrs ?> reader<?= $offline_rdrs!=1?'s':'' ?> offline</div>
        <i class="fas fa-rss stat-icon" style="color:var(--accent)"></i>
    </div>
</div>

<!-- ASSET KPI ROW -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;margin-top:6px">
    <span style="font-size:10px;font-family:'DM Mono',monospace;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase">ASSET OVERVIEW</span>
    <span style="flex:1;height:1px;background:var(--border)"></span>
</div>
<div class="grid-4">
    <div class="stat-card">
        <div class="stat-label">Total Assets</div>
        <div class="stat-value"><?= number_format($kpi['total']) ?></div>
        <div class="stat-sub">₹<?= number_format($kpi['total_value']/100000,1) ?>L portfolio value</div>
        <i class="fas fa-boxes-stacked stat-icon"></i>
    </div>
    <div class="stat-card">
        <div class="stat-label">In Stock</div>
        <div class="stat-value" style="color:var(--green)"><?= number_format($kpi['in_stock']) ?></div>
        <div class="stat-sub"><?= $kpi['checked_out'] ?> checked out · <?= $kpi['under_repair'] ?> in repair</div>
        <i class="fas fa-circle-check stat-icon" style="color:var(--green)"></i>
    </div>
    <div class="stat-card">
        <div class="stat-label">Lifecycle Warnings</div>
        <div class="stat-value" style="color:var(--yellow)"><?= $kpi['expiring_soon']+$kpi['expired'] ?></div>
        <div class="stat-sub"><?= $kpi['expired'] ?> expired · <?= $kpi['low_stock'] ?> low stock</div>
        <i class="fas fa-triangle-exclamation stat-icon" style="color:var(--yellow)"></i>
    </div>
    <div class="stat-card">
        <div class="stat-label">Actions Needed</div>
        <div class="stat-value" style="color:<?= ($open_alerts+$maint_overdue)>0?'var(--red)':'var(--green)' ?>"><?= $open_alerts+$maint_overdue ?></div>
        <div class="stat-sub"><?= $open_alerts ?> alerts · <?= $maint_overdue ?> maintenance overdue</div>
        <i class="fas fa-bell stat-icon" style="color:var(--red)"></i>
    </div>
</div>

<!-- CHARTS ROW 1 -->
<div class="grid-2">
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-chart-line"></i>RFID Scan Activity — Last 14 Days</div></div>
        <canvas id="chartActivity" height="200"></canvas>
    </div>
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-circle-dot"></i>Patient Status Distribution</div></div>
        <canvas id="chartPatient" height="200"></canvas>
    </div>
</div>

<!-- CHARTS ROW 2 -->
<div class="grid-2">
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-tag"></i>Assets by Category</div></div>
        <canvas id="chartCat" height="200"></canvas>
    </div>
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-circle-dot"></i>Equipment Status Breakdown</div></div>
        <canvas id="chartStatus" height="200"></canvas>
    </div>
</div>

<!-- BLOOD BANK + ACTIVE PATIENTS -->
<div class="grid-2">
    <div class="card">
        <div class="card-hdr">
            <div class="card-title"><i class="fas fa-droplet"></i>Blood Bank — Availability</div>
            <a href="blood_bank.php" class="btn btn-dark btn-sm">Manage</a>
        </div>
        <?php if (empty($blood)): ?>
        <p style="color:var(--sub);font-size:13px">No blood units registered.</p>
        <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:10px">
        <?php foreach($blood as $b): ?>
        <div style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);border-radius:12px;padding:14px 18px;min-width:90px;text-align:center">
            <div style="font-size:20px;font-weight:800;color:#fca5a5"><?= h($b['blood_group']) ?></div>
            <div style="font-size:11px;color:var(--sub);margin-top:4px"><?= (int)$b['avail'] ?> units</div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-hdr">
            <div class="card-title"><i class="fas fa-hospital-user"></i>Active Patients</div>
            <a href="patients.php" class="btn btn-dark btn-sm">All Patients</a>
        </div>
        <div class="table-wrap"><table>
            <tr><th>Patient</th><th>Status</th><th>Ward/Bed</th><th>Doctor</th></tr>
            <?php $active_patients->data_seek(0); while($p=$active_patients->fetch_assoc()): ?>
            <tr>
                <td><div class="td-name"><?= h($p['full_name']) ?></div><div class="td-sub"><?= h($p['patient_id']) ?></div></td>
                <td><?php
                    $sc=['admitted'=>'pill-blue','icu'=>'pill-yellow','critical'=>'pill-red','outpatient'=>'pill-gray'];
                    echo '<span class="pill '.($sc[$p['status']]??'pill-gray').'">'.strtoupper($p['status']).'</span>';
                ?></td>
                <td style="font-size:12px"><?= h($p['location_name']??'—') ?><br><span style="color:var(--sub);font-size:11px"><?= h($p['bed_number']??'') ?></span></td>
                <td style="font-size:12px"><?= h($p['attending_doctor']??'—') ?></td>
            </tr>
            <?php endwhile; ?>
        </table></div>
    </div>
</div>

<!-- ALERTS + APPROVALS -->
<div class="grid-2">
    <div class="card">
        <div class="card-hdr">
            <div class="card-title"><i class="fas fa-bell"></i>Active Alerts</div>
            <a href="alerts.php" class="btn btn-dark btn-sm">All Alerts</a>
        </div>
        <?php $alerts->data_seek(0); while($al=$alerts->fetch_assoc()): ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid var(--border)">
            <span class="pill <?= $al['severity']==='critical'?'pill-red':($al['severity']==='warning'?'pill-yellow':'pill-blue') ?>"><?= $al['severity'] ?></span>
            <div style="flex:1;font-size:12px"><?= h($al['message']) ?></div>
            <a href="?resolve_alert=<?= $al['id'] ?>" class="btn btn-green btn-sm"><i class="fas fa-check"></i></a>
        </div>
        <?php endwhile;
        if($alerts->num_rows==0) echo '<p style="color:var(--sub);font-size:13px;padding:12px 0"><i class="fas fa-circle-check" style="color:var(--green);margin-right:8px"></i>No active alerts — system healthy.</p>'; ?>
    </div>
    <div class="card">
        <div class="card-hdr">
            <div class="card-title"><i class="fas fa-route"></i>Recent Movements</div>
            <a href="movements.php" class="btn btn-dark btn-sm">All</a>
        </div>
        <div class="table-wrap"><table>
            <tr><th>Item</th><th>Action</th><th>Route</th><th>Time</th></tr>
            <?php while($m=$movements->fetch_assoc()): ?>
            <tr>
                <td><div class="td-name"><?= h($m['item_name']??'Unknown') ?></div><div class="td-sub"><?= h($m['uid']) ?></div></td>
                <td><?php
                    $pc=['transfer'=>'pill-blue','checkout'=>'pill-cyan','return'=>'pill-green','manual_move'=>'pill-purple','scanned'=>'pill-gray','exit'=>'pill-red','entry'=>'pill-green','staff_scan'=>'pill-pink','patient_scan'=>'pill-yellow'];
                    echo '<span class="pill '.($pc[$m['action_type']]??'pill-gray').'">'.$m['action_type'].'</span>';
                ?></td>
                <td style="font-size:11px;color:var(--sub)"><?= h($m['from_loc']??'—') ?> → <?= h($m['to_loc']??'—') ?></td>
                <td style="font-size:11px;color:var(--sub)"><?= ago($m['scan_time']) ?></td>
            </tr>
            <?php endwhile; ?>
        </table></div>
    </div>
</div>

<script>
const COLORS=['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#a78bfa'];
const baseOpts={responsive:true,plugins:{legend:{labels:{color:'#6b7fa0',font:{family:'DM Mono',size:11}}}},scales:{x:{ticks:{color:'#6b7fa0',font:{family:'DM Mono',size:10}},grid:{color:'rgba(30,45,69,0.5)'}},y:{ticks:{color:'#6b7fa0',font:{family:'DM Mono',size:10}},grid:{color:'rgba(30,45,69,0.5)'},beginAtZero:true}}};
new Chart(document.getElementById('chartActivity'),{type:'line',data:{labels:<?= json_encode($chartDays) ?>,datasets:[{label:'Total Scans',data:<?= json_encode($chartScans) ?>,borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,0.1)',fill:true,tension:0.4,pointRadius:3},{label:'Transfers',data:<?= json_encode($chartTransfers) ?>,borderColor:'#8b5cf6',backgroundColor:'rgba(139,92,246,0.08)',fill:true,tension:0.4,pointRadius:3}]},options:{...baseOpts,interaction:{mode:'index',intersect:false}}});
new Chart(document.getElementById('chartPatient'),{type:'doughnut',data:{labels:<?= json_encode($patLabels) ?>,datasets:[{data:<?= json_encode($patCounts) ?>,backgroundColor:COLORS,borderWidth:0,hoverOffset:6}]},options:{cutout:'65%',plugins:{legend:{position:'right',labels:{color:'#6b7fa0',font:{family:'DM Mono',size:11},padding:10}}}}});
new Chart(document.getElementById('chartCat'),{type:'bar',data:{labels:<?= json_encode($cats) ?>,datasets:[{label:'Count',data:<?= json_encode($catCounts) ?>,backgroundColor:COLORS,borderRadius:6}]},options:{...baseOpts,plugins:{legend:{display:false}},indexAxis:'y'}});
new Chart(document.getElementById('chartStatus'),{type:'polarArea',data:{labels:<?= json_encode($statusLabels) ?>,datasets:[{data:<?= json_encode($statusCounts) ?>,backgroundColor:COLORS.map(c=>c+'99'),borderColor:COLORS,borderWidth:1}]},options:{plugins:{legend:{position:'right',labels:{color:'#6b7fa0',font:{family:'DM Mono',size:11},padding:10}}},scales:{r:{ticks:{display:false},grid:{color:'rgba(30,45,69,0.5)'}}}}});
</script>

<?php render_footer(); ?>