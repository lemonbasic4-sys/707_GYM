<?php
/**
 * 707 GYM - Process Payment
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db = Database::getConnection();
$errors = [];
$transactionTypes = [
    'membership_renewal' => 'Membership Renewal',
    'walk_in_pass' => 'Walk-in Pass',
    'companion_bundle' => 'Companion Bundle',
    'coaching_package' => 'Coaching Package',
];
$paymentMethods = [
    'cash' => 'Cash',
    'gcash' => 'GCash',
    'bank_transfer' => 'Bank Transfer',
    'card' => 'Card',
];
$plans = $db->query("SELECT id, name, duration_days, price FROM membership_plans WHERE status = 'active' ORDER BY name")->fetchAll();

$form = [
    'transaction_type' => $_POST['transaction_type'] ?? 'membership_renewal',
    'member_id' => (int)($_POST['member_id'] ?? 0),
    'plan_id' => (int)($_POST['plan_id'] ?? 0),
    'amount' => $_POST['amount'] ?? '',
    'discount' => $_POST['discount'] ?? '0',
    'payment_method' => $_POST['payment_method'] ?? 'cash',
    'amount_tendered' => $_POST['amount_tendered'] ?? '',
    'payment_date' => $_POST['payment_date'] ?? date('Y-m-d'),
    'start_date' => $_POST['start_date'] ?? date('Y-m-d'),
    'notes' => trim($_POST['notes'] ?? ''),
];
$isValidDate = static function (string $value): bool {
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $dateErrors = DateTime::getLastErrors();
    return $date !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch.';
    } else {
        if (!isset($transactionTypes[$form['transaction_type']])) {
            $errors[] = 'Select a valid transaction type.';
        }
        if (!isset($paymentMethods[$form['payment_method']])) {
            $errors[] = 'Select a valid payment method.';
        }
        if ($form['member_id'] <= 0) {
            $errors[] = 'Select a member.';
        }
        if (!$isValidDate($form['payment_date'])) {
            $errors[] = 'Enter a valid payment date.';
        }

        $memberStmt = $db->prepare("SELECT id FROM members WHERE id = :id AND status = 'active'");
        $memberStmt->execute([':id' => $form['member_id']]);
        if (!$memberStmt->fetchColumn()) {
            $errors[] = 'The selected member is unavailable. Select an active member.';
        }

        $plan = null;
        $subtotal = 0.0;
        if ($form['transaction_type'] === 'membership_renewal') {
            if ($form['plan_id'] <= 0) {
                $errors[] = 'Select a membership plan.';
            } else {
                $planStmt = $db->prepare("SELECT id, name, duration_days, price FROM membership_plans WHERE id = :id AND status = 'active'");
                $planStmt->execute([':id' => $form['plan_id']]);
                $plan = $planStmt->fetch();
                if (!$plan) {
                    $errors[] = 'The selected membership plan is unavailable.';
                } else {
                    $subtotal = (float)$plan['price'];
                }
            }
            if (!$isValidDate($form['start_date'])) {
                $errors[] = 'Enter a valid membership start date.';
            }
        } else {
            if (!is_numeric($form['amount']) || (float)$form['amount'] <= 0) {
                $errors[] = 'Enter a package price greater than zero.';
            } else {
                $subtotal = (float)$form['amount'];
            }
        }

        if (!is_numeric($form['discount']) || (float)$form['discount'] < 0) {
            $errors[] = 'Discount must be zero or greater.';
        }
        $discount = is_numeric($form['discount']) ? (float)$form['discount'] : 0.0;
        $total = round($subtotal - $discount, 2);
        if ($discount > $subtotal) {
            $errors[] = 'Discount cannot exceed the subtotal.';
        }
        if ($total <= 0) {
            $errors[] = 'Total due must be greater than zero.';
        }

        $amountTendered = is_numeric($form['amount_tendered']) ? (float)$form['amount_tendered'] : 0.0;
        if ($amountTendered < $total) {
            $errors[] = 'Amount tendered must cover the total due.';
        }

        if (!$errors) {
            try {
                $db->beginTransaction();
                $membershipId = null;

                if ($form['transaction_type'] === 'membership_renewal' && $plan) {
                    $activeStmt = $db->prepare(
                        "SELECT id FROM memberships
                         WHERE member_id = :member_id AND status = 'active'
                         ORDER BY end_date DESC LIMIT 1"
                    );
                    $activeStmt->execute([':member_id' => $form['member_id']]);
                    $activeMembershipId = $activeStmt->fetchColumn();
                    if ($activeMembershipId) {
                        $db->prepare("UPDATE memberships SET status = 'expired', updated_at = NOW() WHERE id = :id")
                            ->execute([':id' => $activeMembershipId]);
                    }

                    $endDate = date('Y-m-d', strtotime($form['start_date'] . ' +' . (int)$plan['duration_days'] . ' days'));
                    $membershipStmt = $db->prepare(
                        "INSERT INTO memberships (member_id, plan_id, start_date, end_date, status, created_at, updated_at)
                         VALUES (:member_id, :plan_id, :start_date, :end_date, 'active', NOW(), NOW())"
                    );
                    $membershipStmt->execute([
                        ':member_id' => $form['member_id'],
                        ':plan_id' => $plan['id'],
                        ':start_date' => $form['start_date'],
                        ':end_date' => $endDate,
                    ]);
                    $membershipId = (int)$db->lastInsertId();
                }

                $noteParts = [];
                if ($form['transaction_type'] === 'membership_renewal' && $plan) {
                    $noteParts[] = 'Plan: ' . $plan['name'];
                }
                if ($discount > 0) {
                    $noteParts[] = 'Discount: ' . number_format($discount, 2, '.', '');
                }
                if ($form['notes'] !== '') {
                    $noteParts[] = $form['notes'];
                }
                $notes = implode(' | ', $noteParts);
                $transactionId = 'TXN-' . strtoupper(bin2hex(random_bytes(6)));

                $paymentStmt = $db->prepare(
                    "INSERT INTO payments
                        (transaction_id, transaction_type, member_id, membership_id, amount, payment_method, notes, recorded_by, payment_date, status, created_at)
                     VALUES
                        (:transaction_id, :transaction_type, :member_id, :membership_id, :amount, :payment_method, :notes, :recorded_by, :payment_date, 'paid', NOW())"
                );
                $paymentStmt->execute([
                    ':transaction_id' => $transactionId,
                    ':transaction_type' => $form['transaction_type'],
                    ':member_id' => $form['member_id'],
                    ':membership_id' => $membershipId,
                    ':amount' => $total,
                    ':payment_method' => $form['payment_method'],
                    ':notes' => $notes !== '' ? $notes : null,
                    ':recorded_by' => $_SESSION['admin_id'],
                    ':payment_date' => $form['payment_date'],
                ]);

                $paymentId = (int)$db->lastInsertId();
                $db->commit();

                logActivity($_SESSION['admin_id'], 'payment.record', "TXN {$transactionId} ({$form['transaction_type']})");
                setFlash('success', 'Payment processed and invoice generated.');
                redirect(BASE_URL . '/pages/payments/receipt.php?id=' . $paymentId);
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errors[] = 'Payment could not be processed. Please try again.';
                error_log('Payment processing failed: ' . $e->getMessage());
            }
        }
    }
    rotateCsrfToken();
}

$selectedPlanPrice = 0.0;
foreach ($plans as $planOption) {
    if ((int)$planOption['id'] === $form['plan_id']) {
        $selectedPlanPrice = (float)$planOption['price'];
        break;
    }
}
$initialSubtotal = $form['transaction_type'] === 'membership_renewal'
    ? $selectedPlanPrice
    : (is_numeric($form['amount']) ? (float)$form['amount'] : 0.0);
$initialDiscount = is_numeric($form['discount']) ? (float)$form['discount'] : 0.0;
$initialTotal = max(0, $initialSubtotal - $initialDiscount);

$pageTitle = 'Process Payment';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Process Payment</h1>
        <p>Choose a transaction type, review the amount, and generate an invoice.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/payments/index.php" class="btn btn-ghost"><i data-lucide="arrow-left"></i> Back</a>
    </div>
</div>

<?php foreach ($errors as $error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endforeach; ?>

<div class="card payment-checkout-card">
    <form method="POST" novalidate id="paymentForm">
        <?= csrfField() ?>

        <fieldset class="transaction-type-fieldset">
            <legend>Transaction Type</legend>
            <div class="transaction-type-options">
                <?php foreach ($transactionTypes as $value => $label): ?>
                <label class="transaction-type-option">
                    <input type="radio" name="transaction_type" value="<?= sanitize($value) ?>"
                        <?= $form['transaction_type'] === $value ? 'checked' : '' ?>>
                    <span class="transaction-type-button">
                        <i data-lucide="<?= $value === 'membership_renewal' ? 'refresh-cw' : ($value === 'walk_in_pass' ? 'user-round' : ($value === 'companion_bundle' ? 'users-round' : 'dumbbell')) ?>"></i>
                        <?= sanitize($label) ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="payment-field">
            <label for="memberSearch">Select Member <span class="required">*</span></label>
            <input type="text" id="memberSearch" placeholder="Search and select member..." autocomplete="off">
            <input type="hidden" name="member_id" id="memberIdHidden" value="<?= (int)$form['member_id'] ?>">
        </div>

        <div class="payment-field payment-plan-field" <?= $form['transaction_type'] !== 'membership_renewal' ? 'hidden' : '' ?>>
            <label for="planSelect">Membership Plan <span class="required">*</span></label>
            <select name="plan_id" id="planSelect">
                <option value="">Select Plan</option>
                <?php foreach ($plans as $plan): ?>
                <option value="<?= (int)$plan['id'] ?>" data-price="<?= htmlspecialchars((string)$plan['price'], ENT_QUOTES, 'UTF-8') ?>" data-duration="<?= (int)$plan['duration_days'] ?>"
                    <?= $form['plan_id'] === (int)$plan['id'] ? 'selected' : '' ?>>
                    <?= sanitize($plan['name']) ?> — <?= formatPhilippineCurrency($plan['price']) ?> / <?= (int)$plan['duration_days'] ?> days
                </option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint" id="planDurationHint"></span>
        </div>

        <div class="payment-field payment-package-price" <?= $form['transaction_type'] === 'membership_renewal' ? 'hidden' : '' ?>>
            <label for="packagePrice">Package Price (PHP) <span class="required">*</span></label>
            <input type="number" id="packagePrice" name="amount" min="0.01" step="0.01" placeholder="Enter package price"
                value="<?= sanitize((string)$form['amount']) ?>">
        </div>

        <div class="payment-field payment-membership-date" <?= $form['transaction_type'] !== 'membership_renewal' ? 'hidden' : '' ?>>
            <label for="startDate">Membership Start Date <span class="required">*</span></label>
            <input type="date" id="startDate" name="start_date" value="<?= sanitize($form['start_date']) ?>">
        </div>

        <div class="payment-field">
            <label for="paymentDate">Payment Date</label>
            <input type="date" id="paymentDate" name="payment_date" value="<?= sanitize($form['payment_date']) ?>" required>
        </div>

        <div class="payment-field">
            <label for="discount">Apply Discount (Optional)</label>
            <input type="number" id="discount" name="discount" min="0" step="0.01" value="<?= sanitize((string)$form['discount']) ?>">
        </div>

        <div class="payment-total-summary" aria-live="polite">
            <div><span>Subtotal</span><span id="subtotalDisplay"><?= formatPhilippineCurrency($initialSubtotal) ?></span></div>
            <div><span>Discount</span><span class="payment-discount" id="discountDisplay">-<?= formatPhilippineCurrency($initialDiscount) ?></span></div>
            <div class="payment-total-due"><strong>Total Due:</strong><strong id="totalDisplay"><?= formatPhilippineCurrency($initialTotal) ?></strong></div>
        </div>

        <div class="payment-form-grid">
            <div class="payment-field">
                <label for="paymentMethod">Payment Method</label>
                <select name="payment_method" id="paymentMethod">
                    <?php foreach ($paymentMethods as $value => $label): ?>
                    <option value="<?= sanitize($value) ?>" <?= $form['payment_method'] === $value ? 'selected' : '' ?>><?= sanitize($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="payment-field">
                <label for="amountTendered">Amount Tendered (PHP) <span class="required">*</span></label>
                <input type="number" id="amountTendered" name="amount_tendered" min="0" step="0.01" value="<?= sanitize((string)$form['amount_tendered']) ?>" required>
                <span class="form-hint">Change: <strong id="changeDisplay">₱0.00</strong></span>
            </div>
        </div>

        <div class="payment-field">
            <label for="notes">Notes (Optional)</label>
            <textarea id="notes" name="notes" rows="2" placeholder="Additional transaction details"><?= sanitize($form['notes']) ?></textarea>
        </div>

        <div class="payment-form-actions">
            <button type="submit" class="btn btn-primary"><i data-lucide="receipt"></i> Confirm Payment &amp; Generate Invoice</button>
            <a href="<?= BASE_URL ?>/pages/payments/index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initMemberTypeahead('memberSearch', 'memberIdHidden', '<?= BASE_URL ?>/api/members.php');

    const form = document.getElementById('paymentForm');
    const plan = document.getElementById('planSelect');
    const packagePrice = document.getElementById('packagePrice');
    const discount = document.getElementById('discount');
    const tendered = document.getElementById('amountTendered');
    const money = value => '\u20B1' + Math.max(0, Number(value) || 0).toFixed(2);

    function updateCheckout() {
        const type = form.querySelector('input[name="transaction_type"]:checked').value;
        const isMembership = type === 'membership_renewal';
        document.querySelector('.payment-plan-field').hidden = !isMembership;
        document.querySelector('.payment-package-price').hidden = isMembership;
        document.querySelector('.payment-membership-date').hidden = !isMembership;
        plan.required = isMembership;
        packagePrice.required = !isMembership;

        const selectedPlan = plan.options[plan.selectedIndex];
        const subtotal = isMembership ? Number(selectedPlan.dataset.price || 0) : Number(packagePrice.value || 0);
        const discountAmount = Number(discount.value || 0);
        const total = Math.max(0, subtotal - discountAmount);
        const change = Math.max(0, Number(tendered.value || 0) - total);

        document.getElementById('subtotalDisplay').textContent = money(subtotal);
        document.getElementById('discountDisplay').textContent = '-' + money(discountAmount);
        document.getElementById('totalDisplay').textContent = money(total);
        document.getElementById('changeDisplay').textContent = money(change);
        document.getElementById('planDurationHint').textContent = isMembership && selectedPlan.dataset.duration
            ? 'Membership duration: ' + selectedPlan.dataset.duration + ' days'
            : '';
    }

    form.querySelectorAll('input[name="transaction_type"]').forEach(input => input.addEventListener('change', updateCheckout));
    [plan, packagePrice, discount, tendered].forEach(input => input.addEventListener('input', updateCheckout));
    plan.addEventListener('change', updateCheckout);
    updateCheckout();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
