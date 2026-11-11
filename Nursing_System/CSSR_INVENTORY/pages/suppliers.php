<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();
$pageTitle = 'Suppliers';
$activePage = 'suppliers';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        $supplier = $id ? db_one('SELECT * FROM suppliers WHERE id=?', [$id]) : null;
        if (!$supplier) {
            set_flash('danger', 'Supplier not found.');
            redirect('suppliers.php');
        }
        db()->prepare('DELETE FROM suppliers WHERE id=?')->execute([$id]);
        log_activity('DELETE', 'Suppliers', 'Deleted supplier ' . $supplier['supplier_name']);
        set_flash('success', 'Supplier deleted.');
        redirect('suppliers.php');
    }

    $supplierName = strtoupper(trim($_POST['supplier_name']));
    $data = [$supplierName, trim($_POST['contact_person']), trim($_POST['contact_number']), trim($_POST['address']), $_POST['status']];
    if ($id) {
        db()->prepare('UPDATE suppliers SET supplier_name=?,contact_person=?,contact_number=?,address=?,status=? WHERE id=?')->execute([...$data, $id]);
    } else {
        db()->prepare('INSERT INTO suppliers (supplier_name,contact_person,contact_number,address,status) VALUES (?,?,?,?,?)')->execute($data);
    }
    log_activity($id ? 'UPDATE' : 'CREATE', 'Suppliers', $supplierName);
    set_flash('success', 'Supplier saved.');
    redirect('suppliers.php');
}
$edit = null;
if (isset($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM suppliers WHERE id=?');
    $s->execute([(int) $_GET['edit']]);
    $edit = $s->fetch();
}
$rows = db_all('SELECT * FROM suppliers ORDER BY supplier_name');
include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel">
    <div class="module-actions">
        <button class="btn btn-primary" type="button" data-modal-open="#supplierModal">Add Supplier</button>
        <button class="btn btn-light" type="button" onclick="printPage()">Print</button>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Supplier</th><th>Contact</th><th>Number</th><th>Address</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e($r['supplier_name']) ?></strong></td>
                    <td><?= e($r['contact_person']) ?></td>
                    <td><?= e($r['contact_number']) ?></td>
                    <td><?= e($r['address']) ?></td>
                    <td><span class="badge <?= $r['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>"><?= e($r['status']) ?></span></td>
                    <td>
                        <div class="cu-record-actions">
                            <a class="btn btn-sm btn-light cu-record-edit" href="?edit=<?= (int) $r['id'] ?>" aria-label="Edit <?= e($r['supplier_name']) ?>"><?= cssrUiIcon('edit') ?><span>Edit</span></a>
                            <?php if (is_cssr_inventory_admin()): ?>
                                <form method="post" class="m-0" data-confirm-message="Are you sure you want to delete <?= e($r['supplier_name']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="btn btn-sm btn-danger cu-record-delete" type="submit" aria-label="Delete <?= e($r['supplier_name']) ?>"><?= cssrUiIcon('trash') ?><span>Delete</span></button>
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

<div id="supplierModal" class="app-modal <?= ($edit || isset($_GET['new'])) ? 'open' : '' ?>">
    <div class="app-modal-card">
        <div class="app-modal-head">
            <div><span class="eyebrow">Supplier Record</span><h3><?= $edit ? 'Edit Supplier' : 'Add Supplier' ?></h3></div>
            <a class="btn btn-light btn-sm" href="suppliers.php">Close</a>
        </div>
        <form class="app-modal-body" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
            <div class="form-section">
                <div class="form-section-title"><h4>Company Information</h4><span>Required fields are marked</span></div>
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label required">Supplier</label><input class="form-control text-uppercase" name="supplier_name" value="<?= e($edit['supplier_name'] ?? '') ?>" required></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Contact Person</label><input class="form-control" name="contact_person" value="<?= e($edit['contact_person'] ?? '') ?>"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Contact No.</label><input class="form-control" name="contact_number" value="<?= e($edit['contact_number'] ?? '') ?>"></div>
                    <div class="col-md-8 mb-2"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= e($edit['address'] ?? '') ?>"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><option>Active</option><option <?= (($edit['status'] ?? '') === 'Inactive') ? 'selected' : '' ?>>Inactive</option></select></div>
                </div>
            </div>
            <div class="modal-form-actions">
                <a class="btn btn-light" href="suppliers.php">Cancel</a>
                <button class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
