<?php
require_once __DIR__.'/../includes/design-system.php';require_once __DIR__.'/../includes/mobile-navigation.php';
$checks=0;function check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$render=static function($navigation,$active=''){ob_start();cssrMobileNavigation(['navigation'=>$navigation,'active'=>$active]);return ob_get_clean();};
$nav=[['id'=>'dashboard','href'=>'dashboard.php'],['id'=>'inventory','href'=>'items.php','children'=>[['id'=>'items','href'=>'items.php']]],['id'=>'reports','href'=>'reports.php'],['id'=>'inbound','href'=>'inbound.php','mobile_create'=>true],['id'=>'requisitions','href'=>'requisitions.php','mobile_create'=>false]];
$html=$render($nav,'items');
check(str_contains($html,'href="items.php" aria-current="page"'),'Inventory submenu activates Inventory tab');
check(str_contains($html,'inbound.php?new=1'),'Inbound shortcut targets existing editor');
check(!str_contains($html,'Request supplies'),'View-only requests cannot appear as create shortcut');
check(!str_contains($html,'Return supplies'),'Missing module not exposed');
check(str_contains($html,'Mobile navigation')&&str_contains($html,'More modules'),'Accessible navigation labels');
$html=$render([['id'=>'dashboard','href'=>'dashboard.php']]);
check(!str_contains($html,'New Transaction'),'No transaction button without a permitted action');
check(!str_contains($html,'>Inventory<')&&!str_contains($html,'>Reports<'),'Restricted modules not exposed');
$html=$render([['id'=>'requisitions','href'=>'requisitions.php','mobile_create'=>true]]);
check(str_contains($html,'href="item_requests.php"'),'Request shortcut uses existing creation workflow');
$html=$render([['id'=>'reports','href'=>'reports.php?a="unsafe"']]);
check(str_contains($html,'&quot;unsafe&quot;'),'Routes escaped safely');
require_once __DIR__.'/../includes/design-navigation.php';
$nav=cssrDashboardNavigation(false,static fn($module,$action)=>false,'NURSE');
$modules=array_column($nav,null,'id');
check(!isset($modules['inventory'],$modules['users']),'Restricted workspace does not expose inventory or user administration');
check(in_array('chat',array_column($modules['requisitions']['children'],'id'),true),'Chat remains reachable without request access');
check($modules['settings']['href']==='/Nursing_System/pages/account_settings.php?workspace=cssr','Settings keeps CSSR context');
$nav=cssrDashboardNavigation(false,static fn($module,$action)=>$module==='cssr_inventory'&&$action==='view','NURSE');
$modules=array_column($nav,null,'id');
check(!isset($modules['inbound']),'Read-only inventory does not expose receiving form');
check(in_array('inbound-history',array_column($modules['history']['children'],'id'),true),'Read-only inventory retains delivery history');
$nav=cssrDashboardNavigation(true);$modules=array_column($nav,null,'id');
check(count($nav)===11&&!isset($modules['procurement']),'Purchasing removed from CSSR sidebar');
check(array_column($nav,'id')===['dashboard','inventory','inbound','storage','requisitions','reports','stock-levels','bin-card','report-tools','history','settings'],'Workflow submenu order');
check(count($modules['reports']['children'])===12,'Reports remain available through the report page');
check(array_values(array_filter(array_column($nav,'group')))===['Workspace','Administration'],'Menus grouped by workflow');
require_once __DIR__.'/../includes/reports-data.php';
foreach(cssrReportTypes() as $type=>$label){
    $children=array_column($modules['reports']['children'],null,'id');
    $child=$children[cssrReportNavigationActive($type)]??null;
    $destination=in_array($type,['monthly','weekly','annual'],true)?'monthly':$type;
    if($type==='department_consumption')$destination='consumption';
    check($child&&$child['href']==='reports.php?report='.$destination,'Report link and active state: '.$type);
}
check(array_column($modules['inventory']['children'],'id')===['items','price-list','stock-adjustments','adjustment-guide'],'Inventory contains item maintenance tools');
check(!isset(array_column(cssrDashboardNavigation(false,static fn($module,$action)=>false,'NURSE'),null,'id')['procurement']),'Restored procurement link respects role permissions');
check(!isset($modules['users']),'User management removed from CSSR sidebar');
check(array_column($modules['storage']['children'],'id')===['all-storage','cssr-stock','storage-requests'],'Storage groups rooms, CSSR stock and transfers');
check(array_column($modules['requisitions']['children'],'id')===['new-request','issuance','returns','chat'],'Requests groups active workflows without department request list or direct issuance');
check(array_column($modules['history']['children'],'id')===['inbound-history','history','released-requests','audit'],'Delivery, request, release and activity history share one section');
check($modules['reports']['single_link']&&$modules['reports']['href']==='reports.php','Reports is one sidebar link');
check(!isset($modules['stock-levels']['children'],$modules['bin-card']['children']),'Stock Card and Bin Card stay separate');
check(array_column($modules['report-tools']['children'],'id')===['monthly-report','expiry','reorder'],'Other report tools moved into sidebar');
$html=$render($nav);
foreach(['Receive supplies','Request supplies','Return supplies'] as $action)check(str_contains($html,$action),'Grouped mobile shortcut: '.$action);
$readOnly=cssrDashboardNavigation(false,static fn($module,$action)=>$action==='view','NURSE');
check(!str_contains($render($readOnly),'New Transaction'),'Read-only submenus do not grant create shortcuts');
$reportOnly=cssrDashboardNavigation(false,static fn($module,$action)=>$module==='reports'&&$action==='view','CSSR_STAFF');
check(count(array_column($reportOnly,null,'id')['reports']['children'])===12,'Report-only users do not receive inventory ledger permissions');
$ids=[];foreach($nav as $entry)foreach($entry['children']??[$entry] as $child)$ids[]=$child['id'];
check(count($ids)===count(array_unique($ids)),'No duplicate sidebar destinations');
foreach(['NURSE','STAFF_NURSE','NURSING_ATTENDANT','MIDWIFE','HEAD_NURSE','head_nurse'] as $role){
    $departmentNav=cssrDashboardNavigation(false,static fn($module,$action)=>true,$role);
    $departmentIds=array_column($departmentNav,'id');
    check(!array_intersect(['dashboard','reports','report-tools'],$departmentIds),'Department menu hides dashboard and report sections: '.$role);
    check(in_array('requisitions',$departmentIds,true)&&in_array('history',$departmentIds,true)&&in_array('settings',$departmentIds,true),'Department workflows retained: '.$role);
    check(!str_contains($render($departmentNav),'>Home<')&&!str_contains($render($departmentNav),'>Reports<'),'Mobile menu matches department sidebar: '.$role);
    check(cssrNavigationHome($departmentNav)==='item_requests.php','Department home opens supply requests: '.$role);
}
foreach(['SUPER_ADMIN','CSSR_STAFF','CSSR_HEAD','CHIEF_NURSE','SUPERVISOR_NURSE'] as $role){
    $staffNav=cssrDashboardNavigation(false,static fn($module,$action)=>true,$role);
    check(count(array_intersect(['dashboard','reports','report-tools'],array_column($staffNav,'id')))===3,'Other roles retain dashboard and reports: '.$role);
    check(cssrNavigationHome($staffNav)==='dashboard.php','Other roles retain dashboard home: '.$role);
}
foreach(['CSSR',' cssr ','ER','WARD_2F',''] as $department){
    $attendantNav=cssrDashboardNavigation(false,static fn($module,$action)=>true,'NURSING_ATTENDANT',$department);
    $visible=array_intersect(['dashboard','reports','report-tools'],array_column($attendantNav,'id'));
    $cssr=strtoupper(trim($department))==='CSSR';
    check(count($visible)===($cssr?3:0),'Only CSSR nursing attendants are exempt: '.$department);
    check(cssrNavigationHome($attendantNav)===($cssr?'dashboard.php':'item_requests.php'),'Attendant landing matches department: '.$department);
}
foreach(['NURSE','STAFF_NURSE','MIDWIFE','HEAD_NURSE'] as $role){
    $cssrNav=cssrDashboardNavigation(false,static fn($module,$action)=>true,$role,'CSSR');
    check(!array_intersect(['dashboard','reports','report-tools'],array_column($cssrNav,'id')),'CSSR exception applies only to Nursing Attendant: '.$role);
}
$limitedAttendant=cssrDashboardNavigation(false,static fn($module,$action)=>false,'NURSING_ATTENDANT','CSSR');
check(!array_intersect(['reports','report-tools'],array_column($limitedAttendant,'id')),'CSSR exception still respects report and inventory permissions');
echo "Passed $checks navigation permission and routing checks.\n";
