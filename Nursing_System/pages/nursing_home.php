<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();

header('Location: dashboard.php');
exit();
