<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(nurseRoles());
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
$currentWaitSql = patientCurrentWaitMinutesSql('p');
$triageLevelOptions = triageLevelOptions();

$patient_id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Save triage
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_triage'])) {
    $patient_id = intval($_POST['patient_id'] ?? 0);
    $triage_level = $_POST['triage_level'];
    $chief_complaint = trim($_POST['chief_complaint']);
    $assessment = trim($_POST['assessment'] ?? '');

    $patient_check = $conn->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
    $patient_check->bind_param("i", $patient_id);
    $patient_check->execute();
    $patient_exists = $patient_check->get_result()->fetch_assoc();

    if (!$patient_exists) {
        $error = 'Patient no longer exists. Please select a patient from the current list.';
    } else {
        // Vital signs JSON
        $vital_signs = [
            'temperature' => $_POST['temperature'] ?? '',
            'blood_pressure' => $_POST['blood_pressure'] ?? '',
            'heart_rate' => $_POST['heart_rate'] ?? '',
            'respiratory_rate' => $_POST['respiratory_rate'] ?? ''
        ];
        
        $vitals_json = json_encode($vital_signs);
        $user_id = $_SESSION['user_id'];
        
        // Check if triage already exists
        $check = $conn->prepare("SELECT triage_id FROM triage WHERE patient_id = ?");
        $check->bind_param("i", $patient_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        
        if ($existing) {
            $stmt = $conn->prepare("UPDATE triage SET triage_level = ?, chief_complaint = ?, assessment = ?, vital_signs = ? WHERE patient_id = ?");
            $stmt->bind_param("ssssi", $triage_level, $chief_complaint, $assessment, $vitals_json, $patient_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO triage (patient_id, triage_level, chief_complaint, assessment, vital_signs, assigned_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssi", $patient_id, $triage_level, $chief_complaint, $assessment, $vitals_json, $user_id);
        }
        
        if ($stmt->execute()) {
            // Update patient status and record the time spent before this status change.
            $status = $triage_level == 'black' ? 'expired' : 'waiting_for_consultation';
            $statusUpdateResult = updatePatientStatus($conn, $patient_id, $status, $_SESSION['user_id']);
            if ($statusUpdateResult !== true) {
                $error = $statusUpdateResult;
            }
            
            if (!$error) {
                header('Location: triage.php?saved=1');
                exit;
            }
        } else {
            $error = 'Failed to save triage information';
        }
    }
}

// Get patients for triage
$patients_query = "SELECT 
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
WHERE p.status IN ('" . DEFAULT_PATIENT_STATUS . "', 'waiting_for_consultation')
AND t.triage_id IS NULL
ORDER BY CASE 
    WHEN t.triage_level = 'red' THEN 1
    WHEN t.triage_level = 'yellow' THEN 2
    WHEN t.triage_level = 'green' THEN 3
    WHEN t.triage_level = 'black' THEN 4
    ELSE 4
END, p.arrival_time ASC";

$patients = $conn->query($patients_query)->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['saved'])) {
    $success = 'Triage information saved successfully';
}

// Get selected patient details for editing
$selected_patient = null;
$selected_triage = null;
if ($patient_id) {
    $stmt = $conn->prepare("
        SELECT
            p.*,
            CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) as patient_name,
            t.triage_id,
            t.triage_level,
            t.chief_complaint,
            t.assessment,
            t.vital_signs,
            t.assigned_by,
            t.triage_time as assessment_time
        FROM patients p
        LEFT JOIN triage t ON p.patient_id = t.patient_id
        WHERE p.patient_id = ?
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $selected_patient = $result;
    if ($result && $result['triage_id']) {
        $selected_triage = $result;
    } elseif (!$result) {
        $error = 'Patient not found. Please select a patient from the current list.';
        $patient_id = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Triage Management - Nursing Service System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
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

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                <!-- Patients List -->
                <section class="patients-section">
                    <h3>👥 Patients Waiting for Triage</h3>
                    <div style="max-height: 600px; overflow-y: auto;">
                        <div class="table-responsive">
                            <table class="table" style="font-size: 13px;">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Queue No.</th>
                                        <th>Wait</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($patients)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No patients</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($patients as $p): ?>
                                            <tr class="patient-row triage-<?php echo htmlspecialchars($p['triage_level'] ?? 'none'); ?> <?php echo (int) ($p['wait_time'] ?? 0) >= 30 ? 'wait-alert-row' : ''; ?>" style="cursor: pointer;">
                                                <td><?php echo htmlspecialchars($p['patient_name']); ?></td>
                                                <td><strong><?php echo htmlspecialchars(formatQueueNumber($p['queue_number'] ?? null)); ?></strong></td>
                                                <td><span class="wait-time <?php echo (int) ($p['wait_time'] ?? 0) >= 30 ? 'wait-long' : ''; ?>"><?php echo htmlspecialchars(formatWaitTime($p['wait_time'])); ?></span></td>
                                                <td>
                                                    <a href="triage.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm" style="background: #2196F3; color: white; padding: 4px 8px; font-size: 11px;">
                                                        <?php echo $p['triage_level'] ? 'Edit' : 'Add'; ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Triage Form -->
                <?php if ($selected_patient): ?>
                    <section class="patients-section">
                        <h3>⚕️ Triage Assessment: <?php echo htmlspecialchars($selected_patient['patient_name']); ?></h3>
                        <form method="POST">
                            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($selected_patient['patient_id']); ?>">
                            
                            <div class="form-group">
                                <label for="triage-level">Triage Level *</label>
                                <select id="triage-level" name="triage_level" required>
                                    <option value="">-- Select Level --</option>
                                    <?php foreach ($triageLevelOptions as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo ($selected_triage['triage_level'] ?? '') == $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="complaint">Chief Complaint *</label>
                                <textarea id="complaint" name="chief_complaint" required><?php echo htmlspecialchars($selected_triage['chief_complaint'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="assessment">Assessment</label>
                                <textarea id="assessment" name="assessment"><?php echo htmlspecialchars($selected_triage['assessment'] ?? ''); ?></textarea>
                            </div>

                            <h4>Vital Signs</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="temp">Temperature (°C)</label>
                                    <input type="number" id="temp" name="temperature" step="0.1" placeholder="e.g., 37.5" value="<?php echo $selected_triage && isset($selected_triage['vital_signs']) ? (json_decode($selected_triage['vital_signs'], true)['temperature'] ?? '') : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="bp">Blood Pressure</label>
                                    <input type="text" id="bp" name="blood_pressure" placeholder="e.g., 120/80" value="<?php echo $selected_triage && isset($selected_triage['vital_signs']) ? (json_decode($selected_triage['vital_signs'], true)['blood_pressure'] ?? '') : ''; ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="hr">Heart Rate (bpm)</label>
                                    <input type="number" id="hr" name="heart_rate" placeholder="e.g., 72" value="<?php echo $selected_triage && isset($selected_triage['vital_signs']) ? (json_decode($selected_triage['vital_signs'], true)['heart_rate'] ?? '') : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="rr">Respiratory Rate</label>
                                    <input type="number" id="rr" name="respiratory_rate" placeholder="e.g., 16" value="<?php echo $selected_triage && isset($selected_triage['vital_signs']) ? (json_decode($selected_triage['vital_signs'], true)['respiratory_rate'] ?? '') : ''; ?>">
                                </div>
                            </div>

                            <button type="submit" name="save_triage" class="btn btn-primary">💾 Save Triage</button>
                            <a href="triage.php" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
                        </form>
                    </section>
                <?php else: ?>
                    <section class="patients-section">
                        <h3>⚕️ Select a Patient</h3>
                        <p>Select a patient from the list to perform triage assessment.</p>
                    </section>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../public/js/sidebar.js"></script>
    <script src="../public/js/dashboard.js"></script>
</body>
</html>
