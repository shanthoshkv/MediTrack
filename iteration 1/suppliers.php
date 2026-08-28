<?php
require_once 'core.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_supplier'])) {
    require_admin();
    $id     = (int)($_POST['id']??0);
    $name   = db()->real_escape_string(trim($_POST['name']));
    $cp     = db()->real_escape_string(trim($_POST['contact_person']??''));
    $email  = db()->real_escape_string(trim($_POST['email']??''));
    $phone  = db()->real_escape_string(trim($_POST['phone']??''));
    $addr   = db()->real_escape_string(trim($_POST['address']??''));
    $city   = db()->real_escape_string(trim($_POST['city']??''));
    $gst    = db()->real_escape_string(trim($_POST['gst_number']??''));
    $type   = db()->real_escape_string($_POST['supplier_type']??'general');
    $rating = max(1,min(5,(int)($_POST['rating']??3)));
    $notes  = db()->real_escape_string(trim($_POST['notes']??''));

    if ($id > 0) {
        db()->query("UPDATE suppliers SET name='$name',contact_person='$cp',email='$email',phone='$phone',address='$addr',city='$city',gst_number='$gst',supplier_type='$type',rating=$rating,notes='$notes' WHERE id=$id");
        sys_log('info','suppliers.php',"Supplier #$id updated by {$_SESSION['admin_name']}");
    } else {
        db()->query("INSERT INTO suppliers (name,contact_person,email,phone,address,city,gst_number,supplier_type,rating,notes) VALUES ('$name','$cp','$email','$phone','$addr','$city','$gst','$type',$rating,'$notes')");
        sys_log('info','suppliers.php',"New supplier added: $name");
    }
    header("Location: suppliers.php?msg=saved"); exit;
}

if (isset($_GET['toggle'])) {
    require_admin();
    $id = (int)$_GET['toggle'];
    db()->query("UPDATE suppliers SET is_active = 1-is_active WHERE id=$id");
    header("Location: suppliers.php"); exit;
}

if (isset($_GET['delete'])) {
    require_admin();
    $id = (int)$_GET['delete'];
    $used = (int)db()->query("SELECT COUNT(*) as c FROM items WHERE supplier_id=$id")->fetch_assoc()['c'];
    if ($used === 0) { db()->query("DELETE FROM suppliers WHERE id=$id"); sys_log('info','suppliers.php',"Supplier #$id deleted"); }
    header("Location: suppliers.php"); exit;
}

render_header('Suppliers & Vendors');

$msg = $_GET['msg']??'';
if ($msg==='saved') echo '<div class="alert-banner alert-ok"><i class="fas fa-check-circle"></i>Supplier saved successfully.</div>';

$edit_id = (int)($_GET['edit']??0);
$edit = $edit_id > 0 ? db()->query("SELECT * FROM suppliers WHERE id=$edit_id")->fetch_assoc() : null;
$suppliers = db()->query("SELECT s.*,
    (SELECT COUNT(*) FROM items WHERE supplier_id=s.id) as item_count,
    (SELECT COALESCE(SUM(unit_cost*quantity),0) FROM items WHERE supplier_id=s.id) as total_value
    FROM suppliers s ORDER BY s.is_active DESC, s.name");

$types = ['medical_equipment'=>'Medical Equipment','pharmaceuticals'=>'Pharmaceuticals',
          'consumables'=>'Consumables','general'=>'General','lab_reagents'=>'Lab Reagents','it_equipment'=>'IT Equipment'];
?>

<div class="grid-2" style="margin-bottom:0">
<div class="card">
    <div class="card-hdr">
        <div class="card-title"><i class="fas fa-<?= $edit?'pen':'plus-circle' ?>"></i><?= $edit?'Edit Supplier':'Add Supplier' ?></div>
        <?php if ($edit): ?><a href="suppliers.php" class="btn btn-dark btn-sm"><i class="fas fa-plus"></i>New</a><?php endif; ?>
    </div>
    <form method="POST">
        <input type="hidden" name="save_supplier" value="1">
        <input type="hidden" name="id" value="<?= $edit?$edit['id']:0 ?>">
        <div class="form-row">
            <div class="form-group"><label class="form-label">Supplier Name *</label><input type="text" name="name" class="form-control" required value="<?= h($edit['name']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Contact Person</label><input type="text" name="contact_person" class="form-control" value="<?= h($edit['contact_person']??'') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= h($edit['email']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= h($edit['phone']??'') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= h($edit['city']??'') ?>"></div>
            <div class="form-group"><label class="form-label">GST Number</label><input type="text" name="gst_number" class="form-control" value="<?= h($edit['gst_number']??'') ?>" placeholder="e.g. 27AAPFU0939F1ZV"></div>
        </div>
        <div class="form-group"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?= h($edit['address']??'') ?>"></div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Supplier Type</label>
                <select name="supplier_type" class="form-control">
                    <?php foreach ($types as $val=>$lbl): ?>
                    <option value="<?= $val ?>" <?= ($edit['supplier_type']??'general')===$val?'selected':'' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Rating (1–5)</label>
                <select name="rating" class="form-control">
                    <?php for ($i=5;$i>=1;$i--): ?>
                    <option value="<?= $i ?>" <?= ($edit['rating']??3)==$i?'selected':'' ?>><?= str_repeat('★',$i).str_repeat('☆',5-$i) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= h($edit['notes']??'') ?></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i><?= $edit?'Update Supplier':'Add Supplier' ?></button>
    </form>
</div>

<div class="card">
    <div class="card-hdr"><div class="card-title"><i class="fas fa-truck-medical"></i>All Suppliers (<?= $suppliers->num_rows ?>)</div></div>
    <div class="table-wrap"><table>
        <tr><th>Name</th><th>Type</th><th>Contact</th><th>Rating</th><th>Items</th><th>Value</th><th>Status</th><th>Actions</th></tr>
        <?php $suppliers->data_seek(0); while ($s=$suppliers->fetch_assoc()): ?>
        <tr>
            <td>
                <div class="td-name"><?= h($s['name']) ?></div>
                <div class="td-sub"><?= h($s['city']??'') ?><?= $s['gst_number']?' · '.h($s['gst_number']):'' ?></div>
            </td>
            <td><span class="pill pill-purple"><?= h(str_replace('_',' ',$s['supplier_type'])) ?></span></td>
            <td style="font-size:12px">
                <?php if ($s['contact_person']) echo h($s['contact_person']).'<br>'; ?>
                <?php if ($s['phone']) echo '<span style="color:var(--sub)">'.h($s['phone']).'</span>'; ?>
            </td>
            <td style="color:#fcd34d;font-size:13px"><?= str_repeat('★',(int)$s['rating']) ?></td>
            <td style="font-family:'DM Mono',monospace"><?= $s['item_count'] ?></td>
            <td style="font-size:12px;font-family:'DM Mono',monospace"><?= fmt_currency((float)$s['total_value']) ?></td>
            <td><span class="pill <?= $s['is_active']?'pill-green':'pill-gray' ?>"><?= $s['is_active']?'Active':'Inactive' ?></span></td>
            <td style="display:flex;gap:5px;flex-wrap:wrap">
                <a href="?edit=<?= $s['id'] ?>" class="btn btn-dark btn-sm"><i class="fas fa-pen"></i></a>
                <a href="?toggle=<?= $s['id'] ?>" class="btn btn-yellow btn-sm" title="Toggle active"><i class="fas fa-power-off"></i></a>
                <?php if ($s['item_count']==0): ?>
                <a href="?delete=<?= $s['id'] ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete supplier?')"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table></div>
</div>
</div>

<?php render_footer(); ?>