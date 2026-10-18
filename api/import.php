<?php
/**
 * 707 GYM - CSV Import Processing API
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = Database::getConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'validate':
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Valid CSV upload required']);
            exit;
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            echo json_encode(['success' => false, 'message' => 'Failed to read file']);
            exit;
        }

        $headers = fgetcsv($handle);
        $previewRows = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false && $rowCount < 10) {
            $previewRows[] = $row;
            $rowCount++;
        }
        fclose($handle);

        echo json_encode([
            'success' => true,
            'headers' => $headers,
            'preview' => $previewRows,
            'total_previewed' => $rowCount
        ]);
        break;

    case 'execute':
        $type = $_POST['type'] ?? '';
        $actionOnMatch = $_POST['on_match'] ?? 'skip'; // skip or update

        if (!in_array($type, ['members', 'attendance', 'payments', 'memberships'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters provided']);
            exit;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Valid CSV file required']);
            exit;
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $headers = fgetcsv($handle);
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1;

        $pdo->beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count($row) < 2) continue; // skip empty rows

                if ($type === 'members') {
                    // Expect: first_name, last_name, email, phone, gender
                    $fname = trim($row[0] ?? '');
                    $lname = trim($row[1] ?? '');
                    $email = trim($row[2] ?? '');
                    $phone = trim($row[3] ?? '');
                    $gender = trim($row[4] ?? 'other');

                    if (empty($fname) || empty($lname)) {
                        $errors[] = "Row $rowNum: Missing required name fields.";
                        continue;
                    }

                    $existing = false;
                    if ($email !== '') {
                        $chk = $pdo->prepare("SELECT id FROM members WHERE email = :email LIMIT 1");
                        $chk->execute([':email' => $email]);
                        $existing = $chk->fetch();
                    } elseif ($phone !== '') {
                        $chk = $pdo->prepare("SELECT id FROM members WHERE phone = :phone LIMIT 1");
                        $chk->execute([':phone' => $phone]);
                        $existing = $chk->fetch();
                    }

                    if ($existing) {
                        if ($actionOnMatch === 'update') {
                            $upd = $pdo->prepare("UPDATE members SET first_name = :fn, last_name = :ln, email = :e, phone = :p WHERE id = :id");
                            $upd->execute([':fn' => $fname, ':ln' => $lname, ':e' => $email ?: null, ':p' => $phone ?: null, ':id' => $existing['id']]);
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        $ins = $pdo->prepare("INSERT INTO members (first_name, last_name, email, phone, gender, status) VALUES (:fn, :ln, :e, :p, :g, 'active')");
                        $ins->execute([':fn' => $fname, ':ln' => $lname, ':e' => $email ?: null, ':p' => $phone ?: null, ':g' => $gender]);
                        $imported++;
                    }
                }
            }
            fclose($handle);

            // Log import
            $logStmt = $pdo->prepare("INSERT INTO import_logs (import_type, filename, total_rows, imported, updated, skipped, errors, error_details, imported_by)
                                     VALUES (:type, :fn, :tot, :imp, :upd, :skp, :err, :dtl, :adm)");
            $logStmt->execute([
                ':type' => $type,
                ':fn' => $_FILES['csv_file']['name'],
                ':tot' => ($imported + $updated + $skipped + count($errors)),
                ':imp' => $imported,
                ':upd' => $updated,
                ':skp' => $skipped,
                ':err' => count($errors),
                ':dtl' => json_encode($errors),
                ':adm' => $_SESSION['admin_id']
            ]);

            $pdo->commit();

            logActivity($_SESSION['admin_id'], 'Imported CSV Data', "Type: $type, Imported: $imported, Updated: $updated");
            echo json_encode([
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
