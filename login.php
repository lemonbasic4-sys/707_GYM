<?php
/**
 * 707 GYM – Login Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in → redirect
if (isLoggedIn()) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter your username and password.';
        } else {
            $auth = new Auth();
            if ($auth->login($username, $password)) {
                // Log activity
                logActivity($_SESSION['admin_id'], 'auth.login', 'Admin logged in.');

                // Redirect to intended page or dashboard
                $redirectTo = $_SESSION['redirect_after_login'] ?? (BASE_URL . '/pages/dashboard.php');
                unset($_SESSION['redirect_after_login']);
                redirect($redirectTo);
            } else {
                $error = 'Invalid username or password. Please try again.';
            }
        }
    }
    // Rotate token after any POST attempt
    rotateCsrfToken();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login | <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
</head>
<body>

<div class="auth-card">
    <!-- Logo -->
    <div class="auth-logo">
        <img class="auth-logo-image" src="<?= BASE_URL ?>/assets/img/gym_logo.png" alt="707 GYM logo">
    </div>

    <!-- Error alert -->
    <?php if ($error): ?>
    <div class="alert-auth" role="alert">
        <i data-lucide="alert-circle"></i>
        <span><?= sanitize($error) ?></span>
    </div>
    <?php endif; ?>

    <!-- Login form -->
    <form method="POST" action="" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                value="<?= sanitize($username) ?>"
                placeholder="Enter your username"
                autocomplete="username"
                required
                autofocus
                class="<?= ($error && empty($username)) ? 'is-invalid' : '' ?>"
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-group">
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
                <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Toggle password visibility">
                    <i data-lucide="eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-auth">
            Sign In
        </button>
    </form>

    <div class="auth-footer">
        <p>Gym Management System & Analytics</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    function togglePassword() {
        const pwd  = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
        } else {
            pwd.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
        }
        lucide.createIcons();
    }
</script>
</body>
</html>
