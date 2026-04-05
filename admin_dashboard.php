<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// Harden access control: verify role from DB, not only from session.
$uid = (int)$_SESSION['user_id'];
$roleFromDb = null;
$st = mysqli_prepare($conn, 'SELECT role FROM users WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($st, 'i', $uid);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
if ($res) {
    $row = mysqli_fetch_assoc($res);
    $roleFromDb = $row['role'] ?? null;
}
mysqli_stmt_close($st);

if ($roleFromDb !== 'admin') {
    http_response_code(403);
    header('Location: login.html');
    exit;
}

$result = mysqli_query($conn, 'SELECT id, fullname, email, username, role, date_registered FROM users ORDER BY id ASC');

// Quick stats
$total_users   = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS c FROM users'))['c'];
$total_staff   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='staff'"))['c'];
$total_orders  = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS c FROM orders'))['c'];
$today_orders  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE()"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Cozy Corner Café</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-dashboard">
    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:var(--brown-dark);color:#fff;">
                    <h5 class="modal-title">🔑 Reset User Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reset password for <strong id="resetUsername"></strong>?</p>
                    <p class="text-muted small">The password will be reset to <strong>12345</strong>. The user must change it on next login.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="menu-btn secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="menu-btn danger" id="confirmResetBtn" onclick="submitResetPassword()">Reset Password</button>
                </div>
            </div>
        </div>
    </div>

    <div id="alertContainer"></div>

    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>☕ Admin Dashboard</h1>
                <p class="admin-subtitle">User management for Cozy Corner Café staff system</p>
            </div>
            <div class="dashboard-actions">
                <a href="store_dashboard.php" class="menu-btn secondary">📦 Store Dashboard</a>
                <a href="staff_management.php" class="menu-btn primary">👥 Staff Management</a>
                <a href="cafe/index.html" class="btn-link-light" target="_blank">🌐 View Café Site</a>
                <a href="logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">👥</span>
                <div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= $total_users ?></div>
                </div>
            </div>
            <div class="stat-card green">
                <span class="stat-icon">🧑‍💼</span>
                <div>
                    <div class="stat-label">Staff Members</div>
                    <div class="stat-value"><?= $total_staff ?></div>
                </div>
            </div>
            <div class="stat-card amber">
                <span class="stat-icon">📋</span>
                <div>
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value"><?= $total_orders ?></div>
                </div>
            </div>
            <div class="stat-card blue">
                <span class="stat-icon">📅</span>
                <div>
                    <div class="stat-label">Today's Orders</div>
                    <div class="stat-value"><?= $today_orders ?></div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="dashboard-section">
            <div class="section-head">
                <h2>All Users</h2>
                <a href="staff_management.php" class="menu-btn primary">+ Add Staff</a>
            </div>
            <div class="table-wrapper" style="border-radius:0;border:none;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($row['id']) ?></td>
                                    <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><span class="role-badge <?= htmlspecialchars($row['role']) ?>"><?= htmlspecialchars($row['role']) ?></span></td>
                                    <td><?= date('M d, Y g:i A', strtotime($row['date_registered'])) ?></td>
                                    <td class="action-cell">
                                        <div class="action-buttons">
                                            <a href="edit_user.php?id=<?= urlencode($row['id']) ?>" class="edit-btn">✏️ Edit</a>
                                            <?php if ($row['role'] !== 'admin'): ?>
                                                <button type="button" class="reset-password-btn" onclick="openResetModal(<?= $row['id'] ?>, <?= htmlspecialchars(json_encode($row['username']), ENT_QUOTES, 'UTF-8') ?>)">🔑 Reset PW</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:2rem;">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin_dashboard.js"></script>
</body>
</html>
