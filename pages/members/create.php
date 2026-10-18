<?php
/**
 * 707 GYM – Create Member
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db      = Database::getConnection();
$errors  = [];

// ── POST Handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        $fields = [
            'first_name'                => trim($_POST['first_name']  ?? ''),
            'last_name'                 => trim($_POST['last_name']   ?? ''),
            'email'                     => trim($_POST['email']       ?? ''),
            'phone'                     => trim($_POST['phone']       ?? ''),
            'address'                   => trim($_POST['address']     ?? ''),
            'gender'                    => $_POST['gender']           ?? '',
            'date_of_birth'             => $_POST['date_of_birth']    ?? '',
            'emergency_contact_name'    => trim($_POST['emergency_contact_name']  ?? ''),
            'emergency_contact_phone'   => trim($_POST['emergency_contact_phone'] ?? ''),
        ];

        if (empty($fields['first_name']))  $errors[] = 'First name is required.';
        if (empty($fields['last_name']))   $errors[] = 'Last name is required.';
        if (!in_array($fields['gender'], ['male','female','other'], true)) $errors[] = 'Please select a valid gender.';

        // Photo upload
        $photoFilename = null;
        if (!empty($_FILES['photo']['name'])) {
            $file = $_FILES['photo'];
            if (!in_array($file['type'], ALLOWED_IMAGES)) {
                $errors[] = 'Photo must be JPG, PNG, WEBP, or GIF.';
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = 'Photo must not exceed 5 MB.';
            } else {
                $ext           = pathinfo($file['name'], PATHINFO_EXTENSION);
                $photoFilename = 'members/' . uniqid('m_', true) . '.' . $ext;
                $destDir       = UPLOAD_PATH . '/members';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $photoFilename)) {
                    $errors[] = 'Failed to upload photo.';
                    $photoFilename = null;
                }
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $db->prepare(
                    "INSERT INTO members
                        (first_name, last_name, email, phone, address, gender,
                         date_of_birth, photo, emergency_contact_name, emergency_contact_phone,
                         status, created_at, updated_at)
                     VALUES
                        (:first_name, :last_name, :email, :phone, :address, :gender,
                         :dob, :photo, :ec_name, :ec_phone,
                         'active', NOW(), NOW())"
                );
                $stmt->execute([
                    ':first_name'=> $fields['first_name'],
                    ':last_name' => $fields['last_name'],
                    ':email'     => $fields['email']   ?: null,
                    ':phone'     => $fields['phone']   ?: null,
                    ':address'   => $fields['address'] ?: null,
                    ':gender'    => $fields['gender'],
                    ':dob'       => $fields['date_of_birth'] ?: null,
                    ':photo'     => $photoFilename,
                    ':ec_name'   => $fields['emergency_contact_name']  ?: null,
                    ':ec_phone'  => $fields['emergency_contact_phone'] ?: null,
                ]);

                $newId = (int) $db->lastInsertId();
                logActivity($_SESSION['admin_id'], 'member.create', "Created member {$fields['first_name']} {$fields['last_name']} (record {$newId})");

                setFlash('success', 'Member registered successfully.');
                redirect(BASE_URL . '/pages/members/view.php?id=' . $newId);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'A member with this email already exists.';
                } else {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
    rotateCsrfToken();
}

$pageTitle = 'Register Member';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Register New Member</h1>
        <p>Fill in the details below to add a new gym member.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/pages/members/index.php" class="btn btn-ghost">
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
                        <input type="text" name="first_name" value="<?= sanitize($_POST['first_name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="user-round"></i></span>
                    <div class="member-field-content">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" value="<?= sanitize($_POST['last_name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="mail"></i></span>
                    <div class="member-field-content">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= sanitize($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="phone"></i></span>
                    <div class="member-field-content">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?= sanitize($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="users-round"></i></span>
                    <div class="member-field-content">
                        <label>Gender <span class="required">*</span></label>
                        <select name="gender" required>
                            <option value="">Select gender</option>
                            <option value="male"   <?= ($_POST['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other"  <?= ($_POST['gender'] ?? '') === 'other'  ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="calendar-days"></i></span>
                    <div class="member-field-content">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?= sanitize($_POST['date_of_birth'] ?? '') ?>">
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
                    <textarea name="address" rows="2"><?= sanitize($_POST['address'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <section class="member-form-section">
            <div class="member-form-section-header">
                <span class="member-section-icon"><i data-lucide="image"></i></span>
                <div><h2>Profile Photo</h2><p>Upload a photo for the member profile.</p></div>
            </div>
            <div class="member-field">
                <span class="member-field-icon"><i data-lucide="image"></i></span>
                <div class="member-field-content">
                    <label>Member Photo</label>
                    <input type="file" name="photo" accept="image/*">
                    <span class="form-hint">JPG, PNG, WEBP, or GIF. Max 5 MB.</span>
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
                        <input type="text" name="emergency_contact_name" value="<?= sanitize($_POST['emergency_contact_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="member-field">
                    <span class="member-field-icon"><i data-lucide="phone"></i></span>
                    <div class="member-field-content">
                        <label>Emergency Contact Phone</label>
                        <input type="tel" name="emergency_contact_phone" value="<?= sanitize($_POST['emergency_contact_phone'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </section>

        <div class="member-form-actions">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="user-plus"></i> Register Member
            </button>
            <a href="<?= BASE_URL ?>/pages/members/index.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
