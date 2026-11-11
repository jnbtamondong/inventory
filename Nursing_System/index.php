<?php
// Index - Redirect to login or dashboard
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: pages/login.php');
}
exit();
?>
