<?php
require_once __DIR__.'/../includes/design-navigation.php';
function cssrPreviewNavigation(): array
{
    $navigation=cssrDashboardNavigation(true);
    $previews=['dashboard.php'=>'dashboard.php','items.php'=>'inventory.php','inbound.php'=>'inbound.php','storage_room_1.php'=>'storage_room_1.php','cssr_available_stock.php'=>'cssr_stock.php','requisitions.php'=>'requisitions.php','reports.php'=>'reports.php'];
    $route=static function(string $href)use($previews): string {
        if(str_starts_with($href,'/'))return $href;
        [$path,$query]=array_pad(explode('?',$href,2),2,'');
        return ($previews[$path]??'../pages/'.$path).($query!==''?'?'.$query:'');
    };
    foreach($navigation as &$module){$module['href']=$route($module['href']);foreach($module['children']??[] as $index=>$child)$module['children'][$index]['href']=$route($child['href']);}
    unset($module);return $navigation;
}
