<?php
/**
 * 707 GYM – Attendance History
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$db       = Database::getConnection();
$search   = trim($_GET['search']   ?? '');
$dateFrom = $_GET['date_from']     ?? '';
$dateTo   = $_GET['date_to']       ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = "(m.first_name LIKE :first_name OR m.last_name LIKE :last_name OR m.email LIKE :email OR m.phone LIKE :phone)";
    $searchTerm = "%{$search}%";
    $params[':first_name'] = $searchTerm;
    $params[':last_name'] = $searchTerm;
    $params[':email'] = $searchTerm;
    $params[':phone'] = $searchTerm;
}
if ($dateFrom)     { $where[] = 'a.date >= :df'; $params[':df'] = $dateFrom; }
if ($dateTo)       { $where[] = 'a.date <= :dt'; $params[':dt'] = $dateTo; }

$whereSQL = implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM attendance a JOIN members m ON a.member_id=m.id WHERE {$whereSQL}");
$cntStmt->execute($params);
$totalItems = (int)$cntStmt->fetchColumn();
$pagination = getPaginationData($totalItems, $page);

$stmt = $db->prepare(
    "SELECT a.id, a.check_in, a.check_out, a.date,
            m.first_name, m.last_name
     FROM attendance a
     JOIN members m ON a.member_id = m.id
     WHERE {$whereSQL}
     ORDER BY a.check_in DESC
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$records = $stmt->fetchAll();

$pageTitle = 'Attendance History';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Attendance History</h1>
        <p><?= number_format($totalItems) ?> total records</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/attendance/checkin.php" class="btn btn-primary"><i data-lucide="log-in"></i> Check-in</a>
    </div>
</div>

<form method="GET" class="filter-bar">
    <input type="search" name="search" placeholder="Search member..." value="<?= sanitize($search) ?>">
    <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" placeholder="From">
    <input type="date" name="date_to"   value="<?= sanitize($dateTo) ?>" placeholder="To">
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="<?= BASE_URL ?>/pages/attendance/history.php" class="btn btn-ghost">Clear</a>
</form>

<div class="card">
    <?php if (empty($records)): ?>
    <div class="empty-state"><i data-lucide="calendar-x"></i><h3>No records found</h3></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Member</th><th>Date</th><th>Check-in</th><th>Check-out</th><th>Duration</th></tr>
            </thead>
            <tbody>
                <?php foreach ($records as $rec): ?>
                <?php
                    $dur = '';
                    if ($rec['check_out']) {
                        $mins = round((strtotime($rec['check_out']) - strtotime($rec['check_in'])) / 60);
                        $hrs  = floor($mins/60);
                        $rem  = $mins % 60;
                        $dur  = $hrs > 0 ? "{$hrs}h {$rem}m" : "{$rem}m";
                    }
                ?>
                <tr>
                    <td><?= sanitize($rec['first_name'].' '.$rec['last_name']) ?></td>
                    <td><?= formatDate($rec['date']) ?></td>
                    <td><?= date('g:i A', strtotime($rec['check_in'])) ?></td>
                    <td><?= $rec['check_out'] ? date('g:i A', strtotime($rec['check_out'])) : '<span class="badge badge-warning">In Gym</span>' ?></td>
                    <td><?= $dur ?: '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination mt-2">
        <?php for ($i = max(1,$pagination['current_page']-2); $i <= min($pagination['total_pages'],$pagination['current_page']+2); $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
           class="page-link <?= $i===$pagination['current_page']?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
