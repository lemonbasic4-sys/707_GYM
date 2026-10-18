<?php
/**
 * 707 GYM – Payments List
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$db       = Database::getConnection();
$search   = trim($_GET['search']   ?? '');
$method   = $_GET['method']        ?? '';
$status   = $_GET['status']        ?? '';
$dateFrom = $_GET['date_from']     ?? '';
$dateTo   = $_GET['date_to']       ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = "(m.first_name LIKE :first_name OR m.last_name LIKE :last_name OR p.transaction_id LIKE :transaction_id)";
    $searchTerm = "%{$search}%";
    $params[':first_name'] = $searchTerm;
    $params[':last_name'] = $searchTerm;
    $params[':transaction_id'] = $searchTerm;
}
if ($method)       { $where[] = 'p.payment_method = :pm'; $params[':pm'] = $method; }
if ($status)       { $where[] = 'p.status = :st'; $params[':st'] = $status; }
if ($dateFrom)     { $where[] = 'p.payment_date >= :df'; $params[':df'] = $dateFrom; }
if ($dateTo)       { $where[] = 'p.payment_date <= :dt'; $params[':dt'] = $dateTo; }

$whereSQL = implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM payments p JOIN members m ON p.member_id=m.id WHERE {$whereSQL}");
$cntStmt->execute($params);
$totalItems = (int)$cntStmt->fetchColumn();
$pagination = getPaginationData($totalItems, $page);

// Total filtered amount
$sumStmt = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN members m ON p.member_id=m.id WHERE {$whereSQL}");
$sumStmt->execute($params);
$totalAmount = (float)$sumStmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT p.id, p.transaction_id, p.transaction_type, p.amount, p.payment_method, p.payment_date, p.status, p.notes,
            m.first_name, m.last_name,
            pl.name AS plan_name
     FROM payments p
     JOIN members m ON p.member_id = m.id
     LEFT JOIN memberships ms ON p.membership_id = ms.id
     LEFT JOIN membership_plans pl ON ms.plan_id = pl.id
     WHERE {$whereSQL}
     ORDER BY p.created_at DESC
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$payments = $stmt->fetchAll();

$pageTitle = 'Payments';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Payments</h1>
        <p><?= number_format($totalItems) ?> records &bull; Total: <?= formatPhilippineCurrency($totalAmount) ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/payments/record.php" class="btn btn-primary"><i data-lucide="plus"></i> Record Payment</a>
        <a href="<?= BASE_URL ?>/pages/payments/history.php" class="btn btn-secondary"><i data-lucide="history"></i> Full History</a>
    </div>
</div>

<form method="GET" class="filter-bar payments-filter-bar">
    <input type="search" name="search" placeholder="Search member, txn..." value="<?= sanitize($search) ?>">
    <select name="method">
        <option value="">All Methods</option>
        <option value="cash"          <?= $method==='cash'?'selected':'' ?>>Cash</option>
        <option value="gcash"         <?= $method==='gcash'?'selected':'' ?>>GCash</option>
        <option value="bank_transfer" <?= $method==='bank_transfer'?'selected':'' ?>>Bank Transfer</option>
        <option value="card"          <?= $method==='card'?'selected':'' ?>>Card</option>
    </select>
    <select name="status">
        <option value="">All Statuses</option>
        <option value="paid"    <?= $status==='paid'?'selected':'' ?>>Paid</option>
        <option value="partial" <?= $status==='partial'?'selected':'' ?>>Partial</option>
        <option value="unpaid"  <?= $status==='unpaid'?'selected':'' ?>>Unpaid</option>
    </select>
    <div class="payments-date-range">
        <label>From<input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>"></label>
        <label>To<input type="date" name="date_to" value="<?= sanitize($dateTo) ?>"></label>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="<?= BASE_URL ?>/pages/payments/index.php" class="btn btn-ghost">Clear</a>
</form>

<div class="card">
    <?php if (empty($payments)): ?>
    <div class="empty-state"><i data-lucide="dollar-sign"></i><h3>No payments found</h3></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="payments-table">
            <thead>
                <tr><th>Txn ID</th><th>Type</th><th>Member</th><th>Plan</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $pmt): ?>
                <tr>
                    <td><span class="badge badge-muted" style="font-size:10px;"><?= sanitize($pmt['transaction_id']) ?></span></td>
                    <td><?= sanitize(ucwords(str_replace('_', ' ', $pmt['transaction_type']))) ?></td>
                    <td><?= sanitize($pmt['first_name'].' '.$pmt['last_name']) ?></td>
                    <td><?= sanitize($pmt['plan_name'] ?? '—') ?></td>
                    <td class="fw-600"><?= formatPhilippineCurrency($pmt['amount']) ?></td>
                    <td><span class="badge badge-info"><?= sanitize($pmt['payment_method']) ?></span></td>
                    <td><?= formatDate($pmt['payment_date']) ?></td>
                    <td><span class="badge badge-<?= $pmt['status']==='paid'?'success':($pmt['status']==='partial'?'warning':'danger') ?>"><?= sanitize($pmt['status']) ?></span></td>
                    <td>
                        <a href="<?= BASE_URL ?>/pages/payments/receipt.php?id=<?= (int)$pmt['id'] ?>" class="btn btn-ghost btn-sm" title="Receipt">
                            <i data-lucide="receipt"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary row -->
    <div style="padding:12px 16px; border-top:1px solid var(--border); text-align:right; font-size:14px;">
        <strong>Filtered Total: <?= formatPhilippineCurrency($totalAmount) ?></strong>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination mt-2">
        <?php for ($i = max(1,$pagination['current_page']-2); $i <= min($pagination['total_pages'],$pagination['current_page']+2); $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&method=<?= urlencode($method) ?>&status=<?= urlencode($status) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
           class="page-link <?= $i===$pagination['current_page']?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
