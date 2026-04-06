/* Admin Dashboard JS — works with custom modal (no Bootstrap dependency) */
(function () {
    let _resetUserId = null;

    window.openResetModal = function (id, username) {
        _resetUserId = id;
        const el = document.getElementById('resetUsername');
        if (el) el.textContent = username;
        const modal = document.getElementById('resetModal');
        if (modal) { modal.style.display = 'flex'; }
    };

    window.closeResetModal = function () {
        const modal = document.getElementById('resetModal');
        if (modal) modal.style.display = 'none';
    };

    window.submitResetPassword = function () {
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
        .catch(() => {
            closeResetModal();
            if (btn) { btn.disabled = false; btn.innerHTML = '🔑 Reset Password'; }
            showAlert('Request failed. Please try again.', 'error');
        });
    };

    window.showAlert = function (msg, type) {
        const c = document.getElementById('alertContainer');
        if (!c) return;
        const el = document.createElement('div');
        el.className = 'alert-msg ' + (type || '');
        el.textContent = msg;
        if (type === 'error') { el.style.cursor = 'pointer'; el.addEventListener('click', () => el.remove()); }
        c.appendChild(el);
        setTimeout(() => { if (el.parentNode) el.remove(); }, type === 'error' ? 8000 : 4500);
    };

    // Show URL param alerts on page load
    window.addEventListener('DOMContentLoaded', function () {
        const p = new URLSearchParams(window.location.search);
        if (p.get('success')) showAlert('✓ ' + decodeURIComponent(p.get('success')), 'success');
        if (p.get('error'))   showAlert('✗ ' + decodeURIComponent(p.get('error')), 'error');
        window.history.replaceState({}, '', window.location.pathname);
    });
})();
