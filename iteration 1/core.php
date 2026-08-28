<?php
// ============================================================
// core.php — Foundation: DB, Auth, Logging, UI Shell
// MediTrack HMS v3.0
// ============================================================

session_start();
date_default_timezone_set('Asia/Kolkata');

$conn = new mysqli('localhost', 'root', '', 'rfid_inventory');
if ($conn->connect_error) {
    http_response_code(503);
    error_log('[MEDITRACK] DB Error: ' . $conn->connect_error);
    die(json_encode(['error' => 'Database unavailable. Contact administrator.']));
}
$conn->set_charset('utf8mb4');

function db(): mysqli { global $conn; return $conn; }

function sys_log(string $level, string $source, string $msg): void {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $uid = $_SESSION['admin_id'] ?? null;
    $stmt = db()->prepare("INSERT INTO system_logs (log_level,source,message,ip_address,user_id) VALUES (?,?,?,?,?)");
    if ($stmt) { $stmt->bind_param('ssssi',$level,$source,$msg,$ip,$uid); $stmt->execute(); $stmt->close(); }
}

function create_alert(string $severity, string $message, string $type='unknown_tag', ?int $item_id=null, ?int $patient_id=null): void {
    $stmt = db()->prepare("INSERT INTO alerts (severity,message,alert_type,item_id,patient_id) VALUES (?,?,?,?,?)");
    if ($stmt) { $stmt->bind_param('sssii',$severity,$message,$type,$item_id,$patient_id); $stmt->execute(); $stmt->close(); }
}

function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function admin_role(): string { return $_SESSION['admin_role'] ?? 'viewer'; }
function is_super_admin(): bool { return admin_role() === 'super_admin'; }
function is_manager(): bool { return in_array(admin_role(), ['super_admin','manager']); }
function require_admin(): void { if (!is_admin()) { header('Location: login.php'); exit; } }

function h(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function fmt_currency(float $v): string { return '₹' . number_format($v, 2); }

function ago(string $ts): string {
    if (!$ts) return 'Never';
    $d = time() - strtotime($ts);
    if ($d < 60) return $d . 's ago';
    if ($d < 3600) return floor($d/60) . 'm ago';
    if ($d < 86400) return floor($d/3600) . 'h ago';
    return date('d M Y', strtotime($ts));
}

function next_patient_id(): string {
    $year = date('Y');
    $res = db()->query("SELECT patient_id FROM patients WHERE patient_id LIKE 'HOSP-$year-%' ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if (!$res) return "HOSP-$year-0001";
    $num = (int)substr($res['patient_id'], -4) + 1;
    return "HOSP-$year-" . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function refresh_lifecycles(): void {
    db()->query("UPDATE items SET lifecycle='expired' WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() AND lifecycle NOT IN ('expired','retired','disposed')");
    db()->query("UPDATE items SET lifecycle='expiring_soon' WHERE expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 90 DAY) AND lifecycle='active'");
    db()->query("UPDATE items SET lifecycle='active' WHERE (expiry_date IS NULL OR expiry_date > DATE_ADD(CURDATE(),INTERVAL 90 DAY)) AND lifecycle='expiring_soon'");
    // Low stock alerts
    $low = db()->query("SELECT id,item_name,quantity,min_stock_level FROM items WHERE quantity<=min_stock_level AND min_stock_level>0");
    while ($r = $low->fetch_assoc()) {
        $chk = db()->query("SELECT id FROM alerts WHERE item_id={$r['id']} AND alert_type='low_stock' AND is_resolved=0 LIMIT 1")->num_rows;
        if (!$chk) create_alert('warning',"Low stock: {$r['item_name']} — {$r['quantity']} left (min: {$r['min_stock_level']})",'low_stock',$r['id']);
    }
    // Maintenance overdue
    db()->query("UPDATE maintenance_logs SET status='overdue' WHERE status='scheduled' AND scheduled_date < CURDATE()");
    $mdue = db()->query("SELECT ml.id,i.item_name,i.id as iid,ml.maintenance_type FROM maintenance_logs ml JOIN items i ON ml.item_id=i.id WHERE ml.status='overdue'");
    while ($r = $mdue->fetch_assoc()) {
        $chk = db()->query("SELECT id FROM alerts WHERE item_id={$r['iid']} AND alert_type='maintenance_due' AND is_resolved=0 LIMIT 1")->num_rows;
        if (!$chk) create_alert('warning',"Maintenance overdue: {$r['item_name']} ({$r['maintenance_type']})",'maintenance_due',$r['iid']);
    }
    // Blood expiry
    db()->query("UPDATE blood_units SET status='discarded' WHERE expiry_date < CURDATE() AND status='available'");
    $bexp = db()->query("SELECT id,unit_id,blood_group FROM blood_units WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 3 DAY) AND status='available'");
    while ($r = $bexp->fetch_assoc()) {
        $chk = db()->query("SELECT id FROM alerts WHERE message LIKE '%{$r['unit_id']}%' AND alert_type='blood_expiry' AND is_resolved=0 LIMIT 1")->num_rows;
        if (!$chk) create_alert('critical',"Blood unit {$r['unit_id']} ({$r['blood_group']}) expiring within 3 days!",'blood_expiry');
    }
}


// ── Role-based auth (clinical staff system) ──────────────
function require_role(array $roles): void {
    if (!is_admin() && empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
    $role = $_SESSION['user_role'] ?? $_SESSION['admin_role'] ?? '';
    // Map admin roles to clinical roles
    $map = ['super_admin'=>'admin','manager'=>'admin','viewer'=>'nurse','biomedical'=>'nurse','pharmacist'=>'pharmacist','nurse_admin'=>'nurse'];
    $effective = $map[$role] ?? $role;
    if (!in_array($effective, $roles) && !in_array($role, $roles) && $role !== 'super_admin') {
        http_response_code(403); echo '<h2 style="font-family:sans-serif;padding:40px">Access Denied.</h2>'; exit;
    }
}

// ── Audit log ─────────────────────────────────────────────
function audit(string $action, string $module, string $record_type, int $record_id, string $details = ''): void {
    $uid  = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
    $role = $_SESSION['user_role'] ?? $_SESSION['admin_role'] ?? 'system';
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $a    = db()->real_escape_string($action);
    $m    = db()->real_escape_string($module);
    $rt   = db()->real_escape_string($record_type);
    $det  = db()->real_escape_string($details);
    $rl   = db()->real_escape_string($role);
    db()->query("INSERT INTO audit_log (user_id,user_role,action,module,record_type,record_id,details,ip_address)
        VALUES (" . ($uid ? $uid : 'NULL') . ",'$rl','$a','$m','$rt',$record_id,'$det','$ip')");
}

// ── Overloaded create_alert (handles equipment_id 6th param) ─
function create_alert_extended(string $type, string $severity, string $message, ?int $patient_id = null, ?int $zone_id = null, ?int $equipment_id = null): void {
    $stmt = db()->prepare("INSERT INTO alerts (alert_type,severity,message,patient_id) VALUES (?,?,?,?)");
    if ($stmt) { $stmt->bind_param('sssi', $type, $severity, $message, $patient_id); $stmt->execute(); $stmt->close(); }
}

// ── Quick stat helpers ────────────────────────────────────
function open_alerts(): int {
    return (int)db()->query("SELECT COUNT(*) FROM alerts WHERE is_resolved=0")->fetch_row()[0];
}
function active_patients(): int {
    return (int)db()->query("SELECT COUNT(*) FROM admissions WHERE status='active'")->fetch_row()[0];
}

// ── UI badge helpers ──────────────────────────────────────
function patient_avatar(string $name, string $code): string {
    $initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', trim($name)), 0, 2))));
    return '<div class="td-main">' . h($name) . '</div><div class="td-sub">' . h($code) . '</div>';
}
function risk_badge(?string $level): string {
    if (!$level || $level === 'none' || $level === 'low') return '<span class="pill pill-green" style="font-size:10px">Low</span>';
    if ($level === 'medium') return '<span class="pill pill-yellow" style="font-size:10px">Medium</span>';
    return '<span class="pill pill-red" style="font-size:10px">High</span>';
}
function severity_badge(string $s): string {
    $map = ['critical'=>'pill-red','warning'=>'pill-yellow','info'=>'pill-blue'];
    return '<span class="pill ' . ($map[$s] ?? 'pill-gray') . '" style="font-size:10px">' . h(ucfirst($s)) . '</span>';
}
function status_badge(string $s): string {
    $map = ['active'=>'pill-green','inactive'=>'pill-gray','suspended'=>'pill-red','available'=>'pill-green','in_use'=>'pill-blue','missing'=>'pill-red'];
    return '<span class="pill ' . ($map[$s] ?? 'pill-gray') . '" style="font-size:10px">' . h(ucfirst($s)) . '</span>';
}

function render_header(string $title, bool $require_auth=true): void {
    if ($require_auth) require_admin();
    $page = basename($_SERVER['PHP_SELF']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?> | MediTrack HMS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<style>
:root{--bg:#070b12;--bg2:#0d1320;--surface:#111827;--surface2:#1a2338;--border:#1e2d45;--border2:#2a3f5f;--text:#e8edf5;--sub:#6b7fa0;--muted:#3d5275;--accent:#3b82f6;--accent2:#8b5cf6;--green:#10b981;--yellow:#f59e0b;--red:#ef4444;--cyan:#06b6d4;--pink:#ec4899;--grad:linear-gradient(135deg,#3b82f6 0%,#8b5cf6 50%,#06b6d4 100%);--grad2:linear-gradient(135deg,#1e3a5f,#2d1b4e);--sidebar-w:268px;--header-h:64px;--radius:12px;--shadow:0 4px 24px rgba(0,0,0,0.4)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}html{scroll-behavior:smooth}
body{font-family:'Syne',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex}
.sidebar{width:var(--sidebar-w);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-brand{padding:20px 18px 14px;border-bottom:1px solid var(--border)}
.sidebar-brand .logo{font-size:18px;font-weight:800;background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-0.5px}
.sidebar-brand .logo i{margin-right:8px}
.sidebar-brand .tagline{font-size:10px;color:var(--muted);font-family:'DM Mono',monospace;letter-spacing:1px;text-transform:uppercase;margin-top:3px}
.nav-section{padding:14px 14px 6px;font-size:10px;font-family:'DM Mono',monospace;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase}
.nav-link{display:flex;align-items:center;gap:10px;padding:10px 12px;margin:2px 8px;border-radius:var(--radius);color:var(--sub);text-decoration:none;font-size:12.5px;font-weight:600;transition:all 0.15s;position:relative}
.nav-link:hover{background:var(--surface);color:var(--text)}
.nav-link.active{background:var(--surface2);color:var(--accent);border:1px solid var(--border2)}
.nav-link.active::before{content:'';position:absolute;left:0;top:25%;bottom:25%;width:3px;background:var(--grad);border-radius:0 3px 3px 0}
.nav-link .icon{width:18px;text-align:center;font-size:13px}
.nav-link .badge-count{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-family:'DM Mono',monospace;padding:1px 6px;border-radius:20px;min-width:18px;text-align:center}
.sidebar-footer{margin-top:auto;padding:14px;border-top:1px solid var(--border)}
.logout-btn{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:var(--radius);color:var(--red);text-decoration:none;font-size:13px;font-weight:600;transition:background 0.15s}
.logout-btn:hover{background:rgba(239,68,68,0.08)}
.menu-toggle{display:none;position:fixed;top:14px;left:14px;z-index:300;background:var(--surface2);border:1px solid var(--border2);color:var(--text);border-radius:10px;width:42px;height:42px;align-items:center;justify-content:center;cursor:pointer;font-size:16px}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:199}
.main{margin-left:var(--sidebar-w);flex:1;min-width:0;display:flex;flex-direction:column}
.topbar{position:sticky;top:0;z-index:100;height:var(--header-h);background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 28px;gap:16px}
.topbar-title{font-size:16px;font-weight:700}
.topbar-right{display:flex;align-items:center;gap:12px}
.admin-chip{display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--border);border-radius:50px;padding:6px 14px;font-size:12px;font-family:'DM Mono',monospace}
.admin-chip .dot-green{width:7px;height:7px;background:var(--green);border-radius:50%}
.content{padding:26px;flex:1}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;margin-bottom:22px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:22px}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px;position:relative;overflow:hidden;margin-bottom:0}
.card-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:12px}
.card-title{font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;color:var(--text)}
.card-title i{color:var(--accent2);font-size:14px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px 22px;position:relative;overflow:hidden;transition:transform 0.15s,border-color 0.15s}
.stat-card:hover{transform:translateY(-2px);border-color:var(--border2)}
.stat-card::after{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--grad)}
.stat-label{font-size:10px;font-family:'DM Mono',monospace;color:var(--sub);letter-spacing:1px;text-transform:uppercase}
.stat-value{font-size:36px;font-weight:800;line-height:1.1;margin:8px 0 4px}
.stat-sub{font-size:11px;color:var(--muted)}
.stat-icon{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:38px;opacity:0.07}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;padding:10px 14px;font-size:10px;font-family:'DM Mono',monospace;color:var(--sub);letter-spacing:1px;text-transform:uppercase;border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:12px 14px;border-bottom:1px solid rgba(30,45,69,0.5);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,0.02)}
.td-name{font-weight:700}.td-sub{font-size:11px;color:var(--sub);margin-top:2px;font-family:'DM Mono',monospace}
.pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700;font-family:'DM Mono',monospace;white-space:nowrap;border:1px solid}
.pill-blue{background:rgba(59,130,246,0.1);border-color:rgba(59,130,246,0.3);color:#93c5fd}
.pill-purple{background:rgba(139,92,246,0.1);border-color:rgba(139,92,246,0.3);color:#c4b5fd}
.pill-green{background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.3);color:#6ee7b7}
.pill-yellow{background:rgba(245,158,11,0.1);border-color:rgba(245,158,11,0.3);color:#fcd34d}
.pill-red{background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.3);color:#fca5a5}
.pill-cyan{background:rgba(6,182,212,0.1);border-color:rgba(6,182,212,0.3);color:#67e8f9}
.pill-gray{background:rgba(100,116,139,0.1);border-color:rgba(100,116,139,0.3);color:#94a3b8}
.pill-pink{background:rgba(236,72,153,0.1);border-color:rgba(236,72,153,0.3);color:#f9a8d4}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:10px;font-family:'Syne',sans-serif;font-size:12px;font-weight:700;cursor:pointer;border:none;text-decoration:none;transition:all 0.15s;white-space:nowrap}
.btn-primary{background:var(--grad);color:#fff}.btn-primary:hover{opacity:0.88}
.btn-dark{background:var(--surface2);color:var(--text);border:1px solid var(--border2)}.btn-dark:hover{border-color:var(--accent);color:var(--accent)}
.btn-red{background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.3)}.btn-red:hover{background:rgba(239,68,68,0.25)}
.btn-green{background:rgba(16,185,129,0.15);color:#6ee7b7;border:1px solid rgba(16,185,129,0.3)}.btn-green:hover{background:rgba(16,185,129,0.25)}
.btn-yellow{background:rgba(245,158,11,0.15);color:#fcd34d;border:1px solid rgba(245,158,11,0.3)}.btn-yellow:hover{background:rgba(245,158,11,0.25)}
.btn-sm{padding:6px 11px;font-size:11px;border-radius:8px}
.form-group{margin-bottom:18px}
.form-label{display:block;font-size:11px;font-family:'DM Mono',monospace;color:var(--sub);letter-spacing:0.8px;text-transform:uppercase;margin-bottom:7px}
.form-control{width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-family:'Syne',sans-serif;font-size:13px;outline:none;transition:border-color 0.15s,box-shadow 0.15s}
.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,0.1)}
select.form-control option{background:var(--bg2)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
.alert-banner{border-radius:12px;padding:13px 18px;margin-bottom:18px;font-size:13px;display:flex;align-items:center;gap:10px}
.alert-err{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#fca5a5}
.alert-ok{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);color:#6ee7b7}
.alert-warn{background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);color:#fcd34d}
.help-text{font-size:11px;color:var(--muted);margin-top:5px}
.section-divider{display:flex;align-items:center;gap:12px;margin:22px 0 16px;font-size:11px;font-family:'DM Mono',monospace;color:var(--muted);letter-spacing:1px;text-transform:uppercase}
.section-divider::before,.section-divider::after{content:'';flex:1;height:1px;background:var(--border)}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--muted);border-radius:10px}
@media print{.sidebar,.topbar,.btn,.menu-toggle,.sidebar-overlay{display:none!important}.main{margin-left:0!important}body{background:#fff!important;color:#000!important}.card{border:1px solid #ccc!important;background:#fff!important}table{border-collapse:collapse!important}th,td{border:1px solid #ccc!important;color:#000!important;padding:6px 8px!important}.pill{border:1px solid #999!important;color:#000!important;background:#eee!important}}
@media(max-width:1100px){.grid-4{grid-template-columns:repeat(2,1fr)}.grid-3{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.grid-2{grid-template-columns:1fr}.form-row,.form-row-3{grid-template-columns:1fr}}
@media(max-width:768px){.menu-toggle{display:flex}.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0);box-shadow:4px 0 30px rgba(0,0,0,0.5)}.sidebar-overlay.open{display:block}.main{margin-left:0}.topbar{padding:0 16px 0 64px}.content{padding:16px}.grid-4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.grid-4{grid-template-columns:1fr 1fr}}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-bottom:22px}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:10px}
.stat-icon.green{background:rgba(16,185,129,0.15);color:var(--green)}
.stat-icon.red{background:rgba(239,68,68,0.15);color:var(--red)}
.stat-icon.amber{background:rgba(245,158,11,0.15);color:var(--yellow)}
.stat-icon.blue{background:rgba(59,130,246,0.15);color:var(--accent)}
.stat-icon.purple{background:rgba(139,92,246,0.15);color:var(--accent2)}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px}
.banner{border-radius:12px;padding:13px 18px;margin-bottom:18px;font-size:13px;display:flex;align-items:center;gap:10px}
.banner-ok{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);color:#6ee7b7}
.banner-err{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#fca5a5}
.banner-warn{background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);color:#fcd34d}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:50px;font-size:11px;font-weight:700;font-family:'DM Mono',monospace}
.badge-ok{background:rgba(16,185,129,0.12);color:#6ee7b7;border:1px solid rgba(16,185,129,0.3)}
.badge-warn{background:rgba(245,158,11,0.12);color:#fcd34d;border:1px solid rgba(245,158,11,0.3)}
.badge-crit{background:rgba(239,68,68,0.12);color:#fca5a5;border:1px solid rgba(239,68,68,0.3)}
.badge-muted{background:rgba(100,116,139,0.12);color:#94a3b8;border:1px solid rgba(100,116,139,0.3)}
.badge-purple{background:rgba(139,92,246,0.12);color:#c4b5fd;border:1px solid rgba(139,92,246,0.3)}
.tbl-wrap{overflow-x:auto}
.btn-danger{background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.3)}
.btn-success{background:rgba(16,185,129,0.15);color:#6ee7b7;border:1px solid rgba(16,185,129,0.3)}
.btn-warning{background:rgba(245,158,11,0.15);color:#fcd34d;border:1px solid rgba(245,158,11,0.3)}
.btn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border2)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-xs{padding:4px 9px;font-size:10px;border-radius:6px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
.small-text{font-size:11px;color:var(--sub);margin-top:2px}
.divider{height:1px;background:var(--border);margin:16px 0}
.empty{text-align:center;padding:32px;color:var(--sub)}
.empty i{font-size:28px;margin-bottom:10px;display:block}
@media(max-width:900px){.g2{grid-template-columns:1fr}.form-row-3{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.form-row-3{grid-template-columns:1fr}.stat-grid{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<?php if (is_admin()):
$open_alerts      = (int)db()->query("SELECT COUNT(*) as c FROM alerts WHERE is_resolved=0")->fetch_assoc()['c'];
$pending_approvals= (int)db()->query("SELECT COUNT(*) as c FROM approvals WHERE status='pending'")->fetch_assoc()['c'];
$admitted_patients= (int)db()->query("SELECT COUNT(*) as c FROM patients WHERE status IN ('admitted','icu','critical')")->fetch_assoc()['c'];
?>
<button class="menu-toggle" onclick="toggleSidebar()" aria-label="Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo"><i class="fas fa-hospital-symbol"></i> MediTrack</div>
        <div class="tagline">Hospital RFID Management</div>
    </div>

    <div class="nav-section">Clinical</div>
    <a href="index.php" class="nav-link <?= $page==='index.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-chart-pie"></i></span> Dashboard
    </a>
    <a href="patients.php" class="nav-link <?= $page==='patients.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-hospital-user"></i></span> Patients
        <?php if ($admitted_patients > 0): ?><span class="badge-count" style="background:var(--cyan)"><?= $admitted_patients ?></span><?php endif; ?>
    </a>
    <a href="staff.php" class="nav-link <?= $page==='staff.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-user-nurse"></i></span> Staff & RFID
    </a>
    <a href="blood_bank.php" class="nav-link <?= $page==='blood_bank.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-droplet"></i></span> Blood Bank
    </a>

    <div class="nav-section">Assets</div>
    <a href="inventory.php?action=list" class="nav-link <?= $page==='inventory.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-boxes-stacked"></i></span> Inventory
    </a>
    <a href="locations.php" class="nav-link <?= $page==='locations.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-map-location-dot"></i></span> Wards & Zones
    </a>
    <a href="movements.php" class="nav-link <?= $page==='movements.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-route"></i></span> Movements
    </a>
    <a href="maintenance.php" class="nav-link <?= $page==='maintenance.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-wrench"></i></span> Maintenance
    </a>

    <div class="nav-section">Operations</div>
    <a href="inventory.php?action=bulk" class="nav-link">
        <span class="icon"><i class="fas fa-file-import"></i></span> Bulk Import
    </a>
    <a href="inventory.php?action=count" class="nav-link">
        <span class="icon"><i class="fas fa-clipboard-check"></i></span> Cycle Count
    </a>
    <a href="suppliers.php" class="nav-link <?= $page==='suppliers.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-truck-medical"></i></span> Suppliers
    </a>
    <a href="reports.php" class="nav-link <?= $page==='reports.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-chart-bar"></i></span> Reports
    </a>

    <div class="nav-section">Governance</div>
    <a href="approvals.php" class="nav-link <?= $page==='approvals.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-code-pull-request"></i></span> Approvals
        <?php if ($pending_approvals > 0): ?><span class="badge-count"><?= $pending_approvals ?></span><?php endif; ?>
    </a>
    <a href="alerts.php" class="nav-link <?= $page==='alerts.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-bell"></i></span> Alerts
        <?php if ($open_alerts > 0): ?><span class="badge-count"><?= $open_alerts ?></span><?php endif; ?>
    </a>
    <a href="logs.php" class="nav-link <?= $page==='logs.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-shield-halved"></i></span> Audit Logs
    </a>
    <?php if (is_super_admin()): ?>
    <a href="create_admin.php" class="nav-link <?= $page==='create_admin.php'?'active':'' ?>">
        <span class="icon"><i class="fas fa-user-shield"></i></span> Manage Admins
    </a>
    <?php endif; ?>

    <div class="sidebar-footer">
        <div style="font-size:11px;color:var(--muted);font-family:'DM Mono',monospace;margin-bottom:10px;padding:0 4px">
            <?= h($_SESSION['admin_name'] ?? 'Admin') ?><br>
            <span style="color:var(--accent)"><?= strtoupper(admin_role()) ?></span>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fas fa-power-off"></i> Logout</a>
    </div>
</nav>

<div class="main">
    <div class="topbar">
        <div class="topbar-title"><?= h($title) ?></div>
        <div class="topbar-right">
            <a href="live_display.php" target="_blank" style="color:var(--sub);font-size:12px;text-decoration:none;display:flex;align-items:center;gap:6px"><i class="fas fa-tv"></i> Live</a>
            <?php if ($open_alerts > 0): ?>
            <a href="alerts.php" style="color:var(--yellow);font-size:13px;text-decoration:none;display:flex;align-items:center;gap:6px">
                <i class="fas fa-bell fa-shake"></i> <?= $open_alerts ?> alert<?= $open_alerts!=1?'s':'' ?>
            </a>
            <?php endif; ?>
            <div class="admin-chip">
                <span class="dot-green"></span>
                <?= h($_SESSION['admin_name'] ?? 'Admin') ?>
            </div>
        </div>
    </div>
    <div class="content">
<?php endif; ?>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
</script>
<?php
}

function render_footer(): void {
    if (is_admin()) echo '</div></div>';
    echo '</body></html>';
}
?>