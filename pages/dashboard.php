<?php
/**
 * 707 GYM – Dashboard
 * Record keeping shortcuts, upcoming expirations, and recent activity.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$db = Database::getConnection();

$activeMembers = (int) $db->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
$activeMemberships = (int) $db->query("SELECT COUNT(*) FROM memberships WHERE status = 'active' AND end_date >= CURDATE()")->fetchColumn();
$todayCheckIns = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()")->fetchColumn();
$expiringSoon = (int) $db->query(
    "SELECT COUNT(*) FROM memberships
     WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
)->fetchColumn();

// ── Upcoming Expirations (next 7 days) ───────────────────────────────────────
$upcomingStmt = $db->query(
    "SELECT m.id AS member_record_id, m.first_name, m.last_name,
            ms.end_date, ms.status
     FROM memberships ms
     JOIN members m ON ms.member_id = m.id
     WHERE ms.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
       AND ms.status = 'active'
     ORDER BY ms.end_date ASC
     LIMIT 10"
);
$upcomingExpirations = $upcomingStmt->fetchAll();

// ── Recent Transactions ───────────────────────────────────────────────────────
$recentPayments = $db->query(
    "SELECT p.transaction_id, p.amount, p.payment_method, p.payment_date,
            COALESCE(p.visitor_name, CONCAT(m.first_name, ' ', m.last_name)) AS payer_name
     FROM payments p
     LEFT JOIN members m ON p.member_id = m.id
     ORDER BY p.created_at DESC
     LIMIT 5"
)->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Page Header -->
<div class="page-header dashboard-page-header">
    <div class="page-header-left">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= sanitize(ADMIN_DISPLAY_NAME) ?>. Manage your gym records here.</p>
    </div>
</div>

<div class="kpi-grid dashboard-record-grid" aria-label="Current record counts">
    <a class="kpi-card" href="<?= BASE_URL ?>/pages/members/index.php">
        <div class="kpi-icon"><i data-lucide="users"></i></div>
        <div class="kpi-body">
            <div class="kpi-value"><?= number_format($activeMembers) ?></div>
            <div class="kpi-label">Active Members</div>
        </div>
    </a>
    <a class="kpi-card" href="<?= BASE_URL ?>/pages/memberships/index.php">
        <div class="kpi-icon"><i data-lucide="credit-card"></i></div>
        <div class="kpi-body">
            <div class="kpi-value"><?= number_format($activeMemberships) ?></div>
            <div class="kpi-label">Current Memberships</div>
        </div>
    </a>
    <a class="kpi-card" href="<?= BASE_URL ?>/pages/attendance/index.php">
        <div class="kpi-icon"><i data-lucide="calendar-check"></i></div>
        <div class="kpi-body">
            <div class="kpi-value"><?= number_format($todayCheckIns) ?></div>
            <div class="kpi-label">Today's Check-ins</div>
        </div>
    </a>
    <a class="kpi-card" href="<?= BASE_URL ?>/pages/memberships/index.php">
        <div class="kpi-icon"><i data-lucide="calendar-clock"></i></div>
        <div class="kpi-body">
            <div class="kpi-value"><?= number_format($expiringSoon) ?></div>
            <div class="kpi-label">Expiring in 7 Days</div>
        </div>
    </a>
</div>

<!-- Quick action dock -->
<section class="dashboard-shortcuts" aria-labelledby="dashboardShortcutsTitle">
    <div class="dashboard-shortcuts-heading">
        <div>
            <span class="dashboard-shortcuts-eyebrow">QUICK ACTIONS</span>
            <h2 id="dashboardShortcutsTitle">Shortcuts</h2>
        </div>
        <span>Common tasks, one click away</span>
    </div>
    <nav class="dashboard-shortcuts-dock" aria-label="Dashboard shortcuts">
        <a class="dashboard-shortcut" href="<?= BASE_URL ?>/pages/payments/record.php">
            <span class="dashboard-shortcut-icon shortcut-orange"><i data-lucide="receipt"></i></span>
            <span class="dashboard-shortcut-copy"><strong>Record Payment</strong><small>Membership or walk-in</small></span>
            <i class="dashboard-shortcut-arrow" data-lucide="arrow-up-right"></i>
        </a>
        <a class="dashboard-shortcut dashboard-shortcut-walkin" href="<?= BASE_URL ?>/pages/payments/record.php?type=walk_in_pass">
            <span class="dashboard-shortcut-icon shortcut-green"><i data-lucide="user-round"></i></span>
            <span class="dashboard-shortcut-copy"><strong>Walk-in Pass</strong><small>Enter price at checkout</small></span>
            <i class="dashboard-shortcut-arrow" data-lucide="arrow-up-right"></i>
        </a>
        <a class="dashboard-shortcut" href="<?= BASE_URL ?>/pages/members/create.php">
            <span class="dashboard-shortcut-icon shortcut-violet"><i data-lucide="user-plus"></i></span>
            <span class="dashboard-shortcut-copy"><strong>Add Member</strong><small>Create a member profile</small></span>
            <i class="dashboard-shortcut-arrow" data-lucide="arrow-up-right"></i>
        </a>
    </nav>
</section>

<!-- Upcoming Expirations -->
<div class="card mb-3">
    <div class="card-header">
        <span class="card-title">Upcoming Expirations (Next 7 Days)</span>
        <a href="<?= BASE_URL ?>/pages/memberships/index.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <?php if (empty($upcomingExpirations)): ?>
        <div class="empty-state">
            <i data-lucide="calendar-x"></i>
            <h3>No upcoming expirations</h3>
            <p>All memberships are good for the next 7 days.</p>
        </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Expiry Date</th>
                    <th>Days Left</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcomingExpirations as $exp): ?>
                <tr>
                    <td><?= sanitize($exp['first_name'] . ' ' . $exp['last_name']) ?></td>
                    <td><?= formatDate($exp['end_date']) ?></td>
                    <td>
                        <?php $days = daysRemaining($exp['end_date']); ?>
                        <span class="badge <?= $days <= 2 ? 'badge-danger' : 'badge-warning' ?>">
                            <?= $days ?> day<?= $days !== 1 ? 's' : '' ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/pages/memberships/renew.php?member_id=<?= (int)$exp['member_record_id'] ?>"
                           class="btn btn-primary btn-sm">Renew</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Payments -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Transactions</span>
        <a href="<?= BASE_URL ?>/pages/payments/index.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <?php if (empty($recentPayments)): ?>
        <p class="text-muted" style="font-size:13px;">No transactions yet.</p>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Member</th><th>Amount</th><th>Method</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentPayments as $pmt): ?>
                <tr>
                    <td><?= sanitize($pmt['payer_name']) ?></td>
                    <td><?= formatPhilippineCurrency($pmt['amount']) ?></td>
                    <td><span class="badge badge-info"><?= sanitize($pmt['payment_method']) ?></span></td>
                    <td><?= formatDate($pmt['payment_date']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
