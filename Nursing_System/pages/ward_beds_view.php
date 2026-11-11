<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/clinical_modules.php';

requireLogin();
requireRole(array_merge(nurseRoles(), doctorRoles(), admissionRoles()));
if (!hasRole('admin') && !hasRole(admissionRoles()) && !hasDepartment(['WARD_2F', 'WARD_3F', 'ICU', 'NICU'])) {
    header('Location: ../pages/' . homePageForRole());
    exit();
}
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
$bedSyncResult = ensureWardBeds($conn);
$wardBedModule = sanitizeText($_GET['module'] ?? ($_POST['module'] ?? 'ward'), 40);
if (!in_array($wardBedModule, ['ward', 'ob_pedia_ward'], true)) {
    $wardBedModule = 'ward';
}
$wardBedAreaName = $wardBedModule === 'ob_pedia_ward' ? '2ND FLOOR WARD' : '3RD FLOOR WARD';
$wardBedLabel = $wardBedModule === 'ob_pedia_ward' ? 'OB/Pedia Ward' : 'Medical/Surgery Ward';
$wardBedRedirect = 'ward_beds_view.php?module=' . urlencode($wardBedModule);
$obPediaRoomList = implode(',', array_map(
    fn($room) => "'" . $conn->real_escape_string($room) . "'",
    array_values(array_unique(obPediaWardRoomNumbers()))
));
$wardBedRoomWhere = $wardBedModule === 'ob_pedia_ward'
    ? "(room_number IN ($obPediaRoomList))"
    : "(room_number LIKE 'WARD-03%' OR room_number LIKE 'WARD-04%')";
$wardPatientWhere = $wardBedModule === 'ob_pedia_ward'
    ? "ward_department IN ('OB','PEDIA','NEWBORN')"
    : "ward_department IN ('MEDICAL','NON-TRAUMA SURGERY','TRAUMA SURGERY')";

$error = '';
$success = '';
consumeFlashMessages($error, $success);
if ($bedSyncResult !== true) {
    $error = $bedSyncResult;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_ward_bed'])) {
    $bedId = intval($_POST['bed_id'] ?? 0);
    $patientId = intval($_POST['patient_id'] ?? 0);

    $bedStmt = $conn->prepare("SELECT bed_id, bed_number, status FROM beds WHERE bed_id = ? AND $wardBedRoomWhere");
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
        $conn->begin_transaction();
        try {
            if (!empty($patient['current_bed'])) {
                $releaseStmt = $conn->prepare("UPDATE beds SET status = 'available', patient_id = NULL WHERE bed_id = ?");
                $releaseStmt->bind_param("i", $patient['current_bed']);
                $releaseStmt->execute();
            }

            $assignBedStmt = $conn->prepare("UPDATE beds SET status = 'occupied', patient_id = ? WHERE bed_id = ? AND status = 'available' AND patient_id IS NULL");
            $assignBedStmt->bind_param("ii", $patientId, $bedId);
            $assignBedStmt->execute();
            if ($assignBedStmt->affected_rows !== 1) {
                throw new RuntimeException('Selected ward bed was already assigned. Please choose another bed.');
            }

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
            logSecurityEvent($conn, 'Ward Bed Assigned', $trackingDescription);

            $conn->commit();
            $success = 'Ward bed assigned successfully.';
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = $exception->getMessage();
        }
    }

    if ($error) {
        setFlashMessage('error', $error);
    }
    if ($success) {
        setFlashMessage('success', $success);
    }
    redirectAfterPost($wardBedRedirect);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ward_bed_status'])) {
    $bedId = intval($_POST['bed_id'] ?? 0);
    $status = $_POST['status'] ?? 'available';

    $bedStmt = $conn->prepare("
        SELECT b.bed_id, p.patient_id
        FROM beds b
        LEFT JOIN patients p ON p.current_bed = b.bed_id AND p.status = 'admit_inpatient'
        WHERE b.bed_id = ? AND $wardBedRoomWhere
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
        if ($updateStmt->execute()) {
            logSecurityEvent($conn, 'Ward Bed Status Updated', 'Ward bed ID ' . $bedId . ' set to ' . $status);
            $success = 'Ward bed status updated.';
        } else {
            $error = 'Failed to update ward bed.';
        }
    }

    if ($error) {
        setFlashMessage('error', $error);
    }
    if ($success) {
        setFlashMessage('success', $success);
    }
    redirectAfterPost($wardBedRedirect);
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
        p.admission_date,
        p.final_diagnosis,
        p.specific_diagnosis,
        CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name,
        t.triage_level,
        t.chief_complaint
    FROM beds b
    LEFT JOIN patients p ON p.current_bed = b.bed_id AND p.status = 'admit_inpatient'
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    WHERE $wardBedRoomWhere
    ORDER BY b.room_number, b.bed_number
")->fetch_all(MYSQLI_ASSOC);

$occupiedBedsCount = count(array_filter($beds, fn($bed) => $bed['status'] === 'occupied'));
$maintenanceBedsCount = count(array_filter($beds, fn($bed) => $bed['status'] === 'maintenance'));
$capacityBedsCount = clinicalScalar($conn, "SELECT total_bed_capacity FROM bed_capacity_settings WHERE area_name=? AND status='ACTIVE'", [$wardBedAreaName], 's');
if ($capacityBedsCount <= 0) {
    $capacityBedsCount = clinicalDefaultBedCapacities()[$wardBedAreaName] ?? count($beds);
}
$availableBedsCount = max(0, $capacityBedsCount - $occupiedBedsCount - $maintenanceBedsCount);

$availablePatients = $conn->query("
    SELECT patient_id, patient_code, CONCAT(last_name, ', ', first_name, IFNULL(CONCAT(' ', middle_name), '')) AS patient_name, ward_department
    FROM patients
    WHERE status = 'admit_inpatient'
        AND current_bed IS NULL
        AND $wardPatientWhere
    ORDER BY updated_at DESC, last_name, first_name
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($wardBedLabel); ?> Bed Tracker - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .ward-bed-table-wrap {
            background: #fff;
            border: 1px solid #d8e3ef;
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        .ward-bed-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }
        .ward-bed-table th,
        .ward-bed-table td {
            padding: 11px 12px;
            border-bottom: 1px solid #e4edf6;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }
        .ward-bed-table th {
            background: #f4f8fc;
            color: #0B3C5D;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: .02em;
        }
        .ward-bed-table tr:last-child td { border-bottom: 0; }
        .ward-bed-table tr.available { border-left: 4px solid #328CC1; }
        .ward-bed-table tr.occupied { border-left: 4px solid #D32F2F; }
        .ward-bed-table tr.maintenance { border-left: 4px solid #6b7280; }
        .bed-status-pill {
            display: inline-flex;
            align-items: center;
            min-width: 84px;
            justify-content: center;
            padding: 4px 8px;
            border-radius: 999px;
            background: #EAF6FF;
            color: #0B3C5D;
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
        }
        .bed-status-pill.occupied { background: #fdecec; color: #a82121; }
        .bed-status-pill.maintenance { background: #eef1f5; color: #4b5563; }
        .ward-bed-patient {
            display: grid;
            gap: 4px;
            min-width: 220px;
        }
        .ward-bed-muted {
            color: #617188;
            font-size: 12px;
        }
        .ward-bed-actions {
            display: grid;
            gap: 8px;
            min-width: 280px;
        }
        .ward-bed-form {
            display: grid;
            grid-template-columns: minmax(150px, 1fr) auto;
            gap: 8px;
            align-items: end;
            margin: 0;
        }
        .ward-bed-form .form-group {
            margin: 0;
        }
        .ward-bed-form label {
            font-size: 11px;
            margin-bottom: 4px;
        }
        .ward-bed-form select {
            min-height: 34px;
            padding: 6px 8px;
        }
        @media (max-width: 760px) {
            .ward-bed-table {
                min-width: 760px;
            }
            .ward-bed-form {
                grid-template-columns: 1fr;
            }
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
                <h2><?php echo htmlspecialchars($wardBedLabel); ?> Bed Tracker</h2>
                <p class="compact-meta">Manage WARD rooms and beds for admitted ward patients.</p>
            </section>

            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <section class="stats-grid">
                <div class="stat-card available"><div class="stat-info"><span class="stat-label">Available Beds</span><span class="stat-value"><?php echo htmlspecialchars($availableBedsCount); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Capacity Beds</span><span class="stat-value"><?php echo htmlspecialchars($capacityBedsCount); ?></span></div></div>
            </section>

            <section class="beds-section">
                <div class="ward-bed-table-wrap">
                    <table class="ward-bed-table">
                        <thead>
                            <tr>
                                <th>Bed</th>
                                <th>Room</th>
                                <th>Status</th>
                                <th>Patient</th>
                                <th>Ward</th>
                                <th>Date Admitted</th>
                                <th>Admission Diagnosis</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($beds as $bed): ?>
                                <tr class="<?php echo htmlspecialchars($bed['status']); ?>">
                                    <td><strong><?php echo htmlspecialchars($bed['bed_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($bed['room_number']); ?></td>
                                    <td><span class="bed-status-pill <?php echo htmlspecialchars($bed['status']); ?>"><?php echo htmlspecialchars(ucfirst($bed['status'])); ?></span></td>
                                    <td>
                                        <?php if (!empty($bed['patient_id'])): ?>
                                            <div class="ward-bed-patient">
                                                <strong><?php echo htmlspecialchars($bed['patient_name']); ?></strong>
                                                <span class="ward-bed-muted"><?php echo htmlspecialchars($bed['patient_code']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="ward-bed-muted">Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($bed['ward_department'] ?? ''); ?></td>
                                    <td><?php echo !empty($bed['patient_id']) && !empty($bed['admission_date']) ? htmlspecialchars(date('M d, Y', strtotime($bed['admission_date']))) : '<span class="ward-bed-muted">N/A</span>'; ?></td>
                                    <td><?php echo !empty($bed['patient_id']) ? htmlspecialchars(trim((string) ($bed['specific_diagnosis'] ?? '')) !== '' ? $bed['specific_diagnosis'] : ($bed['final_diagnosis'] ?? 'N/A')) : '<span class="ward-bed-muted">N/A</span>'; ?></td>
                                    <td>
                                        <div class="ward-bed-actions">
                                            <?php if (!empty($bed['patient_id'])): ?>
                                                <a href="patient_detail.php?id=<?php echo (int) $bed['patient_id']; ?>" class="btn btn-sm btn-info">View Patient</a>
                                            <?php else: ?>
                                                <?php if ($bed['status'] === 'available' && !empty($availablePatients)): ?>
                                                    <form method="POST" class="ward-bed-form ward-confirm-form">
                                                        <input type="hidden" name="assign_ward_bed" value="1">
                                                        <input type="hidden" name="module" value="<?php echo htmlspecialchars($wardBedModule); ?>">
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
                                                        <button type="submit" class="btn btn-sm btn-primary" data-confirm-title="Confirm Ward Bed Assignment">Assign</button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="POST" class="ward-bed-form ward-confirm-form">
                                                    <input type="hidden" name="update_ward_bed_status" value="1">
                                                    <input type="hidden" name="module" value="<?php echo htmlspecialchars($wardBedModule); ?>">
                                                    <input type="hidden" name="bed_id" value="<?php echo (int) $bed['bed_id']; ?>">
                                                    <div class="form-group">
                                                        <label for="status-<?php echo (int) $bed['bed_id']; ?>">Bed Status</label>
                                                        <select id="status-<?php echo (int) $bed['bed_id']; ?>" name="status">
                                                            <option value="available" <?php echo $bed['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                                            <option value="maintenance" <?php echo $bed['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                                        </select>
                                                    </div>
                                                    <button type="submit" class="btn btn-sm btn-secondary" data-confirm-title="Confirm Ward Bed Status">Update</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
