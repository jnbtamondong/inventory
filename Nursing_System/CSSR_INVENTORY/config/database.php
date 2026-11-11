<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__.'/../../includes/database-settings.php';
$databaseSettings=nursingDatabaseSettings('cssr_inventory');
$host=$databaseSettings['host'];
$dbName=$databaseSettings['database'];
$username=$databaseSettings['user'];
$password=$databaseSettings['password'];
$port=$databaseSettings['port'];
$charset = 'utf8mb4';

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/php_errors.log');

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[DB CONNECT] ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please contact the system administrator.');
}

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function db_statement(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    return db_statement($sql, $params)->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_statement($sql, $params)->fetch();
    return $row ?: null;
}

function db_value(string $sql, array $params = []): mixed
{
    return db_statement($sql, $params)->fetchColumn();
}
