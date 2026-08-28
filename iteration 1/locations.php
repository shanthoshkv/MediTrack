<?php
require_once 'core.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_location'])) {
    require_admin();
    $id     = (int)($_POST['id'] ?? 0);
    $name   = db()->real_escape_string(trim($_POST['location_name']));
    $code   = db()->real_escape_string(trim($_POST['location_code']));
    $type   = db()->real_escape_string($_POST['location_type']);
    $zone   = db()->real_escape_string(trim($_POST['zone']));
    $reader = db()->real_escape_string(trim($_POST['reader_id']));
    $apikey = db()->real_escape_string(trim($_POST['api_key']));
    $parent = (int)($_POST['parent_id'] ?? 0) ?: 'NULL';
    $cap    = (int)($_POST['capacity'] ?? 0);
    $notes  = db()->real_escape_string(trim($_POST['notes'] ?? ''));

    if ($id > 0) {
        db()->query("UPDATE locations SET location_name='$name', location_code=NULLIF('$code',''), location_type='$type', zone='$zone', reader_id=NULLIF('$reader',''), api_key=NULLIF('$apikey',''), parent_id=$parent, capacity=$cap, notes=NULLIF('$notes','') WHERE id=$id");
        sys_log('info', 'locations.php', "Location #$id updated");
    } else {
        db()->query("INSERT INTO locations (location_name, location_code, location_type, zone, reader_id, api_key, parent_id, capacity, notes) VALUES ('$name', NULLIF('$code',''), '$type', '$zone', NULLIF('$reader',''), NULLIF('$apikey',''), $parent, $cap, NULLIF('$notes',''))");
        sys_log('info', 'locations.php', "New location created: $name");
    }
    header("Location: locations.php"); exit;
}

if (isset($_GET['delete'])) {
    require_admin();
    $id = (int)$_GET['delete'];
    $items = (int)db()->query("SELECT COUNT(*) as c FROM items WHERE location_id=$id")->fetch_assoc()['c'];
    if ($items === 0) {
        db()->query("DELETE FROM locations WHERE id=$id");
        sys_log('info', 'locations.php', "Location #$id deleted");
    }
    header("Location: locations.php"); exit;
}

render_header('Location Hierarchy');

$edit_id = (int)($_GET['edit'] ?? 0);
$edit = $edit_id > 0 ? db()->query("SELECT * FROM locations WHERE id=$edit_id")->fetch_assoc() : null;

$locations = db()->query("SELECT l1.*, l2.location_name as parent_name,
    (SELECT COUNT(*) FROM items WHERE location_id=l1.id) as item_count,
    (SELECT COUNT(*) FROM locations WHERE parent_id=l1.id) as child_count
    FROM locations l1
    LEFT JOIN locations l2 ON l1.parent_id=l2.id
    ORDER BY l1.zone, l2.location_name, l1.location_name");

$parents = db()->query("SELECT id, location_name, zone FROM locations ORDER BY zone, location_name");
?>

<div class="grid-2">
    <!-- FORM -->
    <div class="card">
        <div class="card-hdr">
            <div class="card-title"><i class="fas fa-<?= $edit ? 'pen' : 'plus-circle' ?>"></i><?= $edit ? 'Edit Location' : 'Add Location' ?></div>
            <?php if ($edit) echo '<a href="locations.php" class="btn btn-dark btn-sm"><i class="fas fa-plus"></i>New</a>'; ?>
        </div>
        <form method="POST">
            <input type="hidden" name="save_location" value="1">
            <input type="hidden" name="id" value="<?= $edit ? $edit['id'] : 0 ?>">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Location Name *</label><input type="text" name="location_name" class="form-control" required value="<?= h($edit['location_name'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">Location Code</label><input type="text" name="location_code" class="form-control" value="<?= h($edit['location_code'] ?? '') ?>" placeholder="e.g. ZN-A-R1"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Type</label>
                    <select name="location_type" class="form-control">
                        <?php foreach (['building','floor','ward','room','bed','storage','pharmacy','lab','ot','icu','emergency','blood_bank','cssd','reception','corridor'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($edit['location_type']??'')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Zone</label><input type="text" name="zone" class="form-control" value="<?= h($edit['zone'] ?? 'General') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Parent Location</label>
                <select name="parent_id" class="form-control">
                    <option value="">— Top Level —</option>
                    <?php while ($p = $parents->fetch_assoc()):
                        if ($edit && $p['id'] == $edit['id']) continue; ?>
                    <option value="<?= $p['id'] ?>" <?= ($edit['parent_id']??'') == $p['id'] ? 'selected' : '' ?>>[<?= h($p['zone']) ?>] <?= h($p['location_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">RFID Reader ID</label><input type="text" name="reader_id" class="form-control" value="<?= h($edit['reader_id'] ?? '') ?>" placeholder="e.g. ESP32_A1"></div>
                <div class="form-group"><label class="form-label">API Key</label><input type="text" name="api_key" class="form-control" value="<?= h($edit['api_key'] ?? '') ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Capacity (items)</label><input type="number" name="capacity" class="form-control" min="0" value="<?= h($edit['capacity'] ?? 0) ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" value="<?= h($edit['notes'] ?? '') ?>"></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i><?= $edit ? 'Update Location' : 'Add Location' ?></button>
        </form>
    </div>

    <!-- LOCATION LIST -->
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-sitemap"></i>Location Hierarchy (<?= $locations->num_rows ?>)</div></div>
        <div class="table-wrap"><table>
            <tr><th>Name / Code</th><th>Type</th><th>Zone</th><th>Parent</th><th>Reader</th><th>Items</th><th>Actions</th></tr>
            <?php $locations->data_seek(0); while ($l = $locations->fetch_assoc()):
                $is_offline = $l['reader_id'] && (!$l['last_heartbeat'] || strtotime($l['last_heartbeat']) < (time()-120));
            ?>
            <tr>
                <td>
                    <div class="td-name"><?= h($l['location_name']) ?></div>
                    <div class="td-sub" style="font-family:'DM Mono',monospace"><?= h($l['location_code'] ?? '—') ?></div>
                </td>
                <td><span class="pill pill-purple"><?= h($l['location_type']) ?></span></td>
                <td style="font-size:12px"><?= h($l['zone']) ?></td>
                <td style="font-size:12px;color:var(--sub)"><?= h($l['parent_name'] ?? '— Root —') ?></td>
                <td>
                    <?php if ($l['reader_id']): ?>
                    <span class="pill <?= $is_offline ? 'pill-red' : 'pill-green' ?>"><?= h($l['reader_id']) ?></span>
                    <?php else: echo '<span style="color:var(--muted);font-size:11px">—</span>'; endif; ?>
                </td>
                <td style="font-family:'DM Mono',monospace;font-size:13px"><?= $l['item_count'] ?></td>
                <td style="display:flex;gap:6px">
                    <a href="?edit=<?= $l['id'] ?>" class="btn btn-dark btn-sm"><i class="fas fa-pen"></i></a>
                    <?php if ($l['item_count'] == 0 && $l['child_count'] == 0): ?>
                    <a href="?delete=<?= $l['id'] ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete this location?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table></div>
    </div>
</div>

<?php render_footer(); ?>
