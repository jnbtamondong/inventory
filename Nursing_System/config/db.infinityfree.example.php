<?php
// InfinityFree Database Configuration
date_default_timezone_set('Asia/Manila');

define('DB_HOST', 'sql110.infinityfree.com');
define('DB_USER', 'if0_41845631');
define('DB_PASS', 'JVGH1212');
define('DB_NAME', 'if0_41845631_nursingdb');

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8');
$conn->query("SET time_zone = '+08:00'");

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
