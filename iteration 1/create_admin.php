<?php
require_once 'core.php';

$adminCount = (int)db()->query("SELECT COUNT(*) AS c FROM admins")->fetch_assoc()['c'];
if ($adminCount > 0 && !is_admin()) { header('Location: login.php'); exit; }
if ($adminCount > 0 && !is_super_admin()) { http_response_code(403); exit('<h2>Access denied. Super Admin role required.</h2>'); }

$err = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full = trim($_POST['full_name'] ?? '');
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $conf = $_POST['confirm'] ?? '';
    $role = $_POST['role'] ?? 'viewer';
    $email = trim($_POST['email'] ?? '');

    if (!$full || !$user || !$pass) {
        $err = 'Please fill all required fields.';
    } elseif (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif ($pass !== $conf) {
        $err = 'Passwords do not match.';
    } else {
        $chk = db()->real_escape_string($user);
        if (db()->query("SELECT id FROM admins WHERE username='$chk' LIMIT 1")->num_rows > 0) {
            $err = 'Username already exists.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $fn   = db()->real_escape_string($full);
            $un   = db()->real_escape_string($user);
            $rl   = db()->real_escape_string($role);
            $em   = db()->real_escape_string($email);
            db()->query("INSERT INTO admins (full_name, username, password, role, email) VALUES ('$fn', '$un', '$hash', '$rl', '$em')");
            sys_log('info', 'create_admin.php', "New admin created: $un ($rl)");
            $success = 'Administrator account created successfully. <a href="login.php" style="color:#93c5fd">Click here to login.</a>';
        }
    }
}
$isSetup = ($adminCount === 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Admin | SmartRF</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--bg:#070b12;--surface:#111827;--border:#1e2d45;--border2:#2a3f5f;--text:#e8edf5;--sub:#6b7fa0;--accent:#3b82f6;--green:#10b981;--red:#ef4444;--grad:linear-gradient(135deg,#3b82f6,#8b5cf6,#06b6d4);}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Syne',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{width:100%;max-width:480px;background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:38px 34px;box-shadow:0 24px 80px rgba(0,0,0,0.5);position:relative}
.box::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--grad);border-radius:20px 20px 0 0}
h2{font-size:20px;font-weight:800;margin-bottom:6px}
.sub{color:var(--sub);font-size:12px;margin-bottom:24px}
.field{margin-bottom:16px}
label{display:block;font-size:11px;font-family:'DM Mono',monospace;color:var(--sub);letter-spacing:0.8px;text-transform:uppercase;margin-bottom:7px}
input,select{width:100%;padding:11px 14px;background:rgba(7,11,18,0.8);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-family:'Syne',sans-serif;font-size:13px;outline:none;transition:border-color 0.15s}
input:focus,select:focus{border-color:var(--accent)}
.btn{width:100%;padding:13px;background:var(--grad);color:#fff;border:none;border-radius:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:700;cursor:pointer;margin-top:6px}
.err{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#fca5a5;border-radius:10px;padding:11px 14px;font-size:12px;margin-bottom:16px;display:flex;gap:8px;align-items:center}
.ok{background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);color:#6ee7b7;border-radius:10px;padding:11px 14px;font-size:12px;margin-bottom:16px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
</style>
</head>
<body>
<div class="box">
    <h2><i class="fas fa-user-shield" style="color:#8b5cf6;margin-right:10px"></i><?= $isSetup ? 'Initial Setup' : 'Create Admin' ?></h2>
    <p class="sub"><?= $isSetup ? 'No administrators exist. Create the first super admin to get started.' : 'Create a new administrator account.' ?></p>

    <?php if ($err): ?><div class="err"><i class="fas fa-circle-exclamation"></i><?= h($err) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="ok"><?= $success ?></div><?php endif; ?>

    <form method="POST">
        <div class="row">
            <div class="field"><label>Full Name *</label><input type="text" name="full_name" required placeholder="Full name"></div>
            <div class="field"><label>Username *</label><input type="text" name="username" autocomplete="off" required placeholder="Login username"></div>
        </div>
        <div class="field"><label>Email</label><input type="email" name="email" placeholder="admin@company.com"></div>
        <div class="field"><label>Role *</label>
            <select name="role" <?= $isSetup ? 'disabled' : '' ?>>
                <option value="super_admin">Super Admin</option>
                <option value="manager">Manager</option>
                <option value="viewer">Viewer</option>
            </select>
            <?php if ($isSetup): ?><input type="hidden" name="role" value="super_admin"><?php endif; ?>
        </div>
        <div class="row">
            <div class="field"><label>Password *</label><input type="password" name="password" required placeholder="Min. 8 characters"></div>
            <div class="field"><label>Confirm *</label><input type="password" name="confirm" required placeholder="Repeat password"></div>
        </div>
        <button type="submit" class="btn"><i class="fas fa-user-plus" style="margin-right:8px"></i>Create Account</button>
    </form>
</div>
</body>
</html>
