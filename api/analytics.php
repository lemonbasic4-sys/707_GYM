<?php
/**
 * 707 GYM - Analytics API Endpoint
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
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'revenue_trend':
        // Last 12 months revenue
        $labels = [];
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthDate = date('Y-m', strtotime("-$i months"));
            $monthLabel = date('M Y', strtotime("-$i months"));
            $labels[] = $monthLabel;

            $sql = "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid' AND DATE_FORMAT(payment_date, '%Y-%m') = :m";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':m' => $monthDate]);
            $data[] = (float)$stmt->fetchColumn();
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Monthly Collections (₱)',
                        'data' => $data,
                        'borderColor' => '#FF4D00',
                        'backgroundColor' => 'rgba(255, 61, 0, 0.1)',
                        'fill' => true,
                        'tension' => 0.35
                    ]
                ]
            ]
        ]);
        break;

    case 'membership_growth':
        $labels = [];
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = date('Y-m', strtotime("-$i months"));
            $monthLabel = date('M Y', strtotime("-$i months"));
            $labels[] = $monthLabel;

            $sql = "SELECT COUNT(*) FROM members WHERE DATE_FORMAT(created_at, '%Y-%m') = :m";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':m' => $monthDate]);
            $data[] = (int)$stmt->fetchColumn();
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'New Members',
                        'data' => $data,
                        'borderColor' => '#10b981',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'fill' => true,
                        'tension' => 0.35
                    ]
                ]
            ]
        ]);
        break;

    case 'plan_distribution':
        $sql = "SELECT p.name, COUNT(m.id) as cnt
                FROM membership_plans p
                LEFT JOIN memberships m ON p.id = m.plan_id ";
        $sql .= " GROUP BY p.id ORDER BY cnt DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $labels = [];
        $counts = [];
        foreach ($rows as $r) {
            $labels[] = $r['name'];
            $counts[] = (int)$r['cnt'];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'data' => $counts
            ]
        ]);
        break;

    case 'attendance_trend':
        // Last 14 days
        $labels = [];
        $data = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime("-$i days"));

            $sql = "SELECT COUNT(*) FROM attendance WHERE date = :d";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':d' => $date]);
            $data[] = (int)$stmt->fetchColumn();
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Daily Visits',
                        'data' => $data,
                        'borderColor' => '#3b82f6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'fill' => true,
                        'tension' => 0.35
                    ]
                ]
            ]
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
