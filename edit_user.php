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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: linear-gradient(135deg, #0f1f35 0%, #1e3a5f 50%, #2563eb22 100%); min-height: 100vh; }
        .form-page-body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .form-card {
            background: #fff; border-radius: 24px; padding: 2.75rem 2.5rem;
            box-shadow: 0 30px 80px rgba(0,0,0,0.35);
            width: 100%; max-width: 500px;
            animation: cardEntrance 0.6s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        @keyframes cardEntrance { from { opacity:0; transform:translateY(24px) scale(0.97); } to { opacity:1; transform:translateY(0) scale(1); } }
        .card-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, #1e3a5f, #2563eb);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 20px rgba(37,99,235,0.35);
        }
        .card-title { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: #1e3a5f; margin-bottom: 0.35rem; }
        .card-sub { color: #64748b; font-size: 0.88rem; margin-bottom: 1.75rem; line-height: 1.5; }
        .user-chip {
            background: #dbeafe; color: #1e40af;
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.3rem 0.75rem; border-radius: 50px;
            font-size: 0.8rem; font-weight: 600; margin-bottom: 1.5rem;
        }
        .field-stack { margin-bottom: 1.1rem; }
        .field-stack label { display: block; font-size: 0.8rem; font-weight: 600; color: #1e3a5f; margin-bottom: 0.35rem; letter-spacing: 0.02em; }
        .field-stack input, .field-stack select {
            width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0;
            border-radius: 10px; font-size: 0.9rem; background: #f8faff;
            color: #1e293b; outline: none; font-family: 'Inter', sans-serif;
            transition: border-color 0.25s, box-shadow 0.25s;
        }
        .field-stack input:focus, .field-stack select:focus {
            border-color: #2563eb; background: #fff;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .error-msg { background: #fee2e2; color: #991b1b; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.88rem; margin-bottom: 1rem; border: 1px solid #fca5a5; }
        .success-msg { background: #dcfce7; color: #166534; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.88rem; margin-bottom: 1rem; border: 1px solid #86efac; }
        .btn-row { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        .btn-primary {
            flex: 1; padding: 0.85rem; border-radius: 12px; border: none;
            background: linear-gradient(135deg, #1e3a5f, #2563eb);
            color: #fff; font-size: 0.95rem; font-weight: 600;
            cursor: pointer; transition: all 0.25s; font-family: 'Inter', sans-serif;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(37,99,235,0.4); }
        .btn-secondary {
            padding: 0.85rem 1.5rem; border-radius: 12px;
            background: #f1f5f9; color: #475569;
            border: 1.5px solid #e2e8f0; font-size: 0.9rem; font-weight: 600;
            cursor: pointer; text-decoration: none; display: inline-flex;
            align-items: center; justify-content: center; transition: all 0.25s; font-family: 'Inter', sans-serif;
        }
        .btn-secondary:hover { border-color: #2563eb; color: #2563eb; }
        @keyframes fadeUp { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }
        .field-stack:nth-child(1) { animation: fadeUp 0.4s ease both; animation-delay: 0.1s; }
        .field-stack:nth-child(2) { animation: fadeUp 0.4s ease both; animation-delay: 0.18s; }
        .field-stack:nth-child(3) { animation: fadeUp 0.4s ease both; animation-delay: 0.26s; }
        .field-stack:nth-child(4) { animation: fadeUp 0.4s ease both; animation-delay: 0.34s; }
        .btn-row { animation: fadeUp 0.4s ease both; animation-delay: 0.42s; }
    </style>
</head>
<body>
<div class="form-page-body">
    <div class="form-card">
        <div class="card-icon">✏️</div>
        <h2 class="card-title">Edit User</h2>
        <p class="card-sub">Update account details for this user.</p>
        <div class="user-chip">👤 <?= htmlspecialchars($user['username']) ?> &nbsp;·&nbsp; <span class="role-badge <?= $user['role'] ?>" style="font-size:0.7rem;"><?= $user['role'] ?></span></div>

        <?php if ($error):   ?><div class="error-msg">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success-msg">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

        <form method="POST" action="update_user.php">
            <input type="hidden" name="id" value="<?= $user['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="field-stack">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required>
            </div>
            <div class="field-stack">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="field-stack">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            <?php if ($user['role'] !== 'admin'): ?>
            <div class="field-stack">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="staff" <?= $user['role']==='staff'?'selected':'' ?>>Staff</option>
                    <option value="user"  <?= $user['role']==='user'?'selected':'' ?>>User</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="btn-row">
                <button type="submit" class="btn-primary">💾 Save Changes</button>
                <a href="admin_dashboard.php" class="btn-secondary">← Back</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
