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
        <h1>Branch Data Transfer</h1>
        <p>Export or import all branch data with one CSV file.</p>
    </div>
    <div class="page-header-actions imports-page-actions">
        <a href="<?= BASE_URL ?>/pages/analytics/branches.php" class="btn btn-secondary">
            <i data-lucide="git-compare-arrows"></i> Branch Analytics
        </a>
        <a href="<?= BASE_URL ?>/pages/imports/history.php" class="btn btn-secondary">
            <i data-lucide="history"></i> Import History
        </a>
    </div>
</div>

<section class="branch-setup-grid imports-branch-transfer">
    <article class="card branch-setup-card">
        <div class="branch-card-heading">
            <span class="branch-card-icon"><i data-lucide="database"></i></span>
            <div>
                <h2>Export everything</h2>
                <p>Download one CSV with members, plans, memberships, attendance, and payments.</p>
            </div>
        </div>
        <div class="branch-setup-hint"><i data-lucide="shield"></i><span>Export from each branch, then import the other branches’ files on the system used for combined analytics. Give each installation a unique name in Branch Analytics first.</span></div>
        <a href="<?= BASE_URL ?>/exports/excel.php?type=branch_data" class="btn btn-primary">
            <i data-lucide="download"></i> Export All Branch Data
        </a>
    </article>

    <article class="card branch-setup-card">
        <div class="branch-card-heading">
            <span class="branch-card-icon"><i data-lucide="file-up"></i></span>
            <div>
                <h2>Import everything</h2>
                <p>Import one or more branch CSV exports together.</p>
            </div>
        </div>
        <div class="branch-setup-hint"><i data-lucide="refresh-cw"></i><span>Import files from other branches only. Records stay separate by branch; importing the same branch again replaces its previous copy.</span></div>
        <form method="POST" action="<?= BASE_URL ?>/pages/analytics/branches.php" enctype="multipart/form-data" class="branch-import-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="import_branch_data">
            <input type="hidden" name="return_to" value="imports">
            <input type="hidden" name="month" value="<?= date('Y-m') ?>">
            <label class="branch-file-input">
                <i data-lucide="file-spreadsheet"></i>
                <span><strong>Choose branch CSV files</strong><small>One or more files · up to 15 MB each</small></span>
                <input type="file" name="csv_files[]" accept=".csv,text/csv" multiple required>
            </label>
            <button type="submit" class="btn btn-primary"><i data-lucide="upload"></i> Import All Branch Data</button>
        </form>
    </article>
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
