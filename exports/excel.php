<?php
/**
 * 707 GYM - Excel/CSV Export Generator
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pdo = Database::getConnection();
$type = $_GET['type'] ?? 'members';
if (!in_array($type, ['members', 'payments'], true)) {
    http_response_code(400);
    exit('Unsupported export type.');
}

$vendorAutoload = BASE_PATH . '/vendor/autoload.php';
$hasSpreadsheet = file_exists($vendorAutoload);

function paymentExportRows(PDO $pdo): array
{
    $where = ['1=1'];
    $params = [];
    $categories = ['walk_in_pass', 'membership_renewal', 'member_visit'];
    if (in_array($_GET['category'] ?? '', $categories, true)) {
        $where[] = 'p.transaction_type = :category';
        $params[':category'] = $_GET['category'];
    }
    if (!empty($_GET['method'])) { $where[] = 'p.payment_method = :method'; $params[':method'] = $_GET['method']; }
    if (!empty($_GET['date_from'])) { $where[] = 'p.payment_date >= :date_from'; $params[':date_from'] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = 'p.payment_date <= :date_to'; $params[':date_to'] = $_GET['date_to']; }
    if (!empty($_GET['search'])) {
        $where[] = "(m.first_name LIKE :first OR m.last_name LIKE :last OR p.visitor_name LIKE :visitor OR p.transaction_id LIKE :txn)";
        foreach ([':first', ':last', ':visitor', ':txn'] as $key) $params[$key] = '%' . $_GET['search'] . '%';
    }
    $stmt = $pdo->prepare("SELECT p.transaction_id, p.transaction_type, p.payment_date,
        COALESCE(p.visitor_name, CONCAT(m.first_name, ' ', m.last_name)), p.amount, p.payment_method, p.status
        FROM payments p LEFT JOIN members m ON p.member_id = m.id
        WHERE " . implode(' AND ', $where) . " ORDER BY p.payment_date DESC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_NUM);
}

// If PhpSpreadsheet exists and is autoloaded
if ($hasSpreadsheet && class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    if ($type === 'members') {
        $sheet->fromArray(['First Name', 'Last Name', 'Phone', 'Status'], NULL, 'A1');
        $members = $pdo->query("SELECT m.first_name, m.last_name, m.phone, m.status
                                FROM members m ORDER BY m.created_at DESC")->fetchAll(PDO::FETCH_NUM);
        $sheet->fromArray($members, NULL, 'A2');
    } elseif ($type === 'payments') {
        $sheet->fromArray(['Transaction ID', 'Type', 'Date', 'Name', 'Amount', 'Method', 'Status'], NULL, 'A1');
        $payments = paymentExportRows($pdo);
        $sheet->fromArray($payments, NULL, 'A2');
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="707_GYM_' . $type . '_' . date('Ymd_His') . '.xlsx"');
    $writer->save('php://output');
    exit;
} else {
    // Seamless CSV Fallback when composer package isn't installed yet
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="707_GYM_' . $type . '_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');

    if ($type === 'members') {
        fputcsv($output, ['First Name', 'Last Name', 'Phone', 'Status']);
        $members = $pdo->query("SELECT m.first_name, m.last_name, m.phone, m.status
                                FROM members m ORDER BY m.created_at DESC")->fetchAll(PDO::FETCH_NUM);
        foreach ($members as $m) {
            fputcsv($output, $m);
        }
    } elseif ($type === 'payments') {
        fputcsv($output, ['Transaction ID', 'Type', 'Date', 'Name', 'Amount', 'Method', 'Status']);
        $payments = paymentExportRows($pdo);
        foreach ($payments as $p) {
            fputcsv($output, $p);
        }
    }
    fclose($output);
    exit;
}
