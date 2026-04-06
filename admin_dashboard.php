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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-page">
<div id="alertContainer"></div>

<!-- Mobile toggle -->
<button class="sidebar-toggle" id="sidebarToggle">☰</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Custom Reset Password Modal (no Bootstrap) -->
<div id="resetModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
    <div style="background:#fff;border-radius:20px;padding:2.5rem;max-width:420px;width:90%;box-shadow:0 30px 80px rgba(0,0,0,0.2);animation:cardEntrance 0.4s cubic-bezier(0.34,1.56,0.64,1) both;">
        <div style="text-align:center;margin-bottom:1.5rem;">
            <div style="width:56px;height:56px;background:#fef3c7;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.6rem;margin-bottom:0.75rem;">🔑</div>
            <h3 style="font-family:'Playfair Display',serif;color:#3E1F0A;margin-bottom:0.35rem;">Reset Password</h3>
            <p style="color:#7D6350;font-size:0.88rem;">Reset password for <strong id="resetUsername"></strong>?<br>
            New password will be <strong>12345</strong>. Staff must change on next login.</p>
        </div>
        <div style="display:flex;gap:0.75rem;justify-content:center;">
            <button class="menu-btn secondary" onclick="closeResetModal()">Cancel</button>
            <button class="menu-btn danger" id="confirmResetBtn" onclick="submitResetPassword()">🔑 Reset Password</button>
        </div>
    </div>
</div>

<div class="app-layout">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <a href="cafe/index.html" class="sidebar-brand" target="_blank">
            <div class="sidebar-brand-icon">⚡</div>
            <div class="sidebar-brand-text">
                Cozy Corner
                <small>Admin Panel</small>
            </div>
        </a>

        <div class="sidebar-section-label">Management</div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="sidebar-link active">
                <span class="sidebar-link-icon">👥</span>
                User Management
            </a>
            <a href="staff_management.php" class="sidebar-link">
                <span class="sidebar-link-icon">➕</span>
                Add Staff
            </a>
            <a href="store_dashboard.php" class="sidebar-link">
                <span class="sidebar-link-icon">📦</span>
                Store Dashboard
            </a>

            <div class="sidebar-section-label" style="margin-top:.75rem;">System</div>
            <a href="cafe/index.html" class="sidebar-link" target="_blank">
                <span class="sidebar-link-icon">🌐</span>
                View Café Site
            </a>
            <a href="change_password.php" class="sidebar-link">
                <span class="sidebar-link-icon">🔑</span>
                Change Password
            </a>
        </nav>

        <div class="sidebar-user">
            <div class="sidebar-avatar" style="background:#1e3a5f;">A</div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name">Admin</div>
                <div class="sidebar-user-role">administrator</div>
            </div>
            <a href="logout.php" class="sidebar-logout" title="Logout">⬡</a>
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <div class="page-header">
            <p class="page-greeting">Manage your team and monitor activity</p>
            <h1 class="page-title">Admin <span>Dashboard</span></h1>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">👥</span>
                <div><div class="stat-label">Total Users</div><div class="stat-value" data-target="<?= $total_users ?>">0</div></div>
            </div>
            <div class="stat-card green">
                <span class="stat-icon">🧑‍💼</span>
                <div><div class="stat-label">Staff Members</div><div class="stat-value" data-target="<?= $total_staff ?>">0</div></div>
            </div>
            <div class="stat-card amber">
                <span class="stat-icon">📋</span>
                <div><div class="stat-label">Total Orders</div><div class="stat-value" data-target="<?= $total_orders ?>">0</div></div>
            </div>
            <div class="stat-card blue">
                <span class="stat-icon">📅</span>
                <div><div class="stat-label">Today's Orders</div><div class="stat-value" data-target="<?= $today_orders ?>">0</div></div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="dashboard-section">
            <div class="section-head">
                <h2>All Users</h2>
                <a href="staff_management.php" class="menu-btn primary">+ Add Staff</a>
            </div>
            <div class="table-wrapper">
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
                                    <td><strong>#<?= htmlspecialchars($row['id']) ?></strong></td>
                                    <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                                    <td style="color:var(--text-light);font-size:.85rem;"><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><span class="role-badge <?= htmlspecialchars($row['role']) ?>"><?= htmlspecialchars($row['role']) ?></span></td>
                                    <td style="font-size:.82rem;color:var(--text-light);"><?= date('M d, Y', strtotime($row['date_registered'])) ?></td>
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
                            <tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:3rem;">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main><!-- /main-content -->
</div><!-- /app-layout -->

<script>
/* Mobile sidebar */
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('mobile-open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
});
document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('mobile-open');
    document.getElementById('sidebarOverlay').classList.remove('show');
});

/* Count-up */
function animateCountUp(el, target) {
    let start = 0;
    const step = Math.ceil(target / 56);
    const timer = setInterval(() => {
        start += step;
        if (start >= target) { el.textContent = target; clearInterval(timer); }
        else { el.textContent = start; }
    }, 16);
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.stat-value[data-target]').forEach(el => {
        animateCountUp(el, parseInt(el.dataset.target) || 0);
    });
    // Stagger table rows
    document.querySelectorAll('tbody tr').forEach((tr, i) => {
        tr.style.opacity = '0';
        tr.style.transform = 'translateY(8px)';
        tr.style.transition = 'all 0.3s ease';
        setTimeout(() => { tr.style.opacity = '1'; tr.style.transform = 'translateY(0)'; }, 100 + i * 40);
    });
});

/* Reset password modal */
let _resetUserId = null;
function openResetModal(id, username) {
    _resetUserId = id;
    document.getElementById('resetUsername').textContent = username;
    const modal = document.getElementById('resetModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.style.opacity = '1', 10);
}
function closeResetModal() {
    document.getElementById('resetModal').style.display = 'none';
}
document.getElementById('resetModal')?.addEventListener('click', (e) => {
    if (e.target === document.getElementById('resetModal')) closeResetModal();
});

/* Alert helper */
function showAlert(msg, type) {
    const c = document.getElementById('alertContainer');
    const el = document.createElement('div');
    el.className = 'alert-msg ' + (type || '');
    el.textContent = msg;
    if (type === 'error') { el.style.cursor='pointer'; el.addEventListener('click', () => el.remove()); }
    c.appendChild(el);
    setTimeout(() => { if (el.parentNode) el.remove(); }, type === 'error' ? 8000 : 4000);
}
</script>
<script src="admin_dashboard.js"></script>
</body>
</html>

