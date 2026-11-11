<?php
function cssrNavigationActive(string $page): string
{
    return ['items'=>'items','stock_in'=>'inbound','stock_out'=>'direct-issuance','item_requests'=>'new-request',
        'department_returns'=>'returns','request_queue'=>'issuance','storage_masterlist'=>'all-storage','storage_requests'=>'storage-requests','get_stock'=>'storage',
        'expiry_monitoring'=>'expiry','reorder_alerts'=>'reorder','stock_card'=>'stock-levels','bin_card'=>'bin-card','price_list'=>'price-list','item_details'=>'items',
        'stock_adjustment'=>'stock-adjustments','inventory_adjustment'=>'adjustment-guide','request_history'=>'history','inbound_history'=>'inbound-history','released_requests'=>'released-requests',
        'reports'=>'inventory-reports','monthly_report'=>'monthly-report','suppliers'=>'suppliers','departments'=>'departments','activity_logs'=>'audit','backup_restore'=>'backup','chat'=>'chat'][$page]??$page;
}
// Route adapter only: keeps the shared sidebar's structure and appearance intact.
function cssrDashboardNavigation(bool $preview=false, ?callable $permission=null, ?string $role=null, ?string $department=null): array
{
    $identity = !$preview && $role === null ? (current_user() ?? []) : [];
    $role = strtoupper(trim($role ?? ($preview ? 'SUPER_ADMIN' : ($identity['original_role'] ?? $identity['role'] ?? ''))));
    $can = static fn(string $module,string $action): bool => ($module !== 'audit_trail' || $role === 'SUPER_ADMIN')
        && ($preview || ($permission ? $permission($module,$action) : shared_has_permission($module,$action)));
    $department = strtoupper(trim($department ?? $identity['department_name'] ?? ''));
    $cssrAttendant = $role === 'NURSING_ATTENDANT' && $department === 'CSSR';
    $departmentMenu = !$cssrAttendant && in_array($role, ['NURSE','STAFF_NURSE','NURSING_ATTENDANT','MIDWIFE','HEAD_NURSE'], true);
    $inventory = $can('cssr_inventory', 'view');
    $requests = $can('ris_requests', 'view');
    $procurement = $preview || in_array($role, ['SUPER_ADMIN','CSSR_HEAD','CSSR_STAFF','SUPERVISOR_NURSE','CHIEF_NURSE','HEAD_NURSE'], true);
    $routes = [
        'dashboard'=>[true, 'dashboard.php'],
        'inventory'=>[$inventory, 'items.php'],
        'inbound'=>[$can('cssr_inventory','create'), $preview ? 'inbound.php' : 'stock_in.php'],
        'storage'=>[$inventory, 'storage_masterlist.php'],
        'cssr-stock'=>[$inventory, 'cssr_available_stock.php'],
        'requisitions'=>[$requests, 'requisitions.php'],
        'issuance'=>[$inventory, 'request_queue.php'],
        'returns'=>[$can('ris_requests','create'), 'department_returns.php'],
        'ppmp'=>[$procurement, '/Nursing_System/PROCUREMENT/pages/ppmp.php'],
        'purchase-request'=>[$procurement, '/Nursing_System/PROCUREMENT/pages/purchase_requests.php'],
        'reports'=>[$can('reports','view'), 'reports.php'],
        'history'=>[$requests, 'request_history.php'],
        'settings'=>[true, '/Nursing_System/pages/account_settings.php?workspace=cssr'],
    ];
    $children = [
        'inventory'=>[
            ['id'=>'items','label'=>'Item Masterlist','href'=>'items.php'],
            ['id'=>'stock-levels','label'=>'Stock Card','href'=>'stock_card.php'],
            ['id'=>'expiry','label'=>'Manage Expiring Stock','href'=>'expiry_monitoring.php'],
            ['id'=>'reorder','label'=>'Reorder Alerts','href'=>'reorder_alerts.php'],
            ['id'=>'bin-card','label'=>'Bin Card','href'=>'bin_card.php'],
            ['id'=>'price-list','label'=>'Item Prices','href'=>'price_list.php'],
            ['id'=>'stock-adjustments','label'=>'Adjust Stock Count','href'=>'stock_adjustment.php','permission'=>['cssr_inventory','edit']],
            ['id'=>'adjustment-guide','label'=>'Stock Adjustment Guide','href'=>'inventory_adjustment.php','permission'=>['cssr_inventory','edit']],
        ],
        'reports'=>[
            ['id'=>'periodic-reports','label'=>'Periodic Reports','href'=>'reports.php?report=monthly'],
            ['id'=>'usage-reports','label'=>'Consumption Report','href'=>'reports.php?report=consumption'],
            ['id'=>'expiry-reports','label'=>'Items Near Expiry','href'=>'reports.php?report=near_expiry'],
            ['id'=>'out-of-stock-reports','label'=>'Out of Stock Items','href'=>'reports.php?report=out_of_stock'],
            ['id'=>'low-stock-reports','label'=>'Low Stock Items','href'=>'reports.php?report=low_stock'],
            ['id'=>'available-reports','label'=>'Available Items','href'=>'reports.php?report=available'],
            ['id'=>'balance-reports','label'=>'Stock Balances','href'=>'reports.php?report=inventory_balance'],
            ['id'=>'inventory-reports','label'=>'Stock In & Out','href'=>'reports.php?report=stock_movement'],
            ['id'=>'inbound-reports','label'=>'Supplies Received','href'=>'reports.php?report=inbound'],
            ['id'=>'issuance-reports','label'=>'Supplies Issued','href'=>'reports.php?report=issuance'],
            ['id'=>'procurement-reports','label'=>'By Procurement Type','href'=>'reports.php?report=procurement_type'],
            ['id'=>'fast-moving-reports','label'=>'Most Issued Items','href'=>'reports.php?report=fast_moving'],
            ['id'=>'monthly-report','label'=>'Monthly Stock Worksheet','href'=>'monthly_report.php'],
        ],
        'inbound'=>[
            ['id'=>'inbound','label'=>'Receive Supplies','href'=>$preview ? 'inbound.php' : 'stock_in.php'],
            ['id'=>'inbound-history','label'=>'Delivery History','href'=>'inbound_history.php','permission'=>['cssr_inventory','view']],
        ],
        'storage'=>[
            ['id'=>'all-storage','label'=>'Stock in Storage Rooms','href'=>'storage_masterlist.php'],
            ['id'=>'storage-requests','label'=>'Request Stock for CSSR','href'=>'storage_requests.php','permission'=>['cssr_inventory','create']],
        ],
        'requisitions'=>[
            ['id'=>'new-request','label'=>'Create Supply Request','href'=>'item_requests.php','permission'=>['ris_requests','create']],
            ['id'=>'chat','label'=>'Supply Support Chat','href'=>'chat.php'],
        ],
        'issuance'=>[
            ['id'=>'issuance','label'=>'Pending Requests & Returns','href'=>'request_queue.php'],
            ['id'=>'released-requests','label'=>'Released Supplies','href'=>'released_requests.php','permission'=>['ris_requests','view']],
        ],
        'history'=>[
            ['id'=>'history','label'=>'Request History','href'=>'request_history.php'],
            ['id'=>'audit','label'=>'User Activity','href'=>'activity_logs.php','permission'=>['audit_trail','view']],
        ],
        'settings'=>[
            ['id'=>'settings','label'=>'My Account','href'=>'/Nursing_System/pages/account_settings.php?workspace=cssr'],
            ['id'=>'suppliers','label'=>'Manage Suppliers','href'=>'suppliers.php','permission'=>['cssr_inventory','edit']],
            ['id'=>'departments','label'=>'Manage Departments','href'=>'departments.php','permission'=>['cssr_inventory','edit']],
            ['id'=>'backup','label'=>'Backup & Restore','href'=>'backup_restore.php','permission'=>['user_management','view']],
        ],
    ];
    // Keep existing tools reachable when their parent workflow is not in this role's menu.
    if (!$requests) $children['settings'][]=['id'=>'chat','label'=>'Supply Support Chat','href'=>'chat.php'];
    if (!$requests && $can('audit_trail','view')) $children['settings'][]=['id'=>'audit','label'=>'User Activity','href'=>'activity_logs.php'];
    if (!$can('cssr_inventory','create') && $inventory) $children['inventory'][]=['id'=>'inbound-history','label'=>'Delivery History','href'=>'inbound_history.php'];
    $navigation = []; $pendingGroup = null;
    foreach (cssrUiModules() as $module) {
        if (isset($module['group'])) $pendingGroup = $module['group'];
        [$visible, $href] = $routes[$module['id']];
        if (!$visible) continue;
        if ($pendingGroup !== null) { $module['group'] = $pendingGroup; $pendingGroup = null; }
        $module['href'] = $href;
        $module['mobile_create'] = match($module['id']) {
            'inbound'=>$can('cssr_inventory','create'),
            'requisitions','returns'=>$can('ris_requests','create'),
            default=>false,
        };
        if (isset($children[$module['id']])) $module['children'] = array_values(array_filter($children[$module['id']],static fn($child)=>!isset($child['permission'])||$can(...$child['permission'])));
        $navigation[] = $module;
    }
    $navigation = cssrGroupNavigation($navigation);
    if ($departmentMenu) {
        $navigation = array_values(array_filter($navigation, static fn($module) => !in_array($module['id'], ['dashboard','reports','report-tools'], true)));
        if ($navigation && $navigation[0]['id'] !== 'settings') $navigation[0]['group'] = 'Workspace';
    }
    return $navigation;
}

// Reorganize only links that already passed the permission checks above.
function cssrGroupNavigation(array $navigation): array
{
    $links=[];
    foreach($navigation as $module){
        if(empty($module['children'])){$links[$module['id']]=$module;continue;}
        foreach($module['children'] as $child){
            if($child['id']===$module['id']||($module['id']==='requisitions'&&$child['id']==='new-request'))$child['mobile_create']=$module['mobile_create']??false;
            $links[$child['id']]=$child;
        }
    }
    $groups=[
        ['dashboard','Dashboard','dashboard',['dashboard']],
        ['inventory','Inventory','box',['items','price-list','stock-adjustments','adjustment-guide']],
        ['inbound','Incoming Supplies','inbound',['inbound']],
        ['storage','Storage & CSSR','storage',['all-storage','cssr-stock','storage-requests']],
        ['requisitions','Supply Requests','request',['new-request','issuance','returns','chat']],
        ['reports','Reports','report',['periodic-reports','usage-reports','expiry-reports','out-of-stock-reports','low-stock-reports','available-reports','balance-reports','inventory-reports','inbound-reports','issuance-reports','procurement-reports','fast-moving-reports']],
        ['stock-levels','Stock Card','document',['stock-levels']],
        ['bin-card','Bin Card','document',['bin-card']],
        ['report-tools','Report Tools','report',['monthly-report','expiry','reorder']],
        ['history','History','history',['inbound-history','history','released-requests','audit']],
        ['settings','Settings','settings',['settings','suppliers','departments','backup']],
    ];
    $result=[];
    foreach($groups as [$id,$label,$icon,$ids]){
        $children=[];foreach($ids as $key)if(isset($links[$key]))$children[]=$links[$key];
        if(!$children)continue;
        if(in_array($id,['dashboard','stock-levels','bin-card'],true)){$entry=$children[0];$entry['icon']=$icon;unset($entry['group']);}
        else $entry=['id'=>$id,'label'=>$label,'icon'=>$icon,'href'=>$children[0]['href'],'children'=>$children,'mobile_create'=>$links[$id==='requisitions'?'new-request':$id]['mobile_create']??false];
        if($id==='reports'){$entry['single_link']=true;$entry['href']='reports.php';}
        if($id==='dashboard')$entry['group']='Workspace';
        if($id==='settings')$entry['group']='Administration';
        $result[]=$entry;
    }
    return $result;
}
