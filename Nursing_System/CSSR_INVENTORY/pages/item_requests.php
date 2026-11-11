<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles([ROLE_ADMIN, ROLE_HEAD, ROLE_STAFF]);
require_once __DIR__.'/../includes/request-catalog.php';
require_once __DIR__.'/../includes/submission.php';
require_once __DIR__.'/../includes/stock-approval.php';
cssrServeRequestCatalog();
$requestQueueOnly = $requestQueueOnly ?? false;
$embedRequest = !$requestQueueOnly && ($_GET['embed'] ?? '') === '1';
$pageTitle = $requestQueueOnly ? 'Request Queue' : 'Department Supply Request';
$activePage = $requestQueueOnly ? 'request_queue' : 'item_requests';
$pageDescription = $requestQueueOnly
    ? 'Review, approve, release, and monitor submitted department supply requests.'
    : 'Create department supply requests for CSSR review and release.';
$requestPage = $requestQueueOnly ? 'request_queue.php' : 'item_requests.php';
$fromRequisitions = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['return_to'] ?? '') === 'requisitions';
if ($fromRequisitions) $requestPage = 'requisitions.php';
$allowedRequestReturnPages = ['request_history.php', 'request_queue.php'];
$requestReturnParam = (string) ($_GET['return'] ?? '');
$requestReturnPage = in_array($requestReturnParam, $allowedRequestReturnPages, true) ? $requestReturnParam : $requestPage;
if ($requestReturnPage === 'request_history.php') {
    $activePage = 'request_history';
    $pageTitle = 'Request History';
} elseif ($requestReturnPage === 'request_queue.php') {
    $activePage = 'request_queue';
    $pageTitle = 'Release Queue';
}

function next_request_no(?string $requestDate = null): string
{
    $timestamp = $requestDate ? strtotime($requestDate) : time();
    if ($timestamp === false) {
        $timestamp = time();
    }
    $prefix = 'REQ-' . date('Y-m', $timestamp);
    $latest = db_value(
        'SELECT request_no FROM item_requests WHERE request_no LIKE ? ORDER BY request_no DESC LIMIT 1',
        [$prefix . '-%']
    );
    $next = 1;
    if ($latest && preg_match('/-(\d{4})$/', (string) $latest, $matches)) {
        $next = (int) $matches[1] + 1;
    }
    return $prefix . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['next_request_no'])) {
    header('Content-Type: application/json');
    try {
        echo json_encode(['request_no' => next_request_no(cssrTransactionDate(current_user() ?? [], $_GET['next_request_no']))]);
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo json_encode(['error' => safe_error_message($error)]);
    }
    exit;
}

$canViewAllRequests = shared_has_permission('cssr_inventory', 'edit') || shared_has_permission('ris_requests', 'approve');
$canViewRequestLists = can_view_cssr_request_lists();
$currentDepartmentId = current_user()['department_id'] ?? null;
$currentDepartmentName = current_user()['department_name'] ?? null;
$canCreateRequest = shared_has_permission('ris_requests', 'create');
$canAddToSubmittedRequest = shared_has_permission('ris_requests', 'create');
$previewRequestNo = next_request_no(cssrTransactionToday());
$printPromptRequestId = (int) ($_SESSION['print_prompt_request_id'] ?? 0);
unset($_SESSION['print_prompt_request_id']);
$requestPopupError = (string) ($_SESSION['request_popup_error'] ?? '');
unset($_SESSION['request_popup_error']);
$requestFormState = is_array($_SESSION['request_form_state'] ?? null) ? $_SESSION['request_form_state'] : [];
unset($_SESSION['request_form_state']);

if ($requestQueueOnly && !$canViewRequestLists) {
    set_flash('danger', 'Only Admin and CSSR staff can view pending requests.');
    redirect('item_requests.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    try {
        if (!$canCreateRequest) throw new RuntimeException('You do not have permission to create requisitions.');
        $cart = json_decode($_POST['cart_json'] ?? '[]', true);
        if (!is_array($cart) || count($cart) === 0) {
            throw new RuntimeException('Add at least one requested item.');
        }

        $departmentId = $canViewAllRequests ? (int) ($_POST['department_id'] ?? 0) : (int) $currentDepartmentId;
        if (!$canViewAllRequests && $departmentId <= 0) {
            throw new RuntimeException('Your user account is not assigned to a department. Ask the admin to update your user profile.');
        }
        $stmt = db()->prepare('SELECT department_name FROM departments WHERE id=? AND status="Active"');
        $stmt->execute([$departmentId]);
        $departmentName = $stmt->fetchColumn();
        if (!$departmentName) {
            throw new RuntimeException('Please select a valid department.');
        }

        $requestedBy = current_user()['full_name'];
        $requestDate = cssrTransactionDate(current_user() ?? [], $_POST['request_date'] ?? '');

        $submissionPayload=[(int)current_user()['id'],array_diff_key($_POST,['csrf_token'=>true])];
        if(cssrSubmissionReplay('department-create',$submissionPayload)){set_flash('success','This request has already been saved.');redirect('item_requests.php');}
        db()->beginTransaction();
        $requestNo = next_request_no($requestDate);
        $stmt = db()->prepare('INSERT INTO item_requests (request_no, department_id, department_name, requested_by, request_date, purpose, remarks, created_by) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$requestNo, $departmentId, $departmentName, $requestedBy, $requestDate, trim($_POST['purpose'] ?? ''), trim($_POST['remarks'] ?? ''), current_user()['id']]);
        $requestId = (int) db()->lastInsertId();
        $receiptItems = [];
        $receiptTotalQty = 0;

        foreach ($cart as $line) {
            $itemId = (int) ($line['id'] ?? 0);
            $qty = filter_var($line['qty'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
            if ($itemId <= 0 || $qty <= 0) {
                throw new RuntimeException('Every requested item must have a valid quantity.');
            }
            $stmt = db()->prepare('SELECT i.item_code, i.item_name, i.brand, i.lot_number, COALESCE((SELECT SUM(l.quantity_available) FROM item_lots l WHERE l.item_id=i.id AND l.storage_location="CSSR"),0) AS current_stock FROM items i WHERE i.id=? AND i.status="Active"');
            $stmt->execute([$itemId]);
            $itemStock = $stmt->fetch();
            if (!$itemStock) {
                throw new RuntimeException('One requested item is no longer active.');
            }
            if ((int) $itemStock['current_stock'] > 0 && $qty > (int) $itemStock['current_stock']) {
                throw new RuntimeException($itemStock['item_name'] . ' does not have enough stock.');
            }
            db()->prepare('INSERT INTO item_request_details (request_id, item_id, quantity_requested, remarks) VALUES (?,?,?,?)')->execute([$requestId, $itemId, $qty, trim($line['remarks'] ?? '')]);
            $receiptItems[] = [
                'code' => $itemStock['item_code'],
                'description' => $itemStock['item_name'],
                'brand' => $itemStock['brand'],
                'lot' => $itemStock['lot_number'],
                'qty' => $qty,
            ];
            $receiptTotalQty += $qty;
        }

        log_activity('CREATE', 'Item Requests', 'Created request ' . $requestNo . ' for ' . $departmentName);
        db()->commit();
        cssrRememberSubmission('department-create',$submissionPayload,['ok'=>true]);
        $_SESSION['clear_request_cart_after_submit'] = true;
        $_SESSION['print_prompt_request_id'] = $requestId;
        set_flash('success', 'Item request created: ' . $requestNo);
        redirect($requestPage);
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        set_flash('danger', safe_error_message($e));
    }
    redirect($requestPage);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['approve', 'cancel', 'reject', 'release', 'delete'], true)) {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $requestAction = (string) ($_POST['action'] ?? '');
    $requestActionAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    try {
        if (in_array($requestAction, ['approve', 'cancel', 'reject', 'release'], true) && !can_manage()) {
            throw new RuntimeException('Only Admin or CSSR staff can update request status.');
        }
        $requestSql = 'SELECT * FROM item_requests WHERE id=?';
        $requestParams = [$requestId];
        if (!$canViewAllRequests) {
            $requestSql .= ' AND department_id=?';
            $requestParams[] = (int) $currentDepartmentId;
        }
        $requestSql .= ' FOR UPDATE';
        $stmt = db()->prepare($requestSql);
        db()->beginTransaction();
        $stmt->execute($requestParams);
        $request = $stmt->fetch();
        if (!$request) {
            throw new RuntimeException('Request not found.');
        }

        $action = $requestAction;
        if ($action === 'delete') {
            if (!can_manage() && $request['status'] !== 'Pending') {
                throw new RuntimeException('Staff can delete pending requests only.');
            }
            db()->prepare("UPDATE item_requests SET status='Deleted', deleted_by=?, deleted_at=NOW(), remarks=? WHERE id=?")->execute([current_user()['full_name'], trim($_POST['status_remarks'] ?? $request['remarks']), $requestId]);
            log_activity('DELETE', 'Item Requests', 'Deleted request ' . $request['request_no'] . ' by ' . current_user()['full_name']);
            set_flash('success', 'Request deleted by ' . current_user()['full_name'] . '.');
        } elseif ($action === 'approve') {
            if ($request['status'] !== 'Pending') {
                throw new RuntimeException('Only pending requests can be approved.');
            }
            $approvedQuantities = $_POST['approved_qty'] ?? [];
            $approvedLotIds = $_POST['approved_lot_id'] ?? [];
            $approvedTotal = 0;
            $hasReducedQuantity = false;
            $details = db()->prepare('SELECT d.id, d.item_id, d.quantity_requested, i.item_code, i.item_name, COALESCE((SELECT SUM(l.quantity_available) FROM item_lots l WHERE l.item_id=i.id AND l.storage_location="CSSR"),0) AS current_stock FROM item_request_details d JOIN items i ON i.id=d.item_id WHERE d.request_id=?');
            $details->execute([$requestId]);
            foreach ($details->fetchAll() as $detail) {
                $detailId = (int) $detail['id'];
                $rawQty = $approvedQuantities[$detailId] ?? $detail['quantity_requested'];
                if (filter_var($rawQty, FILTER_VALIDATE_INT) === false) throw new RuntimeException('Approved quantities must be whole numbers.');
                $qty = (int) $rawQty;
                if ($fromRequisitions && ($_POST['approval_mode'] ?? '') === 'approve') $qty = (int) $detail['quantity_requested'];
                $approvedTotal += $qty;
                $hasReducedQuantity = $hasReducedQuantity || $qty < (int) $detail['quantity_requested'];
                $approvedLotId = (int) ($approvedLotIds[$detailId] ?? 0);
                if ($qty < 0) {
                    throw new RuntimeException('Approved quantity cannot be negative.');
                }
                if ($qty > (int) $detail['quantity_requested']) {
                    throw new RuntimeException('Approved quantity cannot exceed requested quantity.');
                }
                if ($qty > (int) $detail['current_stock']) {
                    throw new RuntimeException($detail['item_name'] . ' does not have enough stock.');
                }
                if ($qty > 0) {
                    if ($approvedLotId <= 0) {
                        throw new RuntimeException('Select an available lot for ' . $detail['item_name'] . '.');
                    }
                    cssrLockApprovalStock(db(), $approvedLotId, (int) $detail['item_id'], $qty, $detail['item_name']);
                    $fefo=(int)db_value('SELECT l.id FROM item_lots l WHERE l.item_id=? AND l.storage_location="CSSR" AND l.status="AVAILABLE" AND l.quantity_available>(SELECT COALESCE(SUM(r.quantity),0) FROM stock_reservations r WHERE r.item_lot_id=l.id AND r.status="ACTIVE" AND (r.expires_at IS NULL OR r.expires_at>NOW())) AND l.lot_number<>"UNIDENTIFIED / PENDING" AND (l.expiry_date IS NULL OR l.expiry_date>=CURDATE()) ORDER BY l.expiry_date IS NULL,l.expiry_date,l.created_at,l.id LIMIT 1',[(int)$detail['item_id']]);
                    if($fefo>0&&$fefo!==$approvedLotId)throw new RuntimeException('FEFO requires the earliest-expiring available lot for '.$detail['item_name'].'.');
                } else {
                    $approvedLotId = 0;
                }
                db()->prepare('UPDATE item_request_details SET approved_item_lot_id=?, quantity_approved=? WHERE id=?')->execute([$approvedLotId ?: null, $qty, $detailId]);
                if($qty>0)db_statement('INSERT INTO stock_reservations(request_id,request_detail_id,item_lot_id,quantity,reserved_by,reserved_at,expires_at) VALUES(?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 7 DAY)) ON DUPLICATE KEY UPDATE item_lot_id=VALUES(item_lot_id),quantity=VALUES(quantity),status="ACTIVE",reserved_by=VALUES(reserved_by),reserved_at=NOW(),expires_at=VALUES(expires_at)',[$requestId,$detailId,$approvedLotId,$qty,current_user()['full_name']]);
            }
            if ($approvedTotal <= 0) throw new RuntimeException('Approve at least one item, or reject the request with a reason.');
            if ($fromRequisitions && ($_POST['approval_mode'] ?? '') === 'partial' && !$hasReducedQuantity) throw new RuntimeException('Reduce at least one quantity for partial approval.');
            db()->prepare("UPDATE item_requests SET status='Approved', approved_by=?, approved_at=NOW(), remarks=? WHERE id=?")->execute([current_user()['full_name'], trim($_POST['status_remarks'] ?? $request['remarks']), $requestId]);
            log_activity('APPROVE', 'Item Requests', 'Approved request ' . $request['request_no']);
            set_flash('success', 'Request approved.');
            $_SESSION['print_prompt_request_id'] = $requestId;
        } elseif ($action === 'reject') {
            if (!in_array($request['status'], ['Pending','Approved'], true)) throw new RuntimeException('Only pending or approved requests can be rejected.');
            $reason = trim((string) ($_POST['status_remarks'] ?? ''));
            if ($reason === '') throw new RuntimeException('Enter a reason for rejecting this request.');
            db()->prepare("UPDATE item_requests SET status='Rejected', remarks=? WHERE id=?")->execute([$reason,$requestId]);
            db_statement('UPDATE stock_reservations SET status="CANCELLED",released_at=NOW() WHERE request_id=? AND status="ACTIVE"',[$requestId]);
            log_activity('REJECT','Item Requests','Rejected request '.$request['request_no'].': '.$reason);
            set_flash('success','Request rejected. Reserved stock is available again.');
        } elseif ($action === 'cancel') {
            if (in_array($request['status'], ['Released', 'Cancelled', 'Rejected', 'Deleted'], true)) {
                throw new RuntimeException('This request can no longer be cancelled.');
            }
            db()->prepare("UPDATE item_requests SET status='Cancelled', remarks=? WHERE id=?")->execute([trim($_POST['status_remarks'] ?? $request['remarks']), $requestId]);
            db_statement('UPDATE stock_reservations SET status="CANCELLED",released_at=NOW() WHERE request_id=? AND status="ACTIVE"',[$requestId]);
            log_activity('CANCEL', 'Item Requests', 'Cancelled request ' . $request['request_no']);
            set_flash('success', 'Request cancelled.');
        } else {
            if ($fromRequisitions) {
                require_once __DIR__.'/../includes/requisitions-data.php';
                if (!(int)db_value('SELECT COUNT(*) FROM item_requests r WHERE r.id=? AND ('.cssrRequisitionReadySql().')',[$requestId])) {
                    throw new RuntimeException('This request is not ready for release. Review its approval and stock reservations.');
                }
            }
            if (!in_array($request['status'], ['Pending', 'Approved'], true)) {
                throw new RuntimeException('Only pending requests can be released.');
            }
            if ($request['status'] === 'Pending') {
                $releaseQuantities = $_POST['approved_qty'] ?? [];
                $releaseLotIds = $_POST['approved_lot_id'] ?? [];
                $markedOutOfStock = $_POST['mark_out_of_stock'] ?? [];
                $pendingDetails = db()->prepare('SELECT d.id, d.item_id, d.quantity_requested, i.item_name, COALESCE((SELECT SUM(l.quantity_available) FROM item_lots l WHERE l.item_id=i.id AND l.storage_location="CSSR"),0) AS current_stock FROM item_request_details d JOIN items i ON i.id=d.item_id WHERE d.request_id=?');
                $pendingDetails->execute([$requestId]);
                foreach ($pendingDetails->fetchAll() as $detail) {
                    $detailId = (int) $detail['id'];
                    $qty = !empty($markedOutOfStock[$detailId])
                        ? 0
                        : (isset($releaseQuantities[$detailId])
                            ? (int) $releaseQuantities[$detailId]
                            : min((int) $detail['quantity_requested'], (int) $detail['current_stock']));
                    $releaseLotId = (int) ($releaseLotIds[$detailId] ?? 0);
                    if ($qty < 0 || $qty > (int) $detail['quantity_requested']) {
                        throw new RuntimeException('Enter a valid release quantity for ' . $detail['item_name'] . '.');
                    }
                    if ($qty > (int) $detail['current_stock']) {
                        throw new RuntimeException($detail['item_name'] . ' does not have enough stock.');
                    }
                    if ($qty > 0) {
                        if ($releaseLotId <= 0) {
                            throw new RuntimeException('Select an available lot for ' . $detail['item_name'] . '.');
                        }
                        $lotCheck = db()->prepare('SELECT quantity_available FROM item_lots WHERE id=? AND item_id=? AND storage_location="CSSR" AND status="AVAILABLE" AND lot_number<>"UNIDENTIFIED / PENDING" FOR UPDATE');
                        $lotCheck->execute([$releaseLotId, (int) $detail['item_id']]);
                        $releaseLot = $lotCheck->fetch();
                        if (!$releaseLot || $qty > (int) $releaseLot['quantity_available']) {
                            throw new RuntimeException($detail['item_name'] . ' does not have enough stock in the selected lot.');
                        }
                    } else {
                        $releaseLotId = 0;
                    }
                    db()->prepare('UPDATE item_request_details SET approved_item_lot_id=?, quantity_approved=? WHERE id=?')->execute([$releaseLotId ?: null, $qty, $detailId]);
                }
            }
            $details = db()->prepare('SELECT d.id AS detail_id, d.item_id, d.approved_item_lot_id, d.quantity_approved, i.item_code, i.item_name, i.current_stock FROM item_request_details d JOIN items i ON i.id=d.item_id WHERE d.request_id=? FOR UPDATE');
            $details->execute([$requestId]);
            foreach ($details->fetchAll() as $line) {
                $qty = (int) $line['quantity_approved'];
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > (int) $line['current_stock']) {
                    throw new RuntimeException($line['item_name'] . ' does not have enough stock.');
                }
                $lotStmt = db()->prepare('SELECT id, lot_number, expiry_date, quantity_available FROM item_lots WHERE id=? AND item_id=? AND storage_location="CSSR" AND status="AVAILABLE" AND lot_number<>"UNIDENTIFIED / PENDING" AND (expiry_date IS NULL OR expiry_date>=CURDATE()) FOR UPDATE');
                $lotStmt->execute([(int) $line['approved_item_lot_id'], (int) $line['item_id']]);
                $selectedLot = $lotStmt->fetch();
                if (!$selectedLot) {
                    throw new RuntimeException('No approved lot was selected for ' . $line['item_name'] . '.');
                }
                $reservationStmt=db()->prepare('SELECT quantity FROM stock_reservations WHERE item_lot_id=? AND request_detail_id<>? AND status="ACTIVE" AND (expires_at IS NULL OR expires_at>NOW()) FOR UPDATE');
                $reservationStmt->execute([(int)$selectedLot['id'],(int)$line['detail_id']]);
                $otherReserved=array_sum(array_map('intval',$reservationStmt->fetchAll(PDO::FETCH_COLUMN)));
                if ($qty > (int) $selectedLot['quantity_available'] - $otherReserved) {
                    throw new RuntimeException($line['item_name'] . ' does not have enough stock in the selected lot.');
                }
                $lotBalance = (int) $selectedLot['quantity_available'] - $qty;
                db()->prepare('UPDATE item_lots SET quantity_issued=quantity_issued+?, quantity_available=?, status=? WHERE id=?')->execute([$qty, $lotBalance, $lotBalance > 0 ? 'AVAILABLE' : 'DEPLETED', (int) $selectedLot['id']]);
                $balance = (int) db_value('SELECT COALESCE(SUM(quantity_available),0) FROM item_lots WHERE item_id=?', [(int) $line['item_id']]);
                db()->prepare('UPDATE items SET current_stock=? WHERE id=?')->execute([$balance, (int) $line['item_id']]);
                db()->prepare('UPDATE item_request_details SET quantity_released=? WHERE id=?')->execute([$qty, (int) $line['detail_id']]);
                db_statement('UPDATE stock_reservations SET status="RELEASED",released_at=NOW() WHERE request_detail_id=? AND status="ACTIVE"',[(int)$line['detail_id']]);
                db()->prepare('INSERT INTO stock_out (item_id, item_lot_id, reference_no, department, quantity_issued, lot_number, issued_date, issued_by, received_by, remarks) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([(int) $line['item_id'], (int) $selectedLot['id'], $request['request_no'], $request['department_name'], $qty, $selectedLot['lot_number'], date('Y-m-d'), current_user()['full_name'], $request['requested_by'], 'Released from item request']);
                db()->prepare('INSERT INTO bin_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, department, encoded_by, remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([(int) $line['item_id'], date('Y-m-d'), $request['request_no'], 'STOCK OUT', $selectedLot['lot_number'], $selectedLot['expiry_date'], 0, $qty, $balance, $request['department_name'], current_user()['full_name'], 'Released from department request']);
            }
            db()->prepare("UPDATE item_requests SET status='Released', approved_by=COALESCE(approved_by,?), approved_at=COALESCE(approved_at,NOW()), released_by=?, released_at=NOW(), remarks=? WHERE id=?")->execute([current_user()['full_name'], current_user()['full_name'], trim($_POST['status_remarks'] ?? $request['remarks']), $requestId]);
            log_activity('RELEASE', 'Item Requests', 'Released request ' . $request['request_no']);
            set_flash('success', 'Request released and stock was issued.');
            $_SESSION['print_prompt_request_id'] = $requestId;
        }
        db()->commit();
        if ($requestAction === 'delete') {
            $_SESSION['clear_request_cart_after_submit'] = true;
        }
        if ($requestActionAjax && $requestAction === 'release') {
            unset($_SESSION['print_prompt_request_id']);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'message' => 'Request released and stock was issued.']);
            exit;
        }
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if ($requestActionAjax && $requestAction === 'release') {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => safe_error_message($e)]);
            exit;
        }
        $_SESSION['request_popup_error'] = safe_error_message($e);
        $_SESSION['request_form_state'] = [
            'request_id' => $requestId,
            'approved_lot_id' => is_array($_POST['approved_lot_id'] ?? null) ? $_POST['approved_lot_id'] : [],
            'approved_qty' => is_array($_POST['approved_qty'] ?? null) ? $_POST['approved_qty'] : [],
            'status_remarks' => (string) ($_POST['status_remarks'] ?? ''),
        ];
    }
    if (!empty($_SESSION['request_popup_error']) && $requestId > 0) {
        redirect($requestPage . '?view=' . $requestId . '#requestModal');
    }
    if ($fromRequisitions) { unset($_SESSION['print_prompt_request_id']); redirect('requisitions.php'); }
    redirect($requestPage);
}

$catalogData = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? cssrRequestCatalog(db(), []) : ['items'=>[],'has_more'=>false,'next_offset'=>0];
$items = $catalogData['items'];
$departments = db()->query("SELECT id, department_name FROM departments WHERE status='Active' ORDER BY department_name")->fetchAll();
$requestWhere = ' WHERE r.status IN ("Pending","Approved")';
$requestParams = [];
if (!$canViewAllRequests) {
    $requestWhere .= ' AND r.department_id = ?';
    $requestParams[] = (int) $currentDepartmentId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_item') {
    $addItemAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    try {
        if (!$canAddToSubmittedRequest) {
            throw new RuntimeException('Your role cannot add items to submitted requests.');
        }
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $qty = filter_var($_POST['quantity_requested'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
        if ($itemId <= 0 || $qty <= 0) {
            throw new RuntimeException('Select an item and enter a valid quantity.');
        }

        $requestSql = 'SELECT * FROM item_requests WHERE id=? AND status="Pending"';
        $requestParams = [$requestId];
        if (!$canViewAllRequests) {
            $requestSql .= ' AND department_id=?';
            $requestParams[] = (int) $currentDepartmentId;
        }

        $submissionPayload=[$requestId,$itemId,$qty,(string)($_POST['item_remarks']??'')];
            if($replayed=cssrSubmissionReplay('department-add',$submissionPayload)){
                if($addItemAjax){header('Content-Type: application/json');echo json_encode($replayed);exit;}
                set_flash('success',$replayed['message']);redirect('item_requests.php?view='.$requestId);
            }
            db()->beginTransaction();
        $stmt = db()->prepare($requestSql . ' FOR UPDATE');
        $stmt->execute($requestParams);
        $request = $stmt->fetch();
        if (!$request) {
            throw new RuntimeException('Only submitted pending requests can be edited.');
        }

        $stmt = db()->prepare('SELECT item_code, item_name, current_stock FROM items WHERE id=? AND status="Active"');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new RuntimeException('Selected item is not active.');
        }
        $stmt = db()->prepare('SELECT id, quantity_requested FROM item_request_details WHERE request_id=? AND item_id=?');
        $stmt->execute([$requestId, $itemId]);
        $existing = $stmt->fetch();
        if ($existing) {
            $newQty = (int) $existing['quantity_requested'] + $qty;
            db()->prepare('UPDATE item_request_details SET quantity_requested=? WHERE id=?')->execute([$newQty, (int) $existing['id']]);
        } else {
            db()->prepare('INSERT INTO item_request_details (request_id, item_id, quantity_requested, remarks) VALUES (?,?,?,?)')->execute([$requestId, $itemId, $qty, trim($_POST['item_remarks'] ?? '')]);
        }

        log_activity('UPDATE', 'Item Requests', 'Added item ' . $item['item_code'] . ' to request ' . $request['request_no'] . ' by ' . current_user()['full_name']);
        db()->commit();
            cssrRememberSubmission('department-add',$submissionPayload,['ok'=>true,'message'=>'Item added to request.']);
        if ($addItemAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'message' => 'Item added to request.']);
            exit;
        }
        set_flash('success', 'Item added to request.');
        $addItemReturnUrl = $requestPage . '?view=' . $requestId;
        if ($requestReturnPage === 'request_history.php') {
            $addItemReturnUrl .= '&return=request_history.php';
        }
        if ($embedRequest) {
            $addItemReturnUrl .= '&embed=1';
        }
        redirect($addItemReturnUrl . '#requestModal');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if ($addItemAjax) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => safe_error_message($e)]);
            exit;
        }
        set_flash('danger', safe_error_message($e));
        $addItemReturnUrl = $requestPage . '?view=' . $requestId;
        if ($requestReturnPage === 'request_history.php') {
            $addItemReturnUrl .= '&return=request_history.php';
        }
        if ($embedRequest) {
            $addItemReturnUrl .= '&embed=1';
        }
        redirect($addItemReturnUrl . '#requestModal');
    }
}
$requests = [];
if ($canViewRequestLists) {
    $stmt = db()->prepare("SELECT r.*, COALESCE(SUM(d.quantity_requested),0) total_qty, COUNT(d.id) line_count FROM item_requests r LEFT JOIN item_request_details d ON d.request_id=r.id{$requestWhere} GROUP BY r.id ORDER BY r.created_at DESC LIMIT 80");
    $stmt->execute($requestParams);
    $requests = $stmt->fetchAll();
}
$selectedRequest = null;
$selectedDetails = [];
$availableLotsByItem = [];
if (!empty($_GET['view'])) {
    $viewSql = 'SELECT * FROM item_requests WHERE id=?';
    $viewParams = [(int) $_GET['view']];
    if (!$canViewAllRequests) {
        $viewSql .= ' AND department_id=?';
        $viewParams[] = (int) $currentDepartmentId;
    }
    $stmt = db()->prepare($viewSql);
    $stmt->execute($viewParams);
    $selectedRequest = $stmt->fetch();
    if ($selectedRequest) {
        $stmt = db()->prepare('SELECT d.*, i.item_code, i.item_name, i.unit, i.brand, i.item_image, COALESCE((SELECT SUM(l.quantity_available) FROM item_lots l WHERE l.item_id=i.id AND l.storage_location="CSSR"),0) AS current_stock, i.lot_number, i.expiry_date, i.bin_location FROM item_request_details d JOIN items i ON i.id=d.item_id WHERE d.request_id=? ORDER BY i.item_name');
        $stmt->execute([(int) $selectedRequest['id']]);
        $selectedDetails = $stmt->fetchAll();
        $selectedItemIds = array_values(array_unique(array_map(fn($detail) => (int) $detail['item_id'], $selectedDetails)));
        if ($selectedItemIds) {
            $placeholders = implode(',', array_fill(0, count($selectedItemIds), '?'));
            $lotStmt = db()->prepare("SELECT id, item_id, lot_number, expiry_date, lot_image, supplier, storage_location, unit_price, quantity_available, status FROM item_lots WHERE item_id IN ($placeholders) AND storage_location=\"CSSR\" AND lot_number<>\"UNIDENTIFIED / PENDING\" ORDER BY CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date, created_at, id");
            $lotStmt->execute($selectedItemIds);
            foreach ($lotStmt->fetchAll() as $lot) {
                $availableLotsByItem[(int) $lot['item_id']][] = $lot;
            }
        }
    }
}
$clearRequestCartAfterSubmit = !empty($_SESSION['clear_request_cart_after_submit']);
unset($_SESSION['clear_request_cart_after_submit']);
$bodyClass = trim(
    (($selectedRequest && ($_GET['print'] ?? '') === '1') ? 'request-voucher-printing ' : '')
    . ($selectedRequest ? 'request-detail-open' : '')
);
include __DIR__ . '/../includes/header.php';
?>
<?php if ($embedRequest): ?>
<style>
html,body,.app-shell,.main-content{background:transparent!important}
.sidebar,.topbar,.page-heading,.request-shell,.mobile-cart-bar,.mobile-cart-drawer{display:none!important}
.main-content{margin:0!important;width:100%!important;padding:0!important;min-height:0!important}
.request-modal{background:rgba(15,23,42,.32)!important}
</style>
<?php endif; ?>
<section class="<?= !$requestQueueOnly ? 'request-shell' : '' ?>">
    <?php if (!$requestQueueOnly): ?>
    <div class="request-left page-panel">
        <div class="pos-panel-title"><div><span class="eyebrow">Department Supply Request</span><h3>Create Supply Request</h3></div><span class="pos-count">Multi-item</span></div>
        <?php if (!$canCreateRequest): ?>
            <div class="alert alert-info">Your role can view department requests only.</div>
        <?php elseif (!$canViewAllRequests && !$currentDepartmentId): ?>
            <div class="alert alert-warning">Your user account is not assigned to a department. Ask the admin to update your user profile before creating requests.</div>
        <?php else: ?>
        <form id="requestForm" method="post" data-confirm="custom">
            <?= csrf_field() ?><?= cssrSubmissionField() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="cart_json" id="requestCartJson">
            <div class="row">
                <div class="col-md-6 mb-2"><label class="form-label">Request No.</label><input id="requestNoPreview" class="form-control" value="<?= e($previewRequestNo) ?>" readonly></div>
                <div class="col-md-6 mb-2"><label class="form-label" for="requestDate">Request Date</label><input id="requestDate" class="form-control" type="date" name="request_date" value="<?= cssrTransactionToday() ?>" <?= cssrCanEditTransactionDate(current_user() ?? []) ? '' : 'readonly aria-readonly="true"' ?> required></div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Department</label>
                    <?php if ($canViewAllRequests): ?>
                        <select class="form-select" name="department_id" required><option value="">Select department</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= e($department['department_name']) ?></option><?php endforeach; ?></select>
                    <?php else: ?>
                        <input type="hidden" name="department_id" value="<?= (int) $currentDepartmentId ?>">
                        <input class="form-control" value="<?= e($currentDepartmentName) ?>" readonly>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-2"><label class="form-label">Requested By</label><input class="form-control" name="requested_by" value="<?= e(current_user()['full_name']) ?>" readonly></div>
                <input type="hidden" name="purpose" value="">
                <input type="hidden" name="remarks" value="">
            </div>
            <input id="requestSearch" class="form-control pos-search" placeholder="Search item code, description, lot, category, or bin">
            <div id="requestItemList" class="pos-item-list request-item-list"></div>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($canCreateRequest && ($canViewAllRequests || $currentDepartmentId)): ?>
    <div id="departmentRequestCartPanel" class="request-right page-panel mobile-cart-drawer">
        <div class="pos-panel-title"><div><span class="eyebrow">Request Cart</span><h3>Requested Items</h3></div><div class="mobile-cart-head-actions"><span class="pos-count">Selected</span><button class="btn btn-light btn-sm mobile-cart-close" type="button" onclick="closeDepartmentRequestCart()" aria-label="Close requested items">Close</button></div></div>
        <div id="requestCart" class="pos-cart-list"></div>
        <div class="pos-total-bar">
            <div><span>Lines</span><strong id="requestLines">0</strong></div>
            <div><span>Total Qty</span><strong id="requestQty">0</strong></div>
            <button class="btn btn-light" type="button" onclick="clearRequestCart()">Clear Cart</button>
            <button class="btn btn-primary" type="submit" form="requestForm">Submit Request</button>
        </div>
    </div>
    <div id="departmentRequestMobileBar" class="mobile-cart-bar" aria-live="polite">
        <div><strong id="departmentRequestMobileCount">0</strong><span> selected item(s)</span></div>
        <button class="btn btn-primary" type="button" onclick="openDepartmentRequestCart()">View Cart</button>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($requestQueueOnly): ?>
    <div class="page-panel no-print">
        <div class="pos-panel-title"><div><span class="eyebrow">Request Queue</span><h3><?= $canViewAllRequests ? 'Submitted Requests' : e($currentDepartmentName ?: 'My Department') . ' Requests' ?></h3></div></div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Request No.</th><th>Department</th><th>Date</th><th>Lines</th><th>Qty</th><th>Status</th><th></th></tr></thead>
                <tbody><?php foreach ($requests as $request): ?><tr><td><?= e($request['request_no']) ?></td><td><?= e($request['department_name']) ?></td><td><?= e($request['request_date']) ?></td><td><?= (int) $request['line_count'] ?></td><td><?= (int) $request['total_qty'] ?></td><td><span class="badge request-status <?= e(strtolower($request['status'])) ?>"><?= e($request['status']) ?></span></td><td class="request-list-actions"><a class="btn btn-sm btn-light" href="?view=<?= (int) $request['id'] ?>#requestModal">View</a><?php if (in_array($request['status'], ['Approved', 'Released'], true)): ?><a class="btn btn-sm btn-primary" href="?view=<?= (int) $request['id'] ?>&print=1#requestModal">Print</a><?php endif; ?></td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</section>

<?php if (!$requestQueueOnly && $canCreateRequest && ($canViewAllRequests || $currentDepartmentId)): ?>
<div id="requestSummaryModal" class="app-modal">
    <div class="app-modal-card">
        <div class="app-modal-head">
            <div><span class="eyebrow">Request Summary</span><h3>Confirm Requested Items</h3></div>
            <button class="btn btn-light btn-sm" type="button" data-modal-close>Close</button>
        </div>
        <div class="app-modal-body">
            <div class="table-responsive request-summary-scroll" tabindex="0" aria-label="Requested item summary. Swipe or scroll sideways to view all details.">
                <table class="table request-summary-table" data-enhanced="true">
                    <thead><tr><th>Code</th><th>Description</th><th>Requested</th></tr></thead>
                    <tbody id="requestSummaryRows"></tbody>
                </table>
            </div>
            <div class="summary-pager" id="requestSummaryPager"></div>
            <div class="pos-total-bar">
                <div><span>Total Lines</span><strong id="requestSummaryLines">0</strong></div>
                <div><span>Total Qty</span><strong id="requestSummaryQty">0</strong></div>
                <button class="btn btn-primary" type="button" id="confirmRequestSubmit">Confirm and Submit</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($selectedRequest): ?>
<div id="requestModal" class="request-modal open <?= (($_GET['print'] ?? '') === '1') ? 'print-auto-hidden' : '' ?>">
    <div class="request-modal-panel">
        <div class="request-modal-head">
            <div>
                <span class="eyebrow">Request Details</span>
                <h3><?= e($selectedRequest['request_no']) ?> <span class="badge request-status <?= e(strtolower($selectedRequest['status'])) ?>"><?= e($selectedRequest['status']) ?></span></h3>
            </div>
            <div class="request-modal-actions no-print">
                <?php if ($selectedRequest['status'] !== 'Deleted'): ?><button class="btn btn-light" type="button" onclick="printRequestAndClose('<?= e($requestReturnPage) ?>',<?= $embedRequest ? 'true' : 'false' ?>)">Print</button><?php endif; ?>
                <?php if ($embedRequest): ?><button class="btn btn-light" type="button" onclick="parent.closeRequestHistoryDetail()">Close</button><?php else: ?><a class="btn btn-light" href="<?= e($requestReturnPage) ?>">Close</a><?php endif; ?>
            </div>
        </div>
        <div class="request-detail">
    <div class="receipt-meta request-screen-meta">
        <span><strong>Department:</strong> <?= e($selectedRequest['department_name']) ?></span>
        <span><strong>Requested By:</strong> <?= e($selectedRequest['requested_by']) ?></span>
        <span><strong>Date:</strong> <?= e($selectedRequest['request_date']) ?></span>
        <span><strong>Request No.:</strong> <?= e($selectedRequest['request_no']) ?></span>
        <?php if ($selectedRequest['status'] === 'Deleted'): ?><span><strong>Deleted By:</strong> <?= e($selectedRequest['deleted_by']) ?></span><span><strong>Deleted At:</strong> <?= e($selectedRequest['deleted_at']) ?></span><?php endif; ?>
    </div>
            <div class="table-responsive"><table class="table"><thead>
            <tr class="request-repeat-header print-only"><th colspan="5">
                <div class="bin-card-header request-document-header request-repeat-document-header">
                    <img class="bin-logo" src="../assets/img/doh-logo.png" alt="Department of Health logo">
                    <div class="facility-copy hospital-card-letterhead"><strong>DEPARTMENT OF HEALTH</strong><strong>JONI VILLANUEVA GENERAL HOSPITAL</strong><span>National Road, Igulot, Bocaue, Bulacan 3018</span></div>
                    <img class="bin-logo" src="../assets/img/jvgh-seal.png" alt="Joni Villanueva General Hospital seal">
                </div>
                <h2 class="request-voucher-title">VOUCHER'S SLIP</h2>
                <div class="receipt-meta">
                    <span><strong>Department:</strong> <?= e($selectedRequest['department_name']) ?></span>
                    <span><strong>Requested By:</strong> <?= e($selectedRequest['requested_by']) ?></span>
                    <span><strong>Date:</strong> <?= e($selectedRequest['request_date']) ?></span>
                    <span><strong>Request No.:</strong> <?= e($selectedRequest['request_no']) ?></span>
                </div>
            </th></tr>
            <tr><th class="no-print">Picture</th><th class="no-print">Code</th><th>Description</th><th>Unit</th><th class="print-only">Lot Number</th><?php if (can_manage()): ?><th class="no-print">Available Stocks</th><?php endif; ?><th class="no-print">Available Lot</th><?php if (can_manage()): ?><th class="no-print">Lot Stock</th><?php endif; ?><th>Requested Qty</th><th>Release Qty</th></tr></thead><tbody>
            <?php $itemsPerPrintPage = 15; $totalPrintItems = count($selectedDetails); ?>
            <?php foreach ($selectedDetails as $detailIndex => $detail): ?>
                <?php
                    $maxApprove = min((int) $detail['quantity_requested'], (int) $detail['current_stock']);
                    $itemLots = $availableLotsByItem[(int) $detail['item_id']] ?? [];
                    $preservedStateApplies = (int) ($requestFormState['request_id'] ?? 0) === (int) $selectedRequest['id'];
                    $selectedApprovedLotId = $preservedStateApplies
                        ? (int) ($requestFormState['approved_lot_id'][(int) $detail['id']] ?? 0)
                        : (int) ($detail['approved_item_lot_id'] ?? 0);
                    $selectedApprovedQty = $preservedStateApplies
                        ? (int) ($requestFormState['approved_qty'][(int) $detail['id']] ?? $maxApprove)
                        : $maxApprove;
                    $selectedLotRecord = null;
                    foreach ($itemLots as $candidateLot) {
                        if ((int) $candidateLot['id'] === $selectedApprovedLotId) {
                            $selectedLotRecord = $candidateLot;
                            break;
                        }
                    }
                    $requestPictureId = 'requestLotPicture' . (int) $detail['id'];
                    $requestLotStockId = 'requestLotStock' . (int) $detail['id'];
                    $selectedPicture = $selectedApprovedLotId > 0 ? null : ($detail['item_image'] ?? null);
                    if ($selectedApprovedLotId > 0) {
                        foreach ($itemLots as $pictureLot) {
                            if ((int) $pictureLot['id'] === $selectedApprovedLotId) {
                                $selectedPicture = $pictureLot['lot_image'] ?? null;
                                break;
                            }
                        }
                    }
                ?>
                <tr class="request-item-row <?= (($detailIndex + 1) % $itemsPerPrintPage === 0 && ($detailIndex + 1) < $totalPrintItems) ? 'request-page-break-after' : '' ?>">
                    <td class="no-print request-detail-picture-cell" id="<?= e($requestPictureId) ?>" data-fallback-image="<?= !empty($detail['item_image']) ? e('../uploads/items/' . $detail['item_image']) : '' ?>">
                        <?php if (!empty($selectedPicture)): ?>
                            <img class="request-detail-picture" src="../uploads/items/<?= e($selectedPicture) ?>" alt="<?= e($detail['item_name']) ?>" onclick="openImage(this.src)" role="button" tabindex="0">
                        <?php else: ?>
                            <span class="request-detail-picture-empty">IMG</span>
                        <?php endif; ?>
                    </td>
                    <td class="no-print"><?= e($detail['item_code']) ?></td>
                    <td><?= e($detail['item_name']) ?></td>
                    <td><?= e($detail['unit'] ?: 'N/A') ?></td>
                    <td class="print-only"><?= e($selectedLotRecord['lot_number'] ?? 'N/A') ?></td>
                    <?php if (can_manage()): ?><td class="no-print"><?= (int) $detail['current_stock'] ?></td><?php endif; ?>
                    <td class="no-print">
                        <?php if (($selectedRequest['status'] === 'Released' && (int) $detail['quantity_released'] <= 0) || ((int) $detail['current_stock'] <= 0 && $selectedRequest['status'] === 'Pending' && can_manage())): ?>
                            <span class="badge request-status deleted out-of-stock-badge">Out of Stock</span>
                        <?php elseif ($selectedRequest['status'] === 'Pending' && can_manage()): ?>
                            <select form="approveRequestForm" class="form-select approved-lot-select" name="approved_lot_id[<?= (int) $detail['id'] ?>]" data-detail-id="<?= (int) $detail['id'] ?>" data-picture-target="<?= e($requestPictureId) ?>" data-stock-target="<?= e($requestLotStockId) ?>" onchange="updateRequestLotPicture(this);updateRequestLotStock(this)">
                                <option value="">Select lot</option>
                                <?php foreach ($itemLots as $lot): $lotAvailable = (int) $lot['quantity_available']; $lotSelectable = $lotAvailable > 0 && $lot['status'] === 'AVAILABLE'; ?>
                                    <?php $lotOptionLabel = trim((string) $lot['lot_number']) . ' | ' . (($lot['supplier'] ?? '') !== '' ? (string) $lot['supplier'] : 'N/A') . ' | Price: ' . ($lot['unit_price'] === null ? '' : number_format((float) $lot['unit_price'], 2)) . ' | Qty: ' . $lotAvailable; ?>
                                    <option value="<?= (int) $lot['id'] ?>" data-stock="<?= $lotAvailable ?>" data-image="<?= !empty($lot['lot_image']) ? e('../uploads/items/' . $lot['lot_image']) : '' ?>" <?= (int) $lot['id'] === $selectedApprovedLotId ? 'selected' : '' ?> <?= !$lotSelectable ? 'disabled' : '' ?>><?= e($lotOptionLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?php $selectedLot = current(array_filter($itemLots, fn($lot) => (int) $lot['id'] === (int) ($detail['approved_item_lot_id'] ?? 0))); ?>
                            <?= $selectedLot ? e($selectedLot['lot_number']) : 'N/A' ?>
                        <?php endif; ?>
                    </td>
                    <?php if (can_manage()): ?><td class="no-print" id="<?= e($requestLotStockId) ?>"><?= $selectedLotRecord ? (int) $selectedLotRecord['quantity_available'] : ((int) $detail['current_stock'] <= 0 ? '0' : '—') ?></td><?php endif; ?>
                    <td><?= (int) $detail['quantity_requested'] ?></td>
                    <td><?php if ($selectedRequest['status'] === 'Pending' && can_manage() && (int) $detail['current_stock'] <= 0): ?><span class="badge request-status deleted out-of-stock-badge">Out of Stock</span><?php elseif ($selectedRequest['status'] === 'Pending' && can_manage()): ?><div class="release-qty-control"><input form="approveRequestForm" class="form-control qty-edit no-print" type="number" min="0" max="<?= $maxApprove ?>" data-detail-id="<?= (int) $detail['id'] ?>" data-item-name="<?= e($detail['item_name']) ?>" data-stock="<?= (int) $detail['current_stock'] ?>" data-requested="<?= (int) $detail['quantity_requested'] ?>" name="approved_qty[<?= (int) $detail['id'] ?>]" value="<?= $selectedApprovedQty ?>"><label class="release-out-option no-print" title="Mark as Out of Stock"><input form="approveRequestForm" type="checkbox" name="mark_out_of_stock[<?= (int) $detail['id'] ?>]" value="1" data-release-out-of-stock="<?= (int) $detail['id'] ?>" aria-label="Mark <?= e($detail['item_name']) ?> as Out of Stock" <?= $selectedApprovedQty <= 0 ? 'checked' : '' ?>></label></div><span class="print-only"><?= (int) $detail['quantity_approved'] ?></span><?php elseif ($selectedRequest['status'] === 'Released' && (int) $detail['quantity_released'] <= 0): ?><span class="badge request-status deleted out-of-stock-badge">Out of Stock</span><?php else: ?><?= (int) ($selectedRequest['status'] === 'Released' ? $detail['quantity_released'] : $detail['quantity_approved']) ?><?php endif; ?></td>
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
            </tbody></table></div>
            <?php if ($selectedRequest['status'] === 'Pending' && $canAddToSubmittedRequest): ?>
                <form id="addRequestItemForm" method="post" class="add-request-item no-print" data-confirm="custom" data-confirm-message="Add this item to the submitted request?" novalidate>
                    <?= csrf_field() ?><?= cssrSubmissionField() ?>
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="request_id" value="<?= (int) $selectedRequest['id'] ?>">
                    <div>
                        <label class="form-label">Add Item</label>
                        <div class="ris-add-item-picker">
                            <input class="form-control" id="addRequestItemPicker" placeholder="Type or select an item name" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="addRequestItemSuggestions" required>
                            <input type="hidden" id="addRequestItemSelect" name="item_id">
                            <div class="ris-add-item-suggestions" id="addRequestItemSuggestions" role="listbox" aria-label="Items"></div>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Qty</label>
                        <input class="form-control" id="addRequestItemQty" type="number" min="1" step="1" name="quantity_requested" value="1" required>
                    </div>
                    <div>
                        <label class="form-label">Remarks</label>
                        <input class="form-control" name="item_remarks">
                    </div>
                    <button class="btn btn-primary" type="submit" form="addRequestItemForm">Add Item</button>
                    <p id="addRequestItemFeedback" class="request-add-feedback" role="status" aria-live="polite" hidden></p>
                </form>
            <?php endif; ?>
            <?php $canDeleteSelectedRequest = $selectedRequest['status'] !== 'Deleted' && (can_manage() || $selectedRequest['status'] === 'Pending'); ?>
            <?php $canShowRequestActions = $canDeleteSelectedRequest || (can_manage() && in_array($selectedRequest['status'], ['Pending', 'Approved'], true)); ?>
            <?php if ($canShowRequestActions): ?>
                <form id="<?= $selectedRequest['status'] === 'Pending' ? 'approveRequestForm' : 'requestStatusForm' ?>" method="post" class="request-actions no-print" data-confirm="<?= $selectedRequest['status'] === 'Pending' ? 'custom' : '' ?>" data-confirm-message="Are you sure you want to update this item request?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="request_id" value="<?= (int) $selectedRequest['id'] ?>">
                    <input class="form-control" name="status_remarks" placeholder="Status remarks" value="<?= e($selectedRequest['remarks']) ?>">
                    <?php if (can_manage() && $selectedRequest['status'] === 'Pending'): ?><button class="btn btn-primary" name="action" value="release" data-confirm-message="Release this request and deduct stocks?">Release</button><?php endif; ?>
                    <?php if (can_manage() && $selectedRequest['status'] === 'Approved'): ?><button class="btn btn-primary" name="action" value="release" data-confirm-message="Release this request and deduct stocks?">Release Items</button><?php endif; ?>
                    <?php if ($canDeleteSelectedRequest): ?><button class="btn btn-danger" name="action" value="delete" data-confirm-message="Delete this request? This will record your name as the deleting user.">Delete Request</button><?php endif; ?>
                </form>
            <?php endif; ?>
            <div class="request-prepared-by print-only">
                <span>Prepared By:</span>
                <strong><?= e($selectedRequest['approved_by'] ?: 'N/A') ?></strong>
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
        </div>
    </div>
</div>
<?php endif; ?>

<div id="imageModal" class="modal-backdrop" onclick="closeImageModal()" aria-hidden="true">
    <div class="modal-box image-gallery-modal" role="dialog" aria-modal="true" aria-label="Item image preview" onclick="event.stopPropagation()" style="position:relative;max-width:960px">
        <div class="image-gallery-heading"><span>CSSR ITEM MEDIA</span><strong>Lot Image Preview</strong></div>
        <button type="button" class="btn btn-light image-gallery-close" data-image-modal-close onclick="closeImageModal()" aria-label="Close image preview" title="Close (Esc)">&times;</button>
        <button type="button" class="image-gallery-nav prev" data-gallery-prev aria-label="Previous image">&#8249;</button>
        <img class="detail-img" src="" alt="Item image preview">
        <button type="button" class="image-gallery-nav next" data-gallery-next aria-label="Next image">&#8250;</button>
        <div class="image-gallery-counter" data-gallery-counter></div>
    </div>
</div>

<script src="../assets/js/request-catalog.js?v=<?= filemtime(__DIR__.'/../assets/js/request-catalog.js') ?>"></script>
<?php if (!$requestQueueOnly && $canCreateRequest && ($canViewAllRequests || $currentDepartmentId)): ?>
<script>
const requestItems = <?= json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const requestCart = new Map();
const requestUploadBase = '../uploads/items/';
const requestCartStorageKey = 'cssr-request-cart-v2-<?= (int) (current_user()['id'] ?? 0) ?>';
const clearRequestCartAfterSubmit = <?= $clearRequestCartAfterSubmit ? 'true' : 'false' ?>;
const pendingSavedRequestItems = new Map((()=>{try{
    const saved=JSON.parse(localStorage.getItem(requestCartStorageKey)||'[]');
    return !clearRequestCartAfterSubmit&&Array.isArray(saved)?saved.map(row=>[Number(row.id),row]):[];
}catch{return [];}})());
const requestSummaryPageSize = 15;
let requestSummaryPage = 1;
function showRequestCartToast(type,message){
    document.querySelectorAll('.app-toast.request-cart-toast').forEach(toast=>toast.remove());
    showToastMessage(type,message);
    const toast=document.querySelector('.toast-stack .app-toast:last-child');
    if(toast)toast.classList.add('request-cart-toast');
}
function saveRequestCart(){
    try{
        localStorage.setItem(requestCartStorageKey,JSON.stringify([...pendingSavedRequestItems.values(),...Array.from(requestCart.values()).map(item=>({id:Number(item.id),qty:Number(item.qty)}))]));
    }catch(error){}
}
function restoreRequestCart(){
    try{
        const saved=Array.from(pendingSavedRequestItems.values());
        saved.forEach(line=>{
            const item=requestItems.find(row=>Number(row.id)===Number(line.id));
            const qty=Math.max(1,Number(line.qty||1));
            if(item){if(!requestCart.has(Number(item.id)))requestCart.set(Number(item.id),{...item,qty:qty});pendingSavedRequestItems.delete(Number(item.id));}
        });
    }catch(error){localStorage.removeItem(requestCartStorageKey);}
}
function requestEsc(value){return String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
function requestPreviewImage(event,id){
    event.stopPropagation();
    const item=requestItems.find(row=>Number(row.id)===Number(id));
    const images=item&&Array.isArray(item.available_lot_images)&&item.available_lot_images.length?item.available_lot_images:[item&&(item.available_lot_image||item.item_image)].filter(Boolean);
    if(images.length){
        openImageGallery(images.map(image=>requestUploadBase+image),0);
        document.getElementById('imageModal')?.setAttribute('aria-hidden','false');
    }
}
function closeRequestImage(){
    closeImageModal();
}
document.addEventListener('keydown',event=>{
    if(event.key==='Escape'&&document.getElementById('imageModal')?.classList.contains('open'))closeRequestImage();
});
function updateRequestLotPicture(select){
    if(!select||!select.matches('.approved-lot-select[data-picture-target]'))return;
    const target=document.getElementById(select.dataset.pictureTarget);
    if(!target)return;
    const option=select.options[select.selectedIndex];
    const src=select.value ? ((option&&option.dataset.image)||'') : (target.dataset.fallbackImage||'');
    target.replaceChildren();
    if(src){
        const img=document.createElement('img');
        img.className='request-detail-picture';
        img.src=src;
        img.alt='Selected lot image';
        img.tabIndex=0;
        img.setAttribute('role','button');
        img.addEventListener('click',()=>openImage(img.src));
        img.addEventListener('keydown',keyEvent=>{if(keyEvent.key==='Enter'||keyEvent.key===' '){keyEvent.preventDefault();openImage(img.src);}});
        target.appendChild(img);
    }else{
        const empty=document.createElement('span');
        empty.className='request-detail-picture-empty';
        empty.textContent='IMG';
        target.appendChild(empty);
    }
}
function requestImg(item, cls='pos-item-img'){
    const image=(Array.isArray(item.available_lot_images)&&item.available_lot_images[0])||item.available_lot_image||item.item_image;
    if(!image)return `<div class="${cls} pos-img-empty" title="No image uploaded">IMG</div>`;
    return `<img class="${cls}" src="${requestUploadBase}${requestEsc(image)}" alt="${requestEsc(item.item_name)}" title="Click to view the available lot picture" data-image-hover-preview role="button" tabindex="0" onclick="requestPreviewImage(event,${Number(item.id)})" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();requestPreviewImage(event,${Number(item.id)})}">`;
}
function renderRequestItems(visible){
    const q = document.getElementById('requestSearch').value.toLowerCase().trim();
    const list = document.getElementById('requestItemList');
    const filtered = (Array.isArray(visible)?visible:requestItems).filter(item => [item.item_code,item.item_name,item.brand,item.lot_number,item.category,item.bin_location].join(' ').toLowerCase().includes(q));
    list.innerHTML = filtered.map(item => `<button type="button" class="pos-item-card" onclick="addRequestItem(${Number(item.id)})">${requestImg(item)}<span class="pos-item-main"><strong>${requestEsc(item.item_name)}</strong></span></button>`).join('') || '<div class="pos-empty">No matching items found.</div>';
}
function addRequestItem(id){
    const item=requestItems.find(row=>Number(row.id)===Number(id));
    if(!item)return;
    const existing=requestCart.get(id);
    if(existing){
        showRequestCartToast('info','Item is already in the cart.');
        return;
    }
    requestCart.set(id,{...item,qty:1});
    renderRequestCart();
    showRequestCartToast('success',String(item.item_name||'Item')+' was added to the request cart.');
}
function updateRequestQty(id, qty){const item=requestCart.get(id);if(!item)return;item.qty=Math.max(1,Number(qty||1));requestCart.set(id,item);renderRequestCart();}
function removeRequestItem(id){requestCart.delete(id);renderRequestCart();}
function clearRequestCart(){
    if(requestCart.size===0)return;
    showConfirm('Remove all items from the request cart?',function(){pendingSavedRequestItems.clear();requestCart.clear();renderRequestCart();});
}
function openDepartmentRequestCart(){
    document.getElementById('departmentRequestCartPanel')?.classList.add('open');
    document.body.classList.add('mobile-cart-open');
}
function closeDepartmentRequestCart(){
    document.getElementById('departmentRequestCartPanel')?.classList.remove('open');
    document.body.classList.remove('mobile-cart-open');
}
function validateRequestStock(){
    for(const item of requestCart.values()){
        const requested=Number(item.qty||0);
        const available=Number(item.current_stock||0);
        if(available>0&&requested>available){
            showConfirm(String(item.item_name||'This item')+' does not have enough stock.',function(){
                document.querySelector('#requestCart .is-invalid')?.focus();
            },{alert:true});
            return false;
        }
    }
    return true;
}
function renderRequestCart(){
    const rows=Array.from(requestCart.values());let total=0;
    document.getElementById('requestCart').innerHTML = rows.map(item=>{total+=Number(item.qty);const available=Number(item.current_stock);const exceedsStock=available>0&&Number(item.qty)>available;const maxAttribute=available>0?` max="${available}"`:'';return `<div class="pos-cart-row">${requestImg(item,'pos-cart-img')}<div class="pos-cart-info"><strong>${requestEsc(item.item_name)}</strong></div><input class="form-control pos-qty${exceedsStock?' is-invalid':''}" type="number" min="1"${maxAttribute} value="${Number(item.qty)}" aria-invalid="${exceedsStock?'true':'false'}" onchange="updateRequestQty(${Number(item.id)},this.value)"><button type="button" class="btn btn-light btn-sm" onclick="removeRequestItem(${Number(item.id)})">Remove</button></div>`}).join('') || '<div class="pos-empty">Request cart is empty.</div>';
    document.getElementById('requestLines').textContent=rows.length;document.getElementById('requestQty').textContent=total;document.getElementById('requestCartJson').value=JSON.stringify(rows.map(item=>({id:Number(item.id),qty:Number(item.qty)})));
    const mobileBar=document.getElementById('departmentRequestMobileBar');
    const mobileCount=document.getElementById('departmentRequestMobileCount');
    if(mobileCount)mobileCount.textContent=rows.length;
    if(mobileBar)mobileBar.classList.toggle('has-items',rows.length>0);
    if(rows.length===0)closeDepartmentRequestCart();
    saveRequestCart();
}
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeDepartmentRequestCart();});
document.addEventListener('DOMContentLoaded',function(){
    const mobileBar=document.getElementById('departmentRequestMobileBar');
    if(mobileBar&&mobileBar.parentElement!==document.body)document.body.appendChild(mobileBar);
});
document.getElementById('requestSearch').addEventListener('input', renderRequestItems);
document.getElementById('requestDate').addEventListener('change', function(){
    const preview=document.getElementById('requestNoPreview');
    if(!this.value||!preview)return;
    fetch('item_requests.php?next_request_no='+encodeURIComponent(this.value), {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(response=>response.json())
        .then(data=>{if(data&&data.request_no)preview.value=data.request_no;})
        .catch(()=>{preview.value='REQ-'+this.value.slice(0,7)+'-0001';});
});
function renderRequestSummaryPage(){
    const rows=Array.from(requestCart.values());let total=0;
    const totalPages=Math.max(1,Math.ceil(rows.length/requestSummaryPageSize));
    requestSummaryPage=Math.min(Math.max(1,requestSummaryPage),totalPages);
    const pageRows=rows.slice((requestSummaryPage-1)*requestSummaryPageSize,requestSummaryPage*requestSummaryPageSize);
    rows.forEach(item=>{total+=Number(item.qty);});
    document.getElementById('requestSummaryRows').innerHTML=pageRows.map(item=>{
        return `<tr><td><strong>${requestEsc(item.item_code)}</strong></td><td>${requestEsc(item.item_name)}</td><td>${Number(item.qty)}</td></tr>`;
    }).join('');
    document.getElementById('requestSummaryLines').textContent=rows.length;
    document.getElementById('requestSummaryQty').textContent=total;
    document.getElementById('requestSummaryPager').innerHTML=rows.length>requestSummaryPageSize?`<span>Page ${requestSummaryPage} of ${totalPages}</span><div><button class="btn btn-light btn-sm" type="button" ${requestSummaryPage===1?'disabled':''} onclick="requestSummaryPage--;renderRequestSummaryPage()">Previous</button><button class="btn btn-light btn-sm" type="button" ${requestSummaryPage===totalPages?'disabled':''} onclick="requestSummaryPage++;renderRequestSummaryPage()">Next</button></div>`:'';
}
function showRequestSummary(){
    requestSummaryPage=1;
    renderRequestSummaryPage();
    closeDepartmentRequestCart();
    document.getElementById('requestSummaryModal').classList.add('open');
}
document.getElementById('requestForm').addEventListener('submit', function(event){
    if(requestCart.size===0){event.preventDefault();showToastMessage('warning','Add at least one requested item.');return;}
    if(!validateRequestStock()){event.preventDefault();return;}
    event.preventDefault();
    showRequestSummary();
});
document.getElementById('confirmRequestSubmit').addEventListener('click', function(){
    if(!validateRequestStock()){
        document.getElementById('requestSummaryModal').classList.remove('open');
        return;
    }
    var itemCount=requestCart.size;
    var totalQty=Array.from(requestCart.values()).reduce(function(total,item){
        return total+Number(item.qty||0);
    },0);
    showConfirm(
        'Submit this request with '+itemCount+' item'+(itemCount===1?'':'s')+' and a total quantity of '+totalQty+'?',
        function(){
            var form=document.getElementById('requestForm');
            form.dataset.confirm='off';
            document.getElementById('requestSummaryModal').classList.remove('open');
            closeDepartmentRequestCart();
            startLoading('Submitting item request...');
            form.submit();
        }
    );
});
if(clearRequestCartAfterSubmit){localStorage.removeItem(requestCartStorageKey);}else{restoreRequestCart();}
renderRequestItems();renderRequestCart();
<?php if (isset($catalogData)): ?>
const savedRequestIds=Array.from(pendingSavedRequestItems.keys());
cssrCatalogSearch({input:document.getElementById('requestSearch'),list:document.getElementById('requestItemList'),items:requestItems,render:renderRequestItems,endpoint:'item_requests.php',initial:<?= json_encode(['has_more'=>$catalogData['has_more'],'next_offset'=>$catalogData['next_offset']]) ?>,restoreIds:savedRequestIds,restored:()=>{restoreRequestCart();renderRequestCart();}});
<?php endif; ?>
</script>
<?php endif; ?>
<?php if ($selectedRequest): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var panel=document.querySelector('#requestModal .request-modal-panel');
    var original=panel&&panel.querySelector('.request-detail:not(.request-print-page-copy)');
    if(!panel||!original||panel.querySelector('.request-print-pages'))return;
    var rowsPerCopy=15;
    var totalRows=original.querySelectorAll('tbody .request-item-row').length;
    var pageCount=Math.max(1,Math.ceil(totalRows/rowsPerCopy));
    var pages=document.createElement('div');
    pages.className='request-print-pages';
    pages.setAttribute('aria-hidden','true');
    function buildPrintCopy(startIndex){
        var copy=original.cloneNode(true);
        copy.classList.add('request-print-page-copy');
        // Print copies must never contribute controls to the live request forms.
        copy.querySelectorAll('.no-print').forEach(function(element){element.remove()});
        copy.querySelectorAll('[form]').forEach(function(element){element.removeAttribute('form')});
        copy.querySelectorAll('[id]').forEach(function(element){element.removeAttribute('id')});
        copy.querySelectorAll('.request-page-break-after').forEach(function(row){row.classList.remove('request-page-break-after')});
        var copyBody=copy.querySelector('tbody');
        if(!copyBody)return copy;
        var itemRows=Array.from(copyBody.querySelectorAll('.request-item-row'));
        itemRows.forEach(function(row,index){
            if(index<startIndex||index>=startIndex+rowsPerCopy)row.remove();
        });
        copyBody.querySelectorAll('.request-print-blank-row').forEach(function(row){row.remove()});
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
});
</script>
<?php endif; ?>
<?php if ($selectedRequest && $selectedRequest['status'] !== 'Deleted' && ($_GET['print'] ?? '') === '1'): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    setTimeout(function(){ printRequestAndClose('<?= e($requestReturnPage) ?>'); }, 1000);
});
</script>
<?php endif; ?>
<?php if ($printPromptRequestId > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    setTimeout(function(){
        showConfirm('Do you want to print this request?', function(){
            window.location.href = '<?= e($requestPage) ?>?view=<?= (int) $printPromptRequestId ?>&print=1#requestModal';
        });
    }, 600);
});
</script>
<?php endif; ?>
<script>
function printRequestAndClose(closeUrl,closeEmbedded){
    var didClose = false;
    function closeRequestPrint(){
        if (didClose) return;
        didClose = true;
        if(closeEmbedded&&window.parent&&window.parent!==window&&typeof window.parent.closeRequestHistoryDetail==='function'){
            window.parent.closeRequestHistoryDetail();
        }else{
            window.location.href = closeUrl || 'item_requests.php';
        }
    }
    window.addEventListener('afterprint', closeRequestPrint, { once: true });
    window.print();
    setTimeout(closeRequestPrint, 1200);
}
var approveForm=document.getElementById('approveRequestForm');
if(approveForm){
    function syncReleaseOutOfStock(checkbox){
        var detailId=checkbox.dataset.releaseOutOfStock||'';
        var qtyInput=document.querySelector('.qty-edit[data-detail-id="'+detailId+'"][form="approveRequestForm"]');
        var lotSelect=document.querySelector('.approved-lot-select[data-detail-id="'+detailId+'"][form="approveRequestForm"]');
        if(!qtyInput)return;
        if(checkbox.checked){
            if(Number(qtyInput.value)>0)qtyInput.dataset.previousQty=qtyInput.value;
            qtyInput.value='0';
            qtyInput.readOnly=true;
            if(lotSelect)lotSelect.disabled=true;
        }else{
            qtyInput.readOnly=false;
            var maximum=Math.min(Number(qtyInput.dataset.requested||0),Number(qtyInput.dataset.stock||0));
            qtyInput.value=String(Math.min(Number(qtyInput.dataset.previousQty||maximum),maximum));
            if(lotSelect)lotSelect.disabled=false;
        }
    }
    document.querySelectorAll('[data-release-out-of-stock][form="approveRequestForm"]').forEach(function(checkbox){
        checkbox.addEventListener('change',function(){syncReleaseOutOfStock(checkbox)});
        syncReleaseOutOfStock(checkbox);
    });
    approveForm.addEventListener('submit',function(event){
        var submitButton=event.submitter;
        var submitAction=submitButton&&submitButton.value||'release';
        if(submitAction!=='release'){
            event.preventDefault();
            showConfirm(submitButton&&submitButton.dataset.confirmMessage||'Continue with this request action?',function(){
                var action=document.createElement('input');
                action.type='hidden';action.name='action';action.value=submitAction;
                approveForm.appendChild(action);
                approveForm.dataset.confirm='off';
                startLoading('Updating item request...');
                approveForm.submit();
            });
            return;
        }
        var inputs=document.querySelectorAll('.qty-edit[form="approveRequestForm"]');
        for(var i=0;i<inputs.length;i++){
            var input=inputs[i];
            var qty=Number(input.value||0);
            var requested=Number(input.dataset.requested||0);
            var stock=Number(input.dataset.stock||0);
            var itemName=input.dataset.itemName||'This item';
            var detailId=input.dataset.detailId||'';
            var lotSelect=document.querySelector('.approved-lot-select[data-detail-id="'+detailId+'"][form="approveRequestForm"]');
            var selectedLotOption=lotSelect&&lotSelect.selectedIndex>=0?lotSelect.options[lotSelect.selectedIndex]:null;
            var lotStock=selectedLotOption?Number(selectedLotOption.dataset.stock||0):0;
            if(qty<0){event.preventDefault();showToastMessage('danger','Release quantity cannot be negative.');return;}
            if(qty>requested){event.preventDefault();showToastMessage('danger','Release quantity for '+itemName+' cannot exceed requested quantity.');return;}
            if(qty>stock){event.preventDefault();showToastMessage('danger',itemName+' does not have enough stock.');return;}
            if(qty>0&&(!lotSelect||!lotSelect.value)){event.preventDefault();showConfirm('There is no available lot selected for '+itemName+'. Add or select an available lot before releasing.',function(){lotSelect?.focus();},{alert:true});return;}
            if(qty>lotStock){event.preventDefault();showConfirm(itemName+' does not have enough stock in the selected lot.',function(){input.focus();},{alert:true});return;}
        }
        event.preventDefault();
        showConfirm('Release this request and deduct stocks?',function(){
            var action=document.createElement('input');
            action.type='hidden';action.name='action';action.value='release';
            approveForm.appendChild(action);
            approveForm.dataset.confirm='off';
            startLoading('Releasing item request...');
            fetch(window.location.href,{
                method:'POST',
                headers:{'X-Requested-With':'XMLHttpRequest'},
                body:new FormData(approveForm),
                credentials:'same-origin'
            }).then(function(response){
                return response.json().then(function(data){return {ok:response.ok,data:data};});
            }).then(function(result){
                if(!result.ok||!result.data.ok)throw new Error(result.data.message||'Unable to release the request.');
                var overlay=document.getElementById('loadingOverlay');
                if(overlay)overlay.classList.remove('open');
                var releaseMessage=result.data.message||'Request released.';
                if(window.parent&&window.parent!==window&&window.parent.showPendingRequestToast){
                    window.parent.showPendingRequestToast('success',releaseMessage);
                }else{
                    showToastMessage('success',releaseMessage);
                }
                setTimeout(function(){
                    if(window.parent&&window.parent!==window&&window.parent.closePendingRequestDetail){
                        window.parent.closePendingRequestDetail();
                        if(window.parent.reloadPendingRequestsWithToast){
                            window.parent.reloadPendingRequestsWithToast('success',releaseMessage);
                        }else{
                            window.parent.location.reload();
                        }
                    }else{
                        window.location.href='request_queue.php';
                    }
                },700);
            }).catch(function(error){
                var overlay=document.getElementById('loadingOverlay');
                if(overlay)overlay.classList.remove('open');
                showConfirm(error.message||'Unable to release the request.',function(){},{alert:true});
            }).finally(function(){
                action.remove();
            });
        });
    });
}
var requestStatusForm=document.getElementById('requestStatusForm');
if(requestStatusForm){
    requestStatusForm.addEventListener('submit',function(event){
        var submitButton=event.submitter;
        var submitAction=submitButton&&submitButton.value||'';
        if(submitAction!=='release')return;
        event.preventDefault();
        showConfirm(submitButton&&submitButton.dataset.confirmMessage||'Release this request and deduct stocks?',function(){
            var action=document.createElement('input');
            action.type='hidden';action.name='action';action.value='release';
            requestStatusForm.appendChild(action);
            requestStatusForm.dataset.confirm='off';
            startLoading('Releasing item request...');
            fetch(window.location.href,{
                method:'POST',
                headers:{'X-Requested-With':'XMLHttpRequest'},
                body:new FormData(requestStatusForm),
                credentials:'same-origin'
            }).then(function(response){
                return response.json().then(function(data){return {ok:response.ok,data:data};});
            }).then(function(result){
                if(!result.ok||!result.data.ok)throw new Error(result.data.message||'Unable to release the request.');
                var overlay=document.getElementById('loadingOverlay');
                if(overlay)overlay.classList.remove('open');
                var releaseMessage=result.data.message||'Request released.';
                if(window.parent&&window.parent!==window&&window.parent.showPendingRequestToast){
                    window.parent.showPendingRequestToast('success',releaseMessage);
                }else{
                    showToastMessage('success',releaseMessage);
                }
                setTimeout(function(){
                    if(window.parent&&window.parent!==window&&window.parent.closePendingRequestDetail){
                        window.parent.closePendingRequestDetail();
                        if(window.parent.reloadPendingRequestsWithToast){
                            window.parent.reloadPendingRequestsWithToast('success',releaseMessage);
                        }else{
                            window.parent.location.reload();
                        }
                    }else{
                        window.location.href='request_queue.php';
                    }
                },700);
            }).catch(function(error){
                var overlay=document.getElementById('loadingOverlay');
                if(overlay)overlay.classList.remove('open');
                showConfirm(error.message||'Unable to release the request.',function(){},{alert:true});
            }).finally(function(){
                action.remove();
            });
        });
    });
}
var addRequestItemForm=document.getElementById('addRequestItemForm');
if(addRequestItemForm){
    var addPicker=document.getElementById('addRequestItemPicker');
    var addSelect=document.getElementById('addRequestItemSelect');
    var addSuggestions=document.getElementById('addRequestItemSuggestions');
    var addItemMap=<?= json_encode(array_map(fn($item) => [
        'id' => (int) $item['id'],
        'code' => $item['item_code'],
        'label' => $item['item_name'],
    ], $items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    addItemMap=addItemMap.map(function(item){return {id:Number(item.id),code:String(item.code||'').trim(),label:String(item.label||item.code||('Item #'+item.id)).trim()};});
    // Render outside the scrolling request panel so options stay visible and clickable.
    document.body.appendChild(addSuggestions);
    var addItemBusy=false;
    var addFeedback=document.getElementById('addRequestItemFeedback');
    function showAddItemFeedback(message,isError){
        addFeedback.textContent=message;addFeedback.hidden=!message;
        addFeedback.dataset.error=String(!!isError);
    }
    <?php if(isset($catalogData)): ?>
    cssrCatalogLookup({input:addPicker,endpoint:'item_requests.php',onItems:function(rows){
        addItemMap=rows.map(function(item){return {id:Number(item.id),code:item.item_code,label:item.item_name};});
        if(document.activeElement===addPicker)renderAddItemSuggestions();
    },onError:function(message){showAddItemFeedback(message,true);}});
    <?php endif; ?>
    function closeAddItemSuggestions(){
        addSuggestions.innerHTML='';addPicker.setAttribute('aria-expanded','false');addPicker.removeAttribute('aria-activedescendant');
    }
    function positionAddItemSuggestions(){
        if(!addSuggestions.children.length)return;
        var rect=addPicker.getBoundingClientRect();
        var viewport=window.visualViewport;
        var top=viewport?viewport.offsetTop:0;
        var bottom=top+(viewport?viewport.height:window.innerHeight);
        var left=viewport?viewport.offsetLeft:0;
        var width=viewport?viewport.width:window.innerWidth;
        if(rect.bottom<top||rect.top>bottom){closeAddItemSuggestions();return;}
        var below=Math.max(0,bottom-rect.bottom-12),above=Math.max(0,rect.top-top-12);
        var upwards=below<180&&above>below;
        var height=Math.min(260,upwards?above:below);
        addSuggestions.style.width=Math.min(rect.width,width-16)+'px';
        addSuggestions.style.left=Math.max(left+8,Math.min(rect.left,left+width-addSuggestions.offsetWidth-8))+'px';
        addSuggestions.style.maxHeight=height+'px';
        addSuggestions.style.top=(upwards?rect.top-addSuggestions.offsetHeight-4:rect.bottom+4)+'px';
    }
    function addItemEsc(value){
        return String(value||'').replace(/[&<>"']/g,function(char){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
        });
    }
    function renderAddItemSuggestions(){
        if(!addSuggestions||!addPicker)return;
        var typed=(addPicker.value||'').trim().toLowerCase();
        var matches=addItemMap.filter(function(item){
            var label=String(item.label||'').toLowerCase();
            var code=String(item.code||'').toLowerCase();
            return !typed||label.includes(typed)||code.includes(typed);
        }).slice(0,80);
        if(!matches.length){
            addSuggestions.innerHTML='<div class="ris-add-item-suggestion empty">No available item found.</div>';
        }else addSuggestions.innerHTML=matches.map(function(item){
            return '<button type="button" class="ris-add-item-suggestion" id="ris-add-option-'+item.id+'" data-item-id="'+item.id+'" role="option" aria-selected="false" tabindex="-1"><span>'+addItemEsc(item.label)+'</span><small>'+addItemEsc(item.code)+'</small></button>';
        }).join('');
        addPicker.setAttribute('aria-expanded','true');addPicker.removeAttribute('aria-activedescendant');
        positionAddItemSuggestions();
    }
    function selectRisAddItem(itemId){
        var found=addItemMap.find(function(item){return Number(item.id)===Number(itemId);});
        if(!found)return;
        if(addPicker)addPicker.value=found.label;
        if(addSelect)addSelect.value=found.id;
        addPicker.setCustomValidity('');
        addPicker.removeAttribute('aria-invalid');
        showAddItemFeedback('',false);
        closeAddItemSuggestions();
    }
    function selectedRisAddItem(){
        var typed=(addPicker&&addPicker.value||'').trim().toLowerCase();
        if(!typed)return null;
        var selected=addItemMap.find(function(item){return String(item.id)===addSelect.value&&item.label.toLowerCase()===typed;});
        if(selected)return selected;
        var exact=addItemMap.filter(function(item){return item.label.toLowerCase()===typed||item.code.toLowerCase()===typed;});
        return exact.length===1?exact[0]:null;
    }
    function syncAddItemPicker(){
        addPicker.setCustomValidity('');
        addPicker.removeAttribute('aria-invalid');
        showAddItemFeedback('',false);
        var found=selectedRisAddItem();
        if(addSelect)addSelect.value=found?found.id:'';
        renderAddItemSuggestions();
        return found;
    }
    if(addPicker){
        addPicker.addEventListener('input',syncAddItemPicker);
        addPicker.addEventListener('focus',renderAddItemSuggestions);
        addPicker.addEventListener('click',renderAddItemSuggestions);
        addPicker.addEventListener('keydown',function(event){
            if(event.key==='Escape'||event.key==='Tab'){closeAddItemSuggestions();if(event.key==='Escape'){event.preventDefault();event.stopPropagation();}return;}
            if(!['ArrowDown','ArrowUp','Enter'].includes(event.key))return;
            if(addPicker.getAttribute('aria-expanded')!=='true'){if(event.key==='Enter')return;renderAddItemSuggestions();}
            var options=Array.from(addSuggestions.querySelectorAll('[role="option"]'));
            var index=options.findIndex(function(option){return option.id===addPicker.getAttribute('aria-activedescendant');});
            event.preventDefault();
            if(event.key==='Enter'){if(index>=0)selectRisAddItem(options[index].dataset.itemId);return;}
            if(!options.length)return;
            var next=index<0?(event.key==='ArrowDown'?0:options.length-1):(index+(event.key==='ArrowDown'?1:-1)+options.length)%options.length;
            options.forEach(function(option,i){option.setAttribute('aria-selected',String(i===next));});
            addPicker.setAttribute('aria-activedescendant',options[next].id);options[next].scrollIntoView({block:'nearest'});
        });
    }
    if(addSuggestions){
        addSuggestions.addEventListener('mousedown',function(event){event.preventDefault();});
        addSuggestions.addEventListener('click',function(event){
            var button=event.target.closest('.ris-add-item-suggestion[data-item-id]');
            if(!button)return;
            selectRisAddItem(button.dataset.itemId);
        });
    }
    document.addEventListener('click',function(event){
        if(event.target!==addPicker&&!addSuggestions.contains(event.target))closeAddItemSuggestions();
    });
    window.addEventListener('resize',positionAddItemSuggestions);
    document.addEventListener('scroll',function(event){if(!addSuggestions.contains(event.target))positionAddItemSuggestions();},true);
    if(window.visualViewport){window.visualViewport.addEventListener('resize',positionAddItemSuggestions);window.visualViewport.addEventListener('scroll',positionAddItemSuggestions);}
    addRequestItemForm.addEventListener('submit',function(event){
        event.preventDefault();
        if(addItemBusy)return;
        var selected=selectedRisAddItem();
        var qtyInput=document.getElementById('addRequestItemQty');
        var qty=Number(qtyInput&&qtyInput.value||0);
        // Validate here so stale browser validation cannot silently swallow submit.
        addPicker.setCustomValidity('');qtyInput.setCustomValidity('');
        addPicker.removeAttribute('aria-invalid');qtyInput.removeAttribute('aria-invalid');
        if(!selected){showAddItemFeedback('Choose an item from the dropdown before adding it.',true);addPicker.setAttribute('aria-invalid','true');addPicker.focus();renderAddItemSuggestions();return;}
        if(!Number.isSafeInteger(qty)||qty<=0){showAddItemFeedback('Enter a whole quantity of at least 1.',true);qtyInput.setAttribute('aria-invalid','true');qtyInput.focus();return;}
        addSelect.value=String(selected.id);
        closeAddItemSuggestions();
        showAddItemFeedback('',false);
        var addPayload=new FormData(addRequestItemForm);
        showConfirm('Add this item to the submitted request?',function(){
            if(addItemBusy)return;
            addItemBusy=true;
            var submitButton=addRequestItemForm.querySelector('button[type="submit"],button:not([type])');
            if(submitButton){submitButton.disabled=true;submitButton.textContent='Adding…';}
            showAddItemFeedback('Adding item to this request…',false);
            startLoading('Adding item to request...');
            fetch(window.location.href,{
                method:'POST',
                headers:{'X-Requested-With':'XMLHttpRequest'},
                body:addPayload,
                credentials:'same-origin'
            }).then(function(response){
                if(response.redirected)throw new Error('Your session may have expired. Refresh the page and sign in again.');
                return response.json().catch(function(){throw new Error('The server could not save the item. Refresh the page and try again.');}).then(function(data){return {ok:response.ok,data:data}});
            }).then(function(result){
                if(!result.ok||!result.data.ok)throw new Error(result.data.message||'Unable to add the item.');
                var overlay=document.getElementById('loadingOverlay');
                if(overlay)overlay.classList.remove('open');
                var addMessage=result.data.message||'Item added to request.';
                showAddItemFeedback(addMessage,false);
                if(window.parent&&window.parent!==window&&window.parent.showPendingRequestToast){
                    window.parent.showPendingRequestToast('success',addMessage);
                }else{
                    showToastMessage('success',addMessage);
                }
                setTimeout(function(){window.location.reload()},900);
            }).catch(function(error){
                addItemBusy=false;if(submitButton){submitButton.disabled=false;submitButton.textContent='Add Item';}
                var overlay=document.getElementById('loadingOverlay');
                if(overlay)overlay.classList.remove('open');
                showAddItemFeedback(error.message||'Unable to add the item. Please try again.',true);
            });
        });
    });
}
</script>
<?php if ($requestPopupError !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    showConfirm(<?= json_encode($requestPopupError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,function(){
        document.querySelector('#requestModal .approved-lot-select')?.focus();
    },{alert:true});
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>


