<?php
// Presentation adapter for the existing shared account pages. Their own authorization and POST handlers remain in charge.
require_once __DIR__.'/legacy-layout.php';require_once __DIR__.'/design-navigation.php';
require_once __DIR__.'/../../includes/cssr-access.php';
function cssrExternalStart(string $title,string $active,string $bodyClass,array $styles=[]): void
{
    require_once __DIR__.'/request-notifications.php';
    $permission=static fn($module,$action)=>cssrAttendantPermission($_SESSION,$module,$action,true)||hasPermission($module,$action);
    $navigation=cssrDashboardNavigation(false,$permission,$_SESSION['original_role']??$_SESSION['user']['original_role']??canonicalRole($_SESSION['role']??''),$_SESSION['department']??'');
    $absolute=static fn($href)=>str_starts_with($href,'/')?$href:'/Nursing_System/CSSR_INVENTORY/pages/'.$href;
    foreach($navigation as &$module){$module['href']=$absolute($module['href']);foreach($module['children']??[] as $index=>$child)$module['children'][$index]['href']=$absolute($child['href']);}unset($module);
    $name=trim(($_SESSION['first_name']??'').' '.($_SESSION['last_name']??''))?:($_SESSION['username']??'Account');
    cssrLegacyStart(['title'=>$title,'description'=>'Manage your hospital account and access.','assets'=>'/Nursing_System/CSSR_INVENTORY/assets','home'=>'/Nursing_System/CSSR_INVENTORY/pages/dashboard.php','active'=>$active,
        'navigation'=>$navigation,'searchAction'=>'/Nursing_System/CSSR_INVENTORY/pages/items.php','initials'=>strtoupper(substr($name,0,1)),'user'=>$name,'role'=>roleLabel($_SESSION['role']??''),
        'settings'=>'/Nursing_System/pages/account_settings.php?workspace=cssr','bodyClass'=>$bodyClass.' cu-shared-account','baseStyles'=>$styles,
        'requestNotifications'=>cssrCanReceiveRequestNotifications($_SESSION,$permission),
        'chatNotifications'=>cssrCanReceiveChatNotifications($_SESSION,$permission)]);
}
