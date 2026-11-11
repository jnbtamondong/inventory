<?php
require_once __DIR__.'/../includes/auth.php';require_login();
require_once __DIR__.'/../includes/design-system.php';require_once __DIR__.'/../includes/design-navigation.php';
require_once __DIR__.'/../includes/cssr-stock-page-data.php';require_once __DIR__.'/../includes/cssr-stock-page-view.php';
$user=current_user();$name=$user['full_name']?:$user['username'];$initials='';
foreach(array_slice(preg_split('/\\s+/u',$name),0,2) as $part)$initials.=function_exists('mb_substr')?mb_substr($part,0,1,'UTF-8'):substr($part,0,1);
cssrRenderStockPage(cssrStockPageData(db(),$_GET),['title'=>'CSSR Stock','home'=>'dashboard.php','active'=>'cssr-stock','navigation'=>cssrDashboardNavigation(),
    'searchAction'=>'cssr_available_stock.php','searchPlaceholder'=>'Search CSSR stock…','initials'=>strtoupper($initials),'user'=>$name,
    'role'=>str_replace('Cssr','CSSR',ucwords(strtolower(str_replace('_',' ',$user['role'])))),
    'settings'=>'/Nursing_System/pages/account_settings.php','notificationText'=>'Review CSSR stock levels before releasing department requests.',
],['route'=>'cssr_available_stock.php','requests'=>shared_has_permission('ris_requests','view')?'requisitions.php':null]);
