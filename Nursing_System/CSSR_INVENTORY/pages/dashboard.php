<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/design-system.php';
require_once __DIR__ . '/../includes/design-navigation.php';
require_once __DIR__ . '/../includes/dashboard-data.php';
require_once __DIR__ . '/../includes/dashboard-view.php';

$user = current_user();
$navigation = cssrDashboardNavigation();
if (!in_array('dashboard', array_column($navigation, 'id'), true)) {
    header('Location: ' . cssrNavigationHome($navigation));
    exit;
}
$inventoryAccess = shared_has_permission('cssr_inventory', 'view');
$requestAccess = shared_has_permission('ris_requests', 'view');
$allRequests = shared_has_permission('cssr_inventory', 'edit') || shared_has_permission('ris_requests', 'approve');
$data = cssrDashboardData(db(), [
    'inventory'=>$inventoryAccess,
    'requests'=>$requestAccess,
    'all_requests'=>$allRequests,
    'department_id'=>(int)($user['department_id'] ?? 0),
    'department_name'=>$user['department_name'] ?? '',
], $_GET['year'] ?? null);
$displayName = trim((string)($user['full_name'] ?? '')) ?: (string)($user['username'] ?? 'User');
$nameParts = preg_split('/\\s+/u', $displayName) ?: ['U'];
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $part) $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
$roleLabel = ucwords(strtolower(str_replace('_', ' ', (string)($user['role'] ?? ''))));
$roleLabel = str_replace('Cssr', 'CSSR', $roleLabel);
$notificationText = $requestAccess
    ? number_format($data['pending_total']) . ' requisitions await approval or release in your accessible departments.'
    : 'Request notifications are unavailable for your role.';
cssrRenderDashboard($data, [
    'title'=>'Dashboard','home'=>'dashboard.php','active'=>'dashboard','assets'=>'../assets',
    'navigation'=>$navigation,
    'searchAction'=>$inventoryAccess ? 'items.php' : ($requestAccess ? 'requisitions.php' : 'dashboard.php'),
    'searchPlaceholder'=>$inventoryAccess ? 'Search inventory...' : 'Search requests...',
    'initials'=>strtoupper($initials),'user'=>$displayName,'role'=>$roleLabel,
    'settings'=>'/Nursing_System/pages/account_settings.php','environment'=>'Hospital workspace',
    'notificationText'=>$notificationText,
], [
    'inventory'=>$inventoryAccess ? 'items.php' : null,
    'requests'=>$requestAccess ? 'requisitions.php' : null,
    'request_detail'=>$requestAccess ? 'requisitions.php?view=' : null,
]);
