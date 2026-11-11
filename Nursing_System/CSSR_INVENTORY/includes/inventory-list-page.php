<?php
// Included only after the existing items.php authentication and authorization guards.
require_once __DIR__ . '/design-system.php';
require_once __DIR__ . '/design-navigation.php';
require_once __DIR__ . '/inventory-list-data.php';
require_once __DIR__ . '/inventory-list-view.php';
$inventoryData = cssrInventoryList(db(), $_GET);
$inventoryUser = current_user();
$inventoryName = trim((string)($inventoryUser['full_name'] ?? '')) ?: (string)($inventoryUser['username'] ?? 'User');
$inventoryInitials = '';
foreach (array_slice(preg_split('/\s+/u', $inventoryName) ?: ['U'],0,2) as $part) $inventoryInitials .= function_exists('mb_substr') ? mb_substr($part,0,1,'UTF-8') : substr($part,0,1);
$inventoryRole = str_replace('Cssr','CSSR',ucwords(strtolower(str_replace('_',' ',(string)($inventoryUser['role'] ?? '')))));
cssrRenderInventory($inventoryData, [
    'title'=>'Inventory','home'=>'dashboard.php','active'=>'items','assets'=>'../assets','navigation'=>cssrDashboardNavigation(),
    'searchAction'=>'items.php','searchPlaceholder'=>'Search inventory...','initials'=>strtoupper($inventoryInitials),
    'user'=>$inventoryName,'role'=>$inventoryRole,'settings'=>'/Nursing_System/pages/account_settings.php','environment'=>'Hospital workspace',
    'notificationText'=>'Review stock alerts and pending requisitions on your dashboard.',
], ['list'=>'items.php','can_manage'=>can_manage(),'view'=>'item_details.php?id=','history'=>'item_details.php?id=',
    'save'=>'../api/item-editor.php','csrf'=>csrf_token(),
    'units'=>array_column(db_all("SELECT DISTINCT TRIM(unit) unit FROM items WHERE TRIM(COALESCE(unit,''))<>''"),'unit'),
], false, flash());
