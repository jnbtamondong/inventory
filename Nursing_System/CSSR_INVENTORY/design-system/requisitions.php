<?php
require_once __DIR__.'/../includes/design-system.php';
require_once __DIR__.'/../includes/requisitions-data.php';
require_once __DIR__.'/../includes/requisitions-view.php';
require_once __DIR__.'/preview-navigation.php';
require_once __DIR__.'/requisitions-samples.php';
$data=cssrRequisitions(cssrRequisitionSamples(),$_GET,true,0);
cssrRenderRequisitions($data,[
    'title'=>'Requisitions preview','home'=>'dashboard.php','active'=>'requisitions','navigation'=>cssrPreviewNavigation(),
    'searchAction'=>'requisitions.php','searchPlaceholder'=>'Search TRN number…','initials'=>'SA','user'=>'Sample Admin','role'=>'Super Admin · Preview',
    'settings'=>'/Nursing_System/pages/account_settings.php','environment'=>'Requisitions design preview',
    'notificationText'=>'Sample requisitions only. No live notifications or records are shown.',
],['preview'=>true,'manage'=>true,'create'=>true]);
