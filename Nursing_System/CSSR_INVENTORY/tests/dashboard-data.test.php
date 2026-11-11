<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard-data.php';

// Isolated in-memory database; these tests never connect to hospital databases.
$connection = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$connection->sqliteCreateFunction('YEAR', static fn($date) => (int)substr((string)$date,0,4));
$connection->sqliteCreateFunction('MONTH', static fn($date) => (int)substr((string)$date,5,2));
$connection->sqliteCreateFunction('GREATEST', static fn($a,$b) => max($a,$b));
$connection->sqliteCreateFunction('CONCAT', static fn(...$parts) => implode('', $parts));
foreach ([
    'items'=>'id INT, item_code VARCHAR(30), item_name VARCHAR(100), item_description VARCHAR(100), current_stock INT, minimum_stock INT, unit VARCHAR(20), status VARCHAR(20)',
    'stock_out'=>'issued_date DATE, quantity_issued INT',
    'stock_in'=>'id INT, inbound_no VARCHAR(50), reference_no VARCHAR(50), received_date DATE',
    'item_requests'=>'id INT, request_no VARCHAR(30), department_id INT, department_name VARCHAR(100), request_date DATE, status VARCHAR(20), released_at DATETIME',
    'item_request_details'=>'request_id INT, item_id INT',
    'department_returns'=>'id INT, department_id INT, status VARCHAR(20), received_at DATETIME',
    'department_return_details'=>'return_id INT, quantity_received INT',
] as $table=>$columns) $connection->exec("CREATE TEMPORARY TABLE {$table} ({$columns})");
$connection->exec("INSERT INTO items VALUES
    (1,'I-1','At threshold','',10,10,'Box','Active'),
    (2,'I-2','Low item','',9,10,'Pack','Active'),
    (3,'I-3','Zero stock','',0,0,'Box','Active'),
    (4,'I-4','Negative balance','',-1,10,'Box','Active'),
    (5,'I-5','No threshold','',1,0,'Pack','Active'),
    (6,'I-6','Inactive','',0,10,'Pack','Inactive')");
$connection->exec("INSERT INTO stock_out VALUES ('2025-12-31',999),('2026-01-01',20),('2026-01-31',30),('2026-10-07',10),('2027-01-01',777)");
$connection->exec("INSERT INTO stock_in VALUES (1,'IN-1','REF-1','2026-10-07'),(2,'IN-1','REF-1','2026-10-07'),(3,'','REF-2','2026-10-07'),(4,NULL,'REF-2','2026-10-07'),(5,'IN-3','REF-3','2026-10-06')");
$connection->exec("INSERT INTO item_requests VALUES
    (1,'TRN-1',1,'ER','2026-10-07','Pending',NULL),
    (2,'TRN-2',1,'ER','2026-10-06','Approved',NULL),
    (3,'TRN-3',2,'Ward','2026-10-07','Pending',NULL),
    (4,'TRN-4',1,'ER','2026-10-06','Released','2026-10-07 08:00:00'),
    (5,'TRN-5',2,'Ward','2026-10-07','Released','2026-10-07 09:00:00'),
    (6,'TRN-6',1,'ER','2026-10-07','Cancelled',NULL),
    (7,'TRN-7',1,'ER','2026-10-07','Deleted',NULL),
    (8,'TRN-8',1,'ER','2026-10-07','Released','2026-10-08 00:00:00')");
$connection->exec('INSERT INTO item_request_details VALUES (1,10),(1,10),(1,11),(2,12),(3,13)');
$connection->exec("INSERT INTO department_returns VALUES (1,1,'Received','2026-10-07 10:00:00'),(2,2,'Received','2026-10-07 10:00:00'),(3,1,'Pending',NULL),(4,1,'Received','2026-10-06 23:59:59')");
$connection->exec('INSERT INTO department_return_details VALUES (1,7),(2,9),(3,100),(4,100)');
$checks = 0;
function expectDashboard($actual, $expected, string $message): void {
    global $checks; $checks++;
    if ($actual !== $expected) throw new RuntimeException($message . ': ' . json_encode($actual));
}
$now = new DateTimeImmutable('2026-10-07 12:00:00', new DateTimeZone('Asia/Manila'));
$access = ['inventory'=>true,'requests'=>true,'all_requests'=>true,'department_id'=>1,'department_name'=>'ER'];
$data = cssrDashboardData($connection, $access, '2026', $now);
expectDashboard($data['counts'], ['total'=>5,'available'=>2,'low'=>1,'out'=>2], 'Exclusive stock categories, threshold equality, inactive exclusion');
expectDashboard(array_sum(array_slice($data['counts'],1)), $data['counts']['total'], 'Donut adds up to KPI total');
expectDashboard(count($data['monthly']), 12, 'Always twelve months');
expectDashboard($data['monthly'][0], 50, 'January aggregates item quantities');
expectDashboard($data['monthly'][1], 0, 'Missing month zero-filled');
expectDashboard(array_sum($data['monthly']), 60, 'Year boundaries exclude adjacent years');
expectDashboard($data['years'], [2027,2026,2025], 'Year selector comes from available data');
expectDashboard($data['activity'], ['requests'=>4,'pending'=>2,'released'=>2,'returns'=>16,'inbound'=>2], 'Daily activity and deduplicated delivery headers');
expectDashboard($data['pending_total'], 3, 'Pending includes approval and release stages');
expectDashboard(array_column($data['pending'],'request_no'), ['TRN-2','TRN-1','TRN-3'], 'Oldest pending requisitions first');
expectDashboard((int)$data['pending'][1]['item_count'], 2, 'Distinct item count does not count lots twice');
expectDashboard(count($data['low_items']), 3, 'Alerts include low and nonpositive stock');
$access['all_requests'] = false;
$department = cssrDashboardData($connection, $access, '2026', $now);
expectDashboard(array_column($department['pending'],'request_no'), ['TRN-2','TRN-1'], 'Department scoping');
expectDashboard($department['activity']['pending'], 1, 'Pending count is scoped to department and date');
expectDashboard($department['activity']['released'], 1, 'Release activity uses release timestamp and scope');
expectDashboard($department['activity']['returns'], 7, 'Returns count received units in accessible department');
$access['department_id'] = 0;
expectDashboard(cssrDashboardData($connection, $access, null, $now)['pending'], [], 'Unassigned restricted users do not see all departments');
$access['inventory'] = false; $access['requests'] = false;
$restricted = cssrDashboardData($connection, $access, ['2025'], $now);
expectDashboard($restricted['counts']['total'], null, 'Restricted stock does not become a misleading zero');
expectDashboard($restricted['activity']['requests'], null, 'Restricted requests are not queried');
expectDashboard($restricted['low_items'], [], 'No unauthorized inventory details');
expectDashboard($restricted['year'], 2026, 'Array year safely falls back');
$access = ['inventory'=>true,'requests'=>true,'all_requests'=>true];
expectDashboard(cssrDashboardData($connection, $access, '2025', $now)['monthly'][11], 999, 'Historical year selection changes data');
expectDashboard(cssrDashboardData($connection, $access, '2025 OR 1=1', $now)['year'], 2026, 'Invalid year falls back');
foreach (['items','stock_out','stock_in','item_requests','item_request_details','department_returns','department_return_details'] as $table) $connection->exec("DELETE FROM {$table}");
$empty = cssrDashboardData($connection, $access, null, $now);
expectDashboard($empty['counts'], ['total'=>0,'available'=>0,'low'=>0,'out'=>0], 'Empty inventory');
expectDashboard($empty['pending'], [], 'Empty requests');
expectDashboard(array_sum($empty['monthly']), 0, 'Empty issuance');
echo "Passed {$checks} dashboard data checks using an isolated in-memory database.\n";
