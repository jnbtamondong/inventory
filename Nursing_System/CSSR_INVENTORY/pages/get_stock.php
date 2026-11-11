<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can_transact()) {
    http_response_code(403);
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger">Access denied for your role.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = 'Get Stock / Withdrawal';
$activePage = 'get_stock';
$purposeOptions = [
    'FOR CSSR AVAILABLE STOCK',
    'FOR ISSUANCE',
    'FOR STERILIZATION PREPARATION',
    'FOR DEPARTMENT REQUEST',
    'FOR EMERGENCY USE',
    'FOR REPACKING',
    'FOR INSPECTION',
    'OTHER',
];
$destinationOptions = [
    'CSSR',
    'STORAGE ROOM 1',
    'STORAGE ROOM 2',
    'STORAGE ROOM 3',
];
$sourceOptions = $destinationOptions;

function next_withdrawal_number(): string
{
    return 'WD-' . date('Ymd-His');
}

function split_location(?string $locationCode): array
{
    $parts = preg_split('/[-\/\s]+/', trim((string) $locationCode)) ?: [];
    return [
        'rack' => $parts[0] ?? '',
        'shelf' => $parts[1] ?? '',
        'bin' => $parts[2] ?? '',
    ];
}

function nullable_date(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' || $value === '0000-00-00' ? null : $value;
}

$receipt = $_SESSION['last_withdrawal_receipt'] ?? null;
unset($_SESSION['last_withdrawal_receipt']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'post_withdrawal';
    try {
        if ($action === 'void_withdrawal') {
            $withdrawalId = (int) ($_POST['withdrawal_id'] ?? 0);
            $reason = trim((string) ($_POST['cancel_reason'] ?? ''));
            if ($withdrawalId <= 0) {
                throw new RuntimeException('Invalid withdrawal record.');
            }
            if ($reason === '') {
                throw new RuntimeException('Cancellation reason is required.');
            }

            db()->beginTransaction();
            $stmt = db()->prepare('SELECT * FROM stock_withdrawals WHERE id=? FOR UPDATE');
            $stmt->execute([$withdrawalId]);
            $withdrawal = $stmt->fetch();
            if (!$withdrawal || $withdrawal['status'] !== 'POSTED') {
                throw new RuntimeException('Only posted withdrawals can be cancelled or voided.');
            }

            $qty = (int) $withdrawal['quantity_taken'];
            $itemId = (int) $withdrawal['item_id'];
            $stmt = db()->prepare('SELECT * FROM items WHERE id=? FOR UPDATE');
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            if (!$item) {
                throw new RuntimeException('The source item is no longer available.');
            }

            $restoredBalance = (int) $item['current_stock'] + $qty;
            $destinationName = 'CSSR';
            $destinationRecordId = null;
            if ((int) ($withdrawal['added_to_cssr_stock'] ?? 0) === 1) {
                $stmt = db()->prepare('SELECT * FROM cssr_available_stock WHERE id=? FOR UPDATE');
                $stmt->execute([(int) $withdrawal['cssr_stock_id']]);
                $cssrStock = $stmt->fetch();
                if (!$cssrStock || (int) $cssrStock['quantity'] < $qty) {
                    throw new RuntimeException('CSSR stock is lower than the withdrawal quantity. Void cannot continue.');
                }
                $destinationRecordId = (int) $cssrStock['id'];
                $cssrBalance = (int) $cssrStock['quantity'] - $qty;
                db_statement(
                    'UPDATE cssr_available_stock SET quantity=?, status=?, updated_at=NOW() WHERE id=?',
                    [$cssrBalance, $cssrBalance > 0 ? 'AVAILABLE' : 'EMPTY', $destinationRecordId]
                );
            } else {
                $movement = db_one(
                    'SELECT * FROM stock_location_movements WHERE reference_no=? AND status="POSTED" ORDER BY id DESC LIMIT 1',
                    [$withdrawal['withdrawal_number']]
                );
                $destinationName = strtoupper(trim((string) ($movement['to_location_code'] ?? '')));
                $destinationRecordId = (int) ($movement['to_location_id'] ?? 0);
                if ($destinationName === '' || $destinationRecordId <= 0) {
                    throw new RuntimeException('Destination storage room record was not found. Void cannot continue.');
                }
                $stmt = db()->prepare('SELECT * FROM item_lots WHERE id=? AND item_id=? FOR UPDATE');
                $stmt->execute([$destinationRecordId, $itemId]);
                $destinationLot = $stmt->fetch();
                if (!$destinationLot || (int) $destinationLot['quantity_available'] < $qty) {
                    throw new RuntimeException($destinationName . ' stock is lower than the withdrawal quantity. Void cannot continue.');
                }
                $lotReceived = max(0, (int) $destinationLot['quantity_received'] - $qty);
                $lotAvailable = max(0, (int) $destinationLot['quantity_available'] - $qty);
                db_statement(
                    'UPDATE item_lots SET quantity_received=?, quantity_available=?, status=? WHERE id=?',
                    [$lotReceived, $lotAvailable, $lotAvailable > 0 ? 'AVAILABLE' : 'DEPLETED', $destinationRecordId]
                );
            }
            db_statement('UPDATE items SET current_stock=? WHERE id=?', [$restoredBalance, $itemId]);
            db_statement(
                'UPDATE stock_withdrawals SET status="VOID", cancelled_at=NOW(), cancelled_by=?, cancel_reason=? WHERE id=?',
                [current_user()['full_name'], $reason, $withdrawalId]
            );

            $processedAt = date('Y-m-d H:i:s');
            db_statement(
                'INSERT INTO stock_location_movements (item_id, movement_type, reference_no, lot_number, expiry_date, quantity, from_location_id, from_location_code, to_location_id, to_location_code, processed_by, processed_at, remarks, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$itemId, 'VOID STORAGE ROOM RELEASE FROM ' . $destinationName, $withdrawal['withdrawal_number'], $withdrawal['lot_number'], nullable_date($withdrawal['expiry_date']), $qty, $destinationRecordId, $destinationName, $itemId, $withdrawal['from_location_code'], current_user()['full_name'], $processedAt, $reason, 'VOID']
            );
            db_statement(
                'INSERT INTO bin_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, department, encoded_by, remarks)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$itemId, date('Y-m-d'), $withdrawal['withdrawal_number'], 'VOID STORAGE ROOM RELEASE', $withdrawal['lot_number'], nullable_date($withdrawal['expiry_date']), $qty, 0, $restoredBalance, 'Storage Room', current_user()['full_name'], $reason]
            );
            db_statement(
                'INSERT INTO stock_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, from_location, to_location, encoded_by, remarks)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$itemId, $processedAt, $withdrawal['withdrawal_number'], 'VOID STORAGE ROOM RELEASE', $withdrawal['lot_number'], nullable_date($withdrawal['expiry_date']), $qty, 0, $restoredBalance, $destinationName, $withdrawal['from_location_code'], current_user()['full_name'], $reason]
            );

            log_activity('VOID', 'Get Stock / Withdrawal', $withdrawal['withdrawal_number'] . ' voided. ' . $reason);
            db()->commit();
            set_flash('success', 'Withdrawal voided. Stock was restored to Storage Room and deducted from ' . $destinationName . '.');
            redirect('get_stock.php#withdrawalHistory');
        }

        $withdrawalNumber = trim((string) ($_POST['withdrawal_number'] ?? ''));
        $withdrawalDateTime = trim((string) ($_POST['withdrawal_date_time'] ?? ''));
        $purpose = trim((string) ($_POST['purpose'] ?? ''));
        $destination = strtoupper(trim((string) ($_POST['to_location'] ?? 'CSSR')));
        $takenBy = trim((string) ($_POST['taken_by'] ?? ''));
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $cartJson = trim((string) ($_POST['cart_json'] ?? ''));
        $cartRows = [];
        if ($cartJson !== '') {
            $decoded = json_decode($cartJson, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid selected item list.');
            }
            foreach ($decoded as $line) {
                $lineItemId = (int) ($line['id'] ?? 0);
                $lineQty = (int) ($line['qty'] ?? 0);
                $lineSource = strtoupper(trim((string) ($line['source_location'] ?? '')));
                $lineLotId = (int) ($line['lot_id'] ?? 0);
                if ($lineItemId <= 0 || $lineQty <= 0) {
                    continue;
                }
                if ($lineLotId <= 0) {
                    throw new RuntimeException('Lot number is required for every selected item.');
                }
                if (!in_array($lineSource, $sourceOptions, true)) {
                    throw new RuntimeException('Invalid source storage room.');
                }
                $cartKey = $lineItemId . '|' . $lineSource . '|' . $lineLotId;
                if (!isset($cartRows[$cartKey])) {
                    $cartRows[$cartKey] = ['id' => $lineItemId, 'qty' => 0, 'source_location' => $lineSource, 'lot_id' => $lineLotId];
                }
                $cartRows[$cartKey]['qty'] += $lineQty;
            }
            $cartRows = array_values($cartRows);
        } else {
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $qty = (int) ($_POST['quantity_taken'] ?? 0);
            $source = strtoupper(trim((string) ($_POST['source_location'] ?? '')));
            if ($itemId > 0 && $qty > 0) {
                if (!in_array($source, $sourceOptions, true)) {
                    throw new RuntimeException('Invalid source storage room.');
                }
                $cartRows[] = ['id' => $itemId, 'qty' => $qty, 'source_location' => $source];
            }
        }

        if ($withdrawalNumber === '' || $withdrawalDateTime === '' || !$cartRows || $takenBy === '') {
            throw new RuntimeException('Withdrawal number, date/time, selected items, quantity, and taken by are required.');
        }
        if (!in_array($purpose, $purposeOptions, true)) {
            throw new RuntimeException('Invalid purpose.');
        }
        if (!in_array($destination, $destinationOptions, true)) {
            throw new RuntimeException('Invalid destination.');
        }
        $postedAt = str_replace('T', ' ', $withdrawalDateTime);
        if (strlen($postedAt) === 16) {
            $postedAt .= ':00';
        }

        db()->beginTransaction();
        $receiptItems = [];
        $totalQty = 0;
        $lineCount = count($cartRows);
        foreach ($cartRows as $lineIndex => $cartLine) {
            $itemId = (int) $cartLine['id'];
            $qty = (int) $cartLine['qty'];
            $sourceLocation = strtoupper(trim((string) ($cartLine['source_location'] ?? '')));
            $selectedLotId = (int) ($cartLine['lot_id'] ?? 0);
            $lineWithdrawalNumber = $lineCount > 1 ? $withdrawalNumber . '-' . ($lineIndex + 1) : $withdrawalNumber;
            if (!in_array($sourceLocation, $sourceOptions, true)) {
                throw new RuntimeException('Invalid source storage room.');
            }
            if ($selectedLotId <= 0) {
                throw new RuntimeException('Lot number is required for ' . $lineWithdrawalNumber . '.');
            }
            if ($sourceLocation === $destination) {
                throw new RuntimeException('Source and Add To storage room cannot be the same for ' . $lineWithdrawalNumber . '.');
            }

            $stmt = db()->prepare('SELECT * FROM items WHERE id=? AND status="Active" FOR UPDATE');
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            if (!$item) {
                throw new RuntimeException('Selected item is no longer active.');
            }

            $sourceLotStmt = db()->prepare('SELECT * FROM item_lots WHERE id=? AND item_id=? AND storage_location=? AND quantity_available>0 AND lot_number<>"UNIDENTIFIED / PENDING" FOR UPDATE');
            $sourceLotStmt->execute([$selectedLotId, $itemId, $sourceLocation]);
            $sourceLot = $sourceLotStmt->fetch();
            if (!$sourceLot) {
                throw new RuntimeException('Selected lot is no longer available for ' . $item['item_description'] . '.');
            }

            $available = (int) $sourceLot['quantity_available'];
            if ($qty > $available) {
                throw new RuntimeException('Quantity taken for ' . $item['item_description'] . ' cannot be greater than available stock in lot ' . $sourceLot['lot_number'] . '.');
            }

            $destinationRecordId = null;
            $destinationName = $destination;
            $take = $qty;
            $sourceLotBalance = (int) $sourceLot['quantity_available'] - $take;
            db_statement(
                'UPDATE item_lots SET quantity_issued=quantity_issued+?, quantity_available=?, status=? WHERE id=?',
                [$take, $sourceLotBalance, $sourceLotBalance > 0 ? 'AVAILABLE' : 'DEPLETED', (int) $sourceLot['id']]
            );

            if ($destination === 'CSSR') {
                $stmt = db()->prepare(
                    'SELECT * FROM cssr_available_stock
                     WHERE item_id=? AND lot_number <=> ? AND expiry_date <=> ? AND unit_type <=> ?
                     ORDER BY id ASC LIMIT 1 FOR UPDATE'
                );
                $stmt->execute([$itemId, $sourceLot['lot_number'], nullable_date($sourceLot['expiry_date']), $item['unit']]);
                $cssrStock = $stmt->fetch();
                if ($cssrStock) {
                    $destinationRecordId = (int) $cssrStock['id'];
                    db_statement(
                        'UPDATE cssr_available_stock
                         SET quantity=quantity+?, item_name=?, item_description=?, classification=?, procurement_type=?, source_location_id=?, source_location_code=?, status="AVAILABLE", updated_at=NOW()
                         WHERE id=?',
                        [$take, $item['item_description'], $item['item_description'], $item['category'], $sourceLot['supplier'], (int) $sourceLot['id'], $sourceLocation, $destinationRecordId]
                    );
                } else {
                    db_statement(
                        'INSERT INTO cssr_available_stock (item_id, item_name, item_description, lot_number, expiry_date, quantity, unit_type, classification, procurement_type, source_location_id, source_location_code, status, created_by)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        [$itemId, $item['item_description'], $item['item_description'], $sourceLot['lot_number'], nullable_date($sourceLot['expiry_date']), $take, $item['unit'], $item['category'], $sourceLot['supplier'], (int) $sourceLot['id'], $sourceLocation, 'AVAILABLE', current_user()['full_name']]
                    );
                    $destinationRecordId = (int) db()->lastInsertId();
                }
            } else {
                $stmt = db()->prepare(
                    'SELECT * FROM item_lots
                     WHERE item_id=? AND lot_number <=> ? AND expiry_date <=> ? AND brand=? AND supplier=? AND storage_location=? AND unit_price <=> ?
                     ORDER BY id ASC LIMIT 1 FOR UPDATE'
                );
                $stmt->execute([$itemId, $sourceLot['lot_number'], nullable_date($sourceLot['expiry_date']), (string) ($sourceLot['brand'] ?? ''), (string) $sourceLot['supplier'], $destination, $sourceLot['unit_price']]);
                $destinationLot = $stmt->fetch();
                if ($destinationLot) {
                    $destinationRecordId = (int) $destinationLot['id'];
                    db_statement(
                        'UPDATE item_lots SET quantity_received=quantity_received+?, quantity_available=quantity_available+?, status="AVAILABLE" WHERE id=?',
                        [$take, $take, $destinationRecordId]
                    );
                } else {
                    db_statement(
                        'INSERT INTO item_lots (item_id, lot_number, expiry_date, brand, supplier, storage_location, unit_price, quantity_received, quantity_available, status)
                         VALUES (?,?,?,?,?,?,?,?,?,"AVAILABLE")',
                        [$itemId, (string) $sourceLot['lot_number'], nullable_date($sourceLot['expiry_date']), (string) ($sourceLot['brand'] ?? ''), (string) $sourceLot['supplier'], $destination, $sourceLot['unit_price'], $take, $take]
                    );
                    $destinationRecordId = (int) db()->lastInsertId();
                }
            }
            $storageBalance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=? AND storage_location=?', [$itemId, $sourceLocation]);
            $itemBalance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?', [$itemId]);
            db_statement('UPDATE items SET current_stock=?, lot_number=?, expiry_date=?, bin_location=? WHERE id=?', [
                $itemBalance,
                $sourceLot['lot_number'],
                nullable_date($sourceLot['expiry_date']),
                $sourceLocation,
                $itemId,
            ]);
            $lotSummary = (string) ($sourceLot['lot_number'] ?: 'N/A');
            $expirySummary = (string) ($sourceLot['expiry_date'] ?: '');
            $primaryExpiry = nullable_date($sourceLot['expiry_date']);

            db_statement(
                'INSERT INTO stock_withdrawals (withdrawal_number, withdrawal_date_time, item_id, item_name, item_description, lot_number, expiry_date, quantity_taken, unit_type, from_rack_number, from_shelf_number, from_bin_number, from_location_code, purpose, taken_by, remarks, added_to_cssr_stock, cssr_stock_id, posted_date_time, posted_by, status, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$lineWithdrawalNumber, $postedAt, $itemId, $item['item_description'], $item['item_description'], $lotSummary, nullable_date($primaryExpiry), $qty, $item['unit'], '', '', '', $sourceLocation, $purpose, $takenBy, $remarks, $destination === 'CSSR' ? 1 : 0, $destination === 'CSSR' ? $destinationRecordId : null, date('Y-m-d H:i:s'), current_user()['full_name'], 'POSTED', current_user()['id']]
            );

            db_statement(
                'INSERT INTO stock_location_movements (item_id, movement_type, reference_no, lot_number, expiry_date, quantity, from_location_id, from_location_code, to_location_id, to_location_code, processed_by, processed_at, remarks, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$itemId, 'STORAGE ROOM RELEASE TO ' . $destinationName, $lineWithdrawalNumber, $lotSummary, nullable_date($primaryExpiry), $qty, $itemId, $sourceLocation, $destinationRecordId, $destinationName, current_user()['full_name'], $postedAt, $remarks, 'POSTED']
            );
            if ($destination === 'CSSR') {
                $cssrBalance = (int) db_value('SELECT COALESCE(SUM(quantity),0) FROM cssr_available_stock WHERE item_id=? AND status="AVAILABLE"', [$itemId]);
                db_statement(
                    'INSERT INTO bin_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, department, encoded_by, remarks)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$itemId, date('Y-m-d', strtotime($postedAt)), $lineWithdrawalNumber, 'STORAGE ROOM RELEASE TO CSSR', $lotSummary, nullable_date($primaryExpiry), $qty, 0, $cssrBalance, $sourceLocation, current_user()['full_name'], $remarks]
                );
            }
            db_statement(
                'INSERT INTO stock_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, from_location, to_location, encoded_by, remarks)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$itemId, $postedAt, $lineWithdrawalNumber, 'STORAGE ROOM RELEASE', $lotSummary, nullable_date($primaryExpiry), 0, $qty, $storageBalance, $sourceLocation, $destinationName, current_user()['full_name'], $remarks]
            );

            $totalQty += $qty;
            $receiptItems[] = [
                'withdrawal_number' => $lineWithdrawalNumber,
                'item_name' => $item['item_description'],
                'lot_number' => $lotSummary,
                'expiry_date' => $expirySummary,
                'quantity' => $qty,
                'unit_type' => $item['unit'],
                'source_location' => $sourceLocation,
                'storage_balance' => $storageBalance,
            ];
        }

        log_activity('POST', 'Get Stock / Withdrawal', $withdrawalNumber . ' moved ' . $totalQty . ' item(s) from Storage Room to ' . $destination . '.');
        db()->commit();

        $_SESSION['last_withdrawal_receipt'] = [
            'withdrawal_number' => $withdrawalNumber,
            'posted_at' => $postedAt,
            'items' => $receiptItems,
            'line_count' => $lineCount,
            'total_quantity' => $totalQty,
            'to_location' => $destination,
            'processed_by' => current_user()['full_name'],
            'purpose' => $purpose,
            'taken_by' => $takenBy,
            'remarks' => $remarks,
        ];
        set_flash('success', 'Release posted. Storage Room stock was deducted and ' . $destination . ' was updated.');
        redirect('get_stock.php');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        set_flash('danger', safe_error_message($e));
        redirect('get_stock.php');
    }
}

$roomPlaceholders = implode(',', array_fill(0, count($sourceOptions), '?'));
$items = db_all(
    "SELECT i.id, i.item_code, i.item_description, i.category, i.unit, i.supplier, i.item_image,
            l.storage_location AS source_location,
            GROUP_CONCAT(DISTINCT l.lot_number ORDER BY CASE WHEN l.expiry_date IS NULL THEN 1 ELSE 0 END, l.expiry_date, l.id SEPARATOR ', ') AS lot_number,
            MIN(l.expiry_date) AS expiry_date,
            l.storage_location AS bin_location,
            COALESCE(SUM(l.quantity_available),0) AS current_stock
     FROM items i
     JOIN item_lots l ON l.item_id=i.id
     WHERE i.status='Active'
       AND l.storage_location IN ($roomPlaceholders)
       AND l.quantity_available > 0
       AND l.lot_number<>'UNIDENTIFIED / PENDING'
     GROUP BY i.id, i.item_code, i.item_description, i.category, i.unit, i.supplier, i.item_image, l.storage_location
     ORDER BY l.storage_location, i.item_description",
    $sourceOptions
);
$lotRows = db_all(
    "SELECT l.id, l.item_id, l.lot_number, l.expiry_date, l.storage_location AS source_location, l.quantity_available, l.lot_image
     FROM item_lots l
     JOIN items i ON i.id=l.item_id
     WHERE i.status='Active'
       AND l.storage_location IN ($roomPlaceholders)
       AND l.quantity_available > 0
       AND l.lot_number<>'UNIDENTIFIED / PENDING'
     ORDER BY l.item_id, l.storage_location, CASE WHEN l.expiry_date IS NULL THEN 1 ELSE 0 END, l.expiry_date, l.created_at, l.id",
    $sourceOptions
);
$withdrawals = db_all(
    'SELECT w.*,
            COALESCE(
                (SELECT m.to_location_code FROM stock_location_movements m WHERE m.reference_no=w.withdrawal_number AND m.status="POSTED" ORDER BY m.id DESC LIMIT 1),
                CASE WHEN w.added_to_cssr_stock=1 THEN "CSSR" ELSE "" END
            ) AS destination_name
     FROM stock_withdrawals w
     ORDER BY w.created_at DESC
     LIMIT 100'
);
$nowLocal = date('Y-m-d\TH:i');

include __DIR__ . '/../includes/header.php';
?>
<section class="request-shell storage-release-shell">
    <div class="request-left page-panel">
        <div class="pos-panel-title"><div><span class="eyebrow">Storage Room Release</span><h3>Release Items</h3></div><span class="pos-count">Multi-item</span></div>
        <form id="withdrawalForm" method="post" data-confirm="custom">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="post_withdrawal">
            <input type="hidden" name="cart_json" id="withdrawalCartJson">
            <div class="row">
                <div class="col-md-6 mb-2"><label class="form-label required">Withdrawal Number</label><input class="form-control" name="withdrawal_number" value="<?= e(next_withdrawal_number()) ?>" required></div>
                <div class="col-md-6 mb-2"><label class="form-label required">Date and Time</label><input class="form-control" type="datetime-local" name="withdrawal_date_time" value="<?= e($nowLocal) ?>" required></div>
                <div class="col-md-6 mb-2"><label class="form-label required">Taken By</label><input class="form-control" name="taken_by" value="<?= e(current_user()['full_name']) ?>" required></div>
                <div class="col-md-6 mb-2"><label class="form-label required">Purpose</label><select class="form-select" name="purpose" required><?php foreach ($purposeOptions as $purpose): ?><option value="<?= e($purpose) ?>"><?= e($purpose) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6 mb-2">
                    <label class="form-label required">From</label>
                    <select class="form-select" id="withdrawalSource">
                        <?php foreach ($sourceOptions as $sourceOption): ?><option value="<?= e($sourceOption) ?>"><?= e($sourceOption) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-2"><label class="form-label required">Add To</label><select class="form-select" name="to_location" id="withdrawalDestination" required><?php foreach ($destinationOptions as $destinationOption): ?><option value="<?= e($destinationOption) ?>"><?= e($destinationOption) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-12 mb-2"><label class="form-label">Remarks</label><input class="form-control" name="remarks"></div>
            </div>
            <input class="form-control pos-search" id="withdrawalItemSearch" type="search" placeholder="Search item name">
            <div id="withdrawalItemList" class="pos-item-list request-item-list storage-release-item-list"></div>
        </form>
    </div>
    <div class="request-right page-panel">
        <div class="pos-panel-title"><div><span class="eyebrow">Release Cart</span><h3>Selected Items</h3></div><span class="pos-count">Selected</span></div>
        <div id="withdrawalCart" class="pos-cart-list"></div>
        <div class="pos-total-bar">
            <div><span>Lines</span><strong id="withdrawalLines">0</strong></div>
            <div><span>Total Qty</span><strong id="withdrawalQty">0</strong></div>
            <button class="btn btn-light" type="button" onclick="clearWithdrawalCart()">Clear Cart</button>
            <button class="btn btn-primary" type="submit" form="withdrawalForm">Post Release</button>
        </div>
    </div>
</section>

<?php if ($receipt): ?>
<section class="page-panel pos-receipt" id="withdrawalReceipt">
    <div class="receipt-head">
        <div><span class="eyebrow">Printable Withdrawal</span><h3><?= e($receipt['withdrawal_number']) ?></h3></div>
        <button class="btn btn-primary no-print" onclick="printPage()">Print</button>
    </div>
    <div class="receipt-meta">
        <span><strong>Date and Time:</strong> <?= e($receipt['posted_at']) ?></span>
        <span><strong>Lines:</strong> <?= (int) ($receipt['line_count'] ?? count($receipt['items'] ?? [])) ?></span>
        <span><strong>Total Quantity:</strong> <?= (int) ($receipt['total_quantity'] ?? 0) ?></span>
        <span><strong>To:</strong> <?= e($receipt['to_location']) ?></span>
        <span><strong>Processed By:</strong> <?= e($receipt['processed_by']) ?></span>
        <span><strong>Taken By:</strong> <?= e($receipt['taken_by']) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table" data-enhanced="true">
            <thead><tr><th>No.</th><th>From</th><th>Item Name</th><th>Lot Number</th><th>Expiry Date</th><th>Qty</th><th>Storage Balance</th></tr></thead>
            <tbody>
                <?php foreach (($receipt['items'] ?? []) as $receiptItem): ?>
                <tr>
                    <td><?= e($receiptItem['withdrawal_number']) ?></td>
                    <td><?= e($receiptItem['source_location'] ?? 'N/A') ?></td>
                    <td><?= e($receiptItem['item_name']) ?></td>
                    <td><?= e($receiptItem['lot_number']) ?></td>
                    <td><?= e($receiptItem['expiry_date']) ?></td>
                    <td><?= (int) $receiptItem['quantity'] ?> <?= e($receiptItem['unit_type']) ?></td>
                    <td><?= (int) $receiptItem['storage_balance'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="receipt-remarks"><strong>Purpose:</strong> <?= e($receipt['purpose']) ?></p>
    <p class="receipt-remarks"><strong>Remarks:</strong> <?= e($receipt['remarks']) ?></p>
</section>
<?php endif; ?>

<section class="page-panel" id="withdrawalHistory">
    <h3>Withdrawal History</h3>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>No.</th><th>Date and Time</th><th>Item Name</th><th>Lot</th><th>Qty</th><th>To</th><th>Processed By</th><th>Status</th><th class="no-print">Action</th></tr></thead>
            <tbody>
            <?php foreach ($withdrawals as $row): ?>
                <tr>
                    <td><strong><?= e($row['withdrawal_number']) ?></strong></td>
                    <td><?= e($row['withdrawal_date_time']) ?></td>
                    <td><?= e($row['item_name']) ?></td>
                    <td><?= e($row['lot_number']) ?></td>
                    <td><?= (int) $row['quantity_taken'] ?> <?= e($row['unit_type']) ?></td>
                    <td><?= e($row['destination_name'] ?: 'CSSR') ?></td>
                    <td><?= e($row['posted_by']) ?></td>
                    <td><span class="badge <?= $row['status'] === 'POSTED' ? 'status-normal' : 'status-out' ?>"><?= e($row['status']) ?></span></td>
                    <td class="no-print">
                        <?php if ($row['status'] === 'POSTED' && can_manage()): ?>
                            <form method="post" class="inline-form" data-confirm-message="Void this posted withdrawal and reverse stock balances?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="void_withdrawal">
                                <input type="hidden" name="withdrawal_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="cancel_reason" value="Voided by authorized user">
                                <button class="btn btn-sm btn-danger">Void</button>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div id="imageModal" class="modal-backdrop" onclick="closeImageModal()" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true" aria-label="Item image preview" onclick="event.stopPropagation()" style="position:relative;max-width:960px">
        <button type="button" class="btn btn-light" data-image-modal-close onclick="closeImageModal()" aria-label="Close image preview" title="Close (Esc)" style="position:absolute;top:12px;right:12px;z-index:2;font-size:1.25rem;line-height:1">&times;</button>
        <img class="detail-img" src="" alt="Item image preview">
    </div>
</div>

<script>
const withdrawalItems = <?= json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const withdrawalLots = <?= json_encode($lotRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const withdrawalCart = new Map();
const withdrawalUploadBase = '../uploads/items/';
function withdrawalEsc(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}
function withdrawalImg(item, lot = null) {
    const image = lot && lot.lot_image ? lot.lot_image : item.item_image;
    if (!image) {
        return '<div class="pos-item-img pos-img-empty" title="No image uploaded">IMG</div>';
    }
    return `<img class="pos-item-img" src="${withdrawalUploadBase}${withdrawalEsc(image)}" alt="${withdrawalEsc(item.item_description)}" title="Click to preview" role="button" tabindex="0" onclick="event.stopPropagation();openImage(this.src)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();event.stopPropagation();openImage(this.src)}">`;
}
function renderWithdrawalItems() {
    const term = document.getElementById('withdrawalItemSearch').value.toLowerCase().trim();
    const source = document.getElementById('withdrawalSource').value || 'CSSR';
    const list = document.getElementById('withdrawalItemList');
    const filtered = withdrawalItems.filter(item => String(item.source_location || '').toUpperCase() === source && [item.item_description].join(' ').toLowerCase().includes(term));
    list.innerHTML = filtered.map(item => {
        const available = Number(item.current_stock || 0);
        const key = withdrawalCartKey(item.id, item.source_location);
        const selected = withdrawalCart.has(key);
        return `<button type="button" class="pos-item-card ${selected ? 'is-selected' : ''}" onclick="addWithdrawalItem(${Number(item.id)},'${withdrawalEsc(item.source_location || '')}')">
            ${withdrawalImg(item)}
            <span class="pos-item-main"><strong>${withdrawalEsc(item.item_description)}</strong></span>
            <span class="pos-stock"><strong>${available}</strong><small>${withdrawalEsc(item.unit || '')}</small></span>
        </button>`;
    }).join('') || '<div class="pos-empty">No matching items found in ' + withdrawalEsc(source) + '.</div>';
}
function withdrawalCartKey(id, source) {
    return Number(id) + '|' + String(source || '').toUpperCase();
}
function lotsForWithdrawalItem(item) {
    return withdrawalLots.filter(lot => Number(lot.item_id) === Number(item.id) && String(lot.source_location || '').toUpperCase() === String(item.source_location || '').toUpperCase());
}
function selectedWithdrawalLot(item) {
    const lots = lotsForWithdrawalItem(item);
    return lots.find(lot => Number(lot.id) === Number(item.lot_id)) || lots[0] || null;
}
function addWithdrawalItem(id, source) {
    const key = withdrawalCartKey(id, source);
    const item = withdrawalItems.find(row => Number(row.id) === Number(id) && String(row.source_location || '').toUpperCase() === String(source || '').toUpperCase());
    if (!item) {
        return;
    }
    const lot = selectedWithdrawalLot(item);
    const available = Number(lot ? lot.quantity_available : item.current_stock || 0);
    if (available <= 0) {
        showToastMessage('warning', 'This item has no available stock in ' + (item.source_location || 'the selected room') + '.');
        return;
    }
    const existing = withdrawalCart.get(key);
    const nextQty = existing ? Number(existing.qty || 0) + 1 : 1;
    if (nextQty > available) {
        showToastMessage('danger', 'Quantity cannot be greater than available stock in ' + (item.source_location || 'the selected room') + '.');
        return;
    }
    withdrawalCart.set(key, {...item, lot_id: existing ? existing.lot_id : Number(lot.id), qty: nextQty});
    renderWithdrawalCart();
    renderWithdrawalItems();
}
function updateWithdrawalQty(key, qty) {
    const item = withdrawalCart.get(String(key));
    if (!item) {
        return;
    }
    const lot = selectedWithdrawalLot(item);
    const available = Number(lot ? lot.quantity_available : item.current_stock || 0);
    const nextQty = Math.max(1, Number(qty || 1));
    item.qty = Math.min(nextQty, available);
    withdrawalCart.set(String(key), item);
    renderWithdrawalCart();
}
function updateWithdrawalLot(key, lotId) {
    const item = withdrawalCart.get(String(key));
    if (!item) {
        return;
    }
    item.lot_id = Number(lotId || 0);
    const lot = selectedWithdrawalLot(item);
    const available = Number(lot ? lot.quantity_available : 0);
    item.qty = Math.min(Math.max(1, Number(item.qty || 1)), Math.max(available, 1));
    withdrawalCart.set(String(key), item);
    renderWithdrawalCart();
}
function removeWithdrawalItem(key) {
    withdrawalCart.delete(String(key));
    renderWithdrawalCart();
    renderWithdrawalItems();
}
function clearWithdrawalCart() {
    if (withdrawalCart.size === 0) {
        return;
    }
    showConfirm('Remove all items from the release cart?', function(){
        withdrawalCart.clear();
        renderWithdrawalCart();
        renderWithdrawalItems();
    });
}
function renderWithdrawalCart() {
    const rows = Array.from(withdrawalCart.values());
    let total = 0;
    document.getElementById('withdrawalCart').innerHTML = rows.map(item => {
        total += Number(item.qty || 0);
        const lots = lotsForWithdrawalItem(item);
        const lot = selectedWithdrawalLot(item);
        if (lot && !Number(item.lot_id || 0)) {
            item.lot_id = Number(lot.id);
        }
        const available = Number(lot ? lot.quantity_available : 0);
        const overStock = Number(item.qty || 0) > available;
        const key = withdrawalCartKey(item.id, item.source_location);
        const selectedLotId = Number(item.lot_id || (lot ? lot.id : 0));
        const lotOptions = lots.map(lotRow => `<option value="${Number(lotRow.id)}" ${Number(lotRow.id) === selectedLotId ? 'selected' : ''}>${withdrawalEsc(lotRow.lot_number || 'N/A')} | Qty: ${Number(lotRow.quantity_available)}</option>`).join('');
        return `<div class="pos-cart-row ${overStock ? 'over-stock' : ''}">
            ${withdrawalImg(item, lot)}
            <div class="pos-cart-info">
                <strong>${withdrawalEsc(item.item_description)}</strong>
                <small>From: ${withdrawalEsc(item.source_location || 'N/A')} | Available: ${available} ${withdrawalEsc(item.unit || '')}</small>
                ${overStock ? '<em>Quantity exceeds available stock</em>' : ''}
            </div>
            <select class="form-select pos-lot-select" onchange="updateWithdrawalLot('${withdrawalEsc(key)}',this.value)" ${lots.length === 1 ? 'disabled' : ''}>${lotOptions}</select>
            <input class="form-control pos-qty${overStock ? ' is-invalid' : ''}" type="number" min="1" max="${available}" value="${Number(item.qty || 1)}" onchange="updateWithdrawalQty('${withdrawalEsc(key)}',this.value)">
            <button type="button" class="btn btn-light btn-sm" onclick="removeWithdrawalItem('${withdrawalEsc(key)}')">Remove</button>
        </div>`;
    }).join('') || '<div class="pos-empty">Release cart is empty.</div>';
    document.getElementById('withdrawalLines').textContent = rows.length;
    document.getElementById('withdrawalQty').textContent = total;
    document.getElementById('withdrawalCartJson').value = JSON.stringify(rows.map(item => ({id: Number(item.id), lot_id: Number(item.lot_id || 0), qty: Number(item.qty || 1), source_location: String(item.source_location || '')})));
}
document.getElementById('withdrawalItemSearch').addEventListener('input', renderWithdrawalItems);
document.getElementById('withdrawalSource').addEventListener('change', function(){
    document.getElementById('withdrawalItemSearch').value = '';
    withdrawalCart.clear();
    renderWithdrawalCart();
    renderWithdrawalItems();
});
document.getElementById('withdrawalForm').addEventListener('submit', function(event){
    const rows = Array.from(withdrawalCart.values());
    const totalQty = rows.reduce((total, item) => total + Number(item.qty || 0), 0);
    if (rows.length === 0 || totalQty <= 0) {
        event.preventDefault();
        showToastMessage('warning', 'Add at least one item to the release cart.');
        return;
    }
    const overStock = rows.find(item => Number(item.qty || 0) > Number(item.current_stock || 0));
    if (overStock) {
        event.preventDefault();
        showToastMessage('danger', 'Quantity for ' + overStock.item_description + ' cannot be greater than available Storage Room stock.');
        return;
    }
    event.preventDefault();
    const destination = document.getElementById('withdrawalDestination').value || 'CSSR';
    const sameDestination = rows.find(item => String(item.source_location || '').toUpperCase() === String(destination || '').toUpperCase());
    if (sameDestination) {
        showToastMessage('warning', 'Source and Add To storage room cannot be the same for ' + sameDestination.item_description + '.');
        return;
    }
    showConfirm('Post release of ' + rows.length + ' item' + (rows.length === 1 ? '' : 's') + ' with total quantity ' + totalQty + ' to ' + destination + '?', function(){
        const form = document.getElementById('withdrawalForm');
        form.dataset.confirm = 'off';
        startLoading('Posting release...');
        form.submit();
    });
});
renderWithdrawalItems();
renderWithdrawalCart();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
