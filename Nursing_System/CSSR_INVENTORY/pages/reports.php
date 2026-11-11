<?php
require_once __DIR__.'/../includes/auth.php';require_admin_or_cssr();
require_once __DIR__.'/../includes/design-system.php';require_once __DIR__.'/../includes/design-navigation.php';
require_once __DIR__.'/../includes/reports-data.php';require_once __DIR__.'/../includes/reports-export.php';require_once __DIR__.'/../includes/reports-view.php';
$data=cssrReportData(db(),$_GET);
if(isset($_GET['export']))cssrDownloadReport($data,is_string($_GET['export'])?$_GET['export']:'');
$user=current_user();$name=$user['full_name']?:$user['username'];$initials='';
foreach(array_slice(preg_split('/\\s+/u',$name),0,2) as $part)$initials.=function_exists('mb_substr')?mb_substr($part,0,1,'UTF-8'):substr($part,0,1);
header('Cache-Control: private, no-store');
cssrRenderReports($data,['title'=>'Reports','home'=>'dashboard.php','active'=>cssrReportNavigationActive($data['filters']['report']),'navigation'=>cssrDashboardNavigation(),
    'searchAction'=>'items.php','searchPlaceholder'=>'Search inventory…','initials'=>strtoupper($initials),'user'=>$name,
    'role'=>str_replace('Cssr','CSSR',ucwords(strtolower(str_replace('_',' ',$user['role'])))),
    'settings'=>'/Nursing_System/pages/account_settings.php','notificationText'=>'Generate filtered inventory reports and export matching records.',
],['route'=>'reports.php','print'=>isset($_GET['print'])]);
