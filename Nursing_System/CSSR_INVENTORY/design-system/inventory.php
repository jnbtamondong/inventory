<?php
require_once __DIR__ . '/../includes/design-system.php';
require_once __DIR__ . '/../includes/inventory-list-data.php';
require_once __DIR__ . '/../includes/inventory-list-view.php';
require_once __DIR__ . '/preview-navigation.php';
require_once __DIR__ . '/inventory-samples.php';
$inventoryData = cssrInventoryList(cssrInventorySampleConnection(), $_GET);
cssrRenderInventory($inventoryData, [
    'title'=>'Inventory preview','home'=>'dashboard.php','active'=>'items','assets'=>'../assets','navigation'=>cssrPreviewNavigation(),
    'searchAction'=>'inventory.php','searchPlaceholder'=>'Search inventory...','initials'=>'SA','user'=>'Sample Admin','role'=>'Super Admin · Preview',
    'settings'=>'/Nursing_System/pages/account_settings.php','environment'=>'Inventory design preview',
    'notificationText'=>'This is a sample inventory list. No live notifications or records are shown.',
], ['list'=>'inventory.php','can_manage'=>true], true);
