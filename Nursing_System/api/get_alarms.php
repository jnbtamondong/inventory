<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../includes/auth.php';

requireApiPermission('patients', 'view');

// Get active alarms
$query = "SELECT 
    a.alarm_id,
    a.patient_id,
    CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) as patient_name,
    a.alarm_type,
    a.severity,
    a.description,
    a.created_at
FROM alarms a
LEFT JOIN patients p ON a.patient_id = p.patient_id
WHERE a.is_active = TRUE
ORDER BY CASE 
    WHEN a.severity = 'high' THEN 1
    WHEN a.severity = 'medium' THEN 2
    ELSE 3
END, a.created_at DESC
LIMIT 10";

$result = $conn->query($query);
$alarms = [];

while ($row = $result->fetch_assoc()) {
    $alarms[] = $row;
}

echo json_encode($alarms);
?>
