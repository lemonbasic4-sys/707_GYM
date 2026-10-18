<?php
/**
 * 707 GYM – Payment History (full, with export buttons)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$db       = Database::getConnection();
$search   = trim($_GET['search']   ?? '');
$method   = $_GET['method']        ?? '';
$dateFrom = $_GET['date_from']     ?? date('Y-m-01');
$dateTo   = $_GET['date_to']       ?? date('Y-m-d');
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = "(m.first_name LIKE :first_name OR m.last_name LIKE :last_name OR p.transaction_id LIKE :transaction_id)";
    $searchTerm = "%{$search}%";
    $params[':first_name'] = $searchTerm;
    $params[':last_name'] = $searchTerm;
    $params[':transaction_id'] = $searchTerm;
}
if ($method)        { $where[] = 'p.payment_method = :pm'; $params[':pm'] = $method; }
if ($dateFrom)      { $where[] = 'p.payment_date >= :df'; $params[':df'] = $dateFrom; }
if ($dateTo)        { $where[] = 'p.payment_date <= :dt'; $params[':dt'] = $dateTo; }

$whereSQL = implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*), COALESCE(SUM(p.amount),0) FROM payments p JOIN members m ON p.member_id=m.id WHERE {$whereSQL}");
$cntStmt->execute($params);
[$totalItems, $totalAmount] = $cntStmt->fetch(PDO::FETCH_NUM);
$pagination = getPaginationData((int)$totalItems, $page);

$stmt = $db->prepare(
    "SELECT p.transaction_id, p.transaction_type, p.amount, p.payment_method, p.payment_date, p.status,
            m.first_name, m.last_name,
            pl.name AS plan_name
     FROM payments p
     JOIN members m ON p.member_id = m.id
     LEFT JOIN memberships ms ON p.membership_id = ms.id
     LEFT JOIN membership_plans pl ON ms.plan_id = pl.id
     WHERE {$whereSQL}
     ORDER BY p.payment_date DESC, p.created_at DESC
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$payments = $stmt->fetchAll();

// Build export query string
$exportQs = http_build_query(['type'=>'payments','date_from'=>$dateFrom,'date_to'=>$dateTo,'method'=>$method,'search'=>$search]);

$pageTitle = 'Payment History';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Payment History</h1>
        <p><?= number_format((int)$totalItems) ?> records &bull; Total: <?= formatPhilippineCurrency((float)$totalAmount) ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/exports/pdf.php?<?= $exportQs ?>" class="btn btn-secondary" target="_blank">
            <i data-lucide="file-text"></i> Export PDF
        </a>
        <a href="<?= BASE_URL ?>/exports/excel.php?<?= $exportQs ?>" class="btn btn-secondary">
            <i data-lucide="table"></i> Export Excel
        </a>
    </div>
</div>

<form method="GET" class="filter-bar">
    <input type="search" name="search" placeholder="Search..." value="<?= sanitize($search) ?>">
    <select name="method">
        <option value="">All Methods</option>
        <option value="cash">Cash</option>
        <option value="gcash">GCash</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="card">Card</option>
    </select>
    <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>">
    <input type="date" name="date_to"   value="<?= sanitize($dateTo) ?>">
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="<?= BASE_URL ?>/pages/payments/history.php" class="btn btn-ghost">Clear</a>
</form>

<div class="card">
    <?php if (empty($payments)): ?>
    <div class="empty-state"><i data-lucide="dollar-sign"></i><h3>No payments found</h3></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Txn ID</th><th>Type</th><th>Member</th><th>Plan</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr>
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
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="padding:12px 16px; border-top:1px solid var(--border); text-align:right;">
        <strong>Total: <?= formatPhilippineCurrency((float)$totalAmount) ?></strong>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination mt-2">
        <?php for ($i = max(1,$pagination['current_page']-2); $i <= min($pagination['total_pages'],$pagination['current_page']+2); $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&method=<?= urlencode($method) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
           class="page-link <?= $i===$pagination['current_page']?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
