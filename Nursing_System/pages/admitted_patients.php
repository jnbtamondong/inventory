<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(admissionRoles());
ensurePatientWorkflowSchema($conn);
ensureEmergencyRoomBeds($conn);
$schemaResult = ensureAdmittedPatientSchema($conn);

$patient_id = intval($_GET['id'] ?? 0);
$error = $schemaResult === true ? '' : $schemaResult;
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaResult === true) {
    $patient_id = intval($_POST['patient_id'] ?? 0);

    $patientCheck = $conn->prepare("SELECT patient_id, current_bed FROM patients WHERE patient_id = ? AND status = 'for_admission'");
    $patientCheck->bind_param("i", $patient_id);
    $patientCheck->execute();
    $admittedPatient = $patientCheck->get_result()->fetch_assoc();

    if (!$admittedPatient) {
        $error = 'Please select an admission patient.';
    } elseif (isset($_POST['discharge_patient'])) {
        $finalDiagnosis = $_POST['final_diagnosis'] ?? null;
        $specificDiagnosis = $_POST['specific_diagnosis'] ?? null;
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
            $bedCheck = $conn->prepare("SELECT bed_id, bed_number FROM beds WHERE bed_id = ? AND status = 'available' AND patient_id IS NULL");
            $bedCheck->bind_param("i", $bed_id);
            $bedCheck->execute();
            $selectedBed = $bedCheck->get_result()->fetch_assoc();

            if (!$selectedBed) {
                $error = 'Selected bed is no longer available.';
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

                    $assignBed = $conn->prepare("UPDATE beds SET status = 'occupied', patient_id = ? WHERE bed_id = ?");
                    $assignBed->bind_param("ii", $patient_id, $bed_id);
                    $assignBed->execute();

                    $updatePatient = $conn->prepare("UPDATE patients SET current_bed = ?, status = 'admit_inpatient' WHERE patient_id = ?");
                    $updatePatient->bind_param("ii", $bed_id, $patient_id);
                    $updatePatient->execute();

                    $isTransfer = !empty($admittedPatient['current_bed']);
                    $description = $isTransfer
                        ? 'Transferred admission patient from bed ' . $oldBedNumber . ' to bed ' . $selectedBed['bed_number']
                        : 'Assigned admission patient to bed ' . $selectedBed['bed_number'];
                    $user_id = $_SESSION['user_id'];
                    $trackAction = $isTransfer ? 'Bed Transferred' : 'Bed Assigned';
                    $trackStmt = $conn->prepare("INSERT INTO patient_tracking (patient_id, action, description, user_id) VALUES (?, ?, ?, ?)");
                    $trackStmt->bind_param("issi", $patient_id, $trackAction, $description, $user_id);
                    $trackStmt->execute();

                    $conn->commit();
                    $success = $isTransfer
                        ? 'Bed transferred successfully. Bed Tracker and Beds are now updated.'
                        : 'Bed assigned successfully. Bed Tracker and Beds are now updated.';
                    $patient_id = 0;
                } catch (Throwable $exception) {
                    $conn->rollback();
                    $error = 'Failed to update bed assignment: ' . $exception->getMessage();
                }
            }
        }
    }
}

$admittedPatients = [];
if ($schemaResult === true) {
    $admittedPatients = $conn->query("
        SELECT
            p.patient_id,
            p.patient_code,
            CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) as patient_name,
            p.age,
            p.gender,
            p.phone,
            p.arrival_time,
            p.updated_at,
            b.bed_number,
            apr.updated_at as record_updated_at
        FROM patients p
        LEFT JOIN beds b ON p.current_bed = b.bed_id
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
        $availableBeds = $conn->query("
            SELECT bed_id, bed_number, room_number, bed_type
            FROM beds
            WHERE status = 'available' AND patient_id IS NULL
            ORDER BY room_number, bed_number
        ")->fetch_all(MYSQLI_ASSOC);

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
    <title>Admission - Nursing Service System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .admitted-layout {
            display: grid;
            grid-template-columns: minmax(320px, 0.9fr) minmax(420px, 1.4fr);
            gap: 20px;
            align-items: start;
        }

        .compact-meta {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
        }

        .bed-assignment-panel {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
            margin-top: 16px;
            margin-bottom: 18px;
        }

        .bed-assignment-panel form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: end;
        }

        @media (max-width: 980px) {
            .admitted-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand"><h1>Nursing Service System</h1></div>
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
                <h2>Emergency Room - Admission</h2>
            </div>

            <div class="admitted-layout">
                <section class="patients-section">
                    <h3>For Admission</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Bed</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($admittedPatients)): ?>
                                    <tr><td colspan="3" class="text-center">No patients for admission found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($admittedPatients as $patient): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($patient['patient_name']); ?></strong>
                                                <div class="compact-meta">
                                                    <?php echo htmlspecialchars($patient['patient_code']); ?><br>
                                                    <?php echo htmlspecialchars(($patient['age'] ?? 'N/A') . ' yrs / ' . ($patient['gender'] ?? 'N/A')); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['bed_number'] ?? 'Unassigned'); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-primary" href="admitted_patients.php?id=<?php echo $patient['patient_id']; ?>#bed-assignment">Assign Bed</a>
                                                <?php if (hasRole('admin')): ?>
                                                    <form method="POST" class="admitted-discharge-form" style="display: inline;">
                                                        <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient['patient_id']); ?>">
                                                        <input type="hidden" name="discharge_patient" value="1">
                                                        <input type="hidden" name="final_diagnosis" class="admitted-final-diagnosis">
                                                        <input type="hidden" name="specific_diagnosis" class="admitted-specific-diagnosis">
                                                        <button type="submit" class="btn btn-sm btn-danger">Discharge</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <?php if ($selectedPatient): ?>
                    <section class="patients-section">
                        <h3><?php echo htmlspecialchars($selectedPatient['patient_name']); ?></h3>
                        <p class="compact-meta">
                            <?php echo htmlspecialchars($selectedPatient['patient_code']); ?> |
                            <?php echo htmlspecialchars(statusLabel($selectedPatient['status'])); ?> |
                            Bed: <?php echo htmlspecialchars($selectedPatient['bed_number'] ?? 'Unassigned'); ?> |
                            Chief Complaint: <?php echo htmlspecialchars($selectedPatient['chief_complaint'] ?? 'N/A'); ?>
                        </p>

                        <?php $hasCurrentBed = !empty($selectedPatient['current_bed']); ?>
                        <div class="bed-assignment-panel" id="bed-assignment">
                            <h3 style="margin-bottom: 10px;">Bed Assignment / Transfer</h3>
                            <p class="compact-meta" style="margin-bottom: 12px;">
                                Current Bed: <?php echo htmlspecialchars($selectedPatient['bed_number'] ?? 'Unassigned'); ?>
                                <?php if (!empty($selectedPatient['room_number'])): ?>
                                    | Room <?php echo htmlspecialchars($selectedPatient['room_number']); ?>
                                <?php endif; ?>
                            </p>
                            <form method="POST">
                                <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($selectedPatient['patient_id']); ?>">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="bed-id"><?php echo $hasCurrentBed ? 'Transfer To Available Bed' : 'Available Bed'; ?></label>
                                    <select id="bed-id" name="bed_id" required>
                                        <option value="">Select Available Bed</option>
                                        <?php foreach ($availableBeds as $bed): ?>
                                            <option value="<?php echo htmlspecialchars($bed['bed_id']); ?>">
                                                <?php echo htmlspecialchars($bed['bed_number'] . ' - Room ' . $bed['room_number'] . ' (' . $bed['bed_type'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button
                                    type="submit"
                                    name="<?php echo $hasCurrentBed ? 'transfer_bed' : 'assign_bed'; ?>"
                                    class="btn btn-primary"
                                    <?php echo empty($availableBeds) ? 'disabled' : ''; ?>
                                >
                                    <?php echo $hasCurrentBed ? 'Transfer Bed' : 'Assign Bed'; ?>
                                </button>
                            </form>
                            <?php if (empty($availableBeds)): ?>
                                <p class="compact-meta" style="margin-top: 10px;">No available beds right now.</p>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top: 14px;">
                            <a href="admitted_patients.php" class="btn btn-secondary">Close</a>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="patients-section">
                        <h3>Admission Record</h3>
                        <p>Select a patient for admission to assign a bed and confirm admission.</p>
                    </section>
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
    </style>
    <script>
        const dischargeForms = document.querySelectorAll('.admitted-discharge-form');
        const dischargeModal = document.getElementById('discharge-modal');
        const modalDiagnosis = document.getElementById('modal-final-diagnosis');
        const modalSpecificDiagnosis = document.getElementById('modal-specific-diagnosis');
        const cancelModal = document.getElementById('cancel-discharge-modal');
        const confirmModal = document.getElementById('confirm-discharge-modal');
        let pendingDischargeForm = null;

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
                }
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
            closeDischargeModal();
            dischargeForm.submit();
        });
    </script>
</body>
</html>
