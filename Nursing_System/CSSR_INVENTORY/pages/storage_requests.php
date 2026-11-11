<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/request-catalog.php';
require_once __DIR__.'/../includes/submission.php';
cssrServeRequestCatalog(true);

$pageTitle = 'Storage Requests';
$pageDescription = 'Request supplies from storage, review quantities, and replenish CSSR stock.';
$activePage = 'storage_requests';
$locations = ['STORAGE ROOM 1', 'STORAGE ROOM 2', 'STORAGE ROOM 3'];
$storageAllowedReturnPages = ['request_queue.php', 'request_history.php'];
$storageReturnPage = in_array(($_GET['return'] ?? ''), $storageAllowedReturnPages, true) ? (string) $_GET['return'] : 'storage_requests.php';
$embedStorageRequest = ($_GET['embed'] ?? '') === '1';
if ($storageReturnPage === 'request_queue.php') {
    $activePage = 'request_queue';
    $pageTitle = 'Release Queue';
} elseif ($storageReturnPage === 'request_history.php') {
    $activePage = 'request_history';
    $pageTitle = 'Request History';
}

function storage_request_nullable_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '' || strtoupper($value) === 'N/A') {
        return null;
    }
    return $value;
}

function next_storage_request_no(?string $requestDate = null): string
{
    $timestamp = $requestDate ? strtotime($requestDate) : time();
    $prefix = 'SRQ-' . date('Y-m', $timestamp) . '-';
    $last = db_value(
        'SELECT request_no FROM storage_requests WHERE request_no LIKE ? ORDER BY request_no DESC LIMIT 1',
        [$prefix . '%']
    );
    $next = $last ? ((int) substr((string) $last, -4)) + 1 : 1;
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['next_request_no'])) {
    header('Content-Type: application/json');
    try {
        echo json_encode(['request_no' => next_storage_request_no(cssrTransactionDate(current_user() ?? [], $_GET['next_request_no']))]);
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo json_encode(['error' => safe_error_message($error)]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    $isStorageAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    try {
        if ($action === 'create_request') {
            $fromLocation = strtoupper(trim((string) ($_POST['from_location'] ?? '')));
            $requestDate = cssrTransactionDate(current_user() ?? [], $_POST['request_date'] ?? '');
            // These values are system-controlled so a request always identifies
            // the signed-in requester and the standard storage-request purpose.
            $requestedBy = trim((string) (current_user()['full_name'] ?? ''));
            $purpose = 'Replenishment';
            $remarks = trim((string) ($_POST['remarks'] ?? ''));
            $cartJson = trim((string) ($_POST['cart_json'] ?? ''));
            $cartRows = json_decode($cartJson, true);

            if (!in_array($fromLocation, $locations, true) || $requestDate === '' || $requestedBy === '' || !is_array($cartRows) || !$cartRows) {
                throw new RuntimeException('Storage room, request date, requested by, and requested items are required.');
            }

            $lines = [];
            foreach ($cartRows as $line) {
                $itemId = (int) ($line['id'] ?? 0);
                $qty = filter_var($line['qty'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }
                $lines[$itemId] = ($lines[$itemId] ?? 0) + $qty;
            }
            if (!$lines) {
                throw new RuntimeException('Add at least one requested item.');
            }

            $submissionPayload=[(int)current_user()['id'],array_diff_key($_POST,['csrf_token'=>true])];
        if(cssrSubmissionReplay('storage-create',$submissionPayload)){set_flash('success','This request has already been saved.');redirect('storage_requests.php');}
        db()->beginTransaction();
            $requestNo = next_storage_request_no($requestDate);
            db_statement(
                'INSERT INTO storage_requests (request_no, from_location, to_location, requested_by, request_date, purpose, remarks, created_by)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$requestNo, $fromLocation, 'CSSR', $requestedBy, $requestDate, $purpose, $remarks, current_user()['id']]
            );
            $requestId = (int) db()->lastInsertId();
            foreach ($lines as $itemId => $qty) {
                $itemExists = (int) db_value('SELECT COUNT(*) FROM items WHERE id=? AND status="Active"', [$itemId]);
                if ($itemExists <= 0) {
                    throw new RuntimeException('One selected item is no longer active.');
                }
                db_statement(
                    'INSERT INTO storage_request_details (request_id, item_id, requested_qty) VALUES (?,?,?)',
                    [$requestId, $itemId, $qty]
                );
            }
            log_activity('CREATE', 'Storage Requests', 'Created storage request ' . $requestNo . ' from ' . $fromLocation . '.');
            db()->commit();
        cssrRememberSubmission('storage-create',$submissionPayload,['ok'=>true]);
            set_flash('success', 'Storage request created: ' . $requestNo);
            redirect('storage_requests.php?created=' . $requestId);
        }

        if ($action === 'release_request') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $postReturnPage = ($_POST['return_page'] ?? '') === 'request_queue.php' ? 'request_queue.php' : 'storage_requests.php';
            $releaseQty = $_POST['release_qty'] ?? [];
            $releaseLotId = $_POST['release_lot_id'] ?? [];
            $statusRemarks = trim((string) ($_POST['status_remarks'] ?? ''));
            $fefoOverrideReason = trim((string) ($_POST['fefo_override_reason'] ?? ''));
            if ($requestId <= 0 || !is_array($releaseQty) || !is_array($releaseLotId)) {
                throw new RuntimeException('Invalid storage request release.');
            }

            db()->beginTransaction();
            $stmt = db()->prepare('SELECT * FROM storage_requests WHERE id=? FOR UPDATE');
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            if (!$request || $request['status'] !== 'Pending') {
                throw new RuntimeException('Only pending storage requests can be released.');
            }

            $details = db_all(
                'SELECT d.*, i.item_description, i.unit, i.category
                 FROM storage_request_details d
                 JOIN items i ON i.id=d.item_id
                 WHERE d.request_id=?
                 ORDER BY i.item_description',
                [$requestId]
            );
            $totalReleased = 0;
            foreach ($details as $line) {
                $detailId = (int) $line['id'];
                $qty = (int) ($releaseQty[$detailId] ?? 0);
                $lotId = (int) ($releaseLotId[$detailId] ?? 0);
                if ($qty < 0 || $qty > (int) $line['requested_qty']) {
                    throw new RuntimeException('Release quantity cannot exceed requested quantity for ' . $line['item_description'] . '.');
                }
                if ($qty === 0) {
                    db_statement('UPDATE storage_request_details SET released_qty=0, release_lot_id=NULL WHERE id=?', [$detailId]);
                    continue;
                }
                if ($lotId <= 0) {
                    throw new RuntimeException('Select a lot number for ' . $line['item_description'] . '.');
                }

                $fefoLotId = (int) db_value(
                    'SELECT id FROM item_lots
                     WHERE item_id=? AND storage_location=? AND quantity_available>0
                       AND lot_number<>"UNIDENTIFIED / PENDING"
                     ORDER BY expiry_date IS NULL, expiry_date, created_at, id LIMIT 1',
                    [(int) $line['item_id'], $request['from_location']]
                );
                if ($fefoLotId > 0 && $lotId !== $fefoLotId && $fefoOverrideReason === '') {
                    throw new RuntimeException('FEFO requires the earliest-expiring lot for ' . $line['item_description'] . '. Enter an authorized override reason to use another lot.');
                }

                $lotStmt = db()->prepare('SELECT * FROM item_lots WHERE id=? AND item_id=? AND storage_location=? AND quantity_available>0 AND lot_number<>"UNIDENTIFIED / PENDING" FOR UPDATE');
                $lotStmt->execute([$lotId, (int) $line['item_id'], $request['from_location']]);
                $sourceLot = $lotStmt->fetch();
                if (!$sourceLot || $qty > (int) $sourceLot['quantity_available']) {
                    throw new RuntimeException($line['item_description'] . ' does not have enough stock in the selected lot.');
                }

                $lineRemarks = $statusRemarks ?: $request['remarks'];
                if ($fefoLotId > 0 && $lotId !== $fefoLotId) {
                    $lineRemarks = trim($lineRemarks . ' | FEFO override: ' . $fefoOverrideReason, ' |');
                }
                $sourceBalance = (int) $sourceLot['quantity_available'] - $qty;
                db_statement(
                    'UPDATE item_lots SET quantity_issued=quantity_issued+?, quantity_available=?, status=? WHERE id=?',
                    [$qty, $sourceBalance, $sourceBalance > 0 ? 'AVAILABLE' : 'DEPLETED', (int) $sourceLot['id']]
                );

                $destStmt = db()->prepare(
                    'SELECT * FROM item_lots
                     WHERE item_id=? AND lot_number <=> ? AND expiry_date <=> ? AND brand=? AND supplier=? AND storage_location="CSSR" AND unit_price <=> ?
                     ORDER BY id ASC LIMIT 1 FOR UPDATE'
                );
                $destStmt->execute([(int) $line['item_id'], $sourceLot['lot_number'], storage_request_nullable_date($sourceLot['expiry_date']), (string) ($sourceLot['brand'] ?? ''), (string) $sourceLot['supplier'], $sourceLot['unit_price']]);
                $cssrLot = $destStmt->fetch();
                if ($cssrLot) {
                    db_statement('UPDATE item_lots SET quantity_received=quantity_received+?, quantity_available=quantity_available+?, status="AVAILABLE" WHERE id=?', [$qty, $qty, (int) $cssrLot['id']]);
                } else {
                    db_statement(
                        'INSERT INTO item_lots (item_id, lot_number, expiry_date, brand, supplier, storage_location, unit_price, quantity_received, quantity_available, status)
                         VALUES (?,?,?,?,?,?,?,?,?,"AVAILABLE")',
                        [(int) $line['item_id'], (string) $sourceLot['lot_number'], storage_request_nullable_date($sourceLot['expiry_date']), (string) ($sourceLot['brand'] ?? ''), (string) $sourceLot['supplier'], 'CSSR', $sourceLot['unit_price'], $qty, $qty]
                    );
                }

                $itemBalance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?', [(int) $line['item_id']]);
                $sourceRoomBalance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=? AND storage_location=?', [(int) $line['item_id'], $request['from_location']]);
                db_statement('UPDATE items SET current_stock=?, lot_number=?, expiry_date=?, bin_location=? WHERE id=?', [
                    $itemBalance,
                    $sourceLot['lot_number'],
                    storage_request_nullable_date($sourceLot['expiry_date']),
                    $request['from_location'],
                    (int) $line['item_id'],
                ]);
                db_statement(
                    'UPDATE storage_request_details SET released_qty=?, release_lot_id=?, lot_number=?, expiry_date=? WHERE id=?',
                    [$qty, (int) $sourceLot['id'], $sourceLot['lot_number'], storage_request_nullable_date($sourceLot['expiry_date']), $detailId]
                );
                db_statement(
                    'INSERT INTO stock_withdrawals (withdrawal_number, withdrawal_date_time, item_id, item_name, item_description, lot_number, expiry_date, quantity_taken, unit_type, from_rack_number, from_shelf_number, from_bin_number, from_location_code, purpose, taken_by, remarks, added_to_cssr_stock, cssr_stock_id, posted_date_time, posted_by, status, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$request['request_no'] . '-' . $detailId, date('Y-m-d H:i:s'), (int) $line['item_id'], $line['item_description'], $line['item_description'], $sourceLot['lot_number'], storage_request_nullable_date($sourceLot['expiry_date']), $qty, $line['unit'], '', '', '', $request['from_location'], 'Storage Request', $request['requested_by'], $lineRemarks, 0, null, date('Y-m-d H:i:s'), current_user()['full_name'], 'POSTED', current_user()['id']]
                );
                db_statement(
                    'INSERT INTO stock_location_movements (item_id, movement_type, reference_no, lot_number, expiry_date, quantity, from_location_id, from_location_code, to_location_id, to_location_code, processed_by, processed_at, remarks, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [(int) $line['item_id'], 'STORAGE REQUEST RELEASE TO CSSR', $request['request_no'], $sourceLot['lot_number'], storage_request_nullable_date($sourceLot['expiry_date']), $qty, (int) $sourceLot['id'], $request['from_location'], (int) $line['item_id'], 'CSSR', current_user()['full_name'], date('Y-m-d H:i:s'), $lineRemarks, 'POSTED']
                );
                db_statement(
                    'INSERT INTO bin_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, department, encoded_by, remarks)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                    [(int) $line['item_id'], date('Y-m-d'), $request['request_no'], 'STORAGE REQUEST RELEASE', $sourceLot['lot_number'], storage_request_nullable_date($sourceLot['expiry_date']), 0, $qty, $sourceRoomBalance, 'CSSR', current_user()['full_name'], $lineRemarks]
                );
                db_statement(
                    'INSERT INTO stock_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, from_location, to_location, encoded_by, remarks)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [(int) $line['item_id'], date('Y-m-d H:i:s'), $request['request_no'], 'STORAGE REQUEST RELEASE', $sourceLot['lot_number'], storage_request_nullable_date($sourceLot['expiry_date']), 0, $qty, $sourceRoomBalance, $request['from_location'], 'CSSR', current_user()['full_name'], $lineRemarks]
                );
                $totalReleased += $qty;
            }

            if ($totalReleased <= 0) {
                throw new RuntimeException('Release at least one item quantity.');
            }
            db_statement(
                'UPDATE storage_requests SET status="Released", released_by=?, released_at=NOW(), remarks=? WHERE id=?',
                [current_user()['full_name'], $statusRemarks ?: $request['remarks'], $requestId]
            );
            log_activity('RELEASE', 'Storage Requests', 'Released storage request ' . $request['request_no'] . ' to CSSR.');
            db()->commit();
            if ($isStorageAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'message' => 'Storage request released and CSSR stock was updated.']);
                exit;
            }
            set_flash('success', 'Storage request released and CSSR stock was updated.');
            redirect('storage_requests.php?view=' . $requestId . ($postReturnPage === 'request_queue.php' ? '&return=request_queue.php' : '') . '#storageRequestRelease');
        }

        if ($action === 'add_item') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $qty = filter_var($_POST['quantity_requested'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
            $itemRemarks = trim((string) ($_POST['item_remarks'] ?? ''));
            $postReturnPage = ($_POST['return_page'] ?? '') === 'request_queue.php' ? 'request_queue.php' : 'storage_requests.php';
            $postEmbed = ($_POST['embed'] ?? '') === '1';
            if ($requestId <= 0 || $itemId <= 0 || $qty <= 0) {
                throw new RuntimeException('Select an item and enter a valid quantity.');
            }

            $submissionPayload=[$requestId,$itemId,$qty,(string)($_POST['item_remarks']??'')];
            if($replayed=cssrSubmissionReplay('storage-add',$submissionPayload)){
                if($isStorageAjax){header('Content-Type: application/json');echo json_encode($replayed);exit;}
                set_flash('success',$replayed['message']);redirect('storage_requests.php?view='.$requestId);
            }
            db()->beginTransaction();
            $stmt = db()->prepare('SELECT * FROM storage_requests WHERE id=? AND status="Pending" FOR UPDATE');
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            if (!$request) {
                throw new RuntimeException('Only pending storage requests can be edited.');
            }
            $stock = db_one(
                'SELECT i.item_description, COALESCE(SUM(l.quantity_available),0) available_qty
                 FROM items i
                 JOIN item_lots l ON l.item_id=i.id
                 WHERE i.id=? AND i.status="Active" AND l.storage_location=? AND l.quantity_available>0 AND l.lot_number<>"UNIDENTIFIED / PENDING"
                 GROUP BY i.id, i.item_description',
                [$itemId, $request['from_location']]
            );
            if (!$stock) {
                throw new RuntimeException('Selected item is not available in ' . $request['from_location'] . '.');
            }
            if ($qty > (int) $stock['available_qty']) {
                throw new RuntimeException($stock['item_description'] . ' does not have enough stock in ' . $request['from_location'] . '.');
            }
            $existing = db_one('SELECT id, requested_qty FROM storage_request_details WHERE request_id=? AND item_id=?', [$requestId, $itemId]);
            if ($existing) {
                db_statement('UPDATE storage_request_details SET requested_qty=?, remarks=? WHERE id=?', [(int) $existing['requested_qty'] + $qty, $itemRemarks, (int) $existing['id']]);
            } else {
                db_statement('INSERT INTO storage_request_details (request_id, item_id, requested_qty, remarks) VALUES (?,?,?,?)', [$requestId, $itemId, $qty, $itemRemarks]);
            }
            log_activity('UPDATE', 'Storage Requests', 'Added item to storage request ' . $request['request_no'] . '.');
            db()->commit();
            cssrRememberSubmission('storage-add',$submissionPayload,['ok'=>true,'message'=>'Item added to storage request.']);
            if ($isStorageAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'message' => 'Item added to storage request.']);
                exit;
            }
            set_flash('success', 'Item added to storage request.');
            redirect('storage_requests.php?view=' . $requestId . ($postReturnPage === 'request_queue.php' ? '&return=request_queue.php' : '') . ($postEmbed ? '&embed=1' : '') . '#storageRequestRelease');
        }

        if ($action === 'delete_request') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $postReturnPage = ($_POST['return_page'] ?? '') === 'request_queue.php' ? 'request_queue.php' : 'storage_requests.php';
            $postEmbed = ($_POST['embed'] ?? '') === '1';
            if ($requestId <= 0) {
                throw new RuntimeException('Invalid storage request.');
            }
            db()->beginTransaction();
            $stmt = db()->prepare('SELECT * FROM storage_requests WHERE id=? AND status="Pending" FOR UPDATE');
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            if (!$request) {
                throw new RuntimeException('Only pending storage requests can be deleted.');
            }
            db_statement('UPDATE storage_requests SET status="Deleted", remarks=? WHERE id=?', [trim((string) ($_POST['status_remarks'] ?? $request['remarks'])), $requestId]);
            log_activity('DELETE', 'Storage Requests', 'Deleted storage request ' . $request['request_no'] . '.');
            db()->commit();
            set_flash('success', 'Storage request deleted.');
            if ($postEmbed && $postReturnPage === 'request_queue.php') {
                echo '<script>if(parent&&parent.closePendingRequestDetail){parent.closePendingRequestDetail(); parent.location.href="request_queue.php";}else{location.href="request_queue.php";}</script>';
                exit;
            }
            redirect($postReturnPage);
        }
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if (in_array(($action ?? ''), ['add_item', 'release_request'], true) && ($isStorageAjax ?? false)) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => safe_error_message($e)]);
            exit;
        }
        set_flash('danger', safe_error_message($e));
        redirect('storage_requests.php');
    }
}

$roomPlaceholders = implode(',', array_fill(0, count($locations), '?'));
$catalogData = cssrRequestCatalog(db(), ['room'=>$locations[0]], true);
$items = $catalogData['items'];
$lotRows = [];
$requests = db_all(
    'SELECT r.*, COALESCE(SUM(d.requested_qty),0) requested_total, COALESCE(SUM(d.released_qty),0) released_total, COUNT(d.id) line_count
     FROM storage_requests r
     LEFT JOIN storage_request_details d ON d.request_id=r.id
     GROUP BY r.id
     ORDER BY FIELD(r.status, "Pending", "Released", "Cancelled"), r.created_at DESC
     LIMIT 100'
);

$selectedRequest = null;
$selectedDetails = [];
$selectedLotsByItem = [];
$createdRequest = null;
if (isset($_GET['created'])) {
    $createdId = (int) $_GET['created'];
    $stmt = db()->prepare('SELECT * FROM storage_requests WHERE id=?');
    $stmt->execute([$createdId]);
    $createdRequest = $stmt->fetch() ?: null;
}
if (isset($_GET['view'])) {
    $selectedId = (int) $_GET['view'];
    $stmt = db()->prepare('SELECT * FROM storage_requests WHERE id=?');
    $stmt->execute([$selectedId]);
    $selectedRequest = $stmt->fetch() ?: null;
    if ($selectedRequest) {
        $selectedDetails = db_all(
            'SELECT d.*, i.item_code, i.item_description, i.unit, i.item_image,
                    COALESCE((SELECT SUM(l.quantity_available) FROM item_lots l WHERE l.item_id=i.id AND l.storage_location=?),0) AS available_stock
             FROM storage_request_details d
             JOIN items i ON i.id=d.item_id
             WHERE d.request_id=?
             ORDER BY i.item_description',
            [$selectedRequest['from_location'], $selectedId]
        );
        $detailItemIds=array_values(array_unique(array_column($selectedDetails,'item_id')));
        if($detailItemIds){
            $detailMarks=implode(',',array_fill(0,count($detailItemIds),'?'));
            $lotRows=db_all("SELECT id,item_id,lot_number,expiry_date,supplier,storage_location,unit_price,quantity_available,lot_image FROM item_lots WHERE storage_location=? AND item_id IN ($detailMarks) AND quantity_available>0 AND lot_number<>'UNIDENTIFIED / PENDING' ORDER BY item_id,expiry_date IS NULL,expiry_date,id",array_merge([$selectedRequest['from_location']],$detailItemIds));
        }
        foreach ($lotRows as $lot) {
            if ($lot['storage_location'] === $selectedRequest['from_location']) {
                $selectedLotsByItem[(int) $lot['item_id']][] = $lot;
            }
        }
    }
}

$previewRequestNo = next_storage_request_no(cssrTransactionToday());
$bodyClass = trim(
    (($selectedRequest && ($_GET['print'] ?? '') === '1') ? 'request-voucher-printing ' : '')
    . ($selectedRequest ? 'request-detail-open' : '')
);
include __DIR__ . '/../includes/header.php';
?>
<?php if ($embedStorageRequest): ?>
<style>
html,body,.app-shell,.main-content{background:transparent!important}
.sidebar,.topbar,.page-heading,.request-shell,.mobile-cart-bar,.mobile-cart-drawer{display:none!important}
.main-content{margin:0!important;width:100%!important;padding:0!important;min-height:0!important}
.request-modal{background:rgba(15,23,42,.32)!important}
</style>
<?php endif; ?>
<section class="request-shell storage-release-shell sw-request-shell">
    <div class="request-left cu-card">
        <div class="cu-card-head"><div><h2>Create request</h2><p>Select a source location and add the supplies you need.</p></div><span class="cu-badge cu-badge-info">To CSSR</span></div>
        <form id="storageRequestForm" method="post" data-confirm="custom">
            <?= csrf_field() ?><?= cssrSubmissionField() ?>
            <input type="hidden" name="action" value="create_request">
            <input type="hidden" name="cart_json" id="storageRequestCartJson">
            <div class="row">
                <div class="col-md-6 mb-2"><label class="form-label">Request No.</label><input class="form-control" id="storageRequestNoPreview" value="<?= e($previewRequestNo) ?>" readonly></div>
                <div class="col-md-6 mb-2"><label class="form-label required" for="storageRequestDate">Request Date</label><input class="form-control" type="date" name="request_date" id="storageRequestDate" value="<?= e(cssrTransactionToday()) ?>" <?= cssrCanEditTransactionDate(current_user() ?? []) ? '' : 'readonly aria-readonly="true"' ?> required></div>
                <div class="col-md-6 mb-2"><label class="form-label required">From</label><select class="form-select" name="from_location" id="storageRequestFrom" required><?php foreach ($locations as $location): ?><option value="<?= e($location) ?>"><?= e($location) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6 mb-2"><label class="form-label">To</label><input class="form-control" value="CSSR" readonly></div>
                <div class="col-md-6 mb-2"><label class="form-label required">Requested By</label><input class="form-control" name="requested_by" value="<?= e(current_user()['full_name']) ?>" readonly required></div>
                <div class="col-md-6 mb-2"><label class="form-label">Purpose</label><input class="form-control" name="purpose" value="Replenishment" readonly></div>
                <div class="col-md-12 mb-2"><label class="form-label" for="storageRequestSearch">Search items</label><input class="form-control" id="storageRequestSearch" placeholder="Search item name"></div>
            </div>
            <div id="storageRequestItemList" class="pos-item-list request-item-list storage-release-item-list"></div>
            <div class="row mt-2"><div class="col-md-12"><label class="form-label">Remarks</label><input class="form-control" name="remarks"></div></div>
        </form>
    </div>
    <div class="request-right cu-card">
        <div class="cu-card-head"><div><h2>Requested items</h2><p>Review quantities before submitting your request.</p></div><span class="cu-badge cu-badge-info" id="storageRequestCartCount">0</span></div>
        <div id="storageRequestCart" class="pos-cart-list"></div>
        <div class="pos-total-bar">
            <div><span>LINES</span><strong id="storageRequestLines">0</strong></div>
            <div><span>TOTAL QTY</span><strong id="storageRequestQty">0</strong></div>
            <button class="btn btn-light" type="button" onclick="clearStorageRequestCart()">Clear Cart</button>
            <button class="btn btn-primary" type="submit" form="storageRequestForm">Submit Request</button>
        </div>
    </div>
</section>

<?php if ($createdRequest): ?>
<div class="request-modal open" id="storageRequestConfirmation">
<div class="request-modal-panel storage-request-confirmation">
    <div class="request-modal-head">
        <div>
            <span class="eyebrow">Confirmation</span>
            <h3>Storage Request Submitted</h3>
        </div>
        <a class="btn btn-light" href="storage_requests.php">Close</a>
    </div>
    <div class="request-confirmation-body">
        <div class="request-confirmation-icon">OK</div>
        <h2><?= e($createdRequest['request_no']) ?></h2>
        <p>The request was submitted and is now waiting in the Release Queue.</p>
        <div class="receipt-meta">
            <span><strong>From:</strong> <?= e($createdRequest['from_location']) ?></span>
            <span><strong>To:</strong> <?= e($createdRequest['to_location']) ?></span>
            <span><strong>Requested By:</strong> <?= e($createdRequest['requested_by']) ?></span>
            <span><strong>Date:</strong> <?= e($createdRequest['request_date']) ?></span>
        </div>
        <div class="request-actions confirmation-actions">
            <a class="btn btn-primary" href="request_queue.php">Go to Release Queue</a>
            <a class="btn btn-light" href="storage_requests.php">Create Another Request</a>
        </div>
    </div>
</div>
</div>
<?php endif; ?>

<?php if ($selectedRequest): ?>
<div class="request-modal open" id="storageRequestRelease">
<div class="request-modal-panel storage-request-detail">
    <div class="request-modal-head">
        <div>
            <span class="eyebrow">Request Details</span>
            <h3><?= e($selectedRequest['request_no']) ?> <span class="badge request-status <?= e(strtolower($selectedRequest['status'])) ?>"><?= e($selectedRequest['status']) ?></span></h3>
        </div>
        <div class="request-modal-actions">
            <?php if ($selectedRequest['status'] === 'Released'): ?>
                <button class="btn btn-light" type="button" onclick="printStorageRequestAndClose('<?= e($storageReturnPage) ?>',<?= $embedStorageRequest ? 'true' : 'false' ?>)">Print</button>
            <?php endif; ?>
            <?php if ($embedStorageRequest): ?>
                <button class="btn btn-light" type="button" onclick="parent.closePendingRequestDetail ? parent.closePendingRequestDetail() : parent.closeRequestHistoryDetail()">Close</button>
            <?php else: ?>
                <a class="btn btn-light" href="<?= e($storageReturnPage) ?>">Close</a>
            <?php endif; ?>
        </div>
    </div>
    <?php nursingRenderWorkflow('storage', (string)$selectedRequest['status']); ?>
    <div class="request-detail">
        <div class="receipt-meta request-screen-meta">
            <span><strong>From:</strong> <?= e($selectedRequest['from_location']) ?></span>
            <span><strong>To:</strong> <?= e($selectedRequest['to_location']) ?></span>
            <span><strong>Requested By:</strong> <?= e($selectedRequest['requested_by']) ?></span>
            <span><strong>Date:</strong> <?= e($selectedRequest['request_date']) ?></span>
        </div>
    <form method="post" id="storageRequestReleaseForm" data-confirm="custom">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="release_request">
        <input type="hidden" name="request_id" value="<?= (int) $selectedRequest['id'] ?>">
        <input type="hidden" name="return_page" value="<?= e($storageReturnPage) ?>">
        <div class="table-responsive">
            <table class="table storage-request-detail-table">
                <thead>
                    <tr class="request-repeat-header print-only"><th colspan="5">
                        <div class="bin-card-header request-document-header request-repeat-document-header">
                            <img class="bin-logo" src="../assets/img/doh-logo.png" alt="Department of Health logo">
                            <div class="facility-copy hospital-card-letterhead"><strong>DEPARTMENT OF HEALTH</strong><strong>JONI VILLANUEVA GENERAL HOSPITAL</strong><span>National Road, Igulot, Bocaue, Bulacan 3018</span></div>
                            <img class="bin-logo" src="../assets/img/jvgh-seal.png" alt="Joni Villanueva General Hospital seal">
                        </div>
                        <h2 class="request-voucher-title">VOUCHER'S SLIP</h2>
                        <div class="receipt-meta">
                            <span><strong>From:</strong> <?= e($selectedRequest['from_location']) ?></span>
                            <span><strong>To:</strong> <?= e($selectedRequest['to_location']) ?></span>
                            <span><strong>Requested By:</strong> <?= e($selectedRequest['requested_by']) ?></span>
                            <span><strong>Request No.:</strong> <?= e($selectedRequest['request_no']) ?></span>
                        </div>
                    </th></tr>
                    <tr><th class="no-print">Picture</th><th class="no-print">Code</th><th>Description</th><th>Unit</th><th class="print-only">Lot Number</th><th class="no-print">Available Stocks</th><th class="no-print">Available Lot</th><th class="no-print">Lot Stock</th><th>Requested Qty</th><th>Release Qty</th></tr>
                </thead>
                <tbody>
                <?php $itemsPerPrintPage = 17; $totalPrintItems = count($selectedDetails); ?>
                <?php foreach ($selectedDetails as $detailIndex => $detail): $lots = $selectedLotsByItem[(int) $detail['item_id']] ?? []; ?>
                    <tr class="request-item-row <?= (($detailIndex + 1) % $itemsPerPrintPage === 0 && ($detailIndex + 1) < $totalPrintItems) ? 'request-page-break-after' : '' ?>">
                        <td class="storage-request-picture-cell no-print">
                            <?php if (!empty($detail['item_image'])): ?>
                                <img class="request-detail-picture" src="../uploads/items/<?= e($detail['item_image']) ?>" alt="<?= e($detail['item_description']) ?>" onclick="openImage(this.src)" role="button" tabindex="0">
                            <?php else: ?>
                                <span class="request-detail-picture-empty">IMG</span>
                            <?php endif; ?>
                        </td>
                        <td class="no-print"><?= e($detail['item_code']) ?></td>
                        <td><?= e($detail['item_description']) ?></td>
                        <td><?= e($detail['unit'] ?: 'N/A') ?></td>
                        <td class="print-only"><?= e($detail['lot_number'] ?: 'N/A') ?></td>
                        <td class="no-print"><?= (int) $detail['available_stock'] ?></td>
                        <td class="no-print">
                            <?php if ($selectedRequest['status'] === 'Pending'): ?>
                                <select class="form-select storage-request-lot-select" name="release_lot_id[<?= (int) $detail['id'] ?>]" data-qty-input="storageReleaseQty<?= (int) $detail['id'] ?>" data-stock-target="storageLotStock<?= (int) $detail['id'] ?>" data-requested="<?= (int) $detail['requested_qty'] ?>" data-fefo-lot="<?= (int) ($lots[0]['id'] ?? 0) ?>">
                                    <option value=""><?= $lots ? 'Select lot' : 'No available lot' ?></option>
                                    <?php foreach ($lots as $lotIndex => $lot): ?>
                                        <?php
                                            $expiryLabel = 'NO EXPIRY';
                                            if (!empty($lot['expiry_date'])) {
                                                $expiryTime = strtotime((string) $lot['expiry_date']);
                                                $expiryLabel = $expiryTime < strtotime(date('Y-m-d'))
                                                    ? 'EXPIRED'
                                                    : ($expiryTime <= strtotime('+8 months') ? 'EXPIRING SOON' : 'VALID');
                                            }
                                            $lotOptionLabel = ($lotIndex === 0 ? 'FEFO RECOMMENDED | ' : '') . $expiryLabel . ' | ' . trim((string) $lot['lot_number']) . ' | Expiry: ' . ($lot['expiry_date'] ?: 'N/A') . ' | ' . (($lot['supplier'] ?? '') !== '' ? (string) $lot['supplier'] : 'N/A') . ' | Price: ' . ($lot['unit_price'] === null ? '' : number_format((float) $lot['unit_price'], 2)) . ' | Qty: ' . (int) $lot['quantity_available'];
                                        ?>
                                        <option value="<?= (int) $lot['id'] ?>" data-stock="<?= (int) $lot['quantity_available'] ?>" <?= $lotIndex === 0 ? 'selected' : '' ?>><?= e($lotOptionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <?= e($detail['lot_number'] ?: 'N/A') ?>
                            <?php endif; ?>
                        </td>
                        <td class="no-print" id="storageLotStock<?= (int) $detail['id'] ?>"><?= $selectedRequest['status'] === 'Pending' ? '-' : (int) $detail['released_qty'] ?></td>
                        <td><?= (int) $detail['requested_qty'] ?></td>
                        <td>
                            <?php if ($selectedRequest['status'] === 'Pending'): ?>
                                <input class="form-control storage-request-release-qty" id="storageReleaseQty<?= (int) $detail['id'] ?>" type="number" min="0" max="<?= (int) $detail['requested_qty'] ?>" name="release_qty[<?= (int) $detail['id'] ?>]" value="<?= (int) $detail['requested_qty'] ?>">
                            <?php else: ?>
                                <?= (int) $detail['released_qty'] ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php
                    $completedPrintPages = intdiv($totalPrintItems, $itemsPerPrintPage);
                    $itemsOnLastPrintPage = $totalPrintItems % $itemsPerPrintPage;
                    $printRowTarget = $totalPrintItems === 0
                        ? 0
                        : ($completedPrintPages * $itemsPerPrintPage) + ($itemsOnLastPrintPage > 0 ? $itemsPerPrintPage : 0);
                ?>
                <?php for ($blankRow = $totalPrintItems; $blankRow < $printRowTarget; $blankRow++): ?>
                    <tr class="request-print-blank-row print-only"><td colspan="5">&nbsp;</td></tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <?php if ($selectedRequest['status'] === 'Pending'): ?>
            <div class="form-section no-print" id="fefoOverrideField" hidden>
                <label class="form-label required">FEFO Override Reason</label>
                <input class="form-control" id="fefoOverrideReason" name="fefo_override_reason" placeholder="Required when selecting a lot other than FEFO Recommended">
                <small>Only use an override when the earliest-expiring lot cannot be released. The reason is recorded in the audit trail.</small>
            </div>
            <div class="add-request-item no-print storage-request-add-item">
                <div>
                    <label class="form-label">Add Item</label>
                    <div class="storage-add-item-picker">
                    <input class="form-control" id="storageAddItemPicker" placeholder="Type or select an item name" autocomplete="off" form="storageRequestAddItemForm" required>
                    <input type="hidden" id="storageAddItemId" name="item_id" form="storageRequestAddItemForm">
                    <div class="storage-add-item-suggestions" id="storageAddItemSuggestions"></div>
                    </div>
                </div>
                <div>
                    <label class="form-label">Qty</label>
                    <input class="form-control" id="storageAddItemQty" type="number" min="1" step="1" name="quantity_requested" form="storageRequestAddItemForm" required>
                </div>
                <div>
                    <label class="form-label">Remarks</label>
                    <input class="form-control" name="item_remarks" form="storageRequestAddItemForm">
                </div>
                <button class="btn btn-primary" type="submit" form="storageRequestAddItemForm">Add Item</button>
                <p id="storageAddItemFeedback" class="request-add-feedback" role="status" aria-live="polite" hidden></p>
            </div>
        <?php endif; ?>
        <?php if ($selectedRequest['status'] === 'Pending'): ?>
            <div class="request-actions">
                <input class="form-control" name="status_remarks" placeholder="Release remarks" value="<?= e($selectedRequest['remarks']) ?>">
                <button class="btn btn-primary" type="submit">Release</button>
                <button class="btn btn-danger" type="submit" form="storageRequestDeleteForm">Delete Request</button>
            </div>
        <?php endif; ?>
    </form>
    <?php if ($selectedRequest['status'] === 'Released'): ?>
        <div class="request-prepared-by print-only">
            <span>Prepared By:</span>
            <strong><?= e($selectedRequest['released_by'] ?: 'N/A') ?></strong>
            <small>Signature over printed name</small>
        </div>
        <div class="request-received-by print-only">
            <span>Received By:</span>
            <strong>&nbsp;</strong>
            <small>Signature over printed name</small>
        </div>
        <div class="request-signature-divider print-only"></div>
        <div class="bin-card-footer request-document-footer print-only">
            <div class="bin-footer-note">This is a computer-generated form.<br>No stamping is needed.</div>
            <div class="bin-footer-code">
                <strong>JVGH-CSSR-XX-XXX</strong>
                <table>
                    <tr><th>Rev Number</th><td></td></tr>
                    <tr><th>Eff Date</th><td></td></tr>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($selectedRequest['status'] === 'Pending'): ?>
        <form method="post" id="storageRequestAddItemForm" data-confirm="custom" novalidate>
            <?= csrf_field() ?><?= cssrSubmissionField() ?>
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="request_id" value="<?= (int) $selectedRequest['id'] ?>">
            <input type="hidden" name="return_page" value="<?= e($storageReturnPage) ?>">
            <input type="hidden" name="embed" value="<?= $embedStorageRequest ? '1' : '0' ?>">
        </form>
        <form method="post" id="storageRequestDeleteForm" data-confirm="custom">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_request">
            <input type="hidden" name="request_id" value="<?= (int) $selectedRequest['id'] ?>">
            <input type="hidden" name="return_page" value="<?= e($storageReturnPage) ?>">
            <input type="hidden" name="embed" value="<?= $embedStorageRequest ? '1' : '0' ?>">
        </form>
    <?php endif; ?>
    </div>
</div>
</div>
<?php endif; ?>

<script src="../assets/js/request-catalog.js?v=<?= filemtime(__DIR__.'/../assets/js/request-catalog.js') ?>"></script>
<script>
const storageRequestItems = <?= json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const storageRequestCart = new Map();
function srEsc(value){return String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
function printStorageRequestAndClose(closeUrl,closeEmbedded){
    var didClose=false;
    function closeStorageRequestPrint(){
        if(didClose)return;
        didClose=true;
        if(closeEmbedded&&window.parent&&window.parent!==window){
            if(typeof window.parent.closePendingRequestDetail==='function'){
                window.parent.closePendingRequestDetail();
            }else if(typeof window.parent.closeRequestHistoryDetail==='function'){
                window.parent.closeRequestHistoryDetail();
            }
        }else{
            window.location.href=closeUrl||'storage_requests.php';
        }
    }
    window.addEventListener('afterprint',closeStorageRequestPrint,{once:true});
    window.print();
    setTimeout(closeStorageRequestPrint,1200);
}
function buildStorageRequestPrintPages(){
    var panel=document.querySelector('#storageRequestRelease .request-modal-panel');
    var original=panel&&panel.querySelector('.request-detail:not(.request-print-page-copy)');
    if(!panel||!original||panel.querySelector('.request-print-pages'))return;
    var rowsPerCopy=17;
    var totalRows=original.querySelectorAll('tbody .request-item-row').length;
    var pageCount=Math.max(1,Math.ceil(totalRows/rowsPerCopy));
    var pages=document.createElement('div');
    pages.className='request-print-pages';
    pages.setAttribute('aria-hidden','true');
    function buildPrintCopy(startIndex){
        var copy=original.cloneNode(true);
        copy.classList.add('request-print-page-copy');
        copy.querySelectorAll('.no-print').forEach(function(element){element.remove();});
        copy.querySelectorAll('[form]').forEach(function(element){element.removeAttribute('form');});
        copy.querySelectorAll('[id]').forEach(function(element){element.removeAttribute('id');});
        copy.querySelectorAll('form').forEach(function(form){
            var wrapper=document.createElement('div');
            while(form.firstChild)wrapper.appendChild(form.firstChild);
            form.replaceWith(wrapper);
        });
        copy.querySelectorAll('.request-page-break-after').forEach(function(row){row.classList.remove('request-page-break-after');});
        var copyBody=copy.querySelector('tbody');
        if(!copyBody)return copy;
        var itemRows=Array.from(copyBody.querySelectorAll('.request-item-row'));
        itemRows.forEach(function(row,index){
            if(index<startIndex||index>=startIndex+rowsPerCopy)row.remove();
        });
        copyBody.querySelectorAll('.request-print-blank-row').forEach(function(row){row.remove();});
        var keptRows=Math.min(rowsPerCopy,Math.max(0,totalRows-startIndex));
        for(var blankIndex=keptRows;blankIndex<rowsPerCopy;blankIndex++){
            var blankRow=document.createElement('tr');
            blankRow.className='request-print-blank-row print-only';
            var blankCell=document.createElement('td');
            blankCell.colSpan=5;
            blankCell.innerHTML='&nbsp;';
            blankRow.appendChild(blankCell);
            copyBody.appendChild(blankRow);
        }
        return copy;
    }
    for(var pageIndex=0;pageIndex<pageCount;pageIndex++){
        var sheet=document.createElement('section');
        sheet.className='request-print-sheet';
        var topCopy=buildPrintCopy(pageIndex*rowsPerCopy);
        var bottomCopy=buildPrintCopy(pageIndex*rowsPerCopy);
        topCopy.classList.add('request-print-half-top');
        bottomCopy.classList.add('request-print-half-bottom');
        sheet.appendChild(topCopy);
        sheet.appendChild(bottomCopy);
        pages.appendChild(sheet);
    }
    panel.appendChild(pages);
}
function srItemsForRoom(){
    const room = document.getElementById('storageRequestFrom').value;
    const q = document.getElementById('storageRequestSearch').value.toLowerCase().trim();
    return storageRequestItems.filter(item => item.storage_location === room && (!q || String(item.item_name).toLowerCase().includes(q)));
}
function renderStorageRequestItems(visible){
    const list = document.getElementById('storageRequestItemList');
    list.innerHTML = (Array.isArray(visible)?visible:srItemsForRoom()).map(item => `<button type="button" class="pos-item-card" onclick="addStorageRequestItem(${Number(item.id)})"><span class="pos-item-img pos-img-empty">IMG</span><span class="pos-item-main"><strong>${srEsc(item.item_name)}</strong><small>${srEsc(item.item_code)}</small></span><span class="pos-stock"><strong>${Number(item.current_stock || 0)}</strong><small>Available</small></span></button>`).join('') || '<div class="pos-empty">No matching item found in this storage room.</div>';
}
function addStorageRequestItem(id){
    const room = document.getElementById('storageRequestFrom').value;
    const item = storageRequestItems.find(row => Number(row.id) === Number(id) && row.storage_location === room);
    if (!item) return;
    const existing = storageRequestCart.get(id);
    const nextQty = existing ? Number(existing.qty || 0) + 1 : 1;
    storageRequestCart.set(id, {...item, qty: Math.min(nextQty, Number(item.current_stock || 1))});
    renderStorageRequestCart();
}
function updateStorageRequestQty(id, qty){
    const item = storageRequestCart.get(Number(id));
    if (!item) return;
    item.qty = Math.min(Math.max(1, Number(qty || 1)), Number(item.current_stock || 1));
    renderStorageRequestCart();
}
function removeStorageRequestItem(id){
    storageRequestCart.delete(Number(id));
    renderStorageRequestCart();
}
function clearStorageRequestCart(){
    if (!storageRequestCart.size) return;
    showConfirm('Remove all items from the storage request cart?', function(){storageRequestCart.clear();renderStorageRequestCart();});
}
function renderStorageRequestCart(){
    const rows = Array.from(storageRequestCart.values());
    let total = 0;
    document.getElementById('storageRequestCart').innerHTML = rows.map(item => {
        total += Number(item.qty || 0);
        return `<div class="pos-cart-row"><span class="pos-cart-img pos-img-empty">IMG</span><div class="pos-cart-info"><strong>${srEsc(item.item_name)}</strong></div><input class="form-control pos-qty" type="number" min="1" max="${Number(item.current_stock)}" value="${Number(item.qty || 1)}" onchange="updateStorageRequestQty(${Number(item.id)},this.value)"><button type="button" class="btn btn-light btn-sm" onclick="removeStorageRequestItem(${Number(item.id)})">Remove</button></div>`;
    }).join('') || '<div class="pos-empty">Request cart is empty.</div>';
    document.getElementById('storageRequestLines').textContent = rows.length;
    document.getElementById('storageRequestQty').textContent = total;
    document.getElementById('storageRequestCartCount').textContent = rows.length;
    document.getElementById('storageRequestCartJson').value = JSON.stringify(rows.map(item => ({id:Number(item.id), qty:Number(item.qty || 1)})));
}
document.getElementById('storageRequestFrom').addEventListener('change', function(){
    if (storageRequestCart.size) {
        storageRequestCart.clear();
        renderStorageRequestCart();
    }
    renderStorageRequestItems();
});
document.getElementById('storageRequestSearch').addEventListener('input', renderStorageRequestItems);
document.getElementById('storageRequestDate').addEventListener('change', function(){
    fetch('storage_requests.php?next_request_no=' + encodeURIComponent(this.value), {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(response => response.json())
        .then(data => { if (data && data.request_no) document.getElementById('storageRequestNoPreview').value = data.request_no; })
        .catch(() => {});
});
document.getElementById('storageRequestForm').addEventListener('submit', function(event){
    if (!storageRequestCart.size) {
        event.preventDefault();
        showToastMessage('warning', 'Add at least one item to request.');
        return;
    }
    event.preventDefault();
    const totalQty = Array.from(storageRequestCart.values()).reduce((total,item)=>total+Number(item.qty||0),0);
    showConfirm('Submit storage request with total quantity ' + totalQty + '?', function(){
        const form = document.getElementById('storageRequestForm');
        form.dataset.confirm = 'off';
        startLoading('Submitting storage request...');
        form.submit();
    });
});
const releaseForm = document.getElementById('storageRequestReleaseForm');
if (releaseForm) {
    function syncFefoOverride() {
        const field = document.getElementById('fefoOverrideField');
        const reason = document.getElementById('fefoOverrideReason');
        if (!field || !reason) return;
        const overridden = Array.from(document.querySelectorAll('.storage-request-lot-select')).some(function(select) {
            return select.value && Number(select.value) !== Number(select.dataset.fefoLot || 0);
        });
        field.hidden = !overridden;
        reason.required = overridden;
        if (!overridden) reason.value = '';
    }
    function syncStorageRequestLot(select) {
        const qtyInput = document.getElementById(select.dataset.qtyInput || '');
        const stockTarget = document.getElementById(select.dataset.stockTarget || '');
        if (!qtyInput) return;
        const selected = select.options[select.selectedIndex];
        const requested = Number(select.dataset.requested || qtyInput.max || 0);
        const lotStock = selected && selected.value ? Number(selected.dataset.stock || 0) : 0;
        const maxQty = selected && selected.value ? Math.min(requested, lotStock) : requested;
        if (stockTarget) {
            stockTarget.textContent = selected && selected.value ? String(lotStock) : '-';
        }
        qtyInput.max = String(Math.max(0, maxQty));
        if (Number(qtyInput.value || 0) > maxQty) {
            qtyInput.value = String(Math.max(0, maxQty));
        }
        syncFefoOverride();
    }
    document.querySelectorAll('.storage-request-lot-select').forEach(function(select) {
        const realOptions = Array.from(select.options).filter(option => option.value);
        if (realOptions.length > 0) {
            select.value = realOptions[0].value;
        }
        select.addEventListener('change', function(){syncStorageRequestLot(select);});
        syncStorageRequestLot(select);
    });
    syncFefoOverride();
    releaseForm.addEventListener('submit', function(event){
        const invalid = Array.from(document.querySelectorAll('.storage-request-lot-select')).find(function(select) {
            const qtyInput = document.getElementById(select.dataset.qtyInput || '');
            return qtyInput && Number(qtyInput.value || 0) > 0 && !select.value;
        });
        if (invalid) {
            event.preventDefault();
            showToastMessage('warning', 'Select lot number for every item with release quantity.');
            invalid.focus();
            return;
        }
        event.preventDefault();
        showConfirm('Post this storage request release and update CSSR stock?', function(){
            releaseForm.dataset.confirm = 'off';
            startLoading('Posting storage request release...');
            fetch(window.location.href, {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: new FormData(releaseForm),
                credentials: 'same-origin'
            }).then(function(response){
                return response.json().then(function(data){return {ok: response.ok, data: data};});
            }).then(function(result){
                if (!result.ok || !result.data.ok) {
                    throw new Error(result.data.message || 'Unable to release the storage request.');
                }
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.classList.remove('open');
                showToastMessage('success', result.data.message || 'Storage request released.');
                setTimeout(function(){
                    if (window.parent && window.parent !== window && window.parent.closePendingRequestDetail) {
                        window.parent.closePendingRequestDetail();
                        window.parent.location.reload();
                    } else {
                        window.location.href = 'request_queue.php';
                    }
                }, 700);
            }).catch(function(error){
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.classList.remove('open');
                showConfirm(error.message || 'Unable to release the storage request.', function(){}, {alert:true});
            });
        });
    });
}
const storageAddItemPicker = document.getElementById('storageAddItemPicker');
const storageAddItemId = document.getElementById('storageAddItemId');
const storageAddItemSuggestions = document.getElementById('storageAddItemSuggestions');
const storageAddItemForm = document.getElementById('storageRequestAddItemForm');
if (storageAddItemPicker && storageAddItemId && storageAddItemSuggestions && storageAddItemForm) {
    const storageAddFeedback=document.getElementById('storageAddItemFeedback');
    const storageAddQty=document.getElementById('storageAddItemQty');
    const storageAddButton=document.querySelector('button[form="storageRequestAddItemForm"]');
    let storageAddBusy=false;
    function storageAddMessage(message,isError){
        storageAddFeedback.textContent=message;storageAddFeedback.hidden=!message;
        storageAddFeedback.dataset.error=String(!!isError);
    }
    const storageAddRoom = <?= json_encode($selectedRequest['from_location'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const storageAddItems = storageRequestItems.filter(item => item.storage_location === storageAddRoom);
    <?php if(isset($catalogData)): ?>
    cssrCatalogLookup({input:storageAddItemPicker,endpoint:'storage_requests.php',room:()=>storageAddRoom,onItems:function(rows){
        storageAddItems.splice(0,storageAddItems.length,...rows);
        if(document.activeElement===storageAddItemPicker)renderStorageAddItemSuggestions();
    },onError:function(message){storageAddMessage(message,true);}});
    <?php endif; ?>
    function renderStorageAddItemSuggestions(){
        const typed = storageAddItemPicker.value.trim().toLowerCase();
        const matches = storageAddItems.filter(item => !typed || String(item.item_name).toLowerCase().includes(typed)).slice(0, 80);
        storageAddItemSuggestions.innerHTML = matches.length
            ? matches.map(item => `<button type="button" class="storage-add-item-suggestion" onclick="selectStorageAddItem(${Number(item.id)})"><span>${srEsc(item.item_name)}</span></button>`).join('')
            : '<div class="storage-add-item-suggestion empty">No available item found in '+srEsc(storageAddRoom)+'.</div>';
    }
    function selectStorageAddItem(itemId){
        const item = storageAddItems.find(row => Number(row.id) === Number(itemId));
        if (!item) return;
        storageAddItemPicker.value = item.item_name;
        storageAddItemId.value = Number(item.id);
        storageAddItemPicker.setCustomValidity('');
        storageAddItemPicker.removeAttribute('aria-invalid');
        storageAddMessage('',false);
        storageAddItemSuggestions.innerHTML = '';
    }
    window.selectStorageAddItem = selectStorageAddItem;
    function syncStorageAddItem(){
        const typed = storageAddItemPicker.value.trim().toLowerCase();
        const selected=storageAddItems.find(item => String(item.id)===storageAddItemId.value && String(item.item_name).trim().toLowerCase()===typed);
        const exact=storageAddItems.filter(item => String(item.item_name).trim().toLowerCase()===typed || String(item.item_code).trim().toLowerCase()===typed);
        const match = selected || (exact.length===1 ? exact[0] : null);
        storageAddItemId.value = match ? Number(match.id) : '';
        storageAddItemPicker.setCustomValidity('');
        storageAddItemPicker.removeAttribute('aria-invalid');
        storageAddMessage('',false);
        renderStorageAddItemSuggestions();
    }
    storageAddItemPicker.addEventListener('input', syncStorageAddItem);
    storageAddItemPicker.addEventListener('focus', renderStorageAddItemSuggestions);
    storageAddItemPicker.addEventListener('change', syncStorageAddItem);
    document.addEventListener('click', function(event){
        if (!event.target.closest('.storage-add-item-picker')) {
            storageAddItemSuggestions.innerHTML = '';
        }
    });
    storageAddItemForm.addEventListener('submit', function(event){
        event.preventDefault();
        if(storageAddBusy)return;
        syncStorageAddItem();
        if (!storageAddItemId.value) {
            storageAddMessage('Choose an item from the dropdown before adding it.',true);
            storageAddItemPicker.setAttribute('aria-invalid','true');
            storageAddItemPicker.focus();
            return;
        }
        const qty=Number(storageAddQty.value);
        storageAddQty.setCustomValidity('');storageAddQty.removeAttribute('aria-invalid');
        if(!Number.isSafeInteger(qty)||qty<1){
            storageAddMessage('Enter a whole quantity of at least 1.',true);
            storageAddQty.setAttribute('aria-invalid','true');storageAddQty.focus();return;
        }
        storageAddItemSuggestions.innerHTML='';
        const payload=new FormData(storageAddItemForm);
        showConfirm('Add this item to the storage request?', function(){
            if(storageAddBusy)return;
            storageAddBusy=true;storageAddButton.disabled=true;storageAddButton.textContent='Adding…';
            storageAddMessage('Adding item to this request…',false);
            startLoading('Adding item...');
            fetch(window.location.href, {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: payload,
                credentials: 'same-origin'
            }).then(function(response){
                if(response.redirected)throw new Error('Your session may have expired. Refresh the page and sign in again.');
                return response.json().catch(function(){throw new Error('The server could not save the item. Refresh the page and try again.');}).then(function(data){return {ok: response.ok, data: data};});
            }).then(function(result){
                if (!result.ok || !result.data.ok) {
                    throw new Error(result.data.message || 'Unable to add the item.');
                }
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.classList.remove('open');
                storageAddMessage(result.data.message || 'Item added to storage request.',false);
                showToastMessage('success', result.data.message || 'Item added to storage request.');
                setTimeout(function(){window.location.reload();}, 700);
            }).catch(function(error){
                storageAddBusy=false;storageAddButton.disabled=false;storageAddButton.textContent='Add Item';
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.classList.remove('open');
                storageAddMessage(error.message || 'Unable to add the item. Please try again.',true);
            });
        });
    });
}
const storageRequestDeleteForm = document.getElementById('storageRequestDeleteForm');
if (storageRequestDeleteForm) {
    storageRequestDeleteForm.addEventListener('submit', function(event){
        event.preventDefault();
        showConfirm('Delete this storage request?', function(){
            storageRequestDeleteForm.dataset.confirm = 'off';
            startLoading('Deleting request...');
            storageRequestDeleteForm.submit();
        });
    });
}
renderStorageRequestItems();
renderStorageRequestCart();
<?php if(isset($catalogData)): ?>
cssrCatalogSearch({input:document.getElementById('storageRequestSearch'),list:document.getElementById('storageRequestItemList'),items:storageRequestItems,render:renderStorageRequestItems,endpoint:'storage_requests.php',room:()=>document.getElementById('storageRequestFrom').value,roomInput:document.getElementById('storageRequestFrom'),initial:<?=json_encode(['has_more'=>$catalogData['has_more'],'next_offset'=>$catalogData['next_offset']])?>});
<?php endif; ?>
<?php if ($selectedRequest && $selectedRequest['status'] === 'Released'): ?>
buildStorageRequestPrintPages();
<?php endif; ?>
<?php if ($selectedRequest && ($_GET['print'] ?? '') === '1'): ?>
setTimeout(function(){printStorageRequestAndClose('<?= e($storageReturnPage) ?>',false);},800);
<?php endif; ?>
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
