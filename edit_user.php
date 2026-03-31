<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.html'); exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: admin_dashboard.php'); exit; }

$st = mysqli_prepare($conn,'SELECT id,fullname,email,username,role FROM users WHERE id=?');
mysqli_stmt_bind_param($st,'i',$id);
mysqli_stmt_execute($st);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);
if (!$user) { header('Location: admin_dashboard.php'); exit; }

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User — Cozy Corner Café</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-page-body">
    <div class="form-card">
        <h2>✏️ Edit User</h2>
        <p>Update account details for <strong><?= htmlspecialchars($user['username']) ?></strong>.</p>
        <?php if ($error): ?><div class="error-msg">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success-msg">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="POST" action="update_user.php">
            <input type="hidden" name="id" value="<?= $user['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="field-stack" style="margin-bottom:1rem;">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required>
            </div>
            <div class="field-stack" style="margin-bottom:1rem;">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="field-stack" style="margin-bottom:1rem;">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            <?php if ($user['role'] !== 'admin'): ?>
            <div class="field-stack" style="margin-bottom:1rem;">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="staff" <?= $user['role']==='staff'?'selected':'' ?>>Staff</option>
                    <option value="user"  <?= $user['role']==='user'?'selected':'' ?>>User</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="btn-row">
                <button type="submit" class="menu-btn primary">💾 Save Changes</button>
                <a href="admin_dashboard.php" class="menu-btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
