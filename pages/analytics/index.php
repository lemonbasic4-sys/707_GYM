<?php
/**
 * 707 GYM - Analytics Overview
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = Database::getConnection();
$pageTitle = 'Analytics Overview';

// Total Revenue This Month
$revSql = "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid' AND MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())";
$stmtRev = $pdo->query($revSql);
$monthRevenue = $stmtRev->fetchColumn();

// New Members This Month
$memSql = "SELECT COUNT(*) FROM members WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
$stmtMem = $pdo->query($memSql);
$newMembers = $stmtMem->fetchColumn();

// Today Attendance
$attSql = "SELECT COUNT(*) FROM attendance WHERE date = CURRENT_DATE()";
$stmtAtt = $pdo->query($attSql);
$todayAttendance = $stmtAtt->fetchColumn();

// Top Plan
$planSql = "SELECT p.name, COUNT(m.id) as cnt FROM memberships m JOIN membership_plans p ON m.plan_id = p.id";
$planSql .= " GROUP BY p.id ORDER BY cnt DESC LIMIT 1";
$stmtPlan = $pdo->query($planSql);
$topPlan = $stmtPlan->fetch() ?: ['name' => 'None', 'cnt' => 0];

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Analytics &amp; Reports</h1>
        <p>Track operational performance, revenue trends, and member insights</p>
    </div>
    <div class="page-header-actions">
            <a href="<?= BASE_URL ?>/exports/pdf.php?type=analytics" class="btn btn-secondary">
                <i data-lucide="file-text"></i> Export PDF
            </a>
            <a href="<?= BASE_URL ?>/exports/excel.php?type=analytics" class="btn btn-secondary">
                <i data-lucide="file-spreadsheet"></i> Export Excel
            </a>
</div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid mb-4">
        <div class="kpi-card">
            <div class="kpi-icon" style="color: var(--accent); background: rgba(255,77,0,0.1);">
                <i data-lucide="dollar-sign"></i>
            </div>
            <div class="kpi-details">
                <span class="kpi-label">This Month Revenue</span>
                <span class="kpi-value"><?= formatPhilippineCurrency($monthRevenue) ?></span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color: #3b82f6; background: rgba(59,130,246,0.1);">
                <i data-lucide="user-plus"></i>
            </div>
            <div class="kpi-details">
                <span class="kpi-label">New Members (Month)</span>
                <span class="kpi-value"><?= number_format($newMembers) ?></span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color: #10b981; background: rgba(16,185,129,0.1);">
                <i data-lucide="calendar-check"></i>
            </div>
            <div class="kpi-details">
                <span class="kpi-label">Today Attendance</span>
                <span class="kpi-value"><?= number_format($todayAttendance) ?></span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color: #a855f7; background: rgba(168,85,247,0.1);">
                <i data-lucide="award"></i>
            </div>
            <div class="kpi-details">
                <span class="kpi-label">Most Popular Plan</span>
                <span class="kpi-value" style="font-size: 1.15rem;"><?= htmlspecialchars($topPlan['name']) ?></span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid mb-4">
        <div class="card">
            <div class="card-header flex-between mb-3">
                <h3 class="card-title">Revenue Trend (Last 12 Months)</h3>
                <a href="<?= BASE_URL ?>/pages/analytics/revenue.php" class="text-accent text-sm">Detailed Breakdown &rarr;</a>
            </div>
            <div class="chart-container" style="position: relative; height:280px;">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header flex-between mb-3">
                <h3 class="card-title">Membership Status Distribution</h3>
                <a href="<?= BASE_URL ?>/pages/analytics/membership.php" class="text-accent text-sm">Detailed Breakdown &rarr;</a>
            </div>
            <div class="chart-container" style="position: relative; height:280px;">
                <canvas id="membershipStatusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="charts-grid mb-4">
        <div class="card">
            <div class="card-header flex-between mb-3">
                <h3 class="card-title">Daily Attendance Trends</h3>
                <a href="<?= BASE_URL ?>/pages/analytics/attendance.php" class="text-accent text-sm">Detailed Breakdown &rarr;</a>
            </div>
            <div class="chart-container" style="position: relative; height:280px;">
                <canvas id="attendanceTrendChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header flex-between mb-3">
                <h3 class="card-title">Member Growth</h3>
            </div>
            <div class="chart-container" style="position: relative; height:280px;">
                <canvas id="membershipGrowthChart"></canvas>
            </div>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fetch Revenue Trend
    fetch('<?= BASE_URL ?>/api/analytics.php?action=revenue_trend')
        .then(res => res.json())
        .then(res => {
            if (res.success && window.createLineChart) {
                createLineChart('revenueTrendChart', res.data.labels, res.data.datasets);
            }
        });

    // Fetch Plan / Status Distribution
    fetch('<?= BASE_URL ?>/api/analytics.php?action=plan_distribution')
        .then(res => res.json())
        .then(res => {
            if (res.success && window.createDoughnutChart) {
                createDoughnutChart('membershipStatusChart', res.data.labels, res.data.data);
            }
        });

    // Fetch Attendance Trend
    fetch('<?= BASE_URL ?>/api/analytics.php?action=attendance_trend')
        .then(res => res.json())
        .then(res => {
            if (res.success && window.createLineChart) {
                createLineChart('attendanceTrendChart', res.data.labels, res.data.datasets);
            }
        });

    fetch('<?= BASE_URL ?>/api/analytics.php?action=membership_growth')
        .then(res => res.json())
        .then(res => {
            if (res.success && window.createLineChart) {
                createLineChart('membershipGrowthChart', res.data.labels, res.data.datasets);
            }
        });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
