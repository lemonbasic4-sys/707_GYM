<?php
/**
 * 707 GYM – View Member Profile
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db = Database::getConnection();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid member ID.');
    redirect(BASE_URL . '/pages/members/index.php');
}

// Load member
$stmt = $db->prepare("SELECT * FROM members WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$member = $stmt->fetch();

if (!$member) {
    setFlash('error', 'Member not found.');
    redirect(BASE_URL . '/pages/members/index.php');
}

// Membership history
$memberships = $db->prepare(
    "SELECT ms.*, p.name AS plan_name
     FROM memberships ms
     JOIN membership_plans p ON ms.plan_id = p.id
     WHERE ms.member_id = :mid
     ORDER BY ms.created_at DESC"
);
$memberships->execute([':mid' => $id]);
$memberships = $memberships->fetchAll();

// Attendance history (last 30)
$attendance = $db->prepare(
    "SELECT a.*
     FROM attendance a
     WHERE a.member_id = :mid
     ORDER BY a.check_in DESC
     LIMIT 30"
);
$attendance->execute([':mid' => $id]);
$attendance = $attendance->fetchAll();

// Payment history
$payments = $db->prepare(
    "SELECT p.*, pl.name AS plan_name
     FROM payments p
     LEFT JOIN memberships ms ON p.membership_id = ms.id
     LEFT JOIN membership_plans pl ON ms.plan_id = pl.id
     WHERE p.member_id = :mid
     ORDER BY p.created_at DESC"
);
$payments->execute([':mid' => $id]);
$payments = $payments->fetchAll();

// Archive/restore POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $newStatus = $member['status'] === 'active' ? 'archived' : 'active';
        $db->prepare("UPDATE members SET status = :s, updated_at = NOW() WHERE id = :id")
           ->execute([':s' => $newStatus, ':id' => $id]);
        logActivity($_SESSION['admin_id'], 'member.status', "Set member {$id} to {$newStatus}");
        setFlash('success', "Member {$newStatus} successfully.");
        redirect(BASE_URL . '/pages/members/view.php?id=' . $id);
    }
}

$pageTitle = $member['first_name'] . ' ' . $member['last_name'];
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1><?= sanitize($member['first_name'] . ' ' . $member['last_name']) ?></h1>
        <p>
            <span class="badge badge-<?= $member['status'] === 'active' ? 'success' : 'muted' ?>">
                <?= sanitize($member['status']) ?>
            </span>
        </p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/members/edit.php?id=<?= $id ?>" class="btn btn-secondary">
            <i data-lucide="pencil"></i> Edit
        </a>
        <form method="POST" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="toggle_status" value="1">
            <button type="submit"
                    class="btn <?= $member['status'] === 'active' ? 'btn-danger' : 'btn-secondary' ?>"
                    onclick="return confirm('Are you sure?')">
                <i data-lucide="<?= $member['status'] === 'active' ? 'archive' : 'archive-restore' ?>"></i>
                <?= $member['status'] === 'active' ? 'Archive' : 'Restore' ?>
            </button>
        </form>
        <a href="<?= BASE_URL ?>/pages/members/index.php" class="btn btn-ghost">
            <i data-lucide="arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- Profile Card -->
<div style="display:grid; grid-template-columns:1fr 2fr; gap:16px; margin-bottom:24px;">
    <div class="card" style="text-align:center;">
        <?php if ($member['photo']): ?>
        <img src="<?= UPLOAD_URL . '/' . sanitize($member['photo']) ?>"
             alt="Member photo" style="width:120px;height:120px;border-radius:50%;object-fit:cover;margin:0 auto 16px;border:3px solid var(--accent);">
        <?php else: ?>
        <div style="width:120px;height:120px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:42px;font-weight:700;color:#fff;">
            <?= getInitials($member['first_name'] . ' ' . $member['last_name']) ?>
        </div>
        <?php endif; ?>
        <h2 style="font-size:18px;"><?= sanitize($member['first_name'] . ' ' . $member['last_name']) ?></h2>
        <p class="text-muted mt-1" style="font-size:13px;">Member since <?= formatDate($member['created_at']) ?></p>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Personal Information</span></div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:14px;">
            <div><span class="text-muted">Phone</span><br><?= sanitize($member['phone'] ?? '—') ?></div>
            <div><span class="text-muted">Gender</span><br><?= sanitize(ucfirst($member['gender'] ?? '—')) ?></div>
            <div><span class="text-muted">Date of Birth</span><br><?= $member['date_of_birth'] ? formatDate($member['date_of_birth']) : '—' ?></div>
            <div><span class="text-muted">Emergency Contact</span><br><?= sanitize($member['emergency_contact_name'] ?? '—') ?></div>
            <div><span class="text-muted">Emergency Phone</span><br><?= sanitize($member['emergency_contact_phone'] ?? '—') ?></div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs">
    <button class="tab-btn active" onclick="switchTab(this,'tabMemberships')">Membership History</button>
    <button class="tab-btn" onclick="switchTab(this,'tabAttendance')">Attendance (Last 30)</button>
    <button class="tab-btn" onclick="switchTab(this,'tabPayments')">Payments</button>
</div>

<!-- Memberships Tab -->
<div id="tabMemberships" class="tab-panel active">
    <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
        <a href="<?= BASE_URL ?>/pages/memberships/activate.php?member_id=<?= $id ?>" class="btn btn-primary btn-sm">
            <i data-lucide="plus"></i> Activate Membership
        </a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Plan</th><th>Start</th><th>Expiry</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($memberships)): ?>
                <tr><td colspan="4" class="text-center text-muted" style="padding:20px;">No memberships yet.</td></tr>
                <?php else: ?>
                <?php foreach ($memberships as $ms): ?>
                <tr>
                    <td><?= sanitize($ms['plan_name']) ?></td>
                    <td><?= formatDate($ms['start_date']) ?></td>
                    <td><?= formatDate($ms['end_date']) ?></td>
                    <td><span class="badge badge-<?= $ms['status'] === 'active' ? 'success' : ($ms['status'] === 'expired' ? 'danger' : 'muted') ?>"><?= sanitize($ms['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Attendance Tab -->
<div id="tabAttendance" class="tab-panel">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Check-in</th><th>Check-out</th><th>Duration</th></tr></thead>
            <tbody>
                <?php if (empty($attendance)): ?>
                <tr><td colspan="3" class="text-center text-muted" style="padding:20px;">No attendance records.</td></tr>
                <?php else: ?>
                <?php foreach ($attendance as $att): ?>
                <tr>
                    <td><?= formatDate($att['check_in'], 'M d, Y g:i A') ?></td>
                    <td><?= $att['check_out'] ? formatDate($att['check_out'], 'g:i A') : '<span class="badge badge-warning">In Gym</span>' ?></td>
                    <td>
                        <?php if ($att['check_out']):
                            $duration = (strtotime($att['check_out']) - strtotime($att['check_in'])) / 60;
                            echo number_format($duration, 0) . ' min';
                        else: echo '—'; endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Payments Tab -->
<div id="tabPayments" class="tab-panel">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Txn ID</th><th>Plan</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:20px;">No payment records.</td></tr>
                <?php else: ?>
                <?php foreach ($payments as $pay): ?>
                <tr>
                    <td><span class="badge badge-muted"><?= sanitize($pay['transaction_id']) ?></span></td>
                    <td><?= sanitize($pay['plan_name'] ?? '—') ?></td>
                    <td><?= formatPhilippineCurrency($pay['amount']) ?></td>
                    <td><?= sanitize($pay['payment_method']) ?></td>
                    <td><?= formatDate($pay['payment_date']) ?></td>
                    <td><span class="badge badge-<?= $pay['status'] === 'paid' ? 'success' : 'warning' ?>"><?= sanitize($pay['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function switchTab(btn, panelId) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(panelId).classList.add('active');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
