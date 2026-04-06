<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','staff'], true)) {
    echo json_encode(['count'=>0,'orders'=>[]]);
    exit;
}
$user_id = (int)$_SESSION['user_id'];

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS notification_reads (
        user_id INT PRIMARY KEY,
        last_seen_order_id INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_notification_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$last_id = 0;
$stRead = mysqli_prepare($conn, 'SELECT last_seen_order_id FROM notification_reads WHERE user_id=? LIMIT 1');
if ($stRead) {
    mysqli_stmt_bind_param($stRead, 'i', $user_id);
    mysqli_stmt_execute($stRead);
    $resRead = mysqli_stmt_get_result($stRead);
    $rowRead = $resRead ? mysqli_fetch_assoc($resRead) : null;
    mysqli_stmt_close($stRead);

    if ($rowRead) {
        $last_id = (int)($rowRead['last_seen_order_id'] ?? 0);
    } else {
        // First run for this user: baseline to current max to avoid flooding historical orders.
        $resMax = mysqli_query($conn, "SELECT MAX(id) AS max_id FROM orders");
        $rowMax = $resMax ? mysqli_fetch_assoc($resMax) : null;
        $last_id = (int)($rowMax['max_id'] ?? 0);

        $stInsert = mysqli_prepare($conn, '
            INSERT INTO notification_reads (user_id, last_seen_order_id)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE last_seen_order_id = VALUES(last_seen_order_id)
        ');
        if ($stInsert) {
            mysqli_stmt_bind_param($stInsert, 'ii', $user_id, $last_id);
            mysqli_stmt_execute($stInsert);
            mysqli_stmt_close($stInsert);
        }
    }
}

$_SESSION['last_seen_order_id'] = $last_id;
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
echo json_encode(['count'=>count($orders),'orders'=>$orders,'max_id'=>$max_id,'last_seen'=>$last_id]);
