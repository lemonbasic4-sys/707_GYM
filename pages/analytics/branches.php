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

function parseBranchDataCsv(array $file, string $localBranch): array
{
    if (!isset($file['error'], $file['size'], $file['name'], $file['tmp_name'])
        || !is_int($file['error']) || !is_int($file['size'])
        || !is_string($file['name']) || !is_string($file['tmp_name'])
        || $file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > MAX_BRANCH_DATA_FILE_SIZE) {
        throw new InvalidArgumentException('Each branch data CSV must be readable and no larger than 15 MB.');
    }
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        throw new InvalidArgumentException('Branch data imports must be CSV files.');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        throw new InvalidArgumentException('A selected branch data CSV could not be read.');
    }

    $headers = fgetcsv($handle, null, ',', '"', '\\');
    if (is_array($headers) && isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
    }
    $expectedHeaders = ['record_type', 'branch_name', 'captured_at', 'record_id', 'payload_json'];
    if (!is_array($headers) || array_map('strtolower', array_map('trim', $headers)) !== $expectedHeaders) {
        fclose($handle);
        throw new InvalidArgumentException('A selected file is not a valid 707 GYM full branch data CSV.');
    }

    $allowedTypes = ['members', 'membership_plans', 'memberships', 'attendance', 'payments'];
    $data = array_fill_keys($allowedTypes, []);
    $branchName = null;
    $capturedAt = null;
    $manifestSeen = false;
    $seenIds = array_fill_keys($allowedTypes, []);
    $line = 1;
    while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
        $line++;
        if (count($row) !== count($expectedHeaders)) {
            fclose($handle);
            throw new InvalidArgumentException("Invalid CSV row {$line}.");
        }
        [$recordType, $rowBranch, $rowCapturedAt, $recordId, $payloadJson] = array_map('trim', $row);
        if ($branchName === null) {
            $branchName = $rowBranch;
            $capturedAt = $rowCapturedAt;
        }
        if ($rowBranch !== $branchName || $rowCapturedAt !== $capturedAt) {
            fclose($handle);
            throw new InvalidArgumentException('A branch data file must contain one branch and one export time only.');
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            fclose($handle);
            throw new InvalidArgumentException("Invalid record data on CSV row {$line}.");
        }
        if ($recordType === 'manifest') {
            if ($manifestSeen || $line !== 2 || $recordId !== 'manifest' || ($payload['format_version'] ?? null) !== 1) {
                fclose($handle);
                throw new InvalidArgumentException('The branch data CSV manifest is invalid.');
            }
            $manifestSeen = true;
            continue;
        }
        if (!in_array($recordType, $allowedTypes, true)
            || !ctype_digit($recordId)
            || (int) $recordId < 1
            || (int) ($payload['id'] ?? 0) !== (int) $recordId
            || isset($seenIds[$recordType][$recordId])) {
            fclose($handle);
            throw new InvalidArgumentException("Invalid or duplicate record on CSV row {$line}.");
        }
        $seenIds[$recordType][$recordId] = true;
        $data[$recordType][] = $payload;
    }
    fclose($handle);

    $captured = is_string($capturedAt) ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $capturedAt) : false;
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validCapturedAt = $captured !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $captured->format('Y-m-d H:i:s') === $capturedAt;
    if (!$manifestSeen || !is_string($branchName) || !$validCapturedAt) {
        throw new InvalidArgumentException('This branch data file has an invalid manifest or export time. Export a fresh file from Branch Data Transfer and try again.');
    }
    if (!isValidBranchName($branchName)) {
        throw new InvalidArgumentException('The CSV branch name is invalid. Set a unique branch name in Branch Analytics, then export again.');
    }
    if ($branchName === $localBranch) {
        throw new InvalidArgumentException(
            "This file is from {$branchName}, which is the current branch. Import a CSV exported by another branch. Make sure every installation has its own branch name in Branch Analytics."
        );
    }

    $requiredFields = [
        'members' => ['id', 'first_name', 'last_name', 'status', 'created_at'],
        'membership_plans' => ['id', 'name', 'duration_days', 'price', 'status'],
        'memberships' => ['id', 'member_id', 'plan_id', 'start_date', 'end_date', 'status'],
        'attendance' => ['id', 'member_id', 'check_in', 'date'],
        'payments' => ['id', 'transaction_id', 'transaction_type', 'member_id', 'amount', 'payment_method', 'payment_date', 'status'],
    ];
    foreach ($data as $recordType => $records) {
        foreach ($records as $record) {
            foreach ($requiredFields[$recordType] as $field) {
                if (!array_key_exists($field, $record)) {
                    throw new InvalidArgumentException("A {$recordType} record is missing its {$field} field.");
                }
            }
            if ($recordType === 'members' && !in_array($record['status'], ['active', 'archived'], true)) {
                throw new InvalidArgumentException('A member record has an invalid status.');
            }
            if ($recordType === 'membership_plans'
                && (!in_array($record['status'], ['active', 'inactive'], true)
                    || filter_var($record['duration_days'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                    || !is_numeric($record['price']) || !is_finite((float) $record['price']) || (float) $record['price'] < 0)) {
                throw new InvalidArgumentException('A membership plan has invalid duration, price, or status.');
            }
            if ($recordType === 'memberships' && !in_array($record['status'], ['active', 'expired', 'inactive'], true)) {
                throw new InvalidArgumentException('A membership record has an invalid status.');
            }
            if ($recordType === 'payments'
                && (!in_array($record['status'], ['paid', 'partial', 'unpaid'], true)
                    || !in_array($record['payment_method'], ['cash', 'gcash', 'bank_transfer', 'card'], true)
                    || !is_numeric($record['amount']) || !is_finite((float) $record['amount']) || (float) $record['amount'] < 0)) {
                throw new InvalidArgumentException('A payment record has an invalid amount, method, or status.');
            }
        }
    }

    $memberIds = array_fill_keys(array_map(static fn(array $row): int => (int) $row['id'], $data['members']), true);
    $planIds = array_fill_keys(array_map(static fn(array $row): int => (int) $row['id'], $data['membership_plans']), true);
    $membershipIds = array_fill_keys(array_map(static fn(array $row): int => (int) $row['id'], $data['memberships']), true);
    foreach ($data['memberships'] as $membership) {
        if (!isset($memberIds[(int) ($membership['member_id'] ?? 0)])
            || !isset($planIds[(int) ($membership['plan_id'] ?? 0)])) {
            throw new InvalidArgumentException('Membership records must refer to members and plans in the same branch CSV.');
        }
    }
    foreach ($data['attendance'] as $attendance) {
        if (!isset($memberIds[(int) ($attendance['member_id'] ?? 0)])) {
            throw new InvalidArgumentException('Attendance records must refer to members in the same branch CSV.');
        }
    }
    $transactionIds = [];
    foreach ($data['payments'] as $payment) {
        if (!isset($memberIds[(int) ($payment['member_id'] ?? 0)])
            || (!empty($payment['membership_id']) && !isset($membershipIds[(int) $payment['membership_id']]))) {
            throw new InvalidArgumentException('Payment records must refer to members and memberships in the same branch CSV.');
        }
        if (isset($transactionIds[$payment['transaction_id']])) {
            throw new InvalidArgumentException('Payment transaction IDs must be unique within a branch CSV.');
        }
        $transactionIds[$payment['transaction_id']] = true;
    }

    return [
        'branch_name' => $branchName,
        'exported_at' => $capturedAt,
        'source_file' => substr(basename($file['name']), 0, 255),
        'data' => $data,
    ];
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
$hasBranchDataSchema = hasBranchDataSnapshotsSchema($db);

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
            $hasSavedData = false;
            if ($hasBranchDataSchema) {
                $dataCheck = $db->prepare("SELECT COUNT(*) FROM branch_data_snapshots WHERE branch_name = :branch_name");
                $dataCheck->execute([':branch_name' => $branchName]);
                $hasSavedData = (int) $dataCheck->fetchColumn() > 0;
            }
            if ((int) $existing->fetchColumn() > 0 || $hasSavedData) {
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

    if ($action === 'import_branch_data') {
        try {
            if (!$hasBranchDataSchema) {
                throw new InvalidArgumentException('Run database/migrations/010_add_branch_data_snapshots.sql on this database before importing full branch data.');
            }
            $uploads = $_FILES['csv_files'] ?? null;
            if (!is_array($uploads) || !isset($uploads['name'], $uploads['tmp_name'], $uploads['error'], $uploads['size'])
                || !is_array($uploads['name']) || !is_array($uploads['tmp_name'])
                || !is_array($uploads['error']) || !is_array($uploads['size'])
                || count($uploads['name']) === 0) {
                throw new InvalidArgumentException('Choose one or more full branch data CSV files.');
            }

            $localBranch = getLocalBranchName($db);
            $bundles = [];
            $seenBranches = [];
            foreach (array_keys($uploads['name']) as $index) {
                $bundle = parseBranchDataCsv([
                    'name' => $uploads['name'][$index] ?? null,
                    'tmp_name' => $uploads['tmp_name'][$index] ?? null,
                    'error' => $uploads['error'][$index] ?? null,
                    'size' => $uploads['size'][$index] ?? null,
                ], $localBranch);
                if (isset($seenBranches[$bundle['branch_name']])) {
                    throw new InvalidArgumentException('Only one CSV per branch can be imported at a time.');
                }
                $seenBranches[$bundle['branch_name']] = true;
                $bundles[] = $bundle;
            }

            $db->beginTransaction();
            $statement = $db->prepare(
                "INSERT INTO branch_data_snapshots
                    (branch_name, exported_at, source_file, snapshot_json, imported_by, imported_at)
                 VALUES
                    (:branch_name, :exported_at, :source_file, :snapshot_json, :imported_by, CURRENT_TIMESTAMP)
                 ON DUPLICATE KEY UPDATE
                    exported_at = VALUES(exported_at),
                    source_file = VALUES(source_file),
                    snapshot_json = VALUES(snapshot_json),
                    imported_by = VALUES(imported_by),
                    imported_at = CURRENT_TIMESTAMP"
            );
            foreach ($bundles as $bundle) {
                $statement->execute([
                    ':branch_name' => $bundle['branch_name'],
                    ':exported_at' => $bundle['exported_at'],
                    ':source_file' => $bundle['source_file'],
                    ':snapshot_json' => json_encode($bundle['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    ':imported_by' => (int) $_SESSION['admin_id'],
                ]);
            }
            $branchNames = array_column($bundles, 'branch_name');
            logActivity(
                (int) $_SESSION['admin_id'],
                'branch.data.import',
                'Branches: ' . implode(', ', $branchNames)
            );
            $db->commit();
            rotateCsrfToken();
            setFlash('success', count($bundles) . ' full branch data CSV file(s) imported. Existing data for those branches was replaced.');
        } catch (InvalidArgumentException $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            setFlash('error', $error->getMessage());
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Full branch data import failed: ' . $error->getMessage());
            setFlash('error', 'Branch data could not be imported. No branch data was changed.');
        }
        $returnToImports = ($_POST['return_to'] ?? '') === 'imports';
        redirect($returnToImports
            ? BASE_URL . '/pages/imports/index.php'
            : BASE_URL . '/pages/analytics/branches.php?month=' . rawurlencode($month));
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
if ($hasBranchDataSchema) {
    $branchDataStmt = $db->prepare(
        "SELECT branch_name, exported_at, source_file, snapshot_json
         FROM branch_data_snapshots
         WHERE branch_name <> :local_branch
         ORDER BY branch_name"
    );
    $branchDataStmt->execute([':local_branch' => $localBranch]);
    foreach ($branchDataStmt->fetchAll() as $snapshot) {
        $data = json_decode($snapshot['snapshot_json'], true);
        if (!is_array($data)) {
            throw new RuntimeException('Imported branch data is invalid for ' . $snapshot['branch_name'] . '. Re-import that branch CSV.');
        }
        $row = [
            'branch_name' => $snapshot['branch_name'],
            'captured_at' => $snapshot['exported_at'],
            'source_file' => $snapshot['source_file'],
        ] + getImportedBranchAnalytics($data, $month);
        $existingIndex = null;
        foreach ($branchRows as $index => $branchRow) {
            if ($branchRow['branch_name'] === $snapshot['branch_name']) {
                $existingIndex = $index;
                break;
            }
        }
        if ($existingIndex === null) {
            $branchRows[] = $row;
        } else {
            $branchRows[$existingIndex] = $row;
        }
    }
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
    <div class="branch-export-actions">
        <a href="<?= BASE_URL ?>/exports/excel.php?type=branch_analytics&amp;month=<?= rawurlencode($month) ?>" class="btn btn-secondary branch-export-btn">
            <i data-lucide="download" aria-hidden="true"></i> Export summary
        </a>
        <a href="<?= BASE_URL ?>/exports/excel.php?type=branch_data" class="btn btn-primary branch-export-btn">
            <i data-lucide="database" aria-hidden="true"></i> Export all branch data
        </a>
    </div>
</nav>

<?php if (!$hasBranchDataSchema): ?>
<div class="alert alert-warning" role="alert">
    Full branch data transfer requires the one-time migration <code>database/migrations/010_add_branch_data_snapshots.sql</code> on this database.
</div>
<?php endif; ?>

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
            <span class="branch-card-icon"><i data-lucide="database" aria-hidden="true"></i></span>
            <div>
                <h2>Import full branch data</h2>
                <p>Collect complete exports from one or more other branches.</p>
            </div>
        </div>
        <div class="branch-setup-hint"><i data-lucide="refresh-cw" aria-hidden="true"></i><span>Members, plans, memberships, attendance, and payments stay separated by branch. Re-importing a branch replaces its previous imported copy.</span></div>
        <form method="POST" enctype="multipart/form-data" class="branch-import-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="import_branch_data">
            <input type="hidden" name="month" value="<?= sanitize($month) ?>">
            <label class="branch-file-input"><i data-lucide="file-spreadsheet" aria-hidden="true"></i><span><strong>Choose full branch CSV files</strong><small>One or more files · up to 15 MB each</small></span><input type="file" name="csv_files[]" accept=".csv,text/csv" multiple required></label>
            <button type="submit" class="btn btn-primary" <?= !$hasBranchDataSchema ? 'disabled' : '' ?>><i data-lucide="upload" aria-hidden="true"></i> Import branch data</button>
        </form>
    </section>
    <section class="card branch-setup-card">
        <div class="branch-card-heading">
            <span class="branch-card-icon"><i data-lucide="file-up" aria-hidden="true"></i></span>
            <div>
                <h2>Import monthly summary CSV</h2>
                <p>For older exports that contain analytics totals only.</p>
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
    <p class="branch-data-note"><i data-lucide="info" aria-hidden="true"></i><span>* Active member and membership counts reflect each branch’s latest export. Revenue, new members, and attendance visits cover the selected month. Detailed imports stay separated by branch and do not enter this branch’s operational member list. Totals add each branch’s counts, so a person active at multiple branches is counted once at each branch. Revenue is paid collections, not net profit; expenses are not tracked.</span></p>
</section>
<?php else: ?>
<section class="card branch-results-card mb-4">
    <div class="branch-results-heading">
        <div><span class="branch-eyebrow">ALL LOCATIONS</span><h2>Consolidated analytics</h2><p>Combined summary for <?= sanitize(date('F Y', strtotime($month . '-01'))) ?></p></div>
        <span class="branch-count-badge"><i data-lucide="layers-3" aria-hidden="true"></i> <?= count($branchRows) ?> branch<?= count($branchRows) === 1 ? '' : 'es' ?></span>
    </div>
    <div class="branch-consolidated-intro"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i><span>One overview of your branch network. Detailed records are retained separately by branch; combined counts are sums and do not deduplicate a person who is active at multiple locations.</span></div>
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
