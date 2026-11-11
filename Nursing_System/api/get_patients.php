<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
$activeStatuses = "'" . implode("','", activePatientStatuses()) . "'";
$currentWaitSql = patientCurrentWaitMinutesSql('p');

// Get active patients
$query = "SELECT 
    p.patient_id, 
    p.queue_number,
    CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) as patient_name, 
    p.arrival_time,
    $currentWaitSql as wait_time,
    t.triage_level,
    t.chief_complaint,
    p.status
FROM patients p
LEFT JOIN triage t ON p.patient_id = t.patient_id
WHERE p.status IN ($activeStatuses)
ORDER BY CASE 
    WHEN t.triage_level = 'red' THEN 1
    WHEN t.triage_level = 'yellow' THEN 2
    WHEN t.triage_level = 'green' THEN 3
    WHEN t.triage_level = 'black' THEN 4
    ELSE 4
END, p.arrival_time ASC";

$result = $conn->query($query);
$patients = [];

while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

echo json_encode($patients);
?>
