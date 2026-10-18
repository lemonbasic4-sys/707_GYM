<?php
/**
 * 707 GYM - Membership Analytics
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = Database::getConnection();
$pageTitle = 'Membership Analytics';

// Total counts
$countSql = "SELECT 
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
    COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_count,
    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_count,
    COUNT(*) as total_count
    FROM memberships";
$stmt = $pdo->query($countSql);
$counts = $stmt->fetch();

// Inactive/Expired members list (Members with expired memberships who haven't renewed)
$inactiveSql = "SELECT m.id AS member_record_id, m.first_name, m.last_name, m.phone,
    MAX(ms.end_date) as last_expiry
    FROM members m
    LEFT JOIN memberships ms ON m.id = ms.member_id
    WHERE m.status = 'active'
    GROUP BY m.id
    HAVING last_expiry < CURRENT_DATE() OR last_expiry IS NULL
    ORDER BY last_expiry DESC LIMIT 15";
$inactiveMembers = $pdo->query($inactiveSql)->fetchAll();

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Membership Analytics</h1>
        <p>Retention, churn tracking, status breakdown, and inactive member outreach</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/analytics/index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left"></i> Back to Overview
        </a>
    </div>

    <div class="stats-grid mb-4">
        <div class="kpi-card">
            <div class="kpi-icon" style="color:#10b981; background:rgba(16,185,129,0.1);"><i data-lucide="user-check"></i></div>
            <div class="kpi-details">
                <span class="kpi-label">Active Memberships</span>
                <span class="kpi-value"><?= number_format($counts['active_count'] ?? 0) ?></span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color:#ef4444; background:rgba(239,68,68,0.1);"><i data-lucide="user-x"></i></div>
            <div class="kpi-details">
                <span class="kpi-label">Expired Memberships</span>
                <span class="kpi-value"><?= number_format($counts['expired_count'] ?? 0) ?></span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color:#f59e0b; background:rgba(245,158,11,0.1);"><i data-lucide="user-minus"></i></div>
            <div class="kpi-details">
                <span class="kpi-label">Inactive Memberships</span>
                <span class="kpi-value"><?= number_format($counts['inactive_count'] ?? 0) ?></span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color:var(--accent); background:rgba(255,77,0,0.1);"><i data-lucide="percent"></i></div>
            <div class="kpi-details">
                <span class="kpi-label">Retention Rate</span>
                <span class="kpi-value">
                    <?= ($counts['total_count'] ?? 0) > 0 ? round((($counts['active_count'] ?? 0) / $counts['total_count']) * 100, 1) . '%' : '0%' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="charts-grid mb-4">
        <div class="card">
            <h3 class="card-title mb-3">Membership Growth (Monthly New Signups)</h3>
            <div class="chart-container" style="position: relative; height:280px;">
                <canvas id="growthChart"></canvas>
            </div>
        </div>
        <div class="card">
            <h3 class="card-title mb-3">Active vs Expired vs Inactive</h3>
            <div class="chart-container" style="position: relative; height:280px;">
                <canvas id="ratioChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header flex-between mb-3">
            <div>
                <h3 class="card-title">Members Needing Renewal / Inactive</h3>
                <p class="text-muted text-sm">Members with expired plans ready for follow-up or reactivation</p>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Last Expiry</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inactiveMembers)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No inactive members found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($inactiveMembers as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></td>
                                <td><?= htmlspecialchars($m['phone'] ?? '-') ?></td>
                                <td><span class="badge badge-danger"><?= $m['last_expiry'] ? formatDate($m['last_expiry']) : 'Never Active' ?></span></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/pages/memberships/activate.php?member_id=<?= (int)$m['member_record_id'] ?>" class="btn btn-sm btn-primary">
                                        Renew
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('<?= BASE_URL ?>/api/analytics.php?action=membership_growth')
        .then(res => res.json())
        .then(res => {
            if (res.success && window.createLineChart) {
                createLineChart('growthChart', res.data.labels, res.data.datasets);
            }
        });

    if (window.createDoughnutChart) {
        createDoughnutChart('ratioChart', ['Active', 'Expired', 'Inactive'], [
            <?= (int)($counts['active_count'] ?? 0) ?>,
            <?= (int)($counts['expired_count'] ?? 0) ?>,
            <?= (int)($counts['inactive_count'] ?? 0) ?>
        ]);
    }
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
