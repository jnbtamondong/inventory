<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(array_merge(admissionRoles(), nurseRoles(), doctorRoles()));
if (hasRole(doctorRoles()) && !hasDepartment('emergency_room')) {
    header('Location: ../pages/' . homePageForRole());
    exit();
}
ensurePatientWorkflowSchema($conn);

$error = '';
$success = '';
consumeFlashMessages($error, $success);
$canDischargeAdmittedPatient = hasRole('admin') || hasRole(admittedPatientDischargeRoles());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discharge_patient'])) {
    $patient_id = intval($_POST['patient_id'] ?? 0);
    $finalDiagnosis = sanitizeText($_POST['final_diagnosis'] ?? '', 150);
    $specificDiagnosis = sanitizeText($_POST['specific_diagnosis'] ?? '', 2000);

    $patientCheck = $conn->prepare("
        SELECT p.patient_id
        FROM patients p
        LEFT JOIN beds b ON p.current_bed = b.bed_id
        WHERE p.patient_id = ?
            AND p.status = 'admit_inpatient'
            AND b.room_number LIKE 'ER-%'
    ");
    $patientCheck->bind_param("i", $patient_id);
    $patientCheck->execute();
    $admittedPatient = $patientCheck->get_result()->fetch_assoc();

    if (!$canDischargeAdmittedPatient) {
        $error = 'You do not have permission to discharge admitted patients.';
    } elseif (!$admittedPatient) {
        $error = 'Please select an admitted patient.';
    } else {
        $statusUpdateResult = updatePatientStatus($conn, $patient_id, 'discharge', $_SESSION['user_id'], $finalDiagnosis, $specificDiagnosis);

        if ($statusUpdateResult === true) {
            $success = 'Admitted patient discharged successfully.';
        } else {
            $error = $statusUpdateResult;
        }
    }

    if ($error) {
        setFlashMessage('error', $error);
    }
    if ($success) {
        setFlashMessage('success', $success);
    }
    redirectAfterPost('admitted_list.php');
}

$patients = $conn->query("
    SELECT
        p.*,
        b.bed_number,
        b.room_number,
        t.triage_level,
        t.chief_complaint
    FROM patients p
    LEFT JOIN beds b ON p.current_bed = b.bed_id
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    WHERE p.status = 'admit_inpatient'
        AND b.room_number LIKE 'ER-%'
    ORDER BY p.updated_at DESC, p.patient_id DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ER Admitted Patients - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
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
            <section class="patients-section">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <h2>ER Admitted Patients</h2>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Age / Sex</th>
                                <th>Triage</th>
                                <th>Bed</th>
                                <th>Diagnosis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr><td colspan="7" class="text-center">No ER admitted patients found</td></tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($patient['permanent_patient_id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars(patientFullName($patient)); ?></td>
                                        <td><?php echo htmlspecialchars(($patient['age'] ?? calculateAge($patient['date_of_birth'])) . ' / ' . $patient['gender']); ?></td>
                                        <td><?php echo htmlspecialchars(triageLevelLabel($patient['triage_level'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($patient['bed_number'] ? $patient['bed_number'] . ' - Room ' . $patient['room_number'] : 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars($patient['final_diagnosis'] ?? 'Pending'); ?></td>
                                        <td>
                                            <a href="patient_detail.php?id=<?php echo (int) $patient['patient_id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <?php if ($canDischargeAdmittedPatient): ?>
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

        function showConfirmPopup(message, onConfirm) {
            const popup = document.createElement('div');
            popup.className = 'discharge-modal open';
            popup.setAttribute('aria-hidden', 'false');
            popup.innerHTML = `
                <div class="discharge-modal-card" role="dialog" aria-modal="true">
                    <h3>Confirm Discharge</h3>
                    <p class="compact-meta">${message}</p>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px;">
                        <button type="button" class="btn btn-secondary" data-confirm-cancel>Cancel</button>
                        <button type="button" class="btn btn-danger" data-confirm-ok>Confirm</button>
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
                showConfirmPopup('Are you sure you want to discharge this admitted patient?', () => dischargeForm.submit());
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
            showConfirmPopup('Are you sure you want to discharge this admitted patient?', () => {
                closeDischargeModal();
                dischargeForm.submit();
            });
        });
    </script>
</body>
</html>
