<?php
/**
 * 707 GYM – CSV Import Hub
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db = Database::getConnection();

// Recent imports
$recentImports = $db->query(
    "SELECT il.*
     FROM import_logs il
     ORDER BY il.created_at DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'CSV Import';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header imports-page-header">
    <div class="page-header-left">
        <h1>CSV Import</h1>
        <p>Import member, membership, attendance, and payment records.</p>
    </div>
    <div class="page-header-actions imports-page-actions">
        <a href="<?= BASE_URL ?>/pages/imports/history.php" class="btn btn-secondary">
            <i data-lucide="history"></i> Import History
        </a>
    </div>
</div>

<section class="branch-setup-grid imports-branch-transfer">
    <?php foreach (['members' => 'Members', 'memberships' => 'Memberships', 'attendance' => 'Attendance', 'payments' => 'Payments'] as $type => $label): ?>
    <article class="card branch-setup-card">
        <div class="branch-card-heading">
            <span class="branch-card-icon"><i data-lucide="file-up"></i></span>
            <div>
                <h2><?= sanitize($label) ?></h2>
                <p>Upload a CSV and map its columns to <?= strtolower(sanitize($label)) ?> records.</p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/pages/imports/upload.php?type=<?= urlencode($type) ?>" class="btn btn-primary">Import <?= sanitize($label) ?></a>
    </article>
    <?php endforeach; ?>
</section>

<!-- Recent Imports Table -->
<div class="card imports-history-card">
    <div class="card-header">
        <span class="card-title">Recent Imports</span>
    </div>
    <?php if (empty($recentImports)): ?>
    <div class="empty-state"><i data-lucide="upload"></i><h3>No imports yet</h3></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Date</th><th>Type</th><th>Total</th><th>Imported</th><th>Updated</th><th>Skipped</th><th>Errors</th><th>By</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentImports as $imp): ?>
                <tr>
                    <td><?= formatDate($imp['created_at'], 'M d, Y g:i A') ?></td>
                    <td><span class="badge badge-info"><?= sanitize($imp['import_type']) ?></span></td>
                    <td><?= (int)$imp['total_rows'] ?></td>
                    <td class="text-success"><?= (int)$imp['imported'] ?></td>
                    <td><?= (int)$imp['updated'] ?></td>
                    <td class="text-muted"><?= (int)$imp['skipped'] ?></td>
                    <td class="text-danger"><?= (int)$imp['errors'] ?></td>
                    <td><?= sanitize(ADMIN_DISPLAY_NAME) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
