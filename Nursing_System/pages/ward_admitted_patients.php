<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(array_merge(nurseRoles(), doctorRoles(), admissionRoles()));
if (hasRole(doctorRoles()) && !hasDepartment(['WARD_2F', 'WARD_3F', 'ICU', 'NICU'])) {
    header('Location: ../pages/' . homePageForRole());
    exit();
}
if (!hasRole(doctorRoles())) {
    requireDepartment(['WARD_2F', 'WARD_3F', 'ICU', 'NICU']);
}
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
ensureWardBeds($conn);

function ensureWardDischargeTatFlowSchema(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS ward_discharge_tat_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_id INT NOT NULL,
            ward VARCHAR(80) NULL,
            date_mgh_ordered DATE NOT NULL,
            time_mgh_order TIME NOT NULL,
            chart_to_phic_signature_at DATETIME NOT NULL,
            signed_soa_received_at DATETIME NOT NULL,
            disposition_at DATETIME NOT NULL,
            tagubilin_receiver_signature VARCHAR(160) NOT NULL,
            patient_survey_signature VARCHAR(160) NOT NULL,
            actual_discharge_tat_minutes INT NOT NULL DEFAULT 0,
            remarks TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_ward_tat_patient (patient_id),
            INDEX idx_ward_tat_ward (ward),
            INDEX idx_ward_tat_disposition (disposition_at)
        ) ENGINE=InnoDB
    ");
}

function wardTatMinutes(string $mghDate, string $mghTime, string $dispositionAt): int {
    $start = DateTime::createFromFormat('Y-m-d H:i', $mghDate . ' ' . substr($mghTime, 0, 5));
    $end = DateTime::createFromFormat('Y-m-d\TH:i', $dispositionAt);
    if (!$start || !$end || $end < $start) {
        return -1;
    }
    return (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
}

ensureWardDischargeTatFlowSchema($conn);

$error = '';
$success = '';
consumeFlashMessages($error, $success);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discharge_ward_patient'])) {
    $patientId = intval($_POST['patient_id'] ?? 0);
    $wardDisposition = sanitizeText($_POST['ward_disposition'] ?? '', 30);
    $finalDiagnosis = sanitizeText($_POST['final_diagnosis'] ?? '', 150);
    $specificDiagnosis = sanitizeText($_POST['specific_diagnosis'] ?? '', 2000);
    $dateMghOrdered = sanitizeText($_POST['date_mgh_ordered'] ?? '', 20);
    $timeMghOrder = sanitizeText($_POST['time_mgh_order'] ?? '', 20);
    $chartToPhicAt = sanitizeText($_POST['chart_to_phic_signature_at'] ?? '', 30);
    $signedSoaAt = sanitizeText($_POST['signed_soa_received_at'] ?? '', 30);
    $dispositionAt = sanitizeText($_POST['disposition_at'] ?? '', 30);
    $tagubilinSignature = sanitizeText($_POST['tagubilin_receiver_signature'] ?? '', 160);
    $surveySignature = sanitizeText($_POST['patient_survey_signature'] ?? '', 160);
    $tatRemarks = sanitizeText($_POST['tat_remarks'] ?? '', 2000);
    $wardDispositionOptions = wardDispositionOptions();

    $patientStmt = $conn->prepare("
        SELECT p.patient_id, p.ward_department
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
    } elseif (!isset($wardDispositionOptions[$wardDisposition])) {
        $error = 'Please select a valid ward disposition.';
    } elseif ($dateMghOrdered === '' || $timeMghOrder === '' || $chartToPhicAt === '' || $signedSoaAt === '' || $dispositionAt === '' || $tagubilinSignature === '' || $surveySignature === '') {
        $error = 'Please complete all Ward Discharge TAT fields.';
    } elseif (wardTatMinutes($dateMghOrdered, $timeMghOrder, $dispositionAt) < 0) {
        $error = 'Time of disposition must be later than the MGH order date and time.';
    } else {
        $result = updatePatientStatus($conn, $patientId, $wardDisposition, $_SESSION['user_id'], $finalDiagnosis, $specificDiagnosis);
        if ($result === true) {
            $tatMinutes = wardTatMinutes($dateMghOrdered, $timeMghOrder, $dispositionAt);
            $chartToPhicSql = str_replace('T', ' ', $chartToPhicAt) . ':00';
            $signedSoaSql = str_replace('T', ' ', $signedSoaAt) . ':00';
            $dispositionSql = str_replace('T', ' ', $dispositionAt) . ':00';
            $ward = $patient['ward_department'] ?? '';
            $createdBy = $_SESSION['user_id'] ?? null;
            $stmt = $conn->prepare("
                INSERT INTO ward_discharge_tat_records (
                    patient_id, ward, date_mgh_ordered, time_mgh_order, chart_to_phic_signature_at,
                    signed_soa_received_at, disposition_at, tagubilin_receiver_signature,
                    patient_survey_signature, actual_discharge_tat_minutes, remarks, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    ward=VALUES(ward),
                    date_mgh_ordered=VALUES(date_mgh_ordered),
                    time_mgh_order=VALUES(time_mgh_order),
                    chart_to_phic_signature_at=VALUES(chart_to_phic_signature_at),
                    signed_soa_received_at=VALUES(signed_soa_received_at),
                    disposition_at=VALUES(disposition_at),
                    tagubilin_receiver_signature=VALUES(tagubilin_receiver_signature),
                    patient_survey_signature=VALUES(patient_survey_signature),
                    actual_discharge_tat_minutes=VALUES(actual_discharge_tat_minutes),
                    remarks=VALUES(remarks),
                    created_by=VALUES(created_by)
            ");
            $stmt->bind_param("issssssssisi", $patientId, $ward, $dateMghOrdered, $timeMghOrder, $chartToPhicSql, $signedSoaSql, $dispositionSql, $tagubilinSignature, $surveySignature, $tatMinutes, $tatRemarks, $createdBy);
            $stmt->execute();
            $success = 'Ward patient disposition updated successfully.';
        } else {
            $error = $result;
        }
    }

    if ($error) {
        setFlashMessage('error', $error);
    }
    if ($success) {
        setFlashMessage('success', $success);
    }
    redirectAfterPost('ward_admitted_patients.php');
}

$wardDepartments = wardDepartmentOptions();
$wardDispositionOptions = wardDispositionOptions();
$patients = $conn->query("
    SELECT
        p.*,
        COALESCE(admission_event.admitted_at, p.updated_at, p.admission_date, p.created_at) AS admitted_at,
        b.bed_number,
        b.room_number,
        t.triage_level,
        t.chief_complaint,
        t.assessment
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
        AND (
            b.bed_type = 'General'
            OR EXISTS (
                SELECT 1
                FROM patient_tracking ward_pt
                WHERE ward_pt.patient_id = p.patient_id
                    AND ward_pt.action = 'Ward Admission Seeded'
            )
        )
    ORDER BY p.ward_department, b.room_number, b.bed_number, p.updated_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Admitted Patients - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
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
                <h2>Ward Admitted Patients</h2>
                <p class="compact-meta">Patients admitted to the ward from ER disposition.</p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px;">
                    <a href="ward_dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-chart-line"></i> Ward Dashboard</a>
                    <a href="ward_beds.php" class="btn btn-info"><i class="fa-solid fa-bed-pulse"></i> Ward Bed Tracker</a>
                </div>
            </section>

            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <section class="patients-section">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Ward</th>
                                <th>Bed</th>
                                <th>Triage</th>
                                <th>Diagnosis</th>
                                <th>Ward Stay</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr><td colspan="8" class="text-center">No admitted ward patients found</td></tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <?php $wardStay = max(0, (int) floor((time() - strtotime($patient['admitted_at'])) / 60)); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($patient['permanent_patient_id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars(patientFullName($patient)); ?></td>
                                        <td><?php echo htmlspecialchars($wardDepartments[$patient['ward_department']] ?? ($patient['ward_department'] ?? 'MEDICAL')); ?></td>
                                        <td><?php echo htmlspecialchars($patient['bed_number'] ? $patient['bed_number'] . ' - Room ' . $patient['room_number'] : 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars(triageLevelLabel($patient['triage_level'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($patient['final_diagnosis'] ?? 'Pending'); ?></td>
                                        <td><?php echo htmlspecialchars(formatDaysHoursMinutes($wardStay)); ?></td>
                                        <td>
                                            <a href="patient_detail.php?id=<?php echo (int) $patient['patient_id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <?php if (empty($patient['current_bed'])): ?>
                                                <a href="ward_beds.php" class="btn btn-sm btn-primary">Assign Bed</a>
                                            <?php endif; ?>
                                            <form method="POST" class="ward-discharge-form" style="display: inline;">
                                                <input type="hidden" name="patient_id" value="<?php echo (int) $patient['patient_id']; ?>">
                                                <input type="hidden" name="discharge_ward_patient" value="1">
                                                <input type="hidden" name="ward_disposition" class="ward-disposition">
                                                <input type="hidden" name="final_diagnosis" class="ward-final-diagnosis">
                                                <input type="hidden" name="specific_diagnosis" class="ward-specific-diagnosis">
                                                <input type="hidden" name="date_mgh_ordered" class="ward-date-mgh-ordered">
                                                <input type="hidden" name="time_mgh_order" class="ward-time-mgh-order">
                                                <input type="hidden" name="chart_to_phic_signature_at" class="ward-chart-to-phic">
                                                <input type="hidden" name="signed_soa_received_at" class="ward-signed-soa">
                                                <input type="hidden" name="disposition_at" class="ward-disposition-at">
                                                <input type="hidden" name="tagubilin_receiver_signature" class="ward-tagubilin-signature">
                                                <input type="hidden" name="patient_survey_signature" class="ward-survey-signature">
                                                <input type="hidden" name="tat_remarks" class="ward-tat-remarks">
                                                <button type="submit" class="btn btn-sm btn-danger">Discharge / TAT</button>
                                            </form>
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
    <div id="ward-discharge-modal" class="ward-modal" aria-hidden="true">
        <div class="ward-modal-card">
            <h3>Ward Disposition</h3>
            <p class="compact-meta">Complete the ward disposition and discharge TAT timestamps for this patient.</p>
            <div class="form-group">
                <label for="modal-ward-disposition">Disposition *</label>
                <select id="modal-ward-disposition">
                    <option value="">Select disposition</option>
                    <?php foreach ($wardDispositionOptions as $value => $label): ?>
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
            <div class="ward-tat-grid">
                <div class="form-group">
                    <label for="modal-date-mgh-ordered">Date of MGH Ordered *</label>
                    <input type="date" id="modal-date-mgh-ordered" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                </div>
                <div class="form-group">
                    <label for="modal-time-mgh-order">Time of MGH Order *</label>
                    <input type="time" id="modal-time-mgh-order" value="<?php echo htmlspecialchars(date('H:i')); ?>">
                </div>
                <div class="form-group">
                    <label for="modal-chart-to-phic">Time Chart to PhilHealth / PHIC Signature *</label>
                    <input type="datetime-local" id="modal-chart-to-phic" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i')); ?>">
                </div>
                <div class="form-group">
                    <label for="modal-signed-soa">Time Signed SOA Received from Billing / Billing Signature *</label>
                    <input type="datetime-local" id="modal-signed-soa" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i')); ?>">
                </div>
                <div class="form-group">
                    <label for="modal-disposition-at">Time of Disposition *</label>
                    <input type="datetime-local" id="modal-disposition-at" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i')); ?>">
                </div>
                <div class="form-group">
                    <label>Actual Patient Discharge TAT Duration</label>
                    <input type="text" id="modal-tat-duration" value="0 min" readonly>
                </div>
            </div>
            <div class="form-group">
                <label for="modal-tagubilin-signature">Tagubilin Receiving Patient's Signature *</label>
                <input type="text" id="modal-tagubilin-signature" placeholder="Name / signature received by">
            </div>
            <div class="form-group">
                <label for="modal-survey-signature">Patient's Survey Form / Patient's Signature *</label>
                <input type="text" id="modal-survey-signature" placeholder="Name / patient signature">
            </div>
            <div class="form-group">
                <label for="modal-tat-remarks">TAT Remarks</label>
                <textarea id="modal-tat-remarks" rows="3" placeholder="Optional billing, PHIC, or discharge notes"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-secondary" id="cancel-ward-discharge">Cancel</button>
                <button type="button" class="btn btn-primary" id="continue-ward-discharge">Continue</button>
            </div>
        </div>
    </div>
    <style>
        .ward-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(44, 62, 80, 0.55);
            padding: 20px;
            z-index: 999;
        }
        .ward-modal.open { display: flex; }
        .ward-modal-card {
            width: min(760px, 100%);
            max-height: 92vh;
            overflow: auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
            padding: 22px;
        }
        .ward-tat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        .ward-modal-card textarea {
            width: 100%;
            resize: vertical;
        }
        @media (max-width: 720px) {
            .ward-tat-grid { grid-template-columns: 1fr; }
        }
    </style>
    <script>
        const wardDischargeModal = document.getElementById('ward-discharge-modal');
        const modalWardDisposition = document.getElementById('modal-ward-disposition');
        const modalDiagnosis = document.getElementById('modal-final-diagnosis');
        const modalSpecificDiagnosis = document.getElementById('modal-specific-diagnosis');
        const modalDateMgh = document.getElementById('modal-date-mgh-ordered');
        const modalTimeMgh = document.getElementById('modal-time-mgh-order');
        const modalChartToPhic = document.getElementById('modal-chart-to-phic');
        const modalSignedSoa = document.getElementById('modal-signed-soa');
        const modalDispositionAt = document.getElementById('modal-disposition-at');
        const modalTatDuration = document.getElementById('modal-tat-duration');
        const modalTagubilinSignature = document.getElementById('modal-tagubilin-signature');
        const modalSurveySignature = document.getElementById('modal-survey-signature');
        const modalTatRemarks = document.getElementById('modal-tat-remarks');
        let pendingWardDischargeForm = null;

        function formatTatMinutes(minutes) {
            if (minutes < 0 || Number.isNaN(minutes)) {
                return 'Invalid time range';
            }
            const days = Math.floor(minutes / 1440);
            const hours = Math.floor((minutes % 1440) / 60);
            const mins = minutes % 60;
            const parts = [];
            if (days) parts.push(`${days} d`);
            if (hours) parts.push(`${hours} hr`);
            parts.push(`${mins} min`);
            return parts.join(' ');
        }

        function currentTatMinutes() {
            if (!modalDateMgh.value || !modalTimeMgh.value || !modalDispositionAt.value) {
                return -1;
            }
            const start = new Date(`${modalDateMgh.value}T${modalTimeMgh.value}`);
            const end = new Date(modalDispositionAt.value);
            return Math.floor((end.getTime() - start.getTime()) / 60000);
        }

        function refreshTatPreview() {
            modalTatDuration.value = formatTatMinutes(currentTatMinutes());
        }

        function closeWardDischargeModal() {
            wardDischargeModal.classList.remove('open');
            modalWardDisposition.value = '';
            modalDiagnosis.value = '';
            modalSpecificDiagnosis.value = '';
            modalTagubilinSignature.value = '';
            modalSurveySignature.value = '';
            modalTatRemarks.value = '';
            pendingWardDischargeForm = null;
        }

        function showWardConfirm(message, onConfirm) {
            const popup = document.createElement('div');
            popup.className = 'ward-modal open';
            popup.innerHTML = `
                <div class="ward-modal-card" role="dialog" aria-modal="true">
                    <h3>Confirm Ward Discharge</h3>
                    <p class="compact-meta">${message}</p>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px;">
                        <button type="button" class="btn btn-secondary" data-cancel>Cancel</button>
                        <button type="button" class="btn btn-danger" data-ok>Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(popup);
            popup.querySelector('[data-cancel]').addEventListener('click', () => popup.remove());
            popup.querySelector('[data-ok]').addEventListener('click', () => {
                popup.remove();
                onConfirm();
            });
        }

        document.querySelectorAll('.ward-discharge-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                pendingWardDischargeForm = form;
                refreshTatPreview();
                wardDischargeModal.classList.add('open');
                modalWardDisposition.focus();
            });
        });

        [modalDateMgh, modalTimeMgh, modalDispositionAt].forEach((input) => {
            input.addEventListener('input', refreshTatPreview);
        });

        document.getElementById('cancel-ward-discharge').addEventListener('click', closeWardDischargeModal);
        wardDischargeModal.addEventListener('click', (event) => {
            if (event.target === wardDischargeModal) {
                closeWardDischargeModal();
            }
        });
        document.getElementById('continue-ward-discharge').addEventListener('click', () => {
            if (!modalWardDisposition.value || !pendingWardDischargeForm) {
                modalWardDisposition.focus();
                return;
            }

            if (!modalDiagnosis.value.trim()) {
                modalDiagnosis.focus();
                return;
            }

            const requiredTatFields = [
                modalDateMgh,
                modalTimeMgh,
                modalChartToPhic,
                modalSignedSoa,
                modalDispositionAt,
                modalTagubilinSignature,
                modalSurveySignature
            ];
            const missingTatField = requiredTatFields.find((field) => !field.value.trim());
            if (missingTatField) {
                missingTatField.focus();
                return;
            }

            if (currentTatMinutes() < 0) {
                modalDispositionAt.focus();
                return;
            }

            pendingWardDischargeForm.querySelector('.ward-disposition').value = modalWardDisposition.value;
            pendingWardDischargeForm.querySelector('.ward-final-diagnosis').value = modalDiagnosis.value.trim();
            pendingWardDischargeForm.querySelector('.ward-specific-diagnosis').value = modalSpecificDiagnosis.value.trim();
            pendingWardDischargeForm.querySelector('.ward-date-mgh-ordered').value = modalDateMgh.value;
            pendingWardDischargeForm.querySelector('.ward-time-mgh-order').value = modalTimeMgh.value;
            pendingWardDischargeForm.querySelector('.ward-chart-to-phic').value = modalChartToPhic.value;
            pendingWardDischargeForm.querySelector('.ward-signed-soa').value = modalSignedSoa.value;
            pendingWardDischargeForm.querySelector('.ward-disposition-at').value = modalDispositionAt.value;
            pendingWardDischargeForm.querySelector('.ward-tagubilin-signature').value = modalTagubilinSignature.value.trim();
            pendingWardDischargeForm.querySelector('.ward-survey-signature').value = modalSurveySignature.value.trim();
            pendingWardDischargeForm.querySelector('.ward-tat-remarks').value = modalTatRemarks.value.trim();
            const form = pendingWardDischargeForm;
            showWardConfirm(`Confirm ward disposition and save discharge TAT duration of ${modalTatDuration.value}?`, () => {
                closeWardDischargeModal();
                form.submit();
            });
        });
    </script>
</body>
</html>
