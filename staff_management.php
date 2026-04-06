<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.html'); exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$staff_members = [];
$result = mysqli_query($conn, "SELECT id, fullname, email, username, role, must_change_password, date_registered FROM users WHERE role = 'staff' ORDER BY id ASC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) $staff_members[] = $row;
    mysqli_free_result($result);
}
$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management — Cozy Corner Café</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .two-col-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 900px) { .two-col-layout { grid-template-columns: 1fr; } }
        .pw-input-wrap { position: relative; }
        .pw-input-wrap input { padding-right: 2.8rem !important; }
        .pw-toggle {
            position: absolute; right: 0.75rem; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--text-light); cursor: pointer;
            font-size: 1rem; padding: 0;
            transition: color 0.2s;
        }
        .pw-toggle:hover { color: var(--brown-warm); }
        .pw-strength { height: 4px; border-radius: 2px; margin-top: 0.35rem; background: var(--cream-border); overflow: hidden; }
        .pw-strength-fill { height: 100%; width: 0; border-radius: 2px; transition: width 0.3s, background 0.3s; }
        .info-banner {
            background: #eff6ff; color: #1e40af;
            border-radius: var(--radius-md); padding: 0.85rem 1rem;
            font-size: 0.85rem; margin-bottom: 1.25rem;
            border-left: 4px solid #3b82f6;
            display: flex; align-items: flex-start; gap: 0.5rem;
        }
        .must-change-badge {
            background: #fef3c7; color: #92400e;
            padding: 0.2rem 0.6rem; border-radius: 50px;
            font-size: 0.7rem; font-weight: 700;
            display: inline-flex; align-items: center; gap: 0.25rem;
        }
        .pw-ok-badge {
            background: #dcfce7; color: #166534;
            padding: 0.2rem 0.6rem; border-radius: 50px;
            font-size: 0.7rem; font-weight: 700;
            display: inline-flex; align-items: center; gap: 0.25rem;
        }
        .form-section-title {
            font-family: var(--font-serif);
            font-size: 1.15rem; color: var(--brown-dark);
            margin-bottom: 0.25rem;
        }
        .form-section-sub {
            font-size: 0.82rem; color: var(--text-light);
            margin-bottom: 1.25rem; line-height: 1.5;
        }
        .field-group { display: flex; flex-direction: column; gap: 1rem; }
        /* Stagger animation for table rows */
        tbody tr { opacity: 0; transform: translateY(8px); transition: opacity 0.3s ease, transform 0.3s ease; }
        tbody tr.visible { opacity: 1; transform: translateY(0); }
        /* Alert banner inline */
        .inline-alert {
            padding: 0.8rem 1rem; border-radius: var(--radius-md);
            font-size: 0.88rem; margin-bottom: 1rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .inline-alert.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .inline-alert.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    </style>
</head>
<body class="admin-page">
<div id="alertContainer"></div>
<button class="sidebar-toggle" id="sidebarToggle">☰</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Reset Password Modal -->
<div id="resetModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
    <div style="background:#fff;border-radius:20px;padding:2.5rem;max-width:420px;width:90%;box-shadow:0 30px 80px rgba(0,0,0,0.2);animation:cardEntrance 0.4s cubic-bezier(0.34,1.56,0.64,1) both;">
        <div style="text-align:center;margin-bottom:1.5rem;">
            <div style="width:56px;height:56px;background:#fef3c7;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.6rem;margin-bottom:0.75rem;">🔑</div>
            <h3 style="font-family:'Playfair Display',serif;color:#3E1F0A;margin-bottom:0.35rem;">Reset Password</h3>
            <p style="color:#7D6350;font-size:0.88rem;">Reset password for <strong id="resetUsernameDisplay"></strong>?<br>
            New password will be <strong>12345</strong>. Staff must change on next login.</p>
        </div>
        <div style="display:flex;gap:0.75rem;justify-content:center;">
            <button class="menu-btn secondary" onclick="closeResetModal()">Cancel</button>
            <button class="menu-btn danger" id="confirmResetBtn" onclick="submitResetPassword()">🔑 Reset Password</button>
        </div>
    </div>
</div>

<div class="app-layout">
    <!-- SIDEBAR -->
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
            <a href="admin_dashboard.php" class="sidebar-link">
                <span class="sidebar-link-icon">👥</span>
                User Management
            </a>
            <a href="staff_management.php" class="sidebar-link active">
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

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="page-header">
            <p class="page-greeting">Manage your team</p>
            <h1 class="page-title">Staff <span>Management</span></h1>
        </div>

        <div class="two-col-layout">

            <!-- Create Staff Panel -->
            <div class="dashboard-section" style="animation-delay:0.1s;">
                <div class="section-head">
                    <h2>➕ Add Staff Account</h2>
                </div>
                <div class="section-body">
                    <?php if ($success): ?>
                        <div class="inline-alert success">✅ <?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="inline-alert error">❌ <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="info-banner">
                        <span>🔒</span>
                        <span><strong>Admin-only action.</strong> Staff accounts cannot self-register. They must change their password on first login.</span>
                    </div>

                    <form method="POST" action="create_staff.php" id="createStaffForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="field-group">
                            <div class="field-stack">
                                <label for="fullname">Full Name</label>
                                <input type="text" id="fullname" name="fullname" placeholder="e.g. Maria Santos" required>
                            </div>
                            <div class="field-stack">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="staff@cafe.com" required>
                            </div>
                            <div class="field-stack">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" placeholder="e.g. maria.santos" required
                                    pattern="[a-zA-Z0-9._\-]+" title="Letters, numbers, dots, hyphens, underscores only">
                            </div>
                            <div class="field-stack">
                                <label for="password">Temporary Password</label>
                                <div class="pw-input-wrap">
                                    <input type="password" id="password" name="password" minlength="8" placeholder="Min. 8 characters" required>
                                    <button type="button" class="pw-toggle" onclick="togglePw('password',this)" title="Show/hide">👁</button>
                                </div>
                                <div class="pw-strength"><div class="pw-strength-fill" id="pwFill"></div></div>
                            </div>
                            <button type="submit" class="menu-btn primary" style="width:100%;justify-content:center;padding:.85rem;">
                                ➕ Create Staff Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Staff List Panel -->
            <div class="dashboard-section" style="animation-delay:0.2s;">
                <div class="section-head">
                    <h2>Staff Accounts</h2>
                    <span style="font-size:0.82rem;color:var(--text-light);">
                        <?= count($staff_members) ?> member<?= count($staff_members) !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>PW Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="staffTableBody">
                            <?php if ($staff_members): ?>
                                <?php foreach ($staff_members as $sm): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($sm['fullname']) ?></strong></td>
                                        <td><span class="role-badge staff"><?= htmlspecialchars($sm['username']) ?></span></td>
                                        <td style="font-size:.82rem;color:var(--text-light);"><?= htmlspecialchars($sm['email']) ?></td>
                                        <td>
                                            <?php if ((int)$sm['must_change_password'] === 1): ?>
                                                <span class="must-change-badge">⚠ Must Change</span>
                                            <?php else: ?>
                                                <span class="pw-ok-badge">✓ Set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:.82rem;color:var(--text-light);"><?= date('M d, Y', strtotime($sm['date_registered'])) ?></td>
                                        <td class="action-cell">
                                            <div class="action-buttons">
                                                <a href="edit_user.php?id=<?= urlencode($sm['id']) ?>" class="edit-btn">✏️ Edit</a>
                                                <button class="reset-password-btn"
                                                    onclick="openResetModal(<?= $sm['id'] ?>, <?= htmlspecialchars(json_encode($sm['username']), ENT_QUOTES) ?>)">🔑 Reset PW</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="visible"><td colspan="6" style="text-align:center;color:var(--text-light);padding:3rem;">
                                    No staff accounts yet. Create one using the form.
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /two-col-layout -->
    </main>
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

/* Stagger table rows */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#staffTableBody tr').forEach((tr, i) => {
        setTimeout(() => tr.classList.add('visible'), 80 + i * 60);
    });
});

/* Password show toggle */
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}

/* Password strength */
document.getElementById('password')?.addEventListener('input', function () {
    const v = this.value, fill = document.getElementById('pwFill');
    if (!fill) return;
    let s = 0;
    if (v.length >= 8) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    fill.style.width  = ['0%','25%','50%','75%','100%'][s];
    fill.style.background = ['','#ef4444','#f97316','#eab308','#22c55e'][s];
});

/* Reset modal */
let _resetUserId = null;
function openResetModal(id, username) {
    _resetUserId = id;
    document.getElementById('resetUsernameDisplay').textContent = username;
    document.getElementById('resetModal').style.display = 'flex';
}
function closeResetModal() {
    document.getElementById('resetModal').style.display = 'none';
    _resetUserId = null;
}
function submitResetPassword() {
    if (!_resetUserId) return;
    const btn = document.getElementById('confirmResetBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Resetting…'; }
    fetch('reset_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'user_id=' + encodeURIComponent(_resetUserId)
    })
    .then(r => r.json())
    .then(d => {
        closeResetModal();
        if (btn) { btn.disabled = false; btn.innerHTML = '🔑 Reset Password'; }
        showAlert(d.success ? (d.message || '✓ Password reset to 12345.') : (d.message || 'Reset failed.'),
            d.success ? 'success' : 'error');
    })
    .catch(() => { closeResetModal(); showAlert('Request failed.', 'error'); });
}
document.getElementById('resetModal')?.addEventListener('click', e => {
    if (e.target === document.getElementById('resetModal')) closeResetModal();
});

/* Alert helper */
function showAlert(msg, type) {
    const c = document.getElementById('alertContainer');
    const el = document.createElement('div');
    el.className = 'alert-msg ' + (type || '');
    el.textContent = msg;
    if (type === 'error') { el.style.cursor = 'pointer'; el.addEventListener('click', () => el.remove()); }
    c.appendChild(el);
    setTimeout(() => { if (el.parentNode) el.remove(); }, type === 'error' ? 8000 : 4500);
}
</script>
</body>
</html>
