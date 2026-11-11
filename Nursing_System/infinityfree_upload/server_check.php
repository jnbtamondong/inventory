<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_OFF);

echo '<pre>';
echo "PHP is running\n";
echo "PHP version: " . PHP_VERSION . "\n";

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    die("Database connection object was not created.\n");
}

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error . "\n");
}

echo "Database connected\n";

$result = $conn->query("SHOW TABLES LIKE 'users'");
if (!$result) {
    die("Table check failed: " . $conn->error . "\n");
}

echo "Users table exists: " . ($result->num_rows ? 'yes' : 'no') . "\n";

$userCount = $conn->query("SELECT COUNT(*) AS total FROM users");
if (!$userCount) {
    die("Users count failed: " . $conn->error . "\n");
}

$row = $userCount->fetch_assoc();
echo "Users count: " . ($row['total'] ?? 'unknown') . "\n";
echo "Server check completed\n";
echo '</pre>';
?>
