<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'staff'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token. Refresh and try again.']);
    exit;
}

$upload_dir = __DIR__ . '/uploads';

if (!is_dir($upload_dir)) {
    echo json_encode([
        'success' => false,
        'message' => 'uploads/ folder is missing.'
    ]);
    exit;
}

$mode = @fileperms($upload_dir);
$mode_text = $mode ? substr(sprintf('%o', $mode), -4) : 'unknown';

if (!is_writable($upload_dir)) {
    echo json_encode([
        'success' => false,
        'message' => 'uploads/ is not writable by PHP (mode ' . $mode_text . ').'
    ]);
    exit;
}

$probe_name = '.upload_probe_' . bin2hex(random_bytes(8)) . '.tmp';
$probe_path = $upload_dir . '/' . $probe_name;
$bytes = @file_put_contents($probe_path, 'upload-self-test');

if ($bytes === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Write probe failed even though directory reports writable (mode ' . $mode_text . ').'
    ]);
    exit;
}

$deleted = @unlink($probe_path);
if (!$deleted) {
    echo json_encode([
        'success' => false,
        'message' => 'Write probe succeeded but cleanup delete failed. Check directory ownership/ACLs.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'uploads/ passed checks (exists, writable, write/delete probe). Mode: ' . $mode_text . '.'
]);
