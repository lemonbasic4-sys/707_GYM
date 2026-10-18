<?php
/**
 * 707 GYM – Activate / Assign Membership
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db     = Database::getConnection();
$errors = [];

$preMemberId = (int)($_GET['member_id'] ?? 0);

$plans    = $db->query("SELECT * FROM membership_plans WHERE status='active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch.';
    } else {
        $memberId   = (int)($_POST['member_id']   ?? 0);
        $planId     = (int)($_POST['plan_id']     ?? 0);
        $startDate  = $_POST['start_date']         ?? date('Y-m-d');
        $payMethod  = $_POST['payment_method']     ?? 'cash';
        $amountPaid = (float)($_POST['amount']     ?? 0);

        if ($memberId <= 0)  $errors[] = 'Please select a member.';
        if ($planId <= 0)    $errors[] = 'Please select a plan.';
        if (empty($startDate)) $errors[] = 'Start date is required.';

        if (empty($errors)) {
            // Get plan duration
            $plan = $db->prepare("SELECT * FROM membership_plans WHERE id = :id");
            $plan->execute([':id' => $planId]);
            $plan = $plan->fetch();

            if (!$plan) {
                $errors[] = 'Selected plan not found.';
            } else {
                $endDate = date('Y-m-d', strtotime($startDate . ' +' . $plan['duration_days'] . ' days'));

                try {
                    $db->beginTransaction();

                    // Insert membership
                    $msStmt = $db->prepare(
                        "INSERT INTO memberships (member_id, plan_id, start_date, end_date, status, created_at, updated_at)
                         VALUES (:mid, :pid, :sd, :ed, 'active', NOW(), NOW())"
                    );
                    $msStmt->execute([':mid'=>$memberId,':pid'=>$planId,':sd'=>$startDate,':ed'=>$endDate]);
                    $membershipId = (int)$db->lastInsertId();

                    // Insert payment
                    $txnId = 'TXN-' . strtoupper(uniqid());
                    $payStmt = $db->prepare(
                        "INSERT INTO payments (transaction_id, transaction_type, member_id, membership_id, amount, payment_method, recorded_by, payment_date, status, created_at)
                         VALUES (:txn, 'membership_renewal', :mid, :msid, :amt, :pm, :by, :pd, 'paid', NOW())"
                    );
                    $payStmt->execute([
                        ':txn'  => $txnId,
                        ':mid'  => $memberId,
                        ':msid' => $membershipId,
                        ':amt'  => $amountPaid,
                        ':pm'   => $payMethod,
                        ':by'   => $_SESSION['admin_id'],
                        ':pd'   => $startDate,
                    ]);

                    $db->commit();

                    logActivity($_SESSION['admin_id'], 'membership.activate', "Member {$memberId}, plan {$planId}");
                    setFlash('success', 'Membership activated and payment recorded.');
                    redirect(BASE_URL . '/pages/memberships/index.php');
                } catch (Throwable $e) {
                    $db->rollBack();
                    $errors[] = 'Transaction failed: ' . $e->getMessage();
                }
            }
        }
    }
    rotateCsrfToken();
}

$pageTitle = 'Activate Membership';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Activate Membership</h1>
        <p>Assign a plan to a member and record the payment.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/memberships/index.php" class="btn btn-ghost"><i data-lucide="arrow-left"></i> Back</a>
    </div>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= sanitize($e) ?></div><?php endforeach; ?>

<div class="card" style="max-width:640px;">
    <form method="POST" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
            <label>Member <span class="required">*</span></label>
            <input type="text" id="memberSearch" placeholder="Type member name to search..." autocomplete="off">
            <input type="hidden" name="member_id" id="memberIdHidden" value="<?= $preMemberId ?>">
            <span class="form-hint">Start typing to search by name, email, or phone.</span>
        </div>

        <div class="form-group">
            <label>Membership Plan <span class="required">*</span></label>
            <select name="plan_id" id="planSelect" onchange="updatePlanDetails()" required>
                <option value="">Select plan</option>
                <?php foreach ($plans as $p): ?>
                <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" data-duration="<?= $p['duration_days'] ?>">
                    <?= sanitize($p['name']) ?> – <?= formatPhilippineCurrency($p['price']) ?> / <?= $p['duration_days'] ?>d
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Duration</label>
                <input type="text" id="planDuration" readonly placeholder="Auto-filled">
            </div>
            <div class="form-group">
                <label>Plan Price</label>
                <input type="text" id="planPrice" readonly placeholder="Auto-filled">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Start Date <span class="required">*</span></label>
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
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
            <label>Amount Paid (PHP) <span class="required">*</span></label>
            <input type="number" name="amount" id="amountPaid" min="0" step="0.01" placeholder="0.00">
        </div>

        <div style="display:flex; gap:10px; margin-top:24px;">
            <button type="submit" class="btn btn-primary"><i data-lucide="check-circle"></i> Activate Membership</button>
            <a href="<?= BASE_URL ?>/pages/memberships/index.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initMemberTypeahead('memberSearch', 'memberIdHidden', '<?= BASE_URL ?>/api/members.php');
});

function updatePlanDetails() {
    const sel = document.getElementById('planSelect');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('planDuration').value = opt.dataset.duration ? opt.dataset.duration + ' days' : '';
    document.getElementById('planPrice').value    = opt.dataset.price    ? '\u20B1' + parseFloat(opt.dataset.price).toFixed(2) : '';
    document.getElementById('amountPaid').value   = opt.dataset.price    ?? '';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
