<?php
require_once __DIR__.'/../includes/auth.php';require_login();
require_once __DIR__.'/../includes/design-system.php';require_once __DIR__.'/../includes/design-navigation.php';
require_once __DIR__.'/../includes/storage-room-data.php';require_once __DIR__.'/../includes/storage-room-view.php';
$user=current_user();$name=$user['full_name']?:$user['username'];$initials='';foreach(array_slice(preg_split('/\s+/u',$name),0,2) as $part)$initials.=function_exists('mb_substr')?mb_substr($part,0,1,'UTF-8'):substr($part,0,1);
$notice=flash();
cssrRenderStorageRoom(cssrStorageRoomData(db(),$_GET),['title'=>'Storage Room 1','home'=>'dashboard.php','active'=>'storage','navigation'=>cssrDashboardNavigation(),
    'searchAction'=>'storage_room_1.php','searchPlaceholder'=>'Search stored supplies…','initials'=>strtoupper($initials),'user'=>$name,
    'role'=>str_replace('Cssr','CSSR',ucwords(strtolower(str_replace('_',' ',$user['role'])))),
    'settings'=>'/Nursing_System/pages/account_settings.php','notificationText'=>'Review low stock and batches expiring within 90 days.',
],['manage'=>shared_has_permission('cssr_inventory','edit'),'csrf'=>csrf_token(),'notice'=>$notice['message']??'']);
