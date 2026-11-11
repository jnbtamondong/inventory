<?php
require_once __DIR__.'/../includes/design-system.php';require_once __DIR__.'/../includes/reports-data.php';require_once __DIR__.'/../includes/reports-export.php';require_once __DIR__.'/../includes/reports-view.php';
require_once __DIR__.'/preview-navigation.php';require_once __DIR__.'/reports-samples.php';
$data=cssrReportData(cssrReportSamples(),$_GET);
if(isset($_GET['export']))cssrDownloadReport($data,is_string($_GET['export'])?$_GET['export']:'');
cssrRenderReports($data,['title'=>'Reports preview','home'=>'dashboard.php','active'=>cssrReportNavigationActive($data['filters']['report']),'navigation'=>cssrPreviewNavigation(),
    'searchAction'=>'inventory.php','searchPlaceholder'=>'Search inventory…','initials'=>'SA','user'=>'Sample Admin','role'=>'Super Admin · Preview',
    'settings'=>'/Nursing_System/pages/account_settings.php','environment'=>'Reports design preview','notificationText'=>'Sample reports only. No hospital records are shown.',
],['preview'=>true,'route'=>'reports.php','print'=>isset($_GET['print'])]);
