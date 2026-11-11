<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles([ROLE_ADMIN]);

header('Location: /Nursing_System/pages/admin_users.php?workspace=cssr');
exit;
