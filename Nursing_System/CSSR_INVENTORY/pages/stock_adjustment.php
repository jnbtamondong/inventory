<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can_manage()) {
    http_response_code(403);
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger">Access denied for your role.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = 'Physical Count Adjustment';
$activePage = 'stock_adjustment';
$locations = ['CSSR', 'STORAGE ROOM 1', 'STORAGE ROOM 2', 'STORAGE ROOM 3'];
const PENDING_LOT_NUMBER = 'UNIDENTIFIED / PENDING';

function adjustment_item_name(array $item): string
{
    foreach (['master_item_name', 'item_name', 'item_description', 'item_code'] as $field) {
        $name = trim((string) ($item[$field] ?? ''));
        if ($name !== '') return $name;
    }
    return 'Item #' . (int) ($item['item_id'] ?? $item['id'] ?? 0);
}

function next_adjustment_number(): string
{
    return 'ADJ-' . date('Ymd-His');
}

function adjustment_nullable_date(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' || $value === '0000-00-00' ? null : $value;
}

db_statement(
    'CREATE TABLE IF NOT EXISTS stock_adjustments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        adjustment_no VARCHAR(120) NOT NULL UNIQUE,
        location_name VARCHAR(120) NOT NULL,
        stock_type VARCHAR(30) NOT NULL,
        source_record_id INT NOT NULL,
        item_id INT NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        lot_number VARCHAR(120) NULL,
        expiry_date DATE NULL,
        system_quantity INT NOT NULL,
        actual_quantity INT NOT NULL,
        variance_quantity INT NOT NULL,
        reason TEXT NOT NULL,
        adjusted_by VARCHAR(150) NOT NULL,
        adjusted_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES items(id),
        INDEX idx_stock_adjustment_location (location_name),
        INDEX idx_stock_adjustment_item (item_id),
        INDEX idx_stock_adjustment_date (adjusted_at)
    ) ENGINE=InnoDB'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token();
        $action = $_POST['action'] ?? 'post_adjustment';
        if ($action === 'delete_adjustment') {
            $adjustmentId = (int) ($_POST['adjustment_id'] ?? 0);
            if ($adjustmentId <= 0) {
                throw new RuntimeException('Invalid stock adjustment history record.');
            }
            db()->beginTransaction();
            $stmt = db()->prepare('SELECT * FROM stock_adjustments WHERE id=? FOR UPDATE');
            $stmt->execute([$adjustmentId]);
            $adjustment = $stmt->fetch();
            if (!$adjustment) {
                throw new RuntimeException('Stock adjustment history record was not found.');
            }
            db_statement('DELETE FROM stock_cards WHERE reference_no=? AND transaction_type="PHYSICAL COUNT ADJUSTMENT"', [$adjustment['adjustment_no']]);
            db_statement('DELETE FROM stock_adjustments WHERE id=?', [$adjustmentId]);
            log_activity('DELETE', 'Stock Adjustment', 'Deleted adjustment history ' . $adjustment['adjustment_no'] . ' for ' . $adjustment['item_name'] . '.');
            db()->commit();
            set_flash('success', 'Stock adjustment history deleted.');
            redirect('stock_adjustment.php');
        }

        $location = strtoupper(trim((string) ($_POST['location_name'] ?? '')));
        $stockType = strtoupper(trim((string) ($_POST['stock_type'] ?? '')));
        $recordId = (int) ($_POST['source_record_id'] ?? 0);
        $actualQty = (int) ($_POST['actual_quantity'] ?? -1);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $adjustmentNo = next_adjustment_number();
        $adjustedAt = date('Y-m-d H:i:s');

        $pendingItemId = (int) ($_POST['pending_item_id'] ?? 0);

        if (!in_array($location, $locations, true) || !in_array($stockType, ['CSSR', 'STORAGE', 'PENDING'], true) || $actualQty < 0 || $reason === '') {
            throw new RuntimeException('Location, item lot, actual count, and reason are required.');
        }
        if ($stockType !== 'PENDING' && $recordId <= 0) {
            throw new RuntimeException('Select an item lot to adjust.');
        }
        if ($stockType === 'PENDING' && $pendingItemId <= 0) {
            throw new RuntimeException('Select an item before posting an unidentified pending count.');
        }

        db()->beginTransaction();
        if ($stockType === 'PENDING') {
            $stmt = db()->prepare('SELECT * FROM items WHERE id=? AND status="Active" FOR UPDATE');
            $stmt->execute([$pendingItemId]);
            $item = $stmt->fetch();
            if (!$item) {
                throw new RuntimeException('Selected item was not found.');
            }

            $itemId = (int) $item['id'];
            $itemName = adjustment_item_name($item);
            $lotNumber = PENDING_LOT_NUMBER;
            $expiryDate = null;
            $balanceQty = $actualQty;

            if ($location === 'CSSR') {
                $stmt = db()->prepare('SELECT * FROM cssr_available_stock WHERE item_id=? AND lot_number=? AND expiry_date IS NULL FOR UPDATE');
                $stmt->execute([$itemId, PENDING_LOT_NUMBER]);
                $stock = $stmt->fetch();
                $systemQty = $stock ? (int) $stock['quantity'] : 0;
                $variance = $actualQty - $systemQty;
                if ($stock) {
                    $recordId = (int) $stock['id'];
                    db_statement('UPDATE cssr_available_stock SET quantity=?, status=?, updated_at=NOW() WHERE id=?', [$actualQty, $actualQty > 0 ? 'AVAILABLE' : 'EMPTY', $recordId]);
                } else {
                    db_statement(
                        'INSERT INTO cssr_available_stock (item_id, item_name, item_description, lot_number, expiry_date, quantity, unit_type, classification, procurement_type, source_location_code, status, created_by)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                        [$itemId, $itemName, $itemName, PENDING_LOT_NUMBER, null, $actualQty, $item['unit'] ?? '', $item['category'] ?? '', 'PENDING LOT IDENTIFICATION', 'CSSR', $actualQty > 0 ? 'AVAILABLE' : 'EMPTY', current_user()['full_name']]
                    );
                    $recordId = (int) db()->lastInsertId();
                }
            } else {
                $stmt = db()->prepare('SELECT * FROM item_lots WHERE item_id=? AND lot_number=? AND expiry_date IS NULL AND supplier="PENDING" AND storage_location=? AND unit_price=0 ORDER BY id LIMIT 1 FOR UPDATE');
                $stmt->execute([$itemId, PENDING_LOT_NUMBER, $location]);
                $stock = $stmt->fetch();
                $systemQty = $stock ? (int) $stock['quantity_available'] : 0;
                $variance = $actualQty - $systemQty;
                if ($stock) {
                    $recordId = (int) $stock['id'];
                    db_statement('UPDATE item_lots SET quantity_available=?, status=? WHERE id=?', [$actualQty, $actualQty > 0 ? 'AVAILABLE' : 'DEPLETED', $recordId]);
                } else {
                    db_statement(
                        'INSERT INTO item_lots (item_id, lot_number, expiry_date, supplier, storage_location, unit_price, quantity_received, quantity_available, status)
                         VALUES (?,?,?,?,?,?,?,?,?)',
                        [$itemId, PENDING_LOT_NUMBER, null, 'PENDING', $location, 0, $actualQty, $actualQty, $actualQty > 0 ? 'AVAILABLE' : 'DEPLETED']
                    );
                    $recordId = (int) db()->lastInsertId();
                }
                $itemBalance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?', [$itemId]);
                db_statement('UPDATE items SET current_stock=?, lot_number=?, expiry_date=?, bin_location=? WHERE id=?', [$itemBalance, PENDING_LOT_NUMBER, null, $location, $itemId]);
            }

            $fromLocation = $variance >= 0 ? 'PHYSICAL COUNT' : $location;
            $toLocation = $variance >= 0 ? $location : 'PHYSICAL COUNT';
        } elseif ($stockType === 'CSSR') {
            $stmt = db()->prepare('SELECT s.*, i.item_name AS master_item_name, i.item_code, i.item_description, i.current_stock FROM cssr_available_stock s JOIN items i ON i.id=s.item_id WHERE s.id=? FOR UPDATE');
            $stmt->execute([$recordId]);
            $stock = $stmt->fetch();
            if (!$stock) {
                throw new RuntimeException('Selected CSSR stock record was not found.');
            }
            $systemQty = (int) $stock['quantity'];
            $variance = $actualQty - $systemQty;
            db_statement('UPDATE cssr_available_stock SET quantity=?, status=?, updated_at=NOW() WHERE id=?', [$actualQty, $actualQty > 0 ? 'AVAILABLE' : 'EMPTY', $recordId]);
            $itemId = (int) $stock['item_id'];
            $itemName = adjustment_item_name($stock);
            $lotNumber = (string) $stock['lot_number'];
            $expiryDate = adjustment_nullable_date($stock['expiry_date']);
            $balanceQty = $actualQty;
            $fromLocation = $variance >= 0 ? 'PHYSICAL COUNT' : 'CSSR';
            $toLocation = $variance >= 0 ? 'CSSR' : 'PHYSICAL COUNT';
        } else {
            $stmt = db()->prepare('SELECT l.*, i.item_name, i.item_code, i.item_description, i.current_stock FROM item_lots l JOIN items i ON i.id=l.item_id WHERE l.id=? AND l.storage_location=? FOR UPDATE');
            $stmt->execute([$recordId, $location]);
            $stock = $stmt->fetch();
            if (!$stock) {
                throw new RuntimeException('Selected storage room lot was not found.');
            }
            $systemQty = (int) $stock['quantity_available'];
            $variance = $actualQty - $systemQty;
            db_statement('UPDATE item_lots SET quantity_available=?, status=? WHERE id=?', [$actualQty, $actualQty > 0 ? 'AVAILABLE' : 'DEPLETED', $recordId]);
            $itemId = (int) $stock['item_id'];
            $itemBalance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?', [$itemId]);
            db_statement('UPDATE items SET current_stock=?, lot_number=?, expiry_date=?, bin_location=? WHERE id=?', [$itemBalance, $stock['lot_number'], adjustment_nullable_date($stock['expiry_date']), $location, $itemId]);
            $itemName = adjustment_item_name($stock);
            $lotNumber = (string) $stock['lot_number'];
            $expiryDate = adjustment_nullable_date($stock['expiry_date']);
            $balanceQty = $actualQty;
            $fromLocation = $variance >= 0 ? 'PHYSICAL COUNT' : $location;
            $toLocation = $variance >= 0 ? $location : 'PHYSICAL COUNT';
        }

        db_statement(
            'INSERT INTO stock_adjustments (adjustment_no, location_name, stock_type, source_record_id, item_id, item_name, lot_number, expiry_date, system_quantity, actual_quantity, variance_quantity, reason, adjusted_by, adjusted_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$adjustmentNo, $location, $stockType, $recordId, $itemId, $itemName, $lotNumber, $expiryDate, $systemQty, $actualQty, $variance, $reason, current_user()['full_name'], $adjustedAt]
        );
        if ($location === 'CSSR') {
            db_statement(
                'INSERT INTO bin_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, department, encoded_by, remarks)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$itemId, substr($adjustedAt, 0, 10), $adjustmentNo, 'PHYSICAL COUNT ADJUSTMENT', $lotNumber, $expiryDate, max(0, $variance), max(0, -$variance), $balanceQty, 'CSSR', current_user()['full_name'], $reason]
            );
        } else {
            db_statement(
                'INSERT INTO stock_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, from_location, to_location, encoded_by, remarks)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$itemId, $adjustedAt, $adjustmentNo, 'PHYSICAL COUNT ADJUSTMENT', $lotNumber, $expiryDate, max(0, $variance), max(0, -$variance), $balanceQty, $fromLocation, $toLocation, current_user()['full_name'], $reason]
            );
        }

        log_activity('UPDATE', 'Stock Adjustment', $adjustmentNo . ' adjusted ' . $itemName . ' at ' . $location . ' from ' . $systemQty . ' to ' . $actualQty . '.');
        db()->commit();
        set_flash('success', 'Stock adjustment posted. Variance: ' . $variance . '.');
        redirect('stock_adjustment.php');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        set_flash('danger', safe_error_message($e));
        redirect('stock_adjustment.php');
    }
}

$targets = [];
$storageRows = db_all(
    'SELECT l.id, l.item_id, l.lot_number, l.expiry_date, l.storage_location, l.quantity_available, i.item_name, i.item_code, i.item_description, i.unit, i.item_image
     FROM item_lots l
     JOIN items i ON i.id=l.item_id
     WHERE l.storage_location IN ("CSSR","STORAGE ROOM 1","STORAGE ROOM 2","STORAGE ROOM 3")
     ORDER BY l.storage_location, i.item_name, l.lot_number'
);
foreach ($storageRows as $row) {
    $targets[] = [
        'location' => $row['storage_location'],
        'stock_type' => 'STORAGE',
        'record_id' => (int) $row['id'],
        'item_id' => (int) $row['item_id'],
        'item_name' => adjustment_item_name($row),
        'lot_number' => $row['lot_number'] ?: 'N/A',
        'expiry_date' => $row['expiry_date'] ?: '',
        'quantity' => (int) $row['quantity_available'],
        'unit' => $row['unit'] ?: '',
        'item_image' => $row['item_image'] ?: '',
    ];
}
$cssrRows = db_all(
    'SELECT s.id, s.item_id, s.lot_number, s.expiry_date, s.quantity, s.unit_type, s.item_name, s.item_description, i.item_name AS master_item_name, i.item_code, i.unit, i.item_image
     FROM cssr_available_stock s
     JOIN items i ON i.id=s.item_id
     WHERE s.status<>"DELETED"
     ORDER BY s.item_name, s.lot_number'
);
foreach ($cssrRows as $row) {
    $targets[] = [
        'location' => 'CSSR',
        'stock_type' => 'CSSR',
        'record_id' => (int) $row['id'],
        'item_id' => (int) $row['item_id'],
        'item_name' => adjustment_item_name($row),
        'lot_number' => $row['lot_number'] ?: 'N/A',
        'expiry_date' => $row['expiry_date'] ?: '',
        'quantity' => (int) $row['quantity'],
        'unit' => $row['unit_type'] ?: ($row['unit'] ?: ''),
        'item_image' => $row['item_image'] ?: '',
    ];
}

$activeItems = db_all('SELECT id, item_name, item_code, item_description, unit, category FROM items WHERE status="Active" ORDER BY item_name');
foreach ($activeItems as &$activeItem) $activeItem['item_name'] = adjustment_item_name($activeItem);
unset($activeItem);

$recentAdjustments = db_all('SELECT * FROM stock_adjustments ORDER BY adjusted_at DESC, id DESC LIMIT 100');

include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel">
    <div class="pos-panel-title">
        <div><span class="eyebrow">Physical Count</span><h3>Stock Adjustment</h3></div>
        <span class="pos-count">Audited</span>
    </div>
    <form id="adjustmentForm" method="post" data-confirm="custom">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="post_adjustment">
        <input type="hidden" name="stock_type" id="adjustmentStockType">
        <input type="hidden" name="source_record_id" id="adjustmentRecordId">
        <input type="hidden" name="pending_item_id" id="adjustmentPendingItemId">
        <div class="physical-count-form">
            <div class="physical-count-left">
                <div class="adjustment-field adjustment-location-field">
                    <label class="form-label required">Location</label>
                    <select class="form-select" name="location_name" id="adjustmentLocation" required>
                        <?php foreach ($locations as $location): ?><option value="<?= e($location) ?>"><?= e($location) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="adjustment-field adjustment-item-field">
                    <label class="form-label required" for="adjustmentItemPicker">Item Name</label>
                    <input class="form-control" id="adjustmentItemPicker" placeholder="Search item name" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="adjustmentItemSuggestions" required>
                    <input type="hidden" id="adjustmentItem">
                    <div class="adjustment-suggestions" id="adjustmentItemSuggestions" role="listbox" aria-label="Items"></div>
                </div>
                <div class="adjustment-field adjustment-lot-field">
                    <label class="form-label required">Lot Number</label>
                    <select class="form-select" id="adjustmentLot" required></select>
                </div>
                <div class="adjustment-field adjustment-expiry-field">
                    <label class="form-label">Expiration Date</label>
                    <input class="form-control" id="adjustmentExpiry" readonly>
                </div>
            </div>
            <div class="physical-count-right">
                <div class="adjustment-count-grid">
                    <div class="adjustment-field"><label class="form-label">System Quantity</label><input class="form-control" id="adjustmentSystemQty" readonly></div>
                    <div class="adjustment-field"><label class="form-label required">Actual Count</label><input class="form-control" type="number" min="0" name="actual_quantity" id="adjustmentActualQty" required></div>
                    <div class="adjustment-field"><label class="form-label">Variance</label><input class="form-control" id="adjustmentVariance" readonly></div>
                    <div class="adjustment-field"><label class="form-label">Unit</label><input class="form-control" id="adjustmentUnit" readonly></div>
                </div>
                <div class="adjustment-field adjustment-reason-field">
                    <label class="form-label required">Reason / Remarks</label>
                    <textarea class="form-control" name="reason" id="adjustmentReason" rows="3" required></textarea>
                </div>
                <div class="modal-form-actions adjustment-actions">
                    <button class="btn btn-light" type="reset">Clear</button>
                    <button class="btn btn-primary">Post Adjustment</button>
                </div>
            </div>
        </div>
    </form>
</section>

<section class="page-panel">
    <h3>Recent Adjustments</h3>
    <div class="table-responsive">
        <table class="table" data-page-size="15">
            <thead><tr><th>No.</th><th>Date</th><th>Location</th><th>Item Name</th><th>Lot</th><th>System</th><th>Actual</th><th>Variance</th><th>Adjusted By</th><th>Reason</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($recentAdjustments as $row): ?>
                <tr>
                    <td><strong><?= e($row['adjustment_no']) ?></strong></td>
                    <td><?= e($row['adjusted_at']) ?></td>
                    <td><?= e($row['location_name']) ?></td>
                    <td><?= e($row['item_name']) ?></td>
                    <td><?= e($row['lot_number'] ?: 'N/A') ?></td>
                    <td><?= (int) $row['system_quantity'] ?></td>
                    <td><?= (int) $row['actual_quantity'] ?></td>
                    <td><?= (int) $row['variance_quantity'] ?></td>
                    <td><?= e($row['adjusted_by']) ?></td>
                    <td><?= e($row['reason']) ?></td>
                    <td>
                        <form method="post" class="inline-delete-form" data-adjustment-delete-form>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_adjustment">
                            <input type="hidden" name="adjustment_id" value="<?= (int) $row['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$recentAdjustments): ?><tr><td colspan="11" class="text-center">No stock adjustments yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
const adjustmentTargets = <?= json_encode($targets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const adjustmentItems = <?= json_encode($activeItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const pendingLotNumber = <?= json_encode(PENDING_LOT_NUMBER) ?>;
const adjustmentLocation = document.getElementById('adjustmentLocation');
const adjustmentItemPicker = document.getElementById('adjustmentItemPicker');
const adjustmentItemSuggestions = document.getElementById('adjustmentItemSuggestions');
const adjustmentItem = document.getElementById('adjustmentItem');
const adjustmentLot = document.getElementById('adjustmentLot');
const adjustmentActualQty = document.getElementById('adjustmentActualQty');
function adjustmentEsc(value){return String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
function adjustmentSearchText(value){
    return String(value ?? '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, ' ')
        .replace(/\bCATH\b/g, ' CATHETER ')
        .replace(/\bCAN\b/g, ' CATHETER ')
        .replace(/\bG\s*(\d+)\b/g, ' GAUGE $1 ')
        .replace(/\s+/g, ' ')
        .trim();
}
function adjustmentMatchesItem(itemName, typed){
    const itemText = adjustmentSearchText(itemName);
    const searchText = adjustmentSearchText(typed);
    if (!searchText) return true;
    const itemTokens = itemText.split(' ');
    return searchText.split(' ').every(token => itemTokens.some(itemToken => itemToken.startsWith(token)));
}
function targetKey(target){return target.stock_type + ':' + Number(target.record_id);}
function selectedAdjustmentTarget(){
    if (adjustmentLot.value.startsWith('PENDING:')) {
        const item = adjustmentItems.find(row => String(row.id) === adjustmentLot.value.substring(8));
        const pendingStockType = adjustmentLocation.value === 'CSSR' ? 'CSSR' : 'STORAGE';
        const existing = adjustmentTargets.find(target => target.location === adjustmentLocation.value && target.stock_type === pendingStockType && String(target.item_id) === String(item?.id) && String(target.lot_number).toUpperCase() === pendingLotNumber);
        return item ? {
            location: adjustmentLocation.value,
            stock_type: 'PENDING',
            record_id: existing ? Number(existing.record_id) : 0,
            item_id: Number(item.id),
            item_name: item.item_name,
            lot_number: pendingLotNumber,
            expiry_date: '',
            quantity: existing ? Number(existing.quantity || 0) : 0,
            unit: item.unit || ''
        } : undefined;
    }
    return adjustmentTargets.find(target => targetKey(target) === adjustmentLot.value);
}
function itemKey(target){
    return String(target.item_id);
}
function adjustmentLocationItems(){
    const location = adjustmentLocation.value;
    const seen = new Set();
    const existing = adjustmentTargets.filter(target => target.location === location).map(target => ({
        id: target.item_id,
        item_name: target.item_name
    }));
    return existing.concat(adjustmentItems).filter(item => {
        const key = String(item.id);
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
}
function renderAdjustmentItems(){
    adjustmentItem.value = '';
    adjustmentItemPicker.value = '';
    closeAdjustmentSuggestions();
    renderAdjustmentLots();
}
function selectAdjustmentItem(itemId){
    const item = adjustmentLocationItems().find(row => String(row.id) === String(itemId));
    adjustmentItem.value = String(itemId || '');
    adjustmentItemPicker.value = item ? item.item_name : '';
    closeAdjustmentSuggestions();
    adjustmentActualQty.value = '';
    renderAdjustmentLots();
}
function renderAdjustmentSuggestions(showAll = false){
    const typed = adjustmentItemPicker.value.trim();
    if (typed === '' && !showAll) {
        closeAdjustmentSuggestions();
        return;
    }
    const items = adjustmentLocationItems().filter(item => showAll || adjustmentMatchesItem(item.item_name, typed));
    adjustmentItemSuggestions.innerHTML = items.length
        ? items.map(item => `<button type="button" class="adjustment-suggestion" id="adjustment-option-${Number(item.id)}" role="option" aria-selected="false" tabindex="-1" onclick="selectAdjustmentItem(${Number(item.id)})">${adjustmentEsc(item.item_name)}</button>`).join('')
        : `<div class="adjustment-suggestion empty">No matching items found in ${adjustmentEsc(adjustmentLocation.value)}</div>`;
    adjustmentItemPicker.setAttribute('aria-expanded', 'true');
    adjustmentItemPicker.removeAttribute('aria-activedescendant');
}
function closeAdjustmentSuggestions(){
    adjustmentItemSuggestions.innerHTML = '';
    adjustmentItemPicker.setAttribute('aria-expanded', 'false');
    adjustmentItemPicker.removeAttribute('aria-activedescendant');
}
function syncAdjustmentItemPicker(){
    const typed = adjustmentItemPicker.value.trim();
    const match = adjustmentLocationItems().find(item => adjustmentSearchText(item.item_name) === adjustmentSearchText(typed))
        || adjustmentLocationItems().find(item => adjustmentMatchesItem(item.item_name, typed));
    adjustmentItem.value = match ? String(match.id) : '';
    adjustmentActualQty.value = '';
    renderAdjustmentSuggestions();
    renderAdjustmentLots();
}
function renderAdjustmentLots(){
    const location = adjustmentLocation.value;
    const selectedItemId = adjustmentItem.value;
    const lots = adjustmentTargets.filter(target => target.location === location && String(target.item_id) === selectedItemId);
    const displayLots = lots.filter(target => String(target.lot_number).toUpperCase() !== pendingLotNumber);
    const pendingStockType = location === 'CSSR' ? 'CSSR' : 'STORAGE';
    const pendingTarget = lots.find(target => target.stock_type === pendingStockType && String(target.lot_number).toUpperCase() === pendingLotNumber);
    const pendingOption = selectedItemId ? `<option value="PENDING:${Number(selectedItemId)}">${adjustmentEsc(pendingLotNumber)} | Qty: ${Number((pendingTarget || {}).quantity || 0)}</option>` : '';
    adjustmentLot.innerHTML = '<option value="">Select lot number</option>' + (selectedItemId ? displayLots.map(target => `<option value="${adjustmentEsc(targetKey(target))}">${adjustmentEsc(target.lot_number)} | Qty: ${Number(target.quantity)}</option>`).join('') + pendingOption : '<option value="" disabled>Select item first</option>');
    fillAdjustmentFields();
}
function fillAdjustmentFields(){
    const target = selectedAdjustmentTarget() || {};
    document.getElementById('adjustmentStockType').value = target.stock_type || '';
    document.getElementById('adjustmentRecordId').value = target.record_id || '';
    document.getElementById('adjustmentPendingItemId').value = target.stock_type === 'PENDING' ? target.item_id : '';
    document.getElementById('adjustmentExpiry').value = target.expiry_date || '';
    document.getElementById('adjustmentSystemQty').value = target.quantity ?? '';
    document.getElementById('adjustmentUnit').value = target.unit || '';
    if (target.quantity !== undefined && adjustmentActualQty.value === '') {
        adjustmentActualQty.value = Number(target.quantity);
    }
    updateVariance();
}
function updateVariance(){
    const target = selectedAdjustmentTarget();
    const variance = target ? Number(adjustmentActualQty.value || 0) - Number(target.quantity || 0) : '';
    document.getElementById('adjustmentVariance').value = variance;
}
adjustmentLocation.addEventListener('change', function(){adjustmentActualQty.value='';renderAdjustmentItems();});
adjustmentItemPicker.addEventListener('input', syncAdjustmentItemPicker);
adjustmentItemPicker.addEventListener('focus', function(){renderAdjustmentSuggestions(true);});
adjustmentItemPicker.addEventListener('click', function(){renderAdjustmentSuggestions(true);});
adjustmentItemSuggestions.addEventListener('mousedown', event => event.preventDefault());
adjustmentItemPicker.addEventListener('keydown', function(event){
    if (event.key === 'Escape' || event.key === 'Tab') {
        closeAdjustmentSuggestions();
        if (event.key === 'Escape') event.preventDefault();
        return;
    }
    if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
    if (event.key !== 'Enter' && adjustmentItemPicker.getAttribute('aria-expanded') !== 'true') renderAdjustmentSuggestions(!adjustmentItemPicker.value.trim());
    const options = [...adjustmentItemSuggestions.querySelectorAll('[role="option"]')];
    const index = options.findIndex(option => option.id === adjustmentItemPicker.getAttribute('aria-activedescendant'));
    if (event.key === 'Enter') {
        if (adjustmentItemPicker.getAttribute('aria-expanded') === 'true') event.preventDefault();
        if (index >= 0) options[index].click();
        return;
    }
    event.preventDefault();
    if (!options.length) return;
    const next = index < 0 ? (event.key === 'ArrowDown' ? 0 : options.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
    options.forEach((option, i) => option.setAttribute('aria-selected', String(i === next)));
    adjustmentItemPicker.setAttribute('aria-activedescendant', options[next].id);
    options[next].scrollIntoView({block:'nearest'});
});
document.addEventListener('click', function(event){
    if (!event.target.closest('#adjustmentItemPicker') && !event.target.closest('#adjustmentItemSuggestions')) {
        closeAdjustmentSuggestions();
    }
});
adjustmentLot.addEventListener('change', function(){adjustmentActualQty.value='';fillAdjustmentFields();});
adjustmentActualQty.addEventListener('input', updateVariance);
document.getElementById('adjustmentForm').addEventListener('submit', function(event){
    const target = selectedAdjustmentTarget();
    if (!target) {
        event.preventDefault();
        showToastMessage('warning', 'Select an item lot to adjust.');
        return;
    }
    if (document.getElementById('adjustmentReason').value.trim() === '') {
        event.preventDefault();
        showToastMessage('warning', 'Reason is required for audit trail.');
        return;
    }
    event.preventDefault();
    showConfirm('Post physical count adjustment for ' + target.item_name + '?', function(){
        const form = document.getElementById('adjustmentForm');
        form.dataset.confirm = 'off';
        startLoading('Posting adjustment...');
        form.submit();
    });
});
document.querySelectorAll('[data-adjustment-delete-form]').forEach(function(form){
    form.addEventListener('submit', function(event){
        event.preventDefault();
        showConfirm('Delete this adjustment history? This will not reverse the stock quantity.', function(){
            form.dataset.confirm = 'off';
            startLoading('Deleting adjustment history...');
            form.submit();
        });
    });
});
renderAdjustmentItems();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
