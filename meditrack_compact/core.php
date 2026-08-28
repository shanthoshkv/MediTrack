<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

$db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT ?? 3306);
if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}
$db->set_charset('utf8mb4');

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function q($sql) { global $db; return $db->query($sql); }
function one($sql) {
    global $db;
    $r = $db->query($sql);
    if ($db->error) { _log_err('one', $db->error, $sql); return null; }
    return $r ? $r->fetch_assoc() : null;
}
function all_rows($sql) {
    global $db;
    $r = $db->query($sql);
    if ($db->error) { _log_err('all_rows', $db->error, $sql); return []; }
    $rows = [];
    if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; }
    return $rows;
}

function _log_err($fn, $err, $sql) {
    global $db;
    $e = $db->real_escape_string(substr("[$fn] $err | " . substr($sql,0,120), 0, 255));
    @$db->query("INSERT INTO systemlogs(log_level,source_name,message_text) VALUES('error','core','$e')");
}

function audit($action, $table = '', $targetId = 0, $detail = '') {
    global $db;
    $aid = current_admin_id();
    $ip  = $db->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '');
    $a   = $db->real_escape_string(substr($action, 0, 80));
    $t   = $db->real_escape_string($table);
    $d   = $db->real_escape_string(substr($detail, 0, 500));
    $db->query("INSERT INTO audit_logs(admin_id,action,target_table,target_id,detail,ip)
                VALUES({$aid},'{$a}','{$t}'," . (int)$targetId . ",'{$d}','{$ip}')");
}

function posted($k, $d = '') { return trim((string)($_POST[$k] ?? $d)); }
function getv($k, $d = '') { return trim((string)($_GET[$k] ?? $d)); }

function current_admin_id() {
    return (int)($_SESSION['admin_id'] ?? $_SESSION['adminid'] ?? 0);
}
function is_login() { return current_admin_id() > 0; }
function require_login() {
    if (!is_login()) {
        header('Location: login.php');
        exit;
    }
}
function admin() {
    return $_SESSION['admin_name'] ?? $_SESSION['fullname'] ?? 'Admin';
}
function ago($t) {
    if (!$t) return 'Never';
    $ts = strtotime($t);
    if (!$ts) return (string)$t;
    $d = time() - $ts;
    if ($d < 60) return $d . 's ago';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    return date('d M Y H:i', $ts);
}

$GLOBALS['_schema_cache'] = [];

function table_columns($table) {
    global $db;
    if (isset($GLOBALS['_schema_cache'][$table])) {
        return $GLOBALS['_schema_cache'][$table];
    }
    $cols = [];
    $res = $db->query("SHOW COLUMNS FROM `$table`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    $GLOBALS['_schema_cache'][$table] = $cols;
    return $cols;
}

function col($table, ...$names) {
    $cols = table_columns($table);
    foreach ($names as $name) {
        if (in_array($name, $cols, true)) return $name;
    }
    return $names[0];
}

function cols($table, $map) {
    $out = [];
    foreach ($map as $key => $candidates) {
        $out[$key] = col($table, ...$candidates);
    }
    return $out;
}

function patient_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('patients', [
        'patientcode'        => ['patient_code', 'patientcode'],
        'bloodgroup'         => ['blood_group', 'bloodgroup'],
        'wardid'             => ['ward_id', 'wardid'],
        'bedno'              => ['bed_no', 'bedno'],
        'lastseenat'         => ['last_seen_at', 'lastseenat'],
        'lastseenlocationid' => ['last_seen_location_id', 'lastseenlocationid'],
        'rfiduid'            => ['rfiduid', 'rfid_uid'],
        'fallrisk'           => ['fall_risk', 'fallrisk'],
        'elopementrisk'      => ['elopement_risk', 'elopementrisk'],
        'watchlevel'         => ['watch_level', 'watchlevel'],
    ]);
    return $c;
}

function location_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('locations', [
        'locationname' => ['location_name', 'locationname'],
        'locationtype' => ['location_type', 'locationtype'],
        'readerid'     => ['readerid', 'reader_id'],
        'apikey'       => ['apikey', 'api_key'],
        'isactive'     => ['isactive', 'is_active'],
        'lastheartbeat'=> ['lastheartbeat', 'last_heartbeat'],
    ]);
    return $c;
}

function item_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('items', [
        'itemname'          => ['item_name', 'itemname'],
        'itemtype'          => ['item_type', 'itemtype'],
        'batchno'           => ['batch_no', 'batchno'],
        'unitcost'          => ['unit_cost', 'unitcost'],
        'expirydate'        => ['expiry_date', 'expirydate'],
        'locationid'        => ['location_id', 'locationid'],
        'recallflag'        => ['recall_flag', 'recallflag'],
        'coldchainrequired' => ['cold_chain_required', 'coldchainrequired'],
        'lastseenat'        => ['last_seen_at', 'lastseenat'],
    ]);
    return $c;
}

function schedule_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('medicationschedule', [
        'patientid'          => ['patient_id', 'patientid'],
        'itemid'             => ['item_id', 'itemid'],
        'scheduledtime'      => ['scheduled_time', 'scheduledtime'],
        'route'              => ['route_name', 'routename', 'route'],
        'compliancestatus'   => ['compliance_status', 'compliancestatus'],
        'verifiedat'         => ['verified_at', 'verifiedat'],
        'verifiedstaffuid'   => ['verified_staff_uid', 'verifiedstaffuid'],
        'verifiedpatientuid' => ['verified_patient_uid', 'verifiedpatientuid'],
        'verifiedmedicineuid'=> ['verified_medicine_uid', 'verifiedmedicineuid'],
    ]);
    return $c;
}

function vitals_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('patientvitals', [
        'patientid' => ['patient_id', 'patientid'],
        'recordedat'=> ['recorded_at', 'recordedat', 'created_at', 'createdat'],
    ]);
    return $c;
}

function iv_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('iv_drips', [
        'patientid'    => ['patient_id', 'patientid'],
        'itemid'       => ['item_id', 'itemid'],
        'fluidname'    => ['fluid_name', 'fluidname'],
        'totalml'      => ['total_ml', 'totalml'],
        'remainingml'  => ['remaining_ml', 'remainingml'],
        'flowratemlhr' => ['flow_rate_ml_hr', 'flowratemlhr'],
        'startedat'    => ['started_at', 'startedat'],
        'etaend'       => ['eta_end', 'etaend'],
        'createdat'    => ['created_at', 'createdat'],
    ]);
    return $c;
}

function alert_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('alerts', [
        'type'       => ['alert_type', 'alerttype'],
        'url'        => ['related_url', 'relatedurl'],
        'isresolved' => ['is_resolved', 'isresolved'],
        'resolvedby' => ['resolved_by', 'resolvedby'],
        'resolvedat' => ['resolved_at', 'resolvedat'],
        'createdat'  => ['created_at', 'createdat'],
    ]);
    return $c;
}

function task_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('workflowtasks', [
        'taskkey'     => ['task_key', 'taskkey'],
        'tasktype'    => ['task_type', 'tasktype'],
        'patientid'   => ['patient_id', 'patientid'],
        'itemid'      => ['item_id', 'itemid'],
        'dueat'       => ['due_at', 'dueat'],
        'createdat'   => ['created_at', 'createdat'],
        'completedat' => ['completed_at', 'completedat'],
    ]);
    return $c;
}

function scanlog_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('scanlogs', [
        'itemid'         => ['item_id', 'itemid'],
        'patientid'      => ['patient_id', 'patientid'],
        'staffid'        => ['staff_id', 'staffid'],
        'fromlocationid' => ['from_location_id', 'fromlocationid'],
        'tolocationid'   => ['to_location_id', 'tolocationid'],
        'readerid'       => ['readerid', 'reader_id'],
        'actiontype'     => ['action_type', 'actiontype'],
        'scantime'       => ['scan_time', 'scantime'],
    ]);
    return $c;
}

function caretaker_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('caretakers', [
        'patientid'    => ['patient_id', 'patientid'],
        'relationname' => ['relation_name', 'relationname'],
    ]);
    return $c;
}

function token_cols() {
    static $c = null;
    if ($c) return $c;
    $c = cols('caretaker_tokens', [
        'caretakerid' => ['caretaker_id', 'caretakerid'],
        'patientid'   => ['patient_id', 'patientid'],
        'expiresat'   => ['expires_at', 'expiresat'],
        'isactive'    => ['is_active', 'isactive'],
    ]);
    return $c;
}

function notification_cols() {
    static $c = null;
    if ($c) return $c;
    // table is notification_reads
    $c = cols('notification_reads', [
        'alertid' => ['alert_id', 'alertid'],
        'adminid' => ['admin_id', 'adminid'],
        'readat'  => ['read_at', 'readat'],
    ]);
    return $c;
}

function create_alert($severity, $type, $message, $url = '') {
    global $db;
    $a = alert_cols();
    $esc_type = $db->real_escape_string($type);
    $esc_msg  = $db->real_escape_string($message);
    
    // Stronger deduplication - prevent alert spam
    $exists = one("SELECT id FROM alerts WHERE `{$a['type']}`='{$esc_type}' 
                   AND message='{$esc_msg}' AND `{$a['isresolved']}`=0 
                   AND created_at > NOW() - INTERVAL 30 MINUTE LIMIT 1");
    if ($exists) return;
    
    $sql = "INSERT INTO alerts(severity, `{$a['type']}`, message, `{$a['url']}`) VALUES(?,?,?,?)";
    $st = $db->prepare($sql);
    if ($st) {
        $st->bind_param('ssss', $severity, $type, $message, $url);
        $st->execute();
    }
}

function resolve_alert($id) {
    global $db;
    $a = alert_cols();
    $name = admin();
    $sql = "UPDATE alerts SET `{$a['isresolved']}`=1, `{$a['resolvedby']}`=?, `{$a['resolvedat']}`=NOW() WHERE id=?";
    $st = $db->prepare($sql);
    if ($st) {
        $st->bind_param('si', $name, $id);
        $st->execute();
    }
}

function createTask($key, $type, $title, $description = '', $patientId = 0, $itemId = 0, $priority = 'medium', $dueAt = null) {
    global $db;
    $t = task_cols();
    $sql = "INSERT INTO workflowtasks(`{$t['taskkey']}`, `{$t['tasktype']}`, title, description, `{$t['patientid']}`, `{$t['itemid']}`, priority, status, `{$t['dueat']}`)
            VALUES(?,?,?,?,?,?,?,'open',?)
            ON DUPLICATE KEY UPDATE
              title=VALUES(title),
              description=VALUES(description),
              priority=VALUES(priority),
              `{$t['dueat']}`=VALUES(`{$t['dueat']}`),
              status=IF(status='done','done','open')";
    $st = $db->prepare($sql);
    if ($st) {
        $st->bind_param('ssssiiss', $key, $type, $title, $description, $patientId, $itemId, $priority, $dueAt);
        $st->execute();
    }
}

function setTaskStatus($id, $status) {
    global $db;
    $t = task_cols();
    $done = $status === 'done' ? date('Y-m-d H:i:s') : null;
    $sql = "UPDATE workflowtasks SET status=?, `{$t['completedat']}`=? WHERE id=?";
    $st = $db->prepare($sql);
    if ($st) {
        $st->bind_param('ssi', $status, $done, $id);
        $st->execute();
    }
}

function csvHasId($csv, $id) {
    $arr = array_filter(array_map('trim', explode(',', (string)$csv)));
    return in_array((string)$id, $arr, true);
}

function updatePatientSeen($patientId, $locationId) {
    global $db;
    $p = patient_cols();
    $sql = "UPDATE patients SET `{$p['lastseenat']}`=NOW(), `{$p['lastseenlocationid']}`=? WHERE id=?";
    $st = $db->prepare($sql);
    if ($st) {
        $st->bind_param('ii', $locationId, $patientId);
        $st->execute();
    }
}

// Vitals evaluation — no alerts created, just returns summary string
function evaluate_vitals($patientId, $t, $sys, $dia, $pulse, $spo2, $rr) {
    $msgs = [];
    if ($spo2 !== '' && (int)$spo2 < 90) $msgs[] = 'SpO2 critically low';
    if ($t !== '' && (float)$t > 39.5) $msgs[] = 'High fever';
    if ($pulse !== '' && ((int)$pulse < 40 || (int)$pulse > 130)) $msgs[] = 'Abnormal pulse';
    if ($sys !== '' && (int)$sys > 180) $msgs[] = 'BP critically high';
    if ($sys !== '' && (int)$sys < 90) $msgs[] = 'BP critically low';
    if ($rr !== '' && ((int)$rr < 8 || (int)$rr > 30)) $msgs[] = 'Respiratory rate abnormal';
    return implode(', ', $msgs);
}

function calc_eta($remaining, $rate, $start) {
    if ((float)$rate <= 0 || !$start) return null;
    return date('Y-m-d H:i:s', strtotime($start) + round(((float)$remaining / (float)$rate) * 3600));
}

function runSystemJobs() {
    $s = schedule_cols();
    $p = patient_cols();
    $i = item_cols();
    $l = location_cols();

    // Overdue medications — create tasks only (no flood of alerts)
    $overdue = all_rows(
        "SELECT ms.id, ms.`{$s['patientid']}` AS patient_id, ms.`{$s['itemid']}` AS item_id,
                p.fullname, i.`{$i['itemname']}` AS item_name
         FROM medicationschedule ms
         JOIN patients p ON p.id = ms.`{$s['patientid']}`
         JOIN items i ON i.id = ms.`{$s['itemid']}`
         WHERE ms.status='pending' AND ms.`{$s['scheduledtime']}` < NOW() - INTERVAL 30 MINUTE"
    );
    foreach ($overdue as $m) {
        createTask(
            'med-overdue-' . $m['id'],
            'medication',
            'Overdue medication',
            $m['item_name'] . ' overdue for ' . $m['fullname'],
            (int)$m['patient_id'],
            (int)$m['item_id'],
            'high',
            date('Y-m-d H:i:s', time() + 1800)
        );
    }

    // Safety profiles
    $profiles = all_rows(
        "SELECT sp.*, p.fullname, p.`{$p['lastseenat']}` AS last_seen_at
         FROM patientsafetyprofiles sp
         JOIN patients p ON p.id = sp.patientid
         WHERE p.status <> 'discharged'"
    );
    foreach ($profiles as $x) {
        if (empty($x['last_seen_at'])) continue;
        $mins = (time() - strtotime($x['last_seen_at'])) / 60;
        if ($mins > (int)$x['maxunseenminutes']) {
            createTask(
                'unseen-' . $x['patientid'] . '-' . date('YmdHi'),
                'safety',
                'Patient unseen beyond threshold',
                $x['fullname'] . ' has not been scanned recently',
                (int)$x['patientid'],
                0,
                'high',
                date('Y-m-d H:i:s', time() + 900)
            );
        }
    }

    // Offline readers — tasks only
    $offline = all_rows(
        "SELECT id, `{$l['locationname']}` AS location_name
         FROM locations
         WHERE `{$l['readerid']}` IS NOT NULL
         AND (`{$l['lastheartbeat']}` IS NULL OR `{$l['lastheartbeat']}` < NOW() - INTERVAL 10 MINUTE)"
    );
    foreach ($offline as $r) {
        createTask(
            'reader-' . $r['id'],
            'ops',
            'Reader offline',
            $r['location_name'] . ' reader heartbeat missing',
            0, 0, 'medium',
            date('Y-m-d H:i:s', time() + 1800)
        );
    }

    // Drug risk — tasks only
    $risk = all_rows(
        "SELECT id, `{$i['itemname']}` AS item_name, `{$i['expirydate']}` AS expiry_date, `{$i['recallflag']}` AS recall_flag
         FROM items
         WHERE `{$i['itemtype']}`='medicine'
         AND (`{$i['recallflag']}`=1 OR (`{$i['expirydate']}` IS NOT NULL AND `{$i['expirydate']}` <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)))"
    );
    foreach ($risk as $x) {
        $priority = ((int)$x['recall_flag'] === 1 || ($x['expiry_date'] && strtotime($x['expiry_date']) < time())) ? 'critical' : 'medium';
        createTask(
            'drug-risk-' . $x['id'],
            'inventory',
            'Medication risk review',
            $x['item_name'] . ' needs pharmacy review',
            0, (int)$x['id'], $priority,
            date('Y-m-d H:i:s', time() + 86400)
        );
    }
}

function add_caretaker_account($patient_id, $caretaker_id, $username, $password) {
    global $db;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO caretaker_accounts (caretaker_id, patient_id, username, password) 
            VALUES(?,?,?,?)";
    $st = $db->prepare($sql);
    $st->bind_param('iiss', $caretaker_id, $patient_id, $username, $hash);
    return $st->execute();
}

function nav_items() {
    return [
        'dashboard'   => 'Dashboard',
        'patients'    => 'Patients',
        'safety'      => 'Safety',
        'inventory'   => 'Inventory',
        'medications' => 'Medications',
        'workflow'    => 'Workflow',
        'vitals'      => 'Vitals',
        'iv'          => 'IV Drips',
        'alerts'      => 'Alerts',
        'staff'       => 'Staff & RFID',  
        'readers'     => 'Reader Health',
        'maintenance' => 'Maintenance',
        'discharge'   => 'Discharge',
        'audit'       => 'Audit Log',
        'portal'      => 'Caretaker Portal',
        'reports'     => 'Reports',
        'replay'      => 'Replay',
    ];
}
function nav_icons() {
    return [
        'dashboard'   => 'fa-chart-line',
        'patients'    => 'fa-hospital-user',
        'safety'      => 'fa-shield-heart',
        'inventory'   => 'fa-boxes-stacked',
        'medications' => 'fa-pills',
        'workflow'    => 'fa-list-check',
        'vitals'      => 'fa-heart-pulse',
        'iv'          => 'fa-droplet',
        'alerts'      => 'fa-bell',
        'staff'       => 'fa-user-nurse',       // ← ADD
        'readers'     => 'fa-tower-broadcast',
        'maintenance' => 'fa-screwdriver-wrench',
        'discharge'   => 'fa-right-from-bracket',
        'audit'       => 'fa-scroll',
        'portal'      => 'fa-users',
        'reports'     => 'fa-file-lines',
        'replay'      => 'fa-route',
    ];
}

function unread_alert_count() {
    $a = alert_cols();
    $n = notification_cols();
    $adminId = current_admin_id();
    $row = one(
        "SELECT COUNT(*) AS c
         FROM alerts a
         LEFT JOIN notification_reads n
           ON n.`{$n['alertid']}`=a.id
          AND n.`{$n['adminid']}`={$adminId}
         WHERE a.`{$a['isresolved']}`=0 AND n.id IS NULL"
    );
    return (int)($row['c'] ?? 0);
}

function render_header($title, $page = 'dashboard', $auth = true) {
    if ($auth) require_login();
    $nav = nav_items();
    $icons = nav_icons();
    $unread = $auth ? unread_alert_count() : 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> - MediTrack</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<style>
:root{
    --bg:#030508;--bg2:#070b10;--panel:#0b1017;--panel2:#101722;--panel3:#151e2b;
    --line:#1a2636;--line2:#233247;--text:#edf3ff;--muted:#90a4c2;--soft:#5e728f;
    --blue:#56a8ff;--cyan:#29d3ff;--green:#34d399;--yellow:#fbbf24;--red:#fb7185;--purple:#a78bfa;
    --shadow:0 18px 50px rgba(0,0,0,.45);--radius:18px;--side:255px;--top:68px;
}
*{box-sizing:border-box}
html,body{margin:0;padding:0;background:linear-gradient(180deg,#020407 0%,#05080d 100%);color:var(--text);font-family:'JetBrains Mono',monospace}
a{text-decoration:none;color:inherit}
button,input,select,textarea{font:inherit}
body{display:flex;min-height:100vh}
::-webkit-scrollbar{width:8px;height:8px}
::-webkit-scrollbar-thumb{background:#223349;border-radius:999px}
.sidebar{width:var(--side);position:fixed;left:0;top:0;bottom:0;background:rgba(5,8,13,.98);border-right:1px solid var(--line);padding:20px 14px;overflow:auto}
.brand{padding:8px 10px 18px;border-bottom:1px solid var(--line);margin-bottom:14px}
.logo{font-weight:800;font-size:22px;letter-spacing:.5px;background:linear-gradient(90deg,var(--cyan),var(--blue),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.tag{color:var(--soft);font-size:11px;margin-top:6px;text-transform:uppercase;letter-spacing:1px}
.section{color:var(--soft);font-size:11px;padding:16px 10px 8px;text-transform:uppercase;letter-spacing:1px}
.nav{display:flex;flex-direction:column;gap:6px}
.nav a{display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:14px;color:var(--muted);border:1px solid transparent}
.nav a:hover,.nav a.active{background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(255,255,255,.015));border-color:var(--line2);color:var(--text)}
.main{flex:1;min-width:0;margin-left:var(--side)}
.topbar{height:var(--top);position:sticky;top:0;z-index:20;background:rgba(4,7,11,.88);backdrop-filter:blur(12px);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 24px}
.title{font-size:18px;font-weight:700}
.right{display:flex;align-items:center;gap:10px}
.chip,.icon-btn{background:var(--panel);border:1px solid var(--line2);color:var(--text);border-radius:999px;padding:10px 13px}
.icon-btn{cursor:pointer;position:relative}
.badge{position:absolute;top:-4px;right:-4px;background:var(--red);color:#fff;border-radius:999px;padding:2px 7px;font-size:10px;font-weight:700}
.content{padding:24px}
.banner{padding:13px 16px;margin-bottom:18px;background:rgba(41,211,255,.08);border:1px solid rgba(41,211,255,.2);color:#dff7ff;border-radius:14px}
.grid{display:grid;gap:18px}
.g2{grid-template-columns:repeat(2,minmax(0,1fr))}
.g4{grid-template-columns:repeat(4,minmax(0,1fr))}
.card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.01));border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
.card h3{margin:0 0 14px;font-size:15px}
.kpi .label{color:var(--soft);font-size:11px;text-transform:uppercase;letter-spacing:1px}
.kpi .value{font-size:34px;font-weight:800;margin:10px 0 8px}
.kpi .sub{color:var(--muted);font-size:12px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px 10px;border-bottom:1px solid rgba(255,255,255,.06);text-align:left;vertical-align:top}
.table th{font-size:11px;color:var(--soft);text-transform:uppercase}
.table tr:hover td{background:rgba(255,255,255,.02)}
.pill{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;font-size:11px;border:1px solid}
.ok{background:rgba(52,211,153,.12);border-color:rgba(52,211,153,.25);color:#9af0cf}
.warn{background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.25);color:#fde68a}
.danger{background:rgba(251,113,133,.12);border-color:rgba(251,113,133,.25);color:#fecdd3}
.info{background:rgba(86,168,255,.12);border-color:rgba(86,168,255,.25);color:#bfdbfe}
.purple{background:rgba(167,139,250,.12);border-color:rgba(167,139,250,.25);color:#ddd6fe}
.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 14px;border:none;cursor:pointer;border-radius:12px;background:var(--panel2);color:var(--text);border:1px solid var(--line2)}
.btn.primary{background:linear-gradient(90deg,#102335,#17314a);border-color:#254766}
.btn.green{background:rgba(52,211,153,.12);border-color:rgba(52,211,153,.22);color:#9af0cf}
.btn.red{background:rgba(251,113,133,.12);border-color:rgba(251,113,133,.22);color:#fecdd3}
.btn.small{padding:8px 10px;font-size:12px}
.form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.form-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}
label{display:block;margin-bottom:6px;color:var(--muted);font-size:12px}
.inp,select,textarea{width:100%;padding:11px 12px;border-radius:12px;border:1px solid var(--line2);background:#060b12;color:var(--text);outline:none}
.inp:focus,select:focus,textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(86,168,255,.12)}
.muted{color:var(--muted);font-size:12px}
.notif{display:none;position:absolute;right:0;top:45px;width:360px;max-height:430px;overflow:auto;background:var(--panel);border:1px solid var(--line2);border-radius:16px;box-shadow:var(--shadow);padding:10px}
.notif-item{padding:10px;border-bottom:1px solid rgba(255,255,255,.06)}
.heatmap{display:grid;grid-template-columns:180px repeat(4,minmax(0,1fr));gap:8px}
.heatmap div{padding:10px;border-radius:10px;background:var(--panel2);border:1px solid var(--line)}
.heat1{background:rgba(41,211,255,.08)!important}.heat2{background:rgba(86,168,255,.14)!important}
.heat3{background:rgba(167,139,250,.18)!important}.heat4{background:rgba(251,113,133,.22)!important}
@media(max-width:1100px){.g4,.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:860px){body{display:block}.sidebar{position:static;width:100%;height:auto}.main{margin-left:0}.topbar{position:static}.g2,.g4,.form-grid,.form-grid.two,.heatmap{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php if ($auth): ?>
<aside class="sidebar">
    <div class="brand">
        <div class="logo"><i class="fas fa-hospital-symbol"></i> MediTrack</div>
        <div class="tag">RFID Hospital Management</div>
    </div>
    <div class="section">Navigation</div>
    <div class="nav">
        <?php foreach ($nav as $k => $v): ?>
            <a href="index.php?page=<?= e($k) ?>" class="<?= $page === $k ? 'active' : '' ?>">
                <i class="fas <?= e($icons[$k] ?? 'fa-circle') ?>"></i> <?= e($v) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="section">Account</div>
    <div class="nav">
        <a href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </div>
</aside>
<?php endif; ?>

<div class="main">
    <div class="topbar">
        <div class="title"><?= e($title) ?></div>
        <div class="right">
            <?php if ($auth): ?>
            <div style="position:relative">
                <button class="icon-btn" onclick="toggleNotif()">
                    <i class="fas fa-bell"></i>
                    <span id="notifCount" class="badge" <?= $unread ? '' : 'style="display:none"' ?>><?= (int)$unread ?></span>
                </button>
                <div class="notif" id="notifBox">
                    <div class="muted" style="padding:6px 8px">Live notifications</div>
                    <div id="notifItems"></div>
                </div>
            </div>
            <div class="chip">
                <i class="fas fa-user-shield"></i> <?= e(admin()) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="content">
<?php
}

function render_footer($auth = true) {
?>
    </div>
</div>
<?php if ($auth): ?>
<script>
async function loadNotifications(){
    try{
        const r = await fetch('api.php?action=notifications');
        const d = await r.json();
        const count = document.getElementById('notifCount');
        count.textContent = d.count || 0;
        count.style.display = (d.count || 0) > 0 ? 'inline-block' : 'none';
        document.getElementById('notifItems').innerHTML =
            (d.items || []).map(x => `
                <div class="notif-item">
                    <div style="font-size:12px">${escapeHtml(x.message || '')}</div>
                    <div class="muted">${escapeHtml(x.created_at || '')}</div>
                    ${x.related_url ? `<a class="muted" href="${escapeHtml(x.related_url)}">Open</a>` : ''}
                </div>
            `).join('') || '<div class="notif-item muted">No active notifications</div>';
    }catch(e){}
}
function toggleNotif(){
    const box = document.getElementById('notifBox');
    box.style.display = box.style.display === 'block' ? 'none' : 'block';
    if (box.style.display === 'block') loadNotifications();
}
function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
setInterval(loadNotifications, 30000);
</script>
<?php endif; ?>
</body>
</html>
<?php
}

runSystemJobs();