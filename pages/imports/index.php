<?php
/**
 * 707 GYM – CSV Import Hub
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$db = Database::getConnection();

// Recent imports
$recentImports = $db->query(
    "SELECT il.*
     FROM import_logs il
     ORDER BY il.created_at DESC LIMIT 10"
)->fetchAll();

// Last import per type
$lastImports = [];
foreach (['members','memberships','attendance','payments'] as $type) {
    $row = $db->prepare("SELECT created_at, total_rows, imported FROM import_logs WHERE import_type=:t ORDER BY created_at DESC LIMIT 1");
    $row->execute([':t' => $type]);
    $lastImports[$type] = $row->fetch();
}

$pageTitle = 'CSV Import';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header imports-page-header">
    <div class="page-header-left">
        <h1>CSV Import</h1>
        <p>Import data from CSV files exported from other systems.</p>
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

<!-- Import Type Cards -->
<div class="imports-type-grid">
    <?php
    $importTypes = [
        'members'      => ['icon'=>'users',       'label'=>'Members',      'desc'=>'Import member profiles'],
        'memberships'  => ['icon'=>'credit-card', 'label'=>'Memberships',  'desc'=>'Import membership records'],
        'attendance'   => ['icon'=>'calendar-check','label'=>'Attendance', 'desc'=>'Import check-in records'],
        'payments'     => ['icon'=>'dollar-sign', 'label'=>'Payments',     'desc'=>'Import payment history'],
    ];
    foreach ($importTypes as $type => $info):
        $last = $lastImports[$type];
    ?>
    <div class="card imports-type-card">
        <div class="imports-type-heading">
            <div class="kpi-icon imports-type-icon"><i data-lucide="<?= $info['icon'] ?>"></i></div>
            <div>
                <h2 class="card-title"><?= $info['label'] ?></h2>
                <p class="text-muted imports-type-description"><?= $info['desc'] ?></p>
            </div>
        </div>
        <?php if ($last): ?>
        <p class="imports-type-status">
            Last import: <?= timeAgo($last['created_at']) ?> &bull; <?= number_format((int)$last['imported']) ?> records
        </p>
        <?php else: ?>
        <p class="imports-type-status">No previous imports.</p>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/pages/imports/upload.php?type=<?= $type ?>" class="btn btn-primary btn-sm imports-type-action">
            <i data-lucide="upload"></i> Import <?= $info['label'] ?>
        </a>
    </div>
    <?php endforeach; ?>
</div>

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
