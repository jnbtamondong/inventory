<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireApiPermission('patients', 'create');

$date = $_GET['date'] ?? null;
if ($date !== null && !validDateString($date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date']);
    exit();
}

echo json_encode([
    'patient_code' => generatePatientCode($conn, $date)
]);
?>
