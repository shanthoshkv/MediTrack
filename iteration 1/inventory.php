<?php
require_once 'core.php';
refresh_lifecycles();

$action = $_GET['action'] ?? 'list';

// CSV EXPORT
if ($action==='export_csv') {
    require_admin();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="meditrack_inventory_'.date('Ymd_His').'.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output','w');
    fputcsv($out,['UID','SKU','SerialNumber','ItemName','Brand','ModelNumber','ItemType','Category','Quantity','UnitCost_INR','Department','Location','Status','Lifecycle','ConditionGrade','AssignedTo','ManufactureDate','ExpiryDate','WarrantyExpiry','PurchaseDate','IsPerishable','IsHazardous','IsControlledDrug','RequiresSterilization','Notes']);
    $res = db()->query("SELECT i.*, l.location_name FROM items i LEFT JOIN locations l ON i.location_id=l.id ORDER BY i.id");
    while($r=$res->fetch_assoc()) fputcsv($out,[$r['uid'],$r['sku'],$r['serial_number'],$r['item_name'],$r['brand'],$r['model_number'],$r['item_type'],$r['item_category'],$r['quantity'],$r['unit_cost'],$r['department'],$r['location_name'],$r['status'],$r['lifecycle'],$r['condition_grade'],$r['assigned_to'],$r['manufacture_date'],$r['expiry_date'],$r['warranty_expiry'],$r['purchase_date'],$r['is_perishable'],$r['is_hazardous'],$r['is_controlled_drug'],$r['requires_sterilization'],$r['notes']]);
    fclose($out); exit;
}

// PDF/PRINT EXPORT
if ($action==='export_pdf') {
    require_admin();
    render_header('Inventory Audit Report',true);
    echo '<div style="padding:20px"><h2 style="margin-bottom:4px">Inventory Audit Report</h2>';
    echo '<p style="color:var(--sub);margin-bottom:20px;font-size:13px">Generated: '.date('d M Y, H:i:s').' IST &nbsp;|&nbsp; By: '.h($_SESSION['admin_name']).'</p>';
    echo '<div class="table-wrap"><table><tr><th>UID</th><th>SKU</th><th>Item Name</th><th>Brand</th><th>Category</th><th>Qty</th><th>Dept</th><th>Location</th><th>Status</th><th>Lifecycle</th><th>Expiry</th><th>Unit Cost</th></tr>';
    $res = db()->query("SELECT i.*, l.location_name FROM items i LEFT JOIN locations l ON i.location_id=l.id ORDER BY i.item_category, i.item_name");
    while($r=$res->fetch_assoc()) echo '<tr><td>'.h($r['uid']).'</td><td>'.h($r['sku']).'</td><td>'.h($r['item_name']).'</td><td>'.h($r['brand']).'</td><td>'.h($r['item_category']).'</td><td>'.$r['quantity'].'</td><td>'.h($r['department']).'</td><td>'.h($r['location_name']).'</td><td>'.h($r['status']).'</td><td>'.h($r['lifecycle']).'</td><td>'.($r['expiry_date']?:'—').'</td><td>₹'.number_format($r['unit_cost'],2).'</td></tr>';
    echo '</table></div></div><script>window.onload=function(){window.print();}</script>';
    render_footer(); exit;
}

// CSV IMPORT
if ($action==='import_csv' && $_SERVER['REQUEST_METHOD']==='POST') {
    require_admin();
    $imported=0; $skipped=0;
    if (is_uploaded_file($_FILES['csv']['tmp_name']??'')) {
        $file = fopen($_FILES['csv']['tmp_name'],'r');
        fgetcsv($file);
        $stmt = db()->prepare("INSERT IGNORE INTO items (uid,sku,serial_number,item_name,brand,model_number,item_type,item_category,quantity,unit_cost,unit_price,department,expiry_date,warranty_expiry,manufacture_date,purchase_date,is_perishable,is_hazardous,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),?,?,?)");
        while(($row=fgetcsv($file))!==false){
            if(count($row)<13){$skipped++;continue;}
            [$uid,$sku,$serial,$name,$brand,$model,$type,$cat,$qty,$cost,$price,$dept,$expiry,$warranty,$mfg,$purch,$perish,$hazard,$notes]=array_pad($row,19,'');
            if(!trim($uid)||!trim($name)){$skipped++;continue;}
            $stmt->bind_param('ssssssssiidsssssiis',trim($uid),trim($sku),trim($serial),trim($name),trim($brand),trim($model),trim($type),trim($cat),(int)$qty,(float)$cost,(float)$price,trim($dept),trim($expiry),trim($warranty),trim($mfg),trim($purch),(int)$perish,(int)$hazard,trim($notes));
            $stmt->execute()?$imported++:$skipped++;
        }
        fclose($file);
        sys_log('info','inventory.php',"CSV import: $imported ok, $skipped skipped");
        header("Location: inventory.php?action=list&msg=import_ok&imported=$imported&skipped=$skipped"); exit;
    }
    header("Location: inventory.php?action=bulk&err=no_file"); exit;
}

// SAVE ITEM (ADD/EDIT)
if ($action==='save_item' && $_SERVER['REQUEST_METHOD']==='POST') {
    require_admin();
    $id = (int)($_POST['id']??0);
    $uid      = strtoupper(trim($_POST['uid']??''));
    $name     = trim($_POST['item_name']??'');
    $cat      = trim($_POST['item_category']??'');

    // Mandatory expiry check
    $cat_req_expiry = (int)db()->query("SELECT requires_expiry FROM categories WHERE name='".db()->real_escape_string($cat)."' LIMIT 1")->fetch_assoc()['requires_expiry'];
    $expiry_val = trim($_POST['expiry_date']??'');
    if ($cat_req_expiry && !$expiry_val) {
        render_header('Register Asset');
        echo '<div class="alert-banner alert-err"><i class="fas fa-circle-exclamation"></i>Expiry date is MANDATORY for category: '.h($cat).'</div>';
        // fall through to show form again
        $_GET['action'] = 'add';
        goto show_form;
    }

    if (!$uid || !$name) {
        header("Location: inventory.php?action=add&err=missing_fields"); exit;
    }

    $fields = [
        'uid'                  => $uid,
        'sku'                  => trim($_POST['sku']??'')?:null,
        'serial_number'        => trim($_POST['serial_number']??'')?:null,
        'item_name'            => $name,
        'brand'                => trim($_POST['brand']??'')?:null,
        'model_number'         => trim($_POST['model_number']??'')?:null,
        'item_type'            => trim($_POST['item_type']??''),
        'item_category'        => $cat,
        'quantity'             => max(0,(int)($_POST['quantity']??1)),
        'min_stock_level'      => max(0,(int)($_POST['min_stock_level']??0)),
        'unit_cost'            => max(0,(float)($_POST['unit_cost']??0)),
        'unit_price'           => max(0,(float)($_POST['unit_price']??0)),
        'department'           => trim($_POST['department']??'')?:null,
        'location_id'          => ((int)($_POST['location_id']??0))?:null,
        'assigned_to'          => trim($_POST['assigned_to']??'')?:null,
        'lifecycle'            => $_POST['lifecycle']??'active',
        'status'               => $_POST['status']??'in_stock',
        'condition_grade'      => $_POST['condition_grade']??'new',
        'expiry_date'          => $expiry_val?:null,
        'warranty_expiry'      => trim($_POST['warranty_expiry']??'')?:null,
        'manufacture_date'     => trim($_POST['manufacture_date']??'')?:null,
        'purchase_date'        => trim($_POST['purchase_date']??'')?:null,
        'next_service_date'    => trim($_POST['next_service_date']??'')?:null,
        'is_perishable'        => isset($_POST['is_perishable'])?1:0,
        'is_hazardous'         => isset($_POST['is_hazardous'])?1:0,
        'is_controlled_drug'   => isset($_POST['is_controlled_drug'])?1:0,
        'requires_sterilization'=> isset($_POST['requires_sterilization'])?1:0,
        'requires_approval'    => isset($_POST['requires_approval'])?1:0,
        'supplier_id'          => ((int)($_POST['supplier_id']??0))?:null,
        'purchase_order_no'    => trim($_POST['purchase_order_no']??'')?:null,
        'invoice_number'       => trim($_POST['invoice_number']??'')?:null,
        'unit_of_measure'      => trim($_POST['unit_of_measure']??'unit'),
        'min_stock_level'      => max(0,(int)($_POST['min_stock_level']??0)),
        'notes'                => trim($_POST['notes']??'')?:null,
    ];

    if ($id > 0) {
        $set = implode(',', array_map(fn($k) => "`$k`=".($fields[$k]===null?'NULL':("'".db()->real_escape_string($fields[$k])."'")), array_keys($fields)));
        db()->query("UPDATE items SET $set WHERE id=$id");
        sys_log('info','inventory.php',"Item #$id updated by {$_SESSION['admin_name']}");
    } else {
        $cols = implode(',', array_map(fn($k)=>"`$k`", array_keys($fields)));
        $vals = implode(',', array_map(fn($v) => $v===null?'NULL':("'".db()->real_escape_string($v)."'"), array_values($fields)));
        db()->query("INSERT INTO items ($cols) VALUES ($vals)");
        sys_log('info','inventory.php',"New item registered: $uid by {$_SESSION['admin_name']}");
    }
    header("Location: inventory.php?action=list&msg=saved"); exit;
}

// DELETE
if ($action==='delete' && isset($_GET['id'])) {
    require_admin();
    $id = (int)$_GET['id'];
    db()->query("DELETE FROM items WHERE id=$id");
    sys_log('info','inventory.php',"Item #$id deleted by {$_SESSION['admin_name']}");
    header("Location: inventory.php?action=list&msg=deleted"); exit;
}

// START CYCLE COUNT
if ($action==='start_count' && $_SERVER['REQUEST_METHOD']==='POST') {
    require_admin();
    $loc = (int)$_POST['location_id'];
    $exp = (int)db()->query("SELECT COUNT(*) as c FROM items WHERE location_id=$loc")->fetch_assoc()['c'];
    $code = 'CC-'.date('Ymd')+'-'.substr(uniqid(),-5);
    $by  = db()->real_escape_string($_SESSION['admin_name']);
    db()->query("INSERT INTO physical_counts (count_code,location_id,initiated_by,expected_qty) VALUES ('$code',$loc,'$by',$exp)");
    sys_log('info','inventory.php',"Cycle count $code started at loc $loc by $by");
    header("Location: inventory.php?action=count&msg=started"); exit;
}

// FINISH COUNT
if ($action==='finish_count' && isset($_GET['id'])) {
    require_admin();
    $id = (int)$_GET['id'];
    db()->query("UPDATE physical_counts SET status='completed', completed_at=NOW() WHERE id=$id");
    header("Location: inventory.php?action=count"); exit;
}

render_header('Asset Inventory');

if (isset($_GET['msg'])) {
    $msgs=['saved'=>['ok','Asset saved successfully.'],'deleted'=>['err','Asset deleted.'],'import_ok'=>['ok','Import complete: '.($_GET['imported']??0).' added, '.($_GET['skipped']??0).' skipped.']];
    if (isset($msgs[$_GET['msg']])) echo '<div class="alert-banner alert-'.$msgs[$_GET['msg']][0].'"><i class="fas fa-circle-info"></i>'.$msgs[$_GET['msg']][1].'</div>';
}
if (isset($_GET['err'])) echo '<div class="alert-banner alert-err"><i class="fas fa-circle-exclamation"></i>'.h($_GET['err']==='missing_fields'?'UID and Item Name are required.':'File upload failed.').'</div>';

// ============================================================
// LIST VIEW
// ============================================================
if ($action==='list') {
    $search   = trim($_GET['q']??'');
    $f_status = $_GET['fs']??'';
    $f_life   = $_GET['fl']??'';
    $f_cat    = $_GET['fc']??'';
    $f_dept   = $_GET['fd']??'';
    $where = ['1=1'];
    if ($search)   $where[] = "(i.item_name LIKE '%".db()->real_escape_string($search)."%' OR i.uid LIKE '%".db()->real_escape_string($search)."%' OR i.sku LIKE '%".db()->real_escape_string($search)."%' OR i.brand LIKE '%".db()->real_escape_string($search)."%')";
    if ($f_status) $where[] = "i.status='".db()->real_escape_string($f_status)."'";
    if ($f_life)   $where[] = "i.lifecycle='".db()->real_escape_string($f_life)."'";
    if ($f_cat)    $where[] = "i.item_category='".db()->real_escape_string($f_cat)."'";
    if ($f_dept)   $where[] = "i.department='".db()->real_escape_string($f_dept)."'";
    $wsql = implode(' AND ',$where);

    $items = db()->query("SELECT i.*, l.location_name FROM items i LEFT JOIN locations l ON i.location_id=l.id WHERE $wsql ORDER BY i.item_category, i.item_name");
    $cats  = db()->query("SELECT DISTINCT item_category FROM items ORDER BY item_category");
    $depts = db()->query("SELECT DISTINCT department FROM items WHERE department IS NOT NULL ORDER BY department");
    ?>
    <!-- FILTER BAR -->
    <div class="card" style="margin-bottom:18px">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <input type="hidden" name="action" value="list">
            <div style="flex:2;min-width:180px"><label class="form-label">Search</label><input type="text" name="q" class="form-control" value="<?= h($search) ?>" placeholder="Name, UID, SKU, Brand..."></div>
            <div style="min-width:130px"><label class="form-label">Status</label>
                <select name="fs" class="form-control">
                    <option value="">All Status</option>
                    <?php foreach(['in_stock','checked_out','under_repair','missing','quarantined','sterilizing','in_transit','damaged'] as $s): ?>
                    <option value="<?= $s ?>" <?= $f_status===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:130px"><label class="form-label">Lifecycle</label>
                <select name="fl" class="form-control">
                    <option value="">All Lifecycle</option>
                    <?php foreach(['active','expiring_soon','expired','maintenance','retired','disposed'] as $l): ?>
                    <option value="<?= $l ?>" <?= $f_life===$l?'selected':'' ?>><?= ucwords(str_replace('_',' ',$l)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:130px"><label class="form-label">Category</label>
                <select name="fc" class="form-control">
                    <option value="">All Categories</option>
                    <?php while($c=$cats->fetch_assoc()): ?><option value="<?= h($c['item_category']) ?>" <?= $f_cat==$c['item_category']?'selected':'' ?>><?= h($c['item_category']) ?></option><?php endwhile; ?>
                </select>
            </div>
            <div style="min-width:130px"><label class="form-label">Department</label>
                <select name="fd" class="form-control">
                    <option value="">All Depts</option>
                    <?php while($d=$depts->fetch_assoc()): ?><option value="<?= h($d['department']) ?>" <?= $f_dept==$d['department']?'selected':'' ?>><?= h($d['department']) ?></option><?php endwhile; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i>Filter</button>
                <a href="?action=list" class="btn btn-dark btn-sm"><i class="fas fa-xmark"></i></a>
                <a href="?action=add" class="btn btn-green btn-sm"><i class="fas fa-plus"></i>Add Asset</a>
                <a href="?action=export_csv" class="btn btn-dark btn-sm"><i class="fas fa-file-csv"></i>CSV</a>
                <a href="?action=export_pdf" class="btn btn-dark btn-sm" target="_blank"><i class="fas fa-print"></i>Print</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-hdr">
            <div class="card-title"><i class="fas fa-boxes-stacked"></i>Asset Register (<?= $items->num_rows ?> items)</div>
        </div>
        <div class="table-wrap"><table>
            <tr><th>UID / SKU</th><th>Item Name</th><th>Category</th><th>Qty</th><th>Location</th><th>Status</th><th>Lifecycle</th><th>Expiry</th><th>Flags</th><th>Last Seen</th><th>Actions</th></tr>
            <?php while($item=$items->fetch_assoc()):
                $expiring = $item['expiry_date'] && strtotime($item['expiry_date']) < strtotime('+30 days');
            ?>
            <tr>
                <td><div style="font-family:'DM Mono',monospace;font-size:11px;color:var(--accent)"><?= h($item['uid']) ?></div><div class="td-sub"><?= h($item['sku']??'—') ?></div></td>
                <td>
                    <div class="td-name"><?= h($item['item_name']) ?></div>
                    <div class="td-sub"><?= h($item['brand']??'') ?> <?= h($item['model_number']??'') ?></div>
                </td>
                <td><span class="pill pill-purple" style="font-size:10px"><?= h($item['item_category']) ?></span></td>
                <td style="font-family:'DM Mono',monospace;font-weight:700;<?= $item['quantity']<=$item['min_stock_level']&&$item['min_stock_level']>0?'color:var(--red)':'' ?>"><?= $item['quantity'] ?> <span style="font-size:10px;font-weight:400;color:var(--muted)"><?= h($item['unit_of_measure']) ?></span></td>
                <td style="font-size:12px"><?= h($item['location_name']??'Unassigned') ?></td>
                <td><?php
                    $sc=['in_stock'=>'pill-green','checked_out'=>'pill-blue','under_repair'=>'pill-yellow','missing'=>'pill-red','quarantined'=>'pill-red','sterilizing'=>'pill-cyan','in_transit'=>'pill-purple'];
                    echo '<span class="pill '.($sc[$item['status']]??'pill-gray').'">'.str_replace('_',' ',$item['status']).'</span>';
                ?></td>
                <td><?php
                    $lc=['active'=>'pill-green','expiring_soon'=>'pill-yellow','expired'=>'pill-red','maintenance'=>'pill-yellow','retired'=>'pill-gray'];
                    echo '<span class="pill '.($lc[$item['lifecycle']]??'pill-gray').'">'.str_replace('_',' ',$item['lifecycle']).'</span>';
                ?></td>
                <td style="font-size:11px;<?= $expiring?'color:var(--red);font-weight:700':'' ?>"><?= $item['expiry_date']?date('d M Y',strtotime($item['expiry_date'])):'—' ?></td>
                <td>
                    <?php if($item['is_hazardous']): ?><span title="Hazardous" style="color:var(--red)"><i class="fas fa-radiation"></i></span> <?php endif; ?>
                    <?php if($item['is_controlled_drug']): ?><span title="Controlled Drug" style="color:var(--yellow)"><i class="fas fa-capsules"></i></span> <?php endif; ?>
                    <?php if($item['requires_sterilization']): ?><span title="Requires Sterilization" style="color:var(--cyan)"><i class="fas fa-virus-slash"></i></span> <?php endif; ?>
                    <?php if($item['is_perishable']): ?><span title="Perishable" style="color:var(--green)"><i class="fas fa-temperature-low"></i></span> <?php endif; ?>
                </td>
                <td style="font-size:11px;color:var(--sub)"><?= $item['last_seen']?ago($item['last_seen']):'Never' ?></td>
                <td style="display:flex;gap:5px">
                    <a href="?action=edit&id=<?= $item['id'] ?>" class="btn btn-dark btn-sm"><i class="fas fa-pen"></i></a>
                    <a href="?action=delete&id=<?= $item['id'] ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete this asset permanently?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table></div>
    </div>
    <?php
}

// ============================================================
// ADD / EDIT FORM
// ============================================================
elseif ($action==='add' || $action==='edit') {
    show_form:
    $id = (int)($_GET['id']??0);
    $item = $id>0 ? db()->query("SELECT * FROM items WHERE id=$id LIMIT 1")->fetch_assoc() : [];
    $locs = db()->query("SELECT id, location_name, zone FROM locations ORDER BY zone, location_name");
    $cats = db()->query("SELECT name, requires_expiry FROM categories ORDER BY name");
    $sups = db()->query("SELECT id, name FROM suppliers WHERE is_active=1 ORDER BY name");

    // Hospital item quick templates
    $templates = [
        '' => '— Quick Fill Template (optional) —',
        'patient_monitor' => 'Patient Monitor (Bedside)',
        'ventilator' => 'Ventilator / Respiratory Support',
        'ecg_machine' => 'ECG / Cardiac Monitor',
        'infusion_pump' => 'Infusion / IV Pump',
        'defibrillator' => 'Defibrillator / AED',
        'bp_monitor' => 'BP Monitor (Digital)',
        'pulse_oximeter' => 'Pulse Oximeter',
        'syringe_pump' => 'Syringe Pump',
        'wheelchair' => 'Wheelchair (Standard)',
        'hospital_bed' => 'Hospital Bed (Motorized)',
        'oxygen_cylinder' => 'Oxygen Cylinder (D-Type)',
        'surgical_scissor' => 'Surgical Scissor',
        'iv_cannula' => 'IV Cannula (20G)',
        'surgical_gloves' => 'Surgical Gloves (Box)',
        'n95_mask' => 'N95 Respirator Mask',
        'ns_500' => 'Normal Saline 0.9% 500ml',
        'amoxicillin' => 'Amoxicillin 500mg Capsules x100',
        'paracetamol_tab' => 'Paracetamol 500mg Tablet x100',
    ];

    $tpl_data = [
        'patient_monitor' => ['item_type'=>'Equipment','item_category'=>'Patient Monitoring','unit_of_measure'=>'unit','is_perishable'=>0,'is_hazardous'=>0,'department'=>'ICU'],
        'ventilator'      => ['item_type'=>'Equipment','item_category'=>'Life Support','unit_of_measure'=>'unit','is_perishable'=>0,'department'=>'ICU','requires_sterilization'=>0],
        'ecg_machine'     => ['item_type'=>'Equipment','item_category'=>'Diagnostic','unit_of_measure'=>'unit','department'=>'Emergency'],
        'infusion_pump'   => ['item_type'=>'Equipment','item_category'=>'Medical Equipment','unit_of_measure'=>'unit','department'=>'ICU'],
        'defibrillator'   => ['item_type'=>'Equipment','item_category'=>'Life Support','unit_of_measure'=>'unit','department'=>'Emergency'],
        'bp_monitor'      => ['item_type'=>'Equipment','item_category'=>'Diagnostic','unit_of_measure'=>'unit'],
        'pulse_oximeter'  => ['item_type'=>'Equipment','item_category'=>'Diagnostic','unit_of_measure'=>'unit'],
        'syringe_pump'    => ['item_type'=>'Equipment','item_category'=>'Medical Equipment','unit_of_measure'=>'unit'],
        'wheelchair'      => ['item_type'=>'Equipment','item_category'=>'Medical Equipment','unit_of_measure'=>'unit','department'=>'OPD'],
        'hospital_bed'    => ['item_type'=>'Furniture','item_category'=>'Furniture & Fixtures','unit_of_measure'=>'unit'],
        'oxygen_cylinder' => ['item_type'=>'Equipment','item_category'=>'Oxygen & Gas','unit_of_measure'=>'unit','is_perishable'=>1],
        'surgical_scissor'=> ['item_type'=>'Instrument','item_category'=>'Surgical Instruments','unit_of_measure'=>'unit','requires_sterilization'=>1,'department'=>'CSSD'],
        'iv_cannula'      => ['item_type'=>'Consumable','item_category'=>'Consumables','unit_of_measure'=>'box','is_perishable'=>1],
        'surgical_gloves' => ['item_type'=>'Consumable','item_category'=>'PPE','unit_of_measure'=>'box','is_perishable'=>1],
        'n95_mask'        => ['item_type'=>'Consumable','item_category'=>'PPE','unit_of_measure'=>'box','is_perishable'=>1],
        'ns_500'          => ['item_type'=>'IV Fluid','item_category'=>'IV Fluids','unit_of_measure'=>'bag','is_perishable'=>1,'department'=>'Pharmacy'],
        'amoxicillin'     => ['item_type'=>'Medicine','item_category'=>'Pharmaceuticals','unit_of_measure'=>'strip','is_perishable'=>1,'department'=>'Pharmacy'],
        'paracetamol_tab' => ['item_type'=>'Medicine','item_category'=>'Pharmaceuticals','unit_of_measure'=>'strip','is_perishable'=>1,'department'=>'Pharmacy'],
    ];
    ?>
    <div style="display:flex;gap:14px;margin-bottom:20px;align-items:center">
        <a href="?action=list" class="btn btn-dark btn-sm"><i class="fas fa-arrow-left"></i>Back to List</a>
        <h2 style="font-size:18px;font-weight:800"><?= $id>0?'Edit Asset':'Register New Asset' ?></h2>
    </div>

    <!-- Quick Template -->
    <?php if (!$id): ?>
    <div class="alert-banner alert-warn" style="cursor:default">
        <i class="fas fa-bolt"></i>
        <div style="flex:1">
            <strong>Quick Fill:</strong> Select a common hospital item template to pre-fill fields.
            <div style="margin-top:8px">
                <select id="tplSelect" class="form-control" style="max-width:360px;display:inline-block" onchange="applyTemplate()">
                    <?php foreach($templates as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid-2">
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-tag"></i>Identification</div></div>
        <form method="POST" id="assetForm">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-row">
                <div class="form-group"><label class="form-label">RFID Tag UID *</label><input type="text" name="uid" class="form-control" required value="<?= h($item['uid']??'') ?>" placeholder="e.g. MED-0011" style="font-family:'DM Mono',monospace;text-transform:uppercase"></div>
                <div class="form-group"><label class="form-label">SKU</label><input type="text" name="sku" class="form-control" value="<?= h($item['sku']??'') ?>" placeholder="Stock Keeping Unit"></div>
            </div>
            <div class="form-group"><label class="form-label">Item Name *</label><input type="text" name="item_name" class="form-control" required value="<?= h($item['item_name']??'') ?>" id="f_item_name"></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Brand</label><input type="text" name="brand" id="f_brand" class="form-control" value="<?= h($item['brand']??'') ?>"></div>
                <div class="form-group"><label class="form-label">Model Number</label><input type="text" name="model_number" class="form-control" value="<?= h($item['model_number']??'') ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Item Type *</label>
                    <select name="item_type" id="f_item_type" class="form-control" required>
                        <?php foreach(['Equipment','Medicine','Consumable','IV Fluid','Instrument','Furniture','PPE','Reagent','Other'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($item['item_type']??'')===$t?'selected':'' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Category *</label>
                    <select name="item_category" id="f_item_category" class="form-control" required onchange="checkExpiryRequired(this)">
                        <?php while($c=$cats->fetch_assoc()): ?>
                        <option value="<?= h($c['name']) ?>" data-expiry="<?= $c['requires_expiry'] ?>" <?= ($item['item_category']??'')===$c['name']?'selected':'' ?>><?= h($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Serial Number</label><input type="text" name="serial_number" class="form-control" value="<?= h($item['serial_number']??'') ?>" style="font-family:'DM Mono',monospace"></div>
            <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= h($item['description']??'') ?></textarea></div>
        </div>
        <div class="card">
            <div class="card-hdr"><div class="card-title"><i class="fas fa-warehouse"></i>Stock & Location</div></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Quantity *</label><input type="number" name="quantity" class="form-control" required min="0" value="<?= $item['quantity']??1 ?>"></div>
                <div class="form-group"><label class="form-label">Unit of Measure</label>
                    <select name="unit_of_measure" id="f_uom" class="form-control">
                        <?php foreach(['unit','strip','box','pcs','kg','gm','litre','ml','bag','bottle','vial','ampoule','roll','pair'] as $u): ?>
                        <option value="<?= $u ?>" <?= ($item['unit_of_measure']??'unit')===$u?'selected':'' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Min Stock Level</label><input type="number" name="min_stock_level" class="form-control" min="0" value="<?= $item['min_stock_level']??0 ?>"></div>
                <div class="form-group"><label class="form-label">Reorder Quantity</label><input type="number" name="reorder_quantity" class="form-control" min="1" value="<?= $item['reorder_quantity']??1 ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Location / Ward</label>
                <select name="location_id" class="form-control">
                    <option value="">— Unassigned —</option>
                    <?php while($l=$locs->fetch_assoc()): ?>
                    <option value="<?= $l['id'] ?>" <?= ($item['location_id']??0)==$l['id']?'selected':'' ?>>[<?= h($l['zone']) ?>] <?= h($l['location_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Department</label>
                    <input type="text" name="department" id="f_department" class="form-control" value="<?= h($item['department']??'') ?>" list="deptList" placeholder="e.g. ICU, Pharmacy">
                    <datalist id="deptList">
                        <?php foreach(['ICU','Emergency','Pharmacy','CSSD','OT','Lab','Maternity','Paediatrics','Surgical Ward','General Ward','OPD','Blood Bank','Biomedical','Administration'] as $dp): ?><option value="<?= $dp ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group"><label class="form-label">Assigned To</label><input type="text" name="assigned_to" class="form-control" value="<?= h($item['assigned_to']??'') ?>" placeholder="Doctor / Nurse / Ward name"></div>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-hdr"><div class="card-title"><i class="fas fa-calendar"></i>Dates & Lifecycle</div></div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" id="expiryLabel">Expiry Date</label>
                    <input type="date" name="expiry_date" id="f_expiry_date" class="form-control" value="<?= h($item['expiry_date']??'') ?>">
                    <div class="help-text" id="expiryHelp">Mandatory for medicines & consumables.</div>
                </div>
                <div class="form-group"><label class="form-label">Warranty Expiry</label><input type="date" name="warranty_expiry" class="form-control" value="<?= h($item['warranty_expiry']??'') ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Manufacture Date</label><input type="date" name="manufacture_date" class="form-control" value="<?= h($item['manufacture_date']??'') ?>"></div>
                <div class="form-group"><label class="form-label">Purchase Date</label><input type="date" name="purchase_date" class="form-control" value="<?= h($item['purchase_date']??'') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Next Service / Maintenance Due</label><input type="date" name="next_service_date" class="form-control" value="<?= h($item['next_service_date']??'') ?>"></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <?php foreach(['in_stock','checked_out','under_repair','sterilizing','quarantined','missing','in_transit','damaged'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($item['status']??'in_stock')===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Lifecycle</label>
                    <select name="lifecycle" class="form-control">
                        <?php foreach(['active','maintenance','expiring_soon','expired','retired','disposed'] as $l): ?>
                        <option value="<?= $l ?>" <?= ($item['lifecycle']??'active')===$l?'selected':'' ?>><?= ucwords(str_replace('_',' ',$l)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Condition Grade</label>
                <select name="condition_grade" class="form-control">
                    <?php foreach(['new','good','fair','poor','scrap'] as $c): ?>
                    <option value="<?= $c ?>" <?= ($item['condition_grade']??'new')===$c?'selected':'' ?>><?= ucfirst($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card">
            <div class="card-hdr"><div class="card-title"><i class="fas fa-truck-medical"></i>Procurement</div></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Unit Cost (₹)</label><input type="number" name="unit_cost" class="form-control" step="0.01" min="0" value="<?= $item['unit_cost']??0 ?>"></div>
                <div class="form-group"><label class="form-label">Unit Price (₹)</label><input type="number" name="unit_price" class="form-control" step="0.01" min="0" value="<?= $item['unit_price']??0 ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-control">
                    <option value="">— None —</option>
                    <?php while($s=$sups->fetch_assoc()): ?>
                    <option value="<?= $s['id'] ?>" <?= ($item['supplier_id']??0)==$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">PO Number</label><input type="text" name="purchase_order_no" class="form-control" value="<?= h($item['purchase_order_no']??'') ?>" style="font-family:'DM Mono',monospace"></div>
                <div class="form-group"><label class="form-label">Invoice Number</label><input type="text" name="invoice_number" class="form-control" value="<?= h($item['invoice_number']??'') ?>" style="font-family:'DM Mono',monospace"></div>
            </div>
            <div class="section-divider">Clinical Flags</div>
            <div style="display:flex;flex-direction:column;gap:10px">
                <?php $flags=[['is_perishable','Temperature-sensitive / Perishable','temperature-low'],['is_hazardous','Hazardous Material / Biohazard','radiation'],['is_controlled_drug','Controlled Drug (Narcotics/Schedule H1)','capsules'],['requires_sterilization','Requires Sterilization before Use','virus-slash'],['requires_approval','Requires Admin Approval for Checkout','shield-check']]; ?>
                <?php foreach($flags as [$fn,$fl,$fi]): ?>
                <label style="display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer;padding:8px 10px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
                    <input type="checkbox" name="<?= $fn ?>" value="1" id="f_<?= $fn ?>" <?= ($item[$fn]??0)?'checked':'' ?>>
                    <i class="fas fa-<?= $fi ?>" style="color:var(--accent2)"></i> <?= $fl ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="form-group"><label class="form-label">Notes / Additional Information</label><textarea name="notes" class="form-control" rows="3"><?= h($item['notes']??'') ?></textarea></div>
        <div style="display:flex;gap:10px">
            <button type="submit" name="action" value="save_item" form="assetForm" formaction="?action=save_item" class="btn btn-primary"><i class="fas fa-save"></i><?= $id>0?'Update Asset':'Register Asset' ?></button>
            <a href="?action=list" class="btn btn-dark"><i class="fas fa-xmark"></i>Cancel</a>
        </div>
    </div>
    <?php
}

// BULK IMPORT / EXPORT
elseif ($action==='bulk') { ?>
    <div class="grid-2">
        <div class="card">
            <div class="card-hdr"><div class="card-title"><i class="fas fa-upload"></i>Bulk CSV Import</div></div>
            <div class="alert-banner alert-warn" style="margin-bottom:16px">
                <i class="fas fa-circle-info"></i>
                <div><strong>CSV Column Order:</strong><br>
                <code style="font-family:'DM Mono',monospace;font-size:11px;display:block;margin-top:6px;line-height:2">UID, SKU, SerialNumber, ItemName, Brand, ModelNumber, ItemType, Category, Quantity, UnitCost, UnitPrice, Department, ExpiryDate (YYYY-MM-DD), WarrantyExpiry, ManufactureDate, PurchaseDate, IsPerishable (0/1), IsHazardous (0/1), Notes</code></div>
            </div>
            <form method="POST" action="?action=import_csv" enctype="multipart/form-data">
                <div class="form-group"><label class="form-label">Select CSV File</label><input type="file" name="csv" class="form-control" accept=".csv" required></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i>Upload & Import</button>
            </form>
        </div>
        <div class="card">
            <div class="card-hdr"><div class="card-title"><i class="fas fa-download"></i>Export</div></div>
            <p style="font-size:13px;color:var(--sub);margin-bottom:18px">Export the complete asset register.</p>
            <div style="display:flex;flex-direction:column;gap:12px">
                <a href="?action=export_csv" class="btn btn-dark"><i class="fas fa-file-csv"></i>Export as CSV</a>
                <a href="?action=export_pdf" class="btn btn-dark" target="_blank"><i class="fas fa-print"></i>Print Audit Report</a>
            </div>
        </div>
    </div>
<?php }

// CYCLE COUNT
elseif ($action==='count') {
    $active  = db()->query("SELECT p.*,l.location_name FROM physical_counts p JOIN locations l ON p.location_id=l.id WHERE p.status='in_progress' ORDER BY p.started_at DESC");
    $history = db()->query("SELECT p.*,l.location_name FROM physical_counts p JOIN locations l ON p.location_id=l.id WHERE p.status='completed' ORDER BY p.completed_at DESC LIMIT 10");
    ?>
    <div class="grid-2">
        <div class="card">
            <div class="card-hdr"><div class="card-title"><i class="fas fa-play"></i>Start New Cycle Count</div></div>
            <p style="font-size:13px;color:var(--sub);margin-bottom:16px">Initiates a cycle count. RFID readers will automatically tally scanned items vs expected quantities.</p>
            <form method="POST" action="?action=start_count">
                <div class="form-group"><label class="form-label">Select Ward / Location</label>
                    <select name="location_id" class="form-control" required>
                        <option value="">— Choose Location —</option>
                        <?php $ls=db()->query("SELECT id,location_name,zone FROM locations ORDER BY zone,location_name"); while($l=$ls->fetch_assoc()): ?>
                        <option value="<?= $l['id'] ?>">[<?= h($l['zone']) ?>] <?= h($l['location_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-play"></i>Initiate Count</button>
            </form>
        </div>
        <div class="card">
            <div class="card-hdr"><div class="card-title"><i class="fas fa-spinner"></i>Active Reconciliations</div></div>
            <?php if ($active->num_rows===0): ?><p style="color:var(--sub);font-size:13px">No active cycle counts.</p><?php else: ?>
            <div class="table-wrap"><table>
                <tr><th>Code</th><th>Location</th><th>Expected</th><th>Scanned</th><th>Variance</th><th>Action</th></tr>
                <?php while($a=$active->fetch_assoc()): $v=$a['scanned_qty']-$a['expected_qty']; ?>
                <tr>
                    <td style="font-family:'DM Mono',monospace;font-size:11px"><?= h($a['count_code']) ?></td>
                    <td><?= h($a['location_name']) ?></td>
                    <td><?= $a['expected_qty'] ?></td>
                    <td style="color:var(--green);font-weight:700"><?= $a['scanned_qty'] ?></td>
                    <td style="color:<?= $v==0?'var(--green)':($v>0?'var(--yellow)':'var(--red)') ?>;font-weight:700"><?= $v>0?'+':'' ?><?= $v ?></td>
                    <td><a href="?action=finish_count&id=<?= $a['id'] ?>" class="btn btn-green btn-sm" onclick="return confirm('Mark complete?')"><i class="fas fa-check"></i>Complete</a></td>
                </tr>
                <?php endwhile; ?>
            </table></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-hdr"><div class="card-title"><i class="fas fa-history"></i>Cycle Count History</div></div>
        <div class="table-wrap"><table>
            <tr><th>Code</th><th>Location</th><th>Initiated By</th><th>Expected</th><th>Scanned</th><th>Variance</th><th>Started</th><th>Completed</th></tr>
            <?php while($h=$history->fetch_assoc()): $v=$h['scanned_qty']-$h['expected_qty']; ?>
            <tr>
                <td style="font-family:'DM Mono',monospace;font-size:11px"><?= h($h['count_code']) ?></td>
                <td><?= h($h['location_name']) ?></td><td><?= h($h['initiated_by']) ?></td>
                <td><?= $h['expected_qty'] ?></td><td><?= $h['scanned_qty'] ?></td>
                <td style="color:<?= $v==0?'var(--green)':($v>0?'var(--yellow)':'var(--red)') ?>;font-weight:700"><?= $v>0?'+':'' ?><?= $v ?></td>
                <td style="font-size:11px;color:var(--sub)"><?= $h['started_at'] ?></td>
                <td style="font-size:11px;color:var(--sub)"><?= $h['completed_at']?:'—' ?></td>
            </tr>
            <?php endwhile; ?>
        </table></div>
    </div>
<?php }

render_footer(); ?>
<script>
const tplData = <?= json_encode(array_map(fn($d)=>$d,$tpl_data??[])) ?>;
function applyTemplate(){
    const k=document.getElementById('tplSelect').value;
    if(!k||!tplData[k])return;
    const d=tplData[k];
    if(d.item_type) setVal('f_item_type',d.item_type);
    if(d.item_category){const s=document.getElementById('f_item_category');for(let o of s.options)if(o.value===d.item_category){s.value=o.value;checkExpiryRequired(s);break;}}
    if(d.department) document.getElementById('f_department').value=d.department;
    if(d.unit_of_measure) setVal('f_uom',d.unit_of_measure);
    if(d.is_perishable!==undefined) document.getElementById('f_is_perishable').checked=!!d.is_perishable;
    if(d.is_hazardous!==undefined) document.getElementById('f_is_hazardous').checked=!!d.is_hazardous;
    if(d.requires_sterilization!==undefined) document.getElementById('f_requires_sterilization').checked=!!d.requires_sterilization;
}
function setVal(id,v){const el=document.getElementById(id);if(el){for(let o of el.options)if(o.value===v){el.value=v;return;}}}
function checkExpiryRequired(sel){
    const opt=sel.options[sel.selectedIndex];
    const req=opt&&opt.dataset.expiry==='1';
    const lbl=document.getElementById('expiryLabel');
    const fld=document.getElementById('f_expiry_date');
    const hlp=document.getElementById('expiryHelp');
    if(req){lbl.innerHTML='Expiry Date <span style="color:var(--red)">*</span>';fld.required=true;hlp.style.color='var(--red)';}
    else{lbl.textContent='Expiry Date';fld.required=false;hlp.style.color='';}
}
window.addEventListener('DOMContentLoaded',()=>{const s=document.getElementById('f_item_category');if(s)checkExpiryRequired(s);});
</script>