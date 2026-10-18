<?php
/**
 * 707 GYM – Payment Receipt
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$db = Database::getConnection();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid payment ID.');
    redirect(BASE_URL . '/pages/payments/index.php');
}

$stmt = $db->prepare(
    "SELECT p.*, 
            m.first_name, m.last_name,
            pl.name AS plan_name, pl.duration_days,
            ms.start_date, ms.end_date
     FROM payments p
     JOIN members m ON p.member_id = m.id
     LEFT JOIN memberships ms ON p.membership_id = ms.id
     LEFT JOIN membership_plans pl ON ms.plan_id = pl.id
     WHERE p.id = :id LIMIT 1"
);
$stmt->execute([':id' => $id]);
$payment = $stmt->fetch();

if (!$payment) {
    setFlash('error', 'Payment not found.');
    redirect(BASE_URL . '/pages/payments/index.php');
}

$pageTitle = 'Receipt – ' . $payment['transaction_id'];
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Payment Receipt</h1>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-primary btn-print" onclick="window.print()">
            <i data-lucide="printer"></i> Print Receipt
        </button>
        <a href="<?= BASE_URL ?>/pages/payments/index.php" class="btn btn-ghost btn-print">
            <i data-lucide="arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="receipt-wrapper">
    <div class="card">
        <!-- Header -->
        <div style="text-align:center; margin-bottom:24px; padding-bottom:20px; border-bottom:1px solid var(--border);">
            <div style="width:48px;height:48px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:16px;margin:0 auto 12px;">707</div>
            <h2 style="font-size:20px; margin-bottom:4px;"><?= APP_NAME ?></h2>
            <p class="text-muted" style="font-size:13px;">Official Payment Receipt</p>
        </div>

        <!-- Receipt Info -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; font-size:14px; margin-bottom:24px;">
            <div>
                <span class="text-muted" style="font-size:12px;">RECEIPT NO.</span>
                <p style="font-weight:600; font-size:16px; margin-top:2px;"><?= sanitize($payment['transaction_id']) ?></p>
            </div>
            <div style="text-align:right;">
                <span class="text-muted" style="font-size:12px;">DATE</span>
                <p style="font-weight:600; margin-top:2px;"><?= formatDate($payment['payment_date']) ?></p>
            </div>
        </div>

        <!-- Member Info -->
        <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:var(--radius-sm); padding:16px; margin-bottom:20px;">
            <p class="text-muted" style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px;">Member Information</p>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:14px;">
                <div>
                    <span class="text-muted">Name</span><br>
                    <strong><?= sanitize($payment['first_name'].' '.$payment['last_name']) ?></strong>
                </div>
                <div>
                    <span class="text-muted">Transaction Type</span><br>
                    <?= sanitize(ucwords(str_replace('_', ' ', $payment['transaction_type']))) ?>
                </div>
                <div>
                    <span class="text-muted">Plan</span><br>
                    <?= sanitize($payment['plan_name'] ?? '—') ?>
                </div>
                <?php if ($payment['start_date']): ?>
                <div>
                    <span class="text-muted">Validity</span><br>
                    <?= formatDate($payment['start_date']) ?> – <?= formatDate($payment['end_date']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Amount -->
        <div style="text-align:center; margin-bottom:24px;">
            <p class="text-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Amount Paid</p>
            <p style="font-size:42px; font-weight:700; color:var(--accent);">
                <?= formatPhilippineCurrency($payment['amount']) ?>
            </p>
            <span class="badge badge-<?= $payment['status']==='paid'?'success':($payment['status']==='partial'?'warning':'danger') ?>" style="font-size:13px; padding:4px 16px;">
                <?= strtoupper($payment['status']) ?>
            </span>
        </div>

        <!-- Details -->
        <div style="font-size:13px; color:var(--muted); text-align:center; padding-top:16px; border-top:1px solid var(--border);">
            <p>Payment Method: <strong style="color:var(--text);"><?= sanitize(strtoupper($payment['payment_method'])) ?></strong></p>
            <p style="margin-top:4px;">Recorded by: <?= sanitize(ADMIN_DISPLAY_NAME) ?></p>
            <?php if ($payment['notes']): ?>
            <p style="margin-top:8px; font-style:italic;"><?= sanitize($payment['notes']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
