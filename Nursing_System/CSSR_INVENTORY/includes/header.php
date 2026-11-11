<?php
require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? 'CSSR Inventory Management System';
$activePage = $activePage ?? '';
$canSeeInventoryAlerts = is_logged_in() && (
    (current_user()['role'] ?? '') === ROLE_ADMIN
    || (current_user()['role'] ?? '') === ROLE_STAFF
    || is_cssr_department()
);
$notificationCounts = ['low' => 0, 'expiry' => 0, 'expired' => 0, 'quarantine' => 0, 'recall' => 0];
if ($canSeeInventoryAlerts) {
    try {
        $notificationCounts['low'] = (int) db_value("SELECT COUNT(*) FROM items WHERE status='Active' AND current_stock <= minimum_stock");
        $notificationCounts['expiry'] = (int) db_value("SELECT COUNT(*) FROM item_lots WHERE quantity_available>0 AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 8 MONTH)");
        $notificationCounts['expired'] = (int) db_value("SELECT COUNT(*) FROM item_lots WHERE quantity_available>0 AND expiry_date<CURDATE()");
        $notificationCounts['quarantine'] = (int) db_value("SELECT COUNT(*) FROM item_lots WHERE quantity_quarantined>0");
        $notificationCounts['recall'] = (int) db_value("SELECT COUNT(*) FROM product_recalls WHERE status='ACTIVE'");
    } catch (Throwable $e) {
        log_error($e, 'NOTIFICATIONS');
        $notificationCounts = ['low' => 0, 'expiry' => 0, 'expired' => 0, 'quarantine' => 0, 'recall' => 0];
    }
}
$notificationTotal = array_sum($notificationCounts);
$philippinesNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$todayLabel = $philippinesNow->format('M d, Y h:i:s A');
$pageDescriptions = [
    'dashboard' => 'Executive inventory overview, daily movement, alerts, and recent masterlist activity.',
    'chat' => 'Message CSSR staff about supply requests and attach supporting item photos.',
    'items' => 'Maintain item records, photos, stock thresholds, bin locations, lots, and expiry details.',
    'stock_in' => 'Receive multiple items, update stock balances, and write receiving entries to bin cards.',
    'get_stock' => 'Pull items from Storage Room rack, shelf, and bin locations into CSSR Available Stock.',
    'storage_requests' => 'Request items from storage rooms for warehouse release into CSSR.',
    'department_returns' => 'Create and receive returned supplies from departments.',
    'storage_masterlist' => 'View item balances by storage room.',
    'price_list' => 'Review delivered item prices by lot, supplier, storage room, and PO/PR number.',
    'cssr_available_stock' => 'View items already pulled from Storage Room and ready for CSSR issuance or use.',
    'stock_out' => 'Issue multiple items, deduct stock safely, and record issuance activity by department.',
    'inventory_adjustment' => 'Review stock variance workflows and controlled adjustment guidance.',
      'item_requests' => 'Create, approve, release, and monitor department supply requests.',
      'request_queue' => 'Release pending RIS requests, storage room requests, and department returns from one queue.',
    'request_history' => 'Review completed request activity and requested item details by department.',
    'inbound_history' => 'Review inbound deliveries by PO/PR number, supplier, storage room, and received quantity.',
    'released_requests' => 'Review released requests and print release documentation.',
    'bin_card' => 'Generate official item bin cards with receiving, issuance, and running balance history.',
    'expiry_monitoring' => 'Track near-expiry and expired CSSR items for action and prioritization.',
    'reorder_alerts' => 'Review low, critical, and out-of-stock items that need replenishment.',
    'reports' => 'Generate inventory, movement, expiry, low stock, and consumption reports.',
    'monthly_report' => 'Generate monthly consumption reports from stock issuance records.',
    'suppliers' => 'Manage supplier profiles and contact details used in receiving transactions.',
    'departments' => 'Maintain hospital departments used for requests and stock issuance.',
    'users' => 'Administer user accounts, roles, departments, and access status.',
    'activity_logs' => 'Audit user activity and system transactions.',
    'backup_restore' => 'Create SQL backups and restore database snapshots.',
    'settings' => 'Review system profile, environment, security, and deployment readiness.',
];
$pageDescription = $pageDescription ?? ($pageDescriptions[$activePage] ?? 'Manage CSSR inventory records and daily warehouse operations.');
$pageBodyClass = trim(($bodyClass ?? '') . ' page-' . preg_replace('/[^a-z0-9_-]+/i', '-', $activePage));

require_once __DIR__.'/design-navigation.php';require_once __DIR__.'/legacy-layout.php';
$user=current_user();$name=$user['full_name']?:$user['username'];$initials='';
foreach(array_slice(preg_split('/\\s+/u',$name),0,2) as $part)$initials.=function_exists('mb_substr')?mb_substr($part,0,1,'UTF-8'):substr($part,0,1);
$embedded=!empty($embedReturn)||!empty($embedRequest)||!empty($embedStorageRequest);
cssrLegacyStart([
    'title'=>$pageTitle,'description'=>$pageDescription,'home'=>'dashboard.php','active'=>cssrNavigationActive($activePage),
    'navigation'=>cssrDashboardNavigation(),'searchAction'=>'items.php','searchPlaceholder'=>'Search inventory…',
    'initials'=>strtoupper($initials),'user'=>$name,'role'=>str_replace('Cssr','CSSR',ucwords(strtolower(str_replace('_',' ',$user['role'])))),
    'settings'=>'/Nursing_System/pages/account_settings.php','bodyClass'=>$pageBodyClass.($embedded?' cu-embedded':''),'embedded'=>$embedded,
    'notificationText'=>$canSeeInventoryAlerts?$notificationTotal.' inventory alerts to review.':'Review your department requests and updates.',
    'notificationLinks'=>$canSeeInventoryAlerts?[
        ['href'=>'reorder_alerts.php','label'=>$notificationCounts['low'].' low stock items'],
        ['href'=>'expiry_monitoring.php','label'=>$notificationCounts['expiry'].' near expiry items'],
        ['href'=>'expiry_monitoring.php','label'=>$notificationCounts['expired'].' expired items'],
    ]:[],
]);
?>
<?php if ($message = flash()): ?>
<div class="toast-stack"><div class="app-toast toast-<?=e($message['type'])?>" role="status"><strong><?=e(ucfirst($message['type']))?></strong><span><?=e($message['message'])?></span></div></div>
<?php endif; ?>
<div class="confirm-modal" id="confirmModal" aria-hidden="true">
    <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmMessage">
        <div class="confirm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 16h.01"/></svg></div><div class="confirm-copy"><h3 id="confirmTitle">Confirm action</h3><p id="confirmMessage">Are you sure you want to continue?</p></div>
        <div class="confirm-actions"><button class="btn btn-light" type="button" data-confirm-cancel>Cancel</button><button class="btn btn-primary" type="button" data-confirm-ok>Continue</button></div>
    </div>
</div>
<div class="loading-overlay" id="loadingOverlay" aria-hidden="true"><div class="loading-card"><i></i><span>Processing request...</span></div></div>
