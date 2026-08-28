<?php
require_once 'core.php';

$l = location_cols();
$i = item_cols();
$p = patient_cols();
$s = schedule_cols();
$sl = scanlog_cols();

$mode = getv('mode');
$readerid = getv('readerid');
$apikey = getv('apikey');

if (!$readerid || !$apikey) {
    exit('ERROR|Missing reader credentials');
}

$reader = one(
    "SELECT *
     FROM locations
     WHERE `{$l['readerid']}`='" . $db->real_escape_string($readerid) . "'
       AND `{$l['apikey']}`='" . $db->real_escape_string($apikey) . "'
       AND `{$l['isactive']}`=1
     LIMIT 1"
);

if (!$reader) {
    http_response_code(403);
    exit('ERROR|Unauthorized reader');
}

q("UPDATE locations SET `{$l['lastheartbeat']}`=NOW() WHERE id=" . (int)$reader['id']);

if ($mode === 'medverify') {
    $staffUid = strtoupper(getv('staffuid'));
    $patientUid = strtoupper(getv('patientuid'));
    $itemUid = strtoupper(getv('itemuid'));

    $staff = one("SELECT * FROM staffmembers WHERE rfiduid='" . $db->real_escape_string($staffUid) . "' AND is_active=1 LIMIT 1");
    $patient = one("SELECT * FROM patients WHERE `{$p['rfiduid']}`='" . $db->real_escape_string($patientUid) . "' LIMIT 1");
    $item = one("SELECT * FROM items WHERE uid='" . $db->real_escape_string($itemUid) . "' AND `{$i['itemtype']}`='medicine' LIMIT 1");

    if (!$staff || !$patient || !$item) {
        exit('FAIL|Invalid staff, patient or medicine tag');
    }

    if ((int)$item[$i['recallflag']] === 1) {
        create_alert('critical', 'recall', 'Recalled medicine scanned: ' . $item[$i['itemname']], 'index.php?page=medications');
        createTask('recall-scan-' . $item['id'] . '-' . date('YmdH'), 'medication', 'Recalled medicine intervention', 'Stop and remove ' . $item[$i['itemname']], 0, (int)$item['id'], 'critical', date('Y-m-d H:i:s', time() + 600));
        exit('FAIL|Medicine is recalled');
    }

    if (!empty($item[$i['expirydate']]) && strtotime($item[$i['expirydate']]) < time()) {
        create_alert('critical', 'expiry', 'Expired medicine scanned: ' . $item[$i['itemname']], 'index.php?page=medications');
        createTask('expired-scan-' . $item['id'] . '-' . date('YmdH'), 'medication', 'Expired medicine intervention', 'Medicine expired: ' . $item[$i['itemname']], 0, (int)$item['id'], 'critical', date('Y-m-d H:i:s', time() + 600));
        exit('FAIL|Medicine expired');
    }

    $sched = one(
        "SELECT *
         FROM medicationschedule
         WHERE `{$s['patientid']}`=" . (int)$patient['id'] . "
           AND `{$s['itemid']}`=" . (int)$item['id'] . "
           AND status='pending'
         ORDER BY `{$s['scheduledtime']}` ASC
         LIMIT 1"
    );

    if (!$sched) {
        create_alert('warning', 'medication', 'Medication verification failed for ' . $patient['fullname'], 'index.php?page=medications');
        createTask('medfail-' . $patient['id'] . '-' . $item['id'] . '-' . date('YmdHi'), 'medication', 'Unscheduled medication scan', $item[$i['itemname']] . ' scanned for ' . $patient['fullname'] . ' without matching pending order', (int)$patient['id'], (int)$item['id'], 'high', date('Y-m-d H:i:s', time() + 900));
        exit('FAIL|No pending medication found');
    }

    $mins = abs((time() - strtotime($sched[$s['scheduledtime']])) / 60);
    $comp = $mins <= 30 ? 'on_time' : 'late';

    $sql = "UPDATE medicationschedule
            SET status='administered',
                `{$s['compliancestatus']}`=?,
                `{$s['verifiedstaffuid']}`=?,
                `{$s['verifiedpatientuid']}`=?,
                `{$s['verifiedmedicineuid']}`=?,
                `{$s['verifiedat']}`=NOW()
            WHERE id=?";
    $st = $db->prepare($sql);
    $st->bind_param('ssssi', $comp, $staffUid, $patientUid, $itemUid, $sched['id']);
    $st->execute();

    $note = 'Verified by RFID via ' . $readerid;
    $st = $db->prepare("INSERT INTO medicationadministrations(schedule_id,patient_id,item_id,staff_id,note_text) VALUES(?,?,?,?,?)");
    if ($st) {
        $st->bind_param('iiiis', $sched['id'], $patient['id'], $item['id'], $staff['id'], $note);
        $st->execute();
    }

    $pass = 'pass';
    $msg = 'Medication administered successfully';
    $st = $db->prepare("INSERT INTO medication_verifications(schedule_id,patient_id,staff_id,item_id,result_text,message_text) VALUES(?,?,?,?,?,?)");
    if (!empty($item['batchno'])) {
    $bn = $db->real_escape_string($item['batchno']);
    $db->query("INSERT INTO batch_traces(batchno,item_id,patient_id,location_id,scan_time)
                VALUES('{$bn}',".(int)$item['id'].",".(int)$patient['id'].",".(int)$reader['id'].",NOW())");
}
    if ($st) {
        $st->bind_param('iiiiss', $sched['id'], $patient['id'], $staff['id'], $item['id'], $pass, $msg);
        $st->execute();
    }

    q("INSERT INTO scanlogs(uid, `{$sl['itemid']}`, `{$sl['patientid']}`, `{$sl['staffid']}`, `{$sl['tolocationid']}`, `{$sl['readerid']}`, `{$sl['actiontype']}`, notes)
       VALUES('" . $db->real_escape_string($itemUid) . "', " . (int)$item['id'] . ", " . (int)$patient['id'] . ", " . (int)$staff['id'] . ", " . (int)$reader['id'] . ", '" . $db->real_escape_string($readerid) . "', 'medverify', 'RFID medication administration')");

    updatePatientSeen((int)$patient['id'], (int)$reader['id']);
    exit('OK|Medication verified and administered');
}

$uid = strtoupper(getv('uid'));
if (!$uid) exit('ERROR|Missing uid');

$item = one("SELECT * FROM items WHERE uid='" . $db->real_escape_string($uid) . "' LIMIT 1");
if ($item) {
    $from = (int)($item[$i['locationid']] ?: 0);
    $to = (int)$reader['id'];
    $action = $from === $to ? 'scanned' : 'transfer';
    $newStatus = ((int)$item[$i['recallflag']] === 1) ? 'expired' : 'instock';

    $sql = "UPDATE items SET `{$i['locationid']}`=?, status=?, `{$i['lastseenat']}`=NOW() WHERE id=?";
    $st = $db->prepare($sql);
    $st->bind_param('isi', $to, $newStatus, $item['id']);
    $st->execute();

    q("INSERT INTO scanlogs(uid, `{$sl['itemid']}`, `{$sl['fromlocationid']}`, `{$sl['tolocationid']}`, `{$sl['readerid']}`, `{$sl['actiontype']}`, notes)
       VALUES('" . $db->real_escape_string($uid) . "', " . (int)$item['id'] . ", {$from}, {$to}, '" . $db->real_escape_string($readerid) . "', '{$action}', 'Item scan')");

    if (!empty($item[$i['expirydate']]) && strtotime($item[$i['expirydate']]) < time()) {
        create_alert('critical', 'expiry', 'Expired item scanned: ' . $item[$i['itemname']], 'index.php?page=inventory');
        createTask('expired-item-' . $item['id'] . '-' . date('YmdH'), 'inventory', 'Expired item scanned', $item[$i['itemname']] . ' scanned at ' . $reader[$l['locationname']], 0, (int)$item['id'], 'critical', date('Y-m-d H:i:s', time() + 900));
    }

    if ((int)$item[$i['recallflag']] === 1) {
        create_alert('critical', 'recall', 'Recalled item moved: ' . $item[$i['itemname']], 'index.php?page=inventory');
        createTask('recall-item-' . $item['id'] . '-' . date('YmdH'), 'inventory', 'Recalled item moved', $item[$i['itemname']] . ' moved through ' . $reader[$l['locationname']], 0, (int)$item['id'], 'critical', date('Y-m-d H:i:s', time() + 900));
    }

    // Low stock alert
$updatedQty = (int)$item['quantity'];
$threshold  = (int)($item['reorder_threshold'] ?? 10);
if ($updatedQty <= $threshold) {
    create_alert('warning','lowstock','Low stock: '.$item[$i['itemname']].' ('.$updatedQty.' left)','index.php?page=reorder');
    createTask('lowstock-'.$item['id'].'-'.date('Ymd'),'inventory','Reorder required: '.$item[$i['itemname']],
        'Qty '.$updatedQty.' ≤ threshold '.$threshold.'. Suggest reorder: '.($item['reorder_qty']??50),
        0,(int)$item['id'],'high',date('Y-m-d H:i:s',time()+3600));
}
// Batch trace
if (!empty($item['batchno'])) {
    $bn = $db->real_escape_string($item['batchno']);
    $db->query("INSERT INTO batch_traces(batchno,item_id,patient_id,location_id,scan_time)
                VALUES('{$bn}',".(int)$item['id'].",0,{$to},NOW())");
}

    exit('OK|' . $item[$i['itemname']] . ' ' . $action);
}

$patient = one("SELECT * FROM patients WHERE `{$p['rfiduid']}`='" . $db->real_escape_string($uid) . "' LIMIT 1");
if ($patient) {
    updatePatientSeen((int)$patient['id'], (int)$reader['id']);

    q("INSERT INTO scanlogs(uid, `{$sl['patientid']}`, `{$sl['tolocationid']}`, `{$sl['readerid']}`, `{$sl['actiontype']}`, notes)
       VALUES('" . $db->real_escape_string($uid) . "', " . (int)$patient['id'] . ", " . (int)$reader['id'] . ", '" . $db->real_escape_string($readerid) . "', 'patientscan', 'Patient seen at reader')");

    $sp = one("SELECT * FROM patientsafetyprofiles WHERE patientid=" . (int)$patient['id'] . " LIMIT 1");
    if ($sp) {
        if (!empty($sp['restrictedlocations']) && csvHasId($sp['restrictedlocations'], $reader['id'])) {
            create_alert('critical', 'safety', $patient['fullname'] . ' entered restricted location ' . $reader[$l['locationname']], 'index.php?page=safety');
            createTask('restricted-' . $patient['id'] . '-' . $reader['id'] . '-' . date('YmdHi'), 'safety', 'Restricted area breach', $patient['fullname'] . ' entered ' . $reader[$l['locationname']], (int)$patient['id'], 0, 'critical', date('Y-m-d H:i:s', time() + 600));
        }

        if (!empty($sp['allowedlocations']) && !csvHasId($sp['allowedlocations'], $reader['id'])) {
            create_alert('warning', 'safety', $patient['fullname'] . ' moved outside safe zone to ' . $reader[$l['locationname']], 'index.php?page=safety');
            createTask('safezone-' . $patient['id'] . '-' . $reader['id'] . '-' . date('YmdHi'), 'safety', 'Safe-zone deviation', $patient['fullname'] . ' moved to ' . $reader[$l['locationname']], (int)$patient['id'], 0, 'high', date('Y-m-d H:i:s', time() + 900));
        }

        if (($reader[$l['locationtype']] ?? '') === 'washroom') {
            createTask('washroom-' . $patient['id'] . '-' . date('YmdHi'), 'safety', 'Washroom watch', $patient['fullname'] . ' entered washroom area', (int)$patient['id'], 0, 'medium', date('Y-m-d H:i:s', time() + ((int)$sp['washroomlimitminutes'] * 60)));
        }

        if (($reader[$l['locationtype']] ?? '') === 'exit') {
            create_alert('critical', 'safety', $patient['fullname'] . ' reached exit reader', 'index.php?page=safety');
            createTask('exit-' . $patient['id'] . '-' . date('YmdHi'), 'safety', 'Exit proximity alert', $patient['fullname'] . ' scanned at exit point', (int)$patient['id'], 0, 'critical', date('Y-m-d H:i:s', time() + 300));
        }
    }

    exit('OK|Patient ' . $patient['fullname'] . ' scanned');
}

$staff = one("SELECT * FROM staffmembers WHERE rfiduid='" . $db->real_escape_string($uid) . "' LIMIT 1");
if ($staff) {
    q("INSERT INTO scanlogs(uid, `{$sl['staffid']}`, `{$sl['tolocationid']}`, `{$sl['readerid']}`, `{$sl['actiontype']}`, notes)
       VALUES('" . $db->real_escape_string($uid) . "', " . (int)$staff['id'] . ", " . (int)$reader['id'] . ", '" . $db->real_escape_string($readerid) . "', 'staffscan', 'Staff seen at reader')");
    exit('OK|Staff ' . $staff['fullname'] . ' scanned');
}

create_alert('warning', 'unknowntag', 'Unknown RFID tag scanned: ' . $uid, 'index.php?page=alerts');
createTask('unknown-' . $uid . '-' . date('YmdH'), 'ops', 'Unknown RFID tag', 'Unregistered tag ' . $uid . ' scanned at ' . $reader[$l['locationname']], 0, 0, 'medium', date('Y-m-d H:i:s', time() + 1800));
q("INSERT INTO scanlogs(uid, `{$sl['tolocationid']}`, `{$sl['readerid']}`, `{$sl['actiontype']}`, notes)
   VALUES('" . $db->real_escape_string($uid) . "', " . (int)$reader['id'] . ", '" . $db->real_escape_string($readerid) . "', 'unknown', 'Unknown tag')");
exit('UNKNOWN|Unregistered tag');