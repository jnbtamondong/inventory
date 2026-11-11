<?php
// Public, synthetic preview only. No session, authentication bypass, or database calls.
require_once __DIR__ . '/../includes/design-system.php';
require_once __DIR__ . '/../includes/dashboard-view.php';
$today = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$currentYear = (int) $today->format('Y');
$years = [$currentYear, $currentYear - 1, $currentYear - 2];
$requested = $_GET['year'] ?? null;
$year = is_scalar($requested) && in_array((int)$requested, $years, true) ? (int)$requested : $currentYear;
$series = [1840, 2260, 1980, 2840, 2460, 3120, 2760, 3480, 2960, 2180, 3020, 2680];
if ($year === $currentYear) {
    foreach ($series as $month => &$quantity) if ($month + 1 > (int)$today->format('n')) $quantity = 0;
    unset($quantity);
} else {
    $series = array_map(static fn($quantity) => (int)round($quantity * ($year === $currentYear - 1 ? .82 : .68)), $series);
}
$data = [
    'date'=>$today->format('Y-m-d'), 'year'=>$year, 'years'=>$years, 'inventory_visible'=>true, 'requests_visible'=>true,
    'counts'=>['total'=>1284,'available'=>1128,'low'=>124,'out'=>32], 'monthly'=>$series,
    'activity'=>['requests'=>24,'pending'=>8,'released'=>16,'returns'=>12,'inbound'=>5],
    'scope_label'=>'All departments', 'pending_total'=>18,
    'pending'=>[], 'low_items'=>[
        ['item_name'=>'Surgical mask','item_code'=>'CSSR-004','current_stock'=>0,'minimum_stock'=>100,'unit'=>'Box'],
        ['item_name'=>'Sterile cotton balls','item_code'=>'CSSR-018','current_stock'=>0,'minimum_stock'=>40,'unit'=>'Pack'],
        ['item_name'=>'Examination gloves','item_code'=>'CSSR-002','current_stock'=>42,'minimum_stock'=>100,'unit'=>'Box'],
        ['item_name'=>'Sterile gauze 4 × 4','item_code'=>'CSSR-001','current_stock'=>120,'minimum_stock'=>250,'unit'=>'Pack'],
        ['item_name'=>'Disposable syringe 5 mL','item_code'=>'CSSR-003','current_stock'=>180,'minimum_stock'=>300,'unit'=>'Piece'],
        ['item_name'=>'Micropore surgical tape','item_code'=>'CSSR-026','current_stock'=>28,'minimum_stock'=>40,'unit'=>'Roll'],
    ],
];
foreach ([['Emergency Room',8,'Pending'],['Operating Room',12,'Approved'],['Medical Ward',6,'Pending'],['NICU',4,'Approved'],['Outpatient Department',10,'Pending'],['Intensive Care Unit',7,'Approved']] as $index => [$department,$items,$status]) {
    $data['pending'][] = ['id'=>$index + 1,'request_no'=>'TRN-' . $currentYear . '-' . (1042 + $index),'department_name'=>$department,'request_date'=>$today->modify('-' . (5 - $index) . ' days')->format('Y-m-d'),'item_count'=>$items,'status'=>$status];
}
require_once __DIR__ . '/preview-navigation.php';
$navigation = cssrPreviewNavigation();
cssrRenderDashboard($data, [
    'title'=>'Dashboard preview','home'=>'dashboard.php','assets'=>'../assets','active'=>'dashboard','navigation'=>$navigation,
    'searchAction'=>'../pages/items.php','searchPlaceholder'=>'Search inventory...','initials'=>'SA','user'=>'Sample Admin',
    'role'=>'Super Admin · Preview','settings'=>'/Nursing_System/pages/account_settings.php','environment'=>'Dashboard design preview',
    'notificationText'=>'Sample overview: 18 requisitions await approval or release. No live records are shown.',
], ['requests'=>'../pages/item_requests.php','inventory'=>'../pages/items.php'], true);
