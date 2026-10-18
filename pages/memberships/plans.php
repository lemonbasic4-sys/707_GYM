<?php
/**
 * 707 GYM – Membership Plans CRUD
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

requireAuth();

$db     = Database::getConnection();
$errors = [];
$msg    = '';

// ── POST Handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name     = trim($_POST['name']        ?? '');
            $desc     = trim($_POST['description'] ?? '');
            $duration = (int)($_POST['duration_days'] ?? 0);
            $price    = (float)($_POST['price'] ?? 0);
            $status   = in_array($_POST['status']??'', ['active','inactive']) ? $_POST['status'] : 'active';

            if (empty($name))    $errors[] = 'Plan name is required.';
            if ($duration <= 0)  $errors[] = 'Duration must be greater than 0.';
            if ($price < 0)      $errors[] = 'Price cannot be negative.';

            if (empty($errors)) {
                $db->prepare("INSERT INTO membership_plans (name,description,duration_days,price,status,created_at) VALUES (:n,:d,:dur,:p,:s,NOW())")
                   ->execute([':n'=>$name,':d'=>$desc,':dur'=>$duration,':p'=>$price,':s'=>$status]);
                logActivity($_SESSION['admin_id'], 'plan.create', "Plan: {$name}");
                setFlash('success', "Plan \"{$name}\" created.");
                redirect(BASE_URL . '/pages/memberships/plans.php');
            }
        } elseif ($action === 'edit') {
            $planId   = (int)($_POST['plan_id'] ?? 0);
            $name     = trim($_POST['name']        ?? '');
            $desc     = trim($_POST['description'] ?? '');
            $duration = (int)($_POST['duration_days'] ?? 0);
            $price    = (float)($_POST['price'] ?? 0);
            $status   = in_array($_POST['status']??'', ['active','inactive']) ? $_POST['status'] : 'active';

            if ($planId <= 0 || empty($name)) $errors[] = 'Invalid data.';

            if (empty($errors)) {
                $db->prepare("UPDATE membership_plans SET name=:n,description=:d,duration_days=:dur,price=:p,status=:s WHERE id=:id")
                   ->execute([':n'=>$name,':d'=>$desc,':dur'=>$duration,':p'=>$price,':s'=>$status,':id'=>$planId]);
                logActivity($_SESSION['admin_id'], 'plan.edit', "Plan ID {$planId}");
                setFlash('success', "Plan updated.");
                redirect(BASE_URL . '/pages/memberships/plans.php');
            }
        } elseif ($action === 'delete') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            // Soft delete: set to inactive
            $db->prepare("UPDATE membership_plans SET status='inactive' WHERE id=:id")->execute([':id'=>$planId]);
            logActivity($_SESSION['admin_id'], 'plan.delete', "Plan ID {$planId}");
            setFlash('success', 'Plan deactivated.');
            redirect(BASE_URL . '/pages/memberships/plans.php');
        }
    }
    rotateCsrfToken();
}

$plans = $db->query("SELECT * FROM membership_plans ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Membership Plans';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Membership Plans</h1>
        <p>Manage available plans and pricing.</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-primary" onclick="openModal('createPlanModal')">
            <i data-lucide="plus"></i> Add Plan
        </button>
    </div>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= sanitize($e) ?></div><?php endforeach; ?>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Plan Name</th><th>Description</th><th>Duration</th><th>Price</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($plans)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:20px;">No plans found.</td></tr>
                <?php else: ?>
                <?php foreach ($plans as $plan): ?>
                <tr>
                    <td><strong><?= sanitize($plan['name']) ?></strong></td>
                    <td class="text-muted"><?= sanitize($plan['description'] ?? '—') ?></td>
                    <td><?= (int)$plan['duration_days'] ?> days</td>
                    <td><?= formatPhilippineCurrency($plan['price']) ?></td>
                    <td>
                        <span class="badge badge-<?= $plan['status']==='active'?'success':'muted' ?>">
                            <?= sanitize($plan['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <button class="btn btn-secondary btn-sm"
                                    onclick="editPlan(<?= htmlspecialchars(json_encode($plan), ENT_QUOTES) ?>)">
                                <i data-lucide="pencil"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this plan?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><i data-lucide="trash-2"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Plan Modal -->
<div id="createPlanModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add Membership Plan</h3>
            <button class="modal-close" onclick="closeModal('createPlanModal')"><i data-lucide="x"></i></button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-group"><label>Plan Name <span class="required">*</span></label><input type="text" name="name" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="2"></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Duration (days) <span class="required">*</span></label><input type="number" name="duration_days" min="1" required></div>
                <div class="form-group"><label>Price (PHP) <span class="required">*</span></label><input type="number" name="price" min="0" step="0.01" required></div>
            </div>
            <div class="form-group"><label>Status</label>
                <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('createPlanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Plan Modal -->
<div id="editPlanModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Plan</h3>
            <button class="modal-close" onclick="closeModal('editPlanModal')"><i data-lucide="x"></i></button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="plan_id" id="editPlanId">
            <div class="form-group"><label>Plan Name</label><input type="text" name="name" id="editPlanName" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" id="editPlanDesc" rows="2"></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Duration (days)</label><input type="number" name="duration_days" id="editPlanDuration" min="1"></div>
                <div class="form-group"><label>Price (PHP)</label><input type="number" name="price" id="editPlanPrice" min="0" step="0.01"></div>
            </div>
            <div class="form-group"><label>Status</label>
                <select name="status" id="editPlanStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('editPlanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editPlan(plan) {
    document.getElementById('editPlanId').value       = plan.id;
    document.getElementById('editPlanName').value     = plan.name;
    document.getElementById('editPlanDesc').value     = plan.description || '';
    document.getElementById('editPlanDuration').value = plan.duration_days;
    document.getElementById('editPlanPrice').value    = plan.price;
    document.getElementById('editPlanStatus').value   = plan.status;
    openModal('editPlanModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
