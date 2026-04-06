<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.html'); exit; }

$must_change = (int)($_SESSION['must_change_password'] ?? 0);
$error = $success = '';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — Cozy Corner Café</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-page-body">
    <div class="form-card">
        <h2>🔑 <?= $must_change ? 'Set Your New Password' : 'Change Password' ?></h2>
        <p><?= $must_change
            ? 'Your account requires a password change before you can continue.'
            : 'Enter your current password and choose a new one.' ?></p>

        <div id="errorMsg" class="error-msg" style="display:none;"></div>
        <div id="successMsg" class="success-msg" style="display:none;"></div>

        <form id="changePassForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <?php if (!$must_change): ?>
            <div class="field-stack" style="margin-bottom:1rem;">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required placeholder="Your current password">
            </div>
            <?php endif; ?>
            <div class="field-stack" style="margin-bottom:.5rem;">
                <label for="new_password">New Password</label>
                <div style="position:relative;">
                    <input type="password" id="new_password" name="new_password" minlength="8" required placeholder="Min. 8 characters" style="width:100%;padding-right:2.8rem;">
                    <button type="button" onclick="(function(){var i=document.getElementById('new_password');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈';}).call(this)" style="position:absolute;right:0.8rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;padding:0;color:#7D6350;">👁</button>
                </div>
            </div>
            <div style="height:4px;border-radius:2px;background:#E8D9C4;overflow:hidden;margin-bottom:1rem;margin-top:0;border:none;">
                <div id="pwBar" style="height:100%;width:0;border-radius:2px;transition:.3s;"></div>
            </div>
            <div class="field-stack" style="margin-bottom:1rem;">
                <label for="confirm_password">Confirm New Password</label>
                <div style="position:relative;">
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat new password" style="width:100%;padding-right:2.8rem;">
                    <button type="button" onclick="(function(){var i=document.getElementById('confirm_password');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈';}).call(this)" style="position:absolute;right:0.8rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;padding:0;color:#7D6350;">👁</button>
                </div>
            </div>
            <div class="btn-row">
                <button type="submit" class="menu-btn primary">💾 Update Password</button>
                <?php if (!$must_change): ?>
                    <a href="<?= $_SESSION['role']==='admin' ? 'admin_dashboard.php' : 'store_dashboard.php' ?>" class="menu-btn secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('new_password')?.addEventListener('input', function() {
    const bar = document.getElementById('pwBar'); if(!bar) return;
    let s=0; const v=this.value;
    if(v.length>=8)s++; if(/[A-Z]/.test(v))s++; if(/[0-9]/.test(v))s++; if(/[^A-Za-z0-9]/.test(v))s++;
    const w=['0%','25%','50%','75%','100%'], c=['','#ef4444','#f97316','#eab308','#22c55e'];
    bar.style.width=w[s]; bar.style.background=c[s];
});

document.getElementById('changePassForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const errEl = document.getElementById('errorMsg');
    const okEl  = document.getElementById('successMsg');
    const np = document.getElementById('new_password').value;
    const cp = document.getElementById('confirm_password').value;
    errEl.style.display='none'; okEl.style.display='none';
    if (np !== cp) { errEl.textContent='Passwords do not match.'; errEl.style.display='block'; return; }
    if (np.length < 8)  { errEl.textContent='Password must be at least 8 characters.'; errEl.style.display='block'; return; }

    const fd = new FormData(this);
    const res = await fetch('change_password_handler.php', { method:'POST', body: new URLSearchParams(fd) });
    const d = await res.json();
    if (d.success) {
        okEl.textContent = d.message || 'Password updated!';
        okEl.style.display='block';
        this.reset();
        setTimeout(() => { window.location.href = d.redirect || 'store_dashboard.php'; }, 1500);
    } else {
        errEl.textContent = d.message || 'Update failed.';
        errEl.style.display='block';
    }
});
</script>
</body>
</html>
