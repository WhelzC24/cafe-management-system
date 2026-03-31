<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit; }
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>'Invalid token.']); exit; }

$user_id = (int)$_SESSION['user_id'];
$must_change = (int)($_SESSION['must_change_password'] ?? 0);
$new_pass    = $_POST['new_password'] ?? '';
$confirm     = $_POST['confirm_password'] ?? '';
$current     = $_POST['current_password'] ?? '';

if (strlen($new_pass) < 8) { echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters.']); exit; }
if ($new_pass !== $confirm) { echo json_encode(['success'=>false,'message'=>'Passwords do not match.']); exit; }

if (!$must_change) {
    $st = mysqli_prepare($conn,'SELECT password FROM users WHERE id=?');
    mysqli_stmt_bind_param($st,'i',$user_id);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$row || !password_verify($current, $row['password'])) {
        echo json_encode(['success'=>false,'message'=>'Current password is incorrect.']); exit;
    }
}

$hash = password_hash($new_pass, PASSWORD_DEFAULT);
$st = mysqli_prepare($conn,'UPDATE users SET password=?, must_change_password=0 WHERE id=?');
mysqli_stmt_bind_param($st,'si',$hash,$user_id);
$ok = mysqli_stmt_execute($st);
mysqli_stmt_close($st);

if ($ok) {
    $_SESSION['must_change_password'] = 0;
    $role = $_SESSION['role'] ?? '';
    $redirect = $role === 'admin' ? 'admin_dashboard.php' : 'store_dashboard.php';
    echo json_encode(['success'=>true,'message'=>'Password updated successfully!','redirect'=>$redirect]);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed to update password.']);
}
