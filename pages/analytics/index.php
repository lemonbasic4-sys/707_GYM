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

<div class="analytics-overview">
    <header class="analytics-heading">
        <div class="analytics-heading-copy">
            <span class="analytics-eyebrow"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i> INSIGHTS &amp; REPORTS</span>
            <h1>Analytics overview</h1>
            <p>A clear view of your gym’s revenue, members, and daily activity.</p>
        </div>
        <nav class="analytics-actions" aria-label="Analytics actions">
            <a href="<?= BASE_URL ?>/pages/analytics/branches.php" class="btn btn-primary">
                <i data-lucide="git-compare-arrows" aria-hidden="true"></i> Compare branches
            </a>
            <a href="<?= BASE_URL ?>/exports/pdf.php?type=analytics" class="btn btn-secondary">
                <i data-lucide="file-text" aria-hidden="true"></i> Export report
            </a>
        </nav>
    </header>

    <section class="analytics-period" aria-label="Current reporting period">
        <div class="analytics-period-icon"><i data-lucide="calendar-days" aria-hidden="true"></i></div>
        <div>
            <span class="analytics-period-label">REPORTING PERIOD</span>
            <strong><?= sanitize(date('F Y')) ?></strong>
        </div>
        <span class="analytics-period-note">Live data from this gym</span>
    </section>

    <section class="analytics-kpis" aria-label="Key performance indicators">
        <article class="analytics-kpi analytics-kpi-revenue">
            <div class="analytics-kpi-top">
                <span class="analytics-kpi-icon"><i data-lucide="wallet" aria-hidden="true"></i></span>
                <span class="analytics-kpi-period">THIS MONTH</span>
            </div>
            <span class="analytics-kpi-label">Revenue collected</span>
            <strong class="analytics-kpi-value"><?= formatPhilippineCurrency($monthRevenue) ?></strong>
            <span class="analytics-kpi-note">Paid transactions</span>
        </article>

        <article class="analytics-kpi analytics-kpi-members">
            <div class="analytics-kpi-top">
                <span class="analytics-kpi-icon"><i data-lucide="user-plus" aria-hidden="true"></i></span>
                <span class="analytics-kpi-period">THIS MONTH</span>
            </div>
            <span class="analytics-kpi-label">New members</span>
            <strong class="analytics-kpi-value"><?= number_format((int) $newMembers) ?></strong>
            <span class="analytics-kpi-note">Recently joined</span>
        </article>

        <article class="analytics-kpi analytics-kpi-attendance">
            <div class="analytics-kpi-top">
                <span class="analytics-kpi-icon"><i data-lucide="calendar-check" aria-hidden="true"></i></span>
                <span class="analytics-kpi-period">TODAY</span>
            </div>
            <span class="analytics-kpi-label">Gym visits</span>
            <strong class="analytics-kpi-value"><?= number_format((int) $todayAttendance) ?></strong>
            <span class="analytics-kpi-note">Check-ins recorded</span>
        </article>

        <article class="analytics-kpi analytics-kpi-plan">
            <div class="analytics-kpi-top">
                <span class="analytics-kpi-icon"><i data-lucide="award" aria-hidden="true"></i></span>
                <span class="analytics-kpi-period">TOP PLAN</span>
            </div>
            <span class="analytics-kpi-label">Most popular</span>
            <strong class="analytics-kpi-value analytics-kpi-plan-name"><?= sanitize($topPlan['name']) ?></strong>
            <span class="analytics-kpi-note"><?= number_format((int) $topPlan['cnt']) ?> membership records</span>
        </article>
    </section>

    <section class="analytics-section-heading">
        <div>
            <span class="analytics-eyebrow">PERFORMANCE</span>
            <h2>Trends at a glance</h2>
        </div>
        <span class="analytics-section-caption">Updated from your latest records</span>
    </section>

    <section class="analytics-chart-grid analytics-chart-grid-primary" aria-label="Revenue and plan analytics">
        <article class="card analytics-chart-card analytics-chart-card-revenue">
            <div class="analytics-chart-heading">
                <span class="analytics-chart-icon analytics-chart-icon-orange"><i data-lucide="trending-up" aria-hidden="true"></i></span>
                <div>
                    <h3>Revenue trend</h3>
                    <p>Paid collections over the last 12 months</p>
                </div>
                <a href="<?= BASE_URL ?>/pages/analytics/revenue.php" class="analytics-chart-link" aria-label="View revenue breakdown"><i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            </div>
            <div class="analytics-chart-canvas"><canvas id="revenueTrendChart" aria-label="Revenue trend for the last 12 months" role="img"></canvas></div>
        </article>

        <article class="card analytics-chart-card">
            <div class="analytics-chart-heading">
                <span class="analytics-chart-icon analytics-chart-icon-violet"><i data-lucide="pie-chart" aria-hidden="true"></i></span>
                <div>
                    <h3>Membership plan mix</h3>
                    <p>Membership records by plan</p>
                </div>
                <a href="<?= BASE_URL ?>/pages/analytics/membership.php" class="analytics-chart-link" aria-label="View membership breakdown"><i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            </div>
            <div class="analytics-chart-canvas"><canvas id="membershipStatusChart" aria-label="Membership records by plan" role="img"></canvas></div>
        </article>
    </section>

    <section class="analytics-chart-grid" aria-label="Attendance and member growth analytics">
        <article class="card analytics-chart-card">
            <div class="analytics-chart-heading">
                <span class="analytics-chart-icon analytics-chart-icon-blue"><i data-lucide="activity" aria-hidden="true"></i></span>
                <div>
                    <h3>Daily attendance</h3>
                    <p>Gym visits over the last 14 days</p>
                </div>
                <a href="<?= BASE_URL ?>/pages/analytics/attendance.php" class="analytics-chart-link" aria-label="View attendance breakdown"><i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            </div>
            <div class="analytics-chart-canvas"><canvas id="attendanceTrendChart" aria-label="Daily attendance over the last 14 days" role="img"></canvas></div>
        </article>

        <article class="card analytics-chart-card">
            <div class="analytics-chart-heading">
                <span class="analytics-chart-icon analytics-chart-icon-green"><i data-lucide="users" aria-hidden="true"></i></span>
                <div>
                    <h3>Member growth</h3>
                    <p>New members over the last six months</p>
                </div>
            </div>
            <div class="analytics-chart-canvas"><canvas id="membershipGrowthChart" aria-label="New member growth over the last six months" role="img"></canvas></div>
        </article>
    </section>
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
