<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

requireApiPermission('patients', 'edit');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405); header('Allow: POST'); exit;
}

header('Content-Type: application/json');

if (isset($_POST['alarm_id'])) {
    $alarm_id = intval($_POST['alarm_id']);
    $user_id = $_SESSION['user_id'];

    if ($alarm_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid alarm ID']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE alarms SET is_active = FALSE, resolved_by = ?, resolved_at = NOW() WHERE alarm_id = ? AND is_active = TRUE");
    $stmt->bind_param("ii", $user_id, $alarm_id);
    
    if ($stmt->execute()) {
        logSecurityEvent($conn, 'Alarm Resolved', 'Alarm ID ' . $alarm_id . ' resolved');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Unable to resolve alarm']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing alarm ID']);
}
?>
