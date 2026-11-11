<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_or_cssr();
$pageTitle = 'Released Requests';
$activePage = 'released_requests';

$canViewAllRequests = can_manage();
$currentDepartmentId = current_user()['department_id'] ?? null;
$currentDepartmentName = current_user()['department_name'] ?? null;

$requestWhere = ' WHERE r.status = "Released"';
$requestParams = [];
if (!$canViewAllRequests) {
    $requestWhere .= ' AND r.department_id = ?';
    $requestParams[] = (int) $currentDepartmentId;
}
$stmt = db()->prepare("SELECT r.*, COALESCE(SUM(d.quantity_released),0) total_qty, COUNT(d.id) line_count FROM item_requests r LEFT JOIN item_request_details d ON d.request_id=r.id{$requestWhere} GROUP BY r.id ORDER BY r.released_at DESC, r.created_at DESC LIMIT 120");
$stmt->execute($requestParams);
$requests = $stmt->fetchAll();

$selectedRequest = null;
$selectedDetails = [];
if (!empty($_GET['view'])) {
    $viewSql = 'SELECT * FROM item_requests WHERE id=? AND status="Released"';
    $viewParams = [(int) $_GET['view']];
    if (!$canViewAllRequests) {
        $viewSql .= ' AND department_id=?';
        $viewParams[] = (int) $currentDepartmentId;
    }
    $stmt = db()->prepare($viewSql);
    $stmt->execute($viewParams);
    $selectedRequest = $stmt->fetch();
    if ($selectedRequest) {
        $stmt = db()->prepare('SELECT d.*, i.item_code, i.item_description, i.brand, i.current_stock, i.lot_number, i.expiry_date, i.bin_location FROM item_request_details d JOIN items i ON i.id=d.item_id WHERE d.request_id=? ORDER BY i.item_description');
        $stmt->execute([(int) $selectedRequest['id']]);
        $selectedDetails = $stmt->fetchAll();
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel">
    <div class="pos-panel-title">
        <div>
            <span class="eyebrow">Released Queue</span>
            <h3><?= $canViewAllRequests ? 'All Released Requests' : e($currentDepartmentName ?: 'My Department') . ' Released Requests' ?></h3>
        </div>
        <a class="btn btn-light" href="item_requests.php">Back to Requests</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Request No.</th><th>Department</th><th>Requested By</th><th>Released By</th><th>Released At</th><th>Lines</th><th>Released Qty</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= e($request['request_no']) ?></td>
                    <td><?= e($request['department_name']) ?></td>
                    <td><?= e($request['requested_by']) ?></td>
                    <td><?= e($request['released_by']) ?></td>
                    <td><?= e($request['released_at']) ?></td>
                    <td><?= (int) $request['line_count'] ?></td>
                    <td><?= (int) $request['total_qty'] ?></td>
                    <td><a class="btn btn-sm btn-light" href="?view=<?= (int) $request['id'] ?>#requestModal">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($selectedRequest): ?>
<div id="requestModal" class="request-modal released-request-modal open" role="dialog" aria-modal="true" aria-label="Released request details">
    <div class="request-modal-panel">
        <div class="request-modal-head">
            <div>
                <span class="eyebrow">Released Request Details</span>
                <h3><?= e($selectedRequest['request_no']) ?> <span class="badge request-status released">Released</span></h3>
            </div>
            <div class="request-modal-actions no-print">
                <button class="btn btn-light" onclick="printPage()">Print</button>
                <a class="btn btn-light" href="released_requests.php">Close</a>
            </div>
        </div>
        <div class="request-detail">
            <div class="receipt-meta">
                <span><strong>Department:</strong> <?= e($selectedRequest['department_name']) ?></span>
                <span><strong>Requested By:</strong> <?= e($selectedRequest['requested_by']) ?></span>
                <span><strong>Released By:</strong> <?= e($selectedRequest['released_by']) ?></span>
                <span><strong>Released At:</strong> <?= e($selectedRequest['released_at']) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Code</th><th>Description</th><th>Brand</th><th>Lot</th><th>Requested</th><th>Released</th></tr></thead>
                    <tbody><?php foreach ($selectedDetails as $detail): ?><tr><td><?= e($detail['item_code']) ?></td><td><?= e($detail['item_description']) ?></td><td><?= e($detail['brand']) ?></td><td><?= e($detail['lot_number']) ?></td><td><?= (int) $detail['quantity_requested'] ?></td><td><?= (int) ($detail['quantity_released'] ?: $detail['quantity_approved']) ?></td></tr><?php endforeach; ?></tbody>
                </table>
            </div>
            <p class="receipt-remarks"><strong>Remarks:</strong> <?= e($selectedRequest['remarks']) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
