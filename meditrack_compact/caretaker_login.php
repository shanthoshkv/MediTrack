<?php
require_once 'core.php';
if (session_status()===PHP_SESSION_NONE) session_start();
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $u = posted('username');
    $p = posted('password');
    $st = $db->prepare("SELECT ca.*, p.fullname AS patient_name, p.id AS pid
                        FROM caretaker_accounts ca
                        JOIN patients p ON p.id=ca.patient_id
                        WHERE ca.username=? AND ca.is_active=1 LIMIT 1");
    $st->bind_param('s',$u); $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if ($row && password_verify($p, $row['password'])) {
        $_SESSION['ct_account_id'] = $row['id'];
        $_SESSION['ct_patient_id'] = $row['pid'];
        $_SESSION['ct_patient_name'] = $row['patient_name'];
        header('Location: caretaker_home.php'); exit;
    }
    $msg = 'Invalid credentials';
}
?><!DOCTYPE html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MediTrack — Caretaker Login</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#050c14;color:#edf3ff;font-family:Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{background:#0b1017;border:1px solid #1a2636;border-radius:18px;padding:36px;width:100%;max-width:400px}
h2{margin-bottom:6px;font-size:22px;color:#56a8ff}
.sub{color:#90a4c2;font-size:13px;margin-bottom:24px}
label{display:block;font-size:13px;color:#90a4c2;margin-bottom:5px}
input{width:100%;background:#101722;border:1px solid #1a2636;border-radius:10px;padding:12px;color:#edf3ff;font-size:14px;margin-bottom:16px}
button{width:100%;background:#56a8ff;color:#000;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer}
.err{background:rgba(251,113,133,.1);border:1px solid #fb7185;color:#fb7185;border-radius:10px;padding:12px;margin-bottom:16px;font-size:13px}
</style></head><body>
<div class="card">
    <h2>MediTrack</h2>
    <div class="sub">Caretaker Portal — Patient Updates</div>
    <?php if ($msg): ?><div class="err"><?= e($msg) ?></div><?php endif; ?>
    <form method="post">
        <label>Username</label><input name="username" required autocomplete="username">
        <label>Password</label><input type="password" name="password" required autocomplete="current-password">
        <button type="submit">Login</button>
    </form>
</div>
</body></html>