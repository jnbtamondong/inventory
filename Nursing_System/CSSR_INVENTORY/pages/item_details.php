<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pageTitle = 'Item Details';
$activePage = 'items';
$pageDescription = 'Detailed inventory profile with stock summary, lot data, movement history, and bin card activity.';

$itemId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM items WHERE id=?');
$stmt->execute([$itemId]);
$item = $stmt->fetch();
if (!$item) {
    set_flash('danger', 'Item record not found.');
    redirect('items.php');
}

$itemName = trim((string) ($item['item_name'] ?? '')) ?: (trim((string) ($item['item_description'] ?? '')) ?: $item['item_code']);
// Lot details belong to individual stock records, not the item master fields.
$lotRows = db_all('SELECT id, lot_number, expiry_date, storage_location, quantity_available AS quantity, supplier, brand, created_at, status
    FROM item_lots WHERE item_id=? AND status<>"DELETED"
    ORDER BY storage_location, expiry_date IS NULL, expiry_date, lot_number', [$itemId]);
$cssrLotRows = db_all('SELECT id, lot_number, expiry_date, "CSSR" AS storage_location, quantity, NULL AS supplier, NULL AS brand, unit_type AS unit, created_at, status
    FROM cssr_available_stock WHERE item_id=? AND status<>"DELETED"
    ORDER BY expiry_date IS NULL, expiry_date, lot_number', [$itemId]);
$lotRows = array_merge($lotRows, $cssrLotRows);
foreach ($lotRows as &$detailLot) $detailLot['unit'] = trim((string) ($detailLot['unit'] ?? '')) ?: $item['unit'];
unset($detailLot);
function item_detail_value($value): string
{
    $text = trim((string) $value);
    return $text === '' || $text === '0000-00-00' ? 'Not recorded' : $text;
}
function item_detail_lot_summary(array $lots, string $field, $fallback): string
{
    if (!$lots) return item_detail_value($fallback);
    $values = array_values(array_unique(array_map(static fn($lot) => item_detail_value($lot[$field] ?? null), $lots)));
    return count($values) === 1 ? $values[0] : 'Multiple — see Lot Details';
}

$stockIn = db()->prepare('SELECT * FROM stock_in WHERE item_id=? ORDER BY received_date DESC, id DESC LIMIT 20');
$stockIn->execute([$itemId]);
$stockInRows = $stockIn->fetchAll();

$stockOut = db()->prepare('SELECT * FROM stock_out WHERE item_id=? ORDER BY issued_date DESC, id DESC LIMIT 20');
$stockOut->execute([$itemId]);
$stockOutRows = $stockOut->fetchAll();

$binCards = db()->prepare('SELECT * FROM bin_cards WHERE item_id=? ORDER BY transaction_date DESC, id DESC LIMIT 30');
$binCards->execute([$itemId]);
$binRows = $binCards->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<section class="item-profile">
    <div class="page-panel item-profile-media">
        <?php if ($item['item_image']): ?>
            <img class="item-profile-img" src="../uploads/items/<?= e($item['item_image']) ?>" alt="">
        <?php else: ?>
            <div class="item-profile-empty">IMG</div>
        <?php endif; ?>
        <h3><?= e($itemName) ?></h3>
        <p><?= e($item['item_code']) ?></p>
        <span class="badge <?= $item['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>"><?= e($item['status']) ?></span>
    </div>
    <div>
        <section class="metric-row">
            <div class="metric-card"><span id="item-stock-label">Current Stock</span><strong data-lot-field="quantity"><?= (int) $item['current_stock'] ?></strong></div>
            <div class="metric-card"><span>Minimum Stock</span><strong><?= (int) $item['minimum_stock'] ?></strong></div>
            <div class="metric-card"><span>Unit</span><strong data-lot-field="unit"><?= e($item['unit']) ?></strong></div>
            <div class="metric-card"><span>Storage Location</span><strong data-lot-field="storage_location"><?= e(item_detail_lot_summary($lotRows, 'storage_location', $item['bin_location'])) ?></strong></div>
        </section>
        <section class="page-panel">
            <h3>Inventory Profile</h3>
            <div class="item-lot-picker">
                <label class="form-label" for="item-lot-select">Lot Number</label>
                <select class="form-select" id="item-lot-select" aria-describedby="item-lot-help" <?= !$lotRows ? 'disabled' : '' ?>>
                    <option value=""><?= $lotRows ? 'All lots — item summary' : 'No lot records available' ?></option>
                    <?php foreach ($lotRows as $index => $lot): ?>
                    <option value="<?= $index ?>"><?= e(item_detail_value($lot['lot_number']) . ' | ' . item_detail_value($lot['storage_location']) . ' | Expiry: ' . item_detail_value($lot['expiry_date']) . ' | #' . ($index + 1)) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted" id="item-lot-help">Select a lot to view its stock details. All lots remain listed below.</small>
            </div>
            <div class="receipt-meta">
                <span><strong>Category:</strong> <?= e(item_detail_value($item['category'])) ?></span>
                <span><strong>Brand:</strong> <output data-lot-field="brand"><?= e(item_detail_value($item['brand'])) ?></output></span>
                <span><strong>Supplier:</strong> <output data-lot-field="supplier"><?= e(item_detail_value($item['supplier'])) ?></output></span>
                <span><strong>Lot:</strong> <output data-lot-field="lot_number"><?= e(item_detail_lot_summary($lotRows, 'lot_number', $item['lot_number'])) ?></output></span>
                <span><strong>Expiry:</strong> <output data-lot-field="expiry_date"><?= e(item_detail_lot_summary($lotRows, 'expiry_date', $item['expiry_date'])) ?></output></span>
                <span><strong id="item-created-label">Item Created:</strong> <output data-lot-field="created_at"><?= e($item['created_at']) ?></output></span>
                <span><strong>Stock Status:</strong> <output data-lot-field="status">Select a lot</output></span>
            </div>
            <p><strong>Description:</strong> <?= e(item_detail_value($item['item_description'])) ?></p>
        </section>
    </div>
</section>

<section class="page-panel item-details-lots" id="lot-details">
    <h3>Lot Details</h3>
    <div class="table-responsive">
        <table class="table" data-page-size="15">
            <thead><tr><th>Lot / Batch Number</th><th>Expiration Date</th><th>Storage Location</th><th>Quantity</th><th>Unit</th><th>Supplier</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($lotRows as $lot): ?>
                <tr>
                    <td><?= e(item_detail_value($lot['lot_number'])) ?></td>
                    <td><?= e(item_detail_value($lot['expiry_date'])) ?></td>
                    <td><?= e(item_detail_value($lot['storage_location'])) ?></td>
                    <td><?= (int) $lot['quantity'] ?></td>
                    <td><?= e(item_detail_value($item['unit'])) ?></td>
                    <td><?= e(item_detail_value($lot['supplier'])) ?></td>
                    <td><span class="badge <?= $lot['status'] === 'AVAILABLE' ? 'bg-success' : (in_array($lot['status'], ['DEPLETED','EMPTY'], true) ? 'bg-secondary' : 'bg-warning') ?>"><?= e($lot['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$lotRows): ?><tr><td colspan="7" class="text-center">No lot records have been recorded for this item.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="page-panel">
    <h3>Receiving History</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Reference</th><th>Supplier</th><th>Qty</th><th>Received By</th></tr></thead><tbody><?php foreach ($stockInRows as $row): ?><tr><td><?= e($row['received_date']) ?></td><td><?= e($row['reference_no']) ?></td><td><?= e($row['supplier']) ?></td><td><?= (int) $row['quantity_received'] ?></td><td><?= e($row['received_by']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>

<section class="page-panel">
    <h3>Issuance History</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Reference</th><th>Department</th><th>Qty</th><th>Issued By</th></tr></thead><tbody><?php foreach ($stockOutRows as $row): ?><tr><td><?= e($row['issued_date']) ?></td><td><?= e($row['reference_no']) ?></td><td><?= e($row['department']) ?></td><td><?= (int) $row['quantity_issued'] ?></td><td><?= e($row['issued_by']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>

<section class="page-panel" id="stock-history">
    <h3>Bin Card History</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Reference</th><th>Type</th><th>Received</th><th>Issued</th><th>Balance</th><th>Encoded By</th></tr></thead><tbody><?php foreach ($binRows as $row): ?><tr><td><?= e($row['transaction_date']) ?></td><td><?= e($row['reference_no']) ?></td><td><span class="badge bg-secondary"><?= e($row['transaction_type']) ?></span></td><td><?= (int) $row['received_qty'] ?></td><td><?= (int) $row['issued_qty'] ?></td><td><?= (int) $row['balance_qty'] ?></td><td><?= e($row['encoded_by']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<script>
(() => {
    const lots = <?= json_encode($lotRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const selector = document.getElementById('item-lot-select');
    const fields = [...document.querySelectorAll('[data-lot-field]')];
    const summary = fields.map(field => field.textContent);
    selector.addEventListener('change', () => {
        const lot = selector.value === '' ? null : lots[Number(selector.value)];
        fields.forEach((field, index) => {
            const value = lot?.[field.dataset.lotField];
            field.textContent = !lot ? summary[index]
                : value == null || String(value).trim() === '' || value === '0000-00-00' ? 'Not recorded' : String(value);
        });
        document.getElementById('item-stock-label').textContent = lot ? 'Selected Lot Stock' : 'Current Stock';
        document.getElementById('item-created-label').textContent = lot ? 'Lot Created:' : 'Item Created:';
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
