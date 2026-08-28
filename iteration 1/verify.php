<?php
// ============================================================
// verify.php — RFID Scan Endpoint (called by ESP32/Arduino)
// Protocol: GET/POST with uid + reader_id parameters
// Response: STATUS|Message (pipe-separated)
// ============================================================
require_once 'core.php';
header('Content-Type: text/plain; charset=UTF-8');

$uid      = strtoupper(trim($_REQUEST['uid'] ?? ''));
$readerId = trim($_REQUEST['reader_id'] ?? '');

if (!$uid || !$readerId) {
    http_response_code(400);
    exit("ERROR|Missing uid or reader_id parameters");
}

// ---- 1. Authenticate Reader ----
$stmt = db()->prepare("SELECT id, location_name FROM locations WHERE reader_id=? AND is_active=1 LIMIT 1");
$stmt->bind_param('s', $readerId);
$stmt->execute();
$readerRes = $stmt->get_result();
if ($readerRes->num_rows === 0) {
    sys_log('warning', 'verify.php', "Unauthorized reader attempted scan: $readerId (UID: $uid)");
    exit("ERROR|Unauthorized Reader");
}
$reader = $readerRes->fetch_assoc();
$locId  = $reader['id'];
db()->query("UPDATE locations SET last_heartbeat=NOW() WHERE id=$locId");

// ---- 2. Multi-Reader Conflict Resolution (15-second debounce window) ----
$uidSafe = db()->real_escape_string($uid);
$rdSafe  = db()->real_escape_string($readerId);
$recent  = db()->query("SELECT reader_id FROM scan_logs WHERE uid='$uidSafe' AND scan_time >= NOW() - INTERVAL 15 SECOND ORDER BY scan_time DESC LIMIT 1")->fetch_assoc();
if ($recent && $recent['reader_id'] !== $readerId) {
    db()->query("INSERT INTO scan_logs (uid, reader_id, action_type) VALUES ('$uidSafe', '$rdSafe', 'conflict_suppressed')");
    sys_log('warning', 'verify.php', "Conflict suppressed: UID $uid competing readers $readerId vs {$recent['reader_id']}");
    exit("IGNORED|Conflict suppressed — duplicate read from adjacent reader");
}

// ---- 3. Lookup Item ----
$itemRes = db()->query("SELECT id, item_name, location_id, status, lifecycle, expiry_date FROM items WHERE uid='$uidSafe' LIMIT 1");

if ($itemRes->num_rows > 0) {
    $item   = $itemRes->fetch_assoc();
    $itemId = $item['id'];

    // ---- 4. Check for Active Cycle Count at this Location ----
    $countRes = db()->query("SELECT id FROM physical_counts WHERE location_id='$locId' AND status='in_progress' LIMIT 1");
    if ($countRes->num_rows > 0) {
        $countId = $countRes->fetch_assoc()['id'];
        db()->query("UPDATE physical_counts SET scanned_qty=scanned_qty+1 WHERE id=$countId");
        db()->query("INSERT INTO scan_logs (uid, item_id, to_location_id, reader_id, action_type) VALUES ('$uidSafe', $itemId, $locId, '$rdSafe', 'cycle_count')");
        db()->query("UPDATE items SET last_seen=NOW() WHERE id=$itemId");
        exit("COUNTED|{$item['item_name']}");
    }

    // ---- 5. Normal Mode — Location Transfer or Scan ----
    $prevLoc = $item['location_id'];
    $action  = ($prevLoc == $locId) ? 'scanned' : 'transfer';

    db()->query("UPDATE items SET location_id='$locId', status='in_stock', last_seen=NOW() WHERE id=$itemId");
    db()->query("INSERT INTO scan_logs (uid, item_id, from_location_id, to_location_id, reader_id, action_type)
                 VALUES ('$uidSafe', $itemId, ".($prevLoc ? $prevLoc : 'NULL').", $locId, '$rdSafe', '$action')");

    // ---- 6. Lifecycle & Expiry Alerts ----
    if ($item['lifecycle'] === 'expired') {
        create_alert('critical', "Expired item scanned: {$item['item_name']} (UID: $uid)", 'expiry_warning', $itemId);
        exit("EXPIRED|{$item['item_name']}|Item lifecycle is EXPIRED");
    }
    if ($item['lifecycle'] === 'expiring_soon') {
        exit("EXPIRING|{$item['item_name']}|Expiry within 90 days");
    }

    exit("FOUND|{$item['item_name']}|".($action === 'transfer' ? 'Transfer recorded' : 'Location confirmed'));

} else {
    // ---- Unknown Tag ----
    db()->query("INSERT INTO scan_logs (uid, reader_id, action_type) VALUES ('$uidSafe', '$rdSafe', 'scanned')");
    create_alert('critical', "Unknown RFID tag scanned: $uid at {$reader['location_name']}", 'unknown_tag');
    sys_log('warning', 'verify.php', "Unknown tag: $uid at reader $readerId");
    exit("UNKNOWN|Unregistered Tag — Alert created");
}
?>
