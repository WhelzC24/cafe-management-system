<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false]); exit; }

$user_id = (int)$_SESSION['user_id'];
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS notification_reads (
        user_id INT PRIMARY KEY,
        last_seen_order_id INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_notification_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

function save_last_seen(mysqli $conn, int $user_id, int $last_seen): bool {
    $st = mysqli_prepare($conn, '
        INSERT INTO notification_reads (user_id, last_seen_order_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE last_seen_order_id = VALUES(last_seen_order_id)
    ');
    if (!$st) return false;
    mysqli_stmt_bind_param($st, 'ii', $user_id, $last_seen);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return $ok;
}

if (!empty($_POST['mark_all'])) {
    // Set to absolute maximum ID in database so nothing historical pops up
    $res = mysqli_query($conn, "SELECT MAX(id) as max_id FROM orders");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $max = (int)($row['max_id'] ?? 0);
        $_SESSION['last_seen_order_id'] = $max;
        save_last_seen($conn, $user_id, $max);
        echo json_encode(['ok'=>true, 'max_id'=>$max]);
        exit;
    }
}

$max_id = (int)($_POST['max_id'] ?? 0);
if ($max_id > 0) {
    $_SESSION['last_seen_order_id'] = $max_id;
    save_last_seen($conn, $user_id, $max_id);
}
echo json_encode(['ok'=>true]);
