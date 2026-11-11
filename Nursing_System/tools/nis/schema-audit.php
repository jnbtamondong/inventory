<?php
// Read-only metadata inspection. Never prints patient rows or credentials.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../includes/database-settings.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$settings=nursingDatabaseSettings('nursing_system');
try {
    $conn=new mysqli($settings['host'],$settings['user'],$settings['password'],$settings['database'],$settings['port']);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    echo json_encode(['connection'=>'unavailable','live_schema_verified'=>false]),"\n";
    exit(1);
}
$tables = ['patients','beds','patient_movements','patient_tracking','triage','or_schedule','delivery_room_records','micu_records','nicu_records','role_permissions','hospital_census'];
$audit = [];
foreach ($tables as $table) {
    $exists = $conn->query("SHOW TABLES LIKE '$table'")->num_rows > 0;
    if (!$exists) { $audit[$table] = ['missing'=>true]; continue; }
    $status=$conn->query("SHOW TABLE STATUS LIKE '$table'")->fetch_assoc();
    $audit[$table] = ['engine'=>$status['Engine']??null,'columns'=>$conn->query("SHOW COLUMNS FROM `$table`")->fetch_all(MYSQLI_ASSOC),
        'indexes'=>$conn->query("SHOW INDEX FROM `$table`")->fetch_all(MYSQLI_ASSOC)];
}
echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
