<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.html'); exit; }
if ((int)($_SESSION['must_change_password'] ?? 0) === 1) {
    header('Location: change_password.php'); exit;
}
$role = $_SESSION['role'] ?? '';
header('Location: ' . ($role === 'admin' ? 'admin_dashboard.php' : 'store_dashboard.php'));
exit;
