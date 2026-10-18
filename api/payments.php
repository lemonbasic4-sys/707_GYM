<?php
/**
 * 707 GYM - Payments API Endpoint
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
    case 'summary':
        $stmt = $pdo->query("SELECT 
            COALESCE(SUM(amount), 0) as total_revenue,
            COUNT(*) as total_transactions,
            COALESCE(SUM(CASE WHEN payment_date = CURRENT_DATE() THEN amount ELSE 0 END), 0) as today_revenue
            FROM payments WHERE status = 'paid'");
        echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
        break;

    case 'record':
        $memberId = (int)($_POST['member_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $method = trim($_POST['payment_method'] ?? 'cash');
        $notes = trim($_POST['notes'] ?? '');

        if (!$memberId || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Valid member and amount required']);
            exit;
        }

        $txId = 'TXN-' . strtoupper(uniqid());
        $ins = $pdo->prepare("INSERT INTO payments (transaction_id, member_id, amount, payment_method, notes, recorded_by, payment_date, status)
                             VALUES (:tx, :m, :a, :meth, :not, :adm, CURRENT_DATE(), 'paid')");
        $ins->execute([
            ':tx' => $txId,
            ':m' => $memberId,
            ':a' => $amount,
            ':meth' => $method,
            ':not' => $notes,
            ':adm' => $_SESSION['admin_id']
        ]);

        logActivity($_SESSION['admin_id'], 'Recorded Payment', "TxID: $txId, Amount: $amount");
        echo json_encode(['success' => true, 'message' => 'Payment recorded successfully', 'transaction_id' => $txId]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
