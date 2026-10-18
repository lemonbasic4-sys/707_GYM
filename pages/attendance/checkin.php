<?php
/**
 * 707 GYM – Quick Check-in
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db     = Database::getConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch.';
    } else {
        $memberId = (int)($_POST['member_id'] ?? 0);

        if ($memberId <= 0) $errors[] = 'Please select a member.';

        if (empty($errors)) {
            // Check for duplicate check-in today
            $dup = $db->prepare(
                "SELECT id FROM attendance WHERE member_id = :mid AND date = CURDATE() LIMIT 1"
            );
            $dup->execute([':mid' => $memberId]);
            if ($dup->fetch()) {
                $errors[] = 'This member has already checked in today.';
            } else {
                $db->prepare(
                    "INSERT INTO attendance (member_id, check_in, date, created_at)
                     VALUES (:mid, NOW(), CURDATE(), NOW())"
                )->execute([':mid' => $memberId]);

                logActivity($_SESSION['admin_id'], 'attendance.checkin', "Member {$memberId}");
                setFlash('success', 'Check-in recorded successfully.');
                redirect(BASE_URL . '/pages/attendance/index.php');
            }
        }
    }
    rotateCsrfToken();
}

$pageTitle = 'Quick Check-in';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Quick Check-in</h1>
        <p>Record a member's gym visit for today.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/attendance/index.php" class="btn btn-ghost"><i data-lucide="arrow-left"></i> Back</a>
    </div>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= sanitize($e) ?></div><?php endforeach; ?>

<div class="card" style="max-width:480px;">
    <form method="POST" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
            <label>Member <span class="required">*</span></label>
            <input type="text" id="memberSearch" placeholder="Type to search member..." autocomplete="off">
            <input type="hidden" name="member_id" id="memberIdHidden">
        </div>

        <div class="form-group">
            <label>Check-in Time</label>
            <input type="text" value="<?= date('F j, Y g:i A') ?>" readonly>
            <span class="form-hint">Current server time is used automatically.</span>
        </div>

        <div style="display:flex; gap:10px; margin-top:24px;">
            <button type="submit" class="btn btn-primary"><i data-lucide="log-in"></i> Record Check-in</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initMemberTypeahead('memberSearch', 'memberIdHidden', '<?= BASE_URL ?>/api/members.php');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
