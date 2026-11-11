<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(admissionRoles());
ensurePatientWorkflowSchema($conn);
ensureEmergencyRoomBeds($conn);
ensureWardBeds($conn);
$schemaResult = ensureAdmittedPatientSchema($conn);

$patient_id = intval($_GET['id'] ?? 0);
$admission_classification = $_GET['classification'] ?? ($_POST['admission_classification'] ?? 'emergency_room');
$admission_classification = in_array($admission_classification, ['emergency_room', 'ward'], true) ? $admission_classification : 'emergency_room';
$error = $schemaResult === true ? '' : $schemaResult;
$success = '';
consumeFlashMessages($error, $success);

function admissionClassificationLabel($classification) {
    return $classification === 'ward' ? 'For Admission - Ward' : 'For Admission - Emergency Room';
}

function admissionClassificationRoomPrefix($classification) {
    return $classification === 'ward' ? 'WARD-%' : 'ER-%';
}

function admissionPatientAddress(array $patient): string {
    $parts = [
        $patient['house_no_street'] ?? '',
        $patient['barangay_name'] ?? '',
        $patient['municipality_name'] ?? '',
        $patient['province_name'] ?? '',
    ];
    $parts = array_filter(array_map('trim', $parts));
    return $parts ? implode(', ', $parts) : 'N/A';
}

function admissionPatientDiagnosis(array $patient): string {
    $diagnosis = trim((string) ($patient['specific_diagnosis'] ?? ''));
    if ($diagnosis !== '') {
        return $diagnosis;
    }
    $diagnosis = trim((string) ($patient['final_diagnosis'] ?? ''));
    if ($diagnosis !== '') {
        return $diagnosis;
    }
    $complaint = trim((string) ($patient['chief_complaint'] ?? ''));
    return $complaint !== '' ? $complaint : 'Pending';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaResult === true) {
    $patient_id = intval($_POST['patient_id'] ?? 0);

    $patientCheck = $conn->prepare("SELECT patient_id, current_bed FROM patients WHERE patient_id = ? AND status = 'for_admission'");
    $patientCheck->bind_param("i", $patient_id);
    $patientCheck->execute();
    $admittedPatient = $patientCheck->get_result()->fetch_assoc();

    if (!$admittedPatient) {
        $error = 'Please select an admission patient.';
    } elseif (isset($_POST['discharge_patient'])) {
        $finalDiagnosis = sanitizeText($_POST['final_diagnosis'] ?? '', 150);
        $specificDiagnosis = sanitizeText($_POST['specific_diagnosis'] ?? '', 2000);
        $statusUpdateResult = updatePatientStatus($conn, $patient_id, 'discharge', $_SESSION['user_id'], $finalDiagnosis, $specificDiagnosis);

        if ($statusUpdateResult === true) {
            header('Location: discharge_patients.php');
            exit();
        }

        $error = $statusUpdateResult;
    } elseif (isset($_POST['assign_bed']) || isset($_POST['transfer_bed'])) {
        $bed_id = intval($_POST['bed_id'] ?? 0);
        if (!$bed_id) {
            $error = 'Please select an available bed.';
        } else {
            if ($admission_classification === 'ward') {
                $bedCheck = $conn->prepare("SELECT bed_id, bed_number FROM beds WHERE bed_id = ? AND status = 'available' AND patient_id IS NULL AND bed_type = 'General'");
                $bedCheck->bind_param("i", $bed_id);
            } else {
                $roomPrefix = admissionClassificationRoomPrefix($admission_classification);
                $bedCheck = $conn->prepare("SELECT bed_id, bed_number FROM beds WHERE bed_id = ? AND status = 'available' AND patient_id IS NULL AND room_number LIKE ?");
                $bedCheck->bind_param("is", $bed_id, $roomPrefix);
            }
            $bedCheck->execute();
            $selectedBed = $bedCheck->get_result()->fetch_assoc();

            if (!$selectedBed) {
                $error = 'Selected ' . strtolower(admissionClassificationLabel($admission_classification)) . ' bed is no longer available.';
            } else {
                $conn->begin_transaction();

                try {
                    $oldBedNumber = 'Unassigned';
                    if (!empty($admittedPatient['current_bed'])) {
                        $oldBedStmt = $conn->prepare("SELECT bed_number FROM beds WHERE bed_id = ?");
                        $oldBedStmt->bind_param("i", $admittedPatient['current_bed']);
                        $oldBedStmt->execute();
                        $oldBed = $oldBedStmt->get_result()->fetch_assoc();
                        $oldBedNumber = $oldBed['bed_number'] ?? 'Unassigned';
                    }

                    $clearOldBed = $conn->prepare("UPDATE beds SET status = 'available', patient_id = NULL WHERE patient_id = ?");
                    $clearOldBed->bind_param("i", $patient_id);
                    $clearOldBed->execute();

                    $assignBed = $conn->prepare("UPDATE beds SET status = 'occupied', patient_id = ? WHERE bed_id = ? AND status = 'available' AND patient_id IS NULL");
                    $assignBed->bind_param("ii", $patient_id, $bed_id);
                    $assignBed->execute();
                    if ($assignBed->affected_rows !== 1) {
                        throw new RuntimeException('Selected bed was already assigned. Please choose another available bed.');
                    }

                    $updatePatient = $conn->prepare("UPDATE patients SET current_bed = ?, status = 'admit_inpatient' WHERE patient_id = ?");
                    $updatePatient->bind_param("ii", $bed_id, $patient_id);
                    $updatePatient->execute();

                    $isTransfer = !empty($admittedPatient['current_bed']);
                    $classificationLabel = admissionClassificationLabel($admission_classification);
                    $description = $isTransfer
                        ? 'Transferred ' . $classificationLabel . ' patient from bed ' . $oldBedNumber . ' to bed ' . $selectedBed['bed_number']
                        : 'Assigned ' . $classificationLabel . ' patient to bed ' . $selectedBed['bed_number'];
                    $user_id = $_SESSION['user_id'];
                    $trackAction = $isTransfer ? 'Bed Transferred' : 'Bed Assigned';
                    $trackStmt = $conn->prepare("INSERT INTO patient_tracking (patient_id, action, description, user_id) VALUES (?, ?, ?, ?)");
                    $trackStmt->bind_param("issi", $patient_id, $trackAction, $description, $user_id);
                    $trackStmt->execute();
                    logSecurityEvent($conn, $trackAction, $description);
                    require_once '../includes/er-live-tracker.php';
                    erTrackerObserve($conn,(int)$patient_id,(int)$user_id,'bed_assignment');

                    $conn->commit();
                    $success = $isTransfer
                        ? 'Bed transferred successfully. Bed Tracker and Beds are now updated.'
                        : 'Bed assigned successfully. Bed Tracker and Beds are now updated.';
                } catch (Throwable $exception) {
                    $conn->rollback();
                    $error = 'Failed to update bed assignment: ' . $exception->getMessage();
                }
            }
        }
    }

    if ($error) {
        setFlashMessage('error', $error);
    }
    if ($success) {
        setFlashMessage('success', $success);
    }
    redirectAfterPost('admitted_patients.php');
}

$admittedPatients = [];
if ($schemaResult === true) {
    $admittedPatients = $conn->query("
        SELECT
            p.patient_id,
            p.patient_code,
            p.permanent_patient_id,
            CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) as patient_name,
            p.date_of_birth,
            p.age,
            p.gender,
            p.phone,
            p.house_no_street,
            p.barangay_name,
            p.municipality_name,
            p.province_name,
            p.final_diagnosis,
            p.specific_diagnosis,
            p.arrival_time,
            p.updated_at,
            b.bed_number,
            t.chief_complaint,
            apr.updated_at as record_updated_at
        FROM patients p
        LEFT JOIN beds b ON p.current_bed = b.bed_id
        LEFT JOIN triage t ON p.patient_id = t.patient_id
        LEFT JOIN admitted_patient_records apr ON p.patient_id = apr.patient_id
        WHERE p.status = 'for_admission'
        ORDER BY p.updated_at DESC, p.patient_id DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

$selectedPatient = null;
$availableBeds = [];

if ($patient_id && $schemaResult === true) {
    $stmt = $conn->prepare("
        SELECT
            p.*,
            CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) as patient_name,
            b.bed_number,
            b.room_number,
            t.triage_level,
            t.chief_complaint
        FROM patients p
        LEFT JOIN beds b ON p.current_bed = b.bed_id
        LEFT JOIN triage t ON p.patient_id = t.patient_id
        WHERE p.patient_id = ? AND p.status = 'for_admission'
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $selectedPatient = $stmt->get_result()->fetch_assoc();

    if ($selectedPatient) {
        if ($admission_classification === 'ward') {
            $availableBedStmt = $conn->prepare("
                SELECT bed_id, bed_number, room_number, bed_type
                FROM beds
                WHERE status = 'available' AND patient_id IS NULL AND bed_type = 'General'
                ORDER BY room_number, bed_number
            ");
        } else {
            $roomPrefix = admissionClassificationRoomPrefix($admission_classification);
            $availableBedStmt = $conn->prepare("
                SELECT bed_id, bed_number, room_number, bed_type
                FROM beds
                WHERE status = 'available' AND patient_id IS NULL AND room_number LIKE ?
                ORDER BY room_number, bed_number
            ");
            $availableBedStmt->bind_param("s", $roomPrefix);
        }
        $availableBedStmt->execute();
        $availableBeds = $availableBedStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    } else {
        $error = $error ?: 'Admission patient not found.';
        $patient_id = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>For Admission - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .admitted-layout {
            display: grid;
            grid-template-columns: minmax(320px, 1fr);
            gap: 20px;
            align-items: start;
        }

        .admission-list-panel {
            width: min(1240px, 100%);
        }

        .admission-assignment-modal {
            position: static;
            display: none;
            background: transparent;
            padding: 0;
            overflow: visible;
            margin-bottom: 18px;
        }

        .admission-assignment-modal.open {
            display: block;
        }

        .admission-assignment-card {
            width: min(980px, 100%);
            height: auto;
            max-height: none;
            overflow: visible;
            margin: 0;
            border-radius: 8px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.16);
            position: relative;
            padding: 18px;
        }

        .admission-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin: 0 0 12px;
        }

        .admission-modal-header h3 {
            margin: 0;
        }

        .admission-modal-close {
            width: 34px;
            height: 34px;
            border: 1px solid #d7e0ea;
            border-radius: 50%;
            background: #f8fafc;
            color: #0B3C5D;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
        }

        .admission-modal-close:hover {
            background: #eaf1f8;
        }

        .admission-modal-meta,
        .admission-modal-footer {
            max-width: none;
        }

        .compact-meta {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
        }

        .assignment-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin: 10px 0 2px;
            max-width: none;
        }

        .assignment-detail-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 10px;
            background: #fff;
        }

        .assignment-detail-item span {
            display: block;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .assignment-detail-item strong {
            color: #0B3C5D;
            font-size: 13px;
        }

        .bed-assignment-panel {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            margin: 12px 0;
            max-width: none;
        }

        .bed-assignment-panel form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: end;
        }

        .admission-classification-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .classification-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eef4ff;
            color: #0B3C5D;
            font-size: 12px;
            font-weight: 700;
        }

        .for-admission-table th,
        .for-admission-table td {
            vertical-align: top;
        }

        .for-admission-table .patient-name-cell {
            min-width: 170px;
        }

        .for-admission-table .patient-id-cell {
            min-width: 140px;
            color: #0B3C5D;
        }

        .for-admission-table .address-cell {
            min-width: 210px;
        }

        .for-admission-table .diagnosis-cell {
            min-width: 220px;
        }

        .for-admission-table .action-cell {
            min-width: 120px;
            white-space: nowrap;
        }

        @media (max-width: 980px) {
            .admitted-layout {
                grid-template-columns: 1fr;
            }
            .bed-assignment-panel form {
                grid-template-columns: 1fr;
            }
            .assignment-detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-height: 620px) {
            .admission-assignment-card {
                padding: 14px;
            }
            .assignment-detail-grid {
                grid-template-columns: 1fr 1fr;
            }
            .bed-assignment-panel {
                margin-top: 8px;
                margin-bottom: 8px;
            }
        }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
    <nav class="navbar">
        <div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px;">
                <h2>For Admission</h2>
            </div>

            <div class="admitted-layout">
                <section class="patients-section admission-list-panel">
                    <h3>For Admission</h3>
                    <div class="table-responsive">
                        <table class="table table-striped for-admission-table">
                            <thead>
                                <tr>
                                    <th>Patient ID</th>
                                    <th>Patient Name</th>
                                    <th>Birthday</th>
                                    <th>Age / Sex</th>
                                    <th>Address</th>
                                    <th>Diagnosis</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($admittedPatients)): ?>
                                    <tr><td colspan="7" class="text-center">No patients for admission found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($admittedPatients as $patient): ?>
                                        <tr>
                                            <td class="patient-id-cell"><?php echo htmlspecialchars($patient['permanent_patient_id'] ?: ($patient['patient_code'] ?? '')); ?></td>
                                            <td class="patient-name-cell">
                                                <?php echo htmlspecialchars($patient['patient_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(formatPatientDate($patient['date_of_birth'] ?? null)); ?></td>
                                            <td><?php echo htmlspecialchars(($patient['age'] ?? calculateAge($patient['date_of_birth'] ?? null)) . ' yrs / ' . ($patient['gender'] ?? 'N/A')); ?></td>
                                            <td class="address-cell"><?php echo htmlspecialchars(admissionPatientAddress($patient)); ?></td>
                                            <td class="diagnosis-cell"><?php echo htmlspecialchars(admissionPatientDiagnosis($patient)); ?></td>
                                            <td class="action-cell">
                                                <div class="admission-classification-actions">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-primary admit-choice-trigger"
                                                        data-er-url="admitted_patients.php?id=<?php echo (int) $patient['patient_id']; ?>&classification=emergency_room#bed-assignment"
                                                        data-ward-url="admitted_patients.php?id=<?php echo (int) $patient['patient_id']; ?>&classification=ward#bed-assignment"
                                                        data-patient-name="<?php echo htmlspecialchars($patient['patient_name']); ?>"
                                                    >
                                                        For Admission
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <?php if ($selectedPatient): ?>
                    <div class="admission-assignment-modal open" id="bed-assignment-modal" aria-hidden="false">
                    <section class="patients-section admission-assignment-card" role="dialog" aria-modal="true" aria-labelledby="bed-assignment-title">
                        <div class="admission-modal-header">
                            <h3><?php echo htmlspecialchars($selectedPatient['patient_name']); ?></h3>
                            <button type="button" class="admission-modal-close" id="close-bed-assignment-modal" aria-label="Close">&times;</button>
                        </div>
                        <p class="compact-meta admission-modal-meta">
                            <?php echo htmlspecialchars($selectedPatient['permanent_patient_id'] ?? ''); ?> |
                            <?php echo htmlspecialchars(statusLabel($selectedPatient['status'])); ?> |
                            <?php echo htmlspecialchars(admissionClassificationLabel($admission_classification)); ?> |
                            Bed: <?php echo htmlspecialchars($selectedPatient['bed_number'] ?? 'Unassigned'); ?> |
                            Chief Complaint: <?php echo htmlspecialchars($selectedPatient['chief_complaint'] ?? 'N/A'); ?>
                        </p>
                        <div class="assignment-detail-grid">
                            <div class="assignment-detail-item"><span>Birthday</span><strong><?php echo htmlspecialchars(formatPatientDate($selectedPatient['date_of_birth'] ?? null)); ?></strong></div>
                            <div class="assignment-detail-item"><span>Age / Sex</span><strong><?php echo htmlspecialchars(($selectedPatient['age'] ?? calculateAge($selectedPatient['date_of_birth'] ?? null)) . ' yrs / ' . ($selectedPatient['gender'] ?? 'N/A')); ?></strong></div>
                            <div class="assignment-detail-item"><span>Address</span><strong><?php echo htmlspecialchars(admissionPatientAddress($selectedPatient)); ?></strong></div>
                            <div class="assignment-detail-item"><span>Diagnosis</span><strong><?php echo htmlspecialchars(admissionPatientDiagnosis($selectedPatient)); ?></strong></div>
                        </div>

                        <?php $hasCurrentBed = !empty($selectedPatient['current_bed']); ?>
                        <div class="bed-assignment-panel" id="bed-assignment">
                            <h3 id="bed-assignment-title" style="margin-bottom: 10px;"><?php echo htmlspecialchars(admissionClassificationLabel($admission_classification)); ?></h3>
                            <p class="compact-meta" style="margin-bottom: 12px;">
                                Current Bed: <?php echo htmlspecialchars($selectedPatient['bed_number'] ?? 'Unassigned'); ?>
                                <?php if (!empty($selectedPatient['room_number'])): ?>
                                    | Room <?php echo htmlspecialchars($selectedPatient['room_number']); ?>
                                <?php endif; ?>
                            </p>
                            <form method="POST" class="bed-assignment-form">
                                <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($selectedPatient['patient_id']); ?>">
                                <input type="hidden" name="admission_classification" value="<?php echo htmlspecialchars($admission_classification); ?>">
                                <input type="hidden" name="<?php echo $hasCurrentBed ? 'transfer_bed' : 'assign_bed'; ?>" value="1">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="bed-id"><?php echo $hasCurrentBed ? 'Transfer To Available ' : 'Available '; ?><?php echo $admission_classification === 'ward' ? 'Ward Bed' : 'Emergency Room Bed'; ?></label>
                                    <select id="bed-id" name="bed_id" required>
                                        <option value="">Select <?php echo $admission_classification === 'ward' ? 'Ward' : 'Emergency Room'; ?> Bed</option>
                                        <?php foreach ($availableBeds as $bed): ?>
                                            <option value="<?php echo htmlspecialchars($bed['bed_id']); ?>">
                                                <?php echo htmlspecialchars($bed['bed_number'] . ' - Room ' . $bed['room_number'] . ' (' . $bed['bed_type'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    <?php echo empty($availableBeds) ? 'disabled' : ''; ?>
                                >
                                    <?php echo $hasCurrentBed ? 'Transfer Bed' : 'Assign Bed'; ?>
                                </button>
                            </form>
                            <?php if (empty($availableBeds)): ?>
                                <p class="compact-meta" style="margin-top: 10px;">No available <?php echo htmlspecialchars($admission_classification === 'ward' ? 'ward' : 'emergency room'); ?> beds right now.</p>
                            <?php endif; ?>
                        </div>

                        <div class="admission-modal-footer" style="margin-top: 14px;">
                            <a href="admitted_patients.php" class="btn btn-secondary">Close</a>
                        </div>
                    </section>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../public/js/sidebar.js"></script>
    <datalist id="diagnosis-options">
        <?php foreach (diagnosisOptions() as $diagnosis): ?>
            <option value="<?php echo htmlspecialchars($diagnosis); ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <div id="discharge-modal" class="discharge-modal" aria-hidden="true">
        <div class="discharge-modal-card">
            <h3>Discharge Diagnosis</h3>
            <p class="compact-meta">Choose a diagnosis and add a specific diagnosis before discharging the patient.</p>
            <div class="form-group">
                <label for="modal-final-diagnosis">Diagnosis *</label>
                <input type="text" id="modal-final-diagnosis" list="diagnosis-options" placeholder="Choose diagnosis">
            </div>
            <div class="form-group">
                <label for="modal-specific-diagnosis">Specific Diagnosis</label>
                <textarea id="modal-specific-diagnosis" rows="4" placeholder="Type specific diagnosis details"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-secondary" id="cancel-discharge-modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-discharge-modal">Continue</button>
            </div>
        </div>
    </div>
    <div id="admit-choice-modal" class="discharge-modal" aria-hidden="true">
        <div class="discharge-modal-card">
            <h3>For Admission</h3>
            <p class="compact-meta">Choose the admission area for <strong id="admit-choice-patient">this patient</strong>.</p>
            <div class="admit-choice-grid">
                <a href="#" class="btn btn-primary" id="choose-er-admit"><i class="fa-solid fa-truck-medical"></i> Emergency Room</a>
                <a href="#" class="btn btn-info" id="choose-ward-admit"><i class="fa-solid fa-hospital"></i> Ward</a>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
                <button type="button" class="btn btn-secondary" id="cancel-admit-choice">Cancel</button>
            </div>
        </div>
    </div>
    <style>
        .discharge-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(44, 62, 80, 0.55);
            padding: 20px;
            z-index: 999;
        }
        .discharge-modal.open {
            display: flex;
        }
        .discharge-modal-card {
            width: min(520px, 100%);
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
            padding: 22px;
        }
        .discharge-modal-card textarea {
            width: 100%;
            resize: vertical;
        }
        .admit-choice-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 16px;
        }
        .admit-choice-grid .btn {
            justify-content: center;
        }
    </style>
    <script>
        const admitChoiceModal = document.getElementById('admit-choice-modal');
        const bedAssignmentModal = document.getElementById('bed-assignment-modal');
        const closeBedAssignmentModal = document.getElementById('close-bed-assignment-modal');
        const admitChoicePatient = document.getElementById('admit-choice-patient');
        const chooseErAdmit = document.getElementById('choose-er-admit');
        const chooseWardAdmit = document.getElementById('choose-ward-admit');
        const cancelAdmitChoice = document.getElementById('cancel-admit-choice');
        const dischargeForms = document.querySelectorAll('.admitted-discharge-form');
        const bedAssignmentForms = document.querySelectorAll('.bed-assignment-form');
        const dischargeModal = document.getElementById('discharge-modal');
        const modalDiagnosis = document.getElementById('modal-final-diagnosis');
        const modalSpecificDiagnosis = document.getElementById('modal-specific-diagnosis');
        const cancelModal = document.getElementById('cancel-discharge-modal');
        const confirmModal = document.getElementById('confirm-discharge-modal');
        let pendingDischargeForm = null;
        let selectedAdmitPatient = 'this patient';

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value;
            return div.innerHTML;
        }

        function showConfirmPopup(message, onConfirm, title = 'Confirm Action', confirmClass = 'btn-primary') {
            const popup = document.createElement('div');
            popup.className = 'discharge-modal open';
            popup.setAttribute('aria-hidden', 'false');
            popup.innerHTML = `
                <div class="discharge-modal-card" role="dialog" aria-modal="true">
                    <h3>${escapeHtml(title)}</h3>
                    <p class="compact-meta">${escapeHtml(message)}</p>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px;">
                        <button type="button" class="btn btn-secondary" data-confirm-cancel>Cancel</button>
                        <button type="button" class="btn ${confirmClass}" data-confirm-ok>Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(popup);
            popup.querySelector('[data-confirm-cancel]').addEventListener('click', () => popup.remove());
            popup.querySelector('[data-confirm-ok]').addEventListener('click', () => {
                popup.remove();
                onConfirm();
            });
        }

        function closeAdmitChoiceModal() {
            admitChoiceModal.classList.remove('open');
            admitChoiceModal.setAttribute('aria-hidden', 'true');
            chooseErAdmit.setAttribute('href', '#');
            chooseWardAdmit.setAttribute('href', '#');
        }

        document.querySelectorAll('.admit-choice-trigger').forEach((button) => {
            button.addEventListener('click', () => {
                selectedAdmitPatient = button.dataset.patientName || 'this patient';
                admitChoicePatient.textContent = selectedAdmitPatient;
                chooseErAdmit.setAttribute('href', button.dataset.erUrl || '#');
                chooseWardAdmit.setAttribute('href', button.dataset.wardUrl || '#');
                admitChoiceModal.classList.add('open');
                admitChoiceModal.setAttribute('aria-hidden', 'false');
            });
        });

        function confirmAdmitChoice(event, label) {
            event.preventDefault();
            const targetUrl = event.currentTarget.getAttribute('href');
            if (!targetUrl || targetUrl === '#') {
                return;
            }
            closeAdmitChoiceModal();
            showConfirmPopup(
                `Are you sure you want to admit ${selectedAdmitPatient} to ${label}?`,
                () => {
                    window.location.href = targetUrl;
                },
                'Confirm Admission',
                'btn-primary'
            );
        }

        chooseErAdmit.addEventListener('click', (event) => confirmAdmitChoice(event, 'Emergency Room'));
        chooseWardAdmit.addEventListener('click', (event) => confirmAdmitChoice(event, 'Ward'));

        cancelAdmitChoice.addEventListener('click', closeAdmitChoiceModal);
        admitChoiceModal.addEventListener('click', (event) => {
            if (event.target === admitChoiceModal) {
                closeAdmitChoiceModal();
            }
        });

        if (bedAssignmentModal) {
            closeBedAssignmentModal?.addEventListener('click', () => {
                window.location.href = 'admitted_patients.php';
            });
        }

        bedAssignmentForms.forEach((bedForm) => {
            bedForm.addEventListener('submit', (event) => {
                event.preventDefault();
                const bedSelect = bedForm.querySelector('select[name="bed_id"]');
                const selectedBed = bedSelect?.selectedOptions?.[0]?.textContent.trim();
                const isTransfer = bedForm.querySelector('input[name="transfer_bed"]') !== null;

                if (!selectedBed) {
                    bedSelect?.focus();
                    return;
                }

                const title = isTransfer ? 'Confirm Bed Transfer' : 'Confirm Bed Assignment';
                const message = isTransfer
                    ? `Are you sure you want to transfer this patient to ${selectedBed}?`
                    : `Are you sure you want to assign this patient to ${selectedBed}?`;

                showConfirmPopup(message, () => bedForm.submit(), title, 'btn-primary');
            });
        });

        const closeDischargeModal = () => {
            dischargeModal.classList.remove('open');
            dischargeModal.setAttribute('aria-hidden', 'true');
            modalDiagnosis.value = '';
            modalSpecificDiagnosis.value = '';
            pendingDischargeForm = null;
        };

        dischargeForms.forEach((dischargeForm) => {
            dischargeForm.addEventListener('submit', (event) => {
                if (!dischargeForm.querySelector('.admitted-final-diagnosis').value.trim()) {
                    event.preventDefault();
                    pendingDischargeForm = dischargeForm;
                    dischargeModal.classList.add('open');
                    dischargeModal.setAttribute('aria-hidden', 'false');
                    modalDiagnosis.focus();
                    return;
                }

                event.preventDefault();
                showConfirmPopup('Are you sure you want to discharge this admitted patient?', () => dischargeForm.submit(), 'Confirm Discharge', 'btn-danger');
            });
        });

        cancelModal.addEventListener('click', closeDischargeModal);
        dischargeModal.addEventListener('click', (event) => {
            if (event.target === dischargeModal) {
                closeDischargeModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && dischargeModal.classList.contains('open')) {
                closeDischargeModal();
            }
            if (event.key === 'Escape' && bedAssignmentModal?.classList.contains('open')) {
                window.location.href = 'admitted_patients.php';
            }
        });
        confirmModal.addEventListener('click', () => {
            if (!modalDiagnosis.value.trim()) {
                modalDiagnosis.focus();
                return;
            }
            if (!pendingDischargeForm) {
                return;
            }
            pendingDischargeForm.querySelector('.admitted-final-diagnosis').value = modalDiagnosis.value.trim();
            pendingDischargeForm.querySelector('.admitted-specific-diagnosis').value = modalSpecificDiagnosis.value.trim();
            const dischargeForm = pendingDischargeForm;
            showConfirmPopup('Are you sure you want to discharge this admitted patient?', () => {
                closeDischargeModal();
                dischargeForm.submit();
            }, 'Confirm Discharge', 'btn-danger');
        });
    </script>
</body>
</html>
