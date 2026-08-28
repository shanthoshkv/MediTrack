<?php
require_once 'core.php';

// Admit new patient
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['admit_patient'])) {
    require_admin();
    $pid    = db()->real_escape_string(next_patient_id());
    $rfid   = db()->real_escape_string(trim($_POST['rfid_uid']??'')) ?: null;
    $name   = db()->real_escape_string(trim($_POST['full_name']));
    $dob    = db()->real_escape_string($_POST['dob']??'') ?: null;
    $age    = (int)($_POST['age']??0);
    $gender = db()->real_escape_string($_POST['gender']);
    $bg     = db()->real_escape_string($_POST['blood_group']);
    $phone  = db()->real_escape_string(trim($_POST['phone']??''));
    $ec     = db()->real_escape_string(trim($_POST['emergency_contact']??''));
    $ep     = db()->real_escape_string(trim($_POST['emergency_phone']??''));
    $ward   = (int)($_POST['ward_id']??0) ?: 'NULL';
    $bed    = db()->real_escape_string(trim($_POST['bed_number']??''));
    $doc    = db()->real_escape_string(trim($_POST['attending_doctor']??''));
    $diag   = db()->real_escape_string(trim($_POST['diagnosis']??''));
    $atype  = db()->real_escape_string($_POST['admission_type']);
    $status = db()->real_escape_string($_POST['status']??'admitted');
    $diet   = db()->real_escape_string(trim($_POST['diet_type']??''));
    $allergy= db()->real_escape_string(trim($_POST['allergy_notes']??''));
    $ins    = db()->real_escape_string(trim($_POST['insurance_provider']??''));
    $inspol = db()->real_escape_string(trim($_POST['insurance_policy']??''));
    $notes  = db()->real_escape_string(trim($_POST['notes']??''));
    $by     = db()->real_escape_string($_SESSION['admin_name']);

    db()->query("INSERT INTO patients (patient_id,rfid_uid,full_name,dob,age,gender,blood_group,phone,emergency_contact,emergency_phone,ward_id,bed_number,attending_doctor,diagnosis,admission_type,status,diet_type,allergy_notes,insurance_provider,insurance_policy,notes,created_by,admission_date)
        VALUES ('$pid',".($rfid?"'$rfid'":'NULL').",'$name',".($dob?"'$dob'":'NULL').",$age,'$gender','$bg','$phone','$ec','$ep',$ward,'$bed','$doc','$diag','$atype','$status','$diet','$allergy','$ins','$inspol','$notes','$by',NOW())");
    sys_log('info','patients.php',"Patient admitted: $pid — $name by $by");
    header("Location: patients.php?msg=admitted&pid=".urlencode($pid)); exit;
}

// Discharge patient
if (isset($_GET['discharge'])) {
    require_admin();
    $id = (int)$_GET['discharge'];
    $notes = db()->real_escape_string($_GET['dnotes']??'Discharged');
    db()->query("UPDATE patients SET status='discharged', discharge_date=NOW(), notes=CONCAT(IFNULL(notes,''),' | Discharge: $notes') WHERE id=$id");
    sys_log('info','patients.php',"Patient #$id discharged by {$_SESSION['admin_name']}");
    header("Location: patients.php"); exit;
}

// Update RFID wristband
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['assign_rfid'])) {
    require_admin();
    $id   = (int)$_POST['patient_id'];
    $rfid = db()->real_escape_string(strtoupper(trim($_POST['rfid_uid'])));
    db()->query("UPDATE patients SET rfid_uid=".($rfid?"'$rfid'":'NULL')." WHERE id=$id");
    sys_log('info','patients.php',"RFID wristband updated for patient #$id: $rfid");
    header("Location: patients.php?tab=active"); exit;
}

// Transfer patient
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['transfer_patient'])) {
    require_admin();
    $id   = (int)$_POST['patient_id'];
    $ward = (int)$_POST['new_ward_id'];
    $bed  = db()->real_escape_string(trim($_POST['new_bed']??''));
    $st   = db()->real_escape_string($_POST['new_status']??'admitted');
    db()->query("UPDATE patients SET ward_id=$ward, bed_number='$bed', status='$st' WHERE id=$id");
    sys_log('info','patients.php',"Patient #$id transferred to ward $ward by {$_SESSION['admin_name']}");
    header("Location: patients.php?tab=active"); exit;
}

render_header('Patient Management');

$tab = $_GET['tab']??'active';
$msg = $_GET['msg']??'';

if ($msg==='admitted') echo '<div class="alert-banner alert-ok"><i class="fas fa-check-circle"></i>Patient '.h($_GET['pid']??'').' admitted successfully and RFID wristband assigned.</div>';

// Data
$active_pats = db()->query("SELECT p.*, l.location_name FROM patients p LEFT JOIN locations l ON p.ward_id=l.id WHERE p.status IN ('admitted','icu','critical','outpatient') ORDER BY FIELD(p.status,'critical','icu','admitted','outpatient'), p.created_at DESC");
$discharged  = db()->query("SELECT p.*, l.location_name FROM patients p LEFT JOIN locations l ON p.ward_id=l.id WHERE p.status IN ('discharged','transferred','deceased') ORDER BY p.discharge_date DESC LIMIT 50");
$wards       = db()->query("SELECT id, location_name, zone FROM locations WHERE location_type IN ('ward','icu','emergency','room') ORDER BY zone, location_name");
$doctors     = db()->query("SELECT DISTINCT attending_doctor FROM patients WHERE attending_doctor IS NOT NULL ORDER BY attending_doctor");

// Ward occupancy
$ward_occ = db()->query("SELECT l.location_name, l.capacity, COUNT(p.id) as occupied FROM locations l LEFT JOIN patients p ON p.ward_id=l.id AND p.status IN ('admitted','icu','critical') WHERE l.location_type IN ('ward','icu','emergency') GROUP BY l.id ORDER BY l.zone");
?>

<!-- TAB NAVIGATION -->
<div style="display:flex;gap:8px;margin-bottom:22px;flex-wrap:wrap">
    <?php foreach(['active'=>'Active Patients','admit'=>'Admit New Patient','discharged'=>'Discharge History','occupancy'=>'Ward Occupancy'] as $k=>$v): ?>
    <a href="?tab=<?= $k ?>" class="btn <?= $tab===$k?'btn-primary':'btn-dark' ?> btn-sm"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab==='active'): ?>
<!-- ACTIVE PATIENTS TABLE -->
<div class="card">
    <div class="card-hdr">
        <div class="card-title"><i class="fas fa-hospital-user"></i>Active Inpatients (<?= $active_pats->num_rows ?>)</div>
        <a href="?tab=admit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i>Admit Patient</a>
    </div>
    <div class="table-wrap"><table>
        <tr><th>Patient ID</th><th>Name</th><th>Status</th><th>Ward/Bed</th><th>Doctor</th><th>Blood Grp</th><th>Diagnosis</th><th>RFID</th><th>Admitted</th><th>Actions</th></tr>
        <?php while($p=$active_pats->fetch_assoc()): ?>
        <tr>
            <td style="font-family:'DM Mono',monospace;font-size:11px"><?= h($p['patient_id']) ?></td>
            <td>
                <div class="td-name"><?= h($p['full_name']) ?></div>
                <div class="td-sub"><?= $p['age']?$p['age'].'y · ':'' ?><?= strtoupper($p['gender']) ?></div>
            </td>
            <td><?php
                $sc=['admitted'=>'pill-blue','icu'=>'pill-yellow','critical'=>'pill-red','outpatient'=>'pill-gray'];
                echo '<span class="pill '.($sc[$p['status']]??'pill-gray').'">'.strtoupper($p['status']).'</span>';
            ?></td>
            <td style="font-size:12px"><?= h($p['location_name']??'—') ?><br><span style="color:var(--sub);font-size:11px"><?= h($p['bed_number']??'') ?></span></td>
            <td style="font-size:12px"><?= h($p['attending_doctor']??'—') ?></td>
            <td><span class="pill pill-red" style="font-size:11px"><?= h($p['blood_group']) ?></span></td>
            <td style="font-size:11px;color:var(--sub);max-width:160px"><?= h($p['diagnosis']??'—') ?></td>
            <td>
                <?php if($p['rfid_uid']): ?>
                <span class="pill pill-green" style="font-size:10px"><?= h($p['rfid_uid']) ?></span>
                <?php else: ?>
                <span style="color:var(--yellow);font-size:11px"><i class="fas fa-triangle-exclamation"></i> No RFID</span>
                <?php endif; ?>
            </td>
            <td style="font-size:11px;color:var(--sub)"><?= $p['admission_date']?ago($p['admission_date']):'—' ?></td>
            <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap">
                    <button onclick="showRfid(<?= $p['id'] ?>,<?= "'".$p['rfid_uid']."'" ?>)" class="btn btn-dark btn-sm" title="Assign RFID"><i class="fas fa-tag"></i></button>
                    <button onclick="showTransfer(<?= $p['id'] ?>)" class="btn btn-dark btn-sm" title="Transfer Ward"><i class="fas fa-arrows-alt"></i></button>
                    <a href="?discharge=<?= $p['id'] ?>&dnotes=Routine+discharge" class="btn btn-red btn-sm" onclick="return confirm('Discharge this patient?')" title="Discharge"><i class="fas fa-person-walking-arrow-right"></i></a>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
    </table></div>
</div>

<!-- RFID ASSIGN MODAL -->
<div id="rfidModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:500;align-items:center;justify-content:center">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;padding:28px;max-width:380px;width:90%">
        <h3 style="margin-bottom:16px"><i class="fas fa-tag" style="color:var(--accent2)"></i> Assign RFID Wristband</h3>
        <form method="POST">
            <input type="hidden" name="assign_rfid" value="1">
            <input type="hidden" name="patient_id" id="rfid_pid">
            <div class="form-group"><label class="form-label">RFID Tag UID</label>
                <input type="text" name="rfid_uid" id="rfid_input" class="form-control" placeholder="Scan tag or enter UID..." style="font-family:'DM Mono',monospace;text-transform:uppercase">
                <div class="help-text">Leave blank to unassign current wristband</div>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i>Save</button>
                <button type="button" onclick="document.getElementById('rfidModal').style.display='none'" class="btn btn-dark">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- TRANSFER MODAL -->
<div id="transferModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:500;align-items:center;justify-content:center">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;padding:28px;max-width:420px;width:90%">
        <h3 style="margin-bottom:16px"><i class="fas fa-arrows-alt" style="color:var(--accent2)"></i> Transfer Patient</h3>
        <form method="POST">
            <input type="hidden" name="transfer_patient" value="1">
            <input type="hidden" name="patient_id" id="transfer_pid">
            <div class="form-group"><label class="form-label">Transfer To Ward</label>
                <select name="new_ward_id" class="form-control" required>
                    <?php $wards->data_seek(0); while($w=$wards->fetch_assoc()): ?>
                    <option value="<?= $w['id'] ?>"><?= h($w['location_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">New Bed No.</label><input type="text" name="new_bed" class="form-control" placeholder="e.g. GW1-07"></div>
                <div class="form-group"><label class="form-label">New Status</label>
                    <select name="new_status" class="form-control">
                        <option value="admitted">Admitted</option><option value="icu">ICU</option>
                        <option value="critical">Critical</option><option value="outpatient">Outpatient</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary"><i class="fas fa-arrows-alt"></i>Transfer</button>
                <button type="button" onclick="document.getElementById('transferModal').style.display='none'" class="btn btn-dark">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
function showRfid(id,uid){document.getElementById('rfid_pid').value=id;document.getElementById('rfid_input').value=uid||'';document.getElementById('rfidModal').style.display='flex';}
function showTransfer(id){document.getElementById('transfer_pid').value=id;document.getElementById('transferModal').style.display='flex';}
</script>

<?php elseif ($tab==='admit'): ?>
<!-- ADMIT NEW PATIENT -->
<div class="card">
    <div class="card-hdr"><div class="card-title"><i class="fas fa-user-plus"></i>Admit New Patient</div></div>
    <div class="alert-banner alert-warn" style="margin-bottom:18px">
        <i class="fas fa-circle-info"></i>
        Patient ID will be auto-generated (next: <strong><?= next_patient_id() ?></strong>). Assign RFID wristband tag UID after printing wristband label.
    </div>
    <form method="POST">
        <input type="hidden" name="admit_patient" value="1">
        <div class="section-divider">Personal Information</div>
        <div class="form-row-3">
            <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required placeholder="Patient full name"></div>
            <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control"></div>
            <div class="form-group"><label class="form-label">Age (if DOB unknown)</label><input type="number" name="age" class="form-control" min="0" max="150" placeholder="Years"></div>
        </div>
        <div class="form-row-3">
            <div class="form-group"><label class="form-label">Gender *</label>
                <select name="gender" class="form-control" required>
                    <option value="male">Male</option><option value="female">Female</option><option value="other">Other</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Blood Group</label>
                <select name="blood_group" class="form-control">
                    <?php foreach(['Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                    <option value="<?= $bg ?>"><?= $bg ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">RFID Wristband UID</label><input type="text" name="rfid_uid" class="form-control" placeholder="Scan tag UID or enter manually" style="font-family:'DM Mono',monospace;text-transform:uppercase"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control" placeholder="Patient / guardian phone"></div>
            <div class="form-group"><label class="form-label">Emergency Contact</label><input type="text" name="emergency_contact" class="form-control" placeholder="Name (relation)"></div>
        </div>
        <div class="form-group"><label class="form-label">Emergency Phone</label><input type="tel" name="emergency_phone" class="form-control" placeholder="Emergency contact number" style="max-width:300px"></div>
        <div class="form-group"><label class="form-label">Address</label><input type="text" name="address" class="form-control" placeholder="Full address"></div>

        <div class="section-divider">Admission Details</div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Admission Type *</label>
                <select name="admission_type" class="form-control" required>
                    <option value="emergency">🚨 Emergency</option><option value="elective">📋 Elective</option>
                    <option value="transfer">🔄 Transfer from Another Facility</option><option value="outpatient">🏥 Outpatient</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Initial Status</label>
                <select name="status" class="form-control">
                    <option value="admitted">Admitted</option><option value="icu">ICU</option>
                    <option value="critical">Critical</option><option value="outpatient">Outpatient</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Assign Ward</label>
                <select name="ward_id" class="form-control">
                    <option value="">— Select Ward —</option>
                    <?php $wards->data_seek(0); while($w=$wards->fetch_assoc()): ?>
                    <option value="<?= $w['id'] ?>"><?= h($w['location_name']) ?> [<?= h($w['zone']) ?>]</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Bed Number</label><input type="text" name="bed_number" class="form-control" placeholder="e.g. GW1-04"></div>
        </div>
        <div class="form-group"><label class="form-label">Attending Doctor *</label>
            <input type="text" name="attending_doctor" class="form-control" required placeholder="Dr. Name" list="doctorList">
            <datalist id="doctorList">
                <?php while($d=$doctors->fetch_assoc()): ?><option value="<?= h($d['attending_doctor']) ?>"><?php endwhile; ?>
            </datalist>
        </div>
        <div class="form-group"><label class="form-label">Diagnosis / Chief Complaint</label><textarea name="diagnosis" class="form-control" rows="2" placeholder="Primary diagnosis or presenting complaint..."></textarea></div>

        <div class="section-divider">Clinical Notes</div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Allergy Notes</label><input type="text" name="allergy_notes" class="form-control" placeholder="Known allergies (drugs, food, etc.)"></div>
            <div class="form-group"><label class="form-label">Diet Type</label>
                <select name="diet_type" class="form-control">
                    <option value="">— None specified —</option>
                    <option>Normal Diet</option><option>Diabetic Diet</option><option>Low Sodium</option>
                    <option>Liquid Diet</option><option>NPO (Nothing by Mouth)</option><option>Renal Diet</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Insurance Provider</label><input type="text" name="insurance_provider" class="form-control" placeholder="Insurance company name"></div>
            <div class="form-group"><label class="form-label">Policy Number</label><input type="text" name="insurance_policy" class="form-control" placeholder="Policy / ID number"></div>
        </div>
        <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Additional notes for nursing staff..."></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i>Admit Patient</button>
    </form>
</div>

<?php elseif ($tab==='discharged'): ?>
<div class="card">
    <div class="card-hdr"><div class="card-title"><i class="fas fa-history"></i>Discharge History (Last 50)</div></div>
    <div class="table-wrap"><table>
        <tr><th>Patient ID</th><th>Name</th><th>Gender</th><th>Doctor</th><th>Diagnosis</th><th>Admitted</th><th>Discharged</th><th>Status</th></tr>
        <?php while($p=$discharged->fetch_assoc()): ?>
        <tr>
            <td style="font-family:'DM Mono',monospace;font-size:11px"><?= h($p['patient_id']) ?></td>
            <td class="td-name"><?= h($p['full_name']) ?></td>
            <td style="font-size:12px"><?= strtoupper($p['gender']) ?></td>
            <td style="font-size:12px"><?= h($p['attending_doctor']??'—') ?></td>
            <td style="font-size:11px;color:var(--sub);max-width:180px"><?= h($p['diagnosis']??'—') ?></td>
            <td style="font-size:11px;color:var(--sub)"><?= $p['admission_date']?date('d M Y',strtotime($p['admission_date'])):'—' ?></td>
            <td style="font-size:11px;color:var(--sub)"><?= $p['discharge_date']?date('d M Y',strtotime($p['discharge_date'])):'—' ?></td>
            <td><span class="pill pill-gray"><?= strtoupper($p['status']) ?></span></td>
        </tr>
        <?php endwhile; ?>
    </table></div>
</div>

<?php elseif ($tab==='occupancy'): ?>
<div class="card">
    <div class="card-hdr"><div class="card-title"><i class="fas fa-bed"></i>Ward Occupancy Status</div></div>
    <div class="table-wrap"><table>
        <tr><th>Ward / Unit</th><th>Capacity</th><th>Occupied</th><th>Free Beds</th><th>Occupancy %</th><th>Status</th></tr>
        <?php while($w=$ward_occ->fetch_assoc()):
            $pct = $w['capacity']>0 ? round($w['occupied']/$w['capacity']*100) : 0;
        ?>
        <tr>
            <td class="td-name"><?= h($w['location_name']) ?></td>
            <td><?= $w['capacity'] ?></td>
            <td style="font-weight:700;color:var(--cyan)"><?= $w['occupied'] ?></td>
            <td style="color:var(--green)"><?= max(0,$w['capacity']-$w['occupied']) ?></td>
            <td>
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="flex:1;height:6px;background:var(--border);border-radius:4px">
                        <div style="height:100%;width:<?= min(100,$pct) ?>%;background:<?= $pct>=90?'var(--red)':($pct>=70?'var(--yellow)':'var(--green)') ?>;border-radius:4px"></div>
                    </div>
                    <span style="font-size:11px;font-family:'DM Mono',monospace;color:var(--sub)"><?= $pct ?>%</span>
                </div>
            </td>
            <td><span class="pill <?= $pct>=90?'pill-red':($pct>=70?'pill-yellow':'pill-green') ?>"><?= $pct>=90?'FULL':($pct>=70?'BUSY':'AVAILABLE') ?></span></td>
        </tr>
        <?php endwhile; ?>
    </table></div>
</div>
<?php endif; ?>

<?php render_footer(); ?>