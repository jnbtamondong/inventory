<?php
require_once __DIR__.'/../includes/design-system.php';require_once __DIR__.'/../includes/inbound-data.php';require_once __DIR__.'/../includes/inbound-view.php';
require_once __DIR__.'/preview-navigation.php';require_once __DIR__.'/inbound-samples.php';
$data=cssrInboundList(cssrInboundSampleDb(),$_GET,1,true);
cssrRenderInbound($data,['title'=>'Inbound Supplies preview','home'=>'dashboard.php','active'=>'inbound','navigation'=>cssrPreviewNavigation(),
    'searchAction'=>'inbound.php','searchPlaceholder'=>'Search reference number…','initials'=>'SA','user'=>'Sample Admin','role'=>'Super Admin · Preview',
    'settings'=>'/Nursing_System/pages/account_settings.php','environment'=>'Inbound design preview','notificationText'=>'Sample deliveries only. No hospital records are shown.',
],['preview'=>true,'manage'=>true]);
