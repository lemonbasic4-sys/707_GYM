<?php
/**
 * 707 GYM - Database Backup & Restore
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pdo = Database::getConnection();
$pageTitle = 'Database Backup & Restore';

$msg = '';
$err = '';

// Handle Backup download request
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    // Generate simple SQL dump
    $tables = ['admins', 'membership_plans', 'members', 'memberships', 'attendance', 'payments', 'import_logs', 'activity_logs'];
    $dump = "-- 707 GYM Database Backup\n";
    $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $dump .= $createStmt[1] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $dump .= "INSERT INTO `$table` VALUES \n";
            $rowEntries = [];
            foreach ($rows as $row) {
                $values = array_map(function($val) use ($pdo) {
                    return $val === null ? 'NULL' : $pdo->quote($val);
                }, array_values($row));
                $rowEntries[] = "(" . implode(', ', $values) . ")";
            }
            $dump .= implode(",\n", $rowEntries) . ";\n\n";
        }
    }
    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    logActivity($_SESSION['admin_id'], 'Downloaded Database Backup', 'gym_707 backup generated');

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="gym_707_backup_' . date('Ymd_His') . '.sql"');
    header('Content-Length: ' . strlen($dump));
    echo $dump;
    exit;
}

// Handle SQL restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } elseif (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $err = 'Please select a valid .sql backup file.';
    } else {
        $file = $_FILES['sql_file']['tmp_name'];
        $sqlContent = file_get_contents($file);
        
        try {
            $pdo->exec($sqlContent);
            logActivity($_SESSION['admin_id'], 'Restored Database Backup', $_FILES['sql_file']['name']);
            setFlash('success', 'Database restored successfully from backup.');
            redirect(BASE_URL . '/pages/admin/backup.php');
        } catch (Exception $e) {
            $err = 'Restore error: ' . $e->getMessage();
        }
    }
}

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Database Backup & Restore</h1>
            <p class="page-subtitle">Export full SQL dumps or restore the system state from a previous snapshot</p>
        </div>
    </div>

    <?php if ($err): ?>
        <div class="alert alert-danger mb-4"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <div class="cards-grid mb-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
        <div class="card">
            <h3 class="card-title mb-2">Create & Download Backup</h3>
            <p class="text-muted text-sm mb-4">Export the complete schema and all records as a portable SQL script.</p>
            <a href="?action=download_backup" class="btn btn-primary">
                <i data-lucide="download"></i> Download SQL Backup
            </a>
        </div>

        <div class="card">
            <h3 class="card-title mb-2">Restore from Backup</h3>
            <p class="text-danger text-sm mb-3">
                <i data-lucide="alert-triangle"></i> Warning: Restoring will overwrite existing tables and records with the file contents.
            </p>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="restore">
                <div class="form-group mb-3">
                    <input type="file" name="sql_file" class="form-control" accept=".sql" required>
                </div>
                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure you want to restore? Current data may be overwritten.')">
                    <i data-lucide="upload"></i> Upload & Restore
                </button>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
