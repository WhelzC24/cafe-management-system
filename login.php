<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: login.html?error=' . urlencode('Username and password are required.'));
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, fullname, username, password, role, must_change_password FROM users WHERE username = ?');
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$user = mysqli_fetch_assoc($result);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['must_change_password'] = (int) ($user['must_change_password'] ?? 0);

    $is_admin = $user['role'] === 'admin';
    $is_staff = $user['role'] === 'staff';
    $must_change_password = $_SESSION['must_change_password'] === 1;
    $redirect_url = $is_admin
        ? 'admin_dashboard.php'
        : ($must_change_password ? 'menu.php' : ($is_staff ? 'store_dashboard.php' : 'cafe/index.html'));
    $subtitle = $is_admin
        ? 'Opening admin dashboard...'
        : ($must_change_password
            ? 'Please change your password to continue...'
            : ($is_staff ? 'Opening store management...' : 'Preparing your cafe experience...'));

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Logging In...</title>
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            :root {
                --primary-grad: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
                --bg-grad: linear-gradient(135deg,#0f0c29 0%,#302b63 50%,#24243e 100%);
            }

            body {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Inter','Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
                background: var(--bg-grad);
                overflow: hidden;
                position: relative;
                color: #fff;
            }

            body::before,
            body::after {
                content: '';
                position: absolute;
                border-radius: 50%;
                filter: blur(2px);
                opacity: 0.4;
                animation: orbFloat 8s ease-in-out infinite alternate;
            }

            body::before {
                width: 280px;
                height: 280px;
                left: -70px;
                top: -60px;
                background: radial-gradient(circle,#667eea 0%,transparent 70%);
            }

            body::after {
                width: 220px;
                height: 220px;
                right: -40px;
                bottom: -40px;
                background: radial-gradient(circle,#764ba2 0%,transparent 70%);
            }

            .transition-card {
                position: relative;
                z-index: 1;
                background: rgba(255, 255, 255, 0.06);
                border: 1px solid rgba(255, 255, 255, 0.14);
                box-shadow: 0 32px 80px rgba(0,0,0,0.45);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 22px;
                padding: 30px 34px;
                text-align: center;
                min-width: 320px;
                animation: cardIn 0.55s cubic-bezier(0.34,1.56,0.64,1);
            }

            .transition-title {
                color: #fff;
                font-size: 22px;
                font-weight: 800;
                letter-spacing: -0.2px;
                margin-bottom: 10px;
            }

            .transition-subtitle {
                color: rgba(255,255,255,0.68);
                font-size: 14px;
                margin-bottom: 18px;
            }

            .spinner {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                border: 3px solid rgba(255,255,255,0.2);
                border-top-color: #8ea5ff;
                margin: 0 auto;
                animation: spin 0.8s linear infinite;
            }

            .progress-track {
                margin-top: 16px;
                height: 6px;
                width: 100%;
                border-radius: 999px;
                background: rgba(255,255,255,0.08);
                overflow: hidden;
            }

            .progress-fill {
                height: 100%;
                border-radius: inherit;
                background: var(--primary-grad);
                animation: progressFill 1.2s ease forwards;
            }

            .fade-out {
                animation: fadeOut 0.35s ease-in forwards;
            }

            @keyframes cardIn {
                from {
                    opacity: 0;
                    transform: translateY(18px) scale(0.96);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            @keyframes fadeOut {
                to {
                    opacity: 0;
                    transform: translateY(-12px) scale(0.98);
                }
            }

            @keyframes orbFloat {
                from { transform: translateY(-8px); }
                to { transform: translateY(10px); }
            }

            @keyframes progressFill {
                from { width: 0; }
                to { width: 100%; }
            }
        </style>
    </head>
    <body>
        <div class="transition-card" id="transitionCard">
            <div class="transition-title">Welcome, <?php echo htmlspecialchars($user['fullname']); ?>!</div>
            <div class="transition-subtitle"><?php echo htmlspecialchars($subtitle); ?></div>
            <div class="spinner"></div>
            <div class="progress-track" aria-hidden="true"><div class="progress-fill"></div></div>
        </div>

        <script>
            setTimeout(function () {
                document.getElementById('transitionCard').classList.add('fade-out');
            }, 950);

            setTimeout(function () {
                window.location.href = '<?php echo htmlspecialchars($redirect_url, ENT_QUOTES); ?>';
            }, 1250);
        </script>
    </body>
    </html>
    <?php
    exit;
}

header('Location: login.html?error=' . urlencode('Invalid username or password.'));
mysqli_stmt_close($stmt);
?>
