<?php
/**
 * Branch comparison and consolidated analytics from monthly CSV snapshots.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/branch_analytics.php';

requireAuth();

$db = Database::getConnection();
$pageTitle = 'Branch Comparison';

function parseBranchAnalyticsCsv(array $file, string $localBranch): array
{
    if (!isset($file['error'], $file['size'], $file['name'], $file['tmp_name'])
        || !is_int($file['error']) || !is_int($file['size'])
        || !is_string($file['name']) || !is_string($file['tmp_name'])
        || $file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > MAX_FILE_SIZE) {
        throw new InvalidArgumentException('Each selected file must be a readable CSV no larger than 5 MB.');
    }
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        throw new InvalidArgumentException('Only CSV files exported from Branch Analytics can be imported.');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        throw new InvalidArgumentException('A selected CSV file could not be read.');
    }

    $headers = fgetcsv($handle, null, ',', '"', '\\');
    if (is_array($headers) && isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
    }
    $expectedHeaders = [
        'branch_name', 'period_month', 'captured_at', 'revenue',
        'new_members', 'active_members', 'attendance_visits', 'active_memberships',
    ];
    $row = fgetcsv($handle, null, ',', '"', '\\');
    $extraRow = false;
    while (($extra = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
        if (array_filter($extra, static fn($value) => trim((string) $value) !== '')) {
            $extraRow = true;
            break;
        }
    }
    fclose($handle);

    if (!is_array($headers) || array_map('strtolower', array_map('trim', $headers)) !== $expectedHeaders
        || !is_array($row) || count($row) !== count($expectedHeaders) || $extraRow) {
        throw new InvalidArgumentException('A selected file is not a valid single-branch analytics CSV.');
    }

    $snapshot = array_combine($expectedHeaders, array_map('trim', $row));
    $capturedAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $snapshot['captured_at']);
    $capturedErrors = DateTimeImmutable::getLastErrors();
    $validCapturedAt = $capturedAt !== false
        && ($capturedErrors === false || ($capturedErrors['warning_count'] === 0 && $capturedErrors['error_count'] === 0))
        && $capturedAt->format('Y-m-d H:i:s') === $snapshot['captured_at'];
    $validMetrics = preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $snapshot['revenue']) === 1;
    foreach (['new_members', 'active_members', 'attendance_visits', 'active_memberships'] as $field) {
        $count = filter_var($snapshot[$field], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 4294967295],
        ]);
        $validMetrics = $validMetrics && $count !== false;
    }

    if (!isValidBranchName($snapshot['branch_name'])
        || $snapshot['branch_name'] === $localBranch
        || !isValidAnalyticsMonth($snapshot['period_month'])
        || !$validCapturedAt
        || !$validMetrics) {
        throw new InvalidArgumentException('A selected CSV has an invalid branch, month, capture time, or metric. A branch cannot import its own snapshot.');
    }

    return $snapshot + ['source_file' => substr(basename($file['name']), 0, 255)];
}

if (!hasBranchAnalyticsSchema($db)) {
    require_once BASE_PATH . '/includes/header.php';
    require_once BASE_PATH . '/includes/sidebar.php';
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h1>Branch Comparison</h1>
            <p>Branch analytics needs a one-time database update before it can be used.</p>
        </div>
    </div>
    <div class="alert alert-warning" role="alert">
        Run <code>database/migrations/005_add_branch_analytics.sql</code> against the <code><?= sanitize(DB_NAME) ?></code> database in phpMyAdmin, then reload this page.
    </div>
    <?php
    require_once BASE_PATH . '/includes/footer.php';
    exit;
}

$month = isset($_GET['month']) && is_string($_GET['month']) ? $_GET['month'] : date('Y-m');
$view = isset($_GET['view']) && is_string($_GET['view']) ? $_GET['view'] : 'compare';
if (!isValidAnalyticsMonth($month)) {
    setFlash('error', 'Choose a valid month to compare.');
    redirect(BASE_URL . '/pages/analytics/branches.php');
}
if (!in_array($view, ['compare', 'consolidated'], true)) {
    $view = 'compare';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Your session token expired. Please try again.');
        redirect(BASE_URL . '/pages/analytics/branches.php?month=' . rawurlencode($month));
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'save_identity') {
        $branchName = isset($_POST['branch_name']) && is_string($_POST['branch_name']) ? trim($_POST['branch_name']) : '';
        if (!isValidBranchName($branchName)) {
            setFlash('error', 'Branch name must be 707_GYM_MAIN or 707_GYM_ followed by a branch number (for example, 707_GYM_1).');
        } elseif ($branchName !== getLocalBranchName($db)) {
            $existing = $db->prepare("SELECT COUNT(*) FROM branch_analytics_snapshots WHERE branch_name = :branch_name");
            $existing->execute([':branch_name' => $branchName]);
            if ((int) $existing->fetchColumn() > 0) {
                setFlash('error', 'That branch name already has imported snapshots. Choose another branch name to avoid mixing branch data.');
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO branch_analytics_settings (id, branch_name) VALUES (1, :branch_name)
                     ON DUPLICATE KEY UPDATE branch_name = VALUES(branch_name)"
                );
                $stmt->execute([':branch_name' => $branchName]);
                logActivity((int) $_SESSION['admin_id'], 'branch.identity.update', "Branch: {$branchName}");
                setFlash('success', 'This system is now identified as ' . $branchName . '.');
            }
        } else {
            $stmt = $db->prepare(
                "INSERT INTO branch_analytics_settings (id, branch_name) VALUES (1, :branch_name)
                 ON DUPLICATE KEY UPDATE branch_name = VALUES(branch_name)"
            );
            $stmt->execute([':branch_name' => $branchName]);
            logActivity((int) $_SESSION['admin_id'], 'branch.identity.update', "Branch: {$branchName}");
            setFlash('success', 'This system is now identified as ' . $branchName . '.');
        }
        rotateCsrfToken();
        redirect(BASE_URL . '/pages/analytics/branches.php?month=' . rawurlencode($month));
    }

    if ($action === 'import_snapshot') {
        try {
            $uploads = $_FILES['csv_files'] ?? null;
            if (!is_array($uploads) || !isset($uploads['name'], $uploads['type'], $uploads['tmp_name'], $uploads['error'], $uploads['size'])
                || !is_array($uploads['name']) || !is_array($uploads['type'])
                || !is_array($uploads['tmp_name']) || !is_array($uploads['error'])
                || !is_array($uploads['size']) || count($uploads['name']) === 0) {
                throw new InvalidArgumentException('Choose one or more branch CSV files to import.');
            }

            $localBranch = getLocalBranchName($db);
            $snapshots = [];
            $seen = [];
            foreach (array_keys($uploads['name']) as $index) {
                $snapshot = parseBranchAnalyticsCsv([
                    'name' => $uploads['name'][$index] ?? null,
                    'type' => $uploads['type'][$index] ?? null,
                    'tmp_name' => $uploads['tmp_name'][$index] ?? null,
                    'error' => $uploads['error'][$index] ?? null,
                    'size' => $uploads['size'][$index] ?? null,
                ], $localBranch);
                if ($snapshot['period_month'] !== $month) {
                    throw new InvalidArgumentException('All selected CSV files must match the reporting month shown above.');
                }
                $snapshotKey = $snapshot['branch_name'] . '|' . $snapshot['period_month'];
                if (isset($seen[$snapshotKey])) {
                    throw new InvalidArgumentException('Only one CSV per branch is allowed in a single import.');
                }
                $seen[$snapshotKey] = true;
                $snapshots[] = $snapshot;
            }

            $db->beginTransaction();
            $stmt = $db->prepare(
                "INSERT INTO branch_analytics_snapshots
                    (branch_name, period_month, captured_at, revenue, new_members, active_members,
                     attendance_visits, active_memberships, source_file, imported_by)
                 VALUES
                    (:branch_name, :period_month, :captured_at, :revenue, :new_members, :active_members,
                     :attendance_visits, :active_memberships, :source_file, :imported_by)
                 ON DUPLICATE KEY UPDATE
                    captured_at = VALUES(captured_at), revenue = VALUES(revenue),
                    new_members = VALUES(new_members), active_members = VALUES(active_members),
                    attendance_visits = VALUES(attendance_visits),
                    active_memberships = VALUES(active_memberships),
                    source_file = VALUES(source_file), imported_by = VALUES(imported_by),
                    imported_at = CURRENT_TIMESTAMP"
            );
            foreach ($snapshots as $snapshot) {
                $stmt->execute([
                    ':branch_name' => $snapshot['branch_name'],
                    ':period_month' => $snapshot['period_month'],
                    ':captured_at' => $snapshot['captured_at'],
                    ':revenue' => $snapshot['revenue'],
                    ':new_members' => (int) $snapshot['new_members'],
                    ':active_members' => (int) $snapshot['active_members'],
                    ':attendance_visits' => (int) $snapshot['attendance_visits'],
                    ':active_memberships' => (int) $snapshot['active_memberships'],
                    ':source_file' => $snapshot['source_file'],
                    ':imported_by' => (int) $_SESSION['admin_id'],
                ]);
            }
            $branchNames = array_column($snapshots, 'branch_name');
            logActivity(
                (int) $_SESSION['admin_id'],
                'branch.analytics.import',
                'Branches: ' . implode(', ', $branchNames) . '; month: ' . $month
            );
            $db->commit();

            rotateCsrfToken();
            setFlash('success', count($snapshots) . ' branch CSV file(s) imported for ' . $month . '.');
        } catch (InvalidArgumentException $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            setFlash('error', $error->getMessage());
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Branch analytics import failed: ' . $error->getMessage());
            setFlash('error', 'The selected branch CSV files could not be imported. No snapshots were changed.');
        }
        redirect(BASE_URL . '/pages/analytics/branches.php?month=' . rawurlencode($month));
    }

    setFlash('error', 'Invalid branch analytics action.');
    redirect(BASE_URL . '/pages/analytics/branches.php?month=' . rawurlencode($month));
}

$localBranch = getLocalBranchName($db);
$branchRows = [[
    'branch_name' => $localBranch,
    'captured_at' => date('Y-m-d H:i:s'),
    'source_file' => 'Live local data',
] + getCurrentBranchAnalytics($db, $month)];

$stmt = $db->prepare(
    "SELECT branch_name, captured_at, revenue, new_members, active_members,
            attendance_visits, active_memberships, source_file
     FROM branch_analytics_snapshots
     WHERE period_month = :period_month AND branch_name <> :local_branch
     ORDER BY CAST(SUBSTRING(branch_name, 9) AS UNSIGNED)"
);
$stmt->execute([':period_month' => $month, ':local_branch' => $localBranch]);
foreach ($stmt->fetchAll() as $snapshot) {
    $branchRows[] = [
        'branch_name' => $snapshot['branch_name'],
        'captured_at' => $snapshot['captured_at'],
        'source_file' => $snapshot['source_file'],
        'revenue' => (float) $snapshot['revenue'],
        'new_members' => (int) $snapshot['new_members'],
        'active_members' => (int) $snapshot['active_members'],
        'attendance_visits' => (int) $snapshot['attendance_visits'],
        'active_memberships' => (int) $snapshot['active_memberships'],
    ];
}

$totals = [
    'revenue' => 0.0,
    'new_members' => 0,
    'active_members' => 0,
    'attendance_visits' => 0,
    'active_memberships' => 0,
];
foreach ($branchRows as $branchRow) {
    foreach ($totals as $metric => $_) {
        $totals[$metric] += $branchRow[$metric];
    }
}
$mostPopular = $branchRows[0];
$highestRevenue = $branchRows[0];
foreach ($branchRows as $branchRow) {
    if ($branchRow['active_members'] > $mostPopular['active_members']) {
        $mostPopular = $branchRow;
    }
    if ($branchRow['revenue'] > $highestRevenue['revenue']) {
        $highestRevenue = $branchRow;
    }
}

require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/sidebar.php';
?>

<div class="branch-analytics">
<header class="branch-page-heading">
    <div class="branch-page-heading-copy">
        <span class="branch-eyebrow"><i data-lucide="git-compare-arrows" aria-hidden="true"></i> MULTI-LOCATION REPORTING</span>
        <h1>Branch analytics</h1>
        <p>Compare monthly performance and combine summary metrics across your gym locations.</p>
    </div>
    <form method="GET" class="branch-period-control">
        <input type="hidden" name="view" value="<?= sanitize($view) ?>">
        <label for="month"><i data-lucide="calendar-days" aria-hidden="true"></i> Reporting month</label>
        <input id="month" type="month" name="month" value="<?= sanitize($month) ?>" onchange="this.form.submit()">
    </form>
</header>

<nav class="branch-view-nav" aria-label="Branch analytics views">
    <div class="branch-view-tabs">
        <a href="<?= BASE_URL ?>/pages/analytics/branches.php?month=<?= rawurlencode($month) ?>&amp;view=compare" class="branch-view-tab <?= $view === 'compare' ? 'active' : '' ?>" <?= $view === 'compare' ? 'aria-current="page"' : '' ?>>
            <i data-lucide="columns-3" aria-hidden="true"></i> Compare branches
        </a>
        <a href="<?= BASE_URL ?>/pages/analytics/branches.php?month=<?= rawurlencode($month) ?>&amp;view=consolidated" class="branch-view-tab <?= $view === 'consolidated' ? 'active' : '' ?>" <?= $view === 'consolidated' ? 'aria-current="page"' : '' ?>>
            <i data-lucide="chart-no-axes-combined" aria-hidden="true"></i> Consolidated view
        </a>
    </div>
    <a href="<?= BASE_URL ?>/exports/excel.php?type=branch_analytics&amp;month=<?= rawurlencode($month) ?>" class="btn btn-secondary branch-export-btn">
        <i data-lucide="download" aria-hidden="true"></i> Export branch CSV
    </a>
</nav>

<div class="branch-setup-grid">
    <section class="card branch-setup-card">
        <div class="branch-card-heading">
            <span class="branch-card-icon"><i data-lucide="building-2" aria-hidden="true"></i></span>
            <div>
                <h2>This system’s branch name</h2>
                <p>Give each gym copy a unique identity for accurate comparisons.</p>
            </div>
        </div>
        <div class="branch-setup-hint"><i data-lucide="info" aria-hidden="true"></i><span>Give each installation a unique name: <strong>707_GYM_MAIN</strong>, <strong>707_GYM_1</strong>, <strong>707_GYM_2</strong>, and so on. Any installation can compare snapshots from the others.</span></div>
        <form method="POST" class="branch-identity-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_identity">
            <input type="hidden" name="month" value="<?= sanitize($month) ?>">
            <input name="branch_name" value="<?= sanitize($localBranch) ?>" pattern="707_GYM_(MAIN|[1-9][0-9]*)" maxlength="40" required aria-label="Branch name">
            <button type="submit" class="btn btn-secondary"><i data-lucide="save" aria-hidden="true"></i> Save name</button>
        </form>
    </section>
    <section class="card branch-setup-card">
        <div class="branch-card-heading">
            <span class="branch-card-icon"><i data-lucide="file-up" aria-hidden="true"></i></span>
            <div>
                <h2>Import branch analytics</h2>
                <p>Collect monthly snapshots from the other 707 GYM locations.</p>
            </div>
        </div>
        <div class="branch-setup-hint"><i data-lucide="refresh-cw" aria-hidden="true"></i><span>Select one CSV from each branch for <?= sanitize($month) ?>, then import them together. Existing snapshots for those branch-months are replaced.</span></div>
        <form method="POST" enctype="multipart/form-data" class="branch-import-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="import_snapshot">
            <input type="hidden" name="month" value="<?= sanitize($month) ?>">
            <label class="branch-file-input"><i data-lucide="file-spreadsheet" aria-hidden="true"></i><span><strong>Choose branch CSV files</strong><small>One snapshot per branch · up to 5 MB each</small></span><input type="file" name="csv_files[]" accept=".csv,text/csv" multiple required></label>
            <button type="submit" class="btn btn-primary"><i data-lucide="upload" aria-hidden="true"></i> Import CSV</button>
        </form>
    </section>
</div>

<section class="branch-summary-grid" aria-label="Combined branch summary">
    <article class="branch-summary-card branch-summary-revenue">
        <span class="branch-summary-icon"><i data-lucide="wallet" aria-hidden="true"></i></span>
        <div><span class="branch-summary-label">Combined revenue</span><strong><?= formatPhilippineCurrency($totals['revenue']) ?></strong><small>Paid collections for period</small></div>
    </article>
    <article class="branch-summary-card">
        <span class="branch-summary-icon"><i data-lucide="users-round" aria-hidden="true"></i></span>
        <div><span class="branch-summary-label">Active members</span><strong><?= number_format($totals['active_members']) ?></strong><small>Combined across branches</small></div>
    </article>
    <article class="branch-summary-card">
        <span class="branch-summary-icon"><i data-lucide="activity" aria-hidden="true"></i></span>
        <div><span class="branch-summary-label">Attendance visits</span><strong><?= number_format($totals['attendance_visits']) ?></strong><small>Combined for period</small></div>
    </article>
    <article class="branch-summary-card branch-summary-popular">
        <span class="branch-summary-icon"><i data-lucide="trophy" aria-hidden="true"></i></span>
        <div><span class="branch-summary-label">Most active branch</span><strong><?= sanitize($mostPopular['branch_name']) ?></strong><small><?= number_format($mostPopular['active_members']) ?> active members</small></div>
    </article>
</div>

<?php if ($view === 'compare'): ?>
<section class="card branch-results-card mb-4">
    <div class="branch-results-heading">
        <div><span class="branch-eyebrow">LOCATION BREAKDOWN</span><h2>Branch comparison</h2><p>Performance by location for <?= sanitize(date('F Y', strtotime($month . '-01'))) ?></p></div>
        <span class="branch-count-badge"><i data-lucide="building" aria-hidden="true"></i> <?= count($branchRows) ?> branch<?= count($branchRows) === 1 ? '' : 'es' ?></span>
    </div>
    <div class="table-wrapper">
        <table class="branch-analytics-table">
            <thead><tr><th>Branch</th><th>Revenue</th><th>New Members</th><th>Active Members*</th><th>Attendance Visits</th><th>Active Memberships*</th><th>Snapshot</th></tr></thead>
            <tbody>
                <?php foreach ($branchRows as $branchRow): ?>
                <tr>
                    <td><strong><?= sanitize($branchRow['branch_name']) ?></strong><?= $branchRow['branch_name'] === $highestRevenue['branch_name'] ? ' <span class="badge badge-success">Top revenue</span>' : '' ?></td>
                    <td><?= formatPhilippineCurrency($branchRow['revenue']) ?></td>
                    <td><?= number_format($branchRow['new_members']) ?></td>
                    <td><?= number_format($branchRow['active_members']) ?></td>
                    <td><?= number_format($branchRow['attendance_visits']) ?></td>
                    <td><?= number_format($branchRow['active_memberships']) ?></td>
                    <td><?= sanitize($branchRow['source_file']) ?> · <?= formatDate($branchRow['captured_at'], 'M d, Y g:i A') ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <th>All branches combined</th>
                    <th><?= formatPhilippineCurrency($totals['revenue']) ?></th>
                    <th><?= number_format($totals['new_members']) ?></th>
                    <th><?= number_format($totals['active_members']) ?></th>
                    <th><?= number_format($totals['attendance_visits']) ?></th>
                    <th><?= number_format($totals['active_memberships']) ?></th>
                    <th><?= count($branchRows) ?> branch<?= count($branchRows) === 1 ? '' : 'es' ?></th>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="branch-data-note"><i data-lucide="info" aria-hidden="true"></i><span>* Active members and active memberships are point-in-time counts captured when each branch exports its CSV. Revenue, new members, and attendance visits cover the selected month. Revenue is paid collections, not net profit; the system does not track expenses. These totals combine branch summaries; they do not copy or merge member-level records into this system.</span></p>
</section>
<?php else: ?>
<section class="card branch-results-card mb-4">
    <div class="branch-results-heading">
        <div><span class="branch-eyebrow">ALL LOCATIONS</span><h2>Consolidated analytics</h2><p>Combined summary for <?= sanitize(date('F Y', strtotime($month . '-01'))) ?></p></div>
        <span class="branch-count-badge"><i data-lucide="layers-3" aria-hidden="true"></i> <?= count($branchRows) ?> branch<?= count($branchRows) === 1 ? '' : 'es' ?></span>
    </div>
    <div class="branch-consolidated-intro"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i><span>One overview of your branch network. These figures combine exported summaries; they do not merge member accounts between systems.</span></div>
    <div class="table-wrapper">
        <table class="branch-analytics-table branch-consolidated-table">
            <thead><tr><th>Combined metric</th><th>All branches</th></tr></thead>
            <tbody>
                <tr><td>Branch snapshots included</td><td><?= number_format(count($branchRows)) ?></td></tr>
                <tr><td>Revenue for the month</td><td><?= formatPhilippineCurrency($totals['revenue']) ?></td></tr>
                <tr><td>New members for the month</td><td><?= number_format($totals['new_members']) ?></td></tr>
                <tr><td>Active members*</td><td><?= number_format($totals['active_members']) ?></td></tr>
                <tr><td>Attendance visits for the month</td><td><?= number_format($totals['attendance_visits']) ?></td></tr>
                <tr><td>Active memberships*</td><td><?= number_format($totals['active_memberships']) ?></td></tr>
            </tbody>
        </table>
    </div>
    <p class="branch-data-note"><i data-lucide="info" aria-hidden="true"></i><span>Active-member and active-membership counts are captured when each branch exports its CSV. Revenue is paid collections, not net profit, because expenses are not tracked.</span></p>
</section>
<?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
