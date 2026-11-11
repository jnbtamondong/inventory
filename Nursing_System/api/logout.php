<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/logout-confirmation.php';
nursingRequireLogoutPost(csrfToken());

logout();
?>
