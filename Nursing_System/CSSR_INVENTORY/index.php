<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . cssr_landing_page());
    exit;
}

header('Location: /Nursing_System/pages/login.php');
exit;
