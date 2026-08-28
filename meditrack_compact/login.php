<?php
require_once 'core.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = posted('username');
    $p = posted('password');

    $st = $db->prepare("SELECT * FROM admins WHERE username=? LIMIT 1");
    $st->bind_param('s', $u);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if ($row && password_verify($p, $row['password'])) {
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['adminid'] = $row['id'];
        $_SESSION['admin_name'] = $row['fullname'] ?? $row['username'];
        $_SESSION['fullname'] = $row['fullname'] ?? $row['username'];
        header('Location: index.php');
        exit;
    }
    $msg = 'Invalid credentials';
}

render_header('Login', 'dashboard', false);
if ($msg) echo '<div class="banner">' . e($msg) . '</div>';
?>
<div style="max-width:480px;margin:40px auto">
    <div class="card">
        <h3><i class="fas fa-lock"></i> Admin Login</h3>
        <form method="post" class="grid">
            <div>
                <label>Username</label>
                <input class="inp" name="username" required>
            </div>
            <div>
                <label>Password</label>
                <input class="inp" type="password" name="password" required>
            </div>
            <button class="btn primary"><i class="fas fa-right-to-bracket"></i> Login</button>
        </form>
    </div>
</div>
<?php render_footer(false); ?>