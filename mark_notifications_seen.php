<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false]); exit; }
$max_id = (int)($_POST['max_id'] ?? 0);
if ($max_id > 0) $_SESSION['last_seen_order_id'] = $max_id;
echo json_encode(['ok'=>true]);
