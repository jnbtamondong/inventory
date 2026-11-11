<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/request-notifications.php';
$lastInteraction = $_SESSION['last_activity'] ?? time();
require_login();
// Background polling must not extend an otherwise idle user's session.
$_SESSION['last_activity'] = $lastInteraction;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405); header('Allow: GET'); echo json_encode(['ok'=>false]); exit;
}
$requestAccess = cssrCanReceiveRequestNotifications(current_user() ?? [], 'shared_has_permission');
$chatAccess = cssrCanReceiveChatNotifications(current_user() ?? [], 'shared_has_permission');
$sharedUserId = (int) ($_SESSION['user_id'] ?? (current_user()['shared_user_id'] ?? 0));
if ((!$requestAccess && !$chatAccess) || $sharedUserId <= 0) {
    http_response_code(403); echo json_encode(['ok'=>false]); exit;
}
session_write_close();
try {
    $counts = $requestAccess ? cssrRequestNotificationCounts('db_value', shared_has_permission('ris_requests','view') || can_view_cssr_request_lists())
        : ['department'=>0,'storage'=>0,'returns'=>0,'total'=>0];
    $counts['chat'] = $chatAccess ? cssrChatUnreadCount('db_value', $sharedUserId) : 0;
    echo json_encode(['ok'=>true,'counts'=>$counts,'access'=>['requests'=>$requestAccess,'chat'=>$chatAccess]]);
} catch (Throwable $error) {
    log_error($error, 'REQUEST_NOTIFICATIONS');
    http_response_code(500); echo json_encode(['ok'=>false]);
}
