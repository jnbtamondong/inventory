<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();

$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if ($type === 'municipalities' && $id > 0) {
    $stmt = $conn->prepare("SELECT municipality_id AS id, municipality_name AS name FROM municipalities WHERE province_id = ? ORDER BY municipality_name");
    $stmt->bind_param("i", $id);
} elseif ($type === 'barangays' && $id > 0) {
    $stmt = $conn->prepare("SELECT barangay_id AS id, barangay_name AS name FROM barangays WHERE municipality_id = ? ORDER BY barangay_name");
    $stmt->bind_param("i", $id);
} else {
    echo json_encode([]);
    exit();
}

$stmt->execute();
echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
?>
