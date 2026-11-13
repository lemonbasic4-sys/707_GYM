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

$activeMembers = (int) $db->query("SELECT COUNT(*) FROM members m WHERE m.status = 'active' AND EXISTS (
    SELECT 1 FROM memberships ms WHERE ms.member_id = m.id AND ms.status = 'active'
    AND ms.start_date <= CURDATE() AND ms.end_date >= CURDATE())")->fetchColumn();
$activeMemberships = (int) $db->query("SELECT COUNT(*) FROM memberships WHERE status = 'active' AND end_date >= CURDATE()")->fetchColumn();
$todayCheckIns = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()")->fetchColumn();
$todayWalkIns = (int) $db->query(
    "SELECT COUNT(*) FROM payments
     WHERE transaction_type = 'walk_in_pass'
       AND (payment_date = CURDATE() OR DATE(visitor_check_in) = CURDATE())"
)->fetchColumn();

$topMembers = $db->query(
    "SELECT m.id, m.first_name, m.last_name, COUNT(a.id) AS visit_count
     FROM attendance a JOIN members m ON m.id = a.member_id
     GROUP BY m.id, m.first_name, m.last_name
     ORDER BY visit_count DESC, m.first_name, m.last_name
     LIMIT 5"
)->fetchAll();

$insideNow = $db->query(
    "SELECT CONCAT(m.first_name, ' ', m.last_name) AS person_name,
            'Member' AS person_type, a.check_in AS arrived_at
     FROM attendance a JOIN members m ON m.id = a.member_id
     WHERE a.date = CURDATE() AND a.check_out IS NULL
     UNION ALL
     SELECT p.visitor_name AS person_name, 'Walk-in' AS person_type,
            p.visitor_check_in AS arrived_at
     FROM payments p
     WHERE p.transaction_type = 'walk_in_pass' AND p.status = 'paid'
       AND p.visitor_name IS NOT NULL AND p.visitor_check_in IS NOT NULL
       AND DATE(p.visitor_check_in) = CURDATE() AND p.visitor_check_out IS NULL
     ORDER BY arrived_at DESC"
)->fetchAll();

$hourlyVisits = array_fill(0, 24, 0);
$hourlyRows = $db->query(
    "SELECT HOUR(visit_time) AS visit_hour, COUNT(*) AS visits
     FROM (
         SELECT check_in AS visit_time FROM attendance
         WHERE check_in >= CURDATE() - INTERVAL 29 DAY AND check_in < CURDATE() + INTERVAL 1 DAY
         UNION ALL
         SELECT visitor_check_in AS visit_time FROM payments
         WHERE transaction_type = 'walk_in_pass' AND status = 'paid'
           AND visitor_check_in >= CURDATE() - INTERVAL 29 DAY
           AND visitor_check_in < CURDATE() + INTERVAL 1 DAY
     ) visits_by_time
     GROUP BY HOUR(visit_time)"
)->fetchAll();
foreach ($hourlyRows as $row) $hourlyVisits[(int)$row['visit_hour']] = (int)$row['visits'];
$totalVisits = array_sum($hourlyVisits);
$peakHour = array_search(max($hourlyVisits), $hourlyVisits, true);
$chartMax = max(1, max($hourlyVisits));
$chartPoints = [];
foreach ($hourlyVisits as $hour => $visits) {
    $chartPoints[] = round(44 + $hour * 28, 1) . ',' . round(194 - ($visits / $chartMax) * 148, 1);
}

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
);
$recentPayments = $recentPayments->fetchAll();

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
    <a class="kpi-card" href="<?= BASE_URL ?>/pages/attendance/walk-ins.php">
        <div class="kpi-icon"><i data-lucide="user-round"></i></div>
        <div class="kpi-body">
            <div class="kpi-value"><?= number_format($todayWalkIns) ?></div>
            <div class="kpi-label">Today's Walk-ins</div>
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

<div class="dashboard-presence-grid">
    <section class="card" aria-labelledby="insideNowTitle">
        <div class="card-header"><span id="insideNowTitle" class="card-title">Inside the Gym Now (<span id="insideNowCount"><?= count($insideNow) ?></span>)</span><a href="<?= BASE_URL ?>/pages/attendance/index.php" class="btn btn-ghost btn-sm">Attendance</a></div>
        <div class="inside-now-filters" role="group" aria-label="Filter people inside the gym">
            <button type="button" class="btn btn-primary btn-sm" data-inside-filter="all" aria-pressed="true">All</button>
            <button type="button" class="btn btn-ghost btn-sm" data-inside-filter="Member" aria-pressed="false">Members</button>
            <button type="button" class="btn btn-ghost btn-sm" data-inside-filter="Walk-in" aria-pressed="false">Walk-ins</button>
        </div>
        <?php if (!$insideNow): ?>
            <p class="text-muted">No one is currently checked in today.</p>
        <?php else: ?>
            <div class="table-wrapper"><table><thead><tr><th>Name</th><th>Type</th><th>Checked In</th></tr></thead><tbody>
            <?php foreach ($insideNow as $person): ?><tr data-inside-type="<?= sanitize($person['person_type']) ?>"><td><?= sanitize($person['person_name']) ?></td><td><?= sanitize($person['person_type']) ?></td><td><?= formatDate($person['arrived_at'], 'g:i A') ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <p id="insideNowFilteredEmpty" class="text-muted" hidden>No one in this category is inside right now.</p>
    </section>
    <section class="card" aria-labelledby="topMembersTitle">
        <div class="card-header"><span id="topMembersTitle" class="card-title">Top Members by Check-ins</span><a href="<?= BASE_URL ?>/pages/attendance/history.php" class="btn btn-ghost btn-sm">History</a></div>
        <?php if (!$topMembers): ?>
            <p class="text-muted">No member check-ins recorded yet.</p>
        <?php else: ?>
            <div class="table-wrapper"><table><thead><tr><th>Member</th><th>Visits</th></tr></thead><tbody>
            <?php foreach ($topMembers as $member): ?><tr><td><a href="<?= BASE_URL ?>/pages/members/view.php?id=<?= (int)$member['id'] ?>"><?= sanitize($member['first_name'] . ' ' . $member['last_name']) ?></a></td><td><?= number_format((int)$member['visit_count']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
</div>

<script>
document.querySelectorAll('[data-inside-filter]').forEach(button => {
    button.addEventListener('click', () => {
        const filter = button.dataset.insideFilter;
        let visible = 0;
        document.querySelectorAll('[data-inside-type]').forEach(row => {
            row.hidden = filter !== 'all' && row.dataset.insideType !== filter;
            if (!row.hidden) visible++;
        });
        document.getElementById('insideNowCount').textContent = visible;
        document.getElementById('insideNowFilteredEmpty').hidden = visible !== 0 || document.querySelectorAll('[data-inside-type]').length === 0;
        document.querySelectorAll('[data-inside-filter]').forEach(option => {
            const selected = option === button;
            option.classList.toggle('btn-primary', selected);
            option.classList.toggle('btn-ghost', !selected);
            option.setAttribute('aria-pressed', String(selected));
        });
    });
});
</script>

<section class="card dashboard-busy-hours mb-3" aria-labelledby="busyHoursTitle">
    <div class="card-header"><span id="busyHoursTitle" class="card-title">Busiest Gym Hours</span><a href="<?= BASE_URL ?>/pages/attendance/history.php" class="btn btn-ghost btn-sm">Attendance History</a></div>
    <p class="text-muted">Member check-ins and paid walk-in arrivals by hour, last 30 days (<?= number_format($totalVisits) ?> visits).</p>
    <?php if ($totalVisits === 0): ?>
        <div class="empty-state"><i data-lucide="chart-no-axes-combined"></i><h3>No visits recorded yet</h3><p>The graph will appear after check-ins are recorded.</p></div>
    <?php else: ?>
        <p class="dashboard-busy-peak">Peak arrival hour: <strong><?= date('g A', mktime((int)$peakHour, 0)) ?>–<?= date('g A', mktime((int)$peakHour + 1, 0)) ?></strong> (<?= $hourlyVisits[$peakHour] ?> visits)</p>
        <div class="dashboard-busy-chart">
            <svg viewBox="0 0 735 245" role="img" aria-label="Line graph of visits by arrival hour over the last 30 days">
                <line x1="44" y1="46" x2="688" y2="46" class="chart-guide" />
                <line x1="44" y1="120" x2="688" y2="120" class="chart-guide" />
                <line x1="44" y1="194" x2="688" y2="194" class="chart-guide" />
                <text x="8" y="50" class="chart-label"><?= $chartMax ?></text>
                <text x="18" y="198" class="chart-label">0</text>
                <polyline points="<?= implode(' ', $chartPoints) ?>" class="chart-line" />
                <?php foreach ($hourlyVisits as $hour => $visits): ?>
                    <circle cx="<?= 44 + $hour * 28 ?>" cy="<?= round(194 - ($visits / $chartMax) * 148, 1) ?>" r="4" class="chart-point"><title><?= date('g A', mktime($hour, 0)) ?>: <?= $visits ?> visit<?= $visits === 1 ? '' : 's' ?></title></circle>
                <?php endforeach; ?>
                <?php foreach ([0, 3, 6, 9, 12, 15, 18, 21, 23] as $hour): ?>
                    <text x="<?= 44 + $hour * 28 ?>" y="224" text-anchor="middle" class="chart-label"><?= date('g A', mktime($hour, 0)) ?></text>
                <?php endforeach; ?>
            </svg>
        </div>
    <?php endif; ?>
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
                        <a href="<?= BASE_URL ?>/pages/members/view.php?id=<?= (int)$exp['member_record_id'] ?>"
                           class="btn btn-secondary btn-sm">View Member</a>
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
