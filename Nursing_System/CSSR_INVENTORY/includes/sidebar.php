<?php
$menuSections = [
    'Workspace' => [
        ['dashboard', 'Dashboard', 'dashboard.php', 'DB'],
        ['chat', 'CSSR Supply Chat', 'chat.php', 'CH'],
    ],
    'Transactions' => [
        ['stock_in', 'Inbound / Delivery', 'stock_in.php', 'IN'],
        ['item_requests', 'Department Supply Request', 'item_requests.php', 'DSR'],
        ['department_returns', 'Department Return', 'department_returns.php', 'DR'],
        ['request_queue', 'Release Queue', 'request_queue.php', 'RQ'],
        ['stock_adjustment', 'Stock Adjustment', 'stock_adjustment.php', 'SA'],
    ],
    'Storage Management' => [
        ['storage_masterlist', 'Storage Room', 'storage_masterlist.php', 'SM'],
        ['storage_requests', 'Storage Requests', 'storage_requests.php', 'SRQ'],
    ],
    'Inventory Records' => [
        ['items', 'Item Masterlist', 'items.php', 'IM'],
        ['expiry_monitoring', 'Expiry Monitoring', 'expiry_monitoring.php', 'EM'],
        ['stock_card', 'Stock Card', 'stock_card.php', 'SC'],
        ['bin_card', 'Bin Card', 'bin_card.php', 'BC'],
        ['price_list', 'Price List', 'price_list.php', 'PL'],
    ],
    'Reports and History' => [
        ['request_history', 'Request History', 'request_history.php', 'RH'],
        ['inbound_history', 'Inbound History', 'inbound_history.php', 'IH'],
        ['reports', 'Inventory Reports', 'reports.php', 'RP'],
    ],
    'Setup' => [
        ['suppliers', 'Suppliers', 'suppliers.php', 'SP'],
        ['departments', 'Departments', 'departments.php', 'DP'],
    ],
];

function sidebar_item_visible(string $key): bool
{
    if ($key === 'chat') {
        return is_logged_in();
    }
    $inventoryRecordItems = ['items', 'expiry_monitoring', 'stock_card', 'bin_card', 'price_list', 'reports'];
    $isInventoryOperator = in_array(current_user()['role'] ?? '', [ROLE_ADMIN, ROLE_HEAD, ROLE_STAFF], true) || is_cssr_department();
    if (in_array($key, $inventoryRecordItems, true) && !$isInventoryOperator) {
        return false;
    }

    if (in_array($key, ['suppliers', 'departments'], true)) {
        return in_array(current_user()['role'] ?? '', [ROLE_ADMIN, ROLE_HEAD, ROLE_STAFF], true);
    }

    if ($key === 'request_queue') {
        return can_view_cssr_request_lists()
            || shared_has_permission('cssr_inventory', 'view')
            || shared_has_permission('cssr_inventory', 'create');
    }

    if ($key === 'inbound_history') {
        return can_manage();
    }

    $permissionMap = [
        'dashboard' => ['cssr_dashboard', 'view'],
        'items' => ['cssr_inventory', 'view'],
        'expiry_monitoring' => ['cssr_inventory', 'view'],
        'suppliers' => ['cssr_inventory', 'edit'],
        'departments' => ['cssr_inventory', 'edit'],
        'stock_in' => ['cssr_inventory', 'create'],
        'get_stock' => ['cssr_inventory', 'create'],
        'stock_adjustment' => ['cssr_inventory', 'edit'],
        'storage_masterlist' => ['cssr_inventory', 'view'],
        'storage_requests' => ['cssr_inventory', 'create'],
        'department_returns' => ['ris_requests', 'create'],
        'price_list' => ['cssr_inventory', 'view'],
        'stock_out' => ['cssr_inventory', 'edit'],
        'item_requests' => ['ris_requests', 'view'],
        'request_queue' => ['cssr_inventory', 'view'],
        'request_history' => ['ris_requests', 'view'],
        'inbound_history' => ['cssr_inventory', 'view'],
        'stock_pos' => ['cssr_inventory', 'view'],
        'stock_card' => ['cssr_inventory', 'view'],
        'reports' => ['reports', 'view'],
        'bin_card' => ['cssr_inventory', 'view'],
        'settings' => ['ris_requests', 'view'],
    ];

    [$module, $action] = $permissionMap[$key] ?? ['cssr_inventory', 'view'];
    return shared_has_permission($module, $action);
}

function sidebar_icon(string $key): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'chat' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="M8 9h8M8 13h5"/>',
        'stock_in' => '<path d="M12 3v12m-4-4 4 4 4-4"/><path d="M4 19h16"/>',
        'item_requests' => '<path d="M9 5h10a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9"/><path d="m3 12 4-4v3h8v2H7v3z"/>',
        'department_returns' => '<path d="M9 7H5v-4"/><path d="M5 7a8 8 0 1 1-1 8"/>',
        'request_queue' => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
        'stock_adjustment' => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
        'storage_masterlist' => '<path d="m3 7 9-4 9 4-9 4z"/><path d="m3 7v10l9 4 9-4V7M12 11v10"/>',
        'storage_requests' => '<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h5M8 17h3"/>',
        'items' => '<path d="M4 5h16v14H4z"/><path d="M4 10h16M9 10v9"/>',
        'expiry_monitoring' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M12 14v3M12 19h.01"/>',
        'stock_card' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'bin_card' => '<path d="M3 7h18v14H3zM6 3h12l3 4H3z"/><path d="M9 12h6"/>',
        'price_list' => '<path d="M20 13 13 20l-9-9V4h7z"/><circle cx="8.5" cy="8.5" r="1.5"/>',
        'request_history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'inbound_history' => '<path d="M4 19h16M6 16h12V6H6z"/><path d="m9 10 3 3 3-3M12 13V3"/>',
        'reports' => '<path d="M5 20V10M12 20V4M19 20v-7"/>',
        'suppliers' => '<path d="M3 7h11v10H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'departments' => '<path d="M3 21h18M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6M9 10h.01M15 10h.01"/>',
        'menu' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
    ];
    $icons = [
        'dashboard' => 'grid-1x2', 'chat' => 'chat-left-text',
        'stock_in' => 'box-arrow-in-down', 'item_requests' => 'clipboard-check',
        'department_returns' => 'arrow-return-left', 'request_queue' => 'list-check',
        'stock_adjustment' => 'sliders2', 'storage_masterlist' => 'boxes',
        'storage_requests' => 'clipboard2', 'items' => 'box-seam',
        'expiry_monitoring' => 'calendar2-check', 'stock_card' => 'file-earmark-text',
        'bin_card' => 'archive', 'price_list' => 'tag', 'request_history' => 'clock-history',
        'inbound_history' => 'truck', 'reports' => 'bar-chart', 'suppliers' => 'truck',
        'departments' => 'building', 'menu' => 'grid', 'logout' => 'box-arrow-right',
    ];
    return '<i class="bi bi-' . ($icons[$key] ?? 'grid') . '" aria-hidden="true"></i>';
}
$sidebarChatUnread = 0;
if (is_logged_in()) {
    try {
        $sidebarChatUnread = (int) db_value(
            'SELECT COUNT(*) FROM chat_messages WHERE receiver_user_id=? AND read_at IS NULL',
            [(int) ($_SESSION['user_id'] ?? (current_user()['shared_user_id'] ?? 0))]
        );
    } catch (Throwable $e) {
        $sidebarChatUnread = 0;
    }
}
?>
<aside class="sidebar" id="appSidebar">
    <div class="brand">
        <div class="brand-mark brand-logo"><img src="../assets/img/jvgh-seal.png" alt="JVGH seal"></div>
        <div class="brand-copy">
            <strong>JVGH CSSR IMS</strong>
            <span>Central Supply and Sterilization Room</span>
        </div>
        <button class="sidebar-collapse" type="button" data-sidebar-collapse aria-label="Collapse navigation" title="Collapse sidebar">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3.5" y="4" width="17" height="16" rx="2.5"></rect><path d="M9 4v16"></path></svg>
        </button>
        <button class="sidebar-close" type="button" data-sidebar-close aria-label="Close navigation" title="Close sidebar">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3.5" y="4" width="17" height="16" rx="2.5"></rect><path d="M9 4v16"></path></svg>
        </button>
    </div>
    <div class="sidebar-status">
        <span></span>
        <div>
            <strong>Live Operations</strong>
            <small>CSSR inventory control center</small>
        </div>
    </div>
    <nav>
        <?php foreach ($menuSections as $section => $menuItems): ?>
            <?php $visibleItems = array_filter($menuItems, fn($item) => sidebar_item_visible($item[0])); ?>
            <?php if (!$visibleItems) { continue; } ?>
            <div class="nav-section"><?= e($section) ?></div>
            <?php foreach ($visibleItems as [$key, $label, $url, $icon]): ?>
                <?php
                $isActive = $activePage === $key || ($key === 'add_item' && $activePage === 'items' && isset($_GET['new']));
                ?>
                <a class="<?= $isActive ? 'active' : '' ?>" href="<?= e($url) ?>" title="<?= e($label) ?>"><span><?= sidebar_icon($key) ?></span><em><?= e($label) ?></em><?php if ($key === 'chat' && $sidebarChatUnread > 0): ?><b class="sidebar-chat-unread"><?= $sidebarChatUnread > 99 ? '99+' : $sidebarChatUnread ?></b><?php endif; ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>


