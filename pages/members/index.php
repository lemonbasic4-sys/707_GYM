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
$sort      = $_GET['sort']          ?? 'first_name';
$dir       = strtoupper($_GET['dir'] ?? 'ASC') === 'ASC' ? 'ASC' : 'DESC';
$page      = max(1, (int) ($_GET['page'] ?? 1));

$allowedSorts = ['first_name', 'last_name', 'created_at'];
if (!in_array($sort, $allowedSorts, true)) $sort = 'first_name';

// ── Build WHERE ───────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = "(m.first_name LIKE :first_name OR m.last_name LIKE :last_name OR m.phone LIKE :phone)";
    $searchTerm = "%{$search}%";
    $params[':first_name'] = $searchTerm;
    $params[':last_name'] = $searchTerm;
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
    "SELECT m.id, m.first_name, m.last_name, m.phone, m.photo,
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
    <input type="search" name="search" placeholder="Search name or phone..."
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
                    <th style="width:44px;"></th><!-- avatar col -->
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
                <?php
                    $fullName  = sanitize($m['first_name'] . ' ' . $m['last_name']);
                    $initials  = strtoupper(substr($m['first_name'], 0, 1) . substr($m['last_name'], 0, 1));
                    $photoUrl  = $m['photo'] ? UPLOAD_URL . '/' . sanitize($m['photo']) : null;
                    $profileUrl = BASE_URL . '/pages/members/view.php?id=' . (int)$m['id'];
                ?>
                <tr>
                    <!-- Avatar -->
                    <td style="padding-right:0;">
                        <?php if ($photoUrl): ?>
                        <img src="<?= $photoUrl ?>" alt="<?= $fullName ?>"
                             class="member-list-avatar"
                             data-photo="<?= $photoUrl ?>"
                             data-name="<?= $fullName ?>"
                             title="View photo">
                        <?php else: ?>
                        <div class="member-list-initials" title="<?= $fullName ?>">
                            <?= $initials ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <!-- Name -->
                    <td>
                        <a href="<?= $profileUrl ?>">
                            <?= $fullName ?>
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
                            <a href="<?= $profileUrl ?>" class="btn btn-ghost btn-sm" title="View">
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

<!-- ── Photo Lightbox ─────────────────────────────────────── -->
<div class="modal-overlay" id="photoLightbox" role="dialog" aria-modal="true" aria-label="Member photo">
    <div class="modal" style="max-width:400px; text-align:center; padding:20px;">
        <div class="modal-header" style="margin-bottom:14px;">
            <span class="modal-title" id="lightboxName"></span>
            <button class="modal-close" id="lightboxClose" type="button" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>
        <img id="lightboxImg" src="" alt=""
             style="width:100%; max-height:360px; object-fit:contain; border-radius:var(--radius-sm);">
        <div style="margin-top:14px;">
            <a id="lightboxProfileLink" href="#" class="btn btn-primary btn-sm">
                <i data-lucide="user"></i> View Profile
            </a>
        </div>
    </div>
</div>

<style>
/* ── Member list avatar ── */
.member-list-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border);
    cursor: pointer;
    transition: border-color 0.15s, transform 0.15s;
    display: block;
}
.member-list-avatar:hover {
    border-color: var(--accent);
    transform: scale(1.08);
}
.member-list-initials {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(255,77,0,0.13);
    color: var(--accent);
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255,77,0,0.2);
    letter-spacing: 0.5px;
    flex-shrink: 0;
}
</style>

<script>
/* ── Photo Lightbox ─────────────────────────────────────── */
(function () {
    const overlay    = document.getElementById('photoLightbox');
    const img        = document.getElementById('lightboxImg');
    const nameEl     = document.getElementById('lightboxName');
    const closeBtn   = document.getElementById('lightboxClose');
    const profileLnk = document.getElementById('lightboxProfileLink');

    document.querySelectorAll('.member-list-avatar').forEach(avatar => {
        avatar.addEventListener('click', () => {
            img.src          = avatar.dataset.photo;
            nameEl.textContent = avatar.dataset.name;
            // find the profile link from the same row
            const row = avatar.closest('tr');
            const profileHref = row?.querySelector('a[href*="view.php"]')?.getAttribute('href') ?? '#';
            profileLnk.href  = profileHref;
            openModal('photoLightbox');
        });
    });

    closeBtn.addEventListener('click', () => closeModal('photoLightbox'));
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

