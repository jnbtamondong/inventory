<?php
require_once __DIR__.'/../includes/design-system.php';require_once __DIR__.'/../includes/cssr-stock-page-data.php';require_once __DIR__.'/../includes/cssr-stock-page-view.php';
require_once __DIR__.'/preview-navigation.php';require_once __DIR__.'/cssr-stock-samples.php';
cssrRenderStockPage(cssrStockPageData(cssrStockPageSamples(),$_GET),['title'=>'CSSR Stock preview','home'=>'dashboard.php','active'=>'cssr-stock','navigation'=>cssrPreviewNavigation(),
    'searchAction'=>'cssr_stock.php','searchPlaceholder'=>'Search CSSR stock…','initials'=>'SA','user'=>'Sample Admin','role'=>'Super Admin · Preview',
    'settings'=>'/Nursing_System/pages/account_settings.php','environment'=>'CSSR Stock design preview','notificationText'=>'Sample CSSR stock and issuance only. No live records are shown.',
],['preview'=>true,'route'=>'cssr_stock.php','requests'=>'requisitions.php']);
