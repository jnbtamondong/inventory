<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(doctorRoles());
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);

$error = '';
$success = '';
$doctorStatuses = ['waiting_for_consultation', 'consultation', 'treatment', 'waiting_results', 'ready_for_disposition'];
$doctorStatusOptions = [
    'waiting_for_consultation' => 'WAITING FOR CONSULTATION',
    'consultation' => 'CONSULTATION',
    'treatment' => 'UNDER TREATMENT',
    'waiting_results' => 'WAITING FOR RESULTS',
    'ready_for_disposition' => 'READY FOR DISPOSITION'
];
$dispositionOptions = patientDispositionOptions();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_doctor_status'])) {
    $patientId = intval($_POST['patient_id'] ?? 0);
    $disposition = $_POST['disposition'] ?? '';
    $nextStatus = $disposition !== '' ? $disposition : ($_POST['status'] ?? '');
    $finalDiagnosis = $_POST['final_diagnosis'] ?? null;
    $specificDiagnosis = $_POST['specific_diagnosis'] ?? null;

    $allowedStatuses = array_merge(array_keys($doctorStatusOptions), array_keys($dispositionOptions));
    if (!in_array($nextStatus, $allowedStatuses, true)) {
        $error = 'Invalid doctor workflow status.';
    } elseif ($disposition !== '') {
        $patientStatusCheck = $conn->prepare("SELECT status FROM patients WHERE patient_id = ?");
        $patientStatusCheck->bind_param("i", $patientId);
        $patientStatusCheck->execute();
        $currentPatient = $patientStatusCheck->get_result()->fetch_assoc();

        if (!$currentPatient || $currentPatient['status'] !== 'ready_for_disposition') {
            $error = 'Disposition can only be selected when patient status is Ready for Disposition.';
        } else {
            $result = updatePatientStatus($conn, $patientId, $nextStatus, $_SESSION['user_id'], $finalDiagnosis, $specificDiagnosis);
            if ($result === true) {
                $success = 'Patient workflow updated successfully.';
            } else {
                $error = $result;
            }
        }
    } else {
        $result = updatePatientStatus($conn, $patientId, $nextStatus, $_SESSION['user_id'], $finalDiagnosis, $specificDiagnosis);
        if ($result === true) {
            $success = 'Patient workflow updated successfully.';
        } else {
            $error = $result;
        }
    }
}

$queueStatuses = "'" . implode("','", array_map([$conn, 'real_escape_string'], $doctorStatuses)) . "'";
$patients = $conn->query("
    SELECT
        p.*,
        t.triage_id,
        t.triage_level,
        t.chief_complaint,
        t.assessment,
        COALESCE(p.province_name, pr.province_name) as display_province_name,
        COALESCE(p.municipality_name, m.municipality_name) as display_municipality_name,
        COALESCE(p.barangay_name, b.barangay_name) as display_barangay_name,
        " . patientCurrentWaitMinutesSql('p') . " as wait_time
    FROM patients p
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    LEFT JOIN provinces pr ON p.province_id = pr.province_id
    LEFT JOIN municipalities m ON p.municipality_id = m.municipality_id
    LEFT JOIN barangays b ON p.barangay_id = b.barangay_id
    WHERE p.status IN ($queueStatuses)
    ORDER BY CASE
        WHEN t.triage_level = 'red' THEN 1
        WHEN t.triage_level = 'yellow' THEN 2
        WHEN t.triage_level = 'green' THEN 3
        WHEN t.triage_level = 'black' THEN 4
        ELSE 5
    END, p.arrival_time ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Queue - Nursing Service System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand"><h1>Nursing Service System</h1></div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?> (<?php echo htmlspecialchars(roleLabel($_SESSION['role'])); ?>)</span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <section class="patients-section">
                <h2>Consultation Queue</h2>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Queue No.</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Sex</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Triage Level</th>
                                <th>Waiting Time</th>
                                <th>Chief Complaint</th>
                                <th>Status</th>
                                <th>Disposition</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr><td colspan="13" class="text-center">No patients for consultation</td></tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <tr class="patient-row triage-<?php echo htmlspecialchars($patient['triage_level'] ?? 'none'); ?> <?php echo (int) ($patient['wait_time'] ?? 0) >= 30 ? 'wait-alert-row' : ''; ?>">
                                        <td><?php echo htmlspecialchars($patient['patient_code']); ?></td>
                                        <td><strong><?php echo htmlspecialchars(formatQueueNumber($patient['queue_number'] ?? null)); ?></strong></td>
                                        <td><?php echo htmlspecialchars(patientFullName($patient)); ?></td>
                                        <td><?php echo htmlspecialchars($patient['age'] ?? calculateAge($patient['date_of_birth'])); ?></td>
                                        <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                                        <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($patient['house_no_street'] ?? '') . ', ' . ($patient['display_barangay_name'] ?? '') . ', ' . ($patient['display_municipality_name'] ?? '') . ', ' . ($patient['display_province_name'] ?? ''), ', ')); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo htmlspecialchars($patient['triage_level'] ?? 'secondary'); ?>">
                                                <?php echo htmlspecialchars(triageLevelLabel($patient['triage_level'] ?? '')); ?>
                                            </span>
                                        </td>
                                        <td><span class="wait-time <?php echo (int) ($patient['wait_time'] ?? 0) >= 30 ? 'wait-long' : ''; ?>"><?php echo htmlspecialchars(formatWaitTime($patient['wait_time'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($patient['chief_complaint'] ?? 'N/A'); ?></td>
                                        <td>
                                            <select name="status" form="doctor-status-form-<?php echo $patient['patient_id']; ?>" data-original-status="<?php echo htmlspecialchars($patient['status']); ?>">
                                                <?php foreach ($doctorStatusOptions as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $patient['status'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary disposition-btn" data-form-id="doctor-status-form-<?php echo $patient['patient_id']; ?>" <?php echo $patient['status'] !== 'ready_for_disposition' ? 'disabled' : ''; ?>>Set Disposition</button>
                                            <?php if ($patient['status'] !== 'ready_for_disposition'): ?>
                                                <div class="compact-meta">Available when ready for disposition</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" id="doctor-status-form-<?php echo $patient['patient_id']; ?>" class="doctor-status-form" style="display: inline;">
                                                <input type="hidden" name="patient_id" value="<?php echo (int) $patient['patient_id']; ?>">
                                                <input type="hidden" name="update_doctor_status" value="1">
                                                <input type="hidden" name="disposition" class="disposition-input">
                                                <input type="hidden" name="final_diagnosis" class="final-diagnosis-input">
                                                <input type="hidden" name="specific_diagnosis" class="specific-diagnosis-input">
                                                <button type="submit" class="btn btn-sm btn-primary status-save-btn" disabled>Save</button>
                                            </form>
                                            <a href="patient_detail.php?id=<?php echo (int) $patient['patient_id']; ?>" class="btn btn-sm btn-info">View</a>
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

    <datalist id="diagnosis-options">
        <?php foreach (diagnosisOptions() as $diagnosis): ?>
            <option value="<?php echo htmlspecialchars($diagnosis); ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <div id="discharge-modal" class="discharge-modal" aria-hidden="true">
        <div class="discharge-modal-card">
            <h3 id="diagnosis-modal-title">Patient Disposition</h3>
            <p class="compact-meta" id="diagnosis-modal-help">Choose the disposition and diagnosis before updating the patient status.</p>
            <div class="form-group">
                <label for="modal-disposition">Disposition *</label>
                <select id="modal-disposition">
                    <option value="">Select disposition</option>
                    <?php foreach ($dispositionOptions as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
        const diagnosisModal = document.getElementById('discharge-modal');
        const modalDisposition = document.getElementById('modal-disposition');
        const modalDiagnosis = document.getElementById('modal-final-diagnosis');
        const modalSpecificDiagnosis = document.getElementById('modal-specific-diagnosis');
        const modalTitle = document.getElementById('diagnosis-modal-title');
        const modalHelp = document.getElementById('diagnosis-modal-help');
        const cancelModal = document.getElementById('cancel-discharge-modal');
        const confirmModal = document.getElementById('confirm-discharge-modal');
        let pendingForm = null;

        function closeDiagnosisModal() {
            diagnosisModal.classList.remove('open');
            diagnosisModal.setAttribute('aria-hidden', 'true');
            pendingForm = null;
            modalDisposition.value = '';
            modalDiagnosis.value = '';
            modalSpecificDiagnosis.value = '';
        }

        document.querySelectorAll('.doctor-status-form').forEach((form) => {
            const statusSelect = document.querySelector(`select[name="status"][form="${form.id}"]`);
            const button = form.querySelector('.status-save-btn');

            const updateSaveState = () => {
                const statusChanged = statusSelect && statusSelect.value !== statusSelect.dataset.originalStatus;
                button.disabled = !statusChanged;
            };

            if (statusSelect) {
                statusSelect.addEventListener('change', updateSaveState);
            }

            form.addEventListener('submit', (event) => {
                const dispositionValue = form.querySelector('.disposition-input').value;
                if (dispositionValue && !form.querySelector('.final-diagnosis-input').value.trim()) {
                    event.preventDefault();
                    pendingForm = form;
                    modalDisposition.value = dispositionValue;
                    modalTitle.textContent = 'Patient Disposition';
                    modalHelp.textContent = 'Choose the disposition and diagnosis before updating the patient status.';
                    diagnosisModal.classList.add('open');
                    diagnosisModal.setAttribute('aria-hidden', 'false');
                    modalDiagnosis.focus();
                }
            });
        });

        cancelModal.addEventListener('click', closeDiagnosisModal);
        diagnosisModal.addEventListener('click', (event) => {
            if (event.target === diagnosisModal) {
                closeDiagnosisModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && diagnosisModal.classList.contains('open')) {
                closeDiagnosisModal();
            }
        });
        confirmModal.addEventListener('click', () => {
            if (!modalDisposition.value) {
                modalDisposition.focus();
                return;
            }
            if (!modalDiagnosis.value.trim()) {
                modalDiagnosis.focus();
                return;
            }
            if (pendingForm) {
                pendingForm.querySelector('.disposition-input').value = modalDisposition.value;
                pendingForm.querySelector('.final-diagnosis-input').value = modalDiagnosis.value.trim();
                pendingForm.querySelector('.specific-diagnosis-input').value = modalSpecificDiagnosis.value.trim();
                const form = pendingForm;
                closeDiagnosisModal();
                form.submit();
            }
        });

        document.querySelectorAll('.disposition-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const form = document.getElementById(button.dataset.formId);
                if (!form) {
                    return;
                }
                pendingForm = form;
                modalTitle.textContent = 'Patient Disposition';
                modalHelp.textContent = 'Choose the disposition and diagnosis before updating the patient status.';
                diagnosisModal.classList.add('open');
                diagnosisModal.setAttribute('aria-hidden', 'false');
                modalDisposition.focus();
            });
        });
    </script>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
