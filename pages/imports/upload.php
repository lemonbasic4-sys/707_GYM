<?php
/**
 * 707 GYM – CSV Import Wizard (Multi-step)
 * Steps: 1=Upload, 2=Map Columns, 3=Preview, 4=Confirm & Execute
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db   = Database::getConnection();
$type = $_GET['type'] ?? $_POST['import_type'] ?? '';

$allowedTypes = ['members','memberships','attendance','payments'];
if (!in_array($type, $allowedTypes, true)) {
    setFlash('error', 'Invalid import type.');
    redirect(BASE_URL . '/pages/imports/index.php');
}

// ── System fields per type ────────────────────────────────────────────────────
$systemFields = [
    'members'      => ['first_name','last_name','email','phone','address','gender','date_of_birth'],
    'memberships'  => ['member_email','plan_name','start_date','end_date','status'],
    'attendance'   => ['member_email','check_in','check_out','date'],
    'payments'     => ['member_email','amount','payment_method','payment_date','status'],
];
$fields = $systemFields[$type];

$step     = (int)($_POST['step'] ?? 1);
$errors   = [];
$csvData  = [];
$headers  = [];

// ── Step 1 → Step 2: Parse CSV ─────────────────────────────────────────────
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Token mismatch.'; $step = 1; }
    elseif (empty($_FILES['csv_file']['name']))        { $errors[] = 'Please upload a CSV file.'; $step = 1; }
    else {
        $file    = $_FILES['csv_file'];
        $tmpPath = $file['tmp_name'];
        $handle  = fopen($tmpPath, 'r');

        if ($handle) {
            $headers = fgetcsv($handle); // first row = headers
            while (($row = fgetcsv($handle)) !== false) {
                $csvData[] = $row;
            }
            fclose($handle);

            // Store in session for next steps
            $_SESSION['import_csv'] = [
                'type'      => $type,
                'headers'   => $headers,
                'data'      => $csvData,
                'filename'  => $file['name'],
            ];
            $step = 2;
        } else {
            $errors[] = 'Failed to read CSV file.';
            $step = 1;
        }
    }
    rotateCsrfToken();
}

// ── Step 2 → Step 3: Map columns ──────────────────────────────────────────
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['column_map'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Token mismatch.'; }
    else {
        $_SESSION['import_csv']['column_map'] = $_POST['column_map'];
        $step = 3;
    }
    rotateCsrfToken();
}

// ── Step 3 → Step 4: Confirm ───────────────────────────────────────────────
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $step = 4;
}

// ── Step 4: Execute import ─────────────────────────────────────────────────
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token mismatch.';
    } else {
        $csv       = $_SESSION['import_csv'] ?? [];
        $data      = $csv['data']       ?? [];
        $colMap    = $csv['column_map'] ?? [];
        $filename  = $csv['filename']   ?? 'unknown.csv';

        $imported = $updated = $skipped = $errCount = 0;
        $errDetails = [];

        $db->beginTransaction();
        try {
            foreach ($data as $i => $row) {
                $record = [];
                foreach ($colMap as $sysField => $csvIdx) {
                    $record[$sysField] = $row[$csvIdx] ?? null;
                }

                if ($type === 'members') {
                    if (empty($record['first_name'])) { $skipped++; continue; }
                    $email = trim($record['email'] ?? '');
                    $phone = trim($record['phone'] ?? '');
                    $existing = null;
                    if ($email !== '') {
                        $existingStmt = $db->prepare("SELECT id FROM members WHERE email = :email LIMIT 1");
                        $existingStmt->execute([':email' => $email]);
                        $existing = $existingStmt->fetchColumn();
                    } elseif ($phone !== '') {
                        $existingStmt = $db->prepare("SELECT id FROM members WHERE phone = :phone LIMIT 1");
                        $existingStmt->execute([':phone' => $phone]);
                        $existing = $existingStmt->fetchColumn();
                    }
                    if ($existing) {
                        $skipped++;
                        continue;
                    }
                    $db->prepare(
                        "INSERT INTO members (first_name,last_name,email,phone,address,gender,date_of_birth,status,created_at,updated_at)
                         VALUES (:fn,:ln,:em,:ph,:ad,:gn,:dob,'active',NOW(),NOW())"
                    )->execute([
                        ':fn'=>$record['first_name'],':ln'=>$record['last_name']??'',
                        ':em'=>$email ?: null,':ph'=>$phone ?: null,
                        ':ad'=>$record['address']??null,':gn'=>$record['gender']??null,
                        ':dob'=>$record['date_of_birth']??null,
                    ]);
                    $imported++;
                } elseif ($type === 'attendance') {
                    // Look up member
                    $mStmt = $db->prepare("SELECT id FROM members WHERE email = :email LIMIT 1");
                    $mStmt->execute([':email'=>trim($record['member_email'] ?? '')]);
                    $mRow = $mStmt->fetch();
                    if (!$mRow) { $skipped++; $errDetails[] = "Row ".($i+2).": member not found."; continue; }
                    $db->prepare(
                        "INSERT IGNORE INTO attendance (member_id,check_in,check_out,date,created_at)
                         VALUES (:mid,:ci,:co,:d,NOW())"
                    )->execute([
                        ':mid'=>$mRow['id'],
                        ':ci'=>$record['check_in']??null,':co'=>$record['check_out']??null,
                        ':d'=>$record['date']??date('Y-m-d'),
                    ]);
                    $imported++;
                } else {
                    // Generic: count as skipped if no specific handler
                    $skipped++;
                }
            }
            $db->commit();

            // Log import
            $db->prepare(
                "INSERT INTO import_logs (import_type,filename,total_rows,imported,updated,skipped,errors,error_details,imported_by,created_at)
                 VALUES (:t,:fn,:tot,:imp,:upd,:sk,:err,:ed,:by,NOW())"
            )->execute([
                ':t'=>$type,':fn'=>$filename,
                ':tot'=>count($data),':imp'=>$imported,':upd'=>$updated,
                ':sk'=>$skipped,':err'=>$errCount,
                ':ed'=>json_encode($errDetails),':by'=>$_SESSION['admin_id'],
            ]);

            unset($_SESSION['import_csv']);
            logActivity($_SESSION['admin_id'], 'import.execute', "Type: {$type}, imported: {$imported}");
            setFlash('success', "Import complete. {$imported} records imported, {$skipped} skipped.");
            redirect(BASE_URL . '/pages/imports/history.php');
        } catch (Throwable $e) {
            $db->rollBack();
            $errors[] = 'Import failed: ' . $e->getMessage();
        }
    }
    rotateCsrfToken();
}

// Restore CSV data from session for steps 2/3
if ($step >= 2 && isset($_SESSION['import_csv'])) {
    $csv     = $_SESSION['import_csv'];
    $headers = $csv['headers'] ?? [];
    $csvData = $csv['data']    ?? [];
}

$pageTitle = 'Import ' . ucfirst($type);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Import <?= ucfirst($type) ?></h1>
        <p>Step <?= $step ?> of 4</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/imports/index.php" class="btn btn-ghost"><i data-lucide="arrow-left"></i> Back</a>
    </div>
</div>

<!-- Step indicator -->
<div style="display:flex;gap:8px;margin-bottom:28px;align-items:center;">
    <?php foreach(['Upload','Map','Preview','Execute'] as $si => $sl): ?>
    <div style="display:flex;align-items:center;gap:8px;">
        <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;
            background:<?= ($si+1)<=$step?'var(--accent)':'rgba(255,255,255,0.08)' ?>;
            color:<?= ($si+1)<=$step?'#fff':'var(--muted)' ?>;"><?= $si+1 ?></div>
        <span style="font-size:13px;color:<?= ($si+1)===$step?'var(--text)':'var(--muted)' ?>;"><?= $sl ?></span>
    </div>
    <?php if ($si < 3): ?><div style="flex:1;height:1px;background:var(--border);"></div><?php endif; ?>
    <?php endforeach; ?>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= sanitize($e) ?></div><?php endforeach; ?>

<?php if ($step === 1): ?>
<!-- STEP 1: Upload -->
<div class="card" style="max-width:540px;">
    <form method="POST" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="step" value="1">
        <input type="hidden" name="import_type" value="<?= sanitize($type) ?>">

        <div class="form-group">
            <label>CSV File <span class="required">*</span></label>
            <input type="file" name="csv_file" accept=".csv,text/csv" required>
            <span class="form-hint">First row must be column headers.</span>
        </div>
        <button type="submit" class="btn btn-primary"><i data-lucide="upload"></i> Upload and Continue</button>
    </form>
</div>

<?php elseif ($step === 2): ?>
<!-- STEP 2: Map Columns -->
<div class="card" style="max-width:640px;">
    <p class="text-muted mb-2">Detected <?= count($headers) ?> columns in your CSV. Map each system field to a CSV column.</p>
    <form method="POST" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="step" value="2">
        <input type="hidden" name="import_type" value="<?= sanitize($type) ?>">
        <?php foreach ($fields as $field): ?>
        <div class="form-group">
            <label><?= sanitize($field) ?></label>
            <select name="column_map[<?= sanitize($field) ?>]">
                <option value="">-- Skip --</option>
                <?php foreach ($headers as $idx => $header): ?>
                <option value="<?= (int)$idx ?>"><?= sanitize($header) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn btn-primary">Map &amp; Preview</button>
        </div>
    </form>
</div>

<?php elseif ($step === 3): ?>
<!-- STEP 3: Preview -->
<div class="card mb-3">
    <div class="card-header"><span class="card-title">Preview – First 10 Rows</span></div>
    <div class="table-wrapper">
        <table>
            <thead><tr>
                <?php foreach ($fields as $f): ?><th><?= sanitize($f) ?></th><?php endforeach; ?>
                <th>Status</th>
            </tr></thead>
            <tbody>
                <?php
                $colMap  = $_SESSION['import_csv']['column_map'] ?? [];
                $preview = array_slice($csvData, 0, 10);
                foreach ($preview as $row):
                    $valid = !empty($row[0]);
                ?>
                <tr>
                    <?php foreach ($fields as $f): ?>
                    <td><?= sanitize($row[$colMap[$f] ?? -1] ?? '') ?></td>
                    <?php endforeach; ?>
                    <td><span class="badge badge-<?= $valid?'success':'warning' ?>"><?= $valid?'OK':'Check' ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="step" value="3">
    <input type="hidden" name="import_type" value="<?= sanitize($type) ?>">
    <input type="hidden" name="confirm" value="1">
    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Looks good — Proceed to Import</button>
    </div>
</form>

<?php elseif ($step === 4): ?>
<!-- STEP 4: Confirm -->
<div class="card" style="max-width:480px;">
    <div class="alert alert-warning mb-2">
        <i data-lucide="alert-triangle"></i>
        This will import <strong><?= number_format(count($csvData)) ?></strong> rows into the <strong><?= sanitize($type) ?></strong> table.
        This action cannot be easily undone.
    </div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="step" value="4">
        <input type="hidden" name="import_type" value="<?= sanitize($type) ?>">
        <input type="hidden" name="execute" value="1">
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn btn-primary"><i data-lucide="check-circle"></i> Execute Import</button>
            <a href="<?= BASE_URL ?>/pages/imports/index.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
