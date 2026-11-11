<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__.'/../includes/submission.php';
require_login();

$embedReturn = ($_GET['embed'] ?? '') === '1';
$allowedReturnPages = ['request_queue.php', 'request_history.php'];
$returnParam = (string) ($_GET['return'] ?? '');
$returnPage = in_array($returnParam, $allowedReturnPages, true) ? $returnParam : 'department_returns.php';
$pageTitle = $returnPage === 'request_queue.php' ? 'Release Queue' : 'Department Return';
$activePage = $returnPage === 'request_queue.php' ? 'request_queue' : 'department_returns';
$pageDescription = 'Create and receive returned supplies from departments.';

function next_department_return_no(?string $returnDate = null): string
{
    $timestamp = $returnDate ? strtotime($returnDate) : time();
    if ($timestamp === false) {
        $timestamp = time();
    }
    $prefix = 'RET-' . date('Y-m', $timestamp) . '-';
    $latest = db_value(
        'SELECT return_no FROM department_returns WHERE return_no LIKE ? ORDER BY return_no DESC LIMIT 1',
        [$prefix . '%']
    );
    $next = 1;
    if (is_string($latest) && preg_match('/-(\d{4})$/', $latest, $matches)) {
        $next = (int) $matches[1] + 1;
    }
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function return_nullable_date(?string $date): ?string
{
    $date = trim((string) $date);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
}

function department_returnable_quantity(string $departmentName, int $itemId, string $lotNumber): int
{
    $issued = (int) db_value('SELECT COALESCE(SUM(qty),0) FROM (
        SELECT quantity_issued qty FROM stock_out WHERE UPPER(TRIM(department)) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(?)) COLLATE utf8mb4_unicode_ci AND item_id=? AND UPPER(TRIM(COALESCE(lot_number,""))) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(?)) COLLATE utf8mb4_unicode_ci
        UNION ALL
        SELECT d.quantity_released FROM item_requests r JOIN item_request_details d ON d.request_id=r.id JOIN item_lots l ON l.id=d.approved_item_lot_id
         WHERE r.status="Released" AND UPPER(TRIM(r.department_name)) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(?)) COLLATE utf8mb4_unicode_ci AND d.item_id=? AND UPPER(TRIM(l.lot_number)) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(?)) COLLATE utf8mb4_unicode_ci
    ) issued', [$departmentName,$itemId,$lotNumber,$departmentName,$itemId,$lotNumber]);
    $returned = (int) db_value('SELECT COALESCE(SUM(d.quantity_returned),0) FROM department_returns r JOIN department_return_details d ON d.return_id=r.id
        WHERE r.status<>"Deleted" AND UPPER(TRIM(r.department_name)) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(?)) COLLATE utf8mb4_unicode_ci AND d.item_id=? AND UPPER(TRIM(COALESCE(d.lot_number,""))) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(?)) COLLATE utf8mb4_unicode_ci', [$departmentName,$itemId,$lotNumber]);
    return max(0, $issued - $returned);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['next_return_no'])) {
    header('Content-Type: application/json');
    try {
        echo json_encode(['return_no' => next_department_return_no(cssrTransactionDate(current_user() ?? [], $_GET['next_return_no']))]);
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo json_encode(['error' => safe_error_message($error)]);
    }
    exit;
}

$canCreateReturn = shared_has_permission('ris_requests', 'create') || shared_has_permission('cssr_inventory', 'create');
$canReceiveReturn = can_manage();
$currentDepartmentId = (int) (current_user()['department_id'] ?? 0);
$currentDepartmentName = (string) (current_user()['department_name'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    try {
        if (!$canCreateReturn) {
            throw new RuntimeException('You do not have permission to create a department return.');
        }
        $cart = json_decode($_POST['cart_json'] ?? '[]', true);
        if (!is_array($cart) || count($cart) === 0) {
            throw new RuntimeException('Add at least one returned item.');
        }

        $departmentId = can_manage() ? (int) ($_POST['department_id'] ?? 0) : $currentDepartmentId;
        if ($departmentId <= 0) {
            throw new RuntimeException('Please select a valid department.');
        }
        $stmt = db()->prepare('SELECT department_name FROM departments WHERE id=? AND status="Active"');
        $stmt->execute([$departmentId]);
        $departmentName = $stmt->fetchColumn();
        if (!$departmentName) {
            throw new RuntimeException('Please select a valid department.');
        }
        $returnDate = cssrTransactionDate(current_user() ?? [], $_POST['return_date'] ?? '');

        $submissionPayload=[(int)current_user()['id'],array_diff_key($_POST,['csrf_token'=>true])];
        if(cssrSubmissionReplay('return-create',$submissionPayload)){set_flash('success','This request has already been saved.');redirect('department_returns.php');}
        db()->beginTransaction();
        $returnNo = next_department_return_no($returnDate);
        db()->prepare(
            'INSERT INTO department_returns (return_no, department_id, department_name, returned_by, return_date, reason, remarks, created_by)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([
            $returnNo,
            $departmentId,
            $departmentName,
            current_user()['full_name'],
            $returnDate,
            trim((string) ($_POST['reason'] ?? '')),
            trim((string) ($_POST['remarks'] ?? '')),
            current_user()['id'] ?? null,
        ]);
        $returnId = (int) db()->lastInsertId();

        foreach ($cart as $line) {
            $itemId = (int) ($line['id'] ?? 0);
            $qty = filter_var($line['qty'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
            if ($itemId <= 0 || $qty <= 0) {
                throw new RuntimeException('Every returned item must have a valid quantity.');
            }
            $item = db_one('SELECT id, item_description FROM items WHERE id=? AND status="Active"', [$itemId]);
            if (!$item) {
                throw new RuntimeException('One returned item is no longer active.');
            }
            $lotNumber = strtoupper(trim((string) ($line['lot_number'] ?? '')));
            if ($lotNumber === '') {
                throw new RuntimeException('Select the issued lot for ' . $item['item_description'] . '.');
            }
            $returnableQty = department_returnable_quantity((string) $departmentName, $itemId, $lotNumber);
            if ($qty > $returnableQty) {
                throw new RuntimeException($item['item_description'] . ' has only ' . $returnableQty . ' returnable unit(s) for lot ' . $lotNumber . '.');
            }
            db()->prepare(
                'INSERT INTO department_return_details (return_id, item_id, lot_number, expiry_date, quantity_returned, remarks)
                 VALUES (?,?,?,?,?,?)'
            )->execute([
                $returnId,
                $itemId,
                $lotNumber,
                return_nullable_date($line['expiry_date'] ?? null),
                $qty,
                trim((string) ($line['remarks'] ?? '')),
            ]);
        }

        log_activity('CREATE', 'Department Returns', 'Created return ' . $returnNo . ' from ' . $departmentName);
        db()->commit();
        cssrRememberSubmission('return-create',$submissionPayload,['ok'=>true]);
        set_flash('success', 'Department return submitted: ' . $returnNo);
        redirect('department_returns.php');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        set_flash('danger', safe_error_message($e));
        redirect('department_returns.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['receive', 'delete'], true)) {
    $returnId = (int) ($_POST['return_id'] ?? 0);
    $postEmbed = ($_POST['embed'] ?? '') === '1';
    $postReturnPage = ($_POST['return_page'] ?? '') === 'request_queue.php' ? 'request_queue.php' : 'department_returns.php';
    try {
        if (!$canReceiveReturn) {
            throw new RuntimeException('Only Admin or CSSR staff can receive department returns.');
        }
        db()->beginTransaction();
        $stmt = db()->prepare('SELECT * FROM department_returns WHERE id=? FOR UPDATE');
        $stmt->execute([$returnId]);
        $return = $stmt->fetch();
        if (!$return || $return['status'] !== 'Pending') {
            throw new RuntimeException('Pending return was not found.');
        }

        if ($_POST['action'] === 'delete') {
            db_statement(
                'UPDATE department_returns SET status="Deleted", deleted_by=?, deleted_at=NOW(), remarks=? WHERE id=?',
                [current_user()['full_name'], trim((string) ($_POST['status_remarks'] ?? $return['remarks'])), $returnId]
            );
            log_activity('DELETE', 'Department Returns', 'Deleted return ' . $return['return_no']);
            db()->commit();
            set_flash('success', 'Department return deleted.');
            if ($postEmbed && $postReturnPage === 'request_queue.php') {
                echo '<script>if(parent&&parent.reloadPendingRequestsWithToast){parent.reloadPendingRequestsWithToast("success","Department return deleted.");}else{location.href="request_queue.php";}</script>';
                exit;
            }
            redirect($postReturnPage);
        }

        $details = db_all(
            'SELECT d.*, i.item_description, i.unit, i.category, i.current_stock
             FROM department_return_details d
             JOIN items i ON i.id=d.item_id
             WHERE d.return_id=?',
            [$returnId]
        );
        if (!$details) {
            throw new RuntimeException('This return has no items.');
        }

        foreach ($details as $line) {
            $qty = (int) $line['quantity_returned'];
            if ($qty <= 0) {
                continue;
            }
            $lotNumber = trim((string) ($line['lot_number'] ?? '')) ?: 'RETURN';
            $expiryDate = return_nullable_date($line['expiry_date'] ?? null);
            $supplier = 'RETURN FROM ' . strtoupper((string) $return['department_name']);
            $storageLocation = 'CSSR';

            $stmt = db()->prepare(
                'SELECT * FROM item_lots
                 WHERE item_id=? AND lot_number=? AND expiry_date <=> ? AND storage_location=? AND status<>"DELETED"
                 ORDER BY id LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([(int) $line['item_id'], $lotNumber, $expiryDate, $storageLocation]);
            $lot = $stmt->fetch();
            if ($lot) {
                $lotId = (int) $lot['id'];
                db_statement(
                    'UPDATE item_lots SET quantity_received=quantity_received+?, quantity_available=quantity_available+?, status="AVAILABLE" WHERE id=?',
                    [$qty, $qty, $lotId]
                );
            } else {
                db_statement(
                    'INSERT INTO item_lots (item_id, lot_number, expiry_date, supplier, storage_location, unit_price, quantity_received, quantity_available, status)
                     VALUES (?,?,?,?,?,0,?,?,"AVAILABLE")',
                    [(int) $line['item_id'], $lotNumber, $expiryDate, $supplier, $storageLocation, $qty, $qty]
                );
                $lotId = (int) db()->lastInsertId();
            }

            $newBalance = (int) $line['current_stock'] + $qty;
            db_statement('UPDATE items SET current_stock=? WHERE id=?', [$newBalance, (int) $line['item_id']]);
            db_statement('UPDATE department_return_details SET quantity_received=? WHERE id=?', [$qty, (int) $line['id']]);
            db_statement(
                'INSERT INTO bin_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, department, encoded_by, remarks)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [(int) $line['item_id'], (string) $return['return_date'], (string) $return['return_no'], 'DEPARTMENT RETURN', $lotNumber, $expiryDate, $qty, 0, $newBalance, (string) $return['department_name'], current_user()['full_name'], trim((string) ($_POST['status_remarks'] ?? $return['remarks']))]
            );
            db_statement(
                'INSERT INTO stock_cards (item_id, transaction_date, reference_no, transaction_type, lot_number, expiry_date, received_qty, issued_qty, balance_qty, from_location, to_location, encoded_by, remarks)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [(int) $line['item_id'], date('Y-m-d H:i:s'), (string) $return['return_no'], 'DEPARTMENT RETURN', $lotNumber, $expiryDate, $qty, 0, $newBalance, (string) $return['department_name'], 'CSSR', current_user()['full_name'], trim((string) ($_POST['status_remarks'] ?? $return['remarks']))]
            );
        }

        db_statement(
            'UPDATE department_returns SET status="Received", received_by=?, received_at=NOW(), remarks=? WHERE id=?',
            [current_user()['full_name'], trim((string) ($_POST['status_remarks'] ?? $return['remarks'])), $returnId]
        );
        log_activity('RECEIVE', 'Department Returns', 'Received return ' . $return['return_no']);
        db()->commit();
        set_flash('success', 'Department return received and stock was restored.');
        if ($postEmbed && $postReturnPage === 'request_queue.php') {
            echo '<script>if(parent&&parent.reloadPendingRequestsWithToast){parent.reloadPendingRequestsWithToast("success","Department return received.");}else{location.href="request_queue.php";}</script>';
            exit;
        }
        redirect('department_returns.php?view=' . $returnId . '&print=1');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        set_flash('danger', safe_error_message($e));
        redirect('department_returns.php?view=' . $returnId . ($postReturnPage === 'request_queue.php' ? '&return=request_queue.php' : '') . ($postEmbed ? '&embed=1' : ''));
    }
}

$items = db_all("SELECT id, item_code, item_description, unit, item_image FROM items WHERE status='Active' ORDER BY item_description");
$lotRows = db_all(
    'SELECT dep.id department_id, issued.item_id, issued.lot_number, issued.expiry_date,
            GREATEST(0,SUM(issued.issued_qty)-COALESCE((SELECT SUM(rd.quantity_returned) FROM department_returns rr JOIN department_return_details rd ON rd.return_id=rr.id WHERE rr.status<>"Deleted" AND rr.department_id=dep.id AND rd.item_id=issued.item_id AND UPPER(TRIM(rd.lot_number)) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(issued.lot_number)) COLLATE utf8mb4_unicode_ci),0)) returnable_quantity,
            COALESCE((SELECT SUM(il.quantity_available) FROM item_lots il WHERE il.item_id=issued.item_id AND UPPER(TRIM(il.lot_number)) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(issued.lot_number)) COLLATE utf8mb4_unicode_ci AND il.storage_location="CSSR" AND il.status<>"DELETED"),0) cssr_balance,
            (SELECT il2.id FROM item_lots il2 WHERE il2.item_id=issued.item_id AND UPPER(TRIM(il2.lot_number)) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(issued.lot_number)) COLLATE utf8mb4_unicode_ci ORDER BY il2.id DESC LIMIT 1) lot_id,
            (SELECT il2.lot_image FROM item_lots il2 WHERE il2.item_id=issued.item_id AND UPPER(TRIM(il2.lot_number)) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(issued.lot_number)) COLLATE utf8mb4_unicode_ci AND NULLIF(TRIM(il2.lot_image),"") IS NOT NULL ORDER BY il2.id DESC LIMIT 1) lot_image
     FROM departments dep
     JOIN (
        SELECT so.department department_name,so.item_id,so.lot_number,il.expiry_date,so.quantity_issued issued_qty FROM stock_out so LEFT JOIN item_lots il ON il.id=so.item_lot_id WHERE TRIM(COALESCE(so.lot_number,""))<>""
        UNION ALL
        SELECT r.department_name,d.item_id,il.lot_number,il.expiry_date,d.quantity_released FROM item_requests r JOIN item_request_details d ON d.request_id=r.id JOIN item_lots il ON il.id=d.approved_item_lot_id WHERE r.status="Released" AND d.quantity_released>0
     ) issued ON UPPER(TRIM(issued.department_name)) COLLATE utf8mb4_unicode_ci=UPPER(TRIM(dep.department_name)) COLLATE utf8mb4_unicode_ci
     WHERE dep.status="Active"
     GROUP BY dep.id,issued.item_id,issued.lot_number,issued.expiry_date
     HAVING returnable_quantity>0
     ORDER BY dep.id,issued.item_id,issued.expiry_date IS NULL,issued.expiry_date,issued.lot_number'
);
$returnLotIds = array_values(array_unique(array_filter(array_map(static fn($row)=>(int)($row['lot_id']??0),$lotRows))));
$returnLotImageGalleries = [];
if ($returnLotIds) {
    $returnLotPlaceholders = implode(',',array_fill(0,count($returnLotIds),'?'));
    foreach(db_all("SELECT item_lot_id,image_name FROM item_lot_images WHERE item_lot_id IN ($returnLotPlaceholders) ORDER BY item_lot_id,sort_order,id",$returnLotIds) as $imageRow) $returnLotImageGalleries[(int)$imageRow['item_lot_id']][]=(string)$imageRow['image_name'];
}
$lotsByItem = [];
foreach ($lotRows as $lotRow) {
    $lotsByItem[(int) $lotRow['department_id']][(int) $lotRow['item_id']][] = [
        'lot_number' => (string) $lotRow['lot_number'],
        'expiry_date' => (string) ($lotRow['expiry_date'] ?? ''),
        'returnable_quantity' => (int) $lotRow['returnable_quantity'],
        'cssr_balance' => (int) $lotRow['cssr_balance'],
        'lot_image' => (string) ($lotRow['lot_image'] ?? ''),
        'lot_images' => $returnLotImageGalleries[(int)($lotRow['lot_id'] ?? 0)] ?? [],
    ];
}
foreach ($items as &$item) {
    $item['lots_by_department'] = [];
    foreach ($lotsByItem as $departmentId => $departmentLots) {
        if (!empty($departmentLots[(int) $item['id']])) {
            $item['lots_by_department'][(string) $departmentId] = $departmentLots[(int) $item['id']];
        }
    }
}
unset($item);
$departments = db_all("SELECT id, department_name FROM departments WHERE status='Active' ORDER BY department_name");
$previewReturnNo = next_department_return_no(cssrTransactionToday());
$returns = db_all(
    'SELECT r.*, COALESCE(SUM(d.quantity_returned),0) total_qty, COUNT(d.id) line_count
     FROM department_returns r
     LEFT JOIN department_return_details d ON d.return_id=r.id
     WHERE r.status<>"Deleted"
     GROUP BY r.id
     ORDER BY r.created_at DESC
     LIMIT 80'
);
$selectedReturn = null;
$selectedDetails = [];
if (!empty($_GET['view'])) {
    $selectedReturn = db_one('SELECT * FROM department_returns WHERE id=?', [(int) $_GET['view']]);
    if ($selectedReturn) {
        $selectedDetails = db_all(
            'SELECT d.*, i.item_code, i.item_description, i.unit, i.item_image
             FROM department_return_details d
             JOIN items i ON i.id=d.item_id
             WHERE d.return_id=?
             ORDER BY i.item_description, d.id',
            [(int) $selectedReturn['id']]
        );
    }
}

$bodyClass = trim(
    (($selectedReturn && ($_GET['print'] ?? '') === '1') ? 'request-voucher-printing ' : '')
    . ($selectedReturn ? 'request-detail-open' : '')
);
include __DIR__ . '/../includes/header.php';
?>
<?php if ($embedReturn): ?>
<style>
html,body,.app-shell,.main-content{background:transparent!important}
.sidebar,.topbar,.page-heading,.department-return-shell,.mobile-cart-bar,.mobile-cart-drawer{display:none!important}
.main-content{margin:0!important;padding:0!important}
.request-modal{display:block!important;background:rgba(15,23,42,.42)}
</style>
<?php endif; ?>
<?php if (!$embedReturn): ?>
<section class="request-shell department-return-shell">
    <div class="request-left page-panel">
        <div class="pos-panel-title">
            <div><span class="eyebrow">Department Return</span><h3>Create Return</h3></div>
            <span class="pos-count">Multi-item</span>
        </div>
        <form id="returnForm" method="post" data-confirm="custom">
            <?= csrf_field() ?><?= cssrSubmissionField() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="cart_json" id="returnCartJson">
            <div class="row pos-fields">
                <div class="col-md-6 mb-2"><label class="form-label">Return No.</label><input class="form-control" id="returnNoPreview" value="<?= e($previewReturnNo) ?>" readonly></div>
                <div class="col-md-6 mb-2"><label class="form-label" for="returnDate">Return Date</label><input class="form-control" id="returnDate" type="date" name="return_date" value="<?= e(cssrTransactionToday()) ?>" <?= cssrCanEditTransactionDate(current_user() ?? []) ? '' : 'readonly aria-readonly="true"' ?> required></div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Department</label>
                    <?php if (can_manage()): ?>
                    <select class="form-select" id="returnDepartment" name="department_id" required>
                        <option value="">Select department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= (int) $department['id'] ?>" <?= (int) $department['id'] === $currentDepartmentId ? 'selected' : '' ?>><?= e($department['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" id="returnDepartment" name="department_id" value="<?= (int) $currentDepartmentId ?>">
                    <input class="form-control" value="<?= e($currentDepartmentName) ?>" readonly>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-2"><label class="form-label">Returned By</label><input class="form-control" value="<?= e(current_user()['full_name']) ?>" readonly></div>
                <div class="col-md-6 mb-2"><label class="form-label">Reason</label><input class="form-control" name="reason" required></div>
                <div class="col-md-6 mb-2"><label class="form-label">Remarks</label><input class="form-control" name="remarks"></div>
            </div>
            <input id="returnSearch" class="form-control pos-search" placeholder="Search item name">
            <div id="returnItemList" class="pos-item-list"></div>
        </form>
    </div>
    <div class="request-right page-panel">
        <div class="pos-panel-title">
            <div><span class="eyebrow">Return Cart</span><h3>Returned Items</h3></div>
            <span class="pos-count">Selected</span>
        </div>
        <div id="returnCartList" class="pos-cart-list"></div>
        <div class="pos-total-bar">
            <div><span>Lines</span><strong id="returnLines">0</strong></div>
            <div><span>Total Qty</span><strong id="returnQty">0</strong></div>
            <button class="btn btn-light" type="button" onclick="clearReturnCart()">Clear Cart</button>
            <button class="btn btn-primary" type="submit" form="returnForm">Submit Return</button>
        </div>
    </div>
</section>

<div id="returnSummaryModal" class="app-modal">
    <div class="app-modal-card">
        <div class="app-modal-head">
            <div><span class="eyebrow">Return Summary</span><h3>Confirm Returned Items</h3></div>
            <button class="btn btn-light btn-sm" type="button" data-modal-close>Close</button>
        </div>
        <div class="app-modal-body">
            <div class="table-responsive">
                <table class="table" data-enhanced="true">
                    <thead><tr><th>Code</th><th>Description</th><th>Lot Number</th><th>Returned</th></tr></thead>
                    <tbody id="returnSummaryRows"></tbody>
                </table>
            </div>
            <div class="summary-pager" id="returnSummaryPager"></div>
            <div class="pos-total-bar">
                <div><span>Total Lines</span><strong id="returnSummaryLines">0</strong></div>
                <div><span>Total Qty</span><strong id="returnSummaryQty">0</strong></div>
                <button class="btn btn-primary" type="button" id="confirmReturnSubmit">Confirm and Submit</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php if ($selectedReturn): ?>
<div id="departmentReturnModal" class="request-modal open <?= $embedReturn ? 'embedded-request-modal' : '' ?> <?= ($selectedReturn['status'] === 'Received' && ($_GET['print'] ?? '') === '1') ? 'print-auto-hidden' : '' ?>">
    <div class="request-modal-panel">
        <div class="request-modal-head no-print">
            <div>
                <span class="eyebrow">Return Details</span>
                <h3><?= e($selectedReturn['return_no']) ?> <span class="badge request-status <?= e(strtolower($selectedReturn['status'])) ?>"><?= e($selectedReturn['status']) ?></span></h3>
            </div>
            <div class="request-modal-actions">
                <?php if ($selectedReturn['status'] === 'Received'): ?><button class="btn btn-light" type="button" onclick="printReturnAndClose('<?= e($returnPage) ?>',<?= $embedReturn ? 'true' : 'false' ?>)">Print</button><?php endif; ?>
                <?php if ($embedReturn): ?>
                    <button class="btn btn-light" type="button" onclick="parent.closeRequestHistoryDetail ? parent.closeRequestHistoryDetail() : parent.closePendingRequestDetail()">Close</button>
                <?php else: ?>
                    <a class="btn btn-light" href="<?= e($returnPage) ?>">Close</a>
                <?php endif; ?>
            </div>
        </div>
        <?php nursingRenderWorkflow('return', (string)$selectedReturn['status']); ?>
        <div class="request-detail">
            <div class="receipt-meta request-screen-meta">
                <span><strong>From:</strong> <?= e($selectedReturn['department_name']) ?></span>
                <span><strong>To:</strong> CSSR</span>
                <span><strong>Returned By:</strong> <?= e($selectedReturn['returned_by']) ?></span>
                <span><strong>Date:</strong> <?= e($selectedReturn['return_date']) ?></span>
            </div>
            <form method="post" id="departmentReturnReceiveForm" data-confirm="custom">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="receive">
                <input type="hidden" name="return_id" value="<?= (int) $selectedReturn['id'] ?>">
                <input type="hidden" name="return_page" value="<?= e($returnPage) ?>">
                <input type="hidden" name="embed" value="<?= $embedReturn ? '1' : '0' ?>">
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
                                    <span><strong>From:</strong> <?= e($selectedReturn['department_name']) ?></span>
                                    <span><strong>To:</strong> CSSR</span>
                                    <span><strong>Returned By:</strong> <?= e($selectedReturn['returned_by']) ?></span>
                                    <span><strong>Return No.:</strong> <?= e($selectedReturn['return_no']) ?></span>
                                </div>
                            </th></tr>
                            <tr><th class="no-print">Picture</th><th class="no-print">Code</th><th>Description</th><th>Unit</th><th>Lot Number</th><th>Return Qty</th><th>Received Qty</th></tr>
                        </thead>
                        <tbody>
                        <?php $itemsPerPrintPage = 17; $totalPrintItems = count($selectedDetails); ?>
                        <?php foreach ($selectedDetails as $detailIndex => $detail): ?>
                            <tr class="request-item-row <?= (($detailIndex + 1) % $itemsPerPrintPage === 0 && ($detailIndex + 1) < $totalPrintItems) ? 'request-page-break-after' : '' ?>">
                                <td class="no-print"><?php if (!empty($detail['item_image'])): ?><img class="request-detail-picture" src="../uploads/items/<?= e($detail['item_image']) ?>" alt="<?= e($detail['item_description']) ?>" onclick="openImage(this.src)" role="button" tabindex="0"><?php else: ?><span class="request-detail-picture-empty">IMG</span><?php endif; ?></td>
                                <td class="no-print"><?= e($detail['item_code']) ?></td>
                                <td><?= e($detail['item_description']) ?></td>
                                <td><?= e($detail['unit'] ?: 'N/A') ?></td>
                                <td><?= e($detail['lot_number'] ?: 'RETURN') ?></td>
                                <td><?= (int) $detail['quantity_returned'] ?></td>
                                <td><?= $selectedReturn['status'] === 'Pending' ? (int) $detail['quantity_returned'] : (int) $detail['quantity_received'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php
                            $completedPrintPages = intdiv($totalPrintItems, $itemsPerPrintPage);
                            $itemsOnLastPrintPage = $totalPrintItems % $itemsPerPrintPage;
                            $printRowTarget = $totalPrintItems === 0 ? 0 : ($completedPrintPages * $itemsPerPrintPage) + ($itemsOnLastPrintPage > 0 ? $itemsPerPrintPage : 0);
                        ?>
                        <?php for ($blankRow = $totalPrintItems; $blankRow < $printRowTarget; $blankRow++): ?>
                            <tr class="request-print-blank-row print-only"><td colspan="5">&nbsp;</td></tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($selectedReturn['status'] === 'Pending'): ?>
                    <div class="request-actions no-print">
                        <input class="form-control" name="status_remarks" placeholder="Receiving remarks" value="<?= e($selectedReturn['remarks']) ?>">
                        <button class="btn btn-primary" type="submit">Receive Return</button>
                        <button class="btn btn-danger" type="submit" form="departmentReturnDeleteForm">Delete Return</button>
                    </div>
                <?php endif; ?>
            </form>
            <?php if ($selectedReturn['status'] === 'Pending'): ?>
                <form method="post" id="departmentReturnDeleteForm" data-confirm="custom">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="return_id" value="<?= (int) $selectedReturn['id'] ?>">
                    <input type="hidden" name="return_page" value="<?= e($returnPage) ?>">
                    <input type="hidden" name="embed" value="<?= $embedReturn ? '1' : '0' ?>">
                    <input type="hidden" name="status_remarks" value="<?= e($selectedReturn['remarks']) ?>">
                </form>
            <?php endif; ?>
            <?php if ($selectedReturn['status'] === 'Received'): ?>
                <div class="request-prepared-by print-only">
                    <span>Prepared By:</span>
                    <strong><?= e($selectedReturn['received_by'] ?: 'N/A') ?></strong>
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
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$embedReturn): ?>
<div id="imageModal" class="modal-backdrop" aria-hidden="true"><div class="modal-box image-gallery-modal" onclick="event.stopPropagation()"><div class="image-gallery-heading"><span>CSSR ITEM MEDIA</span><strong>Lot Image Preview</strong></div><button type="button" class="btn btn-light image-gallery-close" data-image-modal-close aria-label="Close image preview">&times;</button><button type="button" class="image-gallery-nav prev" data-gallery-prev aria-label="Previous image">&#8249;</button><img class="detail-img" src="" alt="Lot picture"><button type="button" class="image-gallery-nav next" data-gallery-next aria-label="Next image">&#8250;</button><div class="image-gallery-counter" data-gallery-counter></div></div></div>
<script>
const returnItems = <?= json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const returnCart = new Map();
const returnSummaryPageSize = 15;
let returnSummaryPage = 1;
function showReturnCartToast(type,message){
    document.querySelectorAll('.app-toast.return-cart-toast').forEach(toast=>toast.remove());
    showToastMessage(type,message);
    const toast=document.querySelector('.toast-stack .app-toast:last-child');
    if(toast)toast.classList.add('return-cart-toast');
}
function returnEsc(value){return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
function returnLotForImage(item){
    const lots=Array.isArray(item.lots)?item.lots:returnLotsForItem(item);
    return lots.find(lot=>String(lot.lot_number)===String(item.lot_number||''))||lots[0]||null;
}
function previewReturnImage(event,itemId){
    event.stopPropagation();
    const item=returnCart.get(Number(itemId))||returnItems.find(row=>Number(row.id)===Number(itemId));
    if(!item)return;
    const lot=returnLotForImage(item);
    const images=lot&&Array.isArray(lot.lot_images)&&lot.lot_images.length?lot.lot_images:[(lot&&lot.lot_image)||item.item_image].filter(Boolean);
    if(images.length)openImageGallery(images.map(image=>'../uploads/items/'+image),0);
}
function returnImage(item, cls='pos-item-img'){
    const lot=returnLotForImage(item);
    const image=(lot&&Array.isArray(lot.lot_images)&&lot.lot_images[0])||(lot&&lot.lot_image)||item.item_image;
    return image ? `<img class="${cls}" src="../uploads/items/${returnEsc(image)}" alt="${returnEsc(item.item_description)}" title="Click to view the lot picture" role="button" tabindex="0" onclick="previewReturnImage(event,${Number(item.id)})" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();previewReturnImage(event,${Number(item.id)})}">` : `<div class="${cls} pos-img-empty">IMG</div>`;
}
function selectedReturnDepartment(){return String(document.getElementById('returnDepartment')?.value||'');}
function returnLotsForItem(item){return (item.lots_by_department&&item.lots_by_department[selectedReturnDepartment()])||[];}
function renderReturnItems(){
    const q=document.getElementById('returnSearch').value.toLowerCase().trim();
    const list=document.getElementById('returnItemList');
    const departmentId=selectedReturnDepartment();
    if(!departmentId){list.innerHTML='<div class="pos-empty">Select a department to view its previously issued items.</div>';return;}
    const filtered=returnItems.filter(item => returnLotsForItem(item).length>0&&[item.item_code,item.item_description].join(' ').toLowerCase().includes(q));
    list.innerHTML=filtered.map(item => `<button type="button" class="pos-item-card" onclick="addReturnItem(${Number(item.id)})">${returnImage(item)}<span class="pos-item-main"><strong>${returnEsc(item.item_description)}</strong><small>Previously issued to this department</small></span></button>`).join('') || '<div class="pos-empty">No returnable issued items found for this department.</div>';
}
function addReturnItem(id){
    const item=returnItems.find(row => Number(row.id)===Number(id));
    if(!item)return;
    if(returnCart.has(id)){
        showReturnCartToast('info','Item is already in the cart.');
        return;
    }
    const lots=returnLotsForItem(item);
    const defaultLot=lots.length===1 ? lots[0] : null;
    returnCart.set(id,{...item,lots,qty:1,lot_number:defaultLot ? defaultLot.lot_number : '',expiry_date:defaultLot ? defaultLot.expiry_date : '',max_returnable:defaultLot ? Number(defaultLot.returnable_quantity) : 0,remarks:''});
    renderReturnCart();
    showReturnCartToast('success',String(item.item_description||'Item')+' was added to the return cart.');
}
function updateReturnItem(id,field,value){
    const item=returnCart.get(id);
    if(!item)return;
    item[field]=field==='qty'?Math.max(1,Number(value||1)):value;
    returnCart.set(id,item);
    renderReturnTotals();
    syncReturnCart();
}
function updateReturnLot(id,value){
    const item=returnCart.get(id);
    if(!item)return;
    const lot=(item.lots||[]).find(row => String(row.lot_number)===String(value));
    item.lot_number=value||'';
    item.expiry_date=lot ? (lot.expiry_date||'') : '';
    item.max_returnable=lot ? Number(lot.returnable_quantity||0) : 0;
    if(item.max_returnable>0&&Number(item.qty)>item.max_returnable)item.qty=item.max_returnable;
    returnCart.set(id,item);
    renderReturnCart();
}
function removeReturnItem(id){returnCart.delete(id);renderReturnCart();}
function clearReturnCart(){returnCart.clear();renderReturnCart();}
function syncReturnCart(){
    document.getElementById('returnCartJson').value=JSON.stringify(Array.from(returnCart.values()).map(row => ({
        id:Number(row.id),
        qty:Number(row.qty),
        lot_number:row.lot_number||'',
        expiry_date:row.expiry_date||'',
        remarks:row.remarks||''
    })));
}
function renderReturnTotals(){
    const rows=Array.from(returnCart.values());
    document.getElementById('returnLines').textContent=rows.length;
    document.getElementById('returnQty').textContent=rows.reduce((sum,row)=>sum+Number(row.qty||0),0);
}
function renderReturnCart(){
    const list=document.getElementById('returnCartList');
    const rows=Array.from(returnCart.values());
    list.innerHTML=rows.map(item => {
    const lots=Array.isArray(item.lots)?item.lots:[];
    const lotOptions=['<option value="">Select lot</option>'].concat(lots.map(lot => {
        const status=Number(lot.cssr_balance)>0?'Available':'Previously issued';
        const label=[lot.lot_number,lot.expiry_date ? `Exp: ${lot.expiry_date}` : '',`CSSR balance: ${Number(lot.cssr_balance||0)}`,status,`Returnable: ${Number(lot.returnable_quantity||0)}`].filter(Boolean).join(' | ');
        return `<option value="${returnEsc(lot.lot_number)}" ${String(item.lot_number||'')===String(lot.lot_number)?'selected':''}>${returnEsc(label)}</option>`;
    })).join('');
    return `<article class="pos-cart-row return-cart-row">
        ${returnImage(item,'pos-cart-img')}
        <div class="pos-cart-info"><small>Returned Item</small><strong>${returnEsc(item.item_description)}</strong></div>
        <select class="form-select return-cart-lot" aria-label="Lot number for ${returnEsc(item.item_description)}" onchange="updateReturnLot(${Number(item.id)},this.value)">${lotOptions}</select>
        <input class="form-control return-cart-qty" type="number" min="1" ${Number(item.max_returnable)>0?`max="${Number(item.max_returnable)}"`:''} value="${Number(item.qty)}" aria-label="Return quantity for ${returnEsc(item.item_description)}" oninput="updateReturnItem(${Number(item.id)},'qty',this.value)" required>
        <button type="button" class="btn btn-light btn-sm" onclick="removeReturnItem(${Number(item.id)})">Remove</button>
    </article>`;
    }).join('') || '<div class="pos-empty">Cart is empty. Add returned items from the list.</div>';
    renderReturnTotals();
    syncReturnCart();
}
document.getElementById('returnSearch').addEventListener('input',renderReturnItems);
document.getElementById('returnDepartment')?.addEventListener('change',function(){returnCart.clear();renderReturnCart();renderReturnItems();});
document.getElementById('returnDate').addEventListener('change',function(){
    fetch('department_returns.php?next_return_no='+encodeURIComponent(this.value),{headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(response=>response.json())
        .then(data=>{if(data&&data.return_no)document.getElementById('returnNoPreview').value=data.return_no;})
        .catch(()=>{});
});
document.getElementById('returnForm').addEventListener('submit',function(event){
    if(returnCart.size===0){
        event.preventDefault();
        showToastMessage('warning','Add at least one returned item.');
        return;
    }
    event.preventDefault();
    showReturnSummary();
});
function renderReturnSummaryPage(){
    const rows=Array.from(returnCart.values());
    const totalPages=Math.max(1,Math.ceil(rows.length/returnSummaryPageSize));
    returnSummaryPage=Math.min(Math.max(1,returnSummaryPage),totalPages);
    const pageRows=rows.slice((returnSummaryPage-1)*returnSummaryPageSize,returnSummaryPage*returnSummaryPageSize);
    const totalQty=rows.reduce((sum,row)=>sum+Number(row.qty||0),0);
    document.getElementById('returnSummaryRows').innerHTML=pageRows.map(item=>`<tr><td><strong>${returnEsc(item.item_code)}</strong></td><td>${returnEsc(item.item_description)}</td><td>${returnEsc(item.lot_number||'RETURN')}</td><td>${Number(item.qty)}</td></tr>`).join('');
    document.getElementById('returnSummaryLines').textContent=rows.length;
    document.getElementById('returnSummaryQty').textContent=totalQty;
    document.getElementById('returnSummaryPager').innerHTML=rows.length>returnSummaryPageSize?`<span>Page ${returnSummaryPage} of ${totalPages}</span><div><button class="btn btn-light btn-sm" type="button" ${returnSummaryPage===1?'disabled':''} onclick="returnSummaryPage--;renderReturnSummaryPage()">Previous</button><button class="btn btn-light btn-sm" type="button" ${returnSummaryPage===totalPages?'disabled':''} onclick="returnSummaryPage++;renderReturnSummaryPage()">Next</button></div>`:'';
}
function showReturnSummary(){
    returnSummaryPage=1;
    renderReturnSummaryPage();
    document.getElementById('returnSummaryModal').classList.add('open');
}
document.getElementById('confirmReturnSubmit').addEventListener('click',function(){
    showConfirm('Submit this department return?',function(){
        document.getElementById('returnSummaryModal').classList.remove('open');
        startLoading('Submitting department return...');
        document.getElementById('returnForm').submit();
    });
});
renderReturnItems();
renderReturnCart();
</script>
<?php endif; ?>
<?php if ($selectedReturn && $selectedReturn['status'] === 'Received'): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    var panel=document.querySelector('#departmentReturnModal .request-modal-panel');
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
<script>
function printReturnAndClose(closeUrl, embedded){
    var didClose=false;
    function closeReturnPrint(){
        if(didClose)return;
        didClose=true;
        if(embedded&&window.parent&&window.parent!==window){
            if(typeof window.parent.closeRequestHistoryDetail==='function'){
                window.parent.closeRequestHistoryDetail();
            }else if(typeof window.parent.closePendingRequestDetail==='function'){
                window.parent.closePendingRequestDetail();
            }
        }else if(closeUrl){
            window.location.href=closeUrl;
        }
    }
    window.addEventListener('afterprint',closeReturnPrint,{once:true});
    window.print();
    setTimeout(closeReturnPrint,1200);
}
</script>
<?php if (!empty($_GET['print']) && $selectedReturn && $selectedReturn['status'] === 'Received'): ?>
<script>window.addEventListener('load',function(){setTimeout(function(){printReturnAndClose('<?= e($returnPage) ?>',<?= $embedReturn ? 'true' : 'false' ?>);},1000);});</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
