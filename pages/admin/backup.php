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
$backups = listDatabaseBackups();
$dailyBackupEnabled = (int) $pdo->query('SELECT daily_enabled FROM backup_settings WHERE id = 1')->fetchColumn() === 1;
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
            <h2 class="page-title">Database Backup</h2>
            <p class="page-subtitle">Protect your gym records with scheduled backups and straightforward database recovery.</p>
        </div>
        <span class="backup-database-label"><i data-lucide="database"></i><?= htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <?php if ($backupError !== ''): ?>
        <div class="alert alert-error backup-alert mb-4">
            <i data-lucide="circle-alert"></i>
            <span><?= htmlspecialchars($backupError, ENT_QUOTES, 'UTF-8') ?></span>
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
                <span class="badge <?= $dailyBackupEnabled ? 'badge-success' : 'badge-muted' ?>">
                    <?= $dailyBackupEnabled ? 'On' : 'Off' ?>
                </span>
            </div>

            <form method="POST" class="backup-toggle-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="set_daily_backup">
                <input type="hidden" name="daily_backup_enabled" value="<?= $dailyBackupEnabled ? '0' : '1' ?>">
                <button
                    type="submit"
                    class="backup-toggle <?= $dailyBackupEnabled ? 'is-on' : '' ?>"
                    role="switch"
                    aria-checked="<?= $dailyBackupEnabled ? 'true' : 'false' ?>"
                    aria-label="<?= $dailyBackupEnabled ? 'Turn off automatic daily backups' : 'Turn on automatic daily backups' ?>"
                >
                    <span class="backup-toggle-thumb"></span>
                </button>
                <span class="backup-toggle-label"><?= $dailyBackupEnabled ? 'Daily backups are on' : 'Daily backups are off' ?></span>
            </form>
            <p class="backup-toggle-hint">Requires the Windows daily task to be installed once. Runs at 1:00 AM while this computer and MySQL are on.</p>

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
                <p>Create a manual backup or turn on the daily schedule to see snapshots here.</p>
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
