<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid method.']); exit;
}

$target_id = (int)($_POST['user_id'] ?? 0);
if ($target_id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid user.']); exit; }

// Don't allow admin to reset own account this way
if ($target_id === (int)$_SESSION['user_id']) {
    echo json_encode(['success'=>false,'message'=>'Use Change Password to update your own password.']); exit;
}

$new_hash = password_hash('12345', PASSWORD_DEFAULT);
$st = mysqli_prepare($conn,'UPDATE users SET password=?, must_change_password=1 WHERE id=? AND role != "admin"');
mysqli_stmt_bind_param($st,'si',$new_hash,$target_id);
$ok = mysqli_stmt_execute($st);
$affected = mysqli_stmt_affected_rows($st);
mysqli_stmt_close($st);

if ($ok && $affected > 0) {
    echo json_encode(['success'=>true,'message'=>'Password reset to 12345. User must change it on next login.']);
} else {
    echo json_encode(['success'=>false,'message'=>'Reset failed or user not found.']);
}
