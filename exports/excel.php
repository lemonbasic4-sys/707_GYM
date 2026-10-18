<?php
/**
 * 707 GYM - Excel/CSV Export Generator
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_analytics.php';

requireAuth();

$pdo = Database::getConnection();
$type = $_GET['type'] ?? 'members';

if ($type === 'branch_analytics') {
    $month = isset($_GET['month']) && is_string($_GET['month']) ? $_GET['month'] : date('Y-m');
    if (!isValidAnalyticsMonth($month)) {
        http_response_code(400);
        exit('Invalid analytics month.');
    }
    $branchName = getLocalBranchName($pdo);
    $metrics = getCurrentBranchAnalytics($pdo, $month);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $branchName . '_' . $month . '_analytics.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'branch_name', 'period_month', 'captured_at', 'revenue',
        'new_members', 'active_members', 'attendance_visits', 'active_memberships',
    ]);
    fputcsv($output, [
        $branchName, $month, date('Y-m-d H:i:s'), number_format($metrics['revenue'], 2, '.', ''),
        $metrics['new_members'], $metrics['active_members'], $metrics['attendance_visits'],
        $metrics['active_memberships'],
    ]);
    fclose($output);
    exit;
}

$vendorAutoload = BASE_PATH . '/vendor/autoload.php';
$hasSpreadsheet = file_exists($vendorAutoload);

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
        $sheet->fromArray(['Transaction ID', 'Type', 'Date', 'Member', 'Amount', 'Method', 'Status'], NULL, 'A1');
        $payments = $pdo->query("SELECT p.transaction_id, p.transaction_type, p.payment_date, CONCAT(m.first_name, ' ', m.last_name), p.amount, p.payment_method, p.status
                                 FROM payments p JOIN members m ON p.member_id = m.id ORDER BY p.payment_date DESC")->fetchAll(PDO::FETCH_NUM);
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
        fputcsv($output, ['Transaction ID', 'Type', 'Date', 'Member', 'Amount', 'Method', 'Status']);
        $payments = $pdo->query("SELECT p.transaction_id, p.transaction_type, p.payment_date, CONCAT(m.first_name, ' ', m.last_name), p.amount, p.payment_method, p.status
                                 FROM payments p JOIN members m ON p.member_id = m.id ORDER BY p.payment_date DESC")->fetchAll(PDO::FETCH_NUM);
        foreach ($payments as $p) {
            fputcsv($output, $p);
        }
    }
    fclose($output);
    exit;
}
