<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();

$pageTitle = 'Price List';
$activePage = 'price_list';
$classification = trim($_GET['classification'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$where = ['1=1'];
$params = [];
if ($classification !== '') { $where[] = 'classification=?'; $params[] = $classification; }
if ($startDate !== '') { $where[] = 'source_as_of>=?'; $params[] = $startDate; }
if ($endDate !== '') { $where[] = 'source_as_of<=?'; $params[] = $endDate; }

$stmt = db()->prepare('SELECT * FROM item_price_list WHERE ' . implode(' AND ', $where) . ' ORDER BY item_name, lot_number, id');
$stmt->execute($params);
$rows = $stmt->fetchAll();
$classifications = db_all('SELECT DISTINCT classification FROM item_price_list WHERE classification IS NOT NULL AND classification<>"" ORDER BY classification');

include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel">
    <div class="pos-panel-title"><div><span class="eyebrow">Regular Supply Inventory</span><h3>Item Price List</h3></div><span class="pos-count"><?= count($rows) ?> records</span></div>
    <form class="toolbar no-print" method="get">
        <div class="field"><label class="form-label">Category</label><select class="form-select" name="classification"><option value="">All Categories</option><?php foreach ($classifications as $row): ?><option value="<?= e($row['classification']) ?>" <?= $classification === $row['classification'] ? 'selected' : '' ?>><?= e($row['classification']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="form-label">Start Date</label><input class="form-control" type="date" name="start_date" value="<?= e($startDate) ?>"></div>
        <div class="field"><label class="form-label">End Date</label><input class="form-control" type="date" name="end_date" value="<?= e($endDate) ?>"></div>
        <button class="btn btn-primary">View</button><a class="btn btn-light" href="price_list.php">Reset</a>
    </form>
    <div class="table-responsive"><table class="table" data-page-size="25">
        <thead><tr><th>Item Name</th><th>Lot Number</th><th>Unit Type</th><th>Category</th><th>Unit Price</th><th>Mark Up Cost</th><th>Total Price</th><th>Selling Price</th></tr></thead>
        <tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= e($row['item_name']) ?></strong></td><td><?= e($row['lot_number'] ?: 'N/A') ?></td><td><?= e($row['unit_type'] ?: 'N/A') ?></td><td><?= e($row['classification'] ?: 'N/A') ?></td><td>₱<?= number_format((float)$row['unit_price'],2) ?></td><td>₱<?= number_format((float)$row['markup_cost'],2) ?></td><td>₱<?= number_format((float)$row['total_price'],2) ?></td><td><strong>₱<?= number_format((float)$row['selling_price'],2) ?></strong></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr data-empty-row><td colspan="8" class="text-center">No price records found.</td></tr><?php endif; ?></tbody>
    </table></div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
