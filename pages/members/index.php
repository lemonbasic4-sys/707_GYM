<?php
/**
 * 707 GYM – Members List
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$db = Database::getConnection();

// ── Filters ──────────────────────────────────────────────────────────────────
$search    = trim($_GET['search']   ?? '');
$status    = $_GET['status']        ?? '';
$sort      = $_GET['sort']          ?? 'created_at';
$dir       = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page      = max(1, (int) ($_GET['page'] ?? 1));

$allowedSorts = ['first_name', 'created_at'];
if (!in_array($sort, $allowedSorts, true)) $sort = 'created_at';

// ── Build WHERE ───────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = "(m.first_name LIKE :first_name OR m.last_name LIKE :last_name OR m.email LIKE :email OR m.phone LIKE :phone)";
    $searchTerm = "%{$search}%";
    $params[':first_name'] = $searchTerm;
    $params[':last_name'] = $searchTerm;
    $params[':email'] = $searchTerm;
    $params[':phone'] = $searchTerm;
}
if (in_array($status, ['active', 'archived'], true)) {
    $where[]  = 'm.status = :status';
    $params[':status'] = $status;
}

$whereSQL = implode(' AND ', $where);

// ── Count ─────────────────────────────────────────────────────────────────────
$countStmt = $db->prepare("SELECT COUNT(*) FROM members m WHERE {$whereSQL}");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

$pagination = getPaginationData($totalItems, $page, DEFAULT_PER_PAGE);

// ── Fetch Members ─────────────────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT m.id, m.first_name, m.last_name, m.email, m.phone,
            m.status, m.created_at,
            ms.status AS membership_status,
            ms.end_date
     FROM members m
     LEFT JOIN memberships ms ON ms.id = (
         SELECT id FROM memberships WHERE member_id = m.id ORDER BY created_at DESC LIMIT 1
     )
     WHERE {$whereSQL}
     ORDER BY m.{$sort} {$dir}
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$members = $stmt->fetchAll();

$pageTitle = 'Members';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Members</h1>
        <p><?= number_format($totalItems) ?> total member<?= $totalItems !== 1 ? 's' : '' ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/members/create.php" class="btn btn-primary">
            <i data-lucide="user-plus"></i> Add Member
        </a>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="" class="filter-bar">
    <input type="search" name="search" placeholder="Search name, email, phone..."
           value="<?= sanitize($search) ?>">
    <select name="status">
        <option value="">All Statuses</option>
        <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="<?= BASE_URL ?>/pages/members/index.php" class="btn btn-ghost">Clear</a>
</form>

<!-- Table -->
<div class="card">
    <?php if (empty($members)): ?>
    <div class="empty-state">
        <i data-lucide="users"></i>
        <h3>No members found</h3>
        <p>Try adjusting your search or filters.</p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table id="membersTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Membership</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/pages/members/view.php?id=<?= (int)$m['id'] ?>">
                            <?= sanitize($m['first_name'] . ' ' . $m['last_name']) ?>
                        </a>
                    </td>
                    <td><?= sanitize($m['phone'] ?? '—') ?></td>
                    <td>
                        <?php if ($m['membership_status']): ?>
                        <span class="badge badge-<?= $m['membership_status'] === 'active' ? 'success' : ($m['membership_status'] === 'expired' ? 'danger' : 'muted') ?>">
                            <?= sanitize($m['membership_status']) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge badge-muted">None</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $m['end_date'] ? formatDate($m['end_date']) : '—' ?></td>
                    <td>
                        <span class="badge badge-<?= $m['status'] === 'active' ? 'success' : 'muted' ?>">
                            <?= sanitize($m['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="<?= BASE_URL ?>/pages/members/view.php?id=<?= (int)$m['id'] ?>" class="btn btn-ghost btn-sm" title="View">
                                <i data-lucide="eye"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/pages/members/edit.php?id=<?= (int)$m['id'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                                <i data-lucide="pencil"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination mt-2">
        <?php if ($pagination['has_prev']): ?>
        <a href="?page=<?= $pagination['current_page'] - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="page-link">
            <i data-lucide="chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
           class="page-link <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        <?php if ($pagination['has_next']): ?>
        <a href="?page=<?= $pagination['current_page'] + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="page-link">
            <i data-lucide="chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
