<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(array_merge(nurseRoles(), doctorRoles(), admissionRoles()));
if (!hasRole('admin') && !hasRole(admissionRoles()) && !hasDepartment('ward_department')) {
    header('Location: ../pages/' . homePageForRole());
    exit();
}
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
$bedSyncResult = ensureWardBeds($conn);

$error = '';
$success = '';
if ($bedSyncResult !== true) {
    $error = $bedSyncResult;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_ward_bed'])) {
    $bedId = intval($_POST['bed_id'] ?? 0);
    $patientId = intval($_POST['patient_id'] ?? 0);

    $bedStmt = $conn->prepare("SELECT bed_id, bed_number, status FROM beds WHERE bed_id = ? AND room_number LIKE 'WARD-%'");
    $bedStmt->bind_param("i", $bedId);
    $bedStmt->execute();
    $bed = $bedStmt->get_result()->fetch_assoc();

    $patientStmt = $conn->prepare("SELECT patient_id, current_bed FROM patients WHERE patient_id = ? AND status = 'admit_inpatient'");
    $patientStmt->bind_param("i", $patientId);
    $patientStmt->execute();
    $patient = $patientStmt->get_result()->fetch_assoc();

    if (!$bed) {
        $error = 'Please select a valid ward bed.';
    } elseif ($bed['status'] !== 'available') {
        $error = 'Only available ward beds can be assigned.';
    } elseif (!$patient) {
        $error = 'Please select an admitted ward patient.';
    } else {
        if (!empty($patient['current_bed'])) {
            $releaseStmt = $conn->prepare("UPDATE beds SET status = 'available', patient_id = NULL WHERE bed_id = ?");
            $releaseStmt->bind_param("i", $patient['current_bed']);
            $releaseStmt->execute();
        }

        $assignBedStmt = $conn->prepare("UPDATE beds SET status = 'occupied', patient_id = ? WHERE bed_id = ?");
        $assignBedStmt->bind_param("ii", $patientId, $bedId);
        $assignBedStmt->execute();

        $assignPatientStmt = $conn->prepare("UPDATE patients SET current_bed = ? WHERE patient_id = ?");
        $assignPatientStmt->bind_param("ii", $bedId, $patientId);
        $assignPatientStmt->execute();

        $trackingStmt = $conn->prepare("
            INSERT INTO patient_tracking (patient_id, action, description, user_id)
            VALUES (?, 'Ward Bed Assigned', ?, ?)
        ");
        $trackingDescription = 'Patient assigned to ward bed ' . $bed['bed_number'];
        $trackingStmt->bind_param("isi", $patientId, $trackingDescription, $_SESSION['user_id']);
        $trackingStmt->execute();
        $success = 'Ward bed assigned successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ward_bed_status'])) {
    $bedId = intval($_POST['bed_id'] ?? 0);
    $status = $_POST['status'] ?? 'available';

    $bedStmt = $conn->prepare("
        SELECT b.bed_id, p.patient_id
        FROM beds b
        LEFT JOIN patients p ON p.current_bed = b.bed_id AND p.status = 'admit_inpatient'
        WHERE b.bed_id = ? AND b.room_number LIKE 'WARD-%'
    ");
    $bedStmt->bind_param("i", $bedId);
    $bedStmt->execute();
    $bed = $bedStmt->get_result()->fetch_assoc();

    if (!$bed) {
        $error = 'Ward bed not found.';
    } elseif (!in_array($status, ['available', 'maintenance'], true)) {
        $error = 'Invalid ward bed status.';
    } elseif (!empty($bed['patient_id'])) {
        $error = 'Occupied ward beds cannot be changed to maintenance or available here.';
    } else {
        $updateStmt = $conn->prepare("UPDATE beds SET status = ?, patient_id = NULL WHERE bed_id = ?");
        $updateStmt->bind_param("si", $status, $bedId);
        $success = $updateStmt->execute() ? 'Ward bed status updated.' : 'Failed to update ward bed.';
    }
}

$beds = $conn->query("
    SELECT
        b.bed_id,
        b.bed_number,
        b.room_number,
        b.bed_type,
        b.status,
        p.patient_id,
        p.patient_code,
        p.age,
        p.gender,
        p.phone,
        p.ward_department,
        CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name,
        t.triage_level,
        t.chief_complaint
    FROM beds b
    LEFT JOIN patients p ON p.current_bed = b.bed_id AND p.status = 'admit_inpatient'
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    WHERE b.room_number LIKE 'WARD-%'
    ORDER BY b.room_number, b.bed_number
")->fetch_all(MYSQLI_ASSOC);

$availablePatients = $conn->query("
    SELECT patient_id, patient_code, CONCAT(last_name, ', ', first_name, IFNULL(CONCAT(' ', middle_name), '')) AS patient_name, ward_department
    FROM patients
    WHERE status = 'admit_inpatient'
        AND current_bed IS NULL
    ORDER BY updated_at DESC, last_name, first_name
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Bed Tracker - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .ward-bed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(285px, 1fr));
            gap: 16px;
        }
        .ward-bed-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border-top: 4px solid #27AE60;
            padding: 18px;
        }
        .ward-bed-card.occupied { border-top-color: #D32F2F; }
        .ward-bed-card.maintenance { border-top-color: #6b7280; }
        .ward-bed-card h3 {
            margin-bottom: 10px;
            color: #1E3A5F;
        }
        .ward-bed-row {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 8px;
            padding: 7px 0;
            border-bottom: 1px solid #edf1f6;
            font-size: 13px;
        }
        .ward-bed-row:last-child { border-bottom: 0; }
        .ward-bed-row span:first-child {
            color: #5d6b7a;
            font-weight: 700;
        }
        .ward-bed-form {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #edf1f6;
        }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
    <nav class="navbar">
        <div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?> (<?php echo htmlspecialchars(roleLabel($_SESSION['role'])); ?>)</span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <section class="page-header">
                <h2>Ward Bed Tracker</h2>
                <p class="compact-meta">Manage only WARD rooms and beds for admitted ward patients.</p>
            </section>

            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <section class="beds-section">
                <div class="ward-bed-grid">
                    <?php foreach ($beds as $bed): ?>
                        <div class="ward-bed-card <?php echo htmlspecialchars($bed['status']); ?>">
                            <h3><?php echo htmlspecialchars($bed['bed_number']); ?></h3>
                            <div class="ward-bed-row"><span>Room</span><strong><?php echo htmlspecialchars($bed['room_number']); ?></strong></div>
                            <div class="ward-bed-row"><span>Status</span><strong><?php echo htmlspecialchars(ucfirst($bed['status'])); ?></strong></div>
                            <?php if (!empty($bed['patient_id'])): ?>
                                <div class="ward-bed-row"><span>Patient</span><strong><?php echo htmlspecialchars($bed['patient_name']); ?></strong></div>
                                <div class="ward-bed-row"><span>Patient ID</span><strong><?php echo htmlspecialchars($bed['patient_code']); ?></strong></div>
                                <div class="ward-bed-row"><span>Ward</span><strong><?php echo htmlspecialchars($bed['ward_department'] ?? 'MEDICAL'); ?></strong></div>
                                <div class="ward-bed-row"><span>Details</span><strong><?php echo htmlspecialchars(($bed['age'] ?? 'N/A') . ' yrs / ' . ($bed['gender'] ?? 'N/A')); ?></strong></div>
                                <div class="ward-bed-row"><span>Complaint</span><strong><?php echo htmlspecialchars($bed['chief_complaint'] ?? 'N/A'); ?></strong></div>
                                <div style="margin-top: 12px;"><a href="patient_detail.php?id=<?php echo (int) $bed['patient_id']; ?>" class="btn btn-sm btn-info">View Patient</a></div>
                            <?php else: ?>
                                <div class="ward-bed-row"><span>Patient</span><strong>Available</strong></div>
                                <?php if ($bed['status'] === 'available' && !empty($availablePatients)): ?>
                                    <form method="POST" class="ward-bed-form ward-confirm-form">
                                        <input type="hidden" name="assign_ward_bed" value="1">
                                        <input type="hidden" name="bed_id" value="<?php echo (int) $bed['bed_id']; ?>">
                                        <div class="form-group">
                                            <label for="patient-<?php echo (int) $bed['bed_id']; ?>">Assign Admitted Patient</label>
                                            <select id="patient-<?php echo (int) $bed['bed_id']; ?>" name="patient_id" required>
                                                <option value="">Select patient</option>
                                                <?php foreach ($availablePatients as $patient): ?>
                                                    <option value="<?php echo (int) $patient['patient_id']; ?>">
                                                        <?php echo htmlspecialchars($patient['patient_name'] . ' - ' . $patient['ward_department']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary" data-confirm-title="Confirm Ward Bed Assignment">Assign Bed</button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" class="ward-bed-form ward-confirm-form">
                                    <input type="hidden" name="update_ward_bed_status" value="1">
                                    <input type="hidden" name="bed_id" value="<?php echo (int) $bed['bed_id']; ?>">
                                    <div class="form-group">
                                        <label for="status-<?php echo (int) $bed['bed_id']; ?>">Bed Status</label>
                                        <select id="status-<?php echo (int) $bed['bed_id']; ?>" name="status">
                                            <option value="available" <?php echo $bed['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                            <option value="maintenance" <?php echo $bed['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-secondary" data-confirm-title="Confirm Ward Bed Status">Update Status</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="../public/js/sidebar.js"></script>
    <script>
        document.querySelectorAll('.ward-confirm-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const title = form.querySelector('[data-confirm-title]')?.dataset.confirmTitle || 'Confirm Action';
                const popup = document.createElement('div');
                popup.className = 'discharge-modal open';
                popup.innerHTML = `
                    <div class="discharge-modal-card" role="dialog" aria-modal="true">
                        <h3>${title}</h3>
                        <p class="compact-meta">Are you sure you want to continue?</p>
                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px;">
                            <button type="button" class="btn btn-secondary" data-cancel>Cancel</button>
                            <button type="button" class="btn btn-primary" data-ok>Confirm</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(popup);
                popup.querySelector('[data-cancel]').addEventListener('click', () => popup.remove());
                popup.querySelector('[data-ok]').addEventListener('click', () => {
                    popup.remove();
                    form.submit();
                });
            });
        });
    </script>
    <style>
        .discharge-modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(44, 62, 80, 0.55);
            padding: 20px;
            z-index: 999;
        }
        .discharge-modal-card {
            width: min(440px, 100%);
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
            padding: 22px;
        }
    </style>
</body>
</html>
