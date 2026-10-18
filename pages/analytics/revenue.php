<?php
/**
 * 707 GYM - Revenue Analytics
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = Database::getConnection();
$pageTitle = 'Revenue Analytics';

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Total revenue this year
$stmtYear = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid' AND YEAR(payment_date) = :yr");
$stmtYear->execute([':yr' => $year]);
$yearRevenue = $stmtYear->fetchColumn();

// Total revenue all time
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'")->fetchColumn();

// Payment method breakdown
$methodsSql = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total_amount 
               FROM payments WHERE status = 'paid' AND YEAR(payment_date) = :yr 
               GROUP BY payment_method ORDER BY total_amount DESC";
$stmtMethods = $pdo->prepare($methodsSql);
$stmtMethods->execute([':yr' => $year]);
$methodBreakdown = $stmtMethods->fetchAll();

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Revenue Analytics</h1>
        <p>Financial performance and payment method totals</p>
    </div>
    <div class="page-header-actions">
        <form method="GET" class="flex-center gap-2">
            <select name="year" class="form-control" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <a href="<?= BASE_URL ?>/pages/analytics/index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left"></i> Back
        </a>
    </div>

    <div class="stats-grid mb-4">
        <div class="kpi-card">
            <div class="kpi-icon" style="color:var(--accent); background:rgba(255,77,0,0.1);"><i data-lucide="wallet"></i></div>
            <div class="kpi-details">
                <span class="kpi-label">Revenue (<?= $year ?>)</span>
                <span class="kpi-value"><?= formatPhilippineCurrency($yearRevenue) ?></span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color:#10b981; background:rgba(16,185,129,0.1);"><i data-lucide="trending-up"></i></div>
            <div class="kpi-details">
                <span class="kpi-label">Lifetime Collections</span>
                <span class="kpi-value"><?= formatPhilippineCurrency($totalRevenue) ?></span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="color:#3b82f6; background:rgba(59,130,246,0.1);"><i data-lucide="credit-card"></i></div>
            <div class="kpi-details">
                <span class="kpi-label">Top Payment Channel</span>
                <span class="kpi-value" style="font-size:1.2rem;"><?= !empty($methodBreakdown) ? ucfirst($methodBreakdown[0]['payment_method']) : 'None' ?></span>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <h3 class="card-title mb-3">Monthly Revenue Curve (<?= $year ?>)</h3>
        <div class="chart-container" style="position: relative; height:320px;">
            <canvas id="monthlyRevChart"></canvas>
        </div>
    </div>

    <div class="charts-grid mb-4">
        <div class="card">
            <h3 class="card-title mb-3">Collections by Payment Method</h3>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Transactions</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($methodBreakdown)): ?>
                            <tr><td colspan="3" class="text-center text-muted">No transactions for <?= $year ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($methodBreakdown as $m): ?>
                                <tr>
                                    <td><strong><?= ucfirst(htmlspecialchars($m['payment_method'])) ?></strong></td>
                                    <td><?= number_format($m['count']) ?></td>
                                    <td><?= formatPhilippineCurrency($m['total_amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('<?= BASE_URL ?>/api/analytics.php?action=revenue_trend')
        .then(res => res.json())
        .then(res => {
            if (res.success && window.createLineChart) {
                createLineChart('monthlyRevChart', res.data.labels, res.data.datasets);
            }
        });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
