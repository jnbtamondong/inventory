<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/opd_helpers.php';
require_once '../includes/clinical_modules.php';

requireLogin();
requireRole(array_merge(nurseRoles(), doctorRoles(), admissionRoles()));
requireDepartment(['WARD_2F', 'WARD_3F', 'ICU', 'NICU']);
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
ensureWardBeds($conn);
ensureOpdSchema($conn);
ensureClinicalModuleSchema($conn);

$error = '';
$success = '';
consumeFlashMessages($error, $success);

function wardMovementPatientLabel(array $patient): string {
    $name = trim(($patient['last_name'] ?? '') . ', ' . ($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? ''));
    $id = $patient['permanent_patient_id'] ?: ($patient['patient_code'] ?? '');
    return trim($name . ' - ' . $id);
}

function wardTransOutTypes(): array {
    return ['WARD TO OR', 'WARD TO MICU', 'TRANSFER TO OTHER FACILITY'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ward_trans_out'])) {
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $to = sanitizeText($_POST['to_department'] ?? '', 60);
    $movementType = sanitizeText($_POST['movement_type'] ?? '', 80);
    $reason = sanitizeText($_POST['reason'] ?? '', 1000);
    $remarks = sanitizeText($_POST['remarks'] ?? '', 2000);
    $validDestinations = ['OR', 'MICU', 'OTHER FACILITY'];
    $validTypes = wardTransOutTypes();

    if (!hasPermission('patient_movements', 'edit')) {
        $error = 'You do not have permission to transfer ward patients.';
    } elseif ($patientId <= 0 || !in_array($to, $validDestinations, true) || !in_array($movementType, $validTypes, true)) {
        $error = 'Please select a ward patient, destination, and valid movement type.';
    } elseif (
        ($to === 'OR' && $movementType !== 'WARD TO OR')
        || ($to === 'MICU' && $movementType !== 'WARD TO MICU')
        || ($to === 'OTHER FACILITY' && $movementType !== 'TRANSFER TO OTHER FACILITY')
    ) {
        $error = 'Movement type must match the selected destination.';
    } else {
        $patientStmt = $conn->prepare("
            SELECT p.patient_id, p.patient_code
            FROM patients p
            LEFT JOIN beds b ON p.current_bed = b.bed_id
            WHERE p.patient_id = ?
                AND p.status = 'admit_inpatient'
                AND (
                    b.bed_type = 'General'
                    OR EXISTS (
                        SELECT 1
                        FROM patient_tracking ward_pt
                        WHERE ward_pt.patient_id = p.patient_id
                            AND ward_pt.action = 'Ward Admission Seeded'
                    )
                )
        ");
        $patientStmt->bind_param("i", $patientId);
        $patientStmt->execute();
        $patient = $patientStmt->get_result()->fetch_assoc();

        if (!$patient) {
            $error = 'Please select an admitted ward patient.';
        } elseif (clinicalCreateMovementSafe($conn, $patientId, $patient['patient_code'] ?? '', 'WARD', $to, $movementType, $reason, $remarks)) {
            $success = 'Ward trans out request recorded.';
        } else {
            $error = 'Unable to record ward trans out request.';
        }
    }

    if ($error) { setFlashMessage('error', $error); }
    if ($success) { setFlashMessage('success', $success); }
    redirectAfterPost('ward_dashboard.php#trans-out-patient');
}

$wardDepartments = wardDepartmentOptions();
$departmentCounts = array_fill_keys(array_keys($wardDepartments), 0);
$departmentRows = $conn->query("
    SELECT COALESCE(NULLIF(ward_department, ''), 'MEDICAL') AS ward_department, COUNT(*) AS total
    FROM patients
    WHERE status = 'admit_inpatient'
    GROUP BY COALESCE(NULLIF(ward_department, ''), 'MEDICAL')
")->fetch_all(MYSQLI_ASSOC);

foreach ($departmentRows as $row) {
    $departmentCounts[$row['ward_department']] = (int) $row['total'];
}

$wardStats = [
    'admitted_patients' => (int) $conn->query("SELECT COUNT(*) AS total FROM patients WHERE status = 'admit_inpatient'")->fetch_assoc()['total'],
    'assigned_beds' => (int) $conn->query("SELECT COUNT(*) AS total FROM patients WHERE status = 'admit_inpatient' AND current_bed IS NOT NULL")->fetch_assoc()['total'],
    'awaiting_bed' => (int) $conn->query("SELECT COUNT(*) AS total FROM patients WHERE status = 'admit_inpatient' AND current_bed IS NULL")->fetch_assoc()['total'],
    'available_beds' => (int) $conn->query("SELECT COUNT(*) AS total FROM beds WHERE status = 'available' AND bed_type = 'General'")->fetch_assoc()['total'],
    'discharges_today' => (int) $conn->query("SELECT COUNT(*) AS total FROM patients WHERE status IN ('discharge', 'trans_in', 'trans_out', 'hama', 'thoc', 'absconded', 'death', 'expired', 'er_death') AND DATE(updated_at) = CURDATE()")->fetch_assoc()['total'],
];

$avgStayMinutes = $conn->query("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, COALESCE(admission_event.admitted_at, p.updated_at, p.admission_date, p.created_at), NOW())) AS avg_minutes
    FROM patients p
    LEFT JOIN (
        SELECT patient_id, MAX(created_at) AS admitted_at
        FROM patient_tracking
        WHERE to_status = 'admit_inpatient'
        GROUP BY patient_id
    ) admission_event ON p.patient_id = admission_event.patient_id
    WHERE p.status = 'admit_inpatient'
")->fetch_assoc()['avg_minutes'];
$wardStats['average_stay'] = formatWaitTime(round($avgStayMinutes ?? 0));

$opdWardAdmissions = $conn->query("
    SELECT pm.*, p.permanent_patient_id, CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name
    FROM patient_movements pm
    INNER JOIN patients p ON p.patient_id = pm.patient_id
    WHERE pm.source_department='OPD'
        AND pm.destination_department='WARD'
        AND pm.status IN ('PENDING','ACCEPTED','IN TRANSIT','ARRIVED')
    ORDER BY pm.created_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$patients = $conn->query("
    SELECT
        p.patient_id,
        p.patient_code,
        p.queue_number,
        p.first_name,
        p.last_name,
        p.middle_name,
        p.age,
        p.gender,
        p.ward_department,
        p.status,
        p.updated_at,
        COALESCE(admission_event.admitted_at, p.updated_at, p.admission_date, p.created_at) AS admitted_at,
        b.bed_number,
        b.room_number,
        t.triage_level,
        t.chief_complaint
    FROM patients p
    LEFT JOIN beds b ON p.current_bed = b.bed_id
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    LEFT JOIN (
        SELECT patient_id, MAX(created_at) AS admitted_at
        FROM patient_tracking
        WHERE to_status = 'admit_inpatient'
        GROUP BY patient_id
    ) admission_event ON p.patient_id = admission_event.patient_id
    WHERE p.status = 'admit_inpatient'
    ORDER BY p.ward_department, b.room_number, b.bed_number, p.updated_at DESC
")->fetch_all(MYSQLI_ASSOC);

$wardTransOutPatients = $conn->query("
    SELECT
        p.patient_id,
        p.permanent_patient_id,
        p.patient_code,
        p.first_name,
        p.last_name,
        p.middle_name,
        p.ward_department,
        b.bed_number,
        b.room_number
    FROM patients p
    LEFT JOIN beds b ON p.current_bed = b.bed_id
    WHERE p.status = 'admit_inpatient'
        AND (
            b.bed_type = 'General'
            OR EXISTS (
                SELECT 1
                FROM patient_tracking ward_pt
                WHERE ward_pt.patient_id = p.patient_id
                    AND ward_pt.action = 'Ward Admission Seeded'
            )
        )
    ORDER BY p.ward_department, p.last_name, p.first_name
")->fetch_all(MYSQLI_ASSOC);

$recentAdmissions = $conn->query("
    SELECT
        p.patient_id,
        p.patient_code,
        CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name,
        p.ward_department,
        b.bed_number,
        MAX(pt.created_at) AS admitted_at
    FROM patient_tracking pt
    INNER JOIN patients p ON pt.patient_id = p.patient_id
    LEFT JOIN beds b ON p.current_bed = b.bed_id
    WHERE pt.to_status = 'admit_inpatient'
        AND DATE(pt.created_at) = CURDATE()
    GROUP BY p.patient_id, p.patient_code, p.last_name, p.first_name, p.middle_name, p.ward_department, b.bed_number
    ORDER BY admitted_at DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$maxDepartmentCount = max(max($departmentCounts), 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Dashboard - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .ward-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }
        .ward-dashboard-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 18px;
            margin-top: 18px;
        }
        .ward-department-row {
            display: grid;
            grid-template-columns: 155px 1fr 44px;
            gap: 12px;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #edf1f6;
            font-size: 13px;
        }
        .ward-department-row:last-child {
            border-bottom: 0;
        }
        .ward-bar-track {
            height: 9px;
            background: #e8eef6;
            border-radius: 999px;
            overflow: hidden;
        }
        .ward-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #0B3C5D, #0B3C5D);
            border-radius: inherit;
        }
        .ward-table td,
        .ward-table th {
            vertical-align: middle;
        }
        @media (max-width: 900px) {
            .ward-dashboard-grid {
                grid-template-columns: 1fr;
            }
            .ward-department-row {
                grid-template-columns: 1fr;
                gap: 6px;
            }
        }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
    <nav class="navbar">
        <div class="navbar-brand">
            <div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div>
        </div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?> (<?php echo htmlspecialchars(roleLabel($_SESSION['role'])); ?>)</span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <section class="page-header">
                <div class="jvgh-brand dashboard-heading">
                    <div>
                        <h2>Ward Dashboard</h2>
                        <p class="compact-meta">Admitted patient census connected with Emergency Room admissions.</p>
                    </div>
                </div>
                <div class="ward-actions">
                    <a href="ward_admitted_patients.php" class="btn btn-primary"><i class="fa-solid fa-hospital-user"></i> Ward Admitted Patients</a>
                    <a href="ward_beds.php" class="btn btn-info"><i class="fa-solid fa-bed-pulse"></i> Ward Bed Tracker</a>
                    <?php if (hasRole('admin')): ?>
                        <a href="emergency_room_dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-chart-line"></i> ER Dashboard</a>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <section class="stats-grid">
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Ward Census</span><span class="stat-value"><?php echo htmlspecialchars($wardStats['admitted_patients']); ?></span></div></div>
                <div class="stat-card available"><div class="stat-info"><span class="stat-label">Assigned Beds</span><span class="stat-value"><?php echo htmlspecialchars($wardStats['assigned_beds']); ?></span></div></div>
                <div class="stat-card waiting"><div class="stat-info"><span class="stat-label">Awaiting Bed</span><span class="stat-value"><?php echo htmlspecialchars($wardStats['awaiting_bed']); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Available Beds</span><span class="stat-value"><?php echo htmlspecialchars($wardStats['available_beds']); ?></span></div></div>
                <div class="stat-card emergency"><div class="stat-info"><span class="stat-label">Discharges Today</span><span class="stat-value"><?php echo htmlspecialchars($wardStats['discharges_today']); ?></span></div></div>
                <div class="stat-card available"><div class="stat-info"><span class="stat-label">Average Ward Stay</span><span class="stat-value"><?php echo htmlspecialchars($wardStats['average_stay']); ?></span></div></div>
            </section>

            <section class="ward-dashboard-grid">
                <div class="census-card">
                    <h3>Ward Census by Department</h3>
                    <?php foreach ($departmentCounts as $department => $total): ?>
                        <div class="ward-department-row">
                            <strong><?php echo htmlspecialchars($wardDepartments[$department] ?? $department); ?></strong>
                            <div class="ward-bar-track"><div class="ward-bar-fill" style="width: <?php echo round(($total / $maxDepartmentCount) * 100); ?>%;"></div></div>
                            <span><?php echo htmlspecialchars($total); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="census-card">
                    <h3>ER to Ward Today</h3>
                    <?php if (empty($recentAdmissions)): ?>
                        <p class="compact-meta">No ER admissions to ward today.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table ward-table">
                                <thead><tr><th>Patient</th><th>Ward</th><th>Bed</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recentAdmissions as $admission): ?>
                                        <tr>
                                            <td><a href="patient_detail.php?id=<?php echo (int) $admission['patient_id']; ?>"><?php echo htmlspecialchars($admission['patient_name']); ?></a></td>
                                            <td><?php echo htmlspecialchars($admission['ward_department'] ?? 'MEDICAL'); ?></td>
                                            <td><?php echo htmlspecialchars($admission['bed_number'] ?? 'Unassigned'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="census-card">
                    <h3>OPD For Admission</h3>
                    <a class="btn btn-sm btn-info" href="movement_history.php?destination=WARD">Receive OPD Admission</a>
                    <?php if (empty($opdWardAdmissions)): ?>
                        <p class="compact-meta">No OPD ward admissions pending.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table ward-table">
                                <thead><tr><th>Patient</th><th>Status</th><th>Requested</th></tr></thead>
                                <tbody>
                                    <?php foreach ($opdWardAdmissions as $movement): ?>
                                        <tr><td><?php echo htmlspecialchars($movement['patient_name']); ?><br><small><?php echo htmlspecialchars($movement['permanent_patient_id']); ?></small></td><td><?php echo htmlspecialchars($movement['status']); ?></td><td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($movement['created_at']))); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="census-card" id="trans-out-patient">
                    <h3>Ward Transfer Actions</h3>
                    <p class="compact-meta">Trans out ward patients to OR, MICU, or another facility. The source department is fixed to Ward.</p>
                    <form method="POST" style="display:grid;gap:12px;margin-top:12px;">
                        <input type="hidden" name="create_ward_trans_out" value="1">
                        <div class="form-group">
                            <label>Ward Patient</label>
                            <select name="patient_id" required>
                                <option value="">Select admitted ward patient</option>
                                <?php foreach ($wardTransOutPatients as $patient): ?>
                                    <option value="<?php echo (int) $patient['patient_id']; ?>">
                                        <?php echo htmlspecialchars(wardMovementPatientLabel($patient) . ' - ' . ($patient['bed_number'] ? $patient['bed_number'] : 'Unassigned Bed')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>From</label>
                                <input value="WARD" readonly>
                            </div>
                            <div class="form-group">
                                <label>To</label>
                                <select name="to_department" required>
                                    <option value="">Select destination</option>
                                    <option value="OR">OR</option>
                                    <option value="MICU">MICU</option>
                                    <option value="OTHER FACILITY">OTHER FACILITY</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Movement Type</label>
                            <select name="movement_type" required>
                                <?php foreach (wardTransOutTypes() as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Reason</label><input name="reason"></div>
                        <div class="form-group"><label>Remarks</label><textarea name="remarks"></textarea></div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button class="btn btn-primary" type="submit">Record Trans Out</button>
                            <a class="btn btn-info" href="movement_history.php?department=WARD">View Movement History</a>
                        </div>
                    </form>
                </div>
            </section>

            <section class="patients-section">
                <h2>Current Ward Patients</h2>
                <div class="table-responsive">
                    <table class="table table-striped ward-table">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Ward</th>
                                <th>Bed</th>
                                <th>Triage</th>
                                <th>Chief Complaint</th>
                                <th>Ward Stay</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr><td colspan="8" class="text-center">No ward patients found</td></tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <?php $wardStay = max(0, (int) floor((time() - strtotime($patient['admitted_at'])) / 60)); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($patient['patient_code']); ?></td>
                                        <td><?php echo htmlspecialchars(patientFullName($patient)); ?></td>
                                        <td><?php echo htmlspecialchars($wardDepartments[$patient['ward_department']] ?? ($patient['ward_department'] ?? 'MEDICAL')); ?></td>
                                        <td><?php echo htmlspecialchars($patient['bed_number'] ? $patient['bed_number'] . ' - Room ' . $patient['room_number'] : 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars(triageLevelLabel($patient['triage_level'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($patient['chief_complaint'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(formatWaitTime($wardStay)); ?></td>
                                        <td><a href="patient_detail.php?id=<?php echo (int) $patient['patient_id']; ?>" class="btn btn-sm btn-info">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script src="../public/js/sidebar.js"></script>
</body>
</html>
