<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();

$pageTitle = 'All storage rooms';
$pageDescription = 'Review items, delivery lots, and stock balances by storage location.';
$activePage = 'storage_masterlist';
$storageRooms = ['CSSR', 'STORAGE ROOM 1', 'STORAGE ROOM 2', 'STORAGE ROOM 3'];
$selectedRoom = strtoupper(trim($_GET['room'] ?? 'STORAGE ROOM 1'));
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
if (!in_array($selectedRoom, $storageRooms, true)) {
    $selectedRoom = 'STORAGE ROOM 1';
}

$where = ['i.status = "Active"', 'l.storage_location = ?'];
$params = [$selectedRoom];
$issuedWhere = ['so.item_lot_id = l.id'];
$issuedParams = [];
if ($startDate !== '') { $issuedWhere[] = 'so.issued_date >= ?'; $issuedParams[] = $startDate; }
if ($endDate !== '') { $issuedWhere[] = 'so.issued_date <= ?'; $issuedParams[] = $endDate; }

$sql = 'SELECT i.id, i.item_code, COALESCE(NULLIF(TRIM(i.item_name),""),NULLIF(TRIM(i.item_description),""),i.item_code) AS item_name, i.item_description, COALESCE(NULLIF(GROUP_CONCAT(DISTINCT NULLIF(l.brand, "") ORDER BY l.brand SEPARATOR ", "), ""), NULLIF(i.brand, ""), "N/A") AS brand, i.category, i.unit, i.item_image,
               COUNT(l.id) AS lot_count,
               COALESCE(SUM((SELECT SUM(so.quantity_issued) FROM stock_out so WHERE ' . implode(' AND ', $issuedWhere) . ')), 0) AS total_issued,
               COALESCE(SUM(l.quantity_available), 0) AS room_quantity
        FROM items i
        JOIN item_lots l ON l.item_id = i.id
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY i.id, i.item_code, i.item_name, i.item_description, i.brand, i.category, i.unit, i.item_image
        ORDER BY item_name';
$stmt = db()->prepare($sql);
$stmt->execute(array_merge($issuedParams, $params));
$items = $stmt->fetchAll();

$totalsStmt = db()->prepare('SELECT COUNT(DISTINCT l.item_id) item_count, COUNT(l.id) lot_count, COALESCE(SUM(l.quantity_available),0) total_quantity FROM item_lots l JOIN items i ON i.id=l.item_id WHERE l.storage_location=? AND i.status="Active"');
$totalsStmt->execute([$selectedRoom]);
$totals = $totalsStmt->fetch() ?: ['item_count' => 0, 'lot_count' => 0, 'total_quantity' => 0];

include __DIR__ . '/../includes/header.php';
?>
<section class="cu-stats storage-screen-content sw-summary" aria-label="Storage summary">
    <?php foreach ([['Available Quantity',number_format((int)$totals['total_quantity']),'box','Consolidated room balance'],['Items',number_format((int)$totals['item_count']),'check','Distinct items in this room'],['Lots',number_format((int)$totals['lot_count']),'inbound','Delivery lot records'],['Storage Location',$selectedRoom==='CSSR'?'CSSR':ucwords(strtolower($selectedRoom)),'storage','Selected inventory location']] as [$label,$value,$icon,$help]): ?>
    <article class="cu-card cu-stat"><div class="cu-stat-top"><span><?=e($label)?></span><span class="cu-icon-tile"><?=cssrUiIcon($icon)?></span></div><strong class="cu-stat-value"><?=e($value)?></strong><small><?=e($help)?></small></article>
    <?php endforeach; ?>
</section>

<section class="cu-card sw-stock-card storage-screen-content">
    <div class="cu-card-head">
        <div><h2>Stored supplies</h2><p><?=e($selectedRoom==='CSSR'?'CSSR':ucwords(strtolower($selectedRoom)))?> item masterlist</p></div>
        <span class="cu-badge"><?= count($items) ?> items</span>
    </div>
    <form class="sw-filters no-print" method="get">
        <div class="cu-field"><label for="sw-room">Storage Location</label><select class="cu-input" id="sw-room" name="room" onchange="this.form.submit()"><?php foreach ($storageRooms as $room): ?><option value="<?= e($room) ?>" <?= $selectedRoom === $room ? 'selected' : '' ?>><?= e($room==='CSSR'?'CSSR':ucwords(strtolower($room))) ?></option><?php endforeach; ?></select></div>
        <div class="cu-field"><label for="sw-start">Start Date</label><input class="cu-input" id="sw-start" type="date" name="start_date" value="<?= e($startDate) ?>"></div>
        <div class="cu-field"><label for="sw-end">End Date</label><input class="cu-input" id="sw-end" type="date" name="end_date" value="<?= e($endDate) ?>"></div>
        <div class="cu-actions"><button class="cu-btn">Apply filters</button><a class="cu-btn cu-btn-ghost" href="storage_masterlist.php">Reset</a><button class="cu-btn cu-btn-secondary" type="button" onclick="printStorageRoomInventory()">Print</button></div>
    </form>
    <div class="table-responsive">
        <table class="table cu-table storage-room-print-table" data-page-size="25">
            <thead><tr><th>Picture</th><th>Item Code</th><th>Item Name</th><th>Brand</th><th>Category</th><th>Unit</th><th>Lots</th><th>Total Issued</th><th>Available Quantity</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php if ($item['item_image']): ?><img class="image-thumb" src="../uploads/items/<?= e($item['item_image']) ?>" alt="<?= e($item['item_name']) ?>"><?php else: ?><span class="mini-thumb empty">IMG</span><?php endif; ?></td>
                    <td><?= e($item['item_code']) ?></td>
                    <td><strong><?= e($item['item_name']) ?></strong></td>
                    <td><?= e($item['brand']) ?></td>
                    <td><?= e($item['category'] ?: 'N/A') ?></td>
                    <td><?= e($item['unit'] ?: 'N/A') ?></td>
                    <td><?= number_format((int) $item['lot_count']) ?></td>
                    <td><?= number_format((int) $item['total_issued']) ?></td>
                    <td><strong><?= number_format((int) $item['room_quantity']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr data-empty-row><td colspan="9" class="text-center">No items found in <?= e($selectedRoom) ?>.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php $storagePrintPages = array_chunk($items, 38); if (!$storagePrintPages) $storagePrintPages = [[]]; ?>
<div class="storage-print-report print-only">
<?php foreach ($storagePrintPages as $storagePageItems): ?>
<section class="storage-print-page">
    <div class="report-print-header">
        <div class="bin-card-header">
            <img class="bin-logo" src="../assets/img/doh-logo.png" alt="Department of Health logo">
            <div class="facility-copy">
                <span>Republic of the Philippines</span>
                <strong>DEPARTMENT OF HEALTH</strong>
                <span>Central Luzon Center for Health Development</span>
                <strong>JONI VILLANUEVA GENERAL HOSPITAL</strong>
                <span>National Rd. Igulot, Bocaue, Bulacan</span>
                <small>Contact #: 0960 302 6389 E-mail: jvgh@centralluzon.doh.gov.ph</small>
            </div>
            <img class="bin-logo" src="../assets/img/jvgh-seal.png" alt="Joni Villanueva General Hospital seal">
        </div>
        <h2><?= e($selectedRoom) ?> Inventory Report</h2>
        <?php if ($startDate !== '' || $endDate !== ''): ?><p><?= e($startDate ?: 'Beginning') ?> to <?= e($endDate ?: date('Y-m-d')) ?></p><?php endif; ?>
    </div>
    <table class="table storage-report-table">
        <thead><tr><th>Item Code</th><th>Item Name</th><th>Brand</th><th>Unit</th><th>Total Issued</th><th>Available Quantity</th></tr></thead>
        <tbody>
        <?php foreach ($storagePageItems as $item): ?><tr><td><?= e($item['item_code']) ?></td><td><?= e($item['item_name']) ?></td><td><?= e($item['brand']) ?></td><td><?= e($item['unit'] ?: 'N/A') ?></td><td><?= number_format((int)$item['total_issued']) ?></td><td><strong><?= number_format((int)$item['room_quantity']) ?></strong></td></tr><?php endforeach; ?>
        <?php if (!$storagePageItems): ?><tr><td colspan="6" class="text-center">No items found in <?= e($selectedRoom) ?>.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <div class="bin-card-footer storage-report-footer">
        <div class="bin-footer-note">This is a computer-generated form. &nbsp; No<br>stamping is needed.</div>
        <div class="bin-footer-code"><strong>JVGH-CSSR-XX-XXX</strong><table><tr><th>Rev Number</th><td></td></tr><tr><th>Eff Date</th><td></td></tr></table></div>
    </div>
</section>
<?php endforeach; ?>
</div>
<script>
function printStorageRoomInventory(){
    var rows=Array.from(document.querySelectorAll('.storage-room-print-table tbody tr'));
    var displays=rows.map(function(row){return row.style.display});
    rows.forEach(function(row){row.style.display=''});
    document.body.classList.add('storage-data-printing');
    window.addEventListener('afterprint',function(){
        rows.forEach(function(row,index){row.style.display=displays[index]});
        document.body.classList.remove('storage-data-printing');
    },{once:true});
    window.print();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
