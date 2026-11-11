<?php
require_once __DIR__.'/../includes/auth.php';require_login();
if(!shared_has_permission('cssr_inventory','view')){http_response_code(403);exit('Access denied.');}
require_once __DIR__.'/../includes/design-system.php';require_once __DIR__.'/../includes/design-navigation.php';
require_once __DIR__.'/../includes/inbound-data.php';require_once __DIR__.'/../includes/inbound-view.php';
$user=current_user();$data=cssrInboundList(db(),$_GET,(int)$user['id'],is_cssr_inventory_admin());$name=$user['full_name']?:$user['username'];$initials='';
foreach(array_slice(preg_split('/\s+/u',$name),0,2) as $part)$initials.=function_exists('mb_substr')?mb_substr($part,0,1,'UTF-8'):substr($part,0,1);
$notice=flash();
$data['procurement_queue']=cssrInboundProcurementQueue(db());
cssrRenderInbound($data,['title'=>'Inbound Supplies','home'=>'dashboard.php','active'=>'inbound','navigation'=>cssrDashboardNavigation(),
    'searchAction'=>'inbound.php','searchPlaceholder'=>'Search reference number…','initials'=>strtoupper($initials),'user'=>$name,
    'role'=>str_replace('Cssr','CSSR',ucwords(strtolower(str_replace('_',' ',$user['role'])))),
    'settings'=>'/Nursing_System/pages/account_settings.php','notificationText'=>'Review saved drafts and incoming deliveries.',
],['manage'=>can_manage()&&shared_has_permission('cssr_inventory','create'),'csrf'=>csrf_token(),'notice'=>$notice['message']??'']);
