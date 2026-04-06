<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.html'); exit; }
$must_change = (int)($_SESSION['must_change_password'] ?? 0);
$error = $success = '';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$back_url = ($_SESSION['role'] ?? '') === 'admin' ? 'admin_dashboard.php' : 'store_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — Cozy Corner Café</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: linear-gradient(135deg, #1A0A04 0%, #3E1F0A 50%, #6B3A2A 100%); }
        .form-page-body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .form-card {
            background: #fff; border-radius: 24px; padding: 2.75rem 2.5rem;
            box-shadow: 0 30px 80px rgba(0,0,0,0.35);
            width: 100%; max-width: 460px;
            animation: cardEntrance 0.6s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        @keyframes cardEntrance { from { opacity:0; transform:translateY(24px) scale(0.97); } to { opacity:1; transform:translateY(0) scale(1); } }
        .card-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, #8B4513, #C8860A);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 20px rgba(139,69,19,0.35);
        }
        .card-title { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: #3E1F0A; margin-bottom: 0.35rem; }
        .card-sub { color: #7D6350; font-size: 0.88rem; margin-bottom: 1.75rem; line-height: 1.5; }
        .pw-input-wrap { position: relative; }
        .pw-input-wrap input { padding-right: 2.8rem !important; }
        .pw-toggle {
            position: absolute; right: 0.75rem; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: #7D6350; cursor: pointer; font-size: 1rem; padding: 0;
            transition: color 0.2s;
        }
        .pw-toggle:hover { color: #8B4513; }
        .pw-strength { height: 5px; border-radius: 3px; background: #E8D9C4; overflow: hidden; margin-top: 0.4rem; }
        .pw-strength-bar { height: 100%; width: 0; border-radius: 3px; transition: width 0.3s, background 0.3s; }
        .field-stack { margin-bottom: 1.1rem; }
        .field-stack label { display: block; font-size: 0.8rem; font-weight: 600; color: #4A3728; margin-bottom: 0.35rem; letter-spacing: 0.02em; }
        .field-stack input {
            width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #E8D9C4;
            border-radius: 10px; font-size: 0.9rem; background: #FFF8F0;
            color: #1A0F0A; outline: none; font-family: 'Inter', sans-serif;
            transition: border-color 0.25s, box-shadow 0.25s;
        }
        .field-stack input:focus { border-color: #8B4513; background: #fff; box-shadow: 0 0 0 3px rgba(139,69,19,0.12); }
        .must-change-notice {
            background: #fef3c7; color: #92400e; border-radius: 10px;
            padding: 0.85rem 1rem; font-size: 0.85rem; margin-bottom: 1.25rem;
            border-left: 4px solid #f59e0b;
            display: flex; align-items: flex-start; gap: 0.5rem;
        }
        .error-msg { background: #fee2e2; color: #991b1b; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.88rem; margin-bottom: 1rem; border: 1px solid #fca5a5; }
        .success-msg { background: #dcfce7; color: #166534; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.88rem; margin-bottom: 1rem; border: 1px solid #86efac; }
        .btn-row { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        .btn-primary {
            flex: 1; padding: 0.85rem; border-radius: 12px; border: none;
            background: linear-gradient(135deg, #8B4513, #6B3A2A);
            color: #fff; font-size: 0.95rem; font-weight: 600;
            cursor: pointer; transition: all 0.25s; font-family: 'Inter', sans-serif;
            position: relative; overflow: hidden;
        }
        .btn-primary::after { content:''; position:absolute; top:0;left:-100%;width:60%;height:100%; background:linear-gradient(120deg,transparent,rgba(255,255,255,0.13),transparent); transition:left 0.4s; }
        .btn-primary:hover::after { left:130%; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(139,69,19,0.4); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        .btn-secondary {
            padding: 0.85rem 1.5rem; border-radius: 12px;
            background: #F5ECD7; color: #6B3A2A;
            border: 1.5px solid #E8D9C4; font-size: 0.9rem; font-weight: 600;
            cursor: pointer; text-decoration: none; display: inline-flex;
            align-items: center; justify-content: center; transition: all 0.25s; font-family: 'Inter', sans-serif;
        }
        .btn-secondary:hover { border-color: #8B4513; color: #8B4513; }
        /* Stagger field entrance */
        @keyframes fadeUp { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }
        .field-stack:nth-child(1) { animation: fadeUp 0.4s ease both; animation-delay: 0.15s; }
        .field-stack:nth-child(2) { animation: fadeUp 0.4s ease both; animation-delay: 0.25s; }
        .field-stack:nth-child(3) { animation: fadeUp 0.4s ease both; animation-delay: 0.35s; }
        .btn-row { animation: fadeUp 0.4s ease both; animation-delay: 0.45s; }
    </style>
</head>
<body>
<div class="form-page-body">
    <div class="form-card">
        <div class="card-icon">🔑</div>
        <h2 class="card-title"><?= $must_change ? 'Set New Password' : 'Change Password' ?></h2>
        <p class="card-sub"><?= $must_change
            ? 'Your account requires a password change before you can continue.'
            : 'Keep your account secure by choosing a strong password.' ?></p>

        <?php if ($must_change): ?>
        <div class="must-change-notice">
            <span>⚠️</span>
            <span>This is a <strong>required</strong> step. You must change your password to access the dashboard.</span>
        </div>
        <?php endif; ?>

        <div id="errorMsg" class="error-msg" style="display:none;"></div>
        <div id="successMsg" class="success-msg" style="display:none;"></div>

        <form id="changePassForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <?php if (!$must_change): ?>
            <div class="field-stack">
                <label for="current_password">Current Password</label>
                <div class="pw-input-wrap">
                    <input type="password" id="current_password" name="current_password" required placeholder="Your current password">
                    <button type="button" class="pw-toggle" onclick="togglePw('current_password',this)">👁</button>
                </div>
            </div>
            <?php endif; ?>
            <div class="field-stack">
                <label for="new_password">New Password</label>
                <div class="pw-input-wrap">
                    <input type="password" id="new_password" name="new_password" minlength="8" required placeholder="Min. 8 characters">
                    <button type="button" class="pw-toggle" onclick="togglePw('new_password',this)">👁</button>
                </div>
                <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
            </div>
            <div class="field-stack">
                <label for="confirm_password">Confirm New Password</label>
                <div class="pw-input-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat new password">
                    <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)">👁</button>
                </div>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn-primary" id="submitBtn">💾 Update Password</button>
                <?php if (!$must_change): ?>
                    <a href="<?= $back_url ?>" class="btn-secondary">← Back</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}

document.getElementById('new_password')?.addEventListener('input', function() {
    const bar = document.getElementById('pwBar'); if (!bar) return;
    let s = 0; const v = this.value;
    if (v.length >= 8) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    bar.style.width  = ['0%','25%','50%','75%','100%'][s];
    bar.style.background = ['','#ef4444','#f97316','#eab308','#22c55e'][s];
});

document.getElementById('changePassForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const errEl = document.getElementById('errorMsg');
    const okEl  = document.getElementById('successMsg');
    const btn   = document.getElementById('submitBtn');
    const np = document.getElementById('new_password').value;
    const cp = document.getElementById('confirm_password').value;
    errEl.style.display = 'none'; okEl.style.display = 'none';
    if (np !== cp) { errEl.textContent = 'Passwords do not match.'; errEl.style.display = 'block'; return; }
    if (np.length < 8) { errEl.textContent = 'Password must be at least 8 characters.'; errEl.style.display = 'block'; return; }
    btn.disabled = true; btn.textContent = 'Updating…';
    const fd = new FormData(this);
    const res = await fetch('change_password_handler.php', { method: 'POST', body: new URLSearchParams(fd) });
    const d = await res.json();
    btn.disabled = false; btn.textContent = '💾 Update Password';
    if (d.success) {
        okEl.textContent = d.message || 'Password updated!';
        okEl.style.display = 'block';
        this.reset();
        setTimeout(() => { window.location.href = d.redirect || 'store_dashboard.php'; }, 1500);
    } else {
        errEl.textContent = d.message || 'Update failed.';
        errEl.style.display = 'block';
    }
});
</script>
</body>
</html>
