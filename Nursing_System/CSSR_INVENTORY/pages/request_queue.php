<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Pending Requests & Returns';
$activePage = 'request_queue';

$canViewRis = shared_has_permission('ris_requests', 'view') || can_view_cssr_request_lists();
$canViewStorage = shared_has_permission('cssr_inventory', 'view') || shared_has_permission('cssr_inventory', 'create');

if (!$canViewRis && !$canViewStorage) {
    http_response_code(403);
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger">Access denied for your role.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$risRequests = [];
if ($canViewRis) {
    $risWhere = ' WHERE r.status IN ("Pending","Approved")';
    $risParams = [];
    if (!shared_has_permission('cssr_inventory', 'edit') && !shared_has_permission('ris_requests', 'approve')) {
        $currentDepartmentId = (int) (current_user()['department_id'] ?? 0);
        $risWhere .= ' AND r.department_id = ?';
        $risParams[] = $currentDepartmentId;
    }
    $risRequests = db_all(
        "SELECT r.*, COALESCE(SUM(d.quantity_requested),0) total_qty, COUNT(d.id) line_count
         FROM item_requests r
         LEFT JOIN item_request_details d ON d.request_id=r.id
         {$risWhere}
         GROUP BY r.id
         ORDER BY r.created_at DESC
         LIMIT 80",
        $risParams
    );
}

$storageRequests = [];
if ($canViewStorage) {
    $storageRequests = db_all(
        'SELECT r.*, COALESCE(SUM(d.requested_qty),0) requested_total, COALESCE(SUM(d.released_qty),0) released_total, COUNT(d.id) line_count
         FROM storage_requests r
         LEFT JOIN storage_request_details d ON d.request_id=r.id
         WHERE r.status="Pending"
         GROUP BY r.id
         ORDER BY r.created_at DESC
         LIMIT 80'
    );
}

$departmentReturns = [];
if ($canViewStorage) {
    $departmentReturns = db_all(
        'SELECT r.*, COALESCE(SUM(d.quantity_returned),0) total_qty, COUNT(d.id) line_count
         FROM department_returns r
         LEFT JOIN department_return_details d ON d.return_id=r.id
         WHERE r.status="Pending"
         GROUP BY r.id
         ORDER BY r.created_at DESC
         LIMIT 80'
    );
}

$pendingRequests = [];
foreach ($risRequests as $request) {
    $pendingRequests[] = [
        'type' => 'RIS',
        'id' => (int) $request['id'],
        'request_no' => (string) $request['request_no'],
        'location' => (string) $request['department_name'],
        'requested_by' => (string) $request['requested_by'],
        'request_date' => (string) $request['request_date'],
        'line_count' => (int) $request['line_count'],
        'total_qty' => (int) $request['total_qty'],
        'status' => (string) $request['status'],
        'created_at' => (string) $request['created_at'],
        'href' => 'item_requests.php?view=' . (int) $request['id'] . '&return=request_queue.php&embed=1#requestModal',
    ];
}
foreach ($storageRequests as $request) {
    $pendingRequests[] = [
        'type' => 'Storage',
        'id' => (int) $request['id'],
        'request_no' => (string) $request['request_no'],
        'location' => (string) $request['from_location'] . ' -> ' . (string) $request['to_location'],
        'requested_by' => (string) $request['requested_by'],
        'request_date' => (string) $request['request_date'],
        'line_count' => (int) $request['line_count'],
        'total_qty' => (int) $request['requested_total'],
        'status' => (string) $request['status'],
        'created_at' => (string) $request['created_at'],
        'href' => 'storage_requests.php?view=' . (int) $request['id'] . '&return=request_queue.php&embed=1#storageRequestRelease',
    ];
}
foreach ($departmentReturns as $request) {
    $pendingRequests[] = [
        'type' => 'Return',
        'id' => (int) $request['id'],
        'request_no' => (string) $request['return_no'],
        'location' => (string) $request['department_name'] . ' -> CSSR',
        'requested_by' => (string) $request['returned_by'],
        'request_date' => (string) $request['return_date'],
        'line_count' => (int) $request['line_count'],
        'total_qty' => (int) $request['total_qty'],
        'status' => (string) $request['status'],
        'created_at' => (string) $request['created_at'],
        'href' => 'department_returns.php?view=' . (int) $request['id'] . '&return=request_queue.php&embed=1#departmentReturnModal',
    ];
}
usort($pendingRequests, static function (array $a, array $b): int {
    return strcmp($b['created_at'], $a['created_at']);
});

include __DIR__ . '/../includes/header.php';
?>
<section class="page-panel">
    <div class="pos-panel-title">
        <div><span class="eyebrow">Supply Requests</span><h3>Pending Requests & Returns</h3><p class="text-muted">Department requests awaiting approval or release, storage requests awaiting release, and returns awaiting receipt.</p></div>
        <span class="pos-count"><?= count($risRequests) + count($storageRequests) + count($departmentReturns) ?> Awaiting Action</span>
    </div>
    <div class="summary-grid">
        <div class="summary-card"><span>Department Supply Requests</span><strong><?= count($risRequests) ?></strong><small>Department supply requests pending action</small></div>
        <div class="summary-card green"><span>Storage Requests</span><strong><?= count($storageRequests) ?></strong><small>Storage room requests pending release</small></div>
        <div class="summary-card yellow"><span>Department Returns</span><strong><?= count($departmentReturns) ?></strong><small>Returned items awaiting CSSR receiving</small></div>
    </div>
</section>

<section class="page-panel">
    <h3>Requests and Returns Awaiting Action</h3>
    <div class="table-responsive">
        <table class="table pending-request-table" data-page-size="10">
            <thead><tr><th>Request No.</th><th>Type</th><th>Department / From</th><th>Requested By</th><th>Date</th><th>Lines</th><th>Total Qty</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($pendingRequests as $request): ?>
                <tr>
                    <td><strong><?= e($request['request_no']) ?></strong></td>
                    <td><?= e($request['type']) ?></td>
                    <td><?= e($request['location']) ?></td>
                    <td><?= e($request['requested_by']) ?></td>
                    <td><?= e($request['request_date']) ?></td>
                    <td><?= (int) $request['line_count'] ?></td>
                    <td><?= (int) $request['total_qty'] ?></td>
                    <td><span class="badge request-status <?= e(strtolower($request['status'])) ?>"><?= e($request['status']) ?></span></td>
                    <td><a class="btn btn-sm btn-light" onclick="return openPendingRequestDetail(this.href)" href="<?= e($request['href']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pendingRequests): ?><tr><td colspan="9" class="text-center">No requests for release.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
function openPendingRequestDetail(url){
    closePendingRequestDetail();
    var frame=document.createElement('iframe');
    frame.id='pendingRequestDetailFrame';
    frame.title='Pending request details';
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
function closePendingRequestDetail(){
    var frame=document.getElementById('pendingRequestDetailFrame');
    if(frame)frame.remove();
}
function closeRequestHistoryDetail(){
    closePendingRequestDetail();
}
function showPendingRequestToast(type,message){
    if(typeof showToastMessage==='function'){
        showToastMessage(type||'success',message||'Action completed.');
    }
}
function reloadPendingRequestsWithToast(type,message){
    try{
        sessionStorage.setItem('pendingRequestToast',JSON.stringify({type:type||'success',message:message||'Action completed.'}));
    }catch(error){}
    window.location.reload();
}
document.addEventListener('DOMContentLoaded',function(){
    var pendingToast=null;
    try{
        pendingToast=JSON.parse(sessionStorage.getItem('pendingRequestToast')||'null');
        sessionStorage.removeItem('pendingRequestToast');
    }catch(error){}
    if(pendingToast&&pendingToast.message){
        showPendingRequestToast(pendingToast.type,pendingToast.message);
    }
});
document.addEventListener('keydown',function(event){
    if(event.key==='Escape'){
        closePendingRequestDetail();
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
