<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles([ROLE_ADMIN, ROLE_HEAD, ROLE_STAFF]);

$pageTitle = 'Request History';
$activePage = 'request_history';
$canViewAllRequests = shared_has_permission('cssr_inventory', 'edit') || shared_has_permission('ris_requests', 'approve');
$canViewStorageHistory = shared_has_permission('cssr_inventory', 'view') || shared_has_permission('cssr_inventory', 'create') || shared_has_permission('cssr_inventory', 'edit');
$currentDepartmentId = current_user()['department_id'] ?? null;
$currentDepartmentName = current_user()['department_name'] ?? null;

$where = [$canViewAllRequests
    ? 'r.status IN ("Approved","Released","Cancelled","Deleted","Rejected")'
    : 'r.status IN ("Pending","Approved","Released","Cancelled","Deleted","Rejected")'];
$params = [];
if (!$canViewAllRequests) {
    $where[] = 'r.department_id = ?';
    $params[] = (int) $currentDepartmentId;
}

$sql = 'SELECT r.id request_id, r.request_no, r.department_name, r.request_date, r.status, r.created_at,
               COUNT(d.id) AS line_count,
               COALESCE(SUM(d.quantity_requested),0) AS total_requested,
               COALESCE(SUM(d.quantity_approved),0) AS total_approved,
               COALESCE(SUM(d.quantity_released),0) AS total_released
        FROM item_requests r
        JOIN item_request_details d ON d.request_id = r.id
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY r.id, r.request_no, r.department_name, r.request_date, r.status, r.created_at
        ORDER BY r.created_at DESC
        LIMIT 500';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$requestHistoryRows = $stmt->fetchAll();

if ($canViewStorageHistory) {
    $storageHistoryRows = db_all(
        'SELECT r.id request_id, r.request_no, CONCAT(r.from_location, " -> ", r.to_location) department_name, r.request_date, r.status,
                COUNT(d.id) AS line_count,
                COALESCE(SUM(d.requested_qty),0) AS total_requested,
                COALESCE(SUM(d.released_qty),0) AS total_released,
                r.created_at,
                "storage" AS request_type
         FROM storage_requests r
         JOIN storage_request_details d ON d.request_id = r.id
         WHERE r.status IN ("Released","Deleted")
         GROUP BY r.id, r.request_no, r.from_location, r.to_location, r.request_date, r.status, r.created_at
         ORDER BY r.created_at DESC
         LIMIT 500'
    );
    $returnHistoryRows = db_all(
        'SELECT r.id request_id, r.return_no request_no, CONCAT(r.department_name, " -> CSSR") department_name, r.return_date request_date, r.status,
                COUNT(d.id) AS line_count,
                COALESCE(SUM(d.quantity_returned),0) AS total_requested,
                COALESCE(SUM(d.quantity_received),0) AS total_released,
                r.created_at,
                "return" AS request_type
         FROM department_returns r
         JOIN department_return_details d ON d.return_id = r.id
         WHERE r.status IN ("Received","Deleted")
         GROUP BY r.id, r.return_no, r.department_name, r.return_date, r.status, r.created_at
         ORDER BY r.created_at DESC
         LIMIT 500'
    );
    foreach ($requestHistoryRows as &$historyRow) {
        $historyRow['request_type'] = 'ris';
        $historyRow['created_at'] = $historyRow['created_at'] ?? null;
    }
    unset($historyRow);
    $requestHistoryRows = array_merge($requestHistoryRows, $storageHistoryRows, $returnHistoryRows);
    usort($requestHistoryRows, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    $requestHistoryRows = array_slice($requestHistoryRows, 0, 500);
} else {
    foreach ($requestHistoryRows as &$historyRow) {
        $historyRow['request_type'] = 'ris';
    }
    unset($historyRow);
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel no-print">
    <div class="pos-panel-title">
        <div>
            <span class="eyebrow">Request History</span>
            <h3><?= $canViewAllRequests ? 'All Department Requests' : e($currentDepartmentName ?: 'My Department') . ' Request History' ?></h3>
        </div>
        <?php if (can_manage()): ?><button class="btn btn-light" type="button" onclick="printBlankRequestFormat()">Print Blank Format</button><?php endif; ?>
    </div>
    <div class="table-responsive request-history-scroll" tabindex="0" aria-label="Request history. Swipe or scroll sideways to view all details.">
        <table class="table request-history-table" data-page-size="10">
            <thead><tr><th>Request No.</th><th>Department</th><th>Date</th><th>Items</th><th>Total Requested</th><th>Total Released</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($requestHistoryRows as $request): ?>
                <tr>
                    <td><strong><?= e($request['request_no']) ?></strong></td>
                    <td><?= e($request['department_name']) ?></td>
                    <td><?= e($request['request_date']) ?></td>
                    <td><?= (int) $request['line_count'] ?></td>
                    <td><?= (int) $request['total_requested'] ?></td>
                    <td><?= (int) $request['total_released'] ?></td>
                    <td><span class="badge request-status <?= e(strtolower($request['status'])) ?>"><?= e($request['status']) ?></span></td>
                    <td class="request-list-actions">
                        <?php if (($request['request_type'] ?? 'ris') === 'storage'): ?>
                            <?php if ($canViewAllRequests): ?><a class="btn btn-sm btn-light" onclick="return openRequestHistoryDetail(this.href)" href="storage_requests.php?view=<?= (int) $request['request_id'] ?>&return=request_history.php&embed=1#storageRequestRelease">View</a><?php endif; ?>
                            <?php if ($request['status'] === 'Released'): ?><a class="btn btn-sm btn-primary" onclick="return printRequestFromHistory(this.href)" href="storage_requests.php?view=<?= (int) $request['request_id'] ?>&print=1&return=request_history.php#storageRequestRelease">Print</a><?php endif; ?>
                        <?php elseif (($request['request_type'] ?? 'ris') === 'return'): ?>
                            <a class="btn btn-sm btn-light" onclick="return openRequestHistoryDetail(this.href)" href="department_returns.php?view=<?= (int) $request['request_id'] ?>&return=request_history.php&embed=1#departmentReturnModal">View</a>
                            <?php if ($request['status'] === 'Received'): ?><a class="btn btn-sm btn-primary" onclick="return printRequestFromHistory(this.href)" href="department_returns.php?view=<?= (int) $request['request_id'] ?>&print=1&return=request_history.php#departmentReturnModal">Print</a><?php endif; ?>
                        <?php else: ?>
                            <a class="btn btn-sm btn-light" onclick="return openRequestHistoryDetail(this.href)" href="item_requests.php?view=<?= (int) $request['request_id'] ?>&return=request_history.php&embed=1#requestModal">View</a>
                            <?php if (in_array($request['status'], ['Approved', 'Released'], true)): ?><a class="btn btn-sm btn-primary" onclick="return printRequestFromHistory(this.href)" href="item_requests.php?view=<?= (int) $request['request_id'] ?>&print=1&return=request_history.php#requestModal">Print</a><?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requestHistoryRows): ?>
                <tr data-empty-row="true"><td colspan="8" class="text-center">No request history found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
function printRequestFromHistory(url){
    var previous=document.getElementById('requestHistoryPrintFrame');
    if(previous)previous.remove();
    var frame=document.createElement('iframe');
    frame.id='requestHistoryPrintFrame';
    frame.title='Print request';
    frame.style.position='fixed';
    frame.style.width='0';
    frame.style.height='0';
    frame.style.border='0';
    frame.style.visibility='hidden';
    frame.src=url;
    document.body.appendChild(frame);
    setTimeout(function(){frame.remove()},30000);
    return false;
}
function printBlankRequestFormat(){
    return printRequestFromHistory('request_format.php');
}
function openRequestHistoryDetail(url){
    closeRequestHistoryDetail();
    var frame=document.createElement('iframe');
    frame.id='requestHistoryDetailFrame';
    frame.title='Request details';
    frame.setAttribute('allowtransparency','true');
    frame.style.position='fixed';
    frame.style.inset='0';
    frame.style.width='100%';
    frame.style.height='100%';
    frame.style.border='0';
    frame.style.background='transparent';
    frame.style.zIndex='3000';
    frame.src=url;
    document.body.appendChild(frame);
    return false;
}
function closeRequestHistoryDetail(){
    var frame=document.getElementById('requestHistoryDetailFrame');
    if(frame)frame.remove();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
