<?php
require_once __DIR__.'/../../includes/workflow-presentation.php';
require_once __DIR__.'/../../includes/theme-presentation.php';
// Presentation only: callers must authenticate and authorize before rendering.
function cssrUiEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function cssrUiIcon(string $name): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'box' => '<path d="m3 7 9-4 9 4v10l-9 4-9-4V7Zm0 0 9 4 9-4M12 11v10M7 5l10 4"/>',
        'inbound' => '<path d="M12 3v12m-4-4 4 4 4-4M4 15v5h16v-5"/>',
        'storage' => '<path d="M3 21V8l9-5 9 5v13M7 21V11h10v10M7 16h10M7 20h10"/>',
        'request' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v4H9zM9 12h6M9 16h4"/>',
        'issue' => '<path d="M4 5h8v14H4zM10 12h11m-4-4 4 4-4 4"/>',
        'return' => '<path d="M8 4 3 9l5 5M3 9h11a6 6 0 0 1 0 12"/>',
        'document' => '<path d="M14 3H5v18h14V8l-5-5Zm0 0v5h5M9 12h6M9 16h6"/>',
        'report' => '<path d="M4 3v18h17M8 16v-4M13 16V7M18 16v-7"/>',
        'history' => '<path d="M3 11a9 9 0 1 1 2 7M3 4v7h7M12 7v5l3 2"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5"/>',
        'settings' => '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="15" cy="17" r="3"/>',
        'search' => '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M9 21h6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'panel' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
        'plus' => '<path d="M12 4v16M4 12h16"/>',
        'edit' => '<path d="m16 3 5 5M4 20l4-1L21 6a2.1 2.1 0 0 0-3-3L5 16l-1 4ZM13 20h8"/>',
        'trash' => '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'warning' => '<path d="m12 3 10 18H2L12 3ZM12 9v5M12 17v1"/>',
        'download' => '<path d="M12 3v12m-4-4 4 4 4-4M4 17v4h16v-4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ];
    return '<svg class="cu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['box']) . '</svg>';
}

// Filter these entries using actual access rules before calling cssrUiStart().
function cssrUiModules(): array
{
    return [
        ['id'=>'dashboard','label'=>'Dashboard','icon'=>'dashboard','group'=>'Overview'],
        ['id'=>'inventory','label'=>'Inventory','icon'=>'box','group'=>'Inventory','children'=>['Item masterlist','Stock Card','Expiry monitoring']],
        ['id'=>'inbound','label'=>'Inbound','icon'=>'inbound'],
        ['id'=>'storage','label'=>'Storage Room','icon'=>'storage','group'=>'Storage'],
        ['id'=>'cssr-stock','label'=>'Stock in CSSR','icon'=>'box'],
        ['id'=>'requisitions','label'=>'Requisitions','icon'=>'request','group'=>'Requests'],
        ['id'=>'issuance','label'=>'Issuance','icon'=>'issue'],
        ['id'=>'returns','label'=>'Return Supplies','icon'=>'return'],
        ['id'=>'ppmp','label'=>'Purchase Plan (PPMP)','icon'=>'document','group'=>'Procurement'],
        ['id'=>'purchase-request','label'=>'Purchase Request','icon'=>'request'],
        ['id'=>'reports','label'=>'Reports','icon'=>'report','group'=>'Reports & History','children'=>['Periodic Reports','Consumption Report']],
        ['id'=>'history','label'=>'Transaction History','icon'=>'history'],
        ['id'=>'settings','label'=>'Settings','icon'=>'settings'],
    ];
}

function cssrNavigationHome(array $navigation, string $default = 'dashboard.php'): string
{
    $modules = array_column($navigation, null, 'id');
    if (isset($modules['dashboard'])) return $default;
    return $modules['requisitions']['href'] ?? $navigation[0]['href'] ?? $default;
}

function cssrUiStart(array $ui): void
{
    require_once __DIR__.'/request-notifications.php';
    $requestNotifications = $ui['requestNotifications'] ?? (function_exists('shared_has_permission') && function_exists('current_user')
        && cssrCanReceiveRequestNotifications(current_user() ?? [], 'shared_has_permission'));
    $requestCount = 0;
    $chatNotifications = $ui['chatNotifications'] ?? (function_exists('shared_has_permission') && function_exists('current_user')
        && cssrCanReceiveChatNotifications(current_user() ?? [], 'shared_has_permission'));
    $chatCount = 0;
    if ($chatNotifications && function_exists('db_value')) {
        try { $chatCount = cssrChatUnreadCount('db_value', (int) ($_SESSION['user_id'] ?? (current_user()['shared_user_id'] ?? 0))); }
        catch (Throwable $error) { if (function_exists('log_error')) log_error($error, 'CHAT_NOTIFICATIONS'); }
    }
    $liveNotifications = $requestNotifications || $chatNotifications;
    if ($chatNotifications && !isset($ui['notificationText'])) $ui['notificationText'] = 'Review your requests and chat updates.';
    if ($requestNotifications && !isset($ui['notificationText'])) $ui['notificationText'] = 'Review pending requests and inventory alerts.';
    if ($requestNotifications && function_exists('db_value')) {
        try {
            $counts = cssrRequestNotificationCounts('db_value', shared_has_permission('ris_requests','view') || can_view_cssr_request_lists());
            $requestCount = $counts['total'];
        } catch (Throwable $error) { if (function_exists('log_error')) log_error($error, 'REQUEST_NOTIFICATIONS'); }
    }
    $ui['home'] = cssrNavigationHome($ui['navigation'], $ui['home'] ?? 'dashboard.php');
    $asset = $ui['assets'] ?? '../assets';
    if(($ui['settings']??'')==='/Nursing_System/pages/account_settings.php')$ui['settings'].='?workspace=cssr';
    $version = (string) filemtime(__DIR__ . '/../assets/css/design-system.css');
    $scriptVersion = (string) filemtime(__DIR__ . '/../assets/js/design-system.js');
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<title><?=cssrUiEscape($ui['title'])?> | JVGH CSSR</title>
<?=nursingThemeAssets()?>
<link rel="stylesheet" href="<?=cssrUiEscape($asset)?>/css/theme.css?v=<?=filemtime(__DIR__.'/../assets/css/theme.css')?>">
<link rel="stylesheet" href="<?=cssrUiEscape($asset)?>/css/typography.css?v=<?=filemtime(__DIR__.'/../assets/css/typography.css')?>">
<?php foreach ($ui['baseStyles'] ?? [] as $stylesheet): ?><link rel="stylesheet" href="<?=cssrUiEscape($stylesheet)?>"><?php endforeach; ?>
<?php foreach ($ui['headScripts'] ?? [] as $script): ?><script src="<?=cssrUiEscape($script)?>"></script><?php endforeach; ?>
<link rel="stylesheet" href="<?=cssrUiEscape($asset)?>/css/design-system.css?v=<?=$version?>">
<?php foreach ($ui['styles'] ?? [] as $stylesheet): ?><link rel="stylesheet" href="<?=cssrUiEscape($stylesheet)?>"><?php endforeach; ?>
<script defer src="<?=cssrUiEscape($asset)?>/js/design-system.js?v=<?=$scriptVersion?>"></script>
<?php if ($liveNotifications): ?><script defer src="<?=cssrUiEscape($asset)?>/js/request-notifications.js?v=<?=filemtime(__DIR__.'/../assets/js/request-notifications.js')?>"></script><?php endif; ?>
<?php foreach ($ui['scripts'] ?? [] as $script): ?><script defer src="<?=cssrUiEscape($script)?>"></script><?php endforeach; ?>
<link rel="stylesheet" href="<?=cssrUiEscape($asset)?>/css/mobile.css?v=<?=filemtime(__DIR__.'/../assets/css/mobile.css')?>">
<link rel="stylesheet" href="/Nursing_System/public/css/workflow-ui.css?v=<?=filemtime(__DIR__.'/../../public/css/workflow-ui.css')?>">
<script defer src="/Nursing_System/public/js/workflow-ui.js?v=<?=filemtime(__DIR__.'/../../public/js/workflow-ui.js')?>"></script>
<script defer src="<?=cssrUiEscape($asset)?>/js/mobile.js?v=<?=filemtime(__DIR__.'/../assets/js/mobile.js')?>"></script>
<link rel="stylesheet" href="<?=cssrUiEscape($asset)?>/css/motion.css?v=<?=filemtime(__DIR__.'/../assets/css/motion.css')?>">
<script defer src="<?=cssrUiEscape($asset)?>/js/motion.js?v=<?=filemtime(__DIR__.'/../assets/js/motion.js')?>"></script></head>
<body class="cu-app <?=cssrUiEscape($ui['bodyClass'] ?? '')?>" <?= $liveNotifications ? 'data-cu-request-notifications="/Nursing_System/CSSR_INVENTORY/api/request-notifications.php" data-cu-initial-requests="'.$requestCount.'" data-cu-initial-chat="'.$chatCount.'"' : '' ?>><a class="cu-skip" href="#workspace">Skip to workspace</a>
<aside class="cu-sidebar" id="cu-sidebar" aria-label="Main navigation">
    <a class="cu-brand" href="<?=cssrUiEscape($ui['home'])?>"><img class="cu-brand-mark" src="/Nursing_System/public/images/branding/jvgh_seal.png" alt="JVGH seal" width="38" height="38"><span class="cu-nav-copy"><strong>JVGH <span>CSSR</span></strong><small>Inventory management</small></span></a>
    <div class="cu-sidebar-context cu-nav-copy">Joni Villanueva<br>General Hospital</div>
    <button class="cu-icon-button cu-mobile-close" type="button" data-cu-close-nav aria-label="Close navigation"><?=cssrUiIcon('close')?></button>
    <nav class="cu-navigation" aria-label="CSSR modules">
    <?php foreach ($ui['navigation'] as $item): ?>
        <?php if (isset($item['group'])): ?><p class="cu-nav-section cu-nav-copy"><?=cssrUiEscape($item['group'])?></p><?php endif; ?>
        <?php if (!empty($item['children']) && empty($item['single_link'])): ?><details class="cu-nav-group" <?=in_array($ui['active'] ?? '', array_merge([$item['id']], array_column($item['children'], 'id')), true)?'open':''?>>
            <summary aria-label="<?=cssrUiEscape($item['label'])?>" title="<?=cssrUiEscape($item['label'])?>"><?=cssrUiIcon($item['icon'])?><span class="cu-nav-copy"><?=cssrUiEscape($item['label'])?></span><?=cssrRequestNotificationBadge($liveNotifications && $item['id']==='requisitions',$requestCount+$chatCount,'notification')?><span class="cu-nav-chevron cu-nav-copy"><?=cssrUiIcon('chevron')?></span></summary>
            <div class="cu-subnav"><?php foreach ($item['children'] as $child): ?><a href="<?=cssrUiEscape($child['href'])?>" <?=($ui['active'] ?? '')===($child['id'] ?? '')?'aria-current="page"':''?>><?=cssrUiEscape($child['label'])?><?=cssrRequestNotificationBadge($requestNotifications && ($child['id'] ?? '')==='issuance',$requestCount)?><?=cssrRequestNotificationBadge($chatNotifications && ($child['id'] ?? '')==='chat',$chatCount,'chat')?></a><?php endforeach; ?></div>
        </details><?php else: ?>
        <a class="cu-nav-link" aria-label="<?=cssrUiEscape($item['label'])?>" title="<?=cssrUiEscape($item['label'])?>" href="<?=cssrUiEscape($item['href'])?>" <?=in_array($ui['active'] ?? '',array_merge([$item['id']],array_column($item['children'] ?? [],'id')),true)?'aria-current="page"':''?>><?=cssrUiIcon($item['icon'])?><span class="cu-nav-copy"><?=cssrUiEscape($item['label'])?></span></a>
        <?php endif; ?>
    <?php endforeach; ?></nav>
    <footer class="cu-sidebar-footer"><span class="cu-online-dot"></span><span class="cu-nav-copy"><?=cssrUiEscape($ui['environment'] ?? 'Hospital workspace')?></span></footer>
</aside>
<button class="cu-scrim" data-cu-close-nav tabindex="-1" aria-label="Close navigation" hidden></button>
<div class="cu-main">
    <header class="cu-topbar">
        <button class="cu-icon-button" id="cu-nav-toggle" type="button" aria-controls="cu-sidebar" aria-expanded="true" aria-label="Collapse navigation"><?=cssrUiIcon('panel')?></button>
        <time class="cu-clock" data-cu-clock aria-label="Philippine date and time"></time>
        <details class="cu-dropdown"><summary class="cu-icon-button cu-notification-trigger" aria-label="Notifications" title="Notifications"><?=cssrUiIcon('bell')?><?=cssrRequestNotificationBadge($liveNotifications,$requestCount+$chatCount,'notification')?></summary><div class="cu-dropdown-panel"><strong>Notifications</strong><?php if ($requestNotifications): ?><a class="cu-profile-link" data-cu-request-link href="/Nursing_System/CSSR_INVENTORY/pages/request_queue.php"><span data-cu-request-message><?= $requestCount ? $requestCount.' requests awaiting action' : 'No requests awaiting action' ?></span></a><?php endif; ?><?php if ($chatNotifications): ?><a class="cu-profile-link" data-cu-chat-link href="/Nursing_System/CSSR_INVENTORY/pages/chat.php"><span data-cu-chat-message><?= $chatCount ? $chatCount.' unread chat messages' : 'No unread chat messages' ?></span></a><?php endif; ?><p><?=cssrUiEscape($ui['notificationText'] ?? 'No new notifications.')?></p><?php foreach($ui['notificationLinks']??[] as $link): ?><a class="cu-profile-link" href="<?=cssrUiEscape($link['href'])?>"><?=cssrUiEscape($link['label'])?></a><?php endforeach; ?></div></details>
        <details class="cu-dropdown cu-profile"><summary><span class="cu-avatar"><?=cssrUiEscape($ui['initials'])?></span><span class="cu-profile-copy"><strong><?=cssrUiEscape($ui['user'])?></strong><small><?=cssrUiEscape($ui['role'])?></small></span><?=cssrUiIcon('chevron')?></summary><div class="cu-dropdown-panel"><strong><?=cssrUiEscape($ui['user'])?></strong><p><?=cssrUiEscape($ui['role'])?></p><a class="cu-profile-link" href="<?=cssrUiEscape($ui['settings'])?>">Account settings</a><a class="cu-profile-link" href="/Nursing_System/pages/system_menu.php">Switch workspace</a><a class="cu-profile-link" href="/Nursing_System/CSSR_INVENTORY/logout.php">Sign out</a></div></details>
    </header>
    <main class="cu-workspace <?=cssrUiEscape($ui['workspaceClass'] ?? '')?>" id="workspace" tabindex="-1">
<?php }

function cssrUiEnd(?callable $documentFooter=null): void { ?>
    </main>
</div><?php if($documentFooter)$documentFooter(); ?><div class="cu-toast" id="cu-toast" role="status" aria-live="polite" hidden></div>
</body></html>
<?php }
