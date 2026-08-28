<?php
require_once 'core.php';

// Process approval
if (isset($_GET['action']) && $_GET['action'] === 'approve') {
    require_admin();
    $id  = (int)$_GET['id'];
    $act = $_GET['act'] === 'yes' ? 'approved' : 'rejected';
    $app = db()->query("SELECT * FROM approvals WHERE id=$id LIMIT 1")->fetch_assoc();
    if ($app && $app['status'] === 'pending') {
        $reviewer = db()->real_escape_string($_SESSION['admin_name']);
        db()->query("UPDATE approvals SET status='$act', reviewed_by='$reviewer', reviewed_at=NOW() WHERE id=$id");
        if ($act === 'approved') {
            if ($app['request_type'] === 'checkout') {
                $assignee = db()->real_escape_string($app['target_assignee']);
                db()->query("UPDATE items SET status='checked_out', assigned_to='$assignee' WHERE id={$app['item_id']}");
            } elseif ($app['request_type'] === 'checkin' || $app['request_type'] === 'return') {
                db()->query("UPDATE items SET status='in_stock', assigned_to=NULL WHERE id={$app['item_id']}");
            } elseif ($app['request_type'] === 'transfer') {
                $to = (int)$app['to_location'];
                db()->query("UPDATE items SET location_id=$to WHERE id={$app['item_id']}");
                $stmt = db()->prepare("INSERT INTO scan_logs (uid, item_id, from_location_id, to_location_id, reader_id, action_type, performed_by) SELECT uid, id, ?, ?, 'ADMIN', 'transfer', ? FROM items WHERE id=?");
                $stmt->bind_param('iiis', $app['from_location'], $to, $_SESSION['admin_name'], $app['item_id']);
                $stmt->execute();
            } elseif ($app['request_type'] === 'retirement') {
                db()->query("UPDATE items SET lifecycle='retired', status='in_stock' WHERE id={$app['item_id']}");
            } elseif ($app['request_type'] === 'disposal') {
                db()->query("UPDATE items SET lifecycle='disposed', status='quarantined' WHERE id={$app['item_id']}");
            }
        }
        sys_log('info', 'approvals.php', "Approval #$id $act by {$_SESSION['admin_name']}");
    }
    header("Location: approvals.php"); exit;
}

// New approval request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_approval'])) {
    require_admin();
    $item_id  = (int)$_POST['item_id'];
    $type     = db()->real_escape_string($_POST['request_type']);
    $assignee = db()->real_escape_string(trim($_POST['target_assignee'] ?? ''));
    $reason   = db()->real_escape_string(trim($_POST['reason'] ?? ''));
    $from_loc = (int)($_POST['from_location'] ?? 0) ?: null;
    $to_loc   = (int)($_POST['to_location'] ?? 0) ?: null;
    $req      = db()->real_escape_string($_SESSION['admin_name']);
    db()->query("INSERT INTO approvals (item_id, request_type, requested_by, target_assignee, from_location, to_location, reason)
                 VALUES ($item_id, '$type', '$req', '$assignee', ".($from_loc?:'NULL').", ".($to_loc?:'NULL').", '$reason')");
    sys_log('info', 'approvals.php', "Approval request created: $type for item #$item_id by {$_SESSION['admin_name']}");
    header("Location: approvals.php?msg=created"); exit;
}

render_header('Approval Workflows');

if (isset($_GET['msg']) && $_GET['msg'] === 'created')
    echo '<div class="alert-banner alert-ok"><i class="fas fa-check-circle"></i>Approval request submitted successfully.</div>';

$pending  = db()->query("SELECT a.*, i.item_name, i.uid, i.status as item_status FROM approvals a JOIN items i ON a.item_id=i.id WHERE a.status='pending' ORDER BY a.created_at DESC");
$history  = db()->query("SELECT a.*, i.item_name, i.uid FROM approvals a JOIN items i ON a.item_id=i.id WHERE a.status<>'pending' ORDER BY a.created_at DESC LIMIT 30");
$all_items = db()->query("SELECT id, item_name, uid FROM items ORDER BY item_name");
$all_locs  = db()->query("SELECT id, location_name FROM locations ORDER BY location_name");
?>

<div class="grid-2">
    <!-- NEW REQUEST FORM -->
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-plus-circle"></i>Create Approval Request</div></div>
        <form method="POST">
            <input type="hidden" name="submit_approval" value="1">
            <div class="form-group"><label class="form-label">Asset *</label>
                <select name="item_id" class="form-control" required>
                    <option value="">— Select Asset —</option>
                    <?php while ($i = $all_items->fetch_assoc()): ?>
                    <option value="<?= $i['id'] ?>"><?= h($i['item_name']) ?> [<?= h($i['uid']) ?>]</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Request Type *</label>
                <select name="request_type" class="form-control" required id="reqType" onchange="toggleFields()">
                    <option value="checkout">Checkout</option>
                    <option value="checkin">Check-In / Return</option>
                    <option value="transfer">Zone Transfer</option>
                    <option value="retirement">Retirement</option>
                    <option value="disposal">Disposal</option>
                    <option value="repair">Send for Repair</option>
                </select>
            </div>
            <div class="form-group" id="fldAssignee"><label class="form-label">Assignee / Person</label><input type="text" name="target_assignee" class="form-control" placeholder="Name of person / department"></div>
            <div id="fldTransfer" style="display:none">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">From Location</label>
                        <select name="from_location" class="form-control">
                            <option value="">—</option>
                            <?php $all_locs->data_seek(0); while ($l=$all_locs->fetch_assoc()) echo '<option value="'.$l['id'].'">'.h($l['location_name']).'</option>'; ?>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">To Location</label>
                        <select name="to_location" class="form-control">
                            <option value="">—</option>
                            <?php $all_locs->data_seek(0); while ($l=$all_locs->fetch_assoc()) echo '<option value="'.$l['id'].'">'.h($l['location_name']).'</option>'; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Reason / Justification</label><textarea name="reason" class="form-control" rows="2" placeholder="Brief reason for this request..."></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i>Submit Request</button>
        </form>
    </div>

    <!-- PENDING -->
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-hourglass-half"></i>Pending Approvals (<?= $pending->num_rows ?>)</div></div>
        <?php if ($pending->num_rows === 0): ?>
        <p style="color:var(--sub);font-size:13px;padding:12px 0"><i class="fas fa-inbox" style="margin-right:8px"></i>No pending approvals.</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Asset</th><th>Type</th><th>Requested By</th><th>Target</th><th>Time</th><th>Actions</th></tr>
            <?php while ($ap = $pending->fetch_assoc()): ?>
            <tr>
                <td><div class="td-name"><?= h($ap['item_name']) ?></div><div class="td-sub"><?= h($ap['uid']) ?></div></td>
                <td><span class="pill pill-blue"><?= h($ap['request_type']) ?></span></td>
                <td style="font-size:12px"><?= h($ap['requested_by']) ?></td>
                <td style="font-size:12px"><?= h($ap['target_assignee'] ?? '—') ?></td>
                <td style="font-size:11px;color:var(--sub)"><?= ago($ap['created_at']) ?></td>
                <td style="display:flex;gap:6px">
                    <a href="?action=approve&id=<?= $ap['id'] ?>&act=yes" class="btn btn-green btn-sm" title="Approve"><i class="fas fa-check"></i></a>
                    <a href="?action=approve&id=<?= $ap['id'] ?>&act=no" class="btn btn-red btn-sm" title="Reject"><i class="fas fa-xmark"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<!-- HISTORY -->
<div class="card">
    <div class="card-hdr"><div class="card-title"><i class="fas fa-history"></i>Approval History (Last 30)</div></div>
    <div class="table-wrap"><table>
        <tr><th>Asset</th><th>Type</th><th>Requested By</th><th>Status</th><th>Reviewed By</th><th>Reviewed At</th><th>Reason</th></tr>
        <?php while ($h = $history->fetch_assoc()): ?>
        <tr>
            <td><div class="td-name"><?= h($h['item_name']) ?></div><div class="td-sub"><?= h($h['uid']) ?></div></td>
            <td><span class="pill pill-purple"><?= h($h['request_type']) ?></span></td>
            <td style="font-size:12px"><?= h($h['requested_by']) ?></td>
            <td><span class="pill <?= $h['status']==='approved'?'pill-green':'pill-red' ?>"><?= h($h['status']) ?></span></td>
            <td style="font-size:12px"><?= h($h['reviewed_by'] ?? '—') ?></td>
            <td style="font-size:11px;color:var(--sub)"><?= $h['reviewed_at'] ? ago($h['reviewed_at']) : '—' ?></td>
            <td style="font-size:11px;color:var(--sub);max-width:200px"><?= h($h['reason'] ?? '—') ?></td>
        </tr>
        <?php endwhile; ?>
    </table></div>
</div>

<script>
function toggleFields() {
    const t = document.getElementById('reqType').value;
    document.getElementById('fldTransfer').style.display = (t === 'transfer') ? '' : 'none';
    document.getElementById('fldAssignee').style.display = (t === 'transfer' || t === 'retirement' || t === 'disposal') ? 'none' : '';
}
toggleFields();
</script>

<?php render_footer(); ?>
