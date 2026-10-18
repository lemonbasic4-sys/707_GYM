<?php
/**
 * 707 GYM – Today's Attendance
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db       = Database::getConnection();
$search   = trim($_GET['search']  ?? '');

// Handle checkout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_id'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $attId = (int)$_POST['checkout_id'];
        $db->prepare("UPDATE attendance SET check_out = NOW() WHERE id = :id AND check_out IS NULL")
           ->execute([':id' => $attId]);
        logActivity($_SESSION['admin_id'], 'attendance.checkout', "Attendance ID {$attId}");
        setFlash('success', 'Check-out recorded.');
    }
    redirect(BASE_URL . '/pages/attendance/index.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$where  = ["a.date = CURDATE()"];
$params = [];

if ($search !== '') {
    $where[]  = "(m.first_name LIKE :first_name OR m.last_name LIKE :last_name OR m.phone LIKE :phone)";
    $searchTerm = "%{$search}%";
    $params[':first_name'] = $searchTerm;
    $params[':last_name'] = $searchTerm;
    $params[':phone'] = $searchTerm;
}
$whereSQL = implode(' AND ', $where);

$records = $db->prepare(
    "SELECT a.id, a.check_in, a.check_out,
            m.first_name, m.last_name
     FROM attendance a
     JOIN members m ON a.member_id = m.id
     WHERE {$whereSQL}
     ORDER BY a.check_in DESC"
);
$records->execute($params);
$records = $records->fetchAll();

$pageTitle = "Today's Attendance";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Today's Attendance</h1>
        <p><?= date('F j, Y') ?> &bull; <?= count($records) ?> check-in<?= count($records)!==1?'s':'' ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/attendance/history.php" class="btn btn-secondary">
            <i data-lucide="history"></i> History
        </a>
    </div>
</div>

<form method="GET" class="filter-bar">
    <input type="search" name="search" placeholder="Search member..." value="<?= sanitize($search) ?>">
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="<?= BASE_URL ?>/pages/attendance/index.php" class="btn btn-ghost">Clear</a>
</form>

<div class="card">
    <?php if (empty($records)): ?>
    <div class="empty-state">
        <i data-lucide="calendar-x"></i>
        <h3>No attendance recorded today</h3>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Member</th><th>Check-in</th><th>Check-out</th><th>Duration</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($records as $rec): ?>
                <?php
                    $duration = '';
                    if ($rec['check_out']) {
                        $mins = round((strtotime($rec['check_out']) - strtotime($rec['check_in'])) / 60);
                        $duration = $mins . ' min' . ($mins !== 1 ? 's' : '');
                    }
                ?>
                <tr>
                    <td><?= sanitize($rec['first_name'].' '.$rec['last_name']) ?></td>
                    <td><?= date('g:i A', strtotime($rec['check_in'])) ?></td>
                    <td>
                        <?= $rec['check_out'] ? date('g:i A', strtotime($rec['check_out'])) : '<span class="badge badge-warning">Still In</span>' ?>
                    </td>
                    <td><?= $duration ?: '—' ?></td>
                    <td>
                        <?php if (!$rec['check_out']): ?>
                        <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="checkout_id" value="<?= (int)$rec['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <i data-lucide="log-out"></i> Check-out
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:13px;">Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
