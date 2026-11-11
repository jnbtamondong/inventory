<?php
/** Fresh, empty XAMPP databases only. Never restores private operational data. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require_once $root.'/includes/database-settings.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function developmentSql(mysqli $db, string $sql): void {
    $db->multi_query($sql);
    do { if ($result = $db->store_result()) $result->free(); }
    while ($db->more_results() && $db->next_result());
}
try {
    $mode = $argv[1] ?? '';
    if ($mode === '--nursing') {
        putenv('NURSING_RUN_SCHEMA_SETUP=1');
        require $root.'/config/db.php';
        require $root.'/includes/auth.php';
        require $root.'/includes/patient_helpers.php';
        require $root.'/includes/clinical_modules.php';
        require $root.'/includes/nursing_reports.php';
        require $root.'/includes/nursing_requests.php';
        require $root.'/includes/employee_documents_schema.php';
        foreach (['ensureUserRoleSchema','ensureDefaultAdminUser','ensureStatusTrackingSchema','ensurePatientWorkflowSchema','ensureErRecordTriageSchema','ensureAdmittedPatientSchema','ensureClinicalModuleSchema','nursingReportEnsureSchema','nsScheduleSchema','nrSchema','ensureEmployeeDocumentsSchema'] as $function) {
            $result = $function($conn);
            if ($result === false || is_string($result)) throw new RuntimeException($function.' failed.');
        }
        foreach (['20261026_patient_registration.up.sql','20261026_er_live_tracker.up.sql','20261026_request_tracking_up.sql'] as $file) developmentSql($conn, file_get_contents($root.'/database/migrations/'.$file));
        require $root.'/includes/locations.php';
        if ((int)$conn->query('SELECT COUNT(*) FROM provinces')->fetch_row()[0] === 0 && insertLocationData($conn) !== true) throw new RuntimeException('Location initialization failed.');
        echo "Nursing schemas, migrations, locations and development administrator initialized.\n";
        exit;
    }
    if ($mode === '--cssr') {
        putenv('NURSING_RUN_SCHEMA_SETUP=1');
        require $root.'/CSSR_INVENTORY/includes/auth.php';
        // These legacy helpers log some failures; verify key resulting tables below.
        foreach (['item_lots','item_request_details','item_price_list','item_images','item_lot_images','chat_messages','login_attempts'] as $table) db_value('SELECT COUNT(*) FROM '.$table);
        if (!column_exists('items','barcode') || !column_exists('stock_in','inbound_no')) throw new RuntimeException('CSSR schema upgrade incomplete; inspect its logs.');
        echo "CSSR schemas initialized. Procurement initializes on its first authorized visit.\n";
        exit;
    }
    if ($mode !== '--initialize') {
        echo "Fresh development installation only:\n  php tools/setup-development.php --initialize\nExisting nonempty databases are refused.\n";
        exit(1);
    }
    if (getenv('NURSING_APP_ENV') === 'production') throw new RuntimeException('Development setup is disabled in production.');
    $connections = [];
    foreach (['nursing_system','cssr_inventory'] as $name) {
        $settings = nursingDatabaseSettings($name);
        if ($settings['database'] !== $name) throw new RuntimeException('Fresh setup requires the standard database names.');
        $db = new mysqli($settings['host'],$settings['user'],$settings['password'],'',$settings['port']);
        $db->set_charset('utf8mb4');
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=?');
        $stmt->bind_param('s',$name);$stmt->execute();
        if ((int)$stmt->get_result()->fetch_row()[0] !== 0) throw new RuntimeException($name.' is not empty. No schemas imported. Use a separate fresh development MySQL instance.');
        $connections[$name] = $db;
    }
    foreach ($connections as $name => $db) {
        $db->query('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $db->select_db($name);
        $db->query('SET default_storage_engine=InnoDB');
        developmentSql($db,file_get_contents($root.($name === 'nursing_system' ? '/database/schema.sql' : '/CSSR_INVENTORY/database/schema.sql')));
        echo $name." base schema imported.\n";
    }
    foreach (['--nursing','--cssr'] as $phase) {
        $process = proc_open([PHP_BINARY,__FILE__,$phase],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,$root);
        if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Initialization stopped in '.$phase.'. Inspect the error and application logs before retrying.');
    }
    echo "Development installation ready. Sign in locally with admin / admin123 and change the password.\n";
} catch (Throwable $error) {
    fwrite(STDERR,'Setup failed: '.$error->getMessage()."\n");exit(1);
}
