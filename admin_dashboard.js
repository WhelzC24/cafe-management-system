/* Admin Dashboard JS — shared across admin/staff pages */
(function () {
    let _resetUserId = null;

    window.openResetModal = function (id, username) {
        _resetUserId = id;
        const el = document.getElementById('resetUsername');
        if (el) el.textContent = username;
        // Bootstrap modal
        const modal = document.getElementById('resetPasswordModal');
        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    };

    window.submitResetPassword = function () {
        if (!_resetUserId) return;
        fetch('reset_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'user_id=' + encodeURIComponent(_resetUserId)
        })
        .then(r => r.json())
        .then(d => {
            const modal = document.getElementById('resetPasswordModal');
            if (modal && window.bootstrap) bootstrap.Modal.getInstance(modal)?.hide();
            showAlert(d.success ? (d.message || 'Password reset successfully.') : (d.message || 'Reset failed.'),
                d.success ? 'success' : 'error');
        })
        .catch(() => showAlert('Request failed. Please try again.', 'error'));
    };

    window.showAlert = function (msg, type) {
        const c = document.getElementById('alertContainer');
        if (!c) return;
        const el = document.createElement('div');
        el.className = 'alert-msg ' + (type || '');
        el.textContent = msg;
        c.appendChild(el);
        setTimeout(() => el.remove(), 4500);
    };

    // Show URL param alerts on page load
    window.addEventListener('DOMContentLoaded', function () {
        const p = new URLSearchParams(window.location.search);
        if (p.get('success')) showAlert('✓ ' + decodeURIComponent(p.get('success')), 'success');
        if (p.get('error'))   showAlert('✗ ' + decodeURIComponent(p.get('error')), 'error');
    });
})();
