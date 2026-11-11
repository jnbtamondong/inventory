<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles([ROLE_ADMIN, ROLE_HEAD]);
$pageTitle = 'Inventory Adjustment';
$activePage = 'inventory_adjustment';
$pageDescription = 'Controlled adjustment workspace for stock variance review and audit preparation.';
$varianceItems = db_all("SELECT item_code, item_description, category, current_stock, minimum_stock, lot_number FROM items WHERE status='Active' ORDER BY item_description LIMIT 100");
include __DIR__ . '/../includes/header.php';
?>
<section class="metric-row">
    <div class="metric-card"><span>Adjustment Mode</span><strong>Review</strong></div>
    <div class="metric-card"><span>Audit Trail</span><strong>Required</strong></div>
    <div class="metric-card"><span>Stock Source</span><strong>Items</strong></div>
    <div class="metric-card"><span>Access</span><strong><?= e(current_user()['role']) ?></strong></div>
</section>

<section class="page-panel">
    <div class="pos-panel-title">
        <div>
            <span class="eyebrow">Controlled Workflow</span>
            <h3>Adjustment Preparation</h3>
        </div>
        <a class="btn btn-light" href="activity_logs.php">Audit Logs</a>
    </div>
    <div class="form-section">
        <div class="form-section-title"><h4>Recommended Process</h4><span>No stock is changed on this page</span></div>
        <p class="text-muted">For accountability, stock changes should continue to be posted through receiving, issuance, or an approved request workflow. Use this page to review item balances before formal adjustment posting is added.</p>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Code</th><th>Description</th><th>Category</th><th>Lot</th><th>Current Stock</th><th>Minimum Stock</th></tr></thead>
            <tbody><?php foreach ($varianceItems as $item): ?><tr><td><strong><?= e($item['item_code']) ?></strong></td><td><?= e($item['item_description']) ?></td><td><?= e($item['category']) ?></td><td><?= e($item['lot_number']) ?></td><td><?= (int) $item['current_stock'] ?></td><td><?= (int) $item['minimum_stock'] ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
