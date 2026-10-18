<?php
/**
 * 707 GYM - Audit Trail & Activity Logs
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = Database::getConnection();
$pageTitle = 'Audit Logs';

$search = trim($_GET['search'] ?? '');
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(al.action LIKE :action OR al.details LIKE :details OR a.name LIKE :admin_name)";
    $searchTerm = "%$search%";
    $params[':action'] = $searchTerm;
    $params[':details'] = $searchTerm;
    $params[':admin_name'] = $searchTerm;
}

$whereClause = implode(' AND ', $where);

// Total items
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al LEFT JOIN admins a ON al.admin_id = a.id WHERE $whereClause");
$cntStmt->execute($params);
$totalLogs = $cntStmt->fetchColumn();

$pagination = getPaginationData($totalLogs, $page, 25);

// Fetch logs
$sql = "SELECT al.*, a.name as admin_name 
        FROM activity_logs al 
        LEFT JOIN admins a ON al.admin_id = a.id 
        WHERE $whereClause 
        ORDER BY al.created_at DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Audit Trail &amp; Activity Log</h1>
        <p>Complete chronological record of administrative actions and data mutations</p>
    </div>
</div>

<div class="card mb-4">
    <form method="GET" class="filter-form flex-between flex-wrap gap-3">
        <div class="search-input-group" style="flex: 1; max-width: 400px;">
            <input type="text" name="search" class="form-control" placeholder="Search actions, details, admin..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="flex-center gap-2">
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="<?= BASE_URL ?>/pages/admin/audit-log.php" class="btn btn-ghost">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" class="text-center text-muted">No log records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?= date('Y-m-d H:i:s', strtotime($l['created_at'])) ?></td>
                            <td><strong><?= htmlspecialchars($l['admin_name'] ?? 'System') ?></strong></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($l['action']) ?></span></td>
                            <td style="max-width:350px;"><?= htmlspecialchars($l['details'] ?? '-') ?></td>
                            <td><code><?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="pagination flex-center gap-2 mt-4">
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
