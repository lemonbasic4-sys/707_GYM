<?php
/**
 * 707 GYM - System Settings
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = Database::getConnection();
$pageTitle = 'System Settings';

$adminId = $_SESSION['admin_id'];

// Fetch current admin
$stmt = $pdo->prepare("SELECT id, username FROM admins WHERE id = :id");
$stmt->execute([':id' => $adminId]);
$admin = $stmt->fetch();

$errors = [];
$activeTab = $_GET['tab'] ?? 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token.');
        redirect(BASE_URL . '/pages/admin/settings.php');
    }

    $action = $_POST['form_action'] ?? '';

    if ($action === 'update_profile') {
        $activeTab = 'profile';
        $username = trim($_POST['username'] ?? '');

        if (empty($username)) {
            $errors[] = 'Username is required.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM admins WHERE username = :u AND id != :id");
            $chk->execute([':u' => $username, ':id' => $adminId]);
            if ($chk->fetch()) {
                $errors[] = 'That username is already in use by another admin.';
            } else {
                $upd = $pdo->prepare("UPDATE admins SET username = :u WHERE id = :id");
                $upd->execute([':u' => $username, ':id' => $adminId]);
                $_SESSION['admin_name'] = ADMIN_DISPLAY_NAME;
                logActivity($adminId, 'Updated Admin Username', "Username: $username");
                setFlash('success', 'Profile updated successfully.');
                redirect(BASE_URL . '/pages/admin/settings.php?tab=profile');
            }
        }
    } elseif ($action === 'change_password') {
        $activeTab = 'password';
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $errors[] = 'Please fill in all password fields.';
        } elseif ($newPass !== $confirmPass) {
            $errors[] = 'New password and confirmation do not match.';
        } elseif (strlen($newPass) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } else {
            $res = Auth::changePassword($adminId, $currentPass, $newPass);
            if ($res['success']) {
                logActivity($adminId, 'Changed Password', 'Admin password changed');
                setFlash('success', 'Password updated successfully.');
                redirect(BASE_URL . '/pages/admin/settings.php?tab=password');
            } else {
                $errors[] = $res['message'];
            }
        }
    }
}

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>System Settings</h1>
        <p>Manage administrator credentials, profile, and system configurations</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-4">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="settings-tabs" style="border-bottom: 1px solid var(--border); padding-bottom: 12px;">
        <a href="?tab=profile" class="btn <?= $activeTab === 'profile' ? 'btn-primary' : 'btn-ghost' ?>">
            <i data-lucide="user"></i> Admin Account
        </a>
        <a href="?tab=password" class="btn <?= $activeTab === 'password' ? 'btn-primary' : 'btn-ghost' ?>">
            <i data-lucide="lock"></i> Change Password
        </a>
        <a href="?tab=backup" class="btn <?= $activeTab === 'backup' ? 'btn-primary' : 'btn-ghost' ?>">
            <i data-lucide="database"></i> Database Backup
        </a>
        <a href="<?= BASE_URL ?>/pages/memberships/plans.php" class="btn btn-ghost">
            <i data-lucide="credit-card"></i> Manage Membership Plans &rarr;
        </a>
    </div>
</div>

<?php if ($activeTab === 'backup'): ?>
    <?php define('DATABASE_BACKUP_EMBEDDED', true); require BASE_PATH . '/pages/admin/backup.php'; ?>
<?php else: ?>
<div class="card mb-4">
    <?php if ($activeTab === 'profile'): ?>
        <form method="POST" style="max-width: 520px;">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="update_profile">

            <div class="form-group mb-3">
                <label class="form-label" for="name">Display Name</label>
                <input type="text" class="form-control" id="name" value="<?= ADMIN_DISPLAY_NAME ?>" readonly>
            </div>

            <div class="form-group mb-3">
                <label class="form-label" for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary">Save Profile Changes</button>
        </form>
    <?php elseif ($activeTab === 'password'): ?>
        <form method="POST" style="max-width: 520px;">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="change_password">

            <div class="form-group mb-3">
                <label class="form-label" for="current_password">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label" for="new_password">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
            </div>

            <div class="form-group mb-4">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
            </div>

            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
