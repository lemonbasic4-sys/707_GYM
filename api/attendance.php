<?php
/**
 * 707 GYM - Attendance API Endpoint
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
    case 'today':
        $sql = "SELECT a.*, m.first_name, m.last_name
                FROM attendance a 
                JOIN members m ON a.member_id = m.id 
                WHERE a.date = CURRENT_DATE() ";
        $sql .= " ORDER BY a.check_in DESC";
        $stmt = $pdo->query($sql);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'checkout':
        $attId = (int)($_POST['attendance_id'] ?? 0);
        if (!$attId) {
            echo json_encode(['success' => false, 'message' => 'Attendance record ID required']);
            exit;
        }

        $upd = $pdo->prepare("UPDATE attendance SET check_out = NOW() WHERE id = :id AND check_out IS NULL");
        $upd->execute([':id' => $attId]);
        logActivity($_SESSION['admin_id'], 'Attendance Check-out', "Attendance ID: $attId");
        echo json_encode(['success' => true, 'message' => 'Check-out recorded']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
