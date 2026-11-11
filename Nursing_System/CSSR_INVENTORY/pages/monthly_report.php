<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();

$pageTitle = 'Monthly Report';
$activePage = 'monthly_report';
$pageDescription = 'Monthly summary of stock received and issued per item.';

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$from = $month . '-01';
$to = date('Y-m-t', strtotime($from));

$stmt = db()->prepare(
    "SELECT
        i.item_code,
        COALESCE(NULLIF(TRIM(i.item_name),''),NULLIF(TRIM(i.item_description),''),i.item_code) AS item_description,
        i.category,
        i.unit,
        COALESCE(l.lot_numbers, '') lot_number,
        i.current_stock,
        COALESCE(si.total_in, 0) stock_in_qty,
        COALESCE(so.total_out, 0) stock_out_qty,
        COALESCE(si.total_in, 0) - COALESCE(so.total_out, 0) net_movement
     FROM items i
     LEFT JOIN (
        SELECT s.item_id, SUM(s.quantity_received) total_in
        FROM stock_in s
        WHERE received_date BETWEEN ? AND ?
        GROUP BY s.item_id
     ) si ON si.item_id = i.id
     LEFT JOIN (
        SELECT s.item_id, SUM(s.quantity_issued) total_out
        FROM stock_out s
        WHERE issued_date BETWEEN ? AND ?
        GROUP BY s.item_id
     ) so ON so.item_id = i.id
     LEFT JOIN (
        SELECT item_id, GROUP_CONCAT(DISTINCT NULLIF(lot_number, '') ORDER BY lot_number SEPARATOR ', ') lot_numbers
        FROM item_lots
        GROUP BY item_id
     ) l ON l.item_id = i.id
     WHERE i.status = 'Active'
       AND (COALESCE(si.total_in, 0) > 0 OR COALESCE(so.total_out, 0) > 0)
     ORDER BY i.item_description"
);
$stmt->execute([$from, $to, $from, $to]);
$rows = $stmt->fetchAll();

$totalIn = array_sum(array_map(fn($row) => (int) $row['stock_in_qty'], $rows));
$totalOut = array_sum(array_map(fn($row) => (int) $row['stock_out_qty'], $rows));
$netMovement = $totalIn - $totalOut;

include __DIR__ . '/../includes/header.php';
?>
<section class="metric-row">
    <div class="metric-card"><span>Month</span><strong><?= e(date('F Y', strtotime($from))) ?></strong></div>
    <div class="metric-card"><span>Total Stock In</span><strong><?= (int) $totalIn ?></strong></div>
    <div class="metric-card"><span>Total Stock Out</span><strong><?= (int) $totalOut ?></strong></div>
    <div class="metric-card"><span>Net Movement</span><strong><?= (int) $netMovement ?></strong></div>
</section>

<section class="page-panel">
    <form class="toolbar no-print" method="get">
        <div class="field">
            <label class="form-label">Month</label>
            <input class="form-control" type="month" name="month" value="<?= e($month) ?>">
        </div>
        <button class="btn btn-primary">Generate</button>
        <button type="button" class="btn btn-light" onclick="printPage()">Print</button>
    </form>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Lot</th>
                    <th>Stock In</th>
                    <th>Stock Out</th>
                    <th>Net</th>
                    <th>Current Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= e($row['item_code']) ?></strong></td>
                        <td><?= e($row['item_description']) ?></td>
                        <td><?= e($row['category']) ?></td>
                        <td><?= e($row['unit']) ?></td>
                        <td><?= e($row['lot_number']) ?></td>
                        <td><?= (int) $row['stock_in_qty'] ?></td>
                        <td><?= (int) $row['stock_out_qty'] ?></td>
                        <td><?= (int) $row['net_movement'] ?></td>
                        <td><?= (int) $row['current_stock'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
