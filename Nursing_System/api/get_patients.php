<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/nis-tracker.php';

requireApiPermission('patients', 'view');
$units=nisTrackerDepartments();
if(!$units){echo json_encode([]);exit;}
$marks=implode(',',array_fill(0,count($units),'?'));
$trackerFrom=nisTrackerPatientFrom();
$activeStatuses = "'" . implode("','", activePatientStatuses()) . "'";
$currentWaitSql = patientCurrentWaitMinutesSql('p');

// Get active patients
$query = "SELECT 
    p.patient_id, 
    p.queue_number,
    CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) as patient_name, 
    p.arrival_time,
    $currentWaitSql as wait_time,
    tr.triage_level,
    tr.chief_complaint,
    p.status
$trackerFrom
INNER JOIN patients p ON p.patient_id=t.patient_id
LEFT JOIN triage tr ON p.patient_id = tr.patient_id
WHERE p.status IN ($activeStatuses) AND t.department IN ($marks)
ORDER BY p.queue_date ASC, p.queue_number ASC, p.arrival_time ASC";

$statement=$conn->prepare($query);
$statement->bind_param(str_repeat('s',count($units)),...$units);
$statement->execute();$result=$statement->get_result();
$patients = [];

while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

echo json_encode($patients);
?>
