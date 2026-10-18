<?php
/**
 * 707 GYM - Members API Endpoint
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
    case 'search':
        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 1) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, phone, email
                               FROM members
                               WHERE status = 'active' AND (
                                   first_name LIKE :first_name OR
                                   last_name LIKE :last_name OR
                                   phone LIKE :phone OR
                                   email LIKE :email
                               ) LIMIT 10");
        $searchTerm = "%{$query}%";
        $stmt->execute([
            ':first_name' => $searchTerm,
            ':last_name' => $searchTerm,
            ':phone' => $searchTerm,
            ':email' => $searchTerm,
        ]);
        $members = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $members]);
        break;

    case 'list':
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'");
        $total = $stmtCount->fetchColumn();
        
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT m.*
                               FROM members m WHERE m.status = 'active'
                               ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $members = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $members,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ]
        ]);
        break;

    case 'archive':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE members SET status = 'archived' WHERE id = :id");
        $stmt->execute([':id' => $id]);
        logActivity($_SESSION['admin_id'], 'Archived Member', "Member ID: $id");
        echo json_encode(['success' => true, 'message' => 'Member archived']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
