<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/opd_helpers.php';

requireLogin();
requirePermission('opd', 'create', 'OPD');
ensureOpdSchema($conn);

$error = '';
$success = '';
consumeFlashMessages($error, $success);

$search = trim($_GET['search'] ?? '');
$existingPatients = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("
        SELECT *
        FROM patients
        WHERE permanent_patient_id LIKE ? OR patient_code LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?
        ORDER BY patient_id DESC
        LIMIT 20
    ");
    $stmt->bind_param("sssss", $like, $like, $like, $like, $like);
    $stmt->execute();
    $existingPatients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_opd'])) {
    $sourcePatientId = intval($_POST['source_patient_id'] ?? 0);
    $isExisting = $sourcePatientId > 0;
    $firstName = sanitizeText($_POST['first_name'] ?? '', 50);
    $lastName = sanitizeText($_POST['last_name'] ?? '', 50);
    $middleName = sanitizeText($_POST['middle_name'] ?? '', 50);
    $dateOfBirth = normalizePatientDateInput($_POST['date_of_birth'] ?? '');
    $gender = sanitizeText($_POST['gender'] ?? '', 20);
    $phone = sanitizeText($_POST['phone'] ?? '', 20);
    $house = sanitizeText($_POST['house_no_street'] ?? '', 100);
    $consultDateTime = $_POST['consultation_date_time'] ?? date('Y-m-d\TH:i');
    $clinicType = sanitizeText($_POST['clinic_type'] ?? '', 80);
    $chiefComplaint = sanitizeText($_POST['chief_complaint'] ?? '', 2000);
    $diagnosis = sanitizeText($_POST['diagnosis'] ?? '', 200);
    $physician = sanitizeText($_POST['attending_physician'] ?? '', 150);
    $disposition = sanitizeText($_POST['disposition'] ?? '', 60);
    $referredTo = sanitizeText($_POST['referred_to_department'] ?? '', 60);
    $followUpDate = $_POST['follow_up_date'] ?? null;
    $remarks = sanitizeText($_POST['remarks'] ?? '', 2000);

    if (!isset(opdDispositionOptions()[$disposition])) {
        $error = 'Please select a valid OPD disposition.';
    } elseif (!isset(opdClinicTypeOptions()[$clinicType])) {
        $error = 'Please select a valid clinic type.';
    } elseif ($chiefComplaint === '') {
        $error = 'Chief complaint is required.';
    } elseif (!$isExisting && ($firstName === '' || $lastName === '' || !isValidPatientDate($dateOfBirth) || !in_array($gender, ['Male', 'Female', 'Other'], true))) {
        $error = 'For a new OPD patient, first name, last name, birthday, and sex are required.';
    } elseif ($followUpDate && !validDateString($followUpDate)) {
        $error = 'Please enter a valid follow-up date.';
    } else {
        $conn->begin_transaction();
        $numberLockHeld = false;
        try {
            // Share the legacy MAX-based numbering lock with ER registration.
            $lock = $conn->query("SELECT GET_LOCK(CONCAT(DATABASE(),':registration'),10) acquired")->fetch_assoc();
            if ((int)$lock['acquired'] !== 1) throw new RuntimeException('Registration numbering is busy. Please retry.');
            $numberLockHeld = true;
            $patientId = 0;
            $patientCode = generatePatientCode($conn);
            $queueDate = date('Y-m-d');
            $queueNumber = generatePatientQueueNumber($conn, $queueDate);
            $arrivalTime = str_replace('T', ' ', $consultDateTime) . ':00';

            if ($isExisting) {
                $sourceStmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ? LIMIT 1");
                $sourceStmt->bind_param("i", $sourcePatientId);
                $sourceStmt->execute();
                $source = $sourceStmt->get_result()->fetch_assoc();
                if (!$source) {
                    throw new RuntimeException('Existing patient was not found.');
                }
                $permanentId = $source['permanent_patient_id'] ?: generatePermanentPatientId($conn);
                $age = calculateAge($source['date_of_birth']);
                $status = 'discharge';
                $stmt = $conn->prepare("
                    INSERT INTO patients (permanent_patient_id, patient_code, queue_date, queue_number, first_name, last_name, middle_name, date_of_birth, age, gender, civil_status, religion, phone, house_no_street, barangay_name, municipality_name, province_name, barangay_id, municipality_id, province_id, admission_date, triage_date, triage_time, arrival_time, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE(?), TIME(?), ?, ?)
                ");
                $stmt->bind_param("sssissssissssssssssssssss", $permanentId, $patientCode, $queueDate, $queueNumber, $source['first_name'], $source['last_name'], $source['middle_name'], $source['date_of_birth'], $age, $source['gender'], $source['civil_status'], $source['religion'], $source['phone'], $source['house_no_street'], $source['barangay_name'], $source['municipality_name'], $source['province_name'], $source['barangay_id'], $source['municipality_id'], $source['province_id'], $arrivalTime, $arrivalTime, $arrivalTime, $arrivalTime, $status);
                $stmt->execute();
                $patientId = $conn->insert_id;
            } else {
                $permanentId = generatePermanentPatientId($conn);
                $age = calculateAge($dateOfBirth);
                $civil = null;
                $religion = '';
                $status = 'discharge';
                $stmt = $conn->prepare("
                    INSERT INTO patients (permanent_patient_id, patient_code, queue_date, queue_number, first_name, last_name, middle_name, date_of_birth, age, gender, civil_status, religion, phone, house_no_street, admission_date, triage_date, triage_time, arrival_time, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE(?), TIME(?), ?, ?)
                ");
                $stmt->bind_param("sssissssissssssssss", $permanentId, $patientCode, $queueDate, $queueNumber, $firstName, $lastName, $middleName, $dateOfBirth, $age, $gender, $civil, $religion, $phone, $house, $arrivalTime, $arrivalTime, $arrivalTime, $arrivalTime, $status);
                $stmt->execute();
                $patientId = $conn->insert_id;
            }

            $opdNumber = generateOpdNumber($conn, date('Y-m-d', strtotime($arrivalTime)));
            $opdStatus = $disposition === 'CANCELLED' ? 'CANCELLED' : 'ACTIVE';
            $createdBy = $_SESSION['user_id'] ?? null;
            $followUpValue = $followUpDate ?: null;
            $destination = opdDestinationForDisposition($disposition, $referredTo);
            $opdStmt = $conn->prepare("
                INSERT INTO opd_records (patient_id, encounter_id, opd_number, consultation_date_time, clinic_type, chief_complaint, diagnosis, attending_physician, disposition, referred_to_department, follow_up_date, remarks, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $opdStmt->bind_param("issssssssssssi", $patientId, $patientCode, $opdNumber, $arrivalTime, $clinicType, $chiefComplaint, $diagnosis, $physician, $disposition, $destination, $followUpValue, $remarks, $opdStatus, $createdBy);
            $opdStmt->execute();
            createOpdMovement($conn, $patientId, $patientCode, $disposition, $destination, $remarks);
            opdAudit($conn, 'OPD Consultation Created', $patientId, $patientCode, null, ['opd_number' => $opdNumber, 'disposition' => $disposition]);
            $conn->commit();
            $conn->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(),':registration'))");
            $numberLockHeld = false;
            setFlashMessage('success', 'OPD consultation saved and movement tracker updated.');
            redirectAfterPost('opd_patients.php');
        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Unable to save OPD consultation: ' . $e->getMessage();
        } finally {
            if ($numberLockHeld) $conn->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(),':registration'))");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPD Consultation - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
    <nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div><div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?></span></div></nav>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <main class="main-content">
            <section class="page-header"><h2>OPD Consultation</h2><p class="compact-meta">Start a new OPD visit using an existing patient identity or a new patient identity.</p></section>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>

            <section class="patients-section">
                <h3>Search Existing Patient</h3>
                <form method="GET" class="patient-search-form">
                    <div class="form-row"><div class="form-group"><label>Hospital No., name, or phone</label><input type="text" name="search" value="<?php echo h($search); ?>"></div><div class="form-group patient-search-actions"><button class="btn btn-primary">Search Existing Patient</button></div></div>
                </form>
                <?php if ($search !== ''): ?>
                    <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Hospital No.</th><th>Name</th><th>Birthday</th><th>Phone</th><th>Use</th></tr></thead><tbody>
                    <?php foreach ($existingPatients as $patient): ?><tr><td><?php echo h($patient['permanent_patient_id']); ?></td><td><?php echo h(patientFullName($patient)); ?></td><td><?php echo h(formatPatientDate($patient['date_of_birth'])); ?></td><td><?php echo h($patient['phone']); ?></td><td><button type="button" class="btn btn-sm btn-info" onclick="document.getElementById('source-patient-id').value='<?php echo (int) $patient['patient_id']; ?>';document.getElementById('existing-patient-label').textContent='<?php echo h($patient['permanent_patient_id'] . ' - ' . patientFullName($patient)); ?>';">Use Patient</button></td></tr><?php endforeach; ?>
                    <?php if (empty($existingPatients)): ?><tr><td colspan="5" class="text-center">No matching patient.</td></tr><?php endif; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>

            <section class="form-section">
                <h3>New OPD Consultation</h3>
                <form method="POST" class="patient-form">
                    <input type="hidden" name="save_opd" value="1">
                    <input type="hidden" name="source_patient_id" id="source-patient-id" value="">
                    <p class="compact-meta">Selected existing patient: <strong id="existing-patient-label">None</strong></p>
                    <h4>New Patient Details</h4>
                    <div class="form-row form-row-three"><div class="form-group"><label>Last Name</label><input name="last_name"></div><div class="form-group"><label>First Name</label><input name="first_name"></div><div class="form-group"><label>Middle Name</label><input name="middle_name"></div></div>
                    <div class="form-row"><div class="form-group"><label>Birthday</label><input type="date" name="date_of_birth" max="<?php echo h(date('Y-m-d')); ?>"></div><div class="form-group"><label>Sex</label><select name="gender"><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></div></div>
                    <div class="form-row"><div class="form-group"><label>Contact Number</label><input name="phone"></div><div class="form-group"><label>Address</label><input name="house_no_street"></div></div>
                    <h4>Consultation Details</h4>
                    <div class="form-row"><div class="form-group"><label>Date / Time</label><input type="datetime-local" name="consultation_date_time" value="<?php echo h(date('Y-m-d\TH:i')); ?>"></div><div class="form-group"><label>Clinic Type *</label><select name="clinic_type" required><option value="">Select</option><?php foreach (opdClinicTypeOptions() as $value => $label): ?><option value="<?php echo h($value); ?>"><?php echo h($label); ?></option><?php endforeach; ?></select></div></div>
                    <div class="form-row"><div class="form-group"><label>Chief Complaint *</label><textarea name="chief_complaint" required></textarea></div><div class="form-group"><label>Diagnosis</label><input name="diagnosis" list="diagnosis-options"></div></div>
                    <div class="form-row"><div class="form-group"><label>Attending Physician</label><input name="attending_physician"></div><div class="form-group"><label>Disposition *</label><select name="disposition" required><?php foreach (opdDispositionOptions() as $value => $label): ?><option value="<?php echo h($value); ?>"><?php echo h($label); ?></option><?php endforeach; ?></select></div></div>
                    <div class="form-row"><div class="form-group"><label>Referred To Department</label><select name="referred_to_department"><option value="">Auto</option><option>ER</option><option>WARD</option><option>OR</option><option>MICU</option><option>OTHER FACILITY</option></select></div><div class="form-group"><label>Follow-Up Date</label><input type="date" name="follow_up_date"></div></div>
                    <div class="form-row"><div class="form-group"><label>Remarks</label><textarea name="remarks"></textarea></div></div>
                    <button class="btn btn-primary">New OPD Consultation</button>
                    <a class="btn btn-secondary" href="opd_patients.php">Cancel</a>
                </form>
            </section>
        </main>
    </div>
    <datalist id="diagnosis-options"><?php foreach (diagnosisOptions() as $diagnosis): ?><option value="<?php echo h($diagnosis); ?>"></option><?php endforeach; ?></datalist>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
