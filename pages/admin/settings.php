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
require_once __DIR__ . '/../../includes/database_backup.php';

requireAuth();

$pdo = Database::getConnection();
$pageTitle = 'System Settings';

$adminId = $_SESSION['admin_id'];

// Fetch current admin
$stmt = $pdo->prepare("SELECT id, username FROM admins WHERE id = :id");
$stmt->execute([':id' => $adminId]);
$admin = $stmt->fetch();

$errors = [];
$backupError = '';
$activeTab = $_GET['tab'] ?? 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['set_daily_backup', 'create_backup', 'restore_local'], true)) {
    $backupAction = $_POST['action'];
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $backupError = 'Invalid CSRF token. Please reload the page and try again.';
    } else {
        try {
            if ($backupAction === 'set_daily_backup') {
                $enabled = ($_POST['daily_backup_enabled'] ?? '') === '1' ? 1 : 0;
                $setting = $pdo->prepare(
                    'UPDATE backup_settings SET daily_enabled = :enabled WHERE id = 1'
                );
                $setting->execute([':enabled' => $enabled]);
                $storedSetting = (int) $pdo->query('SELECT daily_enabled FROM backup_settings WHERE id = 1')->fetchColumn();
                if ($storedSetting !== $enabled) {
                    throw new RuntimeException('The daily backup setting could not be updated.');
                }

                logActivity(
                    (int) $_SESSION['admin_id'],
                    $enabled ? 'Enabled Daily Database Backups' : 'Disabled Daily Database Backups',
                    'Automatic daily backup generation ' . ($enabled ? 'enabled' : 'disabled')
                );
                setFlash('success', $enabled ? 'Automatic daily backups enabled.' : 'Automatic daily backups turned off.');
            } elseif ($backupAction === 'create_backup') {
                $backup = createDatabaseBackup($pdo, 'manual');
                logActivity((int) $_SESSION['admin_id'], 'Created Database Backup', basename($backup['path']));
                setFlash('success', 'Database backup created: ' . basename($backup['path']));
            } else {
                $fileName = basename((string) ($_POST['backup_file'] ?? ''));
                $backupPath = DATABASE_BACKUP_PATH . DIRECTORY_SEPARATOR . $fileName;
                if (!preg_match('/^' . preg_quote(DB_NAME, '/') . '_backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}_(?:manual|daily|pre-import)\.sql$/', $fileName)
                    || !is_file($backupPath)) {
                    throw new RuntimeException('The selected saved backup could not be found.');
                }
                $sql = file_get_contents($backupPath);
                if ($sql === false) {
                    throw new RuntimeException('The selected saved backup could not be read.');
                }

                $safetyBackup = createDatabaseBackup($pdo, 'pre-import');
                $statementCount = importDatabaseSql($pdo, $sql);
                logActivity(
                    (int) $_SESSION['admin_id'],
                    'Restored Database Backup',
                    $fileName . '; safety backup: ' . basename($safetyBackup['path'])
                );
                setFlash('success', 'Database backup restored (' . $statementCount . ' SQL statements). Safety backup saved as ' . basename($safetyBackup['path']) . '.');
            }
            redirect(BASE_URL . '/pages/admin/settings.php?tab=backup');
        } catch (Throwable $exception) {
            $backupError = 'Database operation failed: ' . $exception->getMessage();
            $activeTab = 'backup';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
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
