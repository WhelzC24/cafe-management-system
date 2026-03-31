<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin','staff'], true)) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'Invalid token.']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid product ID.']); exit; }

// Fetch the image_url BEFORE deleting so we can clean up any uploaded file
$img_st = mysqli_prepare($conn, 'SELECT image_url FROM products WHERE id=?');
mysqli_stmt_bind_param($img_st, 'i', $id);
mysqli_stmt_execute($img_st);
$img_row = mysqli_fetch_assoc(mysqli_stmt_get_result($img_st));
mysqli_stmt_close($img_st);
$existing_image = $img_row['image_url'] ?? '';

// Delete the product record
$st = mysqli_prepare($conn, 'DELETE FROM products WHERE id=?');
mysqli_stmt_bind_param($st, 'i', $id);
$ok = mysqli_stmt_execute($st);
mysqli_stmt_close($st);

// Only delete the local file AFTER a successful DB delete
if ($ok && $existing_image && strpos($existing_image, 'uploads/') === 0) {
    $file_path = __DIR__ . '/' . $existing_image;
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
}

echo json_encode(['success'=>$ok, 'message'=>$ok ? 'Product deleted.' : 'Delete failed.']);
