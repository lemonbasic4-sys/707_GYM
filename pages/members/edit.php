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
            'phone'                   => trim($_POST['phone']       ?? ''),
            'gender'                  => $_POST['gender']           ?? '',
            'date_of_birth'           => $_POST['date_of_birth']    ?? '',
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
        } elseif (!empty($_POST['photo_base64'])) {
            $dataUri = $_POST['photo_base64'];
            if (preg_match('/^data:image\/(png|jpeg|webp);base64,(.+)$/i', $dataUri, $matches)) {
                $imgData = base64_decode($matches[2], true);
                if ($imgData === false || strlen($imgData) > MAX_FILE_SIZE) {
                    $errors[] = 'Captured photo is invalid or exceeds 5 MB.';
                } else {
                    $ext = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
                    $newPhoto = 'members/' . uniqid('m_', true) . '.' . $ext;
                    $destDir = UPLOAD_PATH . '/members';
                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                    if (file_put_contents(UPLOAD_PATH . '/' . $newPhoto, $imgData) === false) {
                        $errors[] = 'Failed to save captured photo.';
                    } else {
                        $photoFilename = $newPhoto;
                    }
                }
            } else {
                $errors[] = 'Invalid camera capture format.';
            }
        }

        if (empty($errors)) {
            $upd = $db->prepare(
                "UPDATE members SET
                    first_name = :first_name,
                    last_name  = :last_name,
                    phone      = :phone,
                    gender     = :gender,
                    date_of_birth = :dob,
                    photo      = :photo,
                    status     = :status,
                    updated_at = NOW()
                 WHERE id = :id"
            );
            $upd->execute([
                ':first_name' => $fields['first_name'],
                ':last_name'  => $fields['last_name'],
                ':phone'      => $fields['phone']   ?: null,
                ':gender'     => $fields['gender'],
                ':dob'        => $fields['date_of_birth'] ?: null,
                ':photo'      => $photoFilename,
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
                <div><h2>Profile Photo</h2><p>Upload a photo or capture one with the camera.</p></div>
            </div>
            <div class="member-field">
                <span class="member-field-icon"><i data-lucide="image"></i></span>
                <div class="member-field-content">
                    <label>Update Photo</label>
                    <?php if ($member['photo']): ?>
                    <img class="member-current-photo" src="<?= UPLOAD_URL . '/' . sanitize($member['photo']) ?>" alt="Current photo">
                    <?php endif; ?>
                    <input type="file" id="editPhotoFile" name="photo" accept="image/*">
                    <input type="hidden" id="editPhotoCapture" name="photo_base64">
                    <button type="button" class="btn btn-secondary" id="editOpenCameraBtn" style="margin-top:10px;">
                        <i data-lucide="camera"></i> Open Camera
                    </button>
                    <div id="editPhotoPreview" hidden style="margin-top:10px;">
                        <img id="editPhotoPreviewImage" src="" alt="New photo preview" class="member-current-photo">
                        <button type="button" class="btn btn-ghost btn-sm" id="editClearCameraBtn">Discard captured photo</button>
                    </div>
                    <span class="form-hint">Leave blank to keep current photo.</span>
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

<div class="modal-overlay" id="editCameraModal">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <span class="modal-title"><i data-lucide="camera"></i> Capture Member Photo</span>
            <button class="modal-close" id="editCloseCameraBtn" type="button" aria-label="Close camera"><i data-lucide="x"></i></button>
        </div>
        <div style="position:relative;background:#000;border-radius:var(--radius-sm);overflow:hidden;aspect-ratio:4/3;">
            <video id="editCameraFeed" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;display:block;"></video>
            <canvas id="editCameraCanvas" hidden></canvas>
            <img id="editCameraSnapshot" src="" alt="Captured photo" hidden style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
        </div>
        <p id="editCameraStatus" style="font-size:12px;color:var(--muted);margin-top:8px;min-height:18px;"></p>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" id="editRetakeBtn" hidden>Retake</button>
            <button type="button" class="btn btn-primary" id="editCaptureBtn"><i data-lucide="aperture"></i> Capture</button>
            <button type="button" class="btn btn-primary" id="editUsePhotoBtn" hidden>Use Photo</button>
        </div>
    </div>
</div>

<script>
(() => {
    const modal = document.getElementById('editCameraModal');
    const video = document.getElementById('editCameraFeed');
    const canvas = document.getElementById('editCameraCanvas');
    const snapshot = document.getElementById('editCameraSnapshot');
    const status = document.getElementById('editCameraStatus');
    const capture = document.getElementById('editCaptureBtn');
    const retake = document.getElementById('editRetakeBtn');
    const use = document.getElementById('editUsePhotoBtn');
    const data = document.getElementById('editPhotoCapture');
    const file = document.getElementById('editPhotoFile');
    const preview = document.getElementById('editPhotoPreview');
    const previewImage = document.getElementById('editPhotoPreviewImage');
    let stream = null;
    let photo = null;

    function stopCamera() {
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
    }
    function closeCamera() {
        stopCamera();
        closeModal('editCameraModal');
    }
    document.getElementById('editOpenCameraBtn').addEventListener('click', async () => {
        openModal('editCameraModal');
        photo = null;
        snapshot.hidden = true;
        video.hidden = false;
        capture.hidden = false;
        capture.disabled = false;
        retake.hidden = true;
        use.hidden = true;
        status.style.color = 'var(--muted)';
        status.textContent = 'Requesting camera access…';
        try {
            stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user'}, audio: false});
            if (!modal.classList.contains('open')) { stopCamera(); return; }
            video.srcObject = stream;
            status.textContent = 'Camera ready. Position the member and press Capture.';
        } catch (error) {
            capture.disabled = true;
            status.style.color = '#ef4444';
            status.textContent = 'Camera access denied or unavailable: ' + error.message;
        }
    });
    capture.addEventListener('click', () => {
        if (!stream || !video.videoWidth) return;
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        photo = canvas.toDataURL('image/png');
        snapshot.src = photo;
        snapshot.hidden = false;
        video.hidden = true;
        capture.hidden = true;
        retake.hidden = false;
        use.hidden = false;
        status.textContent = 'Photo captured. Choose Use Photo to apply it.';
    });
    retake.addEventListener('click', () => {
        photo = null;
        snapshot.hidden = true;
        video.hidden = false;
        capture.hidden = false;
        retake.hidden = true;
        use.hidden = true;
    });
    use.addEventListener('click', () => {
        if (!photo) return;
        data.value = photo;
        file.value = '';
        previewImage.src = photo;
        preview.hidden = false;
        closeCamera();
    });
    file.addEventListener('change', () => {
        if (file.files.length) {
            data.value = '';
            preview.hidden = true;
        }
    });
    document.getElementById('editClearCameraBtn').addEventListener('click', () => {
        data.value = '';
        preview.hidden = true;
    });
    document.getElementById('editCloseCameraBtn').addEventListener('click', closeCamera);
    modal.addEventListener('click', event => { if (event.target === modal) closeCamera(); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal.classList.contains('open')) closeCamera();
    });
    window.addEventListener('beforeunload', stopCamera);
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
