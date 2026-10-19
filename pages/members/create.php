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
            'phone'                     => trim($_POST['phone']       ?? ''),
            'gender'                    => $_POST['gender']           ?? '',
            'date_of_birth'             => $_POST['date_of_birth']    ?? '',
            'emergency_contact_name'    => trim($_POST['emergency_contact_name']  ?? ''),
            'emergency_contact_phone'   => trim($_POST['emergency_contact_phone'] ?? ''),
        ];

        if (empty($fields['first_name']))  $errors[] = 'First name is required.';
        if (empty($fields['last_name']))   $errors[] = 'Last name is required.';
        if (!in_array($fields['gender'], ['male','female','other'], true)) $errors[] = 'Please select a valid gender.';

        // Photo upload – file input takes priority; fall back to webcam capture
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
        } elseif (!empty($_POST['photo_base64'])) {
            // Webcam capture: data:image/png;base64,<data>
            $dataUri = $_POST['photo_base64'];
            if (preg_match('/^data:image\/(png|jpeg|webp);base64,(.+)$/i', $dataUri, $m)) {
                $imgData = base64_decode($m[2], true);
                if ($imgData === false || strlen($imgData) > MAX_FILE_SIZE) {
                    $errors[] = 'Captured photo is invalid or exceeds 5 MB.';
                } else {
                    $ext           = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                    $photoFilename = 'members/' . uniqid('m_', true) . '.' . $ext;
                    $destDir       = UPLOAD_PATH . '/members';
                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                    if (file_put_contents(UPLOAD_PATH . '/' . $photoFilename, $imgData) === false) {
                        $errors[] = 'Failed to save captured photo.';
                        $photoFilename = null;
                    }
                }
            } else {
                $errors[] = 'Invalid camera capture format.';
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $db->prepare(
                    "INSERT INTO members
                        (first_name, last_name, phone, gender,
                         date_of_birth, photo, emergency_contact_name, emergency_contact_phone,
                         status, created_at, updated_at)
                     VALUES
                        (:first_name, :last_name, :phone, :gender,
                         :dob, :photo, :ec_name, :ec_phone,
                         'active', NOW(), NOW())"
                );
                $stmt->execute([
                    ':first_name'=> $fields['first_name'],
                    ':last_name' => $fields['last_name'],
                    ':phone'     => $fields['phone']   ?: null,
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
                    $errors[] = 'This member conflicts with an existing record.';
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
                <span class="member-section-icon"><i data-lucide="image"></i></span>
                <div><h2>Profile Photo</h2><p>Upload a photo or capture one live from the camera.</p></div>
            </div>
            <div class="member-field">
                <span class="member-field-icon"><i data-lucide="image"></i></span>
                <div class="member-field-content">
                    <label>Member Photo</label>

                    <!-- Live preview of captured / chosen photo -->
                    <div id="photoPreviewWrap" style="display:none; margin-bottom:8px;">
                        <img id="photoPreviewImg" src="" alt="Preview"
                             style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:2px solid var(--accent);">
                        <button type="button" id="clearPhotoBtn"
                                style="display:block;margin-top:4px;font-size:11px;color:var(--muted);background:none;border:none;cursor:pointer;padding:0;">
                            ✕ Remove photo
                        </button>
                    </div>

                    <!-- File upload (hidden when a camera capture exists) -->
                    <div id="fileUploadWrap">
                        <input type="file" id="photoFileInput" name="photo" accept="image/*">
                        <span class="form-hint">JPG, PNG, WEBP, or GIF. Max 5&nbsp;MB.</span>
                    </div>

                    <!-- Hidden base64 field for webcam capture -->
                    <input type="hidden" name="photo_base64" id="photoCaptureData">

                    <!-- Camera button -->
                    <button type="button" id="openCameraBtn" class="btn btn-ghost"
                            style="margin-top:8px; font-size:12px;">
                        <i data-lucide="camera"></i> Open Camera
                    </button>
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

<!-- ── Webcam Capture Modal ───────────────────────────────── -->
<div class="modal-overlay" id="cameraModal">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <span class="modal-title"><i data-lucide="camera" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Capture Member Photo</span>
            <button class="modal-close" id="closeCameraBtn" type="button" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>

        <!-- Live viewfinder -->
        <div style="position:relative; background:#000; border-radius:var(--radius-sm); overflow:hidden; aspect-ratio:4/3;">
            <video id="cameraFeed" autoplay playsinline muted
                   style="width:100%; height:100%; object-fit:cover; display:block;"></video>
            <canvas id="cameraCanvas" style="display:none;"></canvas>
            <!-- Overlay after capture -->
            <img id="cameraSnapshot" src="" alt="Snapshot"
                 style="display:none; position:absolute; inset:0; width:100%; height:100%; object-fit:cover;">
        </div>

        <!-- Status / error text -->
        <p id="cameraStatus" style="font-size:12px; color:var(--muted); margin-top:8px; min-height:18px;"></p>

        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" id="retakeBtn" style="display:none;">
                <i data-lucide="refresh-cw"></i> Retake
            </button>
            <button type="button" class="btn btn-primary" id="captureBtn">
                <i data-lucide="aperture"></i> Capture
            </button>
            <button type="button" class="btn btn-primary" id="useCaptureBtn" style="display:none;">
                <i data-lucide="check"></i> Use Photo
            </button>
        </div>
    </div>
</div>

<script>
/* ── Webcam Capture Logic ───────────────────────────────────── */
(function () {
    const openBtn       = document.getElementById('openCameraBtn');
    const closeBtn      = document.getElementById('closeCameraBtn');
    const captureBtn    = document.getElementById('captureBtn');
    const retakeBtn     = document.getElementById('retakeBtn');
    const useBtn        = document.getElementById('useCaptureBtn');
    const video         = document.getElementById('cameraFeed');
    const canvas        = document.getElementById('cameraCanvas');
    const snapshot      = document.getElementById('cameraSnapshot');
    const statusEl      = document.getElementById('cameraStatus');
    const captureData   = document.getElementById('photoCaptureData');
    const fileInput     = document.getElementById('photoFileInput');
    const fileWrap      = document.getElementById('fileUploadWrap');
    const previewWrap   = document.getElementById('photoPreviewWrap');
    const previewImg    = document.getElementById('photoPreviewImg');
    const clearBtn      = document.getElementById('clearPhotoBtn');

    let stream = null;
    let snapshotDataUrl = null;

    /* ── Open camera modal ── */
    openBtn.addEventListener('click', async () => {
        openModal('cameraModal');
        snapshot.style.display = 'none';
        video.style.display    = 'block';
        captureBtn.style.display  = '';
        retakeBtn.style.display   = 'none';
        useBtn.style.display      = 'none';
        statusEl.textContent      = 'Requesting camera access…';
        snapshotDataUrl           = null;

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            video.srcObject = stream;
            statusEl.textContent = 'Camera ready. Position the member and press Capture.';
        } catch (err) {
            statusEl.style.color = '#ef4444';
            statusEl.textContent = 'Camera access denied or unavailable: ' + err.message;
            captureBtn.disabled  = true;
        }
    });

    /* ── Capture frame ── */
    captureBtn.addEventListener('click', () => {
        if (!stream) return;
        canvas.width  = video.videoWidth  || 640;
        canvas.height = video.videoHeight || 480;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        snapshotDataUrl = canvas.toDataURL('image/png');

        snapshot.src         = snapshotDataUrl;
        snapshot.style.display = 'block';
        video.style.display    = 'none';

        captureBtn.style.display = 'none';
        retakeBtn.style.display  = '';
        useBtn.style.display     = '';
        statusEl.style.color     = 'var(--muted)';
        statusEl.textContent     = 'Photo captured! Click "Use Photo" to apply it.';
    });

    /* ── Retake ── */
    retakeBtn.addEventListener('click', () => {
        snapshot.style.display = 'none';
        video.style.display    = 'block';
        captureBtn.style.display  = '';
        retakeBtn.style.display   = 'none';
        useBtn.style.display      = 'none';
        statusEl.textContent      = 'Camera ready. Position the member and press Capture.';
        snapshotDataUrl = null;
    });

    /* ── Use captured photo ── */
    useBtn.addEventListener('click', () => {
        if (!snapshotDataUrl) return;
        captureData.value = snapshotDataUrl;

        // Show preview, hide file upload
        previewImg.src        = snapshotDataUrl;
        previewWrap.style.display = '';
        fileWrap.style.display    = 'none';
        fileInput.value           = '';          // clear any chosen file

        stopCamera();
        closeModal('cameraModal');
    });

    /* ── Clear preview / reset ── */
    clearBtn.addEventListener('click', () => {
        captureData.value         = '';
        previewWrap.style.display = 'none';
        fileWrap.style.display    = '';
        previewImg.src            = '';
    });

    /* ── Clear capture when a file is chosen instead ── */
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            captureData.value = '';
            const url = URL.createObjectURL(fileInput.files[0]);
            previewImg.src        = url;
            previewWrap.style.display = '';
        }
    });

    /* ── Close modal ── */
    closeBtn.addEventListener('click', () => {
        stopCamera();
        closeModal('cameraModal');
    });

    /* Stop stream when overlay clicked / Escape pressed */
    document.getElementById('cameraModal').addEventListener('click', (e) => {
        if (e.target.id === 'cameraModal') { stopCamera(); closeModal('cameraModal'); }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') stopCamera();
    });

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(t => t.stop());
            stream = null;
        }
        captureBtn.disabled = false;
    }
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
