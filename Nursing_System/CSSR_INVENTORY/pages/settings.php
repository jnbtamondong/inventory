<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Location: /Nursing_System/pages/account_settings.php?workspace=cssr');
exit;
