<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();
$pageTitle = 'Reorder Alerts';
$activePage = 'reorder_alerts';
$items = db_all("SELECT i.*, COALESCE(NULLIF(TRIM(i.item_name),''),NULLIF(TRIM(i.item_description),''),i.item_code) AS item_description FROM items i WHERE status='Active' AND current_stock <= minimum_stock ORDER BY current_stock ASC, item_description");
$out = count(array_filter($items, fn($item) => (int) $item['current_stock'] <= 0));
$critical = count(array_filter($items, fn($item) => stock_status($item) === 'CRITICAL'));
$low = count(array_filter($items, fn($item) => stock_status($item) === 'LOW STOCK'));
include __DIR__ . '/../includes/header.php';
?>
<section class="metric-row">
    <div class="metric-card"><span>Total Alerts</span><strong><?= count($items) ?></strong></div>
    <div class="metric-card"><span>Out of Stock</span><strong><?= $out ?></strong></div>
    <div class="metric-card"><span>Critical</span><strong><?= $critical ?></strong></div>
    <div class="metric-card"><span>Low Stock</span><strong><?= $low ?></strong></div>
</section>
<section class="page-panel">
    <div class="module-actions"><button class="btn btn-light" onclick="printPage()">Print Reorder List</button><a class="btn btn-primary" href="stock_in.php">Receive Stock</a></div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Code</th><th>Item Name</th><th>Category</th><th>Current</th><th>Minimum</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($items as $item): $status = stock_status($item); ?><tr><td><strong><?= e($item['item_code']) ?></strong></td><td><?= e($item['item_description']) ?></td><td><?= e($item['category']) ?></td><td><?= (int) $item['current_stock'] ?></td><td><?= (int) $item['minimum_stock'] ?></td><td><span class="badge <?= $status === 'OUT OF STOCK' ? 'status-out' : ($status === 'CRITICAL' ? 'status-critical' : ($status === 'LOW STOCK' ? 'status-low' : 'status-normal')) ?>"><?= e($status) ?></span></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
