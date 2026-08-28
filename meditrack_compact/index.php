<?php
require_once 'core.php';
require_login();

$p   = patient_cols();
$l   = location_cols();
$i   = item_cols();
$s   = schedule_cols();
$v   = vitals_cols();
$ivc = iv_cols();
$a   = alert_cols();
$t   = task_cols();
$sl  = scanlog_cols();
$c   = caretaker_cols();
$tok = token_cols();

$page = getv('page', 'dashboard');
$msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'saveSafety':
            $pid        = (int)posted('patientid');
            $allowed    = posted('allowedlocations');
            $restricted = posted('restrictedlocations');
            $max        = (int)posted('maxunseenminutes', 120);
            $wash       = (int)posted('washroomlimitminutes', 15);
            $bed        = (int)posted('bedexitenabled', 0);

            $exists = one("SELECT id FROM patientsafetyprofiles WHERE patientid={$pid}");
            if ($exists) {
                $st = $db->prepare("UPDATE patientsafetyprofiles
                    SET allowedlocations=?, restrictedlocations=?, maxunseenminutes=?, washroomlimitminutes=?, bedexitenabled=?
                    WHERE patientid=?");
                $st->bind_param('ssiiii', $allowed, $restricted, $max, $wash, $bed, $pid);
                $st->execute();
            } else {
                $st = $db->prepare("INSERT INTO patientsafetyprofiles(patientid,allowedlocations,restrictedlocations,maxunseenminutes,washroomlimitminutes,bedexitenabled)
                    VALUES(?,?,?,?,?,?)");
                $st->bind_param('issiii', $pid, $allowed, $restricted, $max, $wash, $bed);
                $st->execute();
            }
            $msg = 'Safety profile updated';
            break;

        case 'toggleRecall':
            $id  = (int)posted('id');
            $row = one("SELECT * FROM items WHERE id={$id} LIMIT 1");
            if ($row) {
                $new = (int)!((int)$row[$i['recallflag']]);
                q("UPDATE items SET `{$i['recallflag']}`={$new} WHERE id={$id}");
                if ($new) {
                    create_alert('critical', 'recall', 'Recall enabled for ' . $row[$i['itemname']], 'index.php?page=inventory');
                    createTask(
                        'manual-recall-' . $id,
                        'inventory',
                        'Manual recall enabled',
                        $row[$i['itemname']] . ' manually marked as recalled',
                        0,
                        $id,
                        'critical',
                        date('Y-m-d H:i:s', time() + 600)
                    );
                }
                $msg = 'Recall flag updated';
            }
            audit('toggle_recall','items',(int)posted('id'));
            break;

        case 'taskStatus':
            setTaskStatus((int)posted('id'), posted('status'));
            $msg = 'Task updated';
            audit('task_done','workflowtasks',(int)posted('id'),posted('status'));
            break;

        case 'add_patient':
            $pcode  = posted('patientcode');
            $pfull  = posted('fullname');
            $prfid  = posted('rfiduid') ?: null;
            $pgend  = posted('gender');
            $p_age  = (int)posted('age', 0);
            $pblood = posted('bloodgroup');
            $pphone = posted('phone');
            $pdiag  = posted('diagnosis');
            $pward  = (int)posted('wardid', 0);
            $pbed   = posted('bedno');
            $pstat  = posted('status', 'admitted');
            $pfall  = (int)posted('fallrisk', 0);
            $pelop  = (int)posted('elopementrisk', 0);
            $pwatch = posted('watchlevel', 'normal');
            if ($pcode && $pfull) {
                $st = $db->prepare("INSERT INTO patients(patient_code,fullname,rfiduid,gender,age,blood_group,phone,diagnosis,ward_id,bed_no,status,fall_risk,elopement_risk,watch_level) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $st->bind_param('ssssisssissiis', $pcode, $pfull, $prfid, $pgend, $p_age, $pblood, $pphone, $pdiag, $pward, $pbed, $pstat, $pfall, $pelop, $pwatch);
                $st->execute();
                $msg = 'Patient added';
                audit('add_patient','patients',(int)$db->insert_id,$pfull);
            } else {
                $msg = 'Patient code and full name are required';
            }
            break;

        case 'add_item':
            $uid      = posted('uid');
            $iname    = posted('itemname');
            $itype    = posted('itemtype', 'asset');
            $brand    = posted('brand');
            $batchno  = posted('batchno');
            $qty      = (int)posted('quantity', 1);
            $cost     = (float)posted('unitcost', 0);
            $expiry   = posted('expirydate') ?: null;
            $locid    = (int)posted('locationid');
            $status   = posted('status', 'instock');
            $recall   = (int)posted('recallflag', 0);
            $cold     = (int)posted('coldchainrequired', 0);
            if ($uid && $iname) {
                $st = $db->prepare("INSERT INTO items(uid,item_name,item_type,brand,batch_no,quantity,unit_cost,expiry_date,location_id,status,recall_flag,cold_chain_required) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
                $st->bind_param('sssssidsisii', $uid, $iname, $itype, $brand, $batchno, $qty, $cost, $expiry, $locid, $status, $recall, $cold);
                $st->execute();
                $msg = 'Item added';
                audit('add_item','items',(int)$db->insert_id,$iname);
            } else {
                $msg = 'UID and Item Name are required';
            }
            break;

        case 'add_vital':
            $vpid = (int)posted('patient_id');
            $temp = posted('temperature') ?: null;
            $sys  = posted('systolic_bp') ?: null;
            $dia  = posted('diastolic_bp') ?: null;
            $puls = posted('pulse_rate') ?: null;
            $spo2 = posted('spo2') ?: null;
            $rr   = posted('respiratory_rate') ?: null;
            $note = posted('notes');
            if ($vpid) {
                $summary = evaluate_vitals($vpid, $temp, $sys, $dia, $puls, $spo2, $rr);
                $st = $db->prepare("INSERT INTO patientvitals(patient_id,temperature,systolic_bp,diastolic_bp,pulse_rate,spo2,respiratory_rate,notes,alert_summary) VALUES(?,?,?,?,?,?,?,?,?)");
                $st->bind_param('idiiiiiss', $vpid, $temp, $sys, $dia, $puls, $spo2, $rr, $note, $summary);
                $st->execute();
                if ($summary) {
                    create_alert('warning', 'vitals', 'Vitals alert for patient ID '.$vpid.': '.$summary, 'index.php?page=vitals');
                }
                $msg = 'Vitals recorded' . ($summary ? ' — Alert: '.$summary : '');
                audit('add_vital','patientvitals',(int)$db->insert_id,'Patient '.$vpid);
            } else {
                $msg = 'Select a patient';
            }
            break;

        case 'add_med':
            $mpid  = (int)posted('patient_id');
            $miid  = (int)posted('item_id');
            $dose  = posted('dose');
            $route = posted('route_name');
            $stime = posted('scheduled_time');
            if ($mpid && $miid && $dose && $stime) {
                $st = $db->prepare("INSERT INTO medicationschedule(patient_id,item_id,dose,route_name,scheduled_time) VALUES(?,?,?,?,?)");
                $st->bind_param('iisss', $mpid, $miid, $dose, $route, $stime);
                $st->execute();
                $msg = 'Medication scheduled';
                audit('add_med','medicationschedule',(int)$db->insert_id,'Patient '.$mpid);
            } else {
                $msg = 'Patient, item, dose and time are required';
            }
            break;

        case 'mark_med':
            $id = (int)posted('id');
            if ($id) {
                $st = $db->prepare("UPDATE medicationschedule SET status='administered', compliance_status='on_time', verified_at=NOW() WHERE id=? AND status='pending'");
                $st->bind_param('i', $id);
                $st->execute();
                $msg = $st->affected_rows ? 'Medication marked as administered' : 'Already administered or not found';
                audit('mark_med','medicationschedule',$id,'Manual mark done');
            }
            break;

        case 'add_iv':
            $ipid  = (int)posted('patient_id');
            $iiid  = (int)posted('item_id', 0);
            $fname = posted('fluid_name');
            $totml = (int)posted('total_ml');
            $reml  = (int)posted('remaining_ml');
            $rate  = (float)posted('flow_rate_ml_hr');
            $start = posted('started_at');
            if ($ipid && $fname && $totml && $rate && $start) {
                $eta = calc_eta($reml, $rate, $start);
                $st = $db->prepare("INSERT INTO iv_drips(patient_id,item_id,fluid_name,total_ml,remaining_ml,flow_rate_ml_hr,started_at,eta_end,status) VALUES(?,?,?,?,?,?,?,?,'running')");
                $st->bind_param('iisiidss', $ipid, $iiid, $fname, $totml, $reml, $rate, $start, $eta);
                $st->execute();
                $msg = 'IV drip added';
                audit('add_iv','iv_drips',(int)$db->insert_id,'Patient '.$ipid);
            } else {
                $msg = 'Patient, fluid name, volume, rate and start time are required';
            }
            break;

        case 'resolve_alert':
            resolve_alert((int)posted('id'));
            $msg = 'Alert resolved';
            audit('resolve_alert','alerts',(int)posted('id'));
            break;

        case 'add_caretaker':
            $cpid   = (int)posted('patient_id');
            $cfull  = posted('fullname');
            $crel   = posted('relation_name');
            $cphone = posted('phone');
            $cemail = posted('email');
            $cemrg  = posted('emergency_contact');
            $cnotes = posted('notes');
            if ($cpid && $cfull) {
                $st = $db->prepare("INSERT INTO caretakers(patient_id,fullname,relation_name,phone,email,emergency_contact,notes) VALUES(?,?,?,?,?,?,?)");
                $st->bind_param('issssss', $cpid, $cfull, $crel, $cphone, $cemail, $cemrg, $cnotes);
                $st->execute();
                $msg = 'Caretaker added';
                audit('add_caretaker','caretakers',(int)$db->insert_id,$cfull);
            } else {
                $msg = 'Patient and caretaker name are required';
            }
            break;

        case 'generate_token':
            $tcid = (int)posted('caretaker_id');
            $tpid = (int)posted('patient_id');
            $days = max(1, (int)posted('expires_days', 7));
            if ($tcid && $tpid) {
                $token   = bin2hex(random_bytes(20));
                $expires = date('Y-m-d H:i:s', strtotime("+{$days} days"));
                $st = $db->prepare("INSERT INTO caretaker_tokens(caretaker_id,patient_id,token,expires_at,is_active) VALUES(?,?,?,?,1)");
                $st->bind_param('iiss', $tcid, $tpid, $token, $expires);
                $st->execute();
                $msg = 'Token generated: ' . $token . ' (expires ' . $expires . ')';
                audit('generate_token','caretaker_tokens',(int)$db->insert_id,'Caretaker '.$tcid);
            } else {
                $msg = 'Select caretaker and patient';
            }
            break;

        case 'add_caretaker_account':
            $patientId = (int)posted('patient_id');
            $caretakerId = (int)posted('caretaker_id');
            $uname = posted('username');
            $pass = posted('password');
    
            if ($patientId && $caretakerId && $uname && $pass) {
                if (add_caretaker_account($patientId, $caretakerId, $uname, $pass)) {
                    $msg = 'Caretaker account created successfully. They can now login at caretaker_portal.php';
                } else {
                    $msg = 'Failed to create account (username may already exist)';
                }
            }
            break;

            case 'add_maintenance':
            $mid = (int)posted('item_id');
            $ls  = posted('last_service_date') ?: null;
            $ns  = posted('next_service_date');
            $sn  = posted('service_notes');
            if ($mid && $ns) {
                $st = $db->prepare("INSERT INTO maintenance_schedule(item_id,last_service_date,next_service_date,service_notes) VALUES(?,?,?,?)");
                $st->bind_param('isss', $mid, $ls, $ns, $sn);
                $st->execute();
                $msg = 'Maintenance record added';
                audit('add_maintenance','maintenance_schedule',(int)$db->insert_id);
            }
            break;

        case 'add_discharge':
            $dpid  = (int)posted('patient_id');
            $dmeds = (int)posted('meds_cleared');
            $div   = (int)posted('iv_completed');
            $dnote = posted('notes');
            $daid  = current_admin_id();
            $st = $db->prepare("INSERT INTO discharge_checklist(patient_id,admin_id,meds_cleared,iv_completed,notes) VALUES(?,?,?,?,?)");
            $st->bind_param('iiiis', $dpid, $daid, $dmeds, $div, $dnote);
            $st->execute();
            if ($dmeds && $div) q("UPDATE patients SET status='discharged' WHERE id={$dpid}");
            $msg = 'Discharge record saved';
            audit('discharge','patients',$dpid);
            break;
        case 'resolve_selected_alerts':
            if (!empty($_POST['alert_ids']) && is_array($_POST['alert_ids'])) {
                foreach ($_POST['alert_ids'] as $id) {
                    resolve_alert((int)$id);
                }
                $msg = count($_POST['alert_ids']) . ' alerts resolved';
            }
            break;

        case 'resolve_all_alerts':
            q("UPDATE alerts SET is_resolved=1, resolved_by='" . $db->real_escape_string(admin()) . "', resolved_at=NOW() WHERE is_resolved=0");
            $msg = 'All alerts have been resolved';
            break;

        case 'add_staff':
            $empid  = posted('employee_id');
            $sfull  = posted('fullname');
            $srole  = posted('role');
            $srfid  = posted('rfiduid');
            if ($empid && $sfull && $srfid) {
                $st = $db->prepare("INSERT INTO staffmembers(employee_id,fullname,role,rfiduid,is_active) VALUES(?,?,?,?,1)");
                $st->bind_param('ssss', $empid, $sfull, $srole, $srfid);
                if ($st->execute()) {
                    $msg = 'Staff member added successfully';
                    audit('add_staff','staffmembers',(int)$db->insert_id,$sfull);
                } else {
                    $msg = 'Failed — Employee ID or RFID UID may already exist';
                }
            } else {
                $msg = 'Employee ID, name and RFID UID are required';
            }
            break;

        case 'toggle_staff':
            $sid = (int)posted('id');
            $row = one("SELECT is_active FROM staffmembers WHERE id={$sid}");
            if ($row) {
                $new = (int)!((int)$row['is_active']);
                q("UPDATE staffmembers SET is_active={$new} WHERE id={$sid}");
                $msg = $new ? 'Staff activated' : 'Staff deactivated';
                audit('toggle_staff','staffmembers',$sid);
            }
            break;

        case 'update_med_rfid':
            $iid  = (int)posted('item_id');
            $nuid = posted('new_uid');
            if ($iid && $nuid) {
                $st = $db->prepare("UPDATE items SET uid=? WHERE id=? AND item_type='medicine'");
                $st->bind_param('si', $nuid, $iid);
                if ($st->execute() && $st->affected_rows) {
                    $msg = 'Medicine RFID UID updated';
                    audit('update_med_rfid','items',$iid,$nuid);
                } else {
                    $msg = 'Update failed — UID may already be in use';
                }
            }
            break;
    }
}

$k = one(
    "SELECT
        (SELECT COUNT(*) FROM patients WHERE status<>'discharged') AS patients,
        (SELECT COUNT(*) FROM items) AS items,
        (SELECT COUNT(*) FROM alerts WHERE `{$a['isresolved']}`=0) AS alerts,
        (SELECT COUNT(*) FROM workflowtasks WHERE status<>'done') AS tasks,
        (SELECT COUNT(*) FROM medicationschedule WHERE status='pending' AND `{$s['scheduledtime']}` < NOW()) AS overdue,
        (SELECT COUNT(*) FROM items WHERE `{$i['recallflag']}`=1) AS recalled,
        (SELECT COUNT(*) FROM scanlogs WHERE DATE(`{$sl['scantime']}`)=CURDATE()) AS scans,
        (SELECT COUNT(*) FROM locations WHERE `{$l['readerid']}` IS NOT NULL AND (`{$l['lastheartbeat']}` IS NULL OR `{$l['lastheartbeat']}` < NOW() - INTERVAL 10 MINUTE)) AS offline"
);

if (isset($_GET['reset_alerts'])) {
    q("UPDATE alerts SET is_resolved=1");
    $msg = 'Alerts reset';
}

$patients = all_rows(
    "SELECT
        p.*,
        l.`{$l['locationname']}` AS location_name
     FROM patients p
     LEFT JOIN locations l ON l.id=p.`{$p['wardid']}`
     ORDER BY p.id DESC"
);

$locations = all_rows(
    "SELECT
        id,
        `{$l['locationname']}` AS location_name,
        `{$l['locationtype']}` AS location_type,
        `{$l['readerid']}` AS reader_id,
        `{$l['lastheartbeat']}` AS last_heartbeat
     FROM locations
     ORDER BY `{$l['locationname']}`"
);

$items = all_rows(
    "SELECT
        i.*,
        l.`{$l['locationname']}` AS location_name
     FROM items i
     LEFT JOIN locations l ON l.id=i.`{$i['locationid']}`
     ORDER BY i.id DESC"
);

$medItems = all_rows(
    "SELECT
        id,
        uid,
        `{$i['itemname']}` AS item_name
     FROM items
     WHERE `{$i['itemtype']}`='medicine'
     ORDER BY `{$i['itemname']}`"
);

$alerts = all_rows(
    "SELECT
        id,
        severity,
        `{$a['type']}` AS alert_type,
        message,
        `{$a['url']}` AS related_url,
        `{$a['isresolved']}` AS is_resolved,
        `{$a['createdat']}` AS created_at
     FROM alerts
     ORDER BY `{$a['isresolved']}`, id DESC
     LIMIT 60"
);

$vitals = all_rows(
    "SELECT
        v.*,
        p.fullname,
        p.`{$p['patientcode']}` AS patient_code,
        v.`{$v['recordedat']}` AS recorded_at
     FROM patientvitals v
     JOIN patients p ON p.id=v.`{$v['patientid']}`
     ORDER BY v.id DESC
     LIMIT 50"
);

$schedules = all_rows(
    "SELECT
        m.*,
        p.fullname,
        p.`{$p['patientcode']}` AS patient_code,
        i.`{$i['itemname']}` AS item_name,
        m.`{$s['scheduledtime']}` AS scheduled_time,
        m.`{$s['route']}` AS route_name,
        m.`{$s['compliancestatus']}` AS compliance_status
     FROM medicationschedule m
     JOIN patients p ON p.id=m.`{$s['patientid']}`
     JOIN items i ON i.id=m.`{$s['itemid']}`
     ORDER BY m.`{$s['scheduledtime']}` ASC
     LIMIT 60"
);

$ivs = all_rows(
    "SELECT
        iv.*,
        p.fullname,
        i.`{$i['itemname']}` AS item_name,
        iv.`{$ivc['fluidname']}` AS fluid_name,
        iv.`{$ivc['remainingml']}` AS remaining_ml,
        iv.`{$ivc['flowratemlhr']}` AS flow_rate_ml_hr,
        iv.`{$ivc['etaend']}` AS eta_end
     FROM iv_drips iv
     JOIN patients p ON p.id=iv.`{$ivc['patientid']}`
     LEFT JOIN items i ON i.id=iv.`{$ivc['itemid']}`
     ORDER BY iv.id DESC
     LIMIT 30"
);

$caretakers = all_rows(
    "SELECT
        c.*,
        p.fullname AS patient_name
     FROM caretakers c
     JOIN patients p ON p.id=c.`{$c['patientid']}`
     ORDER BY c.id DESC"
);

$tokens = all_rows(
    "SELECT
        t.*,
        c.fullname AS caretaker_name,
        p.fullname AS patient_name,
        t.`{$tok['expiresat']}` AS expires_at
     FROM caretaker_tokens t
     JOIN caretakers c ON c.id=t.`{$tok['caretakerid']}`
     JOIN patients p ON p.id=t.`{$tok['patientid']}`
     ORDER BY t.id DESC
     LIMIT 20"
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
     LIMIT 20"
);

$profiles = all_rows(
    "SELECT
        sp.*,
        p.fullname,
        p.`{$p['patientcode']}` AS patient_code,
        l.`{$l['locationname']}` AS location_name,
        p.`{$p['lastseenat']}` AS last_seen_at
     FROM patientsafetyprofiles sp
     JOIN patients p ON p.id=sp.patientid
     LEFT JOIN locations l ON l.id=p.`{$p['lastseenlocationid']}`
     ORDER BY p.fullname"
);

$tasks = all_rows(
    "SELECT
        w.*,
        p.fullname AS patient_name,
        i.`{$i['itemname']}` AS item_name,
        w.`{$t['tasktype']}` AS task_type,
        w.`{$t['createdat']}` AS created_at,
        w.`{$t['dueat']}` AS due_at
     FROM workflowtasks w
     LEFT JOIN patients p ON p.id=w.`{$t['patientid']}`
     LEFT JOIN items i ON i.id=w.`{$t['itemid']}`
     ORDER BY FIELD(w.priority,'critical','high','medium','low'), w.`{$t['createdat']}` DESC
     LIMIT 120"
);

$heat = [];
foreach ($locations as $loc) {
    $id = (int)$loc['id'];
    $heat[] = [
        'name'  => $loc['location_name'],
        'morn'  => (int)(one("SELECT COUNT(*) c FROM scanlogs WHERE `{$sl['tolocationid']}`={$id} AND HOUR(`{$sl['scantime']}`) BETWEEN 0 AND 5")['c'] ?? 0),
        'day'   => (int)(one("SELECT COUNT(*) c FROM scanlogs WHERE `{$sl['tolocationid']}`={$id} AND HOUR(`{$sl['scantime']}`) BETWEEN 6 AND 11")['c'] ?? 0),
        'eve'   => (int)(one("SELECT COUNT(*) c FROM scanlogs WHERE `{$sl['tolocationid']}`={$id} AND HOUR(`{$sl['scantime']}`) BETWEEN 12 AND 17")['c'] ?? 0),
        'night' => (int)(one("SELECT COUNT(*) c FROM scanlogs WHERE `{$sl['tolocationid']}`={$id} AND HOUR(`{$sl['scantime']}`) BETWEEN 18 AND 23")['c'] ?? 0),
    ];
}

$scanDays = [];
$scanVals = [];
for ($d = 6; $d >= 0; $d--) {
    $date = date('Y-m-d', strtotime("-{$d} day"));
    $scanDays[] = date('d M', strtotime($date));
    $scanVals[] = (int)(one("SELECT COUNT(*) c FROM scanlogs WHERE DATE(`{$sl['scantime']}`)='{$date}'")['c'] ?? 0);
}

$compRows = array_reverse(all_rows(
    "SELECT
        DATE(`{$s['scheduledtime']}`) AS d,
        SUM(`{$s['compliancestatus']}`='on_time') AS on_time,
        SUM(`{$s['compliancestatus']}`='late') AS late_count,
        SUM(status='missed') AS missed_count
     FROM medicationschedule
     GROUP BY DATE(`{$s['scheduledtime']}`)
     ORDER BY d DESC
     LIMIT 7"
));

$compDays = [];
$compOn = [];
$compLate = [];
$compMiss = [];
foreach ($compRows as $r) {
    $compDays[] = date('d M', strtotime($r['d']));
    $compOn[]   = (int)$r['on_time'];
    $compLate[] = (int)$r['late_count'];
    $compMiss[] = (int)$r['missed_count'];
}

render_header(ucfirst($page), $page);
if ($msg) {
    echo '<div class="banner">' . e($msg) . '</div>';
}

if ($page === 'dashboard') {
?>
    <div class="grid g4">
        <div class="card kpi">
            <div class="label">Active Patients</div>
            <div class="value" id="kpi-patients"><?= (int)$k['patients'] ?></div>
            <div class="sub">Currently admitted</div>
        </div>
        <div class="card kpi">
            <div class="label">Inventory Items</div>
            <div class="value" id="kpi-items"><?= (int)$k['items'] ?></div>
            <div class="sub">Tracked with RFID</div>
        </div>
        <div class="card kpi">
            <div class="label">Open Alerts</div>
            <div class="value" id="kpi-alerts"><?= (int)$k['alerts'] ?></div>
            <div class="sub">Need attention</div>
        </div>
        <div class="card kpi">
            <div class="label">Today Scans</div>
            <div class="value" id="kpi-scans"><?= (int)$k['scans'] ?></div>
            <div class="sub">RFID activity today</div>
        </div>
    </div>

    <div class="grid g2" style="margin-top:18px">
        <div class="card">
            <h3>Weekly Scan Trend</h3>
            <canvas id="scanChart" height="120"></canvas>
        </div>
        <div class="card">
            <h3>Medication Compliance</h3>
            <canvas id="medChart" height="120"></canvas>
        </div>
    </div>

    <div class="grid g2" style="margin-top:18px">
        <div class="card">
            <h3>Recent Alerts</h3>
            <table class="table" id="alerts-body">
                <tr><th>Severity</th><th>Message</th><th>Time</th></tr>
                <?php foreach (array_slice($alerts, 0, 8) as $al): ?>
                <tr>
                    <td>
                        <span class="pill <?= $al['severity'] === 'critical' ? 'danger' : ($al['severity'] === 'warning' ? 'warn' : 'info') ?>">
                            <?= e($al['severity']) ?>
                        </span>
                    </td>
                    <td><?= e($al['message']) ?></td>
                    <td><?= e(ago($al['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card" id="moves-body">
            <h3>Recent Movement</h3>
            <table class="table">
                <tr><th>Item / Patient / UID</th><th>Action</th><th>Staff</th><th>Route</th><th>Time</th></tr>
                <?php foreach (array_slice($recentMoves, 0, 8) as $m): 
                    $roleClass = match($m['staff_role'] ?? '') {
                        'Doctor'      => 'info',
                        'Pharmacist'  => 'purple',
                        'Nurse'       => 'ok',
                        'Ward Boy'    => 'warn',
                        default       => 'warn'
                    };
                ?>
                <tr>
                    <td><?= e($m['item_name'] ?: ($m['patient_name'] ?: ($m['staff_name'] ?: $m['uid']))) ?></td>
                    <td><?= e($m['action_type']) ?></td>
                    <td>
                        <?php if ($m['staff_name']): ?>
                        <span class="pill <?= $roleClass ?>">
                            <?= e($m['staff_name']) ?> · <?= e($m['staff_role']) ?>
                        </span>
                        <?php else: ?>
                        <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($m['from_name'] ?: '-') ?> → <?= e($m['to_name'] ?: '-') ?></td>
                    <td><?= e(ago($m['scan_time'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <h3>Reader / Zone Activity Heat Map</h3>
        <div class="heatmap">
            <div class="muted">Location</div>
            <div class="muted">00-05</div>
            <div class="muted">06-11</div>
            <div class="muted">12-17</div>
            <div class="muted">18-23</div>
            <?php foreach ($heat as $h): ?>
                <div><?= e($h['name']) ?></div>
                <div class="<?= $h['morn'] > 12 ? 'heat4' : ($h['morn'] > 7 ? 'heat3' : ($h['morn'] > 3 ? 'heat2' : 'heat1')) ?>"><?= (int)$h['morn'] ?></div>
                <div class="<?= $h['day'] > 12 ? 'heat4' : ($h['day'] > 7 ? 'heat3' : ($h['day'] > 3 ? 'heat2' : 'heat1')) ?>"><?= (int)$h['day'] ?></div>
                <div class="<?= $h['eve'] > 12 ? 'heat4' : ($h['eve'] > 7 ? 'heat3' : ($h['eve'] > 3 ? 'heat2' : 'heat1')) ?>"><?= (int)$h['eve'] ?></div>
                <div class="<?= $h['night'] > 12 ? 'heat4' : ($h['night'] > 7 ? 'heat3' : ($h['night'] > 3 ? 'heat2' : 'heat1')) ?>"><?= (int)$h['night'] ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    new Chart(document.getElementById('scanChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($scanDays) ?>,
            datasets: [{
                label: 'Scans',
                data: <?= json_encode($scanVals) ?>,
                borderColor: '#56a8ff',
                backgroundColor: 'rgba(86,168,255,.15)',
                tension: .35,
                fill: true
            }]
        },
        options: {
            plugins: { legend: { labels: { color: '#cbd5e1' } } },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,.05)' } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,.05)' } }
            }
        }
    });

    new Chart(document.getElementById('medChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($compDays) ?>,
            datasets: [
                { label: 'On Time', data: <?= json_encode($compOn) ?>, backgroundColor: '#34d399' },
                { label: 'Late', data: <?= json_encode($compLate) ?>, backgroundColor: '#fbbf24' },
                { label: 'Missed', data: <?= json_encode($compMiss) ?>, backgroundColor: '#fb7185' }
            ]
        },
        options: {
            plugins: { legend: { labels: { color: '#cbd5e1' } } },
            scales: {
                x: { stacked: true, ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,.05)' } },
                y: { stacked: true, ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,.05)' } }
            }
        }
    });
    </script>

    <script>
    // ── Live RFID polling ──────────────────────────────────────────
    (function () {
        const INTERVAL_MS = 5000; // poll every 5 seconds
        let lastScanId = 0;

        function severityClass(s) {
            return s === 'critical' ? 'danger' : (s === 'warning' ? 'warn' : 'info');
        }

        function ago(dateStr) {
            if (!dateStr) return '-';
            const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
            if (diff < 60)  return diff + 's ago';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            return Math.floor(diff / 3600) + 'h ago';
        }

        function esc(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function flash(el) {
            el.style.transition = 'background 0.3s';
            el.style.background = 'rgba(86,168,255,0.18)';
            setTimeout(() => { el.style.background = ''; }, 800);
        }

        function updateKPIs(kpi) {
            const map = {
                'kpi-patients': kpi.patients,
                'kpi-items':    kpi.items,
                'kpi-alerts':   kpi.alerts,
                'kpi-scans':    kpi.scans
            };
            for (const [id, val] of Object.entries(map)) {
                const el = document.getElementById(id);
                if (el && el.textContent != val) {
                    el.textContent = val;
                    flash(el);
                }
            }
        }

        function updateMoves(moves) {
            if (!moves.length) return;
            const newId = moves[0].id;
            if (newId === lastScanId) return; // nothing new
            lastScanId = newId;

            const card = document.getElementById('moves-body');
            if (!card) return;
            const table = card.querySelector('table');
            if (!table) return;

            // rebuild rows (keep header)
            const rows = moves.slice(0, 8).map(m =>
                `<tr>
                    <td>${esc(m.item_name || m.patient_name || m.staff_name || m.uid)}</td>
                    <td>${esc(m.action_type)}</td>
                    <td>${esc(m.from_name || '-')} → ${esc(m.to_name || '-')}</td>
                    <td>${ago(m.scan_time)}</td>
                </tr>`
            ).join('');
            table.innerHTML =
                '<tr><th>Item / Patient / UID</th><th>Action</th><th>Route</th><th>Time</th></tr>' + rows;
            flash(table);
        }

        function updateAlerts(alerts) {
            const table = document.getElementById('alerts-body');
            if (!table) return;

            const rows = alerts.slice(0, 8).map(al =>
                `<tr>
                    <td><span class="pill ${severityClass(al.severity)}">${esc(al.severity)}</span></td>
                    <td>${esc(al.message)}</td>
                    <td>${ago(al.created_at)}</td>
                </tr>`
            ).join('');
            
            table.innerHTML =
                '<tr><th>Severity</th><th>Message</th><th>Time</th></tr>' + rows;
        }

        async function poll() {
            try {
                const res = await fetch('live_updates.php');
                if (!res.ok) return;
                const data = await res.json();
                if (!data.ok) return;
                updateKPIs(data.kpi);
                updateMoves(data.moves);
                updateAlerts(data.alerts);
                beepIfCritical(data.alerts);
            } catch (e) {
                // silent fail — next poll will retry
            }
        }

        // Run immediately, then on interval
        poll();
        setInterval(poll, INTERVAL_MS);
    })();
    </script>

    <!-- Add to topbar right div in render_header or anywhere in dashboard -->
    <a href="api.php?action=backup" class="btn" style="font-size:12px">⬇ Backup DB</a>

<?php
} elseif ($page === 'patients') {
?>
    <div class="card">
        <h3>Add Patient</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="add_patient">
            <div class="form-grid">
                <div><label>Patient Code</label><input class="inp" name="patientcode" required></div>
                <div><label>Full Name</label><input class="inp" name="fullname" required></div>
                <div><label>RFID UID</label><input class="inp" name="rfiduid" required></div>
                <div>
                    <label>Gender</label>
                    <select name="gender"><option>Male</option><option>Female</option><option>Other</option></select>
                </div>
                <div><label>Age</label><input class="inp" name="age" type="number" min="0"></div>
                <div><label>Blood Group</label><input class="inp" name="bloodgroup"></div>
                <div><label>Phone</label><input class="inp" name="phone"></div>
                <div><label>Diagnosis</label><input class="inp" name="diagnosis"></div>
                <div>
                    <label>Ward / Location</label>
                    <select name="wardid">
                        <?php foreach ($locations as $loc): ?>
                        <option value="<?= (int)$loc['id'] ?>"><?= e($loc['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Bed No</label><input class="inp" name="bedno"></div>
                <div>
                    <label>Status</label>
                    <select name="status"><option>admitted</option><option>observation</option><option>critical</option><option>discharged</option></select>
                </div>
                <div><label>Fall Risk</label><input class="inp" name="fallrisk" type="number" min="0" max="1" value="0"></div>
                <div><label>Elopement Risk</label><input class="inp" name="elopementrisk" type="number" min="0" max="1" value="0"></div>
                <div><label>Watch Level</label><input class="inp" name="watchlevel" value="normal"></div>
            </div>
            <button class="btn primary">Save Patient</button>
        </form>
    </div>

    <div class="card" style="margin-top:18px">
        <h3>Patients</h3>
        <table class="table">
            <tr><th>Patient</th><th>RFID</th><th>Status</th><th>Ward / Bed</th><th>Diagnosis</th></tr>
            <?php foreach ($patients as $pt): ?>
            <tr>
                <td><?= e($pt['fullname']) ?><br><span class="muted"><?= e($pt[$p['patientcode']] ?? '') ?></span></td>
                <td><?= e($pt[$p['rfiduid']] ?? '') ?></td>
                <td><span class="pill info"><?= e($pt['status']) ?></span></td>
                <td><?= e($pt['location_name'] ?: '-') ?> / <?= e($pt[$p['bedno']] ?? '-') ?></td>
                <td><?= e($pt['diagnosis']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'safety') {
?>
    <div class="card">
        <h3>Update Safety Profile</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="saveSafety">
            <div class="form-grid two">
                <div>
                    <label>Patient</label>
                    <select name="patientid">
                        <?php foreach ($patients as $pt): ?>
                        <option value="<?= (int)$pt['id'] ?>"><?= e($pt['fullname']) ?> (<?= e($pt[$p['patientcode']] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Allowed Locations CSV</label><input class="inp" name="allowedlocations" placeholder="1,2,3"></div>
                <div><label>Restricted Locations CSV</label><input class="inp" name="restrictedlocations" placeholder="4,9"></div>
                <div><label>Max Unseen Minutes</label><input class="inp" type="number" name="maxunseenminutes" value="120"></div>
                <div><label>Washroom Limit Minutes</label><input class="inp" type="number" name="washroomlimitminutes" value="15"></div>
                <div><label>Bed Exit Enabled</label><select name="bedexitenabled"><option value="1">1</option><option value="0">0</option></select></div>
            </div>
            <button class="btn primary">Save Safety Profile</button>
        </form>
    </div>

    <div class="card" style="margin-top:18px">
        <h3>Safety Profiles</h3>
        <table class="table">
            <tr><th>Patient</th><th>Allowed</th><th>Restricted</th><th>Last Seen</th><th>Watch</th></tr>
            <?php foreach ($profiles as $sp): ?>
            <tr>
                <td><?= e($sp['fullname']) ?><br><span class="muted"><?= e($sp['patient_code']) ?></span></td>
                <td><?= e($sp['allowedlocations']) ?></td>
                <td><?= e($sp['restrictedlocations']) ?></td>
                <td><?= e($sp['location_name'] ?: '-') ?><br><span class="muted"><?= e(ago($sp['last_seen_at'])) ?></span></td>
                <td><?= e($sp['maxunseenminutes']) ?>m</td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'inventory') {
?>
    <div class="card">
        <h3>Add Item</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="add_item">
            <div class="form-grid">
                <div><label>UID</label><input class="inp" name="uid" required></div>
                <div><label>Item Name</label><input class="inp" name="itemname" required></div>
                <div><label>Item Type</label><input class="inp" name="itemtype" placeholder="medicine / equipment"></div>
                <div><label>Brand</label><input class="inp" name="brand"></div>
                <div><label>Batch No</label><input class="inp" name="batchno"></div>
                <div><label>Quantity</label><input class="inp" type="number" name="quantity" value="1"></div>
                <div><label>Unit Cost</label><input class="inp" type="number" step="0.01" name="unitcost" value="0"></div>
                <div><label>Expiry Date</label><input class="inp" type="date" name="expirydate"></div>
                <div>
                    <label>Location</label>
                    <select name="locationid">
                        <?php foreach ($locations as $loc): ?>
                        <option value="<?= (int)$loc['id'] ?>"><?= e($loc['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Status</label><input class="inp" name="status" value="instock"></div>
                <div><label>Recall Flag</label><select name="recallflag"><option value="0">0</option><option value="1">1</option></select></div>
                <div><label>Cold Chain Required</label><select name="coldchainrequired"><option value="0">0</option><option value="1">1</option></select></div>
            </div>
            <button class="btn primary">Save Item</button>
        </form>
    </div>

    <div class="card" style="margin-top:18px">
        <h3>Inventory</h3>
        <table class="table">
            <tr><th>Item</th><th>UID</th><th>Type</th><th>Qty</th><th>Location</th><th>Status</th><th>Action</th></tr>
            <?php foreach ($items as $it): ?>
            <tr>
                <td><?= e($it[$i['itemname']] ?? '') ?><br><span class="muted"><?= e($it['brand'] ?? '') ?></span></td>
                <td><?= e($it['uid']) ?></td>
                <td><?= e($it[$i['itemtype']] ?? '') ?></td>
                <td><?= e($it['quantity']) ?></td>
                <td><?= e($it['location_name'] ?: '-') ?></td>
                <td>
                    <span class="pill <?= ((int)($it[$i['recallflag']] ?? 0) === 1) ? 'danger' : 'ok' ?>">
                        <?= e($it['status']) ?>
                    </span>
                </td>
                <td>
                    <form method="post">
                        <input type="hidden" name="action" value="toggleRecall">
                        <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                        <button class="btn small <?= ((int)($it[$i['recallflag']] ?? 0) === 1) ? 'red' : 'primary' ?>">
                            <?= ((int)($it[$i['recallflag']] ?? 0) === 1) ? 'Clear Recall' : 'Set Recall' ?>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'medications') {
?>
    <div class="card">
        <h3>Add Medication Schedule</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="add_med">
            <div class="form-grid">
                <div>
                    <label>Patient</label>
                    <select name="patient_id">
                        <?php foreach ($patients as $pt): ?>
                        <option value="<?= (int)$pt['id'] ?>"><?= e($pt['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Medicine</label>
                    <select name="item_id">
                        <?php foreach ($medItems as $mi): ?>
                        <option value="<?= (int)$mi['id'] ?>"><?= e($mi['item_name']) ?> (<?= e($mi['uid']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Dose</label><input class="inp" name="dose" placeholder="500 mg"></div>
                <div><label>Route</label><input class="inp" name="route_name" placeholder="oral / iv"></div>
                <div><label>Scheduled Time</label><input class="inp" type="datetime-local" name="scheduled_time"></div>
            </div>
            <button class="btn primary">Add Schedule</button>
        </form>
    </div>

    <div class="card" style="margin-top:18px">
        <h3>Medication Schedule</h3>
        <table class="table">
            <tr><th>Time</th><th>Patient</th><th>Medicine</th><th>Dose / Route</th><th>Status</th><th>Action</th></tr>
            <?php foreach ($schedules as $sc): ?>
            <tr>
                <td><?= e($sc['scheduled_time']) ?></td>
                <td><?= e($sc['fullname']) ?><br><span class="muted"><?= e($sc['patient_code']) ?></span></td>
                <td><?= e($sc['item_name']) ?></td>
                <td><?= e($sc['dose']) ?> / <?= e(strtoupper($sc['route_name'])) ?></td>
                <td><?= e($sc['status']) ?><br><span class="muted"><?= e($sc['compliance_status']) ?></span></td>
                <td>
                    <?php if ($sc['status'] !== 'administered'): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="mark_med">
                        <input type="hidden" name="id" value="<?= (int)$sc['id'] ?>">
                        <button class="btn small green">Mark Done</button>
                    </form>
                    <?php else: ?>
                    <span class="pill ok">Completed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'vitals') {
?>
    <div class="card">
        <h3>Record Vitals</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="add_vital">
            <div class="form-grid">
                <div>
                    <label>Patient</label>
                    <select name="patient_id">
                        <?php foreach ($patients as $pt): ?>
                        <option value="<?= (int)$pt['id'] ?>"><?= e($pt['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Temperature</label><input class="inp" type="number" step="0.1" name="temperature"></div>
                <div><label>Systolic BP</label><input class="inp" type="number" name="systolic_bp"></div>
                <div><label>Diastolic BP</label><input class="inp" type="number" name="diastolic_bp"></div>
                <div><label>Pulse Rate</label><input class="inp" type="number" name="pulse_rate"></div>
                <div><label>SpO2</label><input class="inp" type="number" name="spo2"></div>
                <div><label>Respiratory Rate</label><input class="inp" type="number" name="respiratory_rate"></div>
                <div><label>Notes</label><input class="inp" name="notes"></div>
            </div>
            <button class="btn primary">Save Vitals</button>
        </form>
    </div>

    <div class="card" style="margin-top:18px">
        <h3>Recent Vitals</h3>
        <table class="table">
            <tr><th>Time</th><th>Patient</th><th>T</th><th>BP</th><th>Pulse</th><th>SpO2</th><th>RR</th><th>Alerts</th></tr>
            <?php foreach ($vitals as $vt): ?>
            <tr>
                <td><?= e(ago($vt['recorded_at'])) ?></td>
                <td><?= e($vt['fullname']) ?><br><span class="muted"><?= e($vt['patient_code']) ?></span></td>
                <td><?= e($vt['temperature']) ?></td>
                <td><?= e($vt['systolic_bp']) ?>/<?= e($vt['diastolic_bp']) ?></td>
                <td><?= e($vt['pulse_rate']) ?></td>
                <td><?= e($vt['spo2']) ?></td>
                <td><?= e($vt['respiratory_rate']) ?></td>
                <td><?= e($vt['alert_summary']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'iv') {
?>
    <div class="card">
        <h3>Add IV Drip</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="add_iv">
            <div class="form-grid">
                <div>
                    <label>Patient</label>
                    <select name="patient_id">
                        <?php foreach ($patients as $pt): ?>
                        <option value="<?= (int)$pt['id'] ?>"><?= e($pt['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Related Item</label>
                    <select name="item_id">
                        <option value="0">None</option>
                        <?php foreach ($items as $it): ?>
                        <option value="<?= (int)$it['id'] ?>"><?= e($it[$i['itemname']] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Fluid Name</label><input class="inp" name="fluid_name"></div>
                <div><label>Total ml</label><input class="inp" type="number" name="total_ml"></div>
                <div><label>Remaining ml</label><input class="inp" type="number" name="remaining_ml"></div>
                <div><label>Rate ml/hr</label><input class="inp" type="number" name="flow_rate_ml_hr"></div>
                <div><label>Started At</label><input class="inp" type="datetime-local" name="started_at"></div>
            </div>
            <button class="btn primary">Save IV</button>
        </form>
    </div>

    <div class="card" style="margin-top:18px">
        <h3>IV Drips</h3>
        <table class="table">
            <tr><th>Patient</th><th>Fluid</th><th>Remaining</th><th>Rate</th><th>ETA End</th><th>Status</th></tr>
            <?php foreach ($ivs as $iv): ?>
            <tr>
                <td><?= e($iv['fullname']) ?></td>
                <td><?= e($iv['fluid_name']) ?><br><span class="muted"><?= e($iv['item_name']) ?></span></td>
                <td><?= e($iv['remaining_ml']) ?> ml</td>
                <td><?= e($iv['flow_rate_ml_hr']) ?> ml/hr</td>
                <td><?= e($iv['eta_end']) ?></td>
                <td><?= e($iv['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'alerts') {
?>
<div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <h3>Open Alerts (<?= (int)$k['alerts'] ?>)</h3>
        
        <div>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="resolve_all_alerts">
                <button class="btn red" onclick="return confirm('Resolve ALL alerts? This action cannot be undone.')">
                    Resolve All Alerts
                </button>
            </form>
        </div>
    </div>

    <form method="post" id="resolveForm">
        <input type="hidden" name="action" value="resolve_selected_alerts">
        
        <table class="table">
            <tr>
                <th><input type="checkbox" onclick="toggleAll(this)"></th>
                <th>Severity</th>
                <th>Type</th>
                <th>Message</th>
                <th>Time</th>
                <th>Action</th>
            </tr>
            <?php foreach ($alerts as $al): ?>
            <tr>
                <td><input type="checkbox" name="alert_ids[]" value="<?= (int)$al['id'] ?>"></td>
                <td><span class="pill <?= $al['severity'] === 'critical' ? 'danger' : ($al['severity'] === 'warning' ? 'warn' : 'info') ?>"><?= e($al['severity']) ?></span></td>
                <td><?= e($al['alert_type']) ?></td>
                <td><?= e($al['message']) ?></td>
                <td><?= e(ago($al['created_at'])) ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="resolve_alert">
                        <input type="hidden" name="id" value="<?= (int)$al['id'] ?>">
                        <button class="btn small green">Resolve</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php if (count($alerts) > 0): ?>
        <div style="margin-top:16px">
            <button type="submit" class="btn green" onclick="return confirm('Resolve selected alerts?')">
                Resolve Selected Alerts
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
function toggleAll(source) {
    document.querySelectorAll('input[name="alert_ids[]"]').forEach(cb => cb.checked = source.checked);
}
</script>
<?php

} elseif ($page === 'portal') {
?>
    <div class="card">
        <h3>Add Caretaker</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="add_caretaker">
            <div class="form-grid">
                <div><label>Patient</label><select name="patient_id"><?php foreach ($patients as $pt): ?><option value="<?= (int)$pt['id'] ?>"><?= e($pt['fullname']) ?></option><?php endforeach; ?></select></div>
                <div><label>Full Name</label><input class="inp" name="fullname" required></div>
                <div><label>Relation</label><input class="inp" name="relation_name" placeholder="e.g. Wife, Son"></div>
                <div><label>Phone</label><input class="inp" name="phone"></div>
                <div><label>Email</label><input class="inp" name="email"></div>
                <div><label>Emergency Contact</label><input class="inp" name="emergency_contact"></div>
            </div>
            <button class="btn primary">Add Caretaker</button>
        </form>
    </div>

    <div class="card" style="margin-top:18px">
        <h3>Generate Portal Token</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="generate_token">
            <div class="form-grid">
                <div><label>Caretaker</label><select name="caretaker_id"><?php foreach ($caretakers as $ct): ?><option value="<?= (int)$ct['id'] ?>"><?= e($ct['fullname']) ?> (<?= e($ct['patient_name']) ?>)</option><?php endforeach; ?></select></div>
                <div><label>Patient</label><select name="patient_id"><?php foreach ($patients as $pt): ?><option value="<?= (int)$pt['id'] ?>"><?= e($pt['fullname']) ?></option><?php endforeach; ?></select></div>
                <div><label>Expires In (days)</label><input class="inp" type="number" name="expires_days" value="7" min="1" max="90"></div>
            </div>
            <button class="btn primary">Generate Token</button>
        </form>
        <?php if (!empty($tokens)): ?>
        <table class="table" style="margin-top:14px">
            <tr><th>Caretaker</th><th>Patient</th><th>Token</th><th>Expires</th><th>Link</th></tr>
            <?php foreach ($tokens as $tk): ?>
            <tr>
                <td><?= e($tk['caretaker_name']) ?></td>
                <td><?= e($tk['patient_name']) ?></td>
                <td><code style="font-size:11px"><?= e(substr($tk['token'],0,16)) ?>...</code></td>
                <td><?= e($tk['expires_at']) ?></td>
                <td><a href="caretaker_portal.php?token=<?= urlencode($tk['token']) ?>" target="_blank" class="btn small primary">Open</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h3>Create Caretaker Login Account</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="add_caretaker_account">
            <div class="form-grid">
                <div>
                    <label>Patient</label>
                    <select name="patient_id" id="ctPat" onchange="filterCT(this.value)">
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $pt): ?>
                        <option value="<?= (int)$pt['id'] ?>"><?= e($pt['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Caretaker</label>
                    <select name="caretaker_id" id="ctSel">
                        <option value="">-- Select Caretaker --</option>
                        <?php foreach ($caretakers as $ct): ?>
                        <option value="<?= (int)$ct['id'] ?>" data-p="<?= (int)$ct[$c['patientid']] ?>"><?= e($ct['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Username</label><input class="inp" name="username" placeholder="e.g. suman_sharma" required></div>
                <div><label>Password</label><input class="inp" type="password" name="password" placeholder="Min 6 chars" required></div>
            </div>
            <div style="margin-top:10px;display:flex;align-items:center;gap:14px">
                <button class="btn primary">Create Account</button>
                <span class="muted" style="font-size:12px">Login URL: <strong>caretaker_login.php</strong></span>
            </div>
        </form>
    </div>
    <script>
    function filterCT(pid) {
        document.querySelectorAll('#ctSel option').forEach(o => {
            o.style.display = (!pid || !o.value || o.dataset.p === pid) ? '' : 'none';
        });
        document.getElementById('ctSel').value = '';
    }
    </script>

    <div class="card" style="margin-top:18px">
        <h3>Caretakers &amp; Accounts</h3>
        <table class="table">
            <tr><th>Caretaker</th><th>Patient</th><th>Relation</th><th>Phone</th><th>Username</th><th>Status</th></tr>
            <?php foreach ($caretakers as $ct):
                $acc = one("SELECT username, last_login FROM caretaker_accounts WHERE caretaker_id=".(int)$ct['id']." LIMIT 1");
            ?>
            <tr>
                <td><?= e($ct['fullname']) ?></td>
                <td><?= e($ct['patient_name']) ?></td>
                <td><?= e($ct['relation_name'] ?? '-') ?></td>
                <td><?= e($ct['phone']) ?></td>
                <td><?= $acc ? e($acc['username']) : '<span class="muted">—</span>' ?></td>
                <td>
                    <?php if ($acc): ?>
                        <span class="pill ok">Active</span>
                        <?php if ($acc['last_login']): ?>
                        <span class="muted" style="font-size:11px"> Last: <?= e(ago($acc['last_login'])) ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="pill warn">No Account</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'workflow') {
?>
    <div class="card">
        <h3>Workflow Tasks</h3>
        <table class="table">
            <tr><th>Time</th><th>Type</th><th>Title</th><th>Patient / Item</th><th>Status</th><th>Priority</th><th>Action</th></tr>
            <?php foreach ($tasks as $tk): ?>
            <tr>
                <td><?= e($tk['created_at']) ?></td>
                <td><?= e($tk['task_type']) ?></td>
                <td><?= e($tk['title']) ?><br><span class="muted"><?= e($tk['description']) ?></span></td>
                <td><?= e($tk['patient_name']) ?><br><span class="muted"><?= e($tk['item_name']) ?></span></td>
                <td><?= e($tk['status']) ?></td>
                <td>
                    <span class="pill <?= $tk['priority'] === 'critical' ? 'danger' : ($tk['priority'] === 'high' ? 'warn' : 'info') ?>">
                        <?= e($tk['priority']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($tk['status'] !== 'done'): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="taskStatus">
                        <input type="hidden" name="id" value="<?= (int)$tk['id'] ?>">
                        <input type="hidden" name="status" value="done">
                        <button class="btn small green">Mark Done</button>
                    </form>
                    <?php else: ?>
                    <span class="pill ok">Completed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'readers') {
    $readersAll = all_rows("SELECT *, `{$l['locationname']}` AS lname, `{$l['lastheartbeat']}` AS lhb, `{$l['readerid']}` AS rid, `{$l['locationtype']}` AS ltype FROM locations ORDER BY `{$l['locationname']}`");
?>
    <div class="card">
        <h3>Reader Health (<?= (int)$k['offline'] ?> offline)</h3>
        <table class="table">
            <tr><th>Location</th><th>Reader ID</th><th>Type</th><th>Last Heartbeat</th><th>Status</th></tr>
            <?php foreach ($readersAll as $r):
                $online = $r['rid'] && $r['lhb'] && strtotime($r['lhb']) > time() - 600;
            ?>
            <tr>
                <td><?= e($r['lname']) ?></td>
                <td><?= e($r['rid'] ?: '—') ?></td>
                <td><?= e($r['ltype']) ?></td>
                <td><?= e($r['lhb'] ? ago($r['lhb']) : 'Never') ?></td>
                <td><span class="pill <?= !$r['rid'] ? 'info' : ($online ? 'ok' : 'danger') ?>">
                    <?= !$r['rid'] ? 'No Reader' : ($online ? 'Online' : 'Offline') ?>
                </span></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'maintenance') {
    $maint = all_rows("SELECT m.*, i.`{$i['itemname']}` AS item_name FROM maintenance_schedule m LEFT JOIN items i ON i.id=m.item_id ORDER BY m.next_service_date ASC");
?>
    <div class="card">
        <h3>Add Maintenance Entry</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="add_maintenance">
            <div class="form-grid">
                <div><label>Item</label><select name="item_id"><?php foreach ($items as $it): ?><option value="<?= (int)$it['id'] ?>"><?= e($it[$i['itemname']] ?? '') ?></option><?php endforeach; ?></select></div>
                <div><label>Last Service Date</label><input class="inp" type="date" name="last_service_date"></div>
                <div><label>Next Service Date</label><input class="inp" type="date" name="next_service_date" required></div>
                <div><label>Notes</label><input class="inp" name="service_notes"></div>
            </div>
            <button class="btn primary">Save</button>
        </form>
    </div>
    <div class="card" style="margin-top:18px">
        <h3>Maintenance Schedule</h3>
        <table class="table">
            <tr><th>Item</th><th>Last Service</th><th>Next Service</th><th>Status</th><th>Notes</th></tr>
            <?php foreach ($maint as $m):
                $overdue = strtotime($m['next_service_date']) < time() && $m['status'] !== 'done';
            ?>
            <tr>
                <td><?= e($m['item_name']) ?></td>
                <td><?= e($m['last_service_date'] ?: '—') ?></td>
                <td><?= e($m['next_service_date']) ?></td>
                <td><span class="pill <?= $m['status'] === 'done' ? 'ok' : ($overdue ? 'danger' : 'warn') ?>"><?= e($m['status']) ?></span></td>
                <td><?= e($m['service_notes'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'discharge') {
    $discharges = all_rows("SELECT d.*, p.fullname AS patient_name, a.username AS admin_name FROM discharge_checklist d LEFT JOIN patients p ON p.id=d.patient_id LEFT JOIN admins a ON a.id=d.admin_id ORDER BY d.id DESC LIMIT 50");
?>
    <div class="card">
        <h3>Discharge Checklist</h3>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="add_discharge">
            <div class="form-grid">
                <div><label>Patient</label><select name="patient_id"><?php foreach ($patients as $pt): ?><option value="<?= (int)$pt['id'] ?>"><?= e($pt['fullname']) ?></option><?php endforeach; ?></select></div>
                <div><label>Meds Cleared</label><select name="meds_cleared"><option value="0">No</option><option value="1">Yes</option></select></div>
                <div><label>IV Completed</label><select name="iv_completed"><option value="0">No</option><option value="1">Yes</option></select></div>
                <div><label>Notes</label><input class="inp" name="notes"></div>
            </div>
            <button class="btn primary">Submit Discharge</button>
        </form>
    </div>
    <div class="card" style="margin-top:18px">
        <h3>Discharge Records</h3>
        <table class="table">
            <tr><th>Patient</th><th>Meds</th><th>IV</th><th>Notes</th><th>By</th><th>Time</th></tr>
            <?php foreach ($discharges as $d): ?>
            <tr>
                <td><?= e($d['patient_name']) ?></td>
                <td><span class="pill <?= $d['meds_cleared'] ? 'ok' : 'warn' ?>"><?= $d['meds_cleared'] ? 'Yes' : 'No' ?></span></td>
                <td><span class="pill <?= $d['iv_completed'] ? 'ok' : 'warn' ?>"><?= $d['iv_completed'] ? 'Yes' : 'No' ?></span></td>
                <td><?= e($d['notes'] ?: '—') ?></td>
                <td><?= e($d['admin_name'] ?? 'system') ?></td>
                <td><?= e(ago($d['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'audit') {
    $auditRows = all_rows("SELECT al.*, a.username FROM audit_logs al LEFT JOIN admins a ON a.id=al.admin_id ORDER BY al.created_at DESC LIMIT 200");
    $afrom = date('Y-m-d', strtotime('-30 days'));
    $ato   = date('Y-m-d');
?>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
            <h3>Audit Log (last 200 entries)</h3>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input class="inp" type="date" id="afrom" value="<?= e($afrom) ?>" style="width:140px">
                <input class="inp" type="date" id="ato"   value="<?= e($ato) ?>"   style="width:140px">
                <a id="csvBtn" href="api.php?action=audit_export&format=csv&from=<?= e($afrom) ?>&to=<?= e($ato) ?>" class="btn green">⬇ CSV</a>
                <a id="pdfBtn" href="api.php?action=audit_export&format=pdf&from=<?= e($afrom) ?>&to=<?= e($ato) ?>" class="btn primary" target="_blank">🖨 PDF</a>
            </div>
        </div>
        <table class="table">
            <tr><th>Time</th><th>Admin</th><th>Action</th><th>Table</th><th>Record</th><th>Detail</th><th>IP</th></tr>
            <?php foreach ($auditRows as $r): ?>
            <tr>
                <td><?= e(ago($r['created_at'])) ?></td>
                <td><?= e($r['username'] ?? 'system') ?></td>
                <td><?= e($r['action']) ?></td>
                <td><?= e($r['target_table']) ?></td>
                <td><?= e($r['target_id']) ?></td>
                <td><?= e($r['detail']) ?></td>
                <td><?= e($r['ip']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <script>
    function updAuditLinks() {
        const f = document.getElementById('afrom').value, t = document.getElementById('ato').value;
        document.getElementById('csvBtn').href = `api.php?action=audit_export&format=csv&from=${f}&to=${t}`;
        document.getElementById('pdfBtn').href = `api.php?action=audit_export&format=pdf&from=${f}&to=${t}`;
    }
    document.getElementById('afrom').addEventListener('change', updAuditLinks);
    document.getElementById('ato').addEventListener('change', updAuditLinks);
    </script>
<?php
} elseif ($page === 'reports') {
?>
    <div class="card">
        <h3>Reports</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
            <a href="report.php?type=summary" class="btn primary" target="_blank">📊 Hospital Summary</a>
            <a href="api.php?action=audit_export&format=csv" class="btn green">⬇ Audit CSV (30d)</a>
            <a href="api.php?action=audit_export&format=pdf" class="btn" target="_blank">🖨 Audit PDF</a>
        </div>
        <h3>Patient Reports</h3>
        <table class="table">
            <tr><th>Patient</th><th>Code</th><th>Status</th><th>Actions</th></tr>
            <?php foreach ($patients as $pt): ?>
            <tr>
                <td><?= e($pt['fullname']) ?></td>
                <td><?= e($pt[$p['patientcode']] ?? '') ?></td>
                <td><?= e($pt['status']) ?></td>
                <td>
                    <a href="report.php?type=patient&patient_id=<?= (int)$pt['id'] ?>" target="_blank" class="btn small primary">View</a>
                    <a href="api.php?action=report_export&type=patient&patient_id=<?= (int)$pt['id'] ?>" target="_blank" class="btn small">Print</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
} elseif ($page === 'replay') {
?>
    <div class="card">
        <h3>Movement Replay</h3>
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
            <label class="muted" style="font-size:12px">Patient:</label>
            <select class="inp" id="replayPid" style="width:200px">
                <option value="0">All</option>
                <?php foreach ($patients as $pt): ?>
                <option value="<?= (int)$pt['id'] ?>"><?= e($pt['fullname']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn primary" onclick="loadReplay()">Load</button>
        </div>
        <div id="replayOut"><div class="muted">Select a patient and click Load.</div></div>
    </div>
    <script>
    async function loadReplay() {
        const pid = document.getElementById('replayPid').value;
        document.getElementById('replayOut').innerHTML = '<div class="muted">Loading…</div>';
        try {
            const d = await (await fetch(`api.php?action=movementreplay&patientid=${pid}`)).json();
            const esc = s => String(s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
            if (!d.items?.length) { document.getElementById('replayOut').innerHTML='<div class="muted">No records found.</div>'; return; }
            document.getElementById('replayOut').innerHTML =
                `<table class="table"><tr><th>Time</th><th>Patient</th><th>Item</th><th>Action</th><th>From</th><th>To</th></tr>
                ${d.items.map(x=>`<tr><td>${esc(x.scan_time)}</td><td>${esc(x.patient_name)}</td><td>${esc(x.item_name)}</td><td>${esc(x.action_type)}</td><td>${esc(x.from_name)}</td><td>${esc(x.to_name)}</td></tr>`).join('')}
                </table>`;
        } catch(e) { document.getElementById('replayOut').innerHTML='<div class="muted">Failed to load.</div>'; }
    }
    </script>
<?php

} elseif ($page === 'staff') {
    $staffAll = all_rows("SELECT * FROM staffmembers ORDER BY id DESC");
    $medItems = all_rows("SELECT id, uid, item_name FROM items WHERE item_type='medicine' ORDER BY item_name");
    $visitLog = all_rows(
        "SELECT s.scan_time, s.action_type, s.notes,
                sm.fullname AS nurse_name, sm.role AS nurse_role, sm.rfiduid,
                p.fullname AS patient_name,
                l.location_name
         FROM scanlogs s
         JOIN staffmembers sm ON sm.id = s.staff_id
         LEFT JOIN patients p ON p.id = s.patient_id
         LEFT JOIN locations l ON l.id = s.to_location_id
         WHERE s.staff_id IS NOT NULL
         ORDER BY s.id DESC LIMIT 60"
    );
?>
<div class="grid g2">

    <!-- LEFT: Staff / Nurse Management -->
    <div>
        <div class="card">
            <h3><i class="fas fa-user-nurse"></i> Add Nurse / Staff</h3>
            <form method="post" class="grid">
                <input type="hidden" name="action" value="add_staff">
                <div class="form-grid two">
                    <div><label>Employee ID</label><input class="inp" name="employee_id" placeholder="EMP003" required></div>
                    <div><label>Full Name</label><input class="inp" name="fullname" required></div>
                    <div>
                        <label>Role</label>
                        <select name="role">
                            <option>Nurse</option>
                            <option>Pharmacist</option>
                            <option>Doctor</option>
                            <option>Ward Boy</option>
                            <option>Technician</option>
                        </select>
                    </div>
                    <div><label>RFID UID (scan tag)</label><input class="inp" name="rfiduid" placeholder="e.g. STAFF003" required></div>
                </div>
                <button class="btn primary"><i class="fas fa-plus"></i> Add Staff</button>
            </form>
        </div>

        <div class="card" style="margin-top:18px">
            <h3>Staff Members</h3>
            <table class="table">
                <tr><th>Name</th><th>Role</th><th>RFID UID</th><th>Emp ID</th><th>Status</th><th>Action</th></tr>
                <?php foreach ($staffAll as $sf): ?>
                <tr>
                    <td><?= e($sf['fullname']) ?></td>
                    <td><?= e($sf['role']) ?></td>
                    <td><code style="font-size:11px;color:var(--cyan)"><?= e($sf['rfiduid']) ?></code></td>
                    <td><?= e($sf['employee_id']) ?></td>
                    <td><span class="pill <?= $sf['is_active'] ? 'ok' : 'danger' ?>"><?= $sf['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="toggle_staff">
                            <input type="hidden" name="id" value="<?= (int)$sf['id'] ?>">
                            <button class="btn small <?= $sf['is_active'] ? 'red' : 'green' ?>">
                                <?= $sf['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- RIGHT: Medication RFID Management -->
    <div>
        <div class="card">
            <h3><i class="fas fa-pills"></i> Update Medicine RFID UID</h3>
            <form method="post" class="grid">
                <input type="hidden" name="action" value="update_med_rfid">
                <div class="form-grid two">
                    <div>
                        <label>Medicine</label>
                        <select name="item_id">
                            <?php foreach ($medItems as $mi): ?>
                            <option value="<?= (int)$mi['id'] ?>">
                                <?= e($mi['item_name']) ?> (current: <?= e($mi['uid']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>New RFID UID</label><input class="inp" name="new_uid" placeholder="e.g. MED003" required></div>
                </div>
                <button class="btn primary"><i class="fas fa-tag"></i> Update UID</button>
            </form>
        </div>

        <div class="card" style="margin-top:18px">
            <h3>Current Medicine RFID Tags</h3>
            <table class="table">
                <tr><th>Medicine</th><th>RFID UID</th></tr>
                <?php foreach ($medItems as $mi): ?>
                <tr>
                    <td><?= e($mi['item_name']) ?></td>
                    <td><code style="font-size:11px;color:var(--cyan)"><?= e($mi['uid']) ?></code></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card" style="margin-top:18px">
            <h3>Recent Staff Visit Log</h3>
            <table class="table">
                <tr><th>Time</th><th>Nurse</th><th>Role</th><th>Patient</th><th>Location</th><th>Action</th></tr>
                <?php foreach ($visitLog as $vl): ?>
                <tr>
                    <td><?= e(ago($vl['scan_time'])) ?></td>
                    <td><?= e($vl['nurse_name']) ?></td>
                    <td><?= e($vl['nurse_role']) ?></td>
                    <td><?= e($vl['patient_name'] ?: '—') ?></td>
                    <td><?= e($vl['location_name'] ?: '—') ?></td>
                    <td><span class="pill <?= $vl['action_type']==='medverify'?'ok':'info' ?>"><?= e($vl['action_type']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

</div>
<?php
} else {
    echo '<div class="card"><h3>Page not found</h3><p class="muted">Use the sidebar to navigate.</p></div>';
}

render_footer();