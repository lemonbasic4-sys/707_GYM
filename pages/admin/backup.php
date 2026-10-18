<?php
/**
 * 707 GYM - Database Backup & Import
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database_backup.php';

requireAuth();

if (($_GET['action'] ?? '') === 'download' && isset($_GET['file'])) {
    $fileName = basename((string) $_GET['file']);
    $backupPath = DATABASE_BACKUP_PATH . DIRECTORY_SEPARATOR . $fileName;
    if (!preg_match('/^' . preg_quote(DB_NAME, '/') . '_backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}_(?:manual|daily|pre-import)\.sql$/', $fileName)
        || !is_file($backupPath)) {
        http_response_code(404);
        exit('Backup not found.');
    }

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($backupPath));
    readfile($backupPath);
    exit;
}

if (!defined('DATABASE_BACKUP_EMBEDDED')) {
    redirect(BASE_URL . '/pages/admin/settings.php?tab=backup');
}

$pdo = Database::getConnection();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token. Please reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'create_backup') {
                $backup = createDatabaseBackup($pdo, 'manual');
                logActivity((int) $_SESSION['admin_id'], 'Created Database Backup', basename($backup['path']));
                setFlash('success', 'Database backup created: ' . basename($backup['path']));
                redirect(BASE_URL . '/pages/admin/settings.php?tab=backup');
            } elseif ($action === 'restore_local') {
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
                $sourceName = $fileName;
            } elseif ($action === 'import_sql') {
                if (!isset($_FILES['sql_file'])) {
                    throw new RuntimeException('Please select a valid .sql file to import.');
                }
                if ($_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
                    $uploadError = match ($_FILES['sql_file']['error']) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The SQL file exceeds the server upload limit.',
                        UPLOAD_ERR_PARTIAL => 'The SQL file upload was incomplete. Please try again.',
                        UPLOAD_ERR_NO_FILE => 'Please select a .sql file to import.',
                        default => 'The SQL file could not be uploaded. Check the server upload settings and try again.',
                    };
                    throw new RuntimeException($uploadError);
                }
                if (strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION)) !== 'sql') {
                    throw new RuntimeException('Only .sql database files are supported.');
                }
                if ($_FILES['sql_file']['size'] > 128 * 1024 * 1024) {
                    throw new RuntimeException('The SQL file exceeds the 128 MB application limit.');
                }
                $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
                if ($sql === false) {
                    throw new RuntimeException('The uploaded SQL file could not be read.');
                }
                $sourceName = basename($_FILES['sql_file']['name']);
            } else {
                throw new RuntimeException('Unknown database action.');
            }

            $safetyBackup = createDatabaseBackup($pdo, 'pre-import');
            $statementCount = importDatabaseSql($pdo, $sql);
            logActivity(
                (int) $_SESSION['admin_id'],
                'Imported Database SQL',
                $sourceName . '; safety backup: ' . basename($safetyBackup['path'])
            );
            setFlash('success', 'Database import completed (' . $statementCount . ' SQL statements). Safety backup saved as ' . basename($safetyBackup['path']) . '.');
            redirect(BASE_URL . '/pages/admin/settings.php?tab=backup');
        } catch (Throwable $exception) {
            $err = 'Database operation failed: ' . $exception->getMessage();
        }
    }
}

$backups = listDatabaseBackups();
$today = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
$dailyBackupExists = false;
foreach ($backups as $backup) {
    if (str_contains($backup['name'], '_backup_' . $today . '_') && str_ends_with($backup['name'], '_daily.sql')) {
        $dailyBackupExists = true;
        break;
    }
}

?>

<div class="backup-page">
    <div class="page-header backup-page-header">
        <div>
            <h2 class="page-title">Database Backup &amp; Import</h2>
            <p class="page-subtitle">Protect your gym records with scheduled backups and straightforward database recovery.</p>
        </div>
        <span class="backup-database-label"><i data-lucide="database"></i><?= htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <?php if ($err): ?>
        <div class="alert alert-error backup-alert mb-4">
            <i data-lucide="circle-alert"></i>
            <span><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <div class="backup-action-grid">
        <section class="card backup-panel backup-create-panel">
            <div class="backup-panel-heading">
                <span class="backup-panel-icon"><i data-lucide="hard-drive-download"></i></span>
                <div>
                    <h2 class="card-title">Automatic backups</h2>
                    <p class="text-muted text-sm">A dated snapshot keeps a restore point for your branch.</p>
                </div>
            </div>

            <div class="backup-status-box">
                <span class="backup-status-indicator <?= $dailyBackupExists ? 'is-ready' : 'is-pending' ?>">
                    <i data-lucide="<?= $dailyBackupExists ? 'check' : 'clock-3' ?>"></i>
                </span>
                <div class="backup-status-copy">
                    <span class="text-muted">Daily backup · <?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?= $dailyBackupExists ? 'Backup saved today' : 'No backup saved today' ?></strong>
                </div>
                <span class="badge <?= $dailyBackupExists ? 'badge-success' : 'badge-warning' ?>">
                    <?= $dailyBackupExists ? 'Up to date' : 'Pending' ?>
                </span>
            </div>

            <div class="backup-location">
                <i data-lucide="folder-closed"></i>
                <div>
                    <span class="text-muted">Backup folder</span>
                    <code><?= htmlspecialchars(DATABASE_BACKUP_PATH, ENT_QUOTES, 'UTF-8') ?></code>
                </div>
            </div>

            <form method="POST" class="backup-create-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i> Create Backup Now
                </button>
                <span class="text-muted text-sm">Includes the database schema and records.</span>
            </form>

            <div class="backup-schedule">
                <div class="backup-schedule-heading">
                    <span class="backup-schedule-icon"><i data-lucide="calendar-clock"></i></span>
                    <div>
                        <h3>Set up a daily schedule</h3>
                        <p>Install a Windows Task Scheduler job to run at 1:00 AM.</p>
                    </div>
                </div>
                <p class="backup-schedule-instructions">Run this command once in PowerShell on the computer hosting XAMPP:</p>
                <pre class="backup-command"><code>powershell -ExecutionPolicy Bypass -File "<?= htmlspecialchars(BASE_PATH, ENT_QUOTES, 'UTF-8') ?>\scripts\install_daily_backup_task.ps1"</code></pre>
                <p class="backup-schedule-footnote"><i data-lucide="info"></i> The computer and MySQL service must be running. The task account needs database access and permission to write to the backup folder.</p>
            </div>
        </section>

        <section class="card backup-panel backup-import-panel">
            <div class="backup-panel-heading">
                <span class="backup-panel-icon backup-import-icon"><i data-lucide="file-up"></i></span>
                <div>
                    <h2 class="card-title">Import a database</h2>
                    <p class="text-muted text-sm">Restore data from a compatible SQL backup.</p>
                </div>
            </div>

            <div class="backup-import-warning">
                <i data-lucide="triangle-alert"></i>
                <p><strong>Import changes this database.</strong> Current tables or records may be replaced. A safety backup is saved before importing.</p>
            </div>

            <div class="backup-import-details">
                <span><i data-lucide="file-code-2"></i> SQL files only</span>
                <span><i data-lucide="shield-check"></i> Database-level commands blocked</span>
                <span><i data-lucide="hard-drive"></i> 128 MB application limit</span>
            </div>

            <form method="POST" enctype="multipart/form-data" class="backup-import-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="import_sql">
                <label class="backup-file-picker" for="sql_file">
                    <span class="backup-file-icon"><i data-lucide="file-up"></i></span>
                    <strong id="backup-file-name">Choose a SQL backup file</strong>
                    <span>Browse files on this device · .sql</span>
                    <input type="file" id="sql_file" name="sql_file" accept=".sql,application/sql" required>
                </label>
                <button type="submit" class="btn btn-danger backup-import-submit" onclick="return confirm('Import this SQL database? Current data will be changed. A safety backup will be created first.')">
                    <i data-lucide="upload"></i> Import SQL Database
                </button>
                <p class="backup-upload-limit">PHP's server upload settings may impose a lower limit than 128 MB.</p>
            </form>
        </section>
    </div>

    <section class="card backup-saved-panel">
        <div class="backup-saved-heading">
            <div>
                <h2 class="card-title">Saved backups</h2>
                <p class="text-muted text-sm">Restore or download a snapshot. Names show the date, time, and backup type.</p>
            </div>
            <span class="backup-count"><i data-lucide="archive"></i><?= count($backups) ?> saved</span>
        </div>
        <?php if (!$backups): ?>
            <div class="backup-empty-state">
                <span><i data-lucide="archive"></i></span>
                <h3>No saved backups yet</h3>
                <p>Create a manual backup or enable the daily schedule to see snapshots here.</p>
            </div>
        <?php else: ?>
            <div class="backup-table-wrap">
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th>Backup details</th>
                            <th>Created</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup):
                            preg_match('/_([0-9]{4}-[0-9]{2}-[0-9]{2})_([0-9]{2})-([0-9]{2})-([0-9]{2})_(manual|daily|pre-import)\.sql$/', $backup['name'], $parts);
                            $backupDate = isset($parts[1]) ? $parts[1] . ' ' . $parts[2] . ':' . $parts[3] . ':' . $parts[4] : date('Y-m-d H:i:s', (int) $backup['modified']);
                            $backupType = $parts[5] ?? 'unknown';
                            ?>
                            <tr>
                                <td class="backup-name-cell">
                                    <span class="backup-row-icon"><i data-lucide="file-code-2"></i></span>
                                    <code><?= htmlspecialchars($backup['name'], ENT_QUOTES, 'UTF-8') ?></code>
                                </td>
                                <td><?= htmlspecialchars($backupDate, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge badge-muted"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $backupType)), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= number_format($backup['size'] / 1024, 1) ?> KB</td>
                                <td class="backup-actions-cell">
                                    <a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>/pages/admin/backup.php?action=download&amp;file=<?= rawurlencode($backup['name']) ?>">
                                        <i data-lucide="download"></i> Download
                                    </a>
                                    <form method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="restore_local">
                                        <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Restore this backup? Current data will be replaced after a safety backup is created.')">
                                            <i data-lucide="rotate-ccw"></i> Restore
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
document.getElementById('sql_file')?.addEventListener('change', function () {
    const selectedFile = this.files?.[0];
    document.getElementById('backup-file-name').textContent = selectedFile ? selectedFile.name : 'Choose a SQL backup file';
});
</script>
