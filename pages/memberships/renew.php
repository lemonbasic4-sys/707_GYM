<?php
/**
 * 707 GYM – Renew Membership
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db       = Database::getConnection();
$memberId = (int)($_GET['member_id'] ?? 0);
$errors   = [];

$mStmt = $db->prepare("SELECT * FROM members WHERE id = :id LIMIT 1");
$mStmt->execute([':id' => $memberId]);
$member = $mStmt->fetch();

if (!$member) {
    setFlash('error', 'Member not found.');
    redirect(BASE_URL . '/pages/memberships/index.php');
}

// Current/last membership
$msStmt = $db->prepare(
    "SELECT ms.*, p.name AS plan_name, p.price, p.duration_days
     FROM memberships ms
     JOIN membership_plans p ON ms.plan_id = p.id
     WHERE ms.member_id = :mid
     ORDER BY ms.created_at DESC LIMIT 1"
);
$msStmt->execute([':mid' => $member['id']]);
$currentMs = $msStmt->fetch();

$plans    = $db->query("SELECT * FROM membership_plans WHERE status='active' ORDER BY name")->fetchAll();

// Default start: day after expiry (or today)
$defaultStart = $currentMs && $currentMs['end_date']
    ? date('Y-m-d', strtotime($currentMs['end_date'] . ' +1 day'))
    : date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch.';
    } else {
        $planId    = (int)($_POST['plan_id']    ?? 0);
        $startDate = $_POST['start_date']        ?? $defaultStart;
        $payMethod = $_POST['payment_method']    ?? 'cash';
        $amount    = (float)($_POST['amount']    ?? 0);

        if ($planId <= 0)  $errors[] = 'Plan required.';

        if (empty($errors)) {
            $planInfo = $db->prepare("SELECT * FROM membership_plans WHERE id=:id")->execute([':id'=>$planId]) ? null : null;
            $planStmt = $db->prepare("SELECT * FROM membership_plans WHERE id=:id");
            $planStmt->execute([':id' => $planId]);
            $planInfo = $planStmt->fetch();

            $endDate = date('Y-m-d', strtotime($startDate . ' +' . $planInfo['duration_days'] . ' days'));

            try {
                $db->beginTransaction();

                // Expire the old membership
                if ($currentMs && $currentMs['status'] === 'active') {
                    $db->prepare("UPDATE memberships SET status='expired', updated_at=NOW() WHERE id=:id")
                       ->execute([':id' => $currentMs['id']]);
                }

                $db->prepare(
                    "INSERT INTO memberships (member_id, plan_id, start_date, end_date, status, created_at, updated_at)
                     VALUES (:mid,:pid,:sd,:ed,'active',NOW(),NOW())"
                )->execute([':mid'=>$member['id'],':pid'=>$planId,':sd'=>$startDate,':ed'=>$endDate]);
                $newMsId = (int)$db->lastInsertId();

                $txnId = 'TXN-' . strtoupper(uniqid());
                $db->prepare(
                    "INSERT INTO payments (transaction_id, transaction_type, member_id, membership_id, amount, payment_method, recorded_by, payment_date, status, created_at)
                     VALUES (:txn,'membership_renewal',:mid,:msid,:amt,:pm,:by,:pd,'paid',NOW())"
                )->execute([
                    ':txn'=>$txnId,':mid'=>$member['id'],':msid'=>$newMsId,
                    ':amt'=>$amount,':pm'=>$payMethod,
                    ':by'=>$_SESSION['admin_id'],':pd'=>$startDate,
                ]);

                $db->commit();
                logActivity($_SESSION['admin_id'], 'membership.renew', "Member {$member['id']}");
                setFlash('success', 'Membership renewed successfully.');
                redirect(BASE_URL . '/pages/members/view.php?id=' . $member['id']);
            } catch (Throwable $e) {
                $db->rollBack();
                $errors[] = 'Failed: ' . $e->getMessage();
            }
        }
    }
    rotateCsrfToken();
}

$pageTitle = 'Renew Membership';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Renew Membership</h1>
        <p><?= sanitize($member['first_name'].' '.$member['last_name']) ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/members/view.php?id=<?= $member['id'] ?>" class="btn btn-ghost"><i data-lucide="arrow-left"></i> Back</a>
    </div>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= sanitize($e) ?></div><?php endforeach; ?>

<?php if ($currentMs): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
    Current plan: <strong><?= sanitize($currentMs['plan_name']) ?></strong> — expires <?= formatDate($currentMs['end_date']) ?>
    (<?= daysRemaining($currentMs['end_date']) ?> days remaining).
</div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <form method="POST" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
            <label>Plan <span class="required">*</span></label>
            <select name="plan_id" onchange="updatePrice(this)" required>
                <option value="">Select plan</option>
                <?php foreach ($plans as $p): ?>
                <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>"
                    <?= $currentMs && (int)$currentMs['plan_id']===(int)$p['id'] ? 'selected' : '' ?>>
                    <?= sanitize($p['name']) ?> – <?= formatPhilippineCurrency($p['price']) ?> / <?= $p['duration_days'] ?>d
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>New Start Date</label>
                <input type="date" name="start_date" value="<?= $defaultStart ?>">
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="card">Card</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Amount (PHP)</label>
            <input type="number" name="amount" id="renewAmount" min="0" step="0.01"
                   value="<?= $currentMs ? $currentMs['price'] : '' ?>">
        </div>

        <div style="display:flex; gap:10px; margin-top:24px;">
            <button type="submit" class="btn btn-primary"><i data-lucide="refresh-cw"></i> Renew</button>
            <a href="<?= BASE_URL ?>/pages/members/view.php?id=<?= $member['id'] ?>" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
function updatePrice(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('renewAmount').value = opt.dataset.price ?? '';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
