<?php
/**
 * 707 GYM – Today's Attendance
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db       = Database::getConnection();
$search   = trim($_GET['search']  ?? '');
$attendanceUrl = BASE_URL . '/pages/attendance/index.php'
    . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token mismatch. Please try again.');
        redirect($attendanceUrl);
    }

    if (isset($_POST['checkin_member_id'])) {
        $memberId = filter_var($_POST['checkin_member_id'], FILTER_VALIDATE_INT);
        if (!$memberId || $memberId < 1) {
            setFlash('error', 'Select a valid member to check in.');
            redirect($attendanceUrl);
        }

        $member = $db->prepare("SELECT first_name, last_name FROM members WHERE id = :id AND status = 'active'");
        $member->execute([':id' => $memberId]);
        $member = $member->fetch();

        if (!$member) {
            setFlash('error', 'That member is unavailable or archived.');
            redirect($attendanceUrl);
        }

        try {
            $checkin = $db->prepare(
                "INSERT INTO attendance (member_id, check_in, date)
                 VALUES (:member_id, NOW(), CURDATE())"
            );
            $checkin->execute([':member_id' => $memberId]);
            logActivity(
                $_SESSION['admin_id'],
                'attendance.checkin',
                "Checked in {$member['first_name']} {$member['last_name']} (member {$memberId})"
            );
            setFlash('success', 'Check-in recorded for ' . $member['first_name'] . ' ' . $member['last_name'] . '.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                setFlash('warning', 'This member already has an attendance record today.');
            } else {
                throw $e;
            }
        }
        redirect($attendanceUrl);
    }

    if (isset($_POST['checkout_id'])) {
        $attId = filter_var($_POST['checkout_id'], FILTER_VALIDATE_INT);
        if (!$attId || $attId < 1) {
            setFlash('error', 'Invalid attendance record.');
            redirect($attendanceUrl);
        }

        $checkout = $db->prepare(
            "UPDATE attendance SET check_out = NOW()
             WHERE id = :id AND date = CURDATE() AND check_out IS NULL"
        );
        $checkout->execute([':id' => $attId]);

        if ($checkout->rowCount() === 1) {
            logActivity($_SESSION['admin_id'], 'attendance.checkout', "Attendance ID {$attId}");
            setFlash('success', 'Check-out recorded.');
        } else {
            setFlash('warning', 'This attendance record was already checked out or is no longer available.');
        }
        redirect($attendanceUrl);
    }
}

$availableMembers = $db->query(
    "SELECT m.id, m.first_name, m.last_name, m.phone
     FROM members m
     WHERE m.status = 'active'
       AND NOT EXISTS (
           SELECT 1 FROM attendance a
           WHERE a.member_id = m.id AND a.date = CURDATE()
       )
     ORDER BY m.first_name, m.last_name"
)->fetchAll();

$where  = ["a.date = CURDATE()"];
$params = [];

if ($search !== '') {
    $where[]  = "(m.first_name LIKE :first_name OR m.last_name LIKE :last_name OR m.phone LIKE :phone)";
    $searchTerm = "%{$search}%";
    $params[':first_name'] = $searchTerm;
    $params[':last_name'] = $searchTerm;
    $params[':phone'] = $searchTerm;
}
$whereSQL = implode(' AND ', $where);

$records = $db->prepare(
    "SELECT a.id, a.check_in, a.check_out,
            m.first_name, m.last_name
     FROM attendance a
     JOIN members m ON a.member_id = m.id
     WHERE {$whereSQL}
     ORDER BY a.check_in DESC"
);
$records->execute($params);
$records = $records->fetchAll();

$pageTitle = "Today's Attendance";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Today's Attendance</h1>
        <p><?= date('F j, Y') ?> &bull; <?= count($records) ?> check-in<?= count($records)!==1?'s':'' ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/attendance/history.php" class="btn btn-secondary">
            <i data-lucide="history"></i> History
        </a>
    </div>
</div>

<section class="card attendance-checkin-card" aria-labelledby="attendanceCheckinTitle">
    <div class="attendance-checkin-copy">
        <span class="attendance-checkin-icon"><i data-lucide="user-check" aria-hidden="true"></i></span>
        <div>
            <h2 id="attendanceCheckinTitle">Check in a member</h2>
            <p>Select an active member to record today's visit.</p>
        </div>
    </div>
    <?php if ($availableMembers): ?>
    <form method="POST" class="attendance-checkin-form">
        <?= csrfField() ?>
        <label for="checkinMember">Member</label>
        <select id="checkinMember" name="checkin_member_id" required>
            <option value="">Select a member</option>
            <?php foreach ($availableMembers as $member): ?>
            <option value="<?= (int)$member['id'] ?>">
                <?= sanitize($member['first_name'] . ' ' . $member['last_name']) ?><?= $member['phone'] ? ' · ' . sanitize($member['phone']) : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="log-in" aria-hidden="true"></i> Check in
        </button>
    </form>
    <?php else: ?>
    <p class="attendance-checkin-empty">All active members have already checked in today, or there are no active members yet.</p>
    <?php endif; ?>
</section>

<form method="GET" class="filter-bar">
    <input type="search" name="search" placeholder="Search member..." value="<?= sanitize($search) ?>">
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="<?= BASE_URL ?>/pages/attendance/index.php" class="btn btn-ghost">Clear</a>
</form>

<div class="card">
    <?php if (empty($records)): ?>
    <div class="empty-state">
        <i data-lucide="calendar-x"></i>
        <h3>No attendance recorded today</h3>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Member</th><th>Check-in</th><th>Check-out</th><th>Duration</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($records as $rec): ?>
                <?php
                    $duration = '';
                    if ($rec['check_out']) {
                        $mins = round((strtotime($rec['check_out']) - strtotime($rec['check_in'])) / 60);
                        $duration = $mins . ' min' . ($mins !== 1 ? 's' : '');
                    }
                ?>
                <tr>
                    <td><?= sanitize($rec['first_name'].' '.$rec['last_name']) ?></td>
                    <td><?= date('g:i A', strtotime($rec['check_in'])) ?></td>
                    <td>
                        <?= $rec['check_out'] ? date('g:i A', strtotime($rec['check_out'])) : '<span class="badge badge-warning">Still In</span>' ?>
                    </td>
                    <td><?= $duration ?: '—' ?></td>
                    <td>
                        <?php if (!$rec['check_out']): ?>
                        <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="checkout_id" value="<?= (int)$rec['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <i data-lucide="log-out"></i> Check-out
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:13px;">Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
