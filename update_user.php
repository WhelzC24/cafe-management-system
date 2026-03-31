<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.html'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php'); exit;
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header('Location: admin_dashboard.php?error='.urlencode('Invalid token.')); exit;
}

$id       = (int)($_POST['id'] ?? 0);
$fullname = trim($_POST['fullname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$role     = trim($_POST['role'] ?? 'staff');

if ($id <= 0 || $fullname==='' || $email==='' || $username==='') {
    header('Location: edit_user.php?id='.$id.'&error='.urlencode('All fields are required.')); exit;
}

// Check self: protect admin role
$selfst = mysqli_prepare($conn,'SELECT role FROM users WHERE id=?');
mysqli_stmt_bind_param($selfst,'i',$id);
mysqli_stmt_execute($selfst);
$self = mysqli_fetch_assoc(mysqli_stmt_get_result($selfst));
mysqli_stmt_close($selfst);
if ($self && $self['role']==='admin') $role = 'admin'; // preserve admin role

$st = mysqli_prepare($conn,'UPDATE users SET fullname=?,email=?,username=?,role=? WHERE id=?');
mysqli_stmt_bind_param($st,'ssssi',$fullname,$email,$username,$role,$id);
$ok = mysqli_stmt_execute($st);
mysqli_stmt_close($st);
header('Location: '.($ok
    ? 'admin_dashboard.php?success='.urlencode('User updated successfully.')
    : 'edit_user.php?id='.$id.'&error='.urlencode('Update failed. Username or email may already exist.')
));
exit;
