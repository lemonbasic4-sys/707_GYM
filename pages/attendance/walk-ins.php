<?php
/** Walk-in visitor visits and check-out. */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

$db = Database::getConnection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token mismatch. Please try again.');
    } else {
        $paymentId = filter_var($_POST['checkout_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$paymentId || $paymentId < 1) {
            setFlash('error', 'Select a valid walk-in visit.');
        } else {
            $checkout = $db->prepare(
                "UPDATE payments SET visitor_check_out = NOW()
                 WHERE id = :id AND transaction_type = 'walk_in_pass'
                   AND visitor_name IS NOT NULL AND visitor_check_in IS NOT NULL
                   AND visitor_check_out IS NULL AND status = 'paid'"
            );
            $checkout->execute([':id' => $paymentId]);
            if ($checkout->rowCount() === 1) {
                logActivity($_SESSION['admin_id'], 'walkin.checkout', "Checked out walk-in payment ID {$paymentId}");
                setFlash('success', 'Visitor checked out.');
            } else {
                setFlash('warning', 'This visit was already checked out or is unavailable.');
            }
        }
    }
    redirect(BASE_URL . '/pages/attendance/walk-ins.php');
}

$activeVisits = $db->query(
    "SELECT id, transaction_id, visitor_name, visitor_check_in
     FROM payments WHERE transaction_type = 'walk_in_pass' AND visitor_name IS NOT NULL
       AND visitor_check_in IS NOT NULL AND visitor_check_out IS NULL AND status = 'paid'
     ORDER BY visitor_check_in DESC"
)->fetchAll();
$recentVisits = $db->query(
    "SELECT transaction_id, visitor_name, visitor_check_in, visitor_check_out
     FROM payments WHERE transaction_type = 'walk_in_pass' AND visitor_name IS NOT NULL
       AND visitor_check_out IS NOT NULL
     ORDER BY visitor_check_out DESC LIMIT 30"
)->fetchAll();

$pageTitle = 'Walk-in Visits';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Walk-in Visits</h1>
        <p>Check out visitors who bought a walk-in pass.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/payments/record.php?type=walk_in_pass" class="btn btn-primary"><i data-lucide="user-plus"></i> New Walk-in Pass</a>
        <a href="<?= BASE_URL ?>/pages/attendance/index.php" class="btn btn-secondary"><i data-lucide="arrow-left"></i> Member Attendance</a>
    </div>
</div>

<section class="card mb-3">
    <div class="card-header"><h2 class="card-title">Currently in the gym (<?= count($activeVisits) ?>)</h2></div>
    <?php if (!$activeVisits): ?>
    <div class="empty-state"><i data-lucide="user-round-check"></i><h3>No walk-in visitors waiting for check-out</h3></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Visitor</th><th>Pass</th><th>Check-in</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($activeVisits as $visit): ?>
                <tr>
                    <td><?= sanitize($visit['visitor_name']) ?></td>
                    <td><?= sanitize($visit['transaction_id']) ?></td>
                    <td><?= formatDate($visit['visitor_check_in'], 'M d, Y g:i A') ?></td>
                    <td><form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="checkout_id" value="<?= (int)$visit['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm"><i data-lucide="log-out"></i> Check out</button>
                    </form></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-header"><h2 class="card-title">Recent check-outs</h2></div>
    <?php if (!$recentVisits): ?>
    <p class="text-muted">No walk-in check-outs recorded yet.</p>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Visitor</th><th>Pass</th><th>Check-in</th><th>Check-out</th></tr></thead>
            <tbody>
                <?php foreach ($recentVisits as $visit): ?>
                <tr>
                    <td><?= sanitize($visit['visitor_name']) ?></td>
                    <td><?= sanitize($visit['transaction_id']) ?></td>
                    <td><?= formatDate($visit['visitor_check_in'], 'M d, Y g:i A') ?></td>
                    <td><?= formatDate($visit['visitor_check_out'], 'M d, Y g:i A') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
