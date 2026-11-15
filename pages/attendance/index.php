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
$attendanceUrl = BASE_URL . '/pages/attendance/index.php'
    . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token mismatch. Please try again.');
        redirect($attendanceUrl);
    }

    if (isset($_POST['checkin_member_id'])) {
        $memberId = filter_var($_POST['checkin_member_id'], FILTER_VALIDATE_INT);
        if (!$memberId || $memberId < 1) {
            setFlash('error', 'Select a valid member to check in.');
            redirect($attendanceUrl);
        }

        $paymentMethod = $_POST['payment_method'] ?? '';

        $member = $db->prepare("SELECT first_name, last_name FROM members WHERE id = :id AND status = 'active'");
        $member->execute([':id' => $memberId]);
        $member = $member->fetch();

        if (!$member) {
            setFlash('error', 'That member is unavailable or archived.');
            redirect($attendanceUrl);
        }

        try {
            $db->beginTransaction();
            $lockMember = $db->prepare("SELECT id FROM members WHERE id = :id AND status = 'active' FOR UPDATE");
            $lockMember->execute([':id' => $memberId]);
            if (!$lockMember->fetchColumn()) {
                throw new RuntimeException('Member is no longer active.');
            }
            $activePlan = $db->prepare(
                "SELECT id FROM memberships WHERE member_id = :id AND status = 'active' AND plan_id <> 3
                 AND start_date <= CURDATE() AND end_date >= CURDATE() LIMIT 1"
            );
            $activePlan->execute([':id' => $memberId]);
            $membershipId = $activePlan->fetchColumn() ?: null;
            $subscription = $db->prepare(
                "SELECT id FROM memberships WHERE member_id = :id AND plan_id = 3 AND status = 'active'
                 AND start_date <= CURDATE() AND end_date >= CURDATE() LIMIT 1"
            );
            $subscription->execute([':id' => $memberId]);
            $eligiblePlan = $db->prepare("SELECT 1 FROM memberships WHERE member_id = :id AND plan_id IN (1, 2) AND status = 'active' AND start_date <= CURDATE() AND end_date >= CURDATE() LIMIT 1");
            $eligiblePlan->execute([':id' => $memberId]);
            $hasSubscription = $eligiblePlan->fetchColumn() && $subscription->fetchColumn();
            $visitFee = $hasSubscription ? 0 : ($membershipId ? 70 : 100);
            if ($visitFee > 0 && ($_POST['payment_confirmed'] ?? '') !== 'yes') {
                throw new RuntimeException('Collect the visit fee before checking in this member.');
            }
            if ($visitFee > 0 && !in_array($paymentMethod, ['cash', 'gcash', 'bank_transfer', 'card'], true)) {
                throw new RuntimeException('Select a valid payment method.');
            }
            $tenderedInput = trim((string)($_POST['amount_tendered'] ?? ''));
            if ($visitFee > 0 && (!preg_match('/^\d+(?:\.\d{1,2})?$/', $tenderedInput) || (float)$tenderedInput < $visitFee)) {
                $db->rollBack();
                setFlash('error', 'Amount tendered must cover the ' . formatPhilippineCurrency($visitFee) . ' visit fee.');
                redirect($attendanceUrl);
            }
            $amountTendered = $visitFee > 0 ? round((float)$tenderedInput, 2) : 0;
            $change = round($amountTendered - $visitFee, 2);
            $checkin = $db->prepare(
                "INSERT INTO attendance (member_id, check_in, date)
                 VALUES (:member_id, NOW(), CURDATE())"
            );
            $checkin->execute([':member_id' => $memberId]);
            if ($visitFee > 0) {
                $payment = $db->prepare(
                    "INSERT INTO payments (transaction_id, transaction_type, member_id, membership_id, amount, payment_method,
                     recorded_by, payment_date, status, notes, created_at)
                     VALUES (:txn, 'member_visit', :member_id, :membership_id, :amount, :method, :admin_id,
                     CURDATE(), 'paid', :notes, NOW())"
                );
                $payment->execute([
                    ':txn' => 'TXN-' . strtoupper(bin2hex(random_bytes(6))),
                    ':member_id' => $memberId,
                    ':membership_id' => $membershipId,
                    ':amount' => $visitFee,
                    ':method' => $paymentMethod,
                    ':admin_id' => $_SESSION['admin_id'],
                    ':notes' => 'Daily gym visit; attendance ID ' . $db->lastInsertId()
                        . '; tendered ' . number_format($amountTendered, 2, '.', '')
                        . '; change ' . number_format($change, 2, '.', ''),
                ]);
            }
            $db->commit();
            logActivity(
                $_SESSION['admin_id'],
                'attendance.checkin',
                "Checked in {$member['first_name']} {$member['last_name']} (member {$memberId})"
            );
            setFlash('success', $hasSubscription
                ? 'Check-in recorded for ' . $member['first_name'] . ' ' . $member['last_name'] . '. Active subscription: no daily fee.'
                : 'Payment of ' . formatPhilippineCurrency($visitFee) . ' and check-in recorded for ' . $member['first_name'] . ' ' . $member['last_name'] . '. Change: ' . formatPhilippineCurrency($change) . '.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) {
                setFlash('error', $e->getMessage());
            } elseif (!$e instanceof PDOException) {
                throw $e;
            } elseif ($e->getCode() === '23000') {
                setFlash('warning', 'This member already has an attendance record today.');
            } else {
                throw $e;
            }
        }
        redirect($attendanceUrl);
    }

    if (isset($_POST['checkout_id'])) {
        $attId = filter_var($_POST['checkout_id'], FILTER_VALIDATE_INT);
        if (!$attId || $attId < 1) {
            setFlash('error', 'Invalid attendance record.');
            redirect($attendanceUrl);
        }

        $checkout = $db->prepare(
            "UPDATE attendance SET check_out = NOW()
             WHERE id = :id AND date = CURDATE() AND check_out IS NULL"
        );
        $checkout->execute([':id' => $attId]);

        if ($checkout->rowCount() === 1) {
            logActivity($_SESSION['admin_id'], 'attendance.checkout', "Attendance ID {$attId}");
            setFlash('success', 'Check-out recorded.');
        } else {
            setFlash('warning', 'This attendance record was already checked out or is no longer available.');
        }
        redirect($attendanceUrl);
    }
}

$availableMembers = $db->query(
    "SELECT m.id, m.first_name, m.last_name, m.phone,
            CASE WHEN EXISTS (SELECT 1 FROM memberships ms WHERE ms.member_id = m.id
                AND ms.plan_id <> 3 AND ms.status = 'active' AND ms.start_date <= CURDATE() AND ms.end_date >= CURDATE())
                 THEN CASE WHEN EXISTS (SELECT 1 FROM memberships sub WHERE sub.member_id = m.id
                     AND sub.plan_id = 3 AND sub.status = 'active' AND sub.start_date <= CURDATE() AND sub.end_date >= CURDATE())
                     AND EXISTS (SELECT 1 FROM memberships eligible WHERE eligible.member_id = m.id
                     AND eligible.plan_id IN (1, 2) AND eligible.status = 'active' AND eligible.start_date <= CURDATE() AND eligible.end_date >= CURDATE())
                     THEN 0 ELSE 70 END ELSE 100 END AS visit_fee
     FROM members m
     WHERE m.status = 'active'
       AND NOT EXISTS (
           SELECT 1 FROM attendance a
           WHERE a.member_id = m.id AND a.date = CURDATE()
       )
     ORDER BY m.first_name, m.last_name"
)->fetchAll();

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
        <a href="<?= BASE_URL ?>/pages/attendance/walk-ins.php" class="btn btn-primary">
            <i data-lucide="user-round"></i> Walk-in Visits
        </a>
        <a href="<?= BASE_URL ?>/pages/attendance/history.php" class="btn btn-secondary">
            <i data-lucide="history"></i> History
        </a>
    </div>
</div>

<section class="card attendance-checkin-card" aria-labelledby="attendanceCheckinTitle">
    <div class="attendance-checkin-copy">
        <span class="attendance-checkin-icon"><i data-lucide="user-check" aria-hidden="true"></i></span>
        <div>
            <h2 id="attendanceCheckinTitle">Check in a member</h2>
            <p>Select an active member to record today's visit.</p>
        </div>
    </div>
    <?php if ($availableMembers): ?>
    <form method="POST" class="attendance-checkin-form">
        <?= csrfField() ?>
        <label for="checkinMember">Member</label>
        <select id="checkinMember" name="checkin_member_id" required>
            <option value="">Select a member</option>
            <?php foreach ($availableMembers as $member): ?>
            <option value="<?= (int)$member['id'] ?>" data-fee="<?= (int)$member['visit_fee'] ?>">
                <?= sanitize($member['first_name'] . ' ' . $member['last_name']) ?><?= $member['phone'] ? ' · ' . sanitize($member['phone']) : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-primary" onclick="showCheckinPayment()">
            <i data-lucide="log-in" aria-hidden="true"></i> Check in
        </button>
    </form>
    <?php else: ?>
    <p class="attendance-checkin-empty">All active members have already checked in today, or there are no active members yet.</p>
    <?php endif; ?>
</section>

<div id="checkinPaymentModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="checkinPaymentTitle">
    <div class="modal">
        <div class="modal-header"><h3 id="checkinPaymentTitle" class="modal-title">Confirm today's visit payment</h3><button type="button" class="modal-close" onclick="closeModal('checkinPaymentModal')" aria-label="Close">&times;</button></div>
        <p id="checkinPaymentMessage"></p>
        <form method="POST" id="checkinPaymentForm">
            <?= csrfField() ?>
            <input type="hidden" name="checkin_member_id" id="confirmedMemberId">
            <input type="hidden" name="payment_confirmed" value="yes">
            <div class="form-group"><label for="visitPaymentMethod">Payment method</label><select id="visitPaymentMethod" name="payment_method" required><option value="cash">Cash</option><option value="gcash">GCash</option><option value="bank_transfer">Bank Transfer</option><option value="card">Card</option></select></div>
            <div class="form-group" id="visitTenderedField"><label for="visitAmountTendered">Amount Tendered (PHP)</label><input type="number" id="visitAmountTendered" name="amount_tendered" min="0" step="0.01" required></div>
            <p aria-live="polite">Visit fee: <strong id="visitFeeDisplay"></strong> &nbsp; Change: <strong id="visitChangeDisplay">₱0.00</strong></p>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('checkinPaymentModal')">Cancel</button><button type="submit" class="btn btn-primary" id="confirmCheckinButton">Paid, Check In</button></div>
        </form>
    </div>
</div>
<script>
const visitTendered = document.getElementById('visitAmountTendered');
const visitChange = document.getElementById('visitChangeDisplay');
let currentVisitFee = 0;
function updateVisitChange() {
    const amount = Number(visitTendered.value);
    visitTendered.setCustomValidity(visitTendered.value !== '' && amount < currentVisitFee ? 'Amount tendered must cover the visit fee.' : '');
    visitChange.textContent = '₱' + Math.max(0, amount - currentVisitFee).toFixed(2);
}
visitTendered.addEventListener('input', updateVisitChange);
function showCheckinPayment() {
    const select = document.getElementById('checkinMember');
    if (!select || !select.value) { select?.reportValidity(); return; }
    const option = select.options[select.selectedIndex];
    document.getElementById('confirmedMemberId').value = select.value;
    currentVisitFee = Number(option.dataset.fee);
    document.getElementById('visitFeeDisplay').textContent = '₱' + currentVisitFee.toFixed(2);
    visitTendered.value = '';
    document.getElementById('visitTenderedField').hidden = currentVisitFee === 0;
    visitTendered.required = currentVisitFee > 0;
    document.getElementById('visitPaymentMethod').closest('.form-group').hidden = currentVisitFee === 0;
    document.getElementById('checkinPaymentTitle').textContent = currentVisitFee === 0 ? 'Confirm subscription check-in' : "Confirm today's visit payment";
    document.getElementById('confirmCheckinButton').textContent = currentVisitFee === 0 ? 'Check In' : 'Paid, Check In';
    updateVisitChange();
    document.getElementById('checkinPaymentMessage').textContent = currentVisitFee === 0
        ? option.textContent.trim() + ': active subscription, no daily fee.'
        : option.textContent.trim() + ': has the ₱' + option.dataset.fee + ' visit fee been paid today?';
    openModal('checkinPaymentModal');
}
</script>

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
