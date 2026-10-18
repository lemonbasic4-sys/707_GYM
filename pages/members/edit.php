<?php
/**
 * 707 GYM – Edit Member
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db = Database::getConnection();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid member ID.');
    redirect(BASE_URL . '/pages/members/index.php');
}

// Load member
$stmt = $db->prepare("SELECT * FROM members WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$member = $stmt->fetch();

if (!$member) {
    setFlash('error', 'Member not found.');
    redirect(BASE_URL . '/pages/members/index.php');
}

$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch.';
    } else {
        $fields = [
            'first_name'              => trim($_POST['first_name']  ?? ''),
            'last_name'               => trim($_POST['last_name']   ?? ''),
            'email'                   => trim($_POST['email']       ?? ''),
            'phone'                   => trim($_POST['phone']       ?? ''),
            'address'                 => trim($_POST['address']     ?? ''),
            'gender'                  => $_POST['gender']           ?? '',
            'date_of_birth'           => $_POST['date_of_birth']    ?? '',
            'emergency_contact_name'  => trim($_POST['emergency_contact_name']  ?? ''),
            'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?? ''),
            'status'                  => in_array($_POST['status'] ?? '', ['active','archived']) ? $_POST['status'] : 'active',
        ];

        if (empty($fields['first_name'])) $errors[] = 'First name is required.';
        if (empty($fields['last_name']))  $errors[] = 'Last name is required.';

        // Optional new photo
        $photoFilename = $member['photo'];
        if (!empty($_FILES['photo']['name'])) {
            $file = $_FILES['photo'];
            if (!in_array($file['type'], ALLOWED_IMAGES)) {
                $errors[] = 'Photo must be JPG, PNG, WEBP, or GIF.';
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = 'Photo must not exceed 5 MB.';
            } else {
                $ext           = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newPhoto      = 'members/' . uniqid('m_', true) . '.' . $ext;
                $destDir       = UPLOAD_PATH . '/members';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                if (move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $newPhoto)) {
                    $photoFilename = $newPhoto;
                } else {
                    $errors[] = 'Failed to upload photo.';
                }
            }
        }

        if (empty($errors)) {
            $upd = $db->prepare(
                "UPDATE members SET
                    first_name = :first_name,
                    last_name  = :last_name,
                    email      = :email,
                    phone      = :phone,
                    address    = :address,
                    gender     = :gender,
                    date_of_birth = :dob,
                    photo      = :photo,
                    emergency_contact_name  = :ec_name,
                    emergency_contact_phone = :ec_phone,
                    status     = :status,
                    updated_at = NOW()
                 WHERE id = :id"
            );
            $upd->execute([
                ':first_name' => $fields['first_name'],
                ':last_name'  => $fields['last_name'],
                ':email'      => $fields['email']   ?: null,
                ':phone'      => $fields['phone']   ?: null,
                ':address'    => $fields['address'] ?: null,
                ':gender'     => $fields['gender'],
                ':dob'        => $fields['date_of_birth'] ?: null,
                ':photo'      => $photoFilename,
                ':ec_name'    => $fields['emergency_contact_name']  ?: null,
                ':ec_phone'   => $fields['emergency_contact_phone'] ?: null,
                ':status'     => $fields['status'],
                ':id'         => $id,
            ]);

            logActivity($_SESSION['admin_id'], 'member.edit', "Updated member ID {$id}");
            setFlash('success', 'Member updated successfully.');
            redirect(BASE_URL . '/pages/members/view.php?id=' . $id);
        }
    }
    rotateCsrfToken();
    // Merge submitted values over $member for re-display
    $member = array_merge($member, $_POST);
}

$pageTitle = 'Edit Member';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Edit Member</h1>
        <p><?= sanitize($member['first_name'] . ' ' . $member['last_name']) ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/members/view.php?id=<?= $id ?>" class="btn btn-ghost">
            <i data-lucide="arrow-left"></i> Back
        </a>
    </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= sanitize($err) ?></div>
<?php endforeach; ?>

<div class="card">
    <form method="POST" action="" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>

        <section class="member-form-section">
            <div class="member-form-section-header">
                <span class="member-section-icon"><i data-lucide="user-round"></i></span>
                <div><h2>Personal Information</h2><p>Basic details about the member.</p></div>
            </div>
            <div class="member-form-grid">
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="user-round"></i></span>
                    <div class="member-field-content">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" value="<?= sanitize($member['first_name']) ?>" required>
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="user-round"></i></span>
                    <div class="member-field-content">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" value="<?= sanitize($member['last_name']) ?>" required>
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="mail"></i></span>
                    <div class="member-field-content">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= sanitize($member['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="phone"></i></span>
                    <div class="member-field-content">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?= sanitize($member['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="users-round"></i></span>
                    <div class="member-field-content">
                        <label>Gender <span class="required">*</span></label>
                        <select name="gender">
                            <option value="male"   <?= $member['gender'] === 'male'   ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $member['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other"  <?= $member['gender'] === 'other'  ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="calendar-days"></i></span>
                    <div class="member-field-content">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?= sanitize($member['date_of_birth'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </section>

        <section class="member-form-section">
            <div class="member-form-section-header">
                <span class="member-section-icon"><i data-lucide="map-pin"></i></span>
                <div><h2>Address Information</h2><p>Complete address of the member.</p></div>
            </div>
            <div class="member-field">
                <span class="member-field-icon"><i data-lucide="map-pin"></i></span>
                <div class="member-field-content">
                    <label>Address</label>
                    <textarea name="address" rows="2"><?= sanitize($member['address'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <section class="member-form-section">
            <div class="member-form-section-header">
                <span class="member-section-icon"><i data-lucide="credit-card"></i></span>
                <div><h2>Member Status</h2><p>Current status of the member.</p></div>
            </div>
            <div class="member-form-grid member-form-grid-single">
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="badge-check"></i></span>
                    <div class="member-field-content">
                        <label>Status</label>
                        <select name="status">
                            <option value="active"   <?= $member['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                            <option value="archived" <?= $member['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="member-form-section">
            <div class="member-form-section-header">
                <span class="member-section-icon"><i data-lucide="image"></i></span>
                <div><h2>Profile Photo</h2><p>Upload or update the member's photo.</p></div>
            </div>
            <div class="member-field">
                <span class="member-field-icon"><i data-lucide="image"></i></span>
                <div class="member-field-content">
                    <label>Update Photo</label>
                    <?php if ($member['photo']): ?>
                    <img class="member-current-photo" src="<?= UPLOAD_URL . '/' . sanitize($member['photo']) ?>" alt="Current photo">
                    <?php endif; ?>
                    <input type="file" name="photo" accept="image/*">
                    <span class="form-hint">Leave blank to keep current photo.</span>
                </div>
            </div>
        </section>

        <section class="member-form-section">
            <div class="member-form-section-header">
                <span class="member-section-icon"><i data-lucide="phone"></i></span>
                <div><h2>Emergency Contact</h2><p>Contact person in case of emergency.</p></div>
            </div>
            <div class="member-form-grid">
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="user-round"></i></span>
                    <div class="member-field-content">
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" value="<?= sanitize($member['emergency_contact_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="phone"></i></span>
                    <div class="member-field-content">
                        <label>Emergency Contact Phone</label>
                        <input type="tel" name="emergency_contact_phone" value="<?= sanitize($member['emergency_contact_phone'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </section>

        <div style="display:flex; gap:10px; margin-top:24px;">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save"></i> Save Changes
            </button>
            <a href="<?= BASE_URL ?>/pages/members/view.php?id=<?= $id ?>" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
