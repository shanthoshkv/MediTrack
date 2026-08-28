<?php
require_once 'core.php';
render_header('Audit & System Logs');

$log_level = $_GET['ll'] ?? '';
$log_source = trim($_GET['src'] ?? '');
$date_from = $_GET['df'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to   = $_GET['dt'] ?? date('Y-m-d');

$sys_where = ["DATE(sl.created_at) BETWEEN '".db()->real_escape_string($date_from)."' AND '".db()->real_escape_string($date_to)."'"];
if ($log_level)  $sys_where[] = "sl.log_level='".db()->real_escape_string($log_level)."'";
if ($log_source) $sys_where[] = "sl.source LIKE '%".db()->real_escape_string($log_source)."%'";
$sys_w = 'WHERE '.implode(' AND ', $sys_where);

$scanLogs = db()->query("SELECT s.*, i.item_name, lf.location_name as from_loc, lt.location_name as to_loc
    FROM scan_logs s
    LEFT JOIN items i ON s.item_id = i.id
    LEFT JOIN locations lf ON s.from_location_id = lf.id
    LEFT JOIN locations lt ON s.to_location_id = lt.id
    ORDER BY s.id DESC LIMIT 100");

$sysLogs = db()->query("SELECT sl.* FROM system_logs sl $sys_w ORDER BY sl.id DESC LIMIT 200");
?>

<div class="card" style="margin-bottom:18px">
    <div class="card-hdr"><div class="card-title"><i class="fas fa-filter"></i>Filter System Logs</div></div>
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <input type="date" name="df" class="form-control" style="max-width:150px" value="<?= h($date_from) ?>">
            <input type="date" name="dt" class="form-control" style="max-width:150px" value="<?= h($date_to) ?>">
            <select name="ll" class="form-control" style="max-width:140px">
                <option value="">All Levels</option>
                <option value="info" <?= $log_level==='info'?'selected':'' ?>>INFO</option>
                <option value="warning" <?= $log_level==='warning'?'selected':'' ?>>WARNING</option>
                <option value="error" <?= $log_level==='error'?'selected':'' ?>>ERROR</option>
                <option value="critical" <?= $log_level==='critical'?'selected':'' ?>>CRITICAL</option>
            </select>
            <input type="text" name="src" class="form-control" style="max-width:160px" value="<?= h($log_source) ?>" placeholder="Filter by source...">
            <button type="submit" class="btn btn-dark btn-sm"><i class="fas fa-search"></i>Filter</button>
            <a href="logs.php" class="btn btn-dark btn-sm"><i class="fas fa-xmark"></i>Clear</a>
        </div>
    </form>
</div>

<div class="card" style="margin-bottom:18px">
    <div class="card-hdr">
        <div class="card-title"><i class="fas fa-wifi"></i>RFID Scan & Movement Stream (Last 100)</div>
    </div>
    <div style="max-height:400px;overflow-y:auto" class="table-wrap">
    <table>
        <tr><th>Timestamp</th><th>UID / Item</th><th>Action</th><th>Transition</th><th>Reader</th></tr>
        <?php while ($s = $scanLogs->fetch_assoc()):
            $pc = ['transfer'=>'pill-blue','checkout'=>'pill-cyan','return'=>'pill-green','manual_move'=>'pill-purple','scanned'=>'pill-gray','conflict_suppressed'=>'pill-red','cycle_count'=>'pill-yellow'];
        ?>
        <tr>
            <td style="font-size:11px;font-family:'DM Mono',monospace;white-space:nowrap"><?= h($s['scan_time']) ?></td>
            <td><div style="font-family:'DM Mono',monospace;font-size:12px"><?= h($s['uid']) ?></div><div class="td-sub"><?= h($s['item_name'] ?? 'Unknown Tag') ?></div></td>
            <td><span class="pill <?= $pc[$s['action_type']]??'pill-gray' ?>"><?= h($s['action_type']) ?></span></td>
            <td style="font-size:12px;color:var(--sub)"><?= h($s['from_loc'] ?? '—') ?> → <?= h($s['to_loc'] ?? '—') ?></td>
            <td style="font-family:'DM Mono',monospace;font-size:11px"><?= h($s['reader_id']) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    </div>
</div>

<div class="card">
    <div class="card-hdr">
        <div class="card-title"><i class="fas fa-bug"></i>Backend System Logs (<?= $sysLogs->num_rows ?> records)</div>
    </div>
    <div style="max-height:380px;overflow-y:auto" class="table-wrap">
    <table>
        <tr><th>Timestamp</th><th>Level</th><th>Source</th><th>Message</th><th>IP</th><th>User</th></tr>
        <?php while ($sy = $sysLogs->fetch_assoc()):
            $lc = ['info'=>'pill-blue','warning'=>'pill-yellow','error'=>'pill-red','critical'=>'pill-red'];
        ?>
        <tr>
            <td style="font-size:11px;font-family:'DM Mono',monospace;white-space:nowrap"><?= h($sy['created_at']) ?></td>
            <td><span class="pill <?= $lc[$sy['log_level']]??'pill-gray' ?>"><?= strtoupper($sy['log_level']) ?></span></td>
            <td style="font-family:'DM Mono',monospace;font-size:11px"><?= h($sy['source']) ?></td>
            <td style="font-size:13px"><?= h($sy['message']) ?></td>
            <td style="font-family:'DM Mono',monospace;font-size:11px;color:var(--sub)"><?= h($sy['ip_address'] ?? '—') ?></td>
            <td style="font-size:11px;color:var(--sub)"><?= h($sy['user_id'] ?? '—') ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    </div>
</div>

<?php render_footer(); ?>
