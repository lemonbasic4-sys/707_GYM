<?php
/**
 * 707 GYM – Import History
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$db = Database::getConnection();

$logs = $db->query(
    "SELECT il.*, a.name AS admin_name
     FROM import_logs il
     LEFT JOIN admins a ON il.imported_by = a.id
     ORDER BY il.created_at DESC"
)->fetchAll();

$pageTitle = 'Import History';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Import History</h1>
        <p>Log of all CSV imports performed in the system.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/imports/index.php" class="btn btn-ghost"><i data-lucide="arrow-left"></i> Back</a>
    </div>
</div>

<div class="card">
    <?php if (empty($logs)): ?>
    <div class="empty-state"><i data-lucide="upload"></i><h3>No imports yet</h3></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Date</th><th>Type</th><th>File</th><th>Total</th><th>Imported</th><th>Updated</th><th>Skipped</th><th>Errors</th><th>By</th></tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= formatDate($log['created_at'], 'M d, Y g:i A') ?></td>
                    <td><span class="badge badge-info"><?= sanitize($log['import_type']) ?></span></td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= sanitize($log['filename']) ?>"><?= sanitize($log['filename']) ?></td>
                    <td><?= (int)$log['total_rows'] ?></td>
                    <td class="text-success"><?= (int)$log['imported'] ?></td>
                    <td><?= (int)$log['updated'] ?></td>
                    <td class="text-muted"><?= (int)$log['skipped'] ?></td>
                    <td class="<?= (int)$log['errors']>0?'text-danger':'' ?>"><?= (int)$log['errors'] ?></td>
                    <td><?= sanitize($log['admin_name'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
