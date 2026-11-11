<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stock-approval.php';

// Dedicated disposable database only; never load application bootstrap or live data.
$dsn = 'mysql:host=127.0.0.1;port=' . (getenv('REQUEST_TEST_PORT') ?: '3306');
$user = getenv('REQUEST_TEST_USER') ?: 'root';
$password = getenv('REQUEST_TEST_PASSWORD') ?: '';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
if (($argv[1] ?? '') === 'worker') {
    $db = new PDO($dsn . ';dbname=' . $argv[2], $user, $password, $options);
    $db->beginTransaction();
    // Establish an old REPEATABLE READ snapshot before the first approval commits.
    $db->query('SELECT * FROM stock_reservations')->fetchAll();
    echo "READY\n"; flush();
    try {
        cssrLockApprovalStock($db, 1, 1, 1, 'Test item');
        $db->exec('INSERT INTO stock_reservations VALUES(2,1,1,"ACTIVE",NULL)');
        $db->commit();
        echo "APPROVED\n";
    } catch (RuntimeException $error) {
        $db->rollBack();
        if (!str_contains($error->getMessage(), 'insufficient unreserved stock')) throw $error;
        echo "INSUFFICIENT\n";
    }
    exit;
}
$db = new PDO($dsn, $user, $password, $options);
$database = 'cssr_approval_test_' . bin2hex(random_bytes(6));
$process = null;
try {
    $db->exec("CREATE DATABASE `$database`");
    $db->exec("USE `$database`");
    $db->exec('CREATE TABLE item_lots(id INT PRIMARY KEY,item_id INT,lot_number VARCHAR(80),quantity_available INT,storage_location VARCHAR(30),status VARCHAR(30),expiry_date DATE) ENGINE=InnoDB');
    $db->exec('CREATE TABLE stock_reservations(id INT PRIMARY KEY,item_lot_id INT,quantity INT,status VARCHAR(30),expires_at DATETIME,INDEX(item_lot_id,status)) ENGINE=InnoDB');
    $db->exec('INSERT INTO item_lots VALUES(1,1,"LOT-1",1,"CSSR","AVAILABLE",NULL)');
    try {
        cssrLockApprovalStock($db, 1, 1, 1, 'Test item');
        throw new RuntimeException('Missing transaction was accepted');
    } catch (LogicException $expected) {}
    $db->beginTransaction();
    cssrLockApprovalStock($db, 1, 1, 1, 'Test item');
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', $database], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    if (!is_resource($process) || trim((string) fgets($pipes[1])) !== 'READY') throw new RuntimeException('Worker did not start');
    // Worker has started its transaction while the first approver holds the lock.
    $db->exec('INSERT INTO stock_reservations VALUES(1,1,1,"ACTIVE",NULL)');
    $db->commit();
    $result = trim(stream_get_contents($pipes[1]));
    $errors = stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) fclose($pipe);
    $exit = proc_close($process); $process = null;
    if ($exit !== 0 || $result !== 'INSUFFICIENT') throw new RuntimeException('Competing approval was not rejected: ' . $result . $errors);
    if ((int) $db->query('SELECT SUM(quantity) FROM stock_reservations')->fetchColumn() !== 1) throw new RuntimeException('Stock was over-reserved');
    // A cancelled reservation frees stock; a rolled-back approval leaves none behind.
    $db->exec('UPDATE stock_reservations SET status="CANCELLED"');
    $db->beginTransaction();
    cssrLockApprovalStock($db, 1, 1, 1, 'Test item');
    $db->exec('INSERT INTO stock_reservations VALUES(3,1,1,"ACTIVE",NULL)');
    $db->rollBack();
    if ((int) $db->query('SELECT COUNT(*) FROM stock_reservations WHERE status="ACTIVE"')->fetchColumn() !== 0) throw new RuntimeException('Rollback leaked a reservation');
    echo "Passed: concurrent last-piece approval, stale snapshot, transaction guard, cancellation and rollback.\n";
} finally {
    if ($db->inTransaction()) $db->rollBack();
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $db->exec("DROP DATABASE IF EXISTS `$database`");
}
