<?php
require_once 'core.php';

if (isset($_GET['resolve'])) {
    require_admin();
    $id     = (int)$_GET['resolve'];
    $action = db()->real_escape_string(trim($_GET['action_taken'] ?? 'Manually resolved'));
    $by     = db()->real_escape_string($_SESSION['admin_name']);
    db()->query("UPDATE alerts SET is_resolved=1, resolved_by='$by', resolved_at=NOW(), action_taken='$action' WHERE id=$id");
    sys_log('info', 'alerts.php', "Alert #$id resolved by {$_SESSION['admin_name']}: $action");
    header("Location: alerts.php"); exit;
}

if (isset($_GET['resolve_all'])) {
    require_admin();
    $by = db()->real_escape_string($_SESSION['admin_name']);
    db()->query("UPDATE alerts SET is_resolved=1, resolved_by='$by', resolved_at=NOW(), action_taken='Bulk resolved' WHERE is_resolved=0");
    sys_log('info', 'alerts.php', "All alerts bulk-resolved by {$_SESSION['admin_name']}");
    header("Location: alerts.php"); exit;
}

render_header('Alerts & Notifications');

$open   = db()->query("SELECT a.*, i.item_name FROM alerts a LEFT JOIN items i ON a.item_id=i.id WHERE a.is_resolved=0 ORDER BY a.created_at DESC");
$closed = db()->query("SELECT a.*, i.item_name FROM alerts a LEFT JOIN items i ON a.item_id=i.id WHERE a.is_resolved=1 ORDER BY a.resolved_at DESC LIMIT 30");

$counts = db()->query("SELECT severity, COUNT(*) as c FROM alerts WHERE is_resolved=0 GROUP BY severity")->fetch_all(MYSQLI_ASSOC);
$cnt = array_column($counts, 'c', 'severity');
?>

<div class="grid-4" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-label">Critical</div>
        <div class="stat-value" style="color:var(--red)"><?= $cnt['critical'] ?? 0 ?></div>
        <div class="stat-sub">Open critical alerts</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Warning</div>
        <div class="stat-value" style="color:var(--yellow)"><?= $cnt['warning'] ?? 0 ?></div>
        <div class="stat-sub">Open warnings</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Info</div>
        <div class="stat-value" style="color:var(--accent)"><?= $cnt['info'] ?? 0 ?></div>
        <div class="stat-sub">Open info alerts</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Open</div>
        <div class="stat-value"><?= $open->num_rows ?></div>
        <div class="stat-sub">Require attention</div>
    </div>
</div>

<div class="card" style="margin-bottom:20px">
    <div class="card-hdr">
        <div class="card-title"><i class="fas fa-bell"></i>Active Alerts (<?= $open->num_rows ?>)</div>
        <?php if ($open->num_rows > 0): ?>
        <a href="?resolve_all=1" class="btn btn-dark btn-sm" onclick="return confirm('Resolve ALL open alerts?')"><i class="fas fa-check-double"></i>Resolve All</a>
        <?php endif; ?>
    </div>
    <?php if ($open->num_rows === 0): ?>
    <div style="text-align:center;padding:32px;color:var(--sub)">
        <i class="fas fa-check-circle" style="font-size:36px;color:var(--green);display:block;margin-bottom:10px"></i>
        All clear — no active alerts.
    </div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Severity</th><th>Type</th><th>Item</th><th>Message</th><th>Created</th><th>Resolve</th></tr>
        <?php while ($al = $open->fetch_assoc()): ?>
        <tr>
            <td><span class="pill <?= $al['severity']==='critical'?'pill-red':($al['severity']==='warning'?'pill-yellow':'pill-blue') ?>"><?= h($al['severity']) ?></span></td>
            <td><span class="pill pill-gray"><?= h(str_replace('_',' ',$al['alert_type'])) ?></span></td>
            <td style="font-size:12px"><?= h($al['item_name'] ?? '—') ?></td>
            <td style="font-size:13px"><?= h($al['message']) ?></td>
            <td style="font-size:11px;color:var(--sub)"><?= ago($al['created_at']) ?></td>
            <td>
                <form method="GET" style="display:flex;gap:6px;align-items:center">
                    <input type="hidden" name="resolve" value="<?= $al['id'] ?>">
                    <input type="text" name="action_taken" class="form-control" style="max-width:140px;padding:6px 10px;font-size:11px" placeholder="Action taken...">
                    <button type="submit" class="btn btn-green btn-sm"><i class="fas fa-check"></i>Resolve</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table></div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-hdr"><div class="card-title"><i class="fas fa-history"></i>Resolved Alerts (Last 30)</div></div>
    <div class="table-wrap"><table>
        <tr><th>Severity</th><th>Type</th><th>Item / Patient</th><th>Message</th><th>Resolved By</th><th>Action Taken</th><th>Resolved At</th></tr>
        <?php while ($c = $closed->fetch_assoc()): ?>
        <tr>
    <td><span class="pill pill-gray"><?= h($c['severity']) ?></span></td>
    <td><span class="pill pill-gray"><?= h(str_replace('_',' ',$c['alert_type'])) ?></span></td>
    <td style="font-size:12px"><?= h($c['item_name'] ?? ($c['patient_id'] ? 'Patient #'.$c['patient_id'] : '—')) ?></td>
    <td style="font-size:12px"><?= h($c['message']) ?></td>
    <td style="font-size:12px"><?= h($c['resolved_by'] ?? '—') ?></td>
    <td style="font-size:12px;color:var(--sub)"><?= h($c['action_taken'] ?? '—') ?></td>
    <td style="font-size:11px;color:var(--sub)"><?= $c['resolved_at'] ? ago($c['resolved_at']) : '—' ?></td>
</tr>
        <?php endwhile; ?>
    </table></div>
</div>

<?php render_footer(); ?>
