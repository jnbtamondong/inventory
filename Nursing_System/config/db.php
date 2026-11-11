<?php
// Local Database Configuration
date_default_timezone_set('Asia/Manila');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'php_errors.log');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__.'/../includes/database-settings.php';
$databaseSettings=nursingDatabaseSettings('nursing_system');
define('DB_HOST', $databaseSettings['host']);
define('DB_USER', $databaseSettings['user']);
define('DB_PASS', $databaseSettings['password']);
define('DB_NAME', $databaseSettings['database']);
define('DB_PORT', $databaseSettings['port']);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $conn->set_charset("utf8mb4");
    $conn->query("SET time_zone = '+08:00'");
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('System temporarily unavailable. Please contact the administrator.');
}
?>
