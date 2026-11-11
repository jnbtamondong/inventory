<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/patient-registration.php';
require_once '../includes/nis-tracker.php';

requireLogin();
requirePermission('patients', 'edit', 'ER');
$requestedPatientId = (int)($_POST['patient_id'] ?? $_GET['id'] ?? 0);
if ($requestedPatientId) {
    $location = nisTrackerRows($conn, 'SELECT t.department '.nisTrackerPatientFrom().' WHERE t.patient_id=?', [(string)$requestedPatientId]);
    if (!$location || $location[0]['department'] !== 'ER') { http_response_code(403); exit('ER assessment is not permitted for this encounter.'); }
}
requireRole(nurseRoles());
if (hasDepartment(['WARD_2F', 'WARD_3F', 'ICU', 'NICU']) && !hasRole('admin')) {
    header('Location: ../pages/patient_queue.php');
    exit();
}
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
$erRecordSchemaResult = ensureErRecordTriageSchema($conn);
if ($erRecordSchemaResult !== true) {
    $error = $erRecordSchemaResult;
}
$currentWaitSql = patientCurrentWaitMinutesSql('p');
$triageLevelOptions = triageLevelOptions();

$patient_id = $_GET['id'] ?? null;
$error = $error ?? '';
$success = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['begin_er_triage'])) {
    try {
        erTrackerAction($conn,'triage_start',$_POST,(int)$_SESSION['user_id']);
        header('Location: triage.php?module=triage&id='.(int)$_POST['patient_id']);exit;
    } catch (DomainException $e) { $error=$e->getMessage(); }
    catch(Throwable $e){ $error='Triage start could not be recorded. Please retry.'; }
}

// Save triage
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_triage'])) {
    $patient_id = intval($_POST['patient_id'] ?? 0);
    $triage_level = sanitizeText($_POST['triage_level'] ?? '', 20);
    $chief_complaint = sanitizeText($_POST['chief_complaint'] ?? '', 2000);
    $assessment = sanitizeText($_POST['assessment'] ?? '', 4000);
    $temperature = sanitizeText($_POST['temperature'] ?? '', 10);
    $blood_pressure = sanitizeText($_POST['blood_pressure'] ?? '', 20);
    $heart_rate = sanitizeText($_POST['heart_rate'] ?? '', 5);
    $respiratory_rate = sanitizeText($_POST['respiratory_rate'] ?? '', 5);
    $medicolegal = ($_POST['medicolegal'] ?? '0') === '1' ? 1 : 0;
    $ip = ($_POST['ip'] ?? '0') === '1' ? 1 : 0;
    $referred_by = sanitizeText($_POST['referred_by'] ?? '', 150);
    $coordinated = ($_POST['coordinated'] ?? '0') === '1' ? 1 : 0;
    $vital_pr = sanitizeText($_POST['vital_pr'] ?? '', 20);
    $vital_height = sanitizeText($_POST['vital_height'] ?? '', 20);
    $vital_weight = sanitizeText($_POST['vital_weight'] ?? '', 20);
    $vital_spo2 = sanitizeText($_POST['vital_spo2'] ?? '', 20);
    $vital_cbg = sanitizeText($_POST['vital_cbg'] ?? '', 20);

    $patient_check = $conn->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
    $patient_check->bind_param("i", $patient_id);
    $patient_check->execute();
    $patient_exists = $patient_check->get_result()->fetch_assoc();

    if (!$patient_exists) {
        $error = 'Patient no longer exists. Please select a patient from the current list.';
    } elseif (!isset($triageLevelOptions[$triage_level])) {
        $error = 'Please select a valid triage level.';
    } elseif ($triage_level === '' || $chief_complaint === '' || $temperature === '' || $blood_pressure === '' || $heart_rate === '' || $respiratory_rate === '') {
        $error = 'Triage level, chief complaint, and all vital signs are required.';
    } elseif (!is_numeric($temperature) || (float) $temperature < 25 || (float) $temperature > 45) {
        $error = 'Please enter a valid temperature.';
    } elseif (!preg_match('/^\d{2,3}\/\d{2,3}$/', $blood_pressure)) {
        $error = 'Please enter blood pressure in systolic/diastolic format.';
    } elseif (!ctype_digit($heart_rate) || (int) $heart_rate < 20 || (int) $heart_rate > 250) {
        $error = 'Please enter a valid heart rate.';
    } elseif (!ctype_digit($respiratory_rate) || (int) $respiratory_rate < 4 || (int) $respiratory_rate > 80) {
        $error = 'Please enter a valid respiratory rate.';
    } else {
        // Vital signs JSON
        $vital_signs = [
            'temperature' => $temperature,
            'blood_pressure' => $blood_pressure,
            'heart_rate' => $heart_rate,
            'respiratory_rate' => $respiratory_rate,
            'pr' => $vital_pr,
            'height' => $vital_height,
            'weight' => $vital_weight,
            'spo2' => $vital_spo2,
            'cbg' => $vital_cbg
        ];
        
        $vitals_json = json_encode($vital_signs);
        $user_id = $_SESSION['user_id'];
        
        // Check if triage already exists
        $check = $conn->prepare("SELECT triage_id FROM triage WHERE patient_id = ?");
        $check->bind_param("i", $patient_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        
        if ($existing) {
            $stmt = $conn->prepare("UPDATE triage SET triage_level = ?, chief_complaint = ?, assessment = ?, medicolegal = ?, ip = ?, referred_by = ?, coordinated = ?, vital_signs = ?, vital_pr = ?, vital_height = ?, vital_weight = ?, vital_spo2 = ?, vital_cbg = ? WHERE patient_id = ?");
            $stmt->bind_param("sssiisissssssi", $triage_level, $chief_complaint, $assessment, $medicolegal, $ip, $referred_by, $coordinated, $vitals_json, $vital_pr, $vital_height, $vital_weight, $vital_spo2, $vital_cbg, $patient_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO triage (patient_id, triage_level, chief_complaint, assessment, medicolegal, ip, referred_by, coordinated, vital_signs, vital_pr, vital_height, vital_weight, vital_spo2, vital_cbg, assigned_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssiisissssssi", $patient_id, $triage_level, $chief_complaint, $assessment, $medicolegal, $ip, $referred_by, $coordinated, $vitals_json, $vital_pr, $vital_height, $vital_weight, $vital_spo2, $vital_cbg, $user_id);
        }
        
        if ($stmt->execute()) {
            // Update patient status and record the time spent before this status change.
            $status = $triage_level == 'black' ? 'expired' : 'waiting_for_consultation';
            $statusUpdateResult = updatePatientStatus($conn, $patient_id, $status, $_SESSION['user_id']);
            if ($statusUpdateResult !== true) {
                $error = $statusUpdateResult;
            }
            
            if (!$error) {
                logSecurityEvent($conn, 'Triage Saved', 'Triage saved for patient ID ' . $patient_id);
                header('Location: triage.php?saved=1&print_id=' . urlencode((string) $patient_id));
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
ORDER BY p.queue_date ASC, p.queue_number ASC, p.arrival_time ASC";

$patients = $conn->query($patients_query)->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['saved'])) {
    $success = 'Triage information saved successfully';
}
$printPatientId = intval($_GET['print_id'] ?? 0);

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
            t.medicolegal,
            t.ip,
            t.referred_by,
            t.coordinated,
            t.vital_signs,
            t.vital_pr,
            t.vital_height,
            t.vital_weight,
            t.vital_spo2,
            t.vital_cbg,
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

$selectedVitals = [];
// Registration captures the complaint and flags, not a second set of vital signs.
if ($selected_patient && !$selected_triage && registrationReady($conn)) {
    $registered = registrationQuery($conn, 'SELECT chief_complaint,medicolegal,ip,referred_by,coordinated FROM nis_registrations WHERE patient_id=?', [(int)$selected_patient['patient_id']])->get_result()->fetch_assoc();
    if ($registered) $selected_triage = $registered;
}
if ($selected_triage && !empty($selected_triage['vital_signs'])) {
    $decodedVitals = json_decode($selected_triage['vital_signs'], true);
    if (is_array($decodedVitals)) {
        $selectedVitals = $decodedVitals;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Triage Management - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui triage-page">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-brand">
            <div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div>
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
                                                    <a href="triage.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm" style="background: #0B3C5D; color: white; padding: 4px 8px; font-size: 11px;">
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
                    <?php if($selected_patient['status']==='triage' && erTrackerReady($conn) && erQuery($conn,'SELECT tracking_id FROM er_public_tracking WHERE patient_id=?',[(int)$selected_patient['patient_id']])->get_result()->fetch_assoc()): ?>
                    <form method="POST"><?=csrfField()?><input type="hidden" name="patient_id" value="<?=(int)$selected_patient['patient_id']?>"><button class="btn btn-primary" name="begin_er_triage" value="1">Begin Triage</button><p>Record the actual start of assessment on the ER board.</p></form>
                    <?php endif; ?>
                    <section class="patients-section">
                        <h3>⚕️ Triage Assessment: <?php echo htmlspecialchars($selected_patient['patient_name']); ?></h3>
                        <form method="POST" id="triage-form">
                            <input type="hidden" name="save_triage" value="1">
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

                            <h4>ER Record Details</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="medicolegal">Medicolegal</label>
                                    <select id="medicolegal" name="medicolegal">
                                        <option value="0" <?php echo (int)($selected_triage['medicolegal'] ?? 0) === 0 ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?php echo (int)($selected_triage['medicolegal'] ?? 0) === 1 ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="ip">IP</label>
                                    <select id="ip" name="ip">
                                        <option value="0" <?php echo (int)($selected_triage['ip'] ?? 0) === 0 ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?php echo (int)($selected_triage['ip'] ?? 0) === 1 ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="referred-by">Referred By</label>
                                    <input type="text" id="referred-by" name="referred_by" maxlength="150" value="<?php echo htmlspecialchars($selected_triage['referred_by'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="coordinated">Coordinated</label>
                                    <select id="coordinated" name="coordinated">
                                        <option value="0" <?php echo (int)($selected_triage['coordinated'] ?? 0) === 0 ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?php echo (int)($selected_triage['coordinated'] ?? 0) === 1 ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>
                            </div>

                            <h4>Vital Signs</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="temp">Temperature (C) *</label>
                                    <input type="number" id="temp" name="temperature" step="0.1" required placeholder="e.g., 37.5" value="<?php echo htmlspecialchars($selectedVitals['temperature'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="bp">Blood Pressure *</label>
                                    <input type="text" id="bp" name="blood_pressure" required placeholder="e.g., 120/80" value="<?php echo htmlspecialchars($selectedVitals['blood_pressure'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="hr">Heart Rate (bpm) *</label>
                                    <input type="number" id="hr" name="heart_rate" required placeholder="e.g., 72" value="<?php echo htmlspecialchars($selectedVitals['heart_rate'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="rr">Respiratory Rate *</label>
                                    <input type="number" id="rr" name="respiratory_rate" required placeholder="e.g., 16" value="<?php echo htmlspecialchars($selectedVitals['respiratory_rate'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="pr">PR</label>
                                    <input type="text" id="pr" name="vital_pr" maxlength="20" value="<?php echo htmlspecialchars($selected_triage['vital_pr'] ?? ($selectedVitals['pr'] ?? '')); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="height">Height</label>
                                    <input type="text" id="height" name="vital_height" maxlength="20" value="<?php echo htmlspecialchars($selected_triage['vital_height'] ?? ($selectedVitals['height'] ?? '')); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="weight">Weight</label>
                                    <input type="text" id="weight" name="vital_weight" maxlength="20" value="<?php echo htmlspecialchars($selected_triage['vital_weight'] ?? ($selectedVitals['weight'] ?? '')); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="spo2">SPO2</label>
                                    <input type="text" id="spo2" name="vital_spo2" maxlength="20" value="<?php echo htmlspecialchars($selected_triage['vital_spo2'] ?? ($selectedVitals['spo2'] ?? '')); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="cbg">CBG</label>
                                    <input type="text" id="cbg" name="vital_cbg" maxlength="20" value="<?php echo htmlspecialchars($selected_triage['vital_cbg'] ?? ($selectedVitals['cbg'] ?? '')); ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">💾 Save Triage</button>
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
    <div id="triage-confirm-modal" class="discharge-modal" aria-hidden="true">
        <div class="discharge-modal-card" role="dialog" aria-modal="true">
            <h3>Save Triage</h3>
            <p class="compact-meta">Are you sure you want to save this triage assessment?</p>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px;">
                <button type="button" class="btn btn-secondary" id="cancel-triage-save">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-triage-save">Save</button>
            </div>
        </div>
    </div>
    <?php if ($printPatientId): ?>
        <div id="print-er-record-modal" class="discharge-modal open" aria-hidden="false">
            <div class="discharge-modal-card" role="dialog" aria-modal="true">
                <h3>Print ER Record</h3>
                <p class="compact-meta">Triage assessment saved. Do you want to view and print the ER Record form?</p>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px;">
                    <a href="triage.php" class="btn btn-secondary">No</a>
                    <a href="er_record.php?id=<?php echo (int) $printPatientId; ?>" class="btn btn-primary">View ER Record</a>
                    <a href="er_record.php?id=<?php echo (int) $printPatientId; ?>&amp;download=pdf" class="btn btn-secondary">Download PDF</a>
                    <a href="er_record.php?id=<?php echo (int) $printPatientId; ?>&amp;print=1" class="btn btn-secondary">Print ER Record</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <script>
        const triageForm = document.getElementById('triage-form');
        const triageConfirmModal = document.getElementById('triage-confirm-modal');
        const cancelTriageSave = document.getElementById('cancel-triage-save');
        const confirmTriageSave = document.getElementById('confirm-triage-save');
        let triageConfirmed = false;

        if (triageForm && triageConfirmModal) {
            triageForm.addEventListener('submit', (event) => {
                if (triageConfirmed) {
                    return;
                }

                event.preventDefault();
                if (!triageForm.reportValidity()) {
                    return;
                }

                triageConfirmModal.classList.add('open');
                triageConfirmModal.setAttribute('aria-hidden', 'false');
            });

            function closeTriageConfirmModal() {
                triageConfirmModal.classList.remove('open');
                triageConfirmModal.setAttribute('aria-hidden', 'true');
            }

            cancelTriageSave.addEventListener('click', closeTriageConfirmModal);
            triageConfirmModal.addEventListener('click', (event) => {
                if (event.target === triageConfirmModal) {
                    closeTriageConfirmModal();
                }
            });
            confirmTriageSave.addEventListener('click', () => {
                triageConfirmed = true;
                closeTriageConfirmModal();
                triageForm.submit();
            });
        }
    </script>
    <style>
        body.triage-page {
            min-height: 100vh;
            overflow-y: auto !important;
        }

        .triage-page .dashboard-container {
            min-height: auto;
            align-items: start;
        }

        .triage-page .main-content {
            min-height: calc(100vh - 70px);
            height: auto;
            max-height: none;
            overflow: visible !important;
            padding-bottom: 96px;
        }

        .triage-page .patients-section {
            overflow: visible;
        }

        @media (max-width: 900px) {
            .triage-page .main-content > div {
                grid-template-columns: 1fr !important;
            }
        }

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
            width: min(460px, 100%);
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
            padding: 22px;
        }
    </style>
</body>
</html>

