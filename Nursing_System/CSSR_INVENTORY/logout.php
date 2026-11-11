<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/logout-confirmation.php';
nursingRequireLogoutPost(csrf_token());
if (is_logged_in()) {
    log_activity('LOGOUT', 'Authentication', 'User logged out');
}
unset($_SESSION['user']);
unset($_SESSION['flash']);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => (bool) $params['secure'],
        'httponly' => (bool) $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
header('Location: /Nursing_System/pages/login.php');
exit;
