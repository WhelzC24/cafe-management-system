<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.html');
    exit;
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .staff-badge-info {
            background: #dbeafe; color: #1e40af;
            border-radius: 8px; padding: 0.75rem 1rem;
            font-size: 0.85rem; margin-bottom: 1.25rem;
            border-left: 4px solid #2563eb;
        }
        .pw-input-wrap { position: relative; }
        .pw-input-wrap input { padding-right: 2.5rem; }
        .pw-toggle {
            position: absolute; right: 0.75rem; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--text-light); cursor: pointer;
            font-size: 1rem; padding: 0;
        }
        .pw-strength {
            height: 4px; border-radius: 2px;
            margin-top: 0.3rem;
            background: var(--cream-border);
            overflow: hidden;
        }
        .pw-strength-fill {
            height: 100%; width: 0;
            border-radius: 2px;
            transition: width 0.3s, background 0.3s;
        }
        .alert-banner {
            padding: 0.85rem 1.1rem;
            border-radius: 8px; font-size: 0.9rem;
            margin-bottom: 1.25rem;
        }
        .alert-banner.success { background: #dcfce7; color: #166534; }
        .alert-banner.error   { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="admin-dashboard">
<div id="alertContainer"></div>
<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1>👥 Staff Management</h1>
            <p class="admin-subtitle">Only admins can create, view, and manage staff accounts.</p>
        </div>
        <div class="dashboard-actions">
            <a href="admin_dashboard.php" class="btn-link-light">← User Dashboard</a>
            <a href="store_dashboard.php" class="menu-btn secondary">📦 Store Dashboard</a>
            <a href="logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </div>

    <div class="admin-grid">
        <!-- Create Staff Panel -->
        <section class="admin-panel">
            <h2>Add Staff Account</h2>
            <p class="admin-subtitle" style="margin-bottom:1rem;">Staff can manage products and process customer orders. They must change their password on first login.</p>

            <?php if ($success): ?>
                <div class="alert-banner success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-banner error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="staff-badge-info">
                🔒 <strong>Admin-only action.</strong> Only administrators can create staff accounts. Staff accounts cannot self-register.
            </div>

            <form method="POST" action="create_staff.php" class="admin-form-grid" id="createStaffForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

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
                    <input type="text" id="username" name="username" placeholder="e.g. maria.santos" required pattern="[a-zA-Z0-9._\-]+" title="Letters, numbers, dots, hyphens, underscores only">
                </div>
                <div class="field-stack">
                    <label for="password">Temporary Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="password" name="password" minlength="8" placeholder="Min. 8 characters" required>
                        <button type="button" class="pw-toggle" onclick="togglePw('password', this)" title="Show/hide password">👁</button>
                    </div>
                    <div class="pw-strength"><div class="pw-strength-fill" id="pwFill"></div></div>
                </div>
                <div class="admin-form-actions">
                    <button type="submit" class="menu-btn primary" style="width:100%;justify-content:center;padding:.75rem;">
                        ➕ Create Staff Account
                    </button>
                </div>
            </form>
        </section>

        <!-- Staff List Panel -->
        <section class="admin-panel">
            <h2>Staff Accounts</h2>
            <p class="admin-subtitle" style="margin-bottom:1rem;">
                <?= count($staff_members) ?> staff member<?= count($staff_members) !== 1 ? 's' : '' ?> registered.
                Use the Admin Dashboard to edit accounts or reset passwords.
            </p>
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
                    <tbody>
                        <?php if ($staff_members): ?>
                            <?php foreach ($staff_members as $sm): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sm['fullname']) ?></strong></td>
                                    <td><span class="role-badge staff"><?= htmlspecialchars($sm['username']) ?></span></td>
                                    <td><?= htmlspecialchars($sm['email']) ?></td>
                                    <td>
                                        <?php if ((int)$sm['must_change_password'] === 1): ?>
                                            <span style="color:#d97706;font-size:0.78rem;font-weight:600;">⚠ Must Change</span>
                                        <?php else: ?>
                                            <span style="color:#16a34a;font-size:0.78rem;font-weight:600;">✓ Set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($sm['date_registered'])) ?></td>
                                    <td class="action-cell">
                                        <div class="action-buttons">
                                            <a href="edit_user.php?id=<?= urlencode($sm['id']) ?>" class="edit-btn">✏️ Edit</a>
                                            <button class="reset-password-btn" onclick="openResetModal(<?= $sm['id'] ?>, '<?= addslashes($sm['username']) ?>')">🔑 Reset PW</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center;color:var(--text-light);padding:2.5rem;">
                                No staff accounts yet. Create one using the form on the left.
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<!-- Reset Password Modal (Bootstrap-free, pure JS) -->
<div id="resetModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:2rem;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <h3 style="font-family:'Playfair Display',serif;color:var(--brown-dark);margin-bottom:.5rem;">Reset Password</h3>
        <p style="color:var(--text-mid);font-size:.9rem;margin-bottom:.5rem;">Reset password for <strong id="resetUsernameDisplay"></strong>?</p>
        <p style="color:var(--text-light);font-size:.82rem;margin-bottom:1.5rem;">Password will be reset to <strong>12345</strong>. The staff member must change it on next login.</p>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
            <button onclick="closeResetModal()" class="menu-btn secondary">Cancel</button>
            <button onclick="submitResetPassword()" class="menu-btn danger">Reset Password</button>
        </div>
    </div>
</div>

<script>
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
    fetch('reset_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'user_id=' + encodeURIComponent(_resetUserId)
    })
    .then(r => r.json())
    .then(d => {
        closeResetModal();
        showAlert(d.success ? d.message : (d.message || 'Reset failed.'), d.success ? 'success' : 'error');
    })
    .catch(() => { closeResetModal(); showAlert('Request failed.', 'error'); });
}

function showAlert(msg, type) {
    const c = document.getElementById('alertContainer');
    const el = document.createElement('div');
    el.className = 'alert-msg ' + (type || '');
    el.textContent = msg;
    c.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}

// Password strength
document.getElementById('password')?.addEventListener('input', function () {
    const v = this.value, fill = document.getElementById('pwFill');
    if (!fill) return;
    let strength = 0;
    if (v.length >= 8) strength++;
    if (/[A-Z]/.test(v)) strength++;
    if (/[0-9]/.test(v)) strength++;
    if (/[^A-Za-z0-9]/.test(v)) strength++;
    const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
    const widths  = ['0%', '25%', '50%', '75%', '100%'];
    fill.style.width = widths[strength];
    fill.style.background = colors[strength];
});

// Close reset modal on backdrop click
document.getElementById('resetModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeResetModal();
});
</script>
</body>
</html>
