<?php
/**
 * 707 GYM - Attendance Analytics
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = Database::getConnection();
$pageTitle = 'Attendance Analytics';

// Total check-ins this month
$thisMonthSql = "SELECT COUNT(*) FROM attendance WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())";
$stmt = $pdo->query($thisMonthSql);
$monthAttendance = $stmt->fetchColumn();

// Daily average this month
$daysInMonth = (int)date('j');
$dailyAvg = $daysInMonth > 0 ? round($monthAttendance / $daysInMonth, 1) : 0;

// Busiest day of the week
$dowSql = "SELECT DAYNAME(date) as day_name, COUNT(*) as count 
           FROM attendance 
           WHERE date >= DATE_SUB(CURRENT_DATE(), INTERVAL 60 DAY) ";
$dowSql .= " GROUP BY DAYOFWEEK(date), DAYNAME(date) ORDER BY count DESC LIMIT 1";
$stmtDow = $pdo->query($dowSql);
$busiestDay = $stmtDow->fetch();

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Attendance Analytics</h1>
        <p>Peak gym hours, daily footfall averages, and busy days</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/analytics/index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left"></i> Back
        </a>
    </div>

    <div class="stats-grid mb-4">
        <div class="kpi-card">
            <div class="kpi-icon" style="color:#10b981; background:rgba(16,185,129,0.1);"><i data-lucide="check-circle-2"></i></div>
            <div class="kpi-details">
                <span class="kpi-label">Month Total Visits</span>
                <span class="kpi-value"><?= number_format($monthAttendance) ?></span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color:#3b82f6; background:rgba(59,130,246,0.1);"><i data-lucide="activity"></i></div>
            <div class="kpi-details">
                <span class="kpi-label">Daily Average</span>
                <span class="kpi-value"><?= $dailyAvg ?> / day</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color:var(--accent); background:rgba(255,77,0,0.1);"><i data-lucide="calendar"></i></div>
            <div class="kpi-details">
                <span class="kpi-label">Busiest Day</span>
                <span class="kpi-value"><?= htmlspecialchars($busiestDay['day_name'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <h3 class="card-title mb-3">Recent 30 Days Check-in Volume</h3>
        <div class="chart-container" style="position: relative; height:300px;">
            <canvas id="attChart"></canvas>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('<?= BASE_URL ?>/api/analytics.php?action=attendance_trend')
        .then(res => res.json())
        .then(res => {
            if (res.success && window.createLineChart) {
                createLineChart('attChart', res.data.labels, res.data.datasets);
            }
        });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
