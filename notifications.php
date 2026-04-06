<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','staff'], true)) {
    echo json_encode(['count'=>0,'orders'=>[]]);
    exit;
}
$last_id = (int)($_SESSION['last_seen_order_id'] ?? 0);

if ($last_id === 0) {
    // Failsafe: If no session memory exists, don't spam historical orders. Subtly initialize to current max.
    $resMax = mysqli_query($conn, "SELECT MAX(id) as max_id FROM orders");
    if ($resMax) {
        $rowMax = mysqli_fetch_assoc($resMax);
        $last_id = (int)($rowMax['max_id'] ?? 0);
        $_SESSION['last_seen_order_id'] = $last_id;
    }
}
$st = mysqli_prepare($conn,
    "SELECT o.id, o.customer_name, o.total_amount, o.created_at,
     GROUP_CONCAT(CONCAT(oi.quantity,'x ',oi.product_name) ORDER BY oi.id SEPARATOR ', ') AS items
     FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id
     WHERE o.id > ? GROUP BY o.id ORDER BY o.id ASC LIMIT 10");
mysqli_stmt_bind_param($st,'i',$last_id);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
$orders = []; $max_id = $last_id;
while ($row = mysqli_fetch_assoc($res)) {
    $orders[] = [
        'id'       => (int)$row['id'],
        'customer' => $row['customer_name'],
        'total'    => number_format((float)$row['total_amount'], 2),
        'items'    => $row['items'] ?? '',
        'time'     => date('g:i A', strtotime($row['created_at'])),
    ];
    if ((int)$row['id'] > $max_id) $max_id = (int)$row['id'];
}
mysqli_stmt_close($st);
echo json_encode(['count'=>count($orders),'orders'=>$orders,'max_id'=>$max_id]);
