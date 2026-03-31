<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.html'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: staff_management.php'); exit;
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header('Location: staff_management.php?error='.urlencode('Invalid request token.')); exit;
}

$fullname = trim($_POST['fullname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($fullname==='' || $email==='' || $username==='' || strlen($password) < 8) {
    header('Location: staff_management.php?error='.urlencode('All fields are required and password must be at least 8 characters.')); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: staff_management.php?error='.urlencode('Invalid email address.')); exit;
}
if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $username)) {
    header('Location: staff_management.php?error='.urlencode('Username may only contain letters, numbers, dots, hyphens, and underscores.')); exit;
}

$chk = mysqli_prepare($conn,'SELECT id FROM users WHERE username=? OR email=?');
mysqli_stmt_bind_param($chk,'ss',$username,$email);
mysqli_stmt_execute($chk);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
mysqli_stmt_close($chk);

if ($existing) {
    header('Location: staff_management.php?error='.urlencode('A user with that username or email already exists.')); exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'staff';
$must = 1;
$ins = mysqli_prepare($conn,'INSERT INTO users (fullname,email,username,password,must_change_password,role) VALUES (?,?,?,?,?,?)');
mysqli_stmt_bind_param($ins,'ssssis',$fullname,$email,$username,$hash,$must,$role);
$ok = mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);

header('Location: staff_management.php?'.($ok
    ? 'success='.urlencode("Staff account for $fullname created successfully.")
    : 'error='.urlencode('Failed to create staff account. Please try again.')
));
exit;
