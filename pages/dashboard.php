<?php
/**
 * 707 GYM – Dashboard
 * Main overview page with KPIs, charts, upcoming expirations, and recent activity.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$db = Database::getConnection();

// ── KPI Queries ──────────────────────────────────────────────────────────────
$totalMembers = (int) $db->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();

$activeMs     = (int) $db->query("SELECT COUNT(*) FROM memberships WHERE status = 'active'")->fetchColumn();
$expiredMs    = (int) $db->query("SELECT COUNT(*) FROM memberships WHERE status = 'expired'")->fetchColumn();

$todayAttendance = (int) $db->query(
    "SELECT COUNT(*) FROM attendance WHERE date = CURDATE()"
)->fetchColumn();

$monthRevenue = (float) $db->query(
    "SELECT COALESCE(SUM(amount),0) FROM payments
     WHERE MONTH(payment_date) = MONTH(NOW()) AND YEAR(payment_date) = YEAR(NOW())
       AND status = 'paid'"
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
            m.first_name, m.last_name,
            pl.name AS plan_name
     FROM payments p
     JOIN members m  ON p.member_id = m.id
     JOIN membership_plans pl ON p.membership_id = (
         SELECT id FROM memberships WHERE member_id = p.member_id LIMIT 1
     )
     ORDER BY p.created_at DESC
     LIMIT 5"
)->fetchAll();

// ── Monthly Revenue Chart Data (last 12 months) ───────────────────────────────
$revenueRows = $db->query(
    "SELECT DATE_FORMAT(payment_date,'%b %Y') AS month_label,
            YEAR(payment_date) AS yr,
            MONTH(payment_date) AS mo,
            COALESCE(SUM(amount),0) AS total
     FROM payments
     WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
       AND status = 'paid'
     GROUP BY yr, mo
     ORDER BY yr ASC, mo ASC"
)->fetchAll();

$revenueLabels  = array_column($revenueRows, 'month_label');
$revenueData    = array_column($revenueRows, 'total');

// ── Membership Status Doughnut Data ──────────────────────────────────────────
$msStatus = $db->query(
    "SELECT status, COUNT(*) AS cnt FROM memberships GROUP BY status"
)->fetchAll();
$donutLabels = array_column($msStatus, 'status');
$donutData   = array_column($msStatus, 'cnt');

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= sanitize($_SESSION['admin_name']) ?>. Here is what's happening today.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/members/create.php" class="btn btn-primary">
            <i data-lucide="user-plus"></i> Add Member
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon"><i data-lucide="users"></i></div>
        <div class="kpi-body">
            <div class="kpi-value"><?= number_format($totalMembers) ?></div>
            <div class="kpi-label">Total Active Members</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon"><i data-lucide="credit-card"></i></div>
        <div class="kpi-body">
            <div class="kpi-value"><?= number_format($activeMs) ?></div>
            <div class="kpi-label">Active Memberships</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon"><i data-lucide="calendar-check"></i></div>
        <div class="kpi-body">
            <div class="kpi-value"><?= number_format($todayAttendance) ?></div>
            <div class="kpi-label">Today's Check-ins</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon"><i data-lucide="dollar-sign"></i></div>
        <div class="kpi-body">
            <div class="kpi-value"><?= formatPhilippineCurrency($monthRevenue) ?></div>
            <div class="kpi-label">Monthly Revenue</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="charts-grid">
    <!-- Revenue Line Chart -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Revenue – Last 12 Months</span>
        </div>
        <div style="height:260px; position:relative;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Membership Status Doughnut -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Membership Status</span>
        </div>
        <div style="height:260px; position:relative;">
            <canvas id="membershipDonut"></canvas>
        </div>
    </div>
</div>

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
                    <td><?= sanitize($pmt['first_name'] . ' ' . $pmt['last_name']) ?></td>
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

<!-- Chart init -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Revenue line chart
    createLineChart(
        'revenueChart',
        <?= json_encode($revenueLabels) ?>,
        [{
            label: 'Revenue (PHP)',
            data: <?= json_encode($revenueData) ?>,
        }]
    );

    // Membership doughnut
    createDoughnutChart(
        'membershipDonut',
        <?= json_encode(array_map('ucfirst', $donutLabels)) ?>,
        <?= json_encode(array_map('intval', $donutData)) ?>
    );
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
