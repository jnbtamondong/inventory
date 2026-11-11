<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(admissionRoles());
ensurePatientWorkflowSchema($conn);
$bedSyncResult = ensureEmergencyRoomBeds($conn);
$activeStatuses = "'" . implode("','", activePatientStatuses()) . "'";

$action = $_GET['action'] ?? 'list';
$bed_id = $_GET['id'] ?? null;
$error = '';
$success = '';
if ($bedSyncResult !== true) {
    $error = $bedSyncResult;
}

// Update bed status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_bed'])) {
    $bed_id = intval($_POST['bed_id']);
    $status = $_POST['status'] ?? 'available';
    $patient_id = !empty($_POST['patient_id']) ? intval($_POST['patient_id']) : NULL;

    $bedCheck = $conn->prepare("SELECT status FROM beds WHERE bed_id = ?");
    $bedCheck->bind_param("i", $bed_id);
    $bedCheck->execute();
    $currentBed = $bedCheck->get_result()->fetch_assoc();

    if (!$currentBed) {
        $error = 'Bed not found';
    } elseif (!in_array($currentBed['status'], ['available', 'maintenance'], true)) {
        $error = 'Only available or maintenance beds can be updated from Bed Management';
    } else {
        if ($patient_id) {
            $patientCheck = $conn->prepare("SELECT patient_id FROM patients WHERE patient_id = ? AND status = 'admit_inpatient'");
            $patientCheck->bind_param("i", $patient_id);
            $patientCheck->execute();
            $admissionPatient = $patientCheck->get_result()->fetch_assoc();

            if (!$admissionPatient) {
                $error = 'Only patients with Admission disposition can be assigned to a bed.';
            }
        }

        if (!$error && $patient_id) {
            $status = 'occupied';
        } elseif (!$error && !in_array($status, ['available', 'maintenance'], true)) {
            $status = 'available';
        }

        if (!$error) {
            // Update bed
            $stmt = $conn->prepare("UPDATE beds SET status = ?, patient_id = ? WHERE bed_id = ?");
            $stmt->bind_param("sii", $status, $patient_id, $bed_id);

            if ($stmt->execute()) {
                if ($patient_id) {
                    $stmt = $conn->prepare("UPDATE patients SET current_bed = ? WHERE patient_id = ?");
                    $stmt->bind_param("ii", $bed_id, $patient_id);
                    $stmt->execute();
                }

                $success = 'Bed updated successfully';
            } else {
                $error = 'Failed to update bed';
            }
        }
    }
}

// Get all beds with patient info
$beds_query = "SELECT 
    b.*,
    COALESCE(CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')), 'Available') as patient_name,
    p.patient_code,
    p.age,
    p.gender,
    p.phone,
    p.status as patient_status,
    p.patient_id,
    u.first_name as nurse_name
FROM beds b
LEFT JOIN patients p ON b.patient_id = p.patient_id
LEFT JOIN users u ON b.assigned_nurse = u.user_id
ORDER BY b.room_number, b.bed_number";

$beds = $conn->query($beds_query)->fetch_all(MYSQLI_ASSOC);

// Get patients for assignment
$patients_available = $conn->query("SELECT patient_id, CONCAT(last_name, ', ', first_name, IFNULL(CONCAT(' ', middle_name), '')) as patient_name FROM patients WHERE current_bed IS NULL AND status = 'admit_inpatient'")->fetch_all(MYSQLI_ASSOC);

// Get available nurses
$nurses = $conn->query("SELECT user_id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role IN ('nurse', 'chief_nurse', 'nurse_supervisor', 'head_nurse', 'staff_nurse') AND is_active = TRUE")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bed Management - Nursing Service System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .bed-management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .bed-management-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border-top: 4px solid;
        }
        
        .bed-management-card.available {
            border-top-color: #4CAF50;
        }
        
        .bed-management-card.occupied {
            border-top-color: #ff9800;
        }
        
        .bed-management-card.maintenance {
            border-top-color: #999;
        }
        
        .bed-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .bed-info {
            margin: 10px 0;
            font-size: 14px;
        }
        
        .bed-info label {
            display: block;
            font-weight: 600;
            color: #666;
            margin-bottom: 3px;
        }
        
        .bed-info span {
            color: #333;
            display: block;
            margin-bottom: 8px;
        }
        
        .bed-edit-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-brand">
            <h1>🏥 Nursing Service System</h1>
        </div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <h2>🛏️ Bed Management</h2>

            <div class="bed-management-grid">
                <?php foreach ($beds as $bed): ?>
                    <div class="bed-management-card <?php echo htmlspecialchars($bed['status']); ?>">
                        <div class="bed-header"><?php echo htmlspecialchars($bed['bed_number']); ?></div>
                        <div class="bed-info">
                            <label>Room:</label>
                            <span><?php echo htmlspecialchars($bed['room_number']); ?></span>
                        </div>
                        <div class="bed-info">
                            <label>Type:</label>
                            <span><?php echo htmlspecialchars($bed['bed_type']); ?></span>
                        </div>
                        <div class="bed-info">
                            <label>Status:</label>
                            <span style="padding: 4px 8px; border-radius: 4px; background: <?php echo $bed['status'] == 'available' ? '#e8f5e9' : ($bed['status'] == 'occupied' ? '#fff3e0' : '#eeeeee'); ?>; color: <?php echo $bed['status'] == 'available' ? '#2e7d32' : ($bed['status'] == 'occupied' ? '#e65100' : '#666'); ?>; font-weight: 600;">
                                <?php echo ucfirst(htmlspecialchars($bed['status'])); ?>
                            </span>
                        </div>
                        <?php if ($bed['patient_id']): ?>
                            <div class="bed-info">
                                <label>Patient:</label>
                                <span><?php echo htmlspecialchars($bed['patient_name']); ?></span>
                            </div>
                            <div class="bed-info">
                                <label>Patient ID:</label>
                                <span><?php echo htmlspecialchars($bed['patient_code'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="bed-info">
                                <label>Details:</label>
                                <span>
                                    <?php echo htmlspecialchars(($bed['age'] ?? 'N/A') . ' yrs / ' . ($bed['gender'] ?? 'N/A')); ?><br>
                                    <?php echo htmlspecialchars($bed['phone'] ?? 'No phone'); ?><br>
                                    <?php echo htmlspecialchars(statusLabel($bed['patient_status'] ?? '')); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if ($bed['assigned_nurse']): ?>
                            <div class="bed-info">
                                <label>Assigned Nurse:</label>
                                <span><?php echo htmlspecialchars($bed['nurse_name']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($bed['status'] == 'available' || $bed['status'] == 'maintenance'): ?>
                            <form method="POST" class="bed-edit-form">
                                <input type="hidden" name="bed_id" value="<?php echo $bed['bed_id']; ?>">

                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label for="status-<?php echo $bed['bed_id']; ?>" style="font-size: 12px;">Bed Menu</label>
                                    <select id="status-<?php echo $bed['bed_id']; ?>" name="status" style="padding: 6px; font-size: 12px;">
                                        <option value="available" <?php echo $bed['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="maintenance" <?php echo $bed['status'] == 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                                    </select>
                                </div>

                                <?php if ($bed['status'] == 'available' && !empty($patients_available)): ?>
                                    <div class="form-group" style="margin-bottom: 10px;">
                                        <label for="patient-<?php echo $bed['bed_id']; ?>" style="font-size: 12px;">Assign Patient</label>
                                        <select id="patient-<?php echo $bed['bed_id']; ?>" name="patient_id" style="padding: 6px; font-size: 12px;">
                                            <option value="">-- Select Patient --</option>
                                            <?php foreach ($patients_available as $p): ?>
                                                <option value="<?php echo $p['patient_id']; ?>"><?php echo htmlspecialchars($p['patient_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <button type="submit" name="update_bed" class="btn btn-sm btn-primary" style="width: 100%;">Update</button>
                            </form>
                        <?php elseif ($bed['status'] == 'occupied'): ?>
                            <div class="bed-edit-form">
                                <span style="font-size: 12px; color: #666;">Occupied beds are read-only here.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
