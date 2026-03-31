<?php
header('Content-Type: application/json');
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['success'=>false,'message'=>'Invalid data.']); exit; }

$name  = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$notes = trim($input['notes'] ?? '');
$items = $input['items'] ?? [];
$total = (float)($input['total'] ?? 0);

if ($name === '' || $phone === '' || !$items) {
    echo json_encode(['success'=>false,'message'=>'Name, phone, and at least one item are required.']); exit;
}

mysqli_begin_transaction($conn);
try {
    $os = mysqli_prepare($conn,'INSERT INTO orders (customer_name,customer_email,customer_phone,notes,total_amount,status) VALUES (?,?,?,?,?,?)');
    $status = 'pending';
    mysqli_stmt_bind_param($os,'ssssds',$name,$email,$phone,$notes,$total,$status);
    if (!mysqli_stmt_execute($os)) throw new Exception('Order insert failed.');
    $order_id = mysqli_insert_id($conn);
    mysqli_stmt_close($os);

    $is = mysqli_prepare($conn,'INSERT INTO order_items (order_id,product_id,product_name,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?)');
    foreach ($items as $item) {
        $pid   = (int)($item['product_id'] ?? 0);
        $pname = substr(trim($item['product_name'] ?? ''), 0, 120);
        $qty   = (int)($item['quantity'] ?? 1);
        $unit  = (float)($item['unit_price'] ?? 0);
        $line  = (float)($item['line_total'] ?? $qty * $unit);
        mysqli_stmt_bind_param($is,'iisidd',$order_id,$pid,$pname,$qty,$unit,$line);
        if (!mysqli_stmt_execute($is)) throw new Exception('Item insert failed.');
    }
    mysqli_stmt_close($is);
    mysqli_commit($conn);
    echo json_encode(['success'=>true,'order_id'=>$order_id]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success'=>false,'message'=>'Could not place order. Please try again.']);
}
mysqli_close($conn);
