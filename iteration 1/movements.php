<?php
require_once 'core.php';

// Manual zone transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_transfer'])) {
    require_admin();
    $item_id  = (int)$_POST['item_id'];
    $to_loc   = (int)$_POST['to_location_id'];
    $note     = db()->real_escape_string(trim($_POST['notes'] ?? ''));
    $item     = db()->query("SELECT uid, location_id FROM items WHERE id=$item_id")->fetch_assoc();
    if ($item) {
        $from_loc = $item['location_id'];
        db()->query("UPDATE items SET location_id=$to_loc, status='in_stock', last_seen=NOW() WHERE id=$item_id");
        $stmt = db()->prepare("INSERT INTO scan_logs (uid, item_id, from_location_id, to_location_id, reader_id, action_type, performed_by, notes) VALUES (?,?,?,?,'ADMIN','manual_move',?,?)");
        $stmt->bind_param('siiiss', $item['uid'], $item_id, $from_loc, $to_loc, $_SESSION['admin_name'], $note);
        $stmt->execute();
        sys_log('info', 'movements.php', "Manual transfer: item #$item_id from loc $from_loc to $to_loc by {$_SESSION['admin_name']}");
        header("Location: movements.php?msg=transferred"); exit;
    }
}

render_header('Movement History');

if (isset($_GET['msg']) && $_GET['msg'] === 'transferred')
    echo '<div class="alert-banner alert-ok"><i class="fas fa-check-circle"></i>Item transferred successfully.</div>';

// Filters
$filter_action = $_GET['fa'] ?? '';
$filter_item   = trim($_GET['q'] ?? '');
$date_from     = $_GET['df'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to       = $_GET['dt'] ?? date('Y-m-d');

$where = ["DATE(s.scan_time) BETWEEN '".db()->real_escape_string($date_from)."' AND '".db()->real_escape_string($date_to)."'"];
if ($filter_action) $where[] = "s.action_type='".db()->real_escape_string($filter_action)."'";
if ($filter_item)   $where[] = "(i.item_name LIKE '%".db()->real_escape_string($filter_item)."%' OR s.uid LIKE '%".db()->real_escape_string($filter_item)."%')";
$where_sql = 'WHERE '.implode(' AND ', $where);

$logs = db()->query("SELECT s.*, i.item_name, lf.location_name as from_loc, lt.location_name as to_loc
    FROM scan_logs s
    LEFT JOIN items i ON s.item_id = i.id
    LEFT JOIN locations lf ON s.from_location_id = lf.id
    LEFT JOIN locations lt ON s.to_location_id = lt.id
    $where_sql ORDER BY s.id DESC LIMIT 200");

$items_list = db()->query("SELECT id, item_name, uid, location_id FROM items ORDER BY item_name");
$locs_list  = db()->query("SELECT l1.id, l1.location_name, l2.location_name as parent FROM locations l1 LEFT JOIN locations l2 ON l1.parent_id=l2.id ORDER BY l1.zone");
?>

<div class="grid-2">
    <!-- FILTER -->
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-filter"></i>Filter Movements</div></div>
        <form method="GET">
            <div class="form-row">
                <div class="form-group"><label class="form-label">From Date</label><input type="date" name="df" class="form-control" value="<?= h($date_from) ?>"></div>
                <div class="form-group"><label class="form-label">To Date</label><input type="date" name="dt" class="form-control" value="<?= h($date_to) ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Item / UID Search</label><input type="text" name="q" class="form-control" value="<?= h($filter_item) ?>" placeholder="Item name or UID..."></div>
                <div class="form-group"><label class="form-label">Action Type</label>
                    <select name="fa" class="form-control">
                        <option value="">All Actions</option>
                        <?php foreach (['scanned','transfer','checkout','return','manual_move','conflict_suppressed','cycle_count','disposal'] as $a): ?>
                        <option value="<?= $a ?>" <?= $filter_action===$a?'selected':'' ?>><?= ucwords(str_replace('_',' ',$a)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i>Apply Filters</button>
            <a href="movements.php" class="btn btn-dark btn-sm"><i class="fas fa-xmark"></i>Reset</a>
        </form>
    </div>

    <!-- MANUAL TRANSFER -->
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-arrow-right-arrow-left"></i>Manual Zone Transfer</div></div>
        <p style="font-size:12px;color:var(--sub);margin-bottom:14px">Override location for an asset. Creates a movement log entry for audit trail.</p>
        <form method="POST">
            <input type="hidden" name="do_transfer" value="1">
            <div class="form-group"><label class="form-label">Select Asset</label>
                <select name="item_id" class="form-control" required>
                    <option value="">— Choose Asset —</option>
                    <?php while ($i = $items_list->fetch_assoc()): ?>
                    <option value="<?= $i['id'] ?>"><?= h($i['item_name']) ?> [<?= h($i['uid']) ?>]</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Transfer To Location</label>
                <select name="to_location_id" class="form-control" required>
                    <option value="">— Choose Destination —</option>
                    <?php $locs_list->data_seek(0); while ($l = $locs_list->fetch_assoc()): ?>
                    <option value="<?= $l['id'] ?>"><?= $l['parent'] ? h($l['parent']).' › '.h($l['location_name']) : h($l['location_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Reason / Notes</label><input type="text" name="notes" class="form-control" placeholder="Reason for transfer..."></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right-arrow-left"></i>Execute Transfer</button>
        </form>
    </div>
</div>

<!-- MOVEMENT TABLE -->
<div class="card">
    <div class="card-hdr">
        <div class="card-title"><i class="fas fa-route"></i>Movement Log (Last 200 records)</div>
        <span style="font-size:12px;color:var(--sub)"><?= $logs->num_rows ?> records</span>
    </div>
    <div class="table-wrap">
    <table>
        <tr><th>Timestamp</th><th>UID</th><th>Item</th><th>Action</th><th>From</th><th>To</th><th>Reader / By</th><th>Notes</th></tr>
        <?php while ($s = $logs->fetch_assoc()):
            $pc = ['transfer'=>'pill-blue','checkout'=>'pill-cyan','return'=>'pill-green','manual_move'=>'pill-purple','scanned'=>'pill-gray','conflict_suppressed'=>'pill-red','cycle_count'=>'pill-yellow','disposal'=>'pill-red'];
        ?>
        <tr>
            <td style="font-size:11px;font-family:'DM Mono',monospace;white-space:nowrap"><?= h($s['scan_time']) ?></td>
            <td style="font-family:'DM Mono',monospace;font-size:11px"><?= h($s['uid']) ?></td>
            <td><div class="td-name"><?= h($s['item_name'] ?? '—') ?></div></td>
            <td><span class="pill <?= $pc[$s['action_type']]??'pill-gray' ?>"><?= h($s['action_type']) ?></span></td>
            <td style="font-size:12px;color:var(--sub)"><?= h($s['from_loc'] ?? '—') ?></td>
            <td style="font-size:12px"><?= h($s['to_loc'] ?? '—') ?></td>
            <td style="font-size:11px;color:var(--sub)"><?= h($s['performed_by'] ?? $s['reader_id']) ?></td>
            <td style="font-size:11px;color:var(--sub)"><?= h($s['notes'] ?? '—') ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    </div>
</div>

<?php render_footer(); ?>
