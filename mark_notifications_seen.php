<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false]); exit; }

if (!empty($_POST['mark_all'])) {
    // Set to absolute maximum ID in database so nothing historical pops up
    $res = mysqli_query($conn, "SELECT MAX(id) as max_id FROM orders");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        if (!empty($row['max_id'])) {
            $_SESSION['last_seen_order_id'] = (int)$row['max_id'];
            echo json_encode(['ok'=>true, 'max_id'=>(int)$row['max_id']]);
            exit;
        }
    }
}

$max_id = (int)($_POST['max_id'] ?? 0);
if ($max_id > 0) $_SESSION['last_seen_order_id'] = $max_id;
echo json_encode(['ok'=>true]);
