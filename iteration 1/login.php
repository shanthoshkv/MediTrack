<?php
require_once 'core.php';

$adminCount = (int)db()->query("SELECT COUNT(*) AS c FROM admins")->fetch_assoc()['c'];
if ($adminCount === 0) { header('Location: create_admin.php'); exit; }
if (is_admin()) { header('Location: index.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $stmt = db()->prepare("SELECT * FROM admins WHERE username=? AND status='active' LIMIT 1");
    $stmt->bind_param('s', $u);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $admin = $res->fetch_assoc();
        if (password_verify($p, $admin['password'])) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            db()->query("UPDATE admins SET last_login=NOW() WHERE id={$admin['id']}");
            sys_log('info', 'login.php', "Successful login: {$admin['username']}");
            header('Location: index.php'); exit;
        } else {
            $err = 'Incorrect password.';
            sys_log('warning', 'login.php', "Failed login attempt for username: $u");
        }
    } else {
        $err = 'Username not found or account is disabled.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | SmartRF Inventory</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root {
    --bg:#070b12; --surface:#111827; --border:#1e2d45; --border2:#2a3f5f;
    --text:#e8edf5; --sub:#6b7fa0; --accent:#3b82f6; --accent2:#8b5cf6;
    --green:#10b981; --red:#ef4444;
    --grad:linear-gradient(135deg, #3b82f6, #8b5cf6, #06b6d4);
}
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
body {
    font-family:'Syne',sans-serif;
    background:var(--bg); color:var(--text);
    min-height:100vh; display:flex;
    align-items:center; justify-content:center;
    padding:20px;
}
body::before {
    content:''; position:fixed;
    top:-20%; right:-10%; width:50vw; height:100vh;
    background:radial-gradient(ellipse, rgba(139,92,246,0.05) 0%, transparent 60%);
    pointer-events:none;
}
body::after {
    content:''; position:fixed;
    bottom:-20%; left:-10%; width:50vw; height:100vh;
    background:radial-gradient(ellipse, rgba(59,130,246,0.04) 0%, transparent 60%);
    pointer-events:none;
}
.wrap {
    width:100%; max-width:420px;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:20px; padding:40px 36px;
    box-shadow:0 24px 80px rgba(0,0,0,0.5);
    position:relative; overflow:hidden;
    animation: slideUp 0.5s cubic-bezier(0.16,1,0.3,1) both;
}
@keyframes slideUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:none} }
.wrap::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:var(--grad); }
.brand {
    text-align:center; margin-bottom:28px;
}
.brand .icon {
    width:64px; height:64px;
    background:var(--grad);
    border-radius:18px; margin:0 auto 16px;
    display:flex; align-items:center; justify-content:center;
    font-size:26px; color:#fff;
    box-shadow:0 8px 32px rgba(59,130,246,0.25);
}
.brand h1 { font-size:20px; font-weight:800; margin-bottom:5px; }
.brand p { font-size:12px; color:var(--sub); }
.live {
    display:inline-flex; align-items:center; gap:8px;
    background:rgba(16,185,129,0.08);
    border:1px solid rgba(16,185,129,0.2);
    border-radius:50px; padding:5px 14px;
    font-size:11px; color:#6ee7b7;
    font-family:'DM Mono',monospace;
    margin:0 auto 28px; width:fit-content; display:flex;
}
.dot { width:7px; height:7px; background:var(--green); border-radius:50%; animation:pulse 1.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
.err {
    background:rgba(239,68,68,0.08);
    border:1px solid rgba(239,68,68,0.2);
    color:#fca5a5; border-radius:10px;
    padding:11px 14px; font-size:12px;
    margin-bottom:20px; display:flex; align-items:center; gap:8px;
}
.field { margin-bottom:18px; }
label { display:block; font-size:11px; font-family:'DM Mono',monospace; color:var(--sub); letter-spacing:0.8px; text-transform:uppercase; margin-bottom:8px; }
input {
    width:100%; padding:12px 14px;
    background:rgba(7,11,18,0.8);
    border:1px solid var(--border2);
    border-radius:10px; color:var(--text);
    font-family:'Syne',sans-serif; font-size:13px;
    outline:none; transition:border-color 0.15s, box-shadow 0.15s;
}
input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
.btn {
    width:100%; padding:13px;
    background:var(--grad); color:#fff;
    border:none; border-radius:10px;
    font-family:'Syne',sans-serif; font-size:14px; font-weight:700;
    cursor:pointer; display:flex; align-items:center;
    justify-content:center; gap:10px;
    transition:opacity 0.15s, transform 0.1s;
}
.btn:hover { opacity:0.88; }
.btn:active { transform:scale(0.98); }
.live-link {
    display:flex; align-items:center; justify-content:center; gap:8px;
    margin-top:22px; font-size:12px; color:var(--sub);
    text-decoration:none; transition:color 0.15s;
}
.live-link:hover { color:var(--accent); }
@media(max-width:480px) { .wrap { padding:28px 22px; } }
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <div class="icon"><i class="fas fa-satellite-dish"></i></div>
        <h1>SmartRF Inventory</h1>
        <p>RFID Asset Management Platform</p>
    </div>
    <div style="display:flex;justify-content:center">
        <div class="live"><span class="dot"></span>System Online</div>
    </div>

    <?php if ($err): ?>
    <div class="err"><i class="fas fa-circle-exclamation"></i><?= h($err) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
        <div class="field">
            <label>Username</label>
            <input type="text" name="username" required autocomplete="username" placeholder="Enter admin username">
        </div>
        <div class="field">
            <label>Password</label>
            <input type="password" name="password" required autocomplete="current-password" placeholder="Enter password">
        </div>
        <button type="submit" class="btn"><i class="fas fa-right-to-bracket"></i>Login to Admin Panel</button>
    </form>

    <a href="live_display.php" class="live-link">
        <i class="fas fa-tv"></i> Open Public Live Display
    </a>
</div>
</body>
</html>
