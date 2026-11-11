<?php
require_once __DIR__.'/../includes/auth.php';
require_roles([ROLE_ADMIN,ROLE_HEAD,ROLE_STAFF]);
require_once __DIR__.'/../includes/design-system.php';
require_once __DIR__.'/../includes/design-navigation.php';
require_once __DIR__.'/../includes/requisitions-data.php';
require_once __DIR__.'/../includes/requisitions-view.php';
$user=current_user();
$viewAll=shared_has_permission('cssr_inventory','edit') || shared_has_permission('ris_requests','approve');
$data=cssrRequisitions(db(),$_GET,$viewAll,(int)($user['department_id']??0));
$name=trim($user['full_name']??'') ?: ($user['username']??'User'); $initials='';
foreach (array_slice(preg_split('/\s+/u',$name),0,2) as $part) $initials.=function_exists('mb_substr')?mb_substr($part,0,1,'UTF-8'):substr($part,0,1);
$error=(string)($_SESSION['request_popup_error']??'');
$state=is_array($_SESSION['request_form_state']??null)?$_SESSION['request_form_state']:[];
if ((int)($state['request_id']??0)!==(int)($data['selected']['id']??0)) $state=[];
unset($_SESSION['request_popup_error'],$_SESSION['request_form_state'],$_SESSION['print_prompt_request_id']);
$notice=flash();
cssrRenderRequisitions($data,[
    'title'=>'Requisitions','home'=>'dashboard.php','active'=>'requisitions','navigation'=>cssrDashboardNavigation(),
    'searchAction'=>'requisitions.php','searchPlaceholder'=>'Search TRN number…','initials'=>strtoupper($initials),
    'user'=>$name,'role'=>str_replace('Cssr','CSSR',ucwords(strtolower(str_replace('_',' ',$user['role']??'')))),
    'settings'=>'/Nursing_System/pages/account_settings.php','notificationText'=>'Review department requests awaiting approval or release.',
],['manage'=>can_manage(),'create'=>shared_has_permission('ris_requests','create'),'csrf'=>csrf_token(),
    'error'=>$error?:($notice&&$notice['type']!=='success'?$notice['message']:''),
    'notice'=>$notice&&$notice['type']==='success'?$notice['message']:'','form_state'=>$state]);
