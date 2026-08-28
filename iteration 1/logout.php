<?php
require_once 'core.php';
if (is_admin()) {
    sys_log('info', 'logout.php', "Admin logged out: " . h($_SESSION['admin_name'] ?? 'unknown'));
}
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>
