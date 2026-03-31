<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin','staff'], true)) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$allowed = ['pending','preparing','ready','completed','cancelled'];
$order_id = (int)($_POST['order_id'] ?? 0);
$status   = trim($_POST['status'] ?? '');

if ($order_id <= 0 || !in_array($status, $allowed, true)) {
    echo json_encode(['success'=>false,'message'=>'Invalid input.']); exit;
}

$uid = (int)$_SESSION['user_id'];
$st = mysqli_prepare($conn,'UPDATE orders SET status=?, processed_by=? WHERE id=?');
mysqli_stmt_bind_param($st,'sii',$status,$uid,$order_id);
$ok = mysqli_stmt_execute($st);
mysqli_stmt_close($st);
echo json_encode(['success'=>$ok,'message'=>$ok?'Updated.':'Update failed.']);
