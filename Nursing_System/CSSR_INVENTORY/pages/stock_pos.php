<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!shared_has_permission('cssr_inventory', 'view')) {
    http_response_code(403);
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger">Access denied for your role.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = $pageTitle ?? 'Stock Card';
$activePage = $activePage ?? 'stock_pos';
if ($activePage === 'stock_pos') {
    include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel construction-panel">
    <div class="construction-card">
        <span class="eyebrow">Inventory Stock Card</span>
        <h2>Under Construction</h2>
        <p>This page is temporarily unavailable while the Stock Card module is being updated.</p>
        <p class="text-muted">Please use the other inventory records and reports for now.</p>
    </div>
</section>
<?php
    include __DIR__ . '/../includes/footer.php';
    return;
}

$defaultTransactionType = $defaultTransactionType ?? 'STOCK IN';
$receipt = $_SESSION['last_pos_receipt'] ?? null;
unset($_SESSION['last_pos_receipt']);
$viewingReceipt = false;

function require_field(string $key, string $label): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') {
        throw new RuntimeException($label . ' is required.');
    }
    return $value;
}

function next_inbound_number(string $receivedDate): string
{
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedDate) ? $receivedDate : date('Y-m-d');
    $prefix = 'INB-' . date('Y-m', strtotime($date)) . '-';
    $latest = db_value(
        'SELECT inbound_no
         FROM stock_in
         WHERE inbound_no LIKE ?
         ORDER BY inbound_no DESC
         LIMIT 1',
        [$prefix . '%']
    );
    $next = 1;
    if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
        $next = (int) $matches[1] + 1;
    }
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function consume_pos_submission_token(): void
{
    $token = trim((string) ($_POST['submission_token'] ?? ''));
    $tokens = $_SESSION['pos_submission_tokens'] ?? [];
    if ($token === '' || !isset($tokens[$token])) {
        throw new RuntimeException('This transaction was already submitted or the form expired. Please try again.');
    }
    unset($tokens[$token]);
    $_SESSION['pos_submission_tokens'] = $tokens;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!can_manage()) {
            throw new RuntimeException('You do not have permission to save stock card transactions.');
        }
        if (($_POST['action'] ?? '') === 'add_supplier') {
            $supplierName = strtoupper(require_field('supplier_name', 'Supplier'));
            $contactPerson = trim($_POST['contact_person'] ?? '');
            $contactNumber = trim($_POST['contact_number'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $existingSupplier = db_one('SELECT id FROM suppliers WHERE supplier_name=?', [$supplierName]);

            if ($existingSupplier) {
                db()->prepare('UPDATE suppliers SET contact_person=?, contact_number=?, address=?, status="Active" WHERE id=?')
                    ->execute([$contactPerson, $contactNumber, $address, (int) $existingSupplier['id']]);
            } else {
                db()->prepare('INSERT INTO suppliers (supplier_name, contact_person, contact_number, address, status) VALUES (?,?,?,?,?)')
                    ->execute([$supplierName, $contactPerson, $contactNumber, $address, 'Active']);
            }

            log_activity($existingSupplier ? 'UPDATE' : 'CREATE', 'Suppliers', $supplierName);
            set_flash('success', 'Supplier saved.');
            redirect('stock_in.php?supplier=' . urlencode($supplierName));
        }

        if (($_POST['action'] ?? '') === 'return_lot') {
            $stockOutId = (int) ($_POST['stock_out_id'] ?? 0);
            $returnQty = (int) ($_POST['return_quantity'] ?? 0);
            if ($stockOutId <= 0 || $returnQty <= 0) {
                throw new RuntimeException('Enter a valid return quantity.');
            }
            db()->beginTransaction();
            $stmt = db()->prepare('SELECT s.*, l.expiry_date, i.current_stock, i.item_code FROM stock_out s JOIN item_lots l ON l.id=s.item_lot_id JOIN items i ON i.id=s.item_id WHERE s.id=? FOR UPDATE');
            $stmt->execute([$stockOutId]);
            $issued = $stmt->fetch();
            if (!$issued) {
                throw new RuntimeException('The issued lot transaction was not found.');
            }
            $returnable = (int) $issued['quantity_issued'] - (int) $issued['quantity_returned'];
            if ($returnQty > $returnable) {
                throw new RuntimeException('Return quantity exceeds the quantity still returnable for this lot.');
            }
            $newItemBalance = (int) $issued['current_stock'] + $returnQty;
            db()->prepare('UPDATE stock_out SET quantity_returned=quantity_returned+? WHERE id=?')->execute([$returnQty, $stockOutId]);
            db()->prepare('UPDATE item_lots SET quantity_returned=quantity_returned+?, quantity_available=quantity_available+?, status="AVAILABLE" WHERE id=?')->execute([$returnQty, $returnQty, (int) $issued['item_lot_id']]);
            db()->prepare('UPDATE items SET current_stock=? WHERE id=?')->execute([$newItemBalance, (int) $issued['item_id']]);
            db()->prepare('INSERT INTO bin_cards (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([(int) $issued['item_id'], date('Y-m-d'), $issued['reference_no'], 'RETURN', $issued['lot_number'], $issued['expiry_date'], $returnQty, 0, $newItemBalance, $issued['department'], current_user()['full_name'], 'Return to exact issued lot']);
            db()->prepare('INSERT INTO stock_cards (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([(int) $issued['item_id'], date('Y-m-d H:i:s'), $issued['reference_no'], 'RETURN', $issued['lot_number'], $issued['expiry_date'], $returnQty, 0, $newItemBalance, $issued['department'], 'INVENTORY', current_user()['full_name'], 'Return to exact issued lot']);
            log_activity('CREATE', 'Stock Return', 'Returned ' . $returnQty . ' of ' . $issued['item_code'] . ' to lot ' . $issued['lot_number'] . '.');
            db()->commit();
            set_flash('success', 'Returned quantity was restored to lot ' . $issued['lot_number'] . '.');
            redirect('stock_out.php');
        }

        $transactionType = $_POST['transaction_type'] ?? '';
        if (!in_array($transactionType, ['STOCK IN', 'STOCK OUT'], true)) {
            throw new RuntimeException('Invalid transaction type.');
        }
        consume_pos_submission_token();

        $cart = json_decode($_POST['cart_json'] ?? '[]', true);
        if (!is_array($cart) || count($cart) === 0) {
            throw new RuntimeException('Add at least one item to the transaction cart.');
        }

        $remarks = require_field('remarks', 'Remarks');
        $receiptItems = [];
        $procurementRequestIds = [];
        $referenceNo = '';
        $inboundNo = '';
        $transactionDate = '';
        $party = '';
        $storageLocation = '';

        db()->beginTransaction();

        if ($transactionType === 'STOCK IN') {
            $supplier = strtoupper(require_field('supplier', 'Supplier'));
            $prNumber = trim((string)($_POST['pr_number'] ?? ''));
            $poNumber = trim((string)($_POST['po_number'] ?? ''));
            $deliveryReceiptNo = trim((string)($_POST['delivery_receipt_no'] ?? ''));
            $invoiceNumber = trim((string)($_POST['invoice_number'] ?? ''));
            $officialReceiptNo = trim((string)($_POST['official_receipt_no'] ?? ''));
            $procurementType = strtoupper(trim((string)($_POST['procurement_type'] ?? '')));
            if(!in_array($procurementType,['REGULAR','DONATION','EMERGENCY PURCHASE'],true)) throw new RuntimeException('Invalid procurement type.');
            $receivingStatus = strtoupper(trim((string)($_POST['receiving_status'] ?? '')));
            if(!in_array($receivingStatus,['INSPECTED','ADVANCE DELIVERY'],true)) throw new RuntimeException('Invalid receiving status.');
            $deliveryCompletion='COMPLETE';
            $partialReason='';
            if($receivingStatus==='ADVANCE DELIVERY'){
                $prNumber='PENDING';
                $poNumber='PENDING';
                $deliveryReceiptNo='PENDING';
                $invoiceNumber='PENDING';
                $officialReceiptNo='PENDING';
            }elseif($prNumber===''||$poNumber===''||$deliveryReceiptNo===''||$invoiceNumber===''||$officialReceiptNo===''||in_array('PENDING',[strtoupper($prNumber),strtoupper($poNumber),strtoupper($deliveryReceiptNo),strtoupper($invoiceNumber),strtoupper($officialReceiptNo)],true)){
                throw new RuntimeException('PR, PO, Official Receipt, Delivery Receipt, and Invoice numbers are required for Inspected / Available items.');
            }
            $transactionDate = require_field('received_date', 'Received date');
            $inboundNo = next_inbound_number($transactionDate);
            $referenceNo = $inboundNo;
            $receivedBy = current_user()['full_name'];
            $party = $supplier;
            $storageLocation = strtoupper(require_field('storage_room', 'Storage room'));
            $allowedStorageLocations = ['CSSR', 'STORAGE ROOM 1', 'STORAGE ROOM 2', 'STORAGE ROOM 3'];
            if (!in_array($storageLocation, $allowedStorageLocations, true)) {
                throw new RuntimeException('Choose a valid storage room.');
            }
        } else {
            $department = strtoupper(require_field('department', 'Department'));
            $referenceNo = require_field('ris_reference_no', 'RIS/reference number');
            $transactionDate = require_field('issued_date', 'Issued date');
            $issuedBy = current_user()['full_name'];
            $receivedBy = current_user()['full_name'];
            $party = $department;
        }

        foreach ($cart as $line) {
            $itemId = (int) ($line['id'] ?? 0);
            $procurementItemId = (int) ($line['procurement_item_id'] ?? 0);
            $allocations = is_array($line['allocations'] ?? null) ? $line['allocations'] : [];
            $qty = $transactionType === 'STOCK OUT'
                ? array_sum(array_map(fn($allocation) => max(0, (int) ($allocation['qty'] ?? 0)), $allocations))
                : (int) ($line['qty'] ?? 0);
            if ($itemId <= 0 || $qty <= 0) {
                throw new RuntimeException('Every cart item must have a valid quantity.');
            }

            $stmt = db()->prepare('SELECT * FROM items WHERE id=? AND status="Active" FOR UPDATE');
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            if (!$item) {
                throw new RuntimeException('One of the selected items is no longer active.');
            }

            $currentStock = (int) $item['current_stock'];
            if ($transactionType === 'STOCK OUT' && $qty > $currentStock) {
                throw new RuntimeException('Stock out quantity for ' . $item['item_code'] . ' is greater than current stock.');
            }

            if ($transactionType === 'STOCK IN') {
                $procurementItem = null;
                if ($procurementItemId > 0) {
                    $procurementItem=db_one('SELECT * FROM procurement_request_items WHERE id=? AND item_id=? AND winning_supplier=? AND procurement_status IN("PENDING DELIVERY","PARTIALLY DELIVERED") FOR UPDATE',[$procurementItemId,$itemId,$supplier]);
                    $procurementBalance=$procurementItem?(int)$procurementItem['quantity_requested']-(int)$procurementItem['quantity_delivered']:0;
                    if(!$procurementItem||$qty>$procurementBalance)throw new RuntimeException('The received quantity exceeds the remaining Procurement quantity for '.$item['item_description'].'.');
                    if (round((float) ($line['unit_price'] ?? 0), 2) !== round((float) $procurementItem['quoted_unit_cost'], 2)) {
                        throw new RuntimeException('Unit price is locked to the awarded Procurement price. Reload Items Ready for Inbound.');
                    }
                }
                $lotNumber = strtoupper(trim((string) ($line['lot_number'] ?? '')));
                $expiryDate = trim((string) ($line['expiry_date'] ?? '')) ?: null;
                $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);
                $brand = strtoupper(trim((string) ($line['brand'] ?? ($item['brand'] ?? ''))));
                $imageNotApplicable = !empty($line['image_na']);
                $lineKey = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($line['line_key'] ?? $itemId));
                $lotImageUpload = [
                    'name' => $_FILES['lot_image']['name'][$lineKey] ?? '',
                    'type' => $_FILES['lot_image']['type'][$lineKey] ?? '',
                    'tmp_name' => $_FILES['lot_image']['tmp_name'][$lineKey] ?? '',
                    'error' => $_FILES['lot_image']['error'][$lineKey] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['lot_image']['size'][$lineKey] ?? 0,
                ];
                if ($lotNumber === '') {
                    throw new RuntimeException('Lot number is required for ' . $item['item_code'] . '.');
                }
                if ($expiryDate === null) {
                    throw new RuntimeException('Expiration date is required for ' . $item['item_description'] . '.');
                }
                if (!$imageNotApplicable && $lotImageUpload['error'] === UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Upload a picture for ' . $item['item_description'] . '.');
                }
                if ($unitPrice < 0) {
                    throw new RuntimeException('Enter a valid price for ' . $item['item_description'] . '.');
                }
                $markupCost = $unitPrice;
                $totalPrice = round($unitPrice + $markupCost, 2);
                $sellingPrice = $totalPrice;
                $existingLotStmt = db()->prepare(
                    'SELECT id, lot_image
                     FROM item_lots
                     WHERE item_id=? AND lot_number=? AND expiry_date <=> ? AND brand=? AND supplier=? AND storage_location=? AND unit_price=?
                     FOR UPDATE'
                );
                $existingLotStmt->execute([$itemId,$lotNumber,$expiryDate,$brand,$supplier,$storageLocation,$unitPrice]);
                $existingLot = $existingLotStmt->fetch();
                $expectedQty=$qty;
                $remainingQty=0;
                $heldForInspection = $receivingStatus !== 'INSPECTED';
                if($heldForInspection){$acceptedQty=0;$quarantinedReceived=$qty;$rejectedQty=0;}
                else{$acceptedQty=$qty;$quarantinedReceived=0;$rejectedQty=0;}
                $availableReceived = $acceptedQty;
                $lotReceivedQty=$acceptedQty+$quarantinedReceived;
                if ($existingLot) {
                    $lotId = (int) $existingLot['id'];
                    db()->prepare('UPDATE item_lots SET quantity_received=quantity_received+?, quantity_available=quantity_available+?,quantity_quarantined=quantity_quarantined+?,inspection_status=?, status=? WHERE id=?')
                        ->execute([$lotReceivedQty,$availableReceived,$quarantinedReceived,$quarantinedReceived>0?'QUARANTINED':'CLEARED',$availableReceived>0?'AVAILABLE':'QUARANTINED',$lotId]);
                } else {
                    db()->prepare('INSERT INTO item_lots (item_id,lot_number,expiry_date,brand,supplier,storage_location,unit_price,quantity_received,quantity_available,quantity_quarantined,inspection_status,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$itemId,$lotNumber,$expiryDate,$brand,$supplier,$storageLocation,$unitPrice,$lotReceivedQty,$availableReceived,$quarantinedReceived,$quarantinedReceived>0?'QUARANTINED':'CLEARED',$availableReceived>0?'AVAILABLE':'QUARANTINED']);
                    $lotId = (int) db()->lastInsertId();
                }
                if (!$imageNotApplicable && $lotImageUpload['error'] !== UPLOAD_ERR_NO_FILE) {
                    $lotImage = upload_item_image($lotImageUpload);
                    if (!$existingLot || empty($existingLot['lot_image'])) {
                        db()->prepare('UPDATE item_lots SET lot_image=? WHERE id=?')->execute([$lotImage, $lotId]);
                    }
                }
                $balance = $currentStock + $availableReceived;
                db()->prepare('UPDATE items SET current_stock=? WHERE id=?')->execute([$balance, $itemId]);
                $stmt = db()->prepare('INSERT INTO stock_in (item_id,item_lot_id,inbound_no,reference_no,pr_number,procurement_request_item_id,po_number,delivery_receipt_no,invoice_number,official_receipt_no,procurement_type,receiving_status,delivery_completion,supplier,quantity_received,expected_quantity,remaining_quantity,accepted_quantity,quarantined_quantity,rejected_quantity,partial_reason,unit_price,lot_number,expiry_date,received_date,received_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$itemId,$lotId,$inboundNo,$referenceNo,$prNumber,$procurementItemId?:null,$poNumber,$deliveryReceiptNo,$invoiceNumber,$officialReceiptNo,$procurementType,$receivingStatus,$deliveryCompletion,$supplier,$qty,$expectedQty,$remainingQty,$acceptedQty,$quarantinedReceived,$rejectedQty,$partialReason?:null,$unitPrice,$lotNumber,$expiryDate,$transactionDate,$receivedBy,$remarks]);
                $stockInId=(int)db()->lastInsertId();
                if($procurementItem){
                    $newProcurementDelivered=(int)$procurementItem['quantity_delivered']+$qty;
                    db_statement('UPDATE procurement_request_items SET quantity_delivered=?,procurement_status=? WHERE id=?',[$newProcurementDelivered,$newProcurementDelivered>=(int)$procurementItem['quantity_requested']?'DELIVERED':'PARTIALLY DELIVERED',$procurementItemId]);
                    $procurementRequestIds[(int)$procurementItem['request_id']]=true;
                }
                $inspectionNo='INS-'.date('Ymd-His').'-'.$stockInId;db_statement('INSERT INTO receiving_inspections(item_lot_id,stock_in_id,inspection_no,delivered_qty,accepted_qty,quarantined_qty,rejected_qty,findings,decision,inspected_by,inspected_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$lotId,$stockInId,$inspectionNo,$qty,$acceptedQty,$quarantinedReceived,$rejectedQty,$heldForInspection?'Advance delivery pending documents':($partialReason?:'Inbound inspection completed'),$heldForInspection?'FOR INSPECTION':($quarantinedReceived>0?'PARTIALLY ACCEPTED':'ACCEPTED'),$heldForInspection?null:current_user()['full_name'],$heldForInspection?null:date('Y-m-d H:i:s')]);
                $priceIdentity = implode('|', array_map(fn($value) => mb_strtoupper(trim((string) $value)), [$item['item_description'],$item['item_description'],$lotNumber,$expiryDate ?: '',$item['unit'] ?? '',$item['category'] ?? '',$unitPrice,$markupCost,$totalPrice,$sellingPrice,$referenceNo,$lotId]));
                db()->prepare('INSERT INTO item_price_list (item_name,item_description,lot_number,expiration_text,unit_type,classification,unit_price,markup_cost,total_price,selling_price,source_as_of,source_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$item['item_description'],$item['item_description'],$lotNumber,$expiryDate,$item['unit'] ?? '',$item['category'] ?? '',$unitPrice,$markupCost,$totalPrice,$sellingPrice,$transactionDate,hash('sha256',$priceIdentity)]);
                $storageRoomBalance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=? AND storage_location=?', [$itemId, $storageLocation]);
                if (!$heldForInspection && $storageLocation === 'CSSR') {
                    db()->prepare('INSERT INTO bin_cards (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$itemId,$transactionDate,$referenceNo,$transactionType,$lotNumber,$expiryDate,$acceptedQty,0,$storageRoomBalance,$supplier,current_user()['full_name'],$remarks]);
                } elseif (!$heldForInspection) {
                    db()->prepare('INSERT INTO stock_cards (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$itemId,$transactionDate,$referenceNo,$transactionType,$lotNumber,$expiryDate,$acceptedQty,0,$storageRoomBalance,$supplier,$storageLocation,current_user()['full_name'],$remarks]);
                }
                $receiptItems[] = ['code'=>$item['item_code'],'description'=>$item['item_description'],'lot'=>$lotNumber,'expiry'=>$expiryDate,'bin'=>$storageLocation,'qty'=>$qty,'previous'=>$currentStock,'balance'=>$balance];
            } else {
                $runningBalance = $currentStock;
                $usedLotIds = [];
                foreach ($allocations as $allocation) {
                    $lotId = (int) ($allocation['lot_id'] ?? 0);
                    $take = (int) ($allocation['qty'] ?? 0);
                    if ($lotId <= 0 || $take <= 0 || isset($usedLotIds[$lotId])) {
                        throw new RuntimeException('Each selected lot must have one valid positive quantity.');
                    }
                    $fefoLotId=(int)db_value('SELECT id FROM item_lots WHERE item_id=? AND quantity_available>0 AND status="AVAILABLE" AND lot_number<>"UNIDENTIFIED / PENDING" AND (expiry_date IS NULL OR expiry_date>=CURDATE()) ORDER BY expiry_date IS NULL,expiry_date,created_at,id LIMIT 1',[$itemId]);
                    if($fefoLotId>0&&$lotId!==$fefoLotId){throw new RuntimeException('FEFO requires the earliest-expiring available lot for '.$item['item_description'].'.');}
                    $usedLotIds[$lotId] = true;
                    $stmt = db()->prepare('SELECT * FROM item_lots WHERE id=? AND item_id=? AND lot_number<>"UNIDENTIFIED / PENDING" FOR UPDATE');
                    $stmt->execute([$lotId, $itemId]);
                    $lot = $stmt->fetch();
                    if (!$lot || (int) $lot['quantity_available'] <= 0) {
                        throw new RuntimeException('A selected lot is no longer available for ' . $item['item_code'] . '.');
                    }
                    $reservedQty=(int)db_value('SELECT COALESCE(SUM(quantity),0) FROM stock_reservations WHERE item_lot_id=? AND status="ACTIVE" AND (expires_at IS NULL OR expires_at>NOW())',[$lotId]);
                    if($take>(int)$lot['quantity_available']-$reservedQty){throw new RuntimeException('The requested quantity includes stock reserved for an approved department request.');}
                    if (!empty($lot['expiry_date']) && $lot['expiry_date'] < date('Y-m-d')) {
                        throw new RuntimeException('Expired lot ' . $lot['lot_number'] . ' cannot be issued.');
                    }
                    if ($take > (int) $lot['quantity_available']) {
                        throw new RuntimeException('Issue quantity exceeds the available balance of lot ' . $lot['lot_number'] . '.');
                    }
                    $lotBalance = (int) $lot['quantity_available'] - $take;
                    db()->prepare('UPDATE item_lots SET quantity_issued=quantity_issued+?,quantity_available=?,status=? WHERE id=?')->execute([$take,$lotBalance,$lotBalance>0?'AVAILABLE':'DEPLETED',$lotId]);
                    $runningBalance -= $take;
                    db()->prepare('INSERT INTO stock_out (item_id,item_lot_id,reference_no,department,quantity_issued,lot_number,issued_date,issued_by,received_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$itemId,(int)$lot['id'],$referenceNo,$department,$take,$lot['lot_number'],$transactionDate,$issuedBy,$receivedBy,$remarks]);
                    $storageRoomBalance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=? AND storage_location=?', [$itemId, $lot['storage_location']]);
                    if ($lot['storage_location'] === 'CSSR') {
                        db()->prepare('INSERT INTO bin_cards (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,department,encoded_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$itemId,$transactionDate,$referenceNo,$transactionType,$lot['lot_number'],$lot['expiry_date'],0,$take,$storageRoomBalance,$department,current_user()['full_name'],$remarks]);
                    } else {
                        db()->prepare('INSERT INTO stock_cards (item_id,transaction_date,reference_no,transaction_type,lot_number,expiry_date,received_qty,issued_qty,balance_qty,from_location,to_location,encoded_by,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$itemId,$transactionDate,$referenceNo,$transactionType,$lot['lot_number'],$lot['expiry_date'],0,$take,$storageRoomBalance,$lot['storage_location'],$department,current_user()['full_name'],$remarks]);
                    }
                    $receiptItems[] = ['code'=>$item['item_code'],'description'=>$item['item_description'],'lot'=>$lot['lot_number'],'expiry'=>$lot['expiry_date'],'bin'=>$item['bin_location'],'qty'=>$take,'previous'=>$runningBalance+$take,'balance'=>$runningBalance];
                }
                $balance = $runningBalance;
                db()->prepare('UPDATE items SET current_stock=? WHERE id=?')->execute([$balance,$itemId]);
            }
        }

        log_activity('CREATE', $transactionType, $transactionType . ' POS transaction ' . $referenceNo . ' with ' . count($receiptItems) . ' item(s).');
        foreach(array_keys($procurementRequestIds) as $procurementRequestId){
            $awaiting=(int)db_value('SELECT COUNT(*) FROM procurement_request_items WHERE request_id=? AND procurement_status="AWAITING QUOTATION"',[$procurementRequestId]);
            $remaining=(int)db_value('SELECT COALESCE(SUM(quantity_requested-quantity_delivered),0) FROM procurement_request_items WHERE request_id=? AND procurement_status<>"AWAITING QUOTATION"',[$procurementRequestId]);
            db_statement('UPDATE procurement_requests SET status=? WHERE id=?',[$awaiting?'PARTIALLY PROCESSED':($remaining>0?'PARTIALLY DELIVERED':'COMPLETED'),$procurementRequestId]);
        }
        db()->commit();

        $_SESSION['last_pos_receipt'] = [
            'type' => $transactionType,
            'inbound_no' => $inboundNo,
            'reference' => $referenceNo,
            'date' => $transactionDate,
            'party' => $party,
            'encoded_by' => current_user()['full_name'],
            'remarks' => $remarks,
            'items' => $receiptItems,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        unset($_SESSION['pos_form_state']);
        set_flash('success', $transactionType . ' transaction saved.');
        redirect($transactionType === 'STOCK IN' ? 'stock_in.php' : 'stock_out.php');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        $_SESSION['pos_form_state'] = [
            'type' => $defaultTransactionType,
            'fields' => array_diff_key($_POST, ['csrf_token'=>true,'submission_token'=>true]),
            'cart' => is_array(json_decode((string)($_POST['cart_json'] ?? '[]'), true)) ? json_decode((string)$_POST['cart_json'], true) : [],
        ];
        set_flash('danger', safe_error_message($e));
        redirect($defaultTransactionType === 'STOCK IN' ? 'stock_in.php' : 'stock_out.php');
    }
}

$savedPosState = $_SESSION['pos_form_state'] ?? [];
unset($_SESSION['pos_form_state']);
if (($savedPosState['type'] ?? '') !== $defaultTransactionType) $savedPosState=[];
$savedFields = is_array($savedPosState['fields'] ?? null) ? $savedPosState['fields'] : [];
$savedCart = is_array($savedPosState['cart'] ?? null) ? $savedPosState['cart'] : [];

$items = db()->query("SELECT id, barcode, item_code, item_name, item_description, category, unit, brand, supplier, lot_number, expiry_date, bin_location, minimum_stock, current_stock, item_image FROM items WHERE status='Active' ORDER BY item_name,item_description")->fetchAll();
$posSubmissionToken = bin2hex(random_bytes(24));
$posSubmissionTokens = $_SESSION['pos_submission_tokens'] ?? [];
$posSubmissionTokens = array_filter($posSubmissionTokens, static fn($createdAt) => (int) $createdAt >= time() - 7200);
$posSubmissionTokens[$posSubmissionToken] = time();
$_SESSION['pos_submission_tokens'] = $posSubmissionTokens;
$lotRows = db()->query('SELECT id,item_id,lot_number,expiry_date,supplier,storage_location,unit_price,quantity_available FROM item_lots WHERE quantity_available>0 AND lot_number<>"UNIDENTIFIED / PENDING" AND (expiry_date IS NULL OR expiry_date>=CURDATE()) ORDER BY item_id,CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END,expiry_date,created_at,id')->fetchAll();
$lotsByItem = [];
foreach ($lotRows as $lotRow) {
    $lotsByItem[(int) $lotRow['item_id']][] = $lotRow;
}
foreach ($items as &$itemRow) {
    $itemRow['lots'] = $lotsByItem[(int) $itemRow['id']] ?? [];
}
unset($itemRow);
$suppliers = db()->query("SELECT supplier_name FROM suppliers WHERE status='Active' ORDER BY supplier_name")->fetchAll();
$departments = db()->query("SELECT department_name FROM departments WHERE status='Active' ORDER BY department_name")->fetchAll();
$selectedSupplier = strtoupper(trim($_GET['supplier'] ?? ''));
$procurementInboundCards=[];
if($defaultTransactionType==='STOCK IN'){
    $procurementInboundCards=db_all('SELECT r.id request_id,COALESCE(NULLIF(r.procurement_pr_no,""),r.pr_no) pr_number,p.ppmp_no,i.winning_supplier,COUNT(*) item_count,SUM(i.quantity_requested-i.quantity_delivered) remaining_qty FROM procurement_requests r JOIN procurement_ppmp p ON p.id=r.ppmp_id JOIN procurement_request_items i ON i.request_id=r.id WHERE i.procurement_status IN("PENDING DELIVERY","PARTIALLY DELIVERED") AND i.quantity_delivered<i.quantity_requested GROUP BY r.id,COALESCE(NULLIF(r.procurement_pr_no,""),r.pr_no),p.ppmp_no,i.winning_supplier ORDER BY r.id DESC,i.winning_supplier');
    $loadRequestId=(int)($_GET['procurement_request']??0);$loadSupplier=trim((string)($_GET['procurement_supplier']??''));
    if($loadRequestId>0&&$loadSupplier!==''&&!$savedCart){
        $loadRows=db_all('SELECT ri.id procurement_item_id,ri.item_id,ri.quantity_requested-ri.quantity_delivered remaining_qty,ri.quoted_unit_cost,r.procurement_pr_no,r.pr_no FROM procurement_request_items ri JOIN procurement_requests r ON r.id=ri.request_id WHERE ri.request_id=? AND ri.winning_supplier=? AND ri.procurement_status IN("PENDING DELIVERY","PARTIALLY DELIVERED") AND ri.quantity_delivered<ri.quantity_requested ORDER BY ri.id',[$loadRequestId,$loadSupplier]);
        foreach($loadRows as $loadRow)$savedCart[]=['id'=>(int)$loadRow['item_id'],'qty'=>(int)$loadRow['remaining_qty'],'unit_price'=>(float)$loadRow['quoted_unit_cost'],'procurement_item_id'=>(int)$loadRow['procurement_item_id'],'expected_quantity'=>(int)$loadRow['remaining_qty'],'lot_number'=>'','expiry_date'=>'','allocations'=>[]];
        if($loadRows){$selectedSupplier=strtoupper($loadSupplier);$savedFields=array_merge($savedFields,['supplier'=>$loadSupplier,'pr_number'=>$loadRows[0]['procurement_pr_no']?:$loadRows[0]['pr_no'],'received_date'=>date('Y-m-d'),'procurement_type'=>'REGULAR']);}
    }
}
$nextInboundNoPreview = $defaultTransactionType === 'STOCK IN' ? next_inbound_number(date('Y-m-d')) : '';

if ($defaultTransactionType === 'STOCK OUT' && !empty($_GET['view_ref']) && !empty($_GET['view_date']) && !empty($_GET['view_party'])) {
    $stmt = db()->prepare('SELECT s.*, i.item_code, i.item_description FROM stock_out s JOIN items i ON i.id=s.item_id WHERE s.reference_no=? AND s.issued_date=? AND s.department=? ORDER BY s.id ASC');
    $stmt->execute([$_GET['view_ref'], $_GET['view_date'], $_GET['view_party']]);
    $receiptRows = $stmt->fetchAll();
    if ($receiptRows) {
        $viewingReceipt = true;
        $firstRow = $receiptRows[0];
        $receipt = [
            'type' => 'STOCK OUT',
            'reference' => $firstRow['reference_no'],
            'date' => $firstRow['issued_date'],
            'party' => $firstRow['department'],
            'encoded_by' => $firstRow['issued_by'],
            'remarks' => $firstRow['remarks'],
            'items' => array_map(fn($row) => [
                'stock_out_id' => (int) $row['id'],
                'code' => $row['item_code'],
                'description' => $row['item_description'],
                'lot' => $row['lot_number'],
                'qty' => (int) $row['quantity_issued'],
                'returned' => (int) $row['quantity_returned'],
            ], $receiptRows),
            'created_at' => $firstRow['created_at'],
        ];
    }
}

$recentFrom = trim($_GET['recent_from'] ?? '');
$recentTo = trim($_GET['recent_to'] ?? '');
$recentDepartment = trim($_GET['recent_department'] ?? '');

if ($defaultTransactionType === 'STOCK IN') {
    $recentSql = 'SELECT COALESCE(NULLIF(s.inbound_no, ""), s.reference_no) inbound_no, MIN(s.created_at) created_at, MIN(s.received_date) tx_date, s.reference_no, s.supplier party, COUNT(*) line_count, SUM(s.quantity_received) qty FROM stock_in s GROUP BY COALESCE(NULLIF(s.inbound_no, ""), s.reference_no), s.reference_no, s.supplier ORDER BY MIN(s.created_at) DESC LIMIT 20';
    $recent = db()->query($recentSql)->fetchAll();
} else {
    $recentWhere = [];
    $recentParams = [];
    if ($recentFrom !== '') {
        $recentWhere[] = 's.issued_date >= ?';
        $recentParams[] = $recentFrom;
    }
    if ($recentTo !== '') {
        $recentWhere[] = 's.issued_date <= ?';
        $recentParams[] = $recentTo;
    }
    if ($recentDepartment !== '') {
        $recentWhere[] = 's.department = ?';
        $recentParams[] = $recentDepartment;
    }
    $recentSql = 'SELECT s.issued_date tx_date, s.reference_no, s.department party, s.issued_by, s.received_by, s.remarks, MIN(s.created_at) created_at, COUNT(*) line_count, SUM(s.quantity_issued) qty FROM stock_out s';
    if ($recentWhere) {
        $recentSql .= ' WHERE ' . implode(' AND ', $recentWhere);
    }
    $recentSql .= ' GROUP BY s.issued_date, s.reference_no, s.department, s.issued_by, s.received_by, s.remarks ORDER BY MIN(s.created_at) DESC LIMIT 100';
    $stmt = db()->prepare($recentSql);
    $stmt->execute($recentParams);
    $recent = $stmt->fetchAll();
}
$recentFilterQuery = http_build_query(array_filter([
    'recent_from' => $recentFrom,
    'recent_to' => $recentTo,
    'recent_department' => $recentDepartment,
], fn($value) => $value !== ''));

include __DIR__ . '/../includes/header.php';
?>
<?php if($defaultTransactionType==='STOCK IN'&&$procurementInboundCards):?>
<section class="page-panel procurement-inbound-panel"><div class="pos-panel-title"><div><span class="eyebrow">From Procurement</span><h3>Items Ready for Inbound</h3></div><span class="pos-count"><?=count($procurementInboundCards)?> cards</span></div><div class="procurement-inbound-cards"><?php foreach($procurementInboundCards as $card):$addUrl='stock_in.php?'.http_build_query(['procurement_request'=>$card['request_id'],'procurement_supplier'=>$card['winning_supplier'],'procurement_added'=>1]);?><article class="procurement-inbound-card"><div class="procurement-card-main"><strong><?=e($card['pr_number'])?></strong><span><?=e($card['winning_supplier'])?></span><small><?=e($card['ppmp_no'])?></small></div><div class="procurement-card-side"><div class="procurement-card-total"><b><?=number_format((int)$card['remaining_qty'])?></b><small><?=number_format((int)$card['item_count'])?> item(s)</small></div><a class="btn btn-primary procurement-card-add" href="<?=e($addUrl)?>" title="Add to inbound" aria-label="Add <?=e($card['pr_number'].' from '.$card['winning_supplier'])?> to inbound">+</a></div></article><?php endforeach;?></div></section>
<style>.procurement-inbound-panel{margin-bottom:16px}.procurement-inbound-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:10px}.procurement-inbound-card{display:flex;justify-content:space-between;gap:14px;padding:14px;border:1px solid #cfdee7;border-left:4px solid #087f5b;border-radius:10px;background:#fff;color:#173d52}.procurement-inbound-card:hover{background:#f0faf6}.procurement-inbound-card strong,.procurement-inbound-card span,.procurement-inbound-card small{display:block}.procurement-inbound-card span{margin-top:4px;font-weight:700}.procurement-inbound-card small{margin-top:3px;color:#6c8190}.procurement-card-main{min-width:0}.procurement-card-main ul{margin:10px 0 0;padding-left:18px;color:#405d6d}.procurement-card-main li{margin-top:5px;line-height:1.35}.procurement-card-side{display:flex;flex:0 0 auto;flex-direction:column;align-items:flex-end;justify-content:space-between;gap:12px}.procurement-card-total{text-align:right}.procurement-card-total b{font-size:20px;color:#087f5b}.procurement-card-add{display:grid!important;place-items:center;width:36px;height:36px;min-width:36px;padding:0!important;border-radius:6px!important;font-size:22px!important;line-height:1!important;text-decoration:none}</style>
<style>.procurement-inbound-card{position:relative;padding-bottom:14px;padding-right:62px}.procurement-card-add{position:absolute;top:50%;right:14px;bottom:auto;left:auto;transform:translateY(-50%)}.procurement-card-add:hover{transform:translateY(-50%) translateX(1px)}</style>
<?php endif;?>
<section class="pos-shell <?= $defaultTransactionType === 'STOCK IN' ? 'inbound-shell inbound-popup-layout' : '' ?>">
    <?php if($defaultTransactionType==='STOCK IN'):?><dialog id="inboundItemDialog" class="inbound-item-dialog"><?php else:?><div class="pos-left page-panel"><?php endif;?>
        <div class="pos-panel-title">
            <div>
                <span class="eyebrow"><?= $defaultTransactionType === 'STOCK IN' ? 'Item Masterlist' : 'Item Search' ?></span>
                <h3><?= $defaultTransactionType === 'STOCK IN' ? 'Add Delivered Items' : 'Find and Add Items' ?></h3>
            </div>
            <div class="inbound-dialog-actions"><?php if($defaultTransactionType==='STOCK IN'):?><button class="btn btn-primary btn-sm" id="inboundAddSelected" type="button" onclick="addSelectedInboundItems()" disabled>Add Items</button><button class="btn btn-light btn-sm" type="button" onclick="closeInboundItems()">Close</button><?php endif;?></div>
        </div>
        <input id="posSearch" class="form-control pos-search" placeholder="<?= $defaultTransactionType === 'STOCK IN' ? 'Search item name' : 'Search code, description, lot, category, or bin location' ?>">
        <div id="posItemList" class="pos-item-list"></div>
    <?php if($defaultTransactionType==='STOCK IN'):?></dialog><style>.inbound-popup-layout{grid-template-columns:1fr!important}.inbound-popup-layout>.inbound-delivery-panel{width:100%;min-height:auto}.inbound-item-dialog{width:min(920px,95vw);height:min(82vh,760px);padding:18px;border:0;border-top:4px solid #087f5b;border-radius:13px;background:#fff;color:#17352f;box-shadow:0 25px 80px #102b3b66}.inbound-item-dialog::backdrop{background:#142a397d;backdrop-filter:blur(2px)}.inbound-item-dialog .pos-panel-title{position:sticky;top:-18px;z-index:2;margin:-18px -18px 12px;padding:15px 18px;border-bottom:1px solid #dce7e2;background:#fff}.inbound-dialog-actions{display:flex;align-items:center;gap:8px}.inbound-view-cart{display:inline-flex;align-items:center;gap:7px}.inbound-cart-count{display:grid;place-items:center;min-width:21px;height:21px;padding:0 6px;border-radius:999px;background:#fff;color:#087f5b;font-size:11px;font-weight:800}.inbound-item-dialog .pos-item-list{max-height:calc(82vh - 145px);overflow:auto}.inbound-item-dialog .pos-item-card.is-selected{border-color:#0b72b9!important;background:#eaf4ff!important;box-shadow:inset 4px 0 0 #0b72b9}.inbound-item-dialog .pos-item-card.is-selected:hover{background:#dceeff!important}@media(max-width:650px){.inbound-item-dialog{width:100vw;height:100dvh;max-width:none;max-height:none;border-radius:0}.inbound-item-dialog .pos-panel-title{align-items:flex-start}.inbound-dialog-actions{flex-wrap:wrap;justify-content:flex-end}.inbound-dialog-actions>.pos-count{display:none}.inbound-view-cart{min-height:38px}}</style><?php else:?></div><?php endif;?>
    <style>.inbound-item-dialog .pos-item-card{grid-template-columns:44px minmax(0,1fr);cursor:pointer}</style>

    <form id="posForm" class="pos-right page-panel mobile-cart-drawer <?= $defaultTransactionType === 'STOCK IN' ? 'inbound-workflow inbound-delivery-panel' : '' ?>" method="post" enctype="multipart/form-data" data-confirm="custom">
        <?= csrf_field() ?>
        <input type="hidden" name="submission_token" value="<?= e($posSubmissionToken) ?>">
        <input type="hidden" name="cart_json" id="cartJson">
        <div class="pos-panel-title">
            <div>
                <span class="eyebrow"><?= $defaultTransactionType === 'STOCK IN' ? 'Inbound Delivery' : 'Transaction Cart' ?></span>
                <h3><?= $defaultTransactionType === 'STOCK IN' ? 'Complete Delivery Information' : 'Stock Movement' ?></h3>
            </div>
            <input id="transactionType" type="hidden" name="transaction_type" value="<?= e($defaultTransactionType) ?>">
            <div class="mobile-cart-head-actions"><?php if($defaultTransactionType==='STOCK IN'):?><button class="btn btn-primary btn-sm" type="button" onclick="openInboundItems()">Add Items</button><?php endif;?><span class="pos-count"><?= e($defaultTransactionType) ?></span><button class="btn btn-light btn-sm mobile-cart-close" type="button" onclick="closeStockCart()" aria-label="Close selected items">Close</button></div>
        </div>

        <div id="stockInFields" class="row pos-fields">
            <div class="col-md-6 mb-2"><label class="form-label">Inbound No.</label><input class="form-control" value="<?= e($nextInboundNoPreview) ?>" readonly></div>
            <div class="col-md-6 mb-2">
                <label class="form-label">Supplier</label>
                <input class="form-control text-uppercase" name="supplier" list="supplierList" value="<?= e($savedFields['supplier'] ?? $selectedSupplier) ?>" required>
            </div>
            <div class="col-md-3 mb-2"><label class="form-label">PR Number</label><input class="form-control" name="pr_number" data-advance-pending value="<?=e($savedFields['pr_number']??'')?>"></div>
            <div class="col-md-3 mb-2"><label class="form-label">PO Number</label><input class="form-control" name="po_number" data-advance-pending value="<?=e($savedFields['po_number']??'')?>"></div>
            <div class="col-md-3 mb-2"><label class="form-label">Delivery Receipt</label><input class="form-control" name="delivery_receipt_no" data-advance-pending value="<?=e($savedFields['delivery_receipt_no']??'')?>"></div>
            <div class="col-md-3 mb-2"><label class="form-label">Invoice Number</label><input class="form-control" name="invoice_number" data-advance-pending value="<?=e($savedFields['invoice_number']??'')?>"></div>
            <div class="col-md-3 mb-2"><label class="form-label">Official Receipt (OR)</label><input class="form-control" name="official_receipt_no" data-advance-pending value="<?=e($savedFields['official_receipt_no']??'')?>"></div>
            <div class="col-md-3 mb-2"><label class="form-label">Procurement Type</label><select class="form-select" name="procurement_type" required><option value="">Select procurement type</option><?php foreach(['REGULAR','DONATION','EMERGENCY PURCHASE'] as $ptype):?><option <?=($savedFields['procurement_type']??'')===$ptype?'selected':''?>><?=e($ptype)?></option><?php endforeach;?></select></div>
            <div class="col-md-3 mb-2"><label class="form-label">Receiving Items</label><select class="form-select" id="receivingStatus" name="receiving_status" required><option value="">Select receiving status</option><option value="ADVANCE DELIVERY" <?=($savedFields['receiving_status']??'')==='ADVANCE DELIVERY'?'selected':''?>>ADVANCE DELIVERY - ON HOLD</option><option value="INSPECTED" <?=($savedFields['receiving_status']??'')==='INSPECTED'?'selected':''?>>INSPECTED / AVAILABLE</option></select></div>
            <div class="col-md-6 mb-2"><label class="form-label">Storage Room</label><select class="form-select" id="storageRoom" name="storage_room" required><option value="">Select storage room</option><?php foreach(['CSSR','STORAGE ROOM 1','STORAGE ROOM 2','STORAGE ROOM 3'] as $room):?><option value="<?=e($room)?>" <?=($savedFields['storage_room']??'')===$room?'selected':''?>><?=e($room)?></option><?php endforeach;?></select></div>
            <div class="col-md-6 mb-2"><label class="form-label">Received Date</label><input class="form-control" type="date" name="received_date" value="<?= e($savedFields['received_date']??'') ?>" required></div>
            <div class="col-md-6 mb-2"><label class="form-label">Received By</label><input class="form-control" name="received_by" value="<?= e(current_user()['full_name']) ?>" readonly></div>
            <div class="col-md-6 mb-2"><label class="form-label">Remarks</label><input class="form-control" name="remarks" value="<?=e($savedFields['remarks']??'')?>" required></div>
        </div>

        <div id="stockOutFields" class="row pos-fields">
            <div class="col-md-6 mb-2"><label class="form-label">Department</label><input class="form-control text-uppercase" name="department" list="departmentList"></div>
            <div class="col-md-6 mb-2"><label class="form-label">RIS / Reference No.</label><input class="form-control" name="ris_reference_no"></div>
            <div class="col-md-6 mb-2"><label class="form-label">Issued Date</label><input class="form-control" type="date" name="issued_date" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-6 mb-2"><label class="form-label">Issued By</label><input class="form-control" name="issued_by" value="<?= e(current_user()['full_name']) ?>" readonly></div>
            <div class="col-md-12 mb-2"><label class="form-label">Received By</label><input class="form-control" name="stockout_received_by" value="<?= e(current_user()['full_name']) ?>" readonly></div>
        </div>

        <datalist id="supplierList"><?php foreach ($suppliers as $supplier): ?><option value="<?= e($supplier['supplier_name']) ?>"><?php endforeach; ?></datalist>
        <datalist id="departmentList"><?php foreach ($departments as $department): ?><option value="<?= e($department['department_name']) ?>"><?php endforeach; ?></datalist>

        <?php if ($defaultTransactionType !== 'STOCK IN'): ?>
        <label class="form-label">Remarks</label>
        <textarea class="form-control mb-2" name="remarks" rows="2" required></textarea>
        <?php endif; ?>

        <div id="cartList" class="pos-cart-list"></div>

        <div class="pos-total-bar">
            <div><span>Total Lines</span><strong id="cartLines">0</strong></div>
            <div><span>Total Quantity</span><strong id="cartQty">0</strong></div>
            <?php if ($defaultTransactionType === 'STOCK IN'): ?><button class="btn btn-light" type="button" onclick="clearInboundCart()">Clear</button><?php endif; ?>
            <button class="btn btn-primary" type="submit">Save Transaction</button>
        </div>
    </form>
    <div id="stockMobileCartBar" class="mobile-cart-bar" aria-live="polite">
        <div><strong id="stockMobileCartCount">0</strong><span> selected item(s)</span></div>
        <button class="btn btn-primary" type="button" onclick="openStockCart()">View Cart</button>
    </div>
</section>

<div id="stockSummaryModal" class="app-modal">
    <div class="app-modal-card">
        <div class="app-modal-head">
            <div><span class="eyebrow">Transaction Summary</span><h3 id="stockSummaryTitle">Confirm Items</h3></div>
            <button class="btn btn-light btn-sm" type="button" data-modal-close>Close</button>
        </div>
        <div class="app-modal-body">
            <div class="table-responsive stock-summary-scroll">
                <table class="table stock-summary-table" data-enhanced="true">
                    <thead><tr><th>Code</th><th>Description</th><th>Lot Number</th><th>Qty</th></tr></thead>
                    <tbody id="stockSummaryRows"></tbody>
                </table>
            </div>
            <div class="summary-pager" id="stockSummaryPager"></div>
            <div class="pos-total-bar">
                <div><span>Total Lines</span><strong id="stockSummaryLines">0</strong></div>
                <div><span>Total Qty</span><strong id="stockSummaryQty">0</strong></div>
                <button class="btn btn-primary" type="button" id="confirmStockSubmit">Confirm and Save</button>
            </div>
        </div>
    </div>
</div>

<?php if ($defaultTransactionType === 'STOCK IN'): ?>
<div id="addSupplierModal" class="app-modal">
    <div class="app-modal-card">
        <div class="app-modal-head">
            <div><span class="eyebrow">Supplier Record</span><h3>Add New Supplier</h3></div>
            <button class="btn btn-light btn-sm" type="button" data-modal-close>Close</button>
        </div>
        <form class="app-modal-body" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_supplier">
            <div class="form-section">
                <div class="form-section-title"><h4>Company Information</h4><span>Saved to supplier list</span></div>
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label required">Supplier</label><input class="form-control text-uppercase" name="supplier_name" required></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Contact Person</label><input class="form-control" name="contact_person"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Contact No.</label><input class="form-control" name="contact_number"></div>
                    <div class="col-md-12 mb-2"><label class="form-label">Address</label><input class="form-control" name="address"></div>
                </div>
            </div>
            <div class="modal-form-actions">
                <button class="btn btn-light" type="button" data-modal-close>Cancel</button>
                <button class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($receipt): ?>
<?php if ($viewingReceipt): ?>
<div id="transactionViewModal" class="app-modal open no-print">
    <div class="app-modal-card">
        <div class="app-modal-head">
            <div>
                <span class="eyebrow">Transaction View</span>
                <h3><?= e($receipt['type']) ?> Transaction</h3>
            </div>
            <div class="request-modal-actions">
                <button class="btn btn-primary" onclick="printPage()">Print Receipt</button>
                <a class="btn btn-light" href="stock_out.php<?= $recentFilterQuery ? '?' . e($recentFilterQuery) : '' ?>#recentIssuance">Close</a>
            </div>
        </div>
        <div class="app-modal-body">
            <div class="receipt-meta">
                <span><strong>Reference:</strong> <?= e($receipt['reference']) ?></span>
                <span><strong>Date:</strong> <?= e($receipt['date']) ?></span>
                <span><strong>Department:</strong> <?= e($receipt['party']) ?></span>
                <span><strong>Encoded By:</strong> <?= e($receipt['encoded_by']) ?></span>
            </div>
            <div class="table-responsive receipt-items-scroll">
                <table class="table" data-enhanced="true">
                    <thead><tr><th>Code</th><th>Description</th><th>Lot</th><th>Issued</th><th>Returned</th><th class="no-print">Return to this lot</th></tr></thead>
                    <tbody><?php foreach ($receipt['items'] as $item): $returnable = (int) $item['qty'] - (int) ($item['returned'] ?? 0); ?><tr><td><?= e($item['code']) ?></td><td><?= e($item['description']) ?></td><td><?= e($item['lot']) ?></td><td><?= (int) $item['qty'] ?></td><td><?= (int) ($item['returned'] ?? 0) ?></td><td class="no-print"><?php if ($returnable > 0): ?><form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="return_lot"><input type="hidden" name="stock_out_id" value="<?= (int) $item['stock_out_id'] ?>"><input class="form-control form-control-sm" style="width:90px" type="number" min="1" max="<?= $returnable ?>" name="return_quantity" required><button class="btn btn-sm btn-primary" onclick="return confirm('Return this quantity to lot <?= e($item['lot']) ?>?')">Return</button></form><?php else: ?>Fully returned<?php endif; ?></td></tr><?php endforeach; ?></tbody>
                </table>
            </div>
            <p class="receipt-remarks"><strong>Remarks:</strong> <?= e($receipt['remarks']) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>
<section class="pos-receipt page-panel <?= $receipt['type'] === 'STOCK OUT' ? 'receipt-print-only' : '' ?>" id="transactionReceipt">
    <div class="receipt-head">
        <div>
            <span class="eyebrow">Printable Receipt</span>
            <h3><?= e($receipt['type']) ?> Transaction</h3>
        </div>
        <?php if ($receipt['type'] !== 'STOCK OUT'): ?><button class="btn btn-primary no-print" onclick="printPage()">Print Receipt</button><?php endif; ?>
    </div>
    <div class="receipt-meta">
        <?php if ($receipt['type'] === 'STOCK IN'): ?><span><strong>Inbound No.:</strong> <?= e($receipt['inbound_no'] ?? '') ?></span><?php endif; ?>
        <span><strong>Reference:</strong> <?= e($receipt['reference']) ?></span>
        <span><strong>Date:</strong> <?= e($receipt['date']) ?></span>
        <span><strong><?= $receipt['type'] === 'STOCK IN' ? 'Supplier' : 'Department' ?>:</strong> <?= e($receipt['party']) ?></span>
        <span><strong>Encoded By:</strong> <?= e($receipt['encoded_by']) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Code</th><th>Description</th><th>Lot</th><th>Qty</th></tr></thead>
            <tbody><?php foreach ($receipt['items'] as $item): ?><tr><td><?= e($item['code']) ?></td><td><?= e($item['description']) ?></td><td><?= e($item['lot']) ?></td><td><?= (int) $item['qty'] ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
    <p class="receipt-remarks"><strong>Remarks:</strong> <?= e($receipt['remarks']) ?></p>
</section>
<?php endif; ?>

<?php if ($defaultTransactionType === 'STOCK OUT'): ?>
<section class="page-panel" id="recentIssuance">
    <h3>Recent <?= e($defaultTransactionType === 'STOCK IN' ? 'Receiving' : 'Issuance') ?></h3>
    <?php if ($defaultTransactionType === 'STOCK OUT'): ?>
        <form class="toolbar no-print" method="get" action="stock_out.php#recentIssuance">
            <div class="field">
                <label class="form-label">Start Date</label>
                <input class="form-control" type="date" name="recent_from" value="<?= e($recentFrom) ?>">
            </div>
            <div class="field">
                <label class="form-label">End Date</label>
                <input class="form-control" type="date" name="recent_to" value="<?= e($recentTo) ?>">
            </div>
            <div class="field">
                <label class="form-label">Department</label>
                <select class="form-select" name="recent_department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= e($department['department_name']) ?>" <?= $recentDepartment === $department['department_name'] ? 'selected' : '' ?>><?= e($department['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary">Apply Filter</button>
            <a class="btn btn-light" href="stock_out.php#recentIssuance">Reset</a>
        </form>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table">
            <?php if ($defaultTransactionType === 'STOCK OUT'): ?>
                <thead><tr><th>Date</th><th>Reference</th><th>Department</th><th>Lines</th><th>Total Qty</th><th>Issued By</th><th>Encoded</th><th class="no-print">Action</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td><?= e($row['tx_date']) ?></td>
                        <td><strong><?= e($row['reference_no']) ?></strong></td>
                        <td><?= e($row['party']) ?></td>
                        <td><?= (int) $row['line_count'] ?></td>
                        <td><?= (int) $row['qty'] ?></td>
                        <td><?= e($row['issued_by']) ?></td>
                        <td><?= e($row['created_at']) ?></td>
                        <td class="no-print"><a class="btn btn-sm btn-light" href="?<?= e(http_build_query(array_filter([
                            'recent_from' => $recentFrom,
                            'recent_to' => $recentTo,
                            'recent_department' => $recentDepartment,
                            'view_ref' => $row['reference_no'],
                            'view_date' => $row['tx_date'],
                            'view_party' => $row['party'],
                        ], fn($value) => $value !== ''))) ?>#recentIssuance">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            <?php else: ?>
                <thead><tr><th>Date</th><th>Inbound No.</th><th>PO/PR Number</th><th>Supplier</th><th>Lines</th><th>Total Qty</th><th>Encoded</th></tr></thead>
                <tbody><?php foreach ($recent as $row): ?><tr><td><?= e($row['tx_date']) ?></td><td><strong><?= e($row['inbound_no']) ?></strong></td><td><?= e($row['reference_no']) ?></td><td><?= e($row['party']) ?></td><td><?= (int) $row['line_count'] ?></td><td><?= (int) $row['qty'] ?></td><td><?= e($row['created_at']) ?></td></tr><?php endforeach; ?></tbody>
            <?php endif; ?>
        </table>
    </div>
</section>
<?php endif; ?>

<script>
const inboundItemDialog=document.getElementById('inboundItemDialog');
function openInboundItems(){if(inboundItemDialog)inboundItemDialog.showModal()}
function closeInboundItems(){if(inboundItemDialog)inboundItemDialog.close()}
inboundItemDialog?.addEventListener('click',event=>{if(event.target===inboundItemDialog)inboundItemDialog.close()});
const posItems = <?= json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const savedCart = <?=json_encode($savedCart,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
savedCart.forEach(saved=>{const item=posItems.find(row=>Number(row.id)===Number(saved.id));if(item&&saved.procurement_item_id)Object.assign(item,saved)});
let inboundLineSequence=0;
const nextInboundLineKey=()=>`line_${Date.now()}_${++inboundLineSequence}`;
const isInboundTransaction=<?= $defaultTransactionType==='STOCK IN'?'true':'false' ?>;
const cart = new Map(savedCart.map((saved,index)=>{const master=posItems.find(i=>Number(i.id)===Number(saved.id))||{},key=isInboundTransaction?String(saved.line_key||`saved_${index}_${saved.id}`):Number(saved.id);return [key,{...master,...saved,id:Number(saved.id),line_key:isInboundTransaction?String(key):''}]}));
const inboundSelection = new Set();
const inboundFiles = new Map();
const uploadBase = '../uploads/items/';
const stockSummaryPageSize = 15;
let stockSummaryPage = 1;

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

function itemImage(item, className = 'pos-item-img') {
    return item.item_image
        ? `<img class="${className}" src="${uploadBase}${esc(item.item_image)}" alt="${esc(item.item_name||item.item_description)}" data-image-hover-preview>`
        : `<div class="${className} pos-img-empty">IMG</div>`;
}

function availableLotSummary(item) {
    const lots = (item.lots || []).filter(lot => Number(lot.quantity_available) > 0);
    if (!lots.length) return 'No available lots';
    return lots.map(lot => `Lot ${esc(lot.lot_number)}: ${Number(lot.quantity_available)}${lot.expiry_date ? ` (Exp ${esc(lot.expiry_date)})` : ''}`).join(' | ');
}

function renderItems() {
    const q = document.getElementById('posSearch').value.toLowerCase().trim();
    const list = document.getElementById('posItemList');
    const filtered = posItems.filter(item => [
        item.item_code,
        item.barcode,
        item.item_name,
        item.item_description,
        item.lot_number,
        item.category,
        item.bin_location
    ].join(' ').toLowerCase().includes(q));

    list.innerHTML = filtered.map(item => `
        <article class="pos-item-card${inboundSelection.has(Number(item.id))?' is-selected':''}" role="button" tabindex="0" onclick="toggleInboundSelection(${Number(item.id)})" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleInboundSelection(${Number(item.id)})}" aria-pressed="${inboundSelection.has(Number(item.id))?'true':'false'}">
            ${itemImage(item)}
            <span class="pos-item-main">
                <strong>${esc(item.item_name||item.item_description)}</strong>
            </span>
        </article>
    `).join('') || '<div class="pos-empty">No matching items found.</div>';
}

function toggleInboundSelection(id) {
    id = Number(id);
    if (inboundSelection.has(id)) inboundSelection.delete(id);
    else inboundSelection.add(id);
    const addButton = document.getElementById('inboundAddSelected');
    if (addButton) {
        addButton.disabled = inboundSelection.size === 0;
        addButton.textContent = inboundSelection.size ? `Add Items (${inboundSelection.size})` : 'Add Items';
    }
    renderItems();
}

function addSelectedInboundItems() {
    inboundSelection.forEach(id => {
        const item = posItems.find(row => Number(row.id) === Number(id));
        if (item) { const key=nextInboundLineKey();cart.set(key, {...item,id:Number(item.id),line_key:key,qty:1,lot_number:'',allocations:[]}); }
    });
    inboundSelection.clear();
    const addButton = document.getElementById('inboundAddSelected');
    if (addButton) { addButton.disabled = true; addButton.textContent = 'Add Items'; }
    renderCart();
    renderItems();
    closeInboundItems();
}

function addToCart(id) {
    const item = posItems.find(row => Number(row.id) === Number(id));
    if (!item) return;
    if (cart.has(id)) return;
    cart.set(id, {...item, qty: 1, lot_number: '', allocations: []});
    renderCart();
    renderItems();
}

function updateQty(id, qty) {
    const item = cart.get(id);
    if (!item) return;
    const entered=Math.max(1,Number(qty||1));
    item.qty=item.expected_quantity?Math.min(entered,Number(item.expected_quantity)):entered;
    cart.set(id, item);
    document.getElementById('cartQty').textContent = Array.from(cart.values()).reduce((sum, row) => sum + Number(row.qty), 0);
    syncCartJson();
}

function previewInboundLotImage(input, id) {
    const preview = document.getElementById('inboundImagePreview_' + id);
    const file = input.files && input.files[0];
    if (!file) {
        inboundFiles.delete(id);
        if (preview) {
            preview.removeAttribute('src');
            preview.classList.remove('has-image');
        }
        return;
    }
    inboundFiles.set(id, file);
    if (preview) {
        preview.src = URL.createObjectURL(file);
        preview.classList.add('has-image');
    }
}

function toggleInboundImageNA(id, checked) {
    const item = cart.get(id);
    if (!item) return;
    item.image_na = Boolean(checked);
    const input = document.querySelector(`input[type="file"][name="lot_image[${id}]"]`);
    const preview = document.getElementById('inboundImagePreview_' + id);
    if (input) {
        input.disabled = Boolean(checked);
        input.required = !checked;
        if (checked) input.value = '';
    }
    if (checked) {
        inboundFiles.delete(id);
        if (preview) {
            preview.removeAttribute('src');
            preview.classList.remove('has-image');
        }
    }
    cart.set(id, item);
    syncCartJson();
}

function updateLot(id, field, value) {
    const item = cart.get(id);
    if (!item) return;
    if (field === 'unit_price' && Number(item.procurement_item_id) > 0) return;
    item[field] = value;
    if (field === 'unit_price') {
        const price = Math.max(0, Number(value || 0));
        item.unit_price = price;
        item.markup_cost = price;
        item.total_price = price * 2;
        item.selling_price = price * 2;
        const markup = document.getElementById('inboundMarkup' + Number(id));
        const total = document.getElementById('inboundTotalPrice' + Number(id));
        const selling = document.getElementById('inboundSellingPrice' + Number(id));
        if (markup) markup.value = price.toFixed(2);
        if (total) total.value = (price * 2).toFixed(2);
        if (selling) selling.value = (price * 2).toFixed(2);
    }
    cart.set(id, item);
    syncCartJson();
}

function updateAllocation(itemId, lotId, qty) {
    const item = cart.get(itemId);
    if (!item) return;
    const amount = Math.max(0, Number(qty || 0));
    const allocations = (item.allocations || []).filter(row => Number(row.lot_id) !== Number(lotId));
    if (amount > 0) allocations.push({lot_id: Number(lotId), qty: amount});
    item.allocations = allocations;
    item.qty = allocations.reduce((sum, row) => sum + Number(row.qty), 0);
    cart.set(itemId, item);
    renderCart();
}

function syncCartJson() {
    document.getElementById('cartJson').value = JSON.stringify(Array.from(cart.values()).map(row => ({
        id: Number(row.id),
        line_key: row.line_key || '',
        qty: Number(row.qty),
        procurement_item_id: Number(row.procurement_item_id || 0),
        expected_quantity: Number(row.expected_quantity || 0),
        lot_number: row.lot_number || '',
        expiry_date: row.expiry_date || '',
        unit_price: Number(row.unit_price || 0),
        brand: row.brand || '',
        markup_cost: Number(row.markup_cost || 0),
        total_price: Number(row.total_price || 0),
        selling_price: Number(row.selling_price || 0),
        image_na: Boolean(row.image_na),
        storage_location: row.storage_location || '',
        allocations: row.allocations || []
    })));
}

function removeFromCart(id) {
    cart.delete(id);
    inboundFiles.delete(id);
    renderCart();
    renderItems();
}

function clearInboundCart() {
    if (cart.size === 0) return;
    showConfirm('Clear all selected delivered items?', function(){
        cart.clear();
        inboundFiles.clear();
        renderCart();
        renderItems();
    });
}

function openStockCart() {
    document.getElementById('posForm')?.classList.add('open');
    document.body.classList.add('mobile-cart-open');
}

function closeStockCart() {
    document.getElementById('posForm')?.classList.remove('open');
    document.body.classList.remove('mobile-cart-open');
}

function renderCart() {
    const type = document.getElementById('transactionType').value;
    const list = document.getElementById('cartList');
    const rows = Array.from(cart.entries());
    let totalQty = 0;

    list.innerHTML = rows.map(([lineKey,item]) => {
        totalQty += Number(item.qty);
        const overStock = type === 'STOCK OUT' && Number(item.qty) > Number(item.current_stock);
        const lotAllocationFields = (item.lots || []).map(lot => {
            const selected = (item.allocations || []).find(row => Number(row.lot_id) === Number(lot.id));
            return `<div class="row align-items-center g-2 mb-1">
                <div class="col"><strong>Lot ${esc(lot.lot_number)}</strong><small class="d-block">Expiry ${esc(lot.expiry_date || 'N/A')} · Balance ${Number(lot.quantity_available)} · ${esc(lot.storage_location || 'No location')}</small></div>
                <div class="col-auto"><input class="form-control form-control-sm" style="width:100px" type="number" min="0" max="${Number(lot.quantity_available)}" value="${selected ? Number(selected.qty) : 0}" onchange="updateAllocation(${Number(item.id)}, ${Number(lot.id)}, this.value)"></div>
            </div>`;
        }).join('');
        if (type === 'STOCK IN') {
            return `<article class="inbound-item-card">
                <div class="inbound-item-head">
                    <div class="inbound-item-identity">${itemImage(item, 'pos-cart-img')}<div><span>Delivered Item</span><strong>${esc(item.item_name||item.item_description)}</strong></div></div>
                    <button type="button" class="btn btn-light btn-sm" onclick='removeFromCart(${JSON.stringify(lineKey)})'>Remove</button>
                </div>
                <div class="inbound-item-fields">
                    <div class="inbound-field"><label class="form-label"><span>1</span>Lot Number</label><input class="form-control" value="${esc(item.lot_number || '')}" oninput='updateLot(${JSON.stringify(lineKey)}, "lot_number", this.value)' placeholder="Enter lot number" required></div>
                    <div class="inbound-field"><label class="form-label"><span>2</span>Expiration Date</label><input class="form-control" type="date" value="${esc(item.expiry_date || '')}" onchange='updateLot(${JSON.stringify(lineKey)}, "expiry_date", this.value)' required></div>
                    <div class="inbound-field"><label class="form-label"><span>3</span>Delivered Quantity</label><input class="form-control" type="number" min="1" ${item.expected_quantity?`max="${Number(item.expected_quantity)}"`:''} value="${Number(item.qty)}" onchange='updateQty(${JSON.stringify(lineKey)},this.value)' required>${item.expected_quantity?`<small>Remaining awarded quantity: ${Number(item.expected_quantity)}</small>`:''}</div>
                    <div class="inbound-field"><label class="form-label"><span>4</span>Brand</label><input class="form-control text-uppercase" value="${esc(item.brand || '')}" oninput='updateLot(${JSON.stringify(lineKey)}, "brand", this.value)' placeholder="Enter brand"></div>
                    <div class="inbound-field"><label class="form-label"><span>5</span>Unit Price</label><input ${Number(item.procurement_item_id) > 0 ? 'readonly aria-readonly="true"' : ''} class="form-control" type="number" min="0" step="0.01" value="${Number(item.unit_price || 0)}" oninput='updateLot(${JSON.stringify(lineKey)}, "unit_price", this.value)' required></div>
                    <div class="inbound-field inbound-picture-field"><label class="form-label"><span>6</span>Item Picture</label><div class="inbound-picture-choice"><input class="form-control" type="file" name="lot_image[${esc(lineKey)}]" accept=".jpg,.jpeg,.png,.webp" onchange='previewInboundLotImage(this, ${JSON.stringify(lineKey)})' ${item.image_na ? 'disabled' : 'required'}><label class="form-check"><input class="form-check-input" type="checkbox" ${item.image_na ? 'checked' : ''} onchange='toggleInboundImageNA(${JSON.stringify(lineKey)}, this.checked)'><span class="form-check-label">N/A</span></label></div><small>JPG, PNG, or WEBP up to 2 MB</small></div>
                </div>
            </article>`;
        }
        return `
            <div class="pos-cart-row ${overStock ? 'over-stock' : ''}">
                ${itemImage(item, 'pos-cart-img')}
                <div class="pos-cart-info">
                    <strong>${esc(item.item_description)}</strong>
                    <div class="mt-2">${lotAllocationFields || '<em>No non-expired lot with a positive balance is available.</em>'}</div>
                    ${overStock ? '<em>Quantity exceeds current stock.</em>' : ''}
                </div>
                <span class="pos-count">${Number(item.qty)} selected</span>
                <button type="button" class="btn btn-light btn-sm" onclick="removeFromCart(${Number(item.id)})">Remove</button>
            </div>
    `;
    }).join('') || '<div class="pos-empty">Cart is empty. Add items from the search list.</div>';

    if (type === 'STOCK IN') {
        inboundFiles.forEach((file, id) => {
            const input = list.querySelector(`input[type="file"][name="lot_image[${id}]"]`);
            const preview = document.getElementById('inboundImagePreview_' + id);
            if (input && typeof DataTransfer !== 'undefined') {
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
            }
            if (preview) {
                preview.src = URL.createObjectURL(file);
                preview.classList.add('has-image');
            }
        });
    }

    document.getElementById('cartLines').textContent = rows.length;
    document.getElementById('cartQty').textContent = totalQty;
    const mobileBar = document.getElementById('stockMobileCartBar');
    const mobileCount = document.getElementById('stockMobileCartCount');
    if (mobileCount) mobileCount.textContent = rows.length;
    if (mobileBar) mobileBar.classList.toggle('has-items', rows.length > 0);
    if (rows.length === 0) closeStockCart();
    syncCartJson();
}

document.addEventListener('keydown', event => { if (event.key === 'Escape') closeStockCart(); });
document.addEventListener('DOMContentLoaded', function () {
    const mobileBar = document.getElementById('stockMobileCartBar');
    if (mobileBar && mobileBar.parentElement !== document.body) document.body.appendChild(mobileBar);
});

function toggleFields() {
    const type = document.getElementById('transactionType').value;
    document.getElementById('stockInFields').style.display = type === 'STOCK IN' ? '' : 'none';
    document.getElementById('stockOutFields').style.display = type === 'STOCK OUT' ? '' : 'none';
    document.querySelectorAll('#stockInFields input,#stockInFields select,#stockInFields textarea').forEach(el=>el.disabled=type!=='STOCK IN');
    document.querySelectorAll('#stockOutFields input,#stockOutFields select,#stockOutFields textarea').forEach(el=>el.disabled=type!=='STOCK OUT');
    renderCart();
}

document.getElementById('posSearch').addEventListener('input', renderItems);
document.getElementById('transactionType').addEventListener('change', toggleFields);
function syncAdvanceDeliveryFields(){
    const status=document.getElementById('receivingStatus');
    if(!status)return;
    document.querySelectorAll('[data-advance-pending]').forEach(input=>{
        if(status.value==='ADVANCE DELIVERY')input.value='PENDING';
        else if(input.value.trim().toUpperCase()==='PENDING')input.value='';
        input.readOnly=status.value==='ADVANCE DELIVERY';
        input.required=status.value==='INSPECTED';
    });
}
document.getElementById('receivingStatus')?.addEventListener('change',function(){syncAdvanceDeliveryFields();renderCart();});
function stockSummaryLines(){
    const type = document.getElementById('transactionType').value;
    const rows = [];
    cart.forEach(item => {
        if (type === 'STOCK OUT') {
            (item.allocations || []).forEach(allocation => {
                const lot = (item.lots || []).find(row => Number(row.id) === Number(allocation.lot_id));
                if (lot && Number(allocation.qty) > 0) {
                    rows.push({code:item.item_code,description:item.item_description,lot:lot.lot_number,qty:Number(allocation.qty)});
                }
            });
        } else {
            rows.push({code:item.item_code,description:item.item_name||item.item_description,lot:item.lot_number || '',qty:Number(item.qty || 0)});
        }
    });
    return rows;
}
function renderStockSummaryPage(){
    const rows = stockSummaryLines();
    const totalPages = Math.max(1, Math.ceil(rows.length / stockSummaryPageSize));
    stockSummaryPage = Math.min(Math.max(1, stockSummaryPage), totalPages);
    const pageRows = rows.slice((stockSummaryPage - 1) * stockSummaryPageSize, stockSummaryPage * stockSummaryPageSize);
    const totalQty = rows.reduce((sum, row) => sum + Number(row.qty || 0), 0);
    document.getElementById('stockSummaryRows').innerHTML = pageRows.map(row => `<tr><td><strong>${esc(row.code)}</strong></td><td>${esc(row.description)}</td><td>${esc(row.lot || 'N/A')}</td><td>${Number(row.qty)}</td></tr>`).join('');
    document.getElementById('stockSummaryLines').textContent = rows.length;
    document.getElementById('stockSummaryQty').textContent = totalQty;
    document.getElementById('stockSummaryPager').innerHTML = rows.length > stockSummaryPageSize ? `<span>Page ${stockSummaryPage} of ${totalPages}</span><div><button class="btn btn-light btn-sm" type="button" ${stockSummaryPage===1?'disabled':''} onclick="stockSummaryPage--;renderStockSummaryPage()">Previous</button><button class="btn btn-light btn-sm" type="button" ${stockSummaryPage===totalPages?'disabled':''} onclick="stockSummaryPage++;renderStockSummaryPage()">Next</button></div>` : '';
}
function showStockSummary(){
    const type = document.getElementById('transactionType').value;
    stockSummaryPage = 1;
    document.getElementById('stockSummaryTitle').textContent = 'Confirm ' + (type === 'STOCK IN' ? 'Inbound / Delivery Items' : 'Stock Out Items');
    renderStockSummaryPage();
    closeStockCart();
    document.getElementById('stockSummaryModal').classList.add('open');
}
document.getElementById('posForm').addEventListener('submit', function (event) {
    if (!this.checkValidity()) {
        event.preventDefault();
        this.reportValidity();
        showToastMessage('warning', 'Complete all required delivery details. Your entries were not cleared.');
        return;
    }
    if (cart.size === 0) {
        event.preventDefault();
        showToastMessage('warning', 'Add at least one item to the transaction cart.');
        return;
    }
    const type = document.getElementById('transactionType').value;
    const invalid = Array.from(cart.values()).find(item => type === 'STOCK OUT' && Number(item.qty) > Number(item.current_stock));
    if (invalid) {
        event.preventDefault();
        showToastMessage('danger', 'Cannot issue more than current stock for ' + invalid.item_code + '.');
        return;
    }
    const missingAllocation = Array.from(cart.values()).find(item => type === 'STOCK OUT' && (!item.allocations || item.allocations.length === 0));
    if (missingAllocation) {
        event.preventDefault();
        showToastMessage('danger', 'Select at least one available lot for ' + missingAllocation.item_code + '.');
        return;
    }
    const invalidAllocation = Array.from(cart.values()).find(item => type === 'STOCK OUT' && (item.allocations || []).some(allocation => {
        const lot = (item.lots || []).find(row => Number(row.id) === Number(allocation.lot_id));
        return !lot || Number(allocation.qty) <= 0 || Number(allocation.qty) > Number(lot.quantity_available);
    }));
    if (invalidAllocation) {
        event.preventDefault();
        showToastMessage('danger', 'A selected lot quantity exceeds its available balance for ' + invalidAllocation.item_code + '.');
        return;
    }
    const missingLot = Array.from(cart.values()).find(item => type === 'STOCK IN' && !String(item.lot_number || '').trim());
    if (missingLot) {
        event.preventDefault();
        showToastMessage('danger', 'Enter a lot number for ' + missingLot.item_code + '.');
        return;
    }
    const storageRoom = document.getElementById('storageRoom');
    if (type === 'STOCK IN' && storageRoom && !storageRoom.value) {
        event.preventDefault();
        showToastMessage('danger', 'Choose a storage room.');
        return;
    }
    event.preventDefault();
    showStockSummary();
});
document.getElementById('confirmStockSubmit').addEventListener('click', function(){
    const confirmButton = this;
    const type = document.getElementById('transactionType').value;
    showConfirm('Save this ' + type + ' transaction with ' + stockSummaryLines().length + ' line(s)?', function(){
        confirmButton.disabled = true;
        confirmButton.textContent = 'Saving...';
        var form = document.getElementById('posForm');
        form.dataset.confirm = 'off';
        document.getElementById('stockSummaryModal').classList.remove('open');
        closeStockCart();
        startLoading('Saving stock transaction...');
        form.submit();
    });
});

renderItems();
toggleFields();
syncAdvanceDeliveryFields();
renderCart();
<?php if(isset($_GET['procurement_added'])):?>
window.addEventListener('load',()=>showToastMessage('success','Procurement items successfully added to inbound.'));
<?php endif;?>
<?php if ($receipt && $receipt['type'] === 'STOCK OUT' && !$viewingReceipt): ?>
window.addEventListener('load', function(){
    showConfirm('Do you want to print the receipt now?', function(){
        window.print();
    });
});
<?php endif; ?>
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
