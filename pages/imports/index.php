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
    "SELECT il.*, a.name AS admin_name
     FROM import_logs il
     LEFT JOIN admins a ON il.imported_by = a.id
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

<div class="page-header">
    <div class="page-header-left">
        <h1>CSV Import</h1>
        <p>Import data from CSV files exported from other systems.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/imports/history.php" class="btn btn-secondary">
            <i data-lucide="history"></i> Import History
        </a>
    </div>
</div>

<!-- Import Type Cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px,1fr)); gap:16px; margin-bottom:28px;">
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
    <div class="card" style="display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div class="kpi-icon"><i data-lucide="<?= $info['icon'] ?>"></i></div>
            <div>
                <div class="card-title"><?= $info['label'] ?></div>
                <div class="text-muted" style="font-size:13px;"><?= $info['desc'] ?></div>
            </div>
        </div>
        <?php if ($last): ?>
        <p style="font-size:12px; color:var(--muted);">
            Last import: <?= timeAgo($last['created_at']) ?> &bull; <?= number_format((int)$last['imported']) ?> records
        </p>
        <?php else: ?>
        <p style="font-size:12px; color:var(--muted);">No previous imports.</p>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/pages/imports/upload.php?type=<?= $type ?>" class="btn btn-primary btn-sm" style="align-self:flex-start;">
            <i data-lucide="upload"></i> Import <?= $info['label'] ?>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent Imports Table -->
<div class="card">
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
                    <td><?= sanitize($imp['admin_name'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
