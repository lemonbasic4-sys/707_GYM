<?php
/**
 * 707 GYM – Memberships List
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$db = Database::getConnection();

$search   = trim($_GET['search']  ?? '');
$status   = $_GET['status']       ?? '';
$planId   = (int)($_GET['plan']   ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = "(m.first_name LIKE :first_name OR m.last_name LIKE :last_name OR m.phone LIKE :phone)";
    $searchTerm = "%{$search}%";
    $params[':first_name'] = $searchTerm;
    $params[':last_name'] = $searchTerm;
    $params[':phone'] = $searchTerm;
}
if (in_array($status, ['active','expired','inactive'], true)) { $where[] = 'ms.status = :status'; $params[':status'] = $status; }
if ($planId > 0) { $where[] = 'ms.plan_id = :plan'; $params[':plan'] = $planId; }

$whereSQL = implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM memberships ms JOIN members m ON ms.member_id=m.id WHERE {$whereSQL}");
$cntStmt->execute($params);
$totalItems = (int)$cntStmt->fetchColumn();

$pagination = getPaginationData($totalItems, $page);

$stmt = $db->prepare(
    "SELECT ms.id, ms.start_date, ms.end_date, ms.status,
            m.id AS member_record_id, m.first_name, m.last_name,
            p.name AS plan_name
     FROM memberships ms
     JOIN members m ON ms.member_id = m.id
     JOIN membership_plans p ON ms.plan_id = p.id
     WHERE {$whereSQL}
     ORDER BY ms.created_at DESC
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$memberships = $stmt->fetchAll();

$plans    = $db->query("SELECT id, name FROM membership_plans WHERE status='active' ORDER BY name")->fetchAll();

$pageTitle = 'Memberships';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Memberships</h1>
        <p><?= number_format($totalItems) ?> total record<?= $totalItems !== 1 ? 's' : '' ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/payments/record.php?type=membership_renewal" class="btn btn-primary">
            <i data-lucide="plus"></i> Record Membership Payment
        </a>
        <a href="<?= BASE_URL ?>/pages/memberships/plans.php" class="btn btn-secondary">
            <i data-lucide="list"></i> Plans
        </a>
    </div>
</div>

<form method="GET" class="filter-bar">
    <input type="search" name="search" placeholder="Search member..." value="<?= sanitize($search) ?>">
    <select name="plan">
        <option value="">All Plans</option>
        <?php foreach ($plans as $p): ?><option value="<?= $p['id'] ?>" <?= $planId===$p['id']?'selected':'' ?>><?= sanitize($p['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">All Statuses</option>
        <option value="active"   <?= $status==='active'?'selected':'' ?>>Active</option>
        <option value="expired"  <?= $status==='expired'?'selected':'' ?>>Expired</option>
        <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Canceled</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="<?= BASE_URL ?>/pages/memberships/index.php" class="btn btn-ghost">Clear</a>
</form>

<div class="card">
    <?php if (empty($memberships)): ?>
    <div class="empty-state"><i data-lucide="credit-card"></i><h3>No memberships found</h3></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Member</th><th>Plan</th>
                    <th>Start</th><th>Expiry</th><th>Status</th><th>Days Left</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($memberships as $ms): ?>
                <?php $days = daysRemaining($ms['end_date']); ?>
                <tr>
                    <td><?= sanitize($ms['first_name'].' '.$ms['last_name']) ?></td>
                    <td><?= sanitize($ms['plan_name']) ?></td>
                    <td><?= formatDate($ms['start_date']) ?></td>
                    <td><?= formatDate($ms['end_date']) ?></td>
                    <td>
                        <span class="badge badge-<?= $ms['status']==='active'?'success':($ms['status']==='expired'?'danger':'muted') ?>">
                            <?= $ms['status'] === 'inactive' ? 'Canceled' : sanitize($ms['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($ms['status']==='active'): ?>
                        <span class="badge badge-<?= $days<=7?'warning':'info' ?>"><?= $days ?>d</span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/pages/memberships/renew.php?member_id=<?= (int)$ms['member_record_id'] ?>" class="btn btn-primary btn-sm">Renew</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination mt-2">
        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&plan=<?= $planId ?>"
           class="page-link <?= $i===$pagination['current_page']?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
