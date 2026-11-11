<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();
$pageTitle = 'Departments';
$activePage = 'departments';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        if (!is_cssr_inventory_admin()) {
            set_flash('danger', 'Only a CSSR inventory administrator can delete departments.');
            redirect('departments.php');
        }

        $department = $id ? db_one('SELECT * FROM departments WHERE id=?', [$id]) : null;
        if (!$department) {
            set_flash('danger', 'Department not found.');
            redirect('departments.php');
        }

        $requestCount = (int) db_value('SELECT COUNT(*) FROM item_requests WHERE department_id=?', [$id]);
        $userCount = (int) db_value('SELECT COUNT(*) FROM users WHERE department_id=?', [$id]);
        if ($requestCount > 0 || $userCount > 0) {
            set_flash('danger', 'This department cannot be deleted because it is assigned to users or request records. Set it to Inactive instead.');
            redirect('departments.php');
        }

        db()->prepare('DELETE FROM departments WHERE id=?')->execute([$id]);
        log_activity('DELETE', 'Departments', 'Deleted department ' . $department['department_name']);
        set_flash('success', 'Department deleted.');
        redirect('departments.php');
    }

    $departmentName = strtoupper(trim($_POST['department_name']));
    if ($id) {
        db()->prepare('UPDATE departments SET department_name=?,status=? WHERE id=?')->execute([$departmentName, $_POST['status'], $id]);
    } else {
        db()->prepare('INSERT INTO departments (department_name,status) VALUES (?,?)')->execute([$departmentName, $_POST['status']]);
    }
    log_activity($id ? 'UPDATE' : 'CREATE', 'Departments', $departmentName);
    set_flash('success', 'Department saved.');
    redirect('departments.php');
}
$edit = null;
if (isset($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM departments WHERE id=?');
    $s->execute([(int) $_GET['edit']]);
    $edit = $s->fetch();
}
$rows = db_all('SELECT * FROM departments ORDER BY department_name');
include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel">
    <div class="module-actions">
        <button class="btn btn-primary" type="button" data-modal-open="#departmentModal">Add Department</button>
        <button class="btn btn-light" type="button" onclick="printPage()">Print</button>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Department</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e($r['department_name']) ?></strong></td>
                    <td><span class="badge <?= $r['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>"><?= e($r['status']) ?></span></td>
                    <td>
                        <div class="cu-record-actions">
                            <a class="btn btn-sm btn-light cu-record-edit" href="?edit=<?= (int) $r['id'] ?>" aria-label="Edit <?= e($r['department_name']) ?>"><?= cssrUiIcon('edit') ?><span>Edit</span></a>
                            <?php if (is_cssr_inventory_admin()): ?>
                                <form method="post" class="m-0" data-confirm-message="Are you sure you want to delete <?= e($r['department_name']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="btn btn-sm btn-danger cu-record-delete" type="submit" aria-label="Delete <?= e($r['department_name']) ?>"><?= cssrUiIcon('trash') ?><span>Delete</span></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div id="departmentModal" class="app-modal <?= $edit ? 'open' : '' ?>">
    <div class="app-modal-card">
        <div class="app-modal-head">
            <div><span class="eyebrow">Department Setup</span><h3><?= $edit ? 'Edit Department' : 'Add Department' ?></h3></div>
            <a class="btn btn-light btn-sm" href="departments.php">Close</a>
        </div>
        <form class="app-modal-body" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
            <div class="form-section">
                <div class="form-section-title"><h4>Department Details</h4><span>Used for requests and issuance</span></div>
                <div class="row">
                    <div class="col-md-8 mb-2"><label class="form-label required">Department</label><input class="form-control text-uppercase" name="department_name" value="<?= e($edit['department_name'] ?? '') ?>" required></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><option>Active</option><option <?= (($edit['status'] ?? '') === 'Inactive') ? 'selected' : '' ?>>Inactive</option></select></div>
                </div>
            </div>
            <div class="modal-form-actions"><a class="btn btn-light" href="departments.php">Cancel</a><button class="btn btn-primary">Save Department</button></div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
