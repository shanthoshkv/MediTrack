<?php
require_once 'core.php';

// Schedule maintenance
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['schedule_maint'])) {
    require_admin();
    $item_id  = (int)$_POST['item_id'];
    $type     = db()->real_escape_string($_POST['maintenance_type']);
    $sched    = db()->real_escape_string($_POST['scheduled_date']??'') ?: null;
    $by       = db()->real_escape_string(trim($_POST['performed_by']??''));
    $vendor   = db()->real_escape_string(trim($_POST['vendor_name']??''));
    $next     = db()->real_escape_string($_POST['next_due_date']??'') ?: null;
    $notes    = db()->real_escape_string(trim($_POST['notes']??''));
    $creator  = db()->real_escape_string($_SESSION['admin_name']);
    db()->query("INSERT INTO maintenance_logs (item_id,maintenance_type,scheduled_date,performed_by,vendor_name,next_due_date,notes,created_by,status)
        VALUES ($item_id,'$type',".($sched?"'$sched'":'NULL').",'$by','$vendor',".($next?"'$next'":'NULL').",'$notes','$creator','scheduled')");
    // Update item's next service date
    if ($sched) db()->query("UPDATE items SET next_service_date='$sched' WHERE id=$item_id");
    sys_log('info','maintenance.php',"Maintenance scheduled for item #$item_id by $creator");
    header("Location: maintenance.php?msg=scheduled"); exit;
}

// Mark complete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['complete_maint'])) {
    require_admin();
    $id       = (int)$_POST['maint_id'];
    $pdate    = db()->real_escape_string($_POST['performed_date']??date('Y-m-d'));
    $findings = db()->real_escape_string(trim($_POST['findings']??''));
    $cost     = (float)($_POST['cost']??0);
    $certno   = db()->real_escape_string(trim($_POST['certificate_no']??''));
    $next     = db()->real_escape_string($_POST['next_due_date']??'') ?: null;
    db()->query("UPDATE maintenance_logs SET status='completed',performed_date='$pdate',findings='$findings',cost=$cost,certificate_no='$certno',next_due_date=".($next?"'$next'":'NULL')." WHERE id=$id");
    // Get item id to update
    $iid = (int)db()->query("SELECT item_id FROM maintenance_logs WHERE id=$id")->fetch_assoc()['item_id'];
    db()->query("UPDATE items SET last_service_date='$pdate',status=IF(status='under_repair','in_stock',status)".($next?",next_service_date='$next'":'')." WHERE id=$iid");
    sys_log('info','maintenance.php',"Maintenance #$id completed by {$_SESSION['admin_name']}");
    header("Location: maintenance.php?msg=completed"); exit;
}

// Cancel
if (isset($_GET['cancel'])) {
    require_admin();
    $id = (int)$_GET['cancel'];
    db()->query("UPDATE maintenance_logs SET status='cancelled' WHERE id=$id");
    header("Location: maintenance.php"); exit;
}

render_header('Equipment Maintenance');

$msg = $_GET['msg']??'';
if ($msg==='scheduled') echo '<div class="alert-banner alert-ok"><i class="fas fa-check-circle"></i>Maintenance scheduled successfully.</div>';
if ($msg==='completed') echo '<div class="alert-banner alert-ok"><i class="fas fa-check-circle"></i>Maintenance marked as completed.</div>';

// Stats
$overdue_cnt  = (int)db()->query("SELECT COUNT(*) as c FROM maintenance_logs WHERE status='overdue'")->fetch_assoc()['c'];
$scheduled_cnt= (int)db()->query("SELECT COUNT(*) as c FROM maintenance_logs WHERE status='scheduled'")->fetch_assoc()['c'];
$due_30       = (int)db()->query("SELECT COUNT(*) as c FROM maintenance_logs WHERE status='scheduled' AND scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetch_assoc()['c'];

$items_list   = db()->query("SELECT id,item_name,uid,department FROM items WHERE status NOT IN ('disposed','retired') ORDER BY item_name");
$active_maint = db()->query("SELECT ml.*,i.item_name,i.uid,i.department FROM maintenance_logs ml JOIN items i ON ml.item_id=i.id WHERE ml.status IN ('scheduled','overdue','in_progress') ORDER BY FIELD(ml.status,'overdue','in_progress','scheduled'),ml.scheduled_date");
$history      = db()->query("SELECT ml.*,i.item_name,i.uid FROM maintenance_logs ml JOIN items i ON ml.item_id=i.id WHERE ml.status IN ('completed','cancelled') ORDER BY ml.performed_date DESC LIMIT 50");

// AMC tracking: items with warranty expiring in next 90 days
$warranty_due = db()->query("SELECT id,item_name,uid,brand,warranty_expiry,department FROM items WHERE warranty_expiry IS NOT NULL AND warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 90 DAY) ORDER BY warranty_expiry");
?>

<!-- STATS -->
<div class="grid-4" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-label">Overdue</div>
        <div class="stat-value" style="color:var(--red)"><?= $overdue_cnt ?></div>
        <div class="stat-sub">Maintenance overdue</div>
        <i class="fas fa-triangle-exclamation stat-icon" style="color:var(--red)"></i>
    </div>
    <div class="stat-card">
        <div class="stat-label">Scheduled</div>
        <div class="stat-value" style="color:var(--yellow)"><?= $scheduled_cnt ?></div>
        <div class="stat-sub">Upcoming maintenance</div>
        <i class="fas fa-calendar stat-icon" style="color:var(--yellow)"></i>
    </div>
    <div class="stat-card">
        <div class="stat-label">Due in 30 Days</div>
        <div class="stat-value" style="color:var(--cyan)"><?= $due_30 ?></div>
        <div class="stat-sub">Action needed soon</div>
        <i class="fas fa-clock stat-icon" style="color:var(--cyan)"></i>
    </div>
    <div class="stat-card">
        <div class="stat-label">Warranty Expiring</div>
        <div class="stat-value" style="color:var(--yellow)"><?= $warranty_due->num_rows ?></div>
        <div class="stat-sub">Within 90 days</div>
        <i class="fas fa-file-shield stat-icon" style="color:var(--yellow)"></i>
    </div>
</div>

<div class="grid-2">
    <!-- SCHEDULE FORM -->
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-plus-circle"></i>Schedule Maintenance</div></div>
        <form method="POST">
            <input type="hidden" name="schedule_maint" value="1">
            <div class="form-group"><label class="form-label">Equipment / Asset *</label>
                <select name="item_id" class="form-control" required>
                    <option value="">— Select Asset —</option>
                    <?php while($i=$items_list->fetch_assoc()): ?>
                    <option value="<?= $i['id'] ?>"><?= h($i['item_name']) ?> [<?= h($i['uid']) ?>] — <?= h($i['department']??'N/A') ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Maintenance Type *</label>
                    <select name="maintenance_type" class="form-control" required>
                        <option value="preventive">🔧 Preventive (PM)</option>
                        <option value="corrective">⚠️ Corrective (Repair)</option>
                        <option value="calibration">📐 Calibration</option>
                        <option value="inspection">🔍 Inspection</option>
                        <option value="sterilization">♻️ Sterilization Cycle</option>
                        <option value="amc">📋 AMC Service</option>
                        <option value="installation">🏗️ Installation / Commissioning</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Scheduled Date</label><input type="date" name="scheduled_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Performed By</label><input type="text" name="performed_by" class="form-control" placeholder="Engineer / Technician name"></div>
                <div class="form-group"><label class="form-label">Vendor / Agency</label><input type="text" name="vendor_name" class="form-control" placeholder="Service vendor name"></div>
            </div>
            <div class="form-group"><label class="form-label">Next Due Date</label><input type="date" name="next_due_date" class="form-control"></div>
            <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Details, checklist items, parts required..."></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus"></i>Schedule Maintenance</button>
        </form>
    </div>

    <!-- WARRANTY EXPIRY -->
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-file-shield"></i>Warranty / AMC Expiring (90 days)</div></div>
        <?php if ($warranty_due->num_rows===0): ?>
        <p style="color:var(--sub);font-size:13px"><i class="fas fa-circle-check" style="color:var(--green);margin-right:8px"></i>No warranties expiring within 90 days.</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Asset</th><th>Brand</th><th>Department</th><th>Warranty Expiry</th><th>Days Left</th></tr>
            <?php while($w=$warranty_due->fetch_assoc()):
                $days_left = (int)ceil((strtotime($w['warranty_expiry'])-time())/86400);
            ?>
            <tr>
                <td><div class="td-name"><?= h($w['item_name']) ?></div><div class="td-sub"><?= h($w['uid']) ?></div></td>
                <td style="font-size:12px"><?= h($w['brand']??'—') ?></td>
                <td style="font-size:12px"><?= h($w['department']??'—') ?></td>
                <td style="font-size:12px"><?= date('d M Y',strtotime($w['warranty_expiry'])) ?></td>
                <td><span class="pill <?= $days_left<=30?'pill-red':($days_left<=60?'pill-yellow':'pill-green') ?>"><?= $days_left ?>d</span></td>
            </tr>
            <?php endwhile; ?>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<!-- ACTIVE/OVERDUE MAINTENANCE -->
<div class="card" style="margin-bottom:18px">
    <div class="card-hdr"><div class="card-title"><i class="fas fa-wrench"></i>Active & Overdue Maintenance (<?= $active_maint->num_rows ?>)</div></div>
    <div class="table-wrap"><table>
        <tr><th>Asset</th><th>Type</th><th>Status</th><th>Scheduled</th><th>Performed By</th><th>Vendor</th><th>Next Due</th><th>Actions</th></tr>
        <?php while($m=$active_maint->fetch_assoc()): ?>
        <tr>
            <td><div class="td-name"><?= h($m['item_name']) ?></div><div class="td-sub"><?= h($m['uid']) ?> · <?= h($m['department']??'') ?></div></td>
            <td><span class="pill pill-purple"><?= h(str_replace('_',' ',$m['maintenance_type'])) ?></span></td>
            <td><span class="pill <?= $m['status']==='overdue'?'pill-red':($m['status']==='in_progress'?'pill-yellow':'pill-blue') ?>"><?= strtoupper($m['status']) ?></span></td>
            <td style="font-size:12px"><?= $m['scheduled_date']?date('d M Y',strtotime($m['scheduled_date'])):'—' ?></td>
            <td style="font-size:12px"><?= h($m['performed_by']??'TBD') ?></td>
            <td style="font-size:12px"><?= h($m['vendor_name']??'—') ?></td>
            <td style="font-size:12px;<?= $m['next_due_date']&&strtotime($m['next_due_date'])<strtotime('+30 days')?'color:var(--yellow)':'' ?>"><?= $m['next_due_date']?date('d M Y',strtotime($m['next_due_date'])):'—' ?></td>
            <td style="display:flex;gap:6px">
                <button onclick="completeModal(<?= $m['id'] ?>,<?= $m['item_id'] ?>)" class="btn btn-green btn-sm"><i class="fas fa-check"></i>Complete</button>
                <a href="?cancel=<?= $m['id'] ?>" class="btn btn-red btn-sm" onclick="return confirm('Cancel this schedule?')"><i class="fas fa-xmark"></i></a>
            </td>
        </tr>
        <?php endwhile; ?>
        <?php if($active_maint->num_rows===0): ?><tr><td colspan="8" style="text-align:center;color:var(--sub);padding:24px"><i class="fas fa-check-circle" style="color:var(--green)"></i> No active maintenance tasks.</td></tr><?php endif; ?>
    </table></div>
</div>

<!-- HISTORY -->
<div class="card">
    <div class="card-hdr"><div class="card-title"><i class="fas fa-history"></i>Maintenance History (Last 50)</div></div>
    <div class="table-wrap"><table>
        <tr><th>Asset</th><th>Type</th><th>Status</th><th>Performed Date</th><th>Cost</th><th>Certificate</th><th>Findings</th><th>Next Due</th></tr>
        <?php while($h=$history->fetch_assoc()): ?>
        <tr>
            <td><div class="td-name"><?= h($h['item_name']) ?></div><div class="td-sub"><?= h($h['uid']) ?></div></td>
            <td><span class="pill pill-gray"><?= h(str_replace('_',' ',$h['maintenance_type'])) ?></span></td>
            <td><span class="pill <?= $h['status']==='completed'?'pill-green':'pill-gray' ?>"><?= strtoupper($h['status']) ?></span></td>
            <td style="font-size:12px"><?= $h['performed_date']?date('d M Y',strtotime($h['performed_date'])):'—' ?></td>
            <td style="font-size:12px">₹<?= number_format($h['cost'],2) ?></td>
            <td style="font-family:'DM Mono',monospace;font-size:11px"><?= h($h['certificate_no']??'—') ?></td>
            <td style="font-size:11px;color:var(--sub);max-width:160px"><?= h($h['findings']??'—') ?></td>
            <td style="font-size:12px"><?= $h['next_due_date']?date('d M Y',strtotime($h['next_due_date'])):'—' ?></td>
        </tr>
        <?php endwhile; ?>
    </table></div>
</div>

<!-- COMPLETE MODAL -->
<div id="completeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:500;align-items:center;justify-content:center">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;padding:28px;max-width:480px;width:90%">
        <h3 style="margin-bottom:16px"><i class="fas fa-check-circle" style="color:var(--green)"></i> Complete Maintenance</h3>
        <form method="POST">
            <input type="hidden" name="complete_maint" value="1">
            <input type="hidden" name="maint_id" id="c_maint_id">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Performed Date *</label><input type="date" name="performed_date" id="c_pdate" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
                <div class="form-group"><label class="form-label">Cost (₹)</label><input type="number" name="cost" class="form-control" step="0.01" min="0" value="0"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Certificate / Report No.</label><input type="text" name="certificate_no" class="form-control" style="font-family:'DM Mono',monospace" placeholder="Optional certificate no."></div>
                <div class="form-group"><label class="form-label">Next Due Date</label><input type="date" name="next_due_date" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Findings / Remarks</label><textarea name="findings" class="form-control" rows="3" placeholder="Work done, parts replaced, observations..."></textarea></div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-green"><i class="fas fa-check"></i>Mark Completed</button>
                <button type="button" onclick="document.getElementById('completeModal').style.display='none'" class="btn btn-dark">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>function completeModal(mid,iid){document.getElementById('c_maint_id').value=mid;document.getElementById('completeModal').style.display='flex';}</script>

<?php render_footer(); ?>