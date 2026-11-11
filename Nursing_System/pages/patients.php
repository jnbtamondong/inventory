<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/locations.php';

requireLogin();
// All legacy registration entry points use the same identity-aware workflow.
if (in_array($_GET['action'] ?? '', ['choose','add','old','old_edit'], true)) {
    requirePermission('patients','create','ER');
    $existing = ($_GET['action'] ?? '') === 'old_edit' ? max(0,(int)($_GET['id'] ?? 0)) : 0;
    header('Location: patient_registration.php'.($existing?'?existing='.$existing:''), true, 303);
    exit;
}
requireRole(array_merge(nurseRoles(), doctorRoles(), admissionRoles()));
if (hasRole(nurseRoles()) && hasDepartment(['WARD_2F', 'WARD_3F', 'ICU', 'NICU']) && !hasRole('admin')) {
    header('Location: ../pages/patient_queue.php');
    exit();
}
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
$erRecordSchemaResult = ensureErRecordTriageSchema($conn);
if ($erRecordSchemaResult !== true) {
    $error = $erRecordSchemaResult;
}

function savePatientTriageFromPost(mysqli $conn, int $patientId, array $post, int $userId) {
    $triageLevelOptions = triageLevelOptions();
    $triage_level = sanitizeText($post['triage_level'] ?? '', 20);
    $chief_complaint = sanitizeText($post['chief_complaint'] ?? '', 2000);
    $assessment = sanitizeText($post['assessment'] ?? '', 4000);
    $temperature = sanitizeText($post['temperature'] ?? '', 10);
    $blood_pressure = sanitizeText($post['blood_pressure'] ?? '', 20);
    $heart_rate = sanitizeText($post['heart_rate'] ?? '', 5);
    $respiratory_rate = sanitizeText($post['respiratory_rate'] ?? '', 5);
    $medicolegal = ($post['medicolegal'] ?? '0') === '1' ? 1 : 0;
    $ip = ($post['ip'] ?? '0') === '1' ? 1 : 0;
    $referred_by = sanitizeText($post['referred_by'] ?? '', 150);
    $coordinated = ($post['coordinated'] ?? '0') === '1' ? 1 : 0;
    $vital_pr = sanitizeText($post['vital_pr'] ?? '', 20);
    $vital_height = sanitizeText($post['vital_height'] ?? '', 20);
    $vital_weight = sanitizeText($post['vital_weight'] ?? '', 20);
    $vital_spo2 = sanitizeText($post['vital_spo2'] ?? '', 20);
    $vital_cbg = sanitizeText($post['vital_cbg'] ?? '', 20);

    if (!isset($triageLevelOptions[$triage_level])) {
        return 'Please select a valid triage level.';
    }
    if ($chief_complaint === '' || $temperature === '' || $blood_pressure === '' || $heart_rate === '' || $respiratory_rate === '') {
        return 'Triage level, chief complaint, and all vital signs are required.';
    }
    if (!is_numeric($temperature) || (float) $temperature < 25 || (float) $temperature > 45) {
        return 'Please enter a valid temperature.';
    }
    if (!preg_match('/^\d{2,3}\/\d{2,3}$/', $blood_pressure)) {
        return 'Please enter blood pressure in systolic/diastolic format.';
    }
    if (!ctype_digit($heart_rate) || (int) $heart_rate < 20 || (int) $heart_rate > 250) {
        return 'Please enter a valid heart rate.';
    }
    if (!ctype_digit($respiratory_rate) || (int) $respiratory_rate < 4 || (int) $respiratory_rate > 80) {
        return 'Please enter a valid respiratory rate.';
    }

    $vital_signs = [
        'temperature' => $temperature,
        'blood_pressure' => $blood_pressure,
        'heart_rate' => $heart_rate,
        'respiratory_rate' => $respiratory_rate,
        'pr' => $vital_pr,
        'height' => $vital_height,
        'weight' => $vital_weight,
        'spo2' => $vital_spo2,
        'cbg' => $vital_cbg,
    ];
    $vitals_json = json_encode($vital_signs);

    $stmt = $conn->prepare("
        INSERT INTO triage (patient_id, triage_level, chief_complaint, assessment, medicolegal, ip, referred_by, coordinated, vital_signs, vital_pr, vital_height, vital_weight, vital_spo2, vital_cbg, assigned_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            triage_level = VALUES(triage_level),
            chief_complaint = VALUES(chief_complaint),
            assessment = VALUES(assessment),
            medicolegal = VALUES(medicolegal),
            ip = VALUES(ip),
            referred_by = VALUES(referred_by),
            coordinated = VALUES(coordinated),
            vital_signs = VALUES(vital_signs),
            vital_pr = VALUES(vital_pr),
            vital_height = VALUES(vital_height),
            vital_weight = VALUES(vital_weight),
            vital_spo2 = VALUES(vital_spo2),
            vital_cbg = VALUES(vital_cbg)
    ");
    $stmt->bind_param("isssiisissssssi", $patientId, $triage_level, $chief_complaint, $assessment, $medicolegal, $ip, $referred_by, $coordinated, $vitals_json, $vital_pr, $vital_height, $vital_weight, $vital_spo2, $vital_cbg, $userId);
    if (!$stmt->execute()) {
        return 'Failed to save triage information.';
    }

    $status = $triage_level === 'black' ? 'expired' : 'waiting_for_consultation';
    return updatePatientStatus($conn, $patientId, $status, $userId);
}

$action = $_GET['action'] ?? 'list';
$patient_id = intval($_GET['id'] ?? 0);
$error = $error ?? '';
$success = '';
consumeFlashMessages($error, $success);
$statusOptions = hasRole('admin') ? patientWorkflowStatusOptions() : nurseWorkflowStatusOptions();
$dispositionOptions = patientDispositionOptions();
$civilStatusOptions = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'];
$triageLevelOptions = triageLevelOptions();
$canAddPatient = hasRole('admin') || hasRole(admittingPatientCreationRoles());
$canSetDisposition = hasRole('admin') || hasRole(dispositionDecisionRoles());
$canUpdatePatientStatus = hasRole('admin') || hasRole(nurseRoles()) || hasRole(doctorRoles());
$currentDateTime = new DateTime('now');
$currentDate = $currentDateTime->format('Y-m-d');
$currentTime = $currentDateTime->format('H:i');
$oldPatientSearch = trim($_GET['search'] ?? '');
$activeStatuses = "'" . implode("','", activePatientStatuses()) . "'";
$duplicateOldPatients = [];
$pendingNewPatientPost = [];

if (in_array($action, ['choose', 'add', 'old', 'old_edit'], true) && !$canAddPatient) {
    $error = 'You do not have permission to add patients.';
    $action = 'list';
}

if ($action === 'add' && isset($_GET['duplicate'])) {
    $duplicateOldPatients = $_SESSION['duplicate_patient_matches'] ?? [];
    $pendingNewPatientPost = $_SESSION['duplicate_patient_pending_post'] ?? [];
    if (!empty($duplicateOldPatients)) {
        $error = $error ?: 'A patient with the same first name, last name, and birthday already exists. Use the old patient encounter flow instead of creating a new patient.';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $patient_id = intval($_POST['patient_id']);
    $disposition = sanitizeText($_POST['disposition'] ?? '', 50);
    $status = $disposition !== '' ? $disposition : ($_POST['status'] ?? DEFAULT_PATIENT_STATUS);
    $finalDiagnosis = sanitizeText($_POST['final_diagnosis'] ?? '', 150);
    $specificDiagnosis = sanitizeText($_POST['specific_diagnosis'] ?? '', 2000);
    $triageCheck = $conn->prepare("SELECT triage_id FROM triage WHERE patient_id = ? LIMIT 1");
    $triageCheck->bind_param("i", $patient_id);
    $triageCheck->execute();
    $hasTriageAssessment = (bool) $triageCheck->get_result()->fetch_assoc();

    if (!$canUpdatePatientStatus && $disposition === '') {
        $result = 'You do not have permission to update patient status.';
    } elseif ($disposition === '' && !hasRole('admin') && !isset(nurseWorkflowStatusOptions()[$status])) {
        $result = 'Nurses can update patient status only up to Waiting for Consultation.';
    } elseif (!$hasTriageAssessment && ($status !== DEFAULT_PATIENT_STATUS || $disposition !== '')) {
        $result = 'Please save the triage assessment before updating the patient status.';
    } elseif ($disposition !== '') {
        $patientStatusCheck = $conn->prepare("SELECT status FROM patients WHERE patient_id = ?");
        $patientStatusCheck->bind_param("i", $patient_id);
        $patientStatusCheck->execute();
        $currentPatient = $patientStatusCheck->get_result()->fetch_assoc();

        if (!$canSetDisposition) {
            $result = 'Only doctors can set the final disposition.';
        } elseif (!$currentPatient || $currentPatient['status'] !== 'ready_for_disposition') {
            $result = 'Disposition can only be selected when patient status is Ready for Disposition.';
        } else {
            $result = updatePatientStatus($conn, $patient_id, $status, $_SESSION['user_id'], $finalDiagnosis, $specificDiagnosis);
        }
    } else {
        $result = updatePatientStatus($conn, $patient_id, $status, $_SESSION['user_id'], $finalDiagnosis, $specificDiagnosis);
    }

    if ($result === true) {
        setFlashMessage('success', 'Patient status updated successfully');
    } else {
        setFlashMessage('error', $result);
    }
    redirectAfterPost('patients.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_patient'])) {
    $last_name = sanitizeText($_POST['last_name'] ?? '', 50);
    $first_name = sanitizeText($_POST['first_name'] ?? '', 50);
    $middle_name = sanitizeText($_POST['middle_name'] ?? '', 50);
    $raw_date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $date_of_birth = normalizePatientDateInput($raw_date_of_birth);
    $age = calculateAge($date_of_birth);
    $house_no_street = sanitizeText($_POST['house_no_street'] ?? '', 100);
    $barangay_id = !empty($_POST['barangay_id']) ? trim($_POST['barangay_id']) : null;
    $municipality_id = !empty($_POST['municipality_id']) ? trim($_POST['municipality_id']) : null;
    $province_id = !empty($_POST['province_id']) ? trim($_POST['province_id']) : null;
    $barangay_name = sanitizeText($_POST['barangay_name'] ?? '', 100);
    $municipality_name = sanitizeText($_POST['municipality_name'] ?? '', 100);
    $province_name = sanitizeText($_POST['province_name'] ?? '', 100);
    $gender = sanitizeText($_POST['gender'] ?? '', 20);
    $civil_status = sanitizeText($_POST['civil_status'] ?? '', 20);
    $ward_department = sanitizeText($_POST['ward_department'] ?? 'MEDICAL', 30);
    $religion = sanitizeText($_POST['religion'] ?? '', 50);
    $phone = sanitizeText($_POST['phone'] ?? '', 20);
    $triage_date = $_POST['triage_date'] ?? $currentDate;
    $triage_time = $_POST['triage_time'] ?? $currentTime;
    $arrival_time = $triage_date . ' ' . $triage_time . ':00';
    $isNewPatient = $action == 'add';
    $isOldPatientReturn = $action == 'old_edit';
    if (!$isNewPatient && !$isOldPatientReturn) {
        requirePermission('patients','edit','ER');
        require_once '../includes/nis-tracker.php';
        $location = nisTrackerRows($conn,'SELECT t.department '.nisTrackerPatientFrom().' WHERE t.patient_id=?',[(string)$patient_id]);
        if (!$location || $location[0]['department'] !== 'ER') { http_response_code(403); exit('Editing this encounter is not permitted.'); }
    }
    $permanent_patient_id = $isNewPatient ? generatePermanentPatientId($conn) : sanitizeText($_POST['permanent_patient_id'] ?? '', 30);
    $patient_code = $isNewPatient ? generatePatientCode($conn) : sanitizeText($_POST['patient_code'] ?? '', 30);
    if (!$isNewPatient && !$isOldPatientReturn) {
        $identityStmt=$conn->prepare('SELECT permanent_patient_id,patient_code FROM patients WHERE patient_id=?');
        $identityStmt->bind_param('i',$patient_id);$identityStmt->execute();$immutableIdentity=$identityStmt->get_result()->fetch_assoc();
        $permanent_patient_id=$immutableIdentity['permanent_patient_id'];$patient_code=$immutableIdentity['patient_code'];
    }
    $queue_date = $currentDate;
    $queue_number = ($isNewPatient || $isOldPatientReturn) ? generatePatientQueueNumber($conn, $queue_date) : null;
    $requiresTriageOnSave = $isNewPatient || $isOldPatientReturn;
    $postedTriageLevel = sanitizeText($_POST['triage_level'] ?? '', 20);
    $postedChiefComplaint = sanitizeText($_POST['chief_complaint'] ?? '', 2000);
    $postedTemperature = sanitizeText($_POST['temperature'] ?? '', 10);
    $postedBloodPressure = sanitizeText($_POST['blood_pressure'] ?? '', 20);
    $postedHeartRate = sanitizeText($_POST['heart_rate'] ?? '', 5);
    $postedRespiratoryRate = sanitizeText($_POST['respiratory_rate'] ?? '', 5);

    $missingRequiredPatientInfo = empty($permanent_patient_id)
        || empty($patient_code)
        || empty($last_name)
        || empty($first_name)
        || empty($middle_name)
        || empty($date_of_birth)
        || empty($gender)
        || empty($ward_department)
        || empty($religion)
        || empty($phone)
        || empty($province_id)
        || empty($municipality_id)
        || empty($barangay_id);
    $missingArrivalInfo = empty($triage_date) || empty($triage_time);

    if (!$canAddPatient && in_array($action, ['add', 'old_edit'], true)) {
        $error = 'You do not have permission to add patients.';
    } elseif ($isOldPatientReturn && (!$patient_id || $missingArrivalInfo)) {
        $error = 'Date of arrival and time of arrival are required';
    } elseif (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $error = 'Please select a valid sex.';
    } elseif ($civil_status !== '' && !in_array($civil_status, $civilStatusOptions, true)) {
        $error = 'Please select a valid civil status.';
    } elseif (!isset(wardDepartmentOptions()[$ward_department])) {
        $error = 'Please select a valid department.';
    } elseif (!$isOldPatientReturn && ($raw_date_of_birth === '' || !isValidPatientDate($date_of_birth) || strtotime($date_of_birth) > strtotime($currentDate))) {
        $error = 'Please enter a valid birthday.';
    } elseif ($age !== null && ($age < 0 || $age > 130)) {
        $error = 'Please enter a valid age/date of birth.';
    } elseif (!validDateString($triage_date) || !preg_match('/^\d{2}:\d{2}$/', $triage_time)) {
        $error = 'Please enter a valid date and time of arrival.';
    } elseif (!preg_match('/^[0-9+()\\-\\s]{7,20}$/', $phone)) {
        $error = 'Please enter a valid contact number.';
    } elseif (!$isOldPatientReturn && ($missingRequiredPatientInfo || $missingArrivalInfo)) {
            $error = 'Patient ID, last name, first name, middle name, birthday, sex, department, province, municipality, barangay, religion, contact number, date of arrival, and time of arrival are required';
    } elseif ($requiresTriageOnSave && (!isset($triageLevelOptions[$postedTriageLevel]) || $postedChiefComplaint === '' || $postedTemperature === '' || $postedBloodPressure === '' || $postedHeartRate === '' || $postedRespiratoryRate === '')) {
        $error = 'Triage level, chief complaint, and all vital signs are required.';
    } elseif ($requiresTriageOnSave && (!is_numeric($postedTemperature) || (float) $postedTemperature < 25 || (float) $postedTemperature > 45)) {
        $error = 'Please enter a valid temperature.';
    } elseif ($requiresTriageOnSave && !preg_match('/^\d{2,3}\/\d{2,3}$/', $postedBloodPressure)) {
        $error = 'Please enter blood pressure in systolic/diastolic format.';
    } elseif ($requiresTriageOnSave && (!ctype_digit($postedHeartRate) || (int) $postedHeartRate < 20 || (int) $postedHeartRate > 250)) {
        $error = 'Please enter a valid heart rate.';
    } elseif ($requiresTriageOnSave && (!ctype_digit($postedRespiratoryRate) || (int) $postedRespiratoryRate < 4 || (int) $postedRespiratoryRate > 80)) {
        $error = 'Please enter a valid respiratory rate.';
    } else {
        $civil_status = $civil_status === '' ? null : $civil_status;

        if ($isNewPatient) {
            $duplicateCheck = $conn->prepare("
                SELECT patient_id, permanent_patient_id, patient_code, first_name, last_name, middle_name, date_of_birth, age, gender, phone, status, admission_date
                FROM patients
                WHERE LOWER(TRIM(first_name)) = LOWER(TRIM(?))
                    AND LOWER(TRIM(last_name)) = LOWER(TRIM(?))
                    AND date_of_birth = ?
                ORDER BY admission_date DESC, patient_id DESC
                LIMIT 5
            ");
            $duplicateCheck->bind_param("sss", $first_name, $last_name, $date_of_birth);
            $duplicateCheck->execute();
            $duplicateOldPatients = $duplicateCheck->get_result()->fetch_all(MYSQLI_ASSOC);
            $pendingNewPatientPost = $_POST;

            if (!empty($duplicateOldPatients)) {
                $_SESSION['duplicate_patient_matches'] = $duplicateOldPatients;
                $_SESSION['duplicate_patient_pending_post'] = $pendingNewPatientPost;
                setFlashMessage('error', 'A patient with the same first name, last name, and birthday already exists. Use the old patient menu to create a new encounter.');
                redirectAfterPost('patients.php?action=add&duplicate=1');
            }
        }

        if ($isNewPatient) {
            if (!isValidPatientDate($date_of_birth)) {
                $error = 'Please enter a valid birthday.';
                $stmt = null;
            } else {
            $stmt = $conn->prepare("INSERT INTO patients (permanent_patient_id, patient_code, queue_date, queue_number, first_name, last_name, middle_name, date_of_birth, age, gender, civil_status, ward_department, religion, phone, house_no_street, barangay_name, municipality_name, province_name, barangay_id, municipality_id, province_id, admission_date, triage_date, triage_time, arrival_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, 'triage')");
            $stmt->bind_param("sssissssisssssssssssssss", $permanent_patient_id, $patient_code, $queue_date, $queue_number, $first_name, $last_name, $middle_name, $date_of_birth, $age, $gender, $civil_status, $ward_department, $religion, $phone, $house_no_street, $barangay_name, $municipality_name, $province_name, $barangay_id, $municipality_id, $province_id, $triage_date, $triage_time, $arrival_time);
            }
        } elseif ($isOldPatientReturn) {
            $sourcePatientStmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ? LIMIT 1");
            $sourcePatientStmt->bind_param("i", $patient_id);
            $sourcePatientStmt->execute();
            $sourcePatient = $sourcePatientStmt->get_result()->fetch_assoc();

            if (!$sourcePatient) {
                $error = 'Old patient record not found.';
                $stmt = null;
            } elseif (!isValidPatientDate($sourcePatient['date_of_birth'] ?? null)) {
                $error = 'This old patient record has no valid birthday. Please edit the old patient record birthday before creating a new encounter.';
                $stmt = null;
            } else {
                $newEncounterCode = generatePatientCode($conn);
                $existingPermanentPatientId = $sourcePatient['permanent_patient_id'] ?: generatePermanentPatientId($conn);
                if (empty($sourcePatient['permanent_patient_id'])) {
                    $sourceIdUpdate = $conn->prepare("UPDATE patients SET permanent_patient_id = ? WHERE patient_id = ?");
                    $sourceIdUpdate->bind_param("si", $existingPermanentPatientId, $patient_id);
                    $sourceIdUpdate->execute();
                }
                $newEncounterAge = calculateAge($sourcePatient['date_of_birth']);
                $stmt = $conn->prepare("INSERT INTO patients (permanent_patient_id, patient_code, queue_date, queue_number, first_name, last_name, middle_name, date_of_birth, age, gender, civil_status, ward_department, religion, phone, house_no_street, barangay_name, municipality_name, province_name, barangay_id, municipality_id, province_id, admission_date, triage_date, triage_time, arrival_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'triage')");
                $stmt->bind_param("sssissssissssssssssssssss", $existingPermanentPatientId, $newEncounterCode, $queue_date, $queue_number, $sourcePatient['first_name'], $sourcePatient['last_name'], $sourcePatient['middle_name'], $sourcePatient['date_of_birth'], $newEncounterAge, $sourcePatient['gender'], $sourcePatient['civil_status'], $sourcePatient['ward_department'], $sourcePatient['religion'], $sourcePatient['phone'], $sourcePatient['house_no_street'], $sourcePatient['barangay_name'], $sourcePatient['municipality_name'], $sourcePatient['province_name'], $sourcePatient['barangay_id'], $sourcePatient['municipality_id'], $sourcePatient['province_id'], $arrival_time, $triage_date, $triage_time, $arrival_time);
            }
        } else {
            $stmt = $conn->prepare("UPDATE patients SET permanent_patient_id = ?, patient_code = ?, first_name = ?, last_name = ?, middle_name = ?, date_of_birth = ?, age = ?, gender = ?, civil_status = ?, ward_department = ?, religion = ?, phone = ?, house_no_street = ?, barangay_name = ?, municipality_name = ?, province_name = ?, barangay_id = ?, municipality_id = ?, province_id = ?, triage_date = ?, triage_time = ?, arrival_time = ? WHERE patient_id = ?");
            $stmt->bind_param("ssssssisssssssssssssssi", $permanent_patient_id, $patient_code, $first_name, $last_name, $middle_name, $date_of_birth, $age, $gender, $civil_status, $ward_department, $religion, $phone, $house_no_street, $barangay_name, $municipality_name, $province_name, $barangay_id, $municipality_id, $province_id, $triage_date, $triage_time, $arrival_time, $patient_id);
        }

        if ($stmt && $stmt->execute()) {
            $savedPatientId = ($isNewPatient || $isOldPatientReturn) ? $conn->insert_id : $patient_id;
            if ($requiresTriageOnSave) {
                $triageResult = savePatientTriageFromPost($conn, $savedPatientId, $_POST, (int) $_SESSION['user_id']);
                if ($triageResult !== true) {
                    $error = $triageResult;
                }
            }
            if ($isOldPatientReturn) {
                $trackAction = 'Old Patient Returned';
                $trackDescription = 'New emergency room encounter created from old patient record';
                $trackStmt = $conn->prepare("INSERT INTO patient_tracking (patient_id, action, description, user_id) VALUES (?, ?, ?, ?)");
                $trackStmt->bind_param("issi", $savedPatientId, $trackAction, $trackDescription, $_SESSION['user_id']);
                $trackStmt->execute();
            }
            if (!$error) {
                logSecurityEvent($conn, $isNewPatient || $isOldPatientReturn ? 'Patient Registered' : 'Patient Updated', 'Patient record saved: ' . $patient_code);
                setFlashMessage('success', $isNewPatient ? 'Patient and triage added successfully' : ($isOldPatientReturn ? 'Old patient encounter and triage created successfully' : 'Patient updated successfully'));
                redirectAfterPost('patients.php');
            }
        } else {
            $error = $error ?: 'Failed to save patient: ' . $stmt->error;
        }
    }
}

$patients = $conn->query("
    SELECT p.*, t.triage_id, t.triage_level, COALESCE(p.province_name, pr.province_name) as display_province_name, COALESCE(p.municipality_name, m.municipality_name) as display_municipality_name, COALESCE(p.barangay_name, b.barangay_name) as display_barangay_name
    FROM patients p
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    LEFT JOIN provinces pr ON p.province_id = pr.province_id
    LEFT JOIN municipalities m ON p.municipality_id = m.municipality_id
    LEFT JOIN barangays b ON p.barangay_id = b.barangay_id
    WHERE p.status IN ($activeStatuses)
    ORDER BY p.queue_date ASC, p.queue_number ASC, p.arrival_time ASC
")->fetch_all(MYSQLI_ASSOC);

$patient = null;
if ($patient_id && in_array($action, ['edit', 'detail', 'old_edit'], true)) {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    if (!$patient) {
        $error = 'Patient not found.';
        $action = $action === 'old_edit' ? 'old' : 'list';
    }
}

$oldPatientResults = [];
if ($action === 'old' && $oldPatientSearch !== '') {
    $searchTerm = '%' . $oldPatientSearch . '%';
    $stmt = $conn->prepare("
        SELECT patient_id, permanent_patient_id, patient_code, first_name, last_name, middle_name, date_of_birth, age, gender, phone, status, admission_date
        FROM patients
        WHERE (first_name LIKE ? OR last_name LIKE ?)
            AND status NOT IN ($activeStatuses)
        ORDER BY last_name ASC, first_name ASC
        LIMIT 25
    ");
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $oldPatientResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$newCode = generatePatientCode($conn);
$newQueueNumber = generatePatientQueueNumber($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui patients-page">
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
            <?php if(hasPermission('patients','create','ER')): ?><div class="page-actions"><a class="btn btn-primary" href="patient_registration.php?module=er">+ Register Patient</a></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <?php if ($action == 'add' || $action == 'edit' || $action == 'old_edit'): ?>
                <?php $isOldEditForm = $action == 'old_edit'; ?>
                <div class="form-section">
                    <h2>
                        <?php
                        if ($action == 'add') {
                            echo 'Add Patient - Triage';
                        } elseif ($action == 'old_edit') {
                            echo 'Create Old Patient Encounter';
                        } else {
                            echo 'Edit Patient';
                        }
                        ?>
                    </h2>
                    <form method="POST" class="patient-form" data-action="<?php echo htmlspecialchars($action); ?>">
                        <input type="hidden" name="save_patient" value="1">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="permanent-patient-id">Patient ID</label>
                                <input type="text" id="permanent-patient-id" name="permanent_patient_id" readonly value="<?php echo htmlspecialchars($isOldEditForm ? ($patient['permanent_patient_id'] ?? '') : ($patient['permanent_patient_id'] ?? $permanent_patient_id ?? generatePermanentPatientId($conn))); ?>">
                            </div>
                            <div class="form-group">
                                <label for="patient-code">Encounter ID</label>
                                <input type="text" id="patient-code" name="patient_code" readonly value="<?php echo htmlspecialchars($isOldEditForm ? $newCode : ($patient['patient_code'] ?? $newCode)); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="queue-number">Queue No.</label>
                                <input type="text" id="queue-number" readonly value="<?php echo htmlspecialchars(formatQueueNumber($action == 'old_edit' ? $newQueueNumber : ($patient['queue_number'] ?? $newQueueNumber))); ?>">
                            </div>
                        </div>

                        <div class="form-row form-row-three">
                            <div class="form-group">
                                <label for="last-name">Last Name *</label>
                                <input type="text" id="last-name" name="last_name" required value="<?php echo htmlspecialchars($patient['last_name'] ?? ''); ?>" <?php echo $isOldEditForm ? 'readonly' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="first-name">First Name *</label>
                                <input type="text" id="first-name" name="first_name" required value="<?php echo htmlspecialchars($patient['first_name'] ?? ''); ?>" <?php echo $isOldEditForm ? 'readonly' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="middle-name">Middle Name *</label>
                                <input type="text" id="middle-name" name="middle_name" <?php echo $isOldEditForm ? 'readonly' : 'required'; ?> value="<?php echo htmlspecialchars($patient['middle_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="dob">Birthday *</label>
                                <?php if ($isOldEditForm): ?>
                                    <input type="text" id="dob" readonly value="<?php echo htmlspecialchars(formatPatientDate($patient['date_of_birth'] ?? null)); ?>">
                                <?php else: ?>
                                    <input
                                        type="date"
                                        id="dob"
                                        name="date_of_birth"
                                        required
                                        max="<?php echo htmlspecialchars($currentDate); ?>"
                                        value="<?php echo htmlspecialchars(isValidPatientDate($patient['date_of_birth'] ?? null) ? $patient['date_of_birth'] : ''); ?>"
                                    >
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="age">Age</label>
                                <input type="number" id="age" name="age" readonly value="<?php echo htmlspecialchars($patient['age'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="house-no-street">House No. / Street</label>
                                <input type="text" id="house-no-street" name="house_no_street" value="<?php echo htmlspecialchars($patient['house_no_street'] ?? ''); ?>" <?php echo $isOldEditForm ? 'readonly' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="province">Province *</label>
                                <select id="province" name="province_id" required data-selected="<?php echo htmlspecialchars($patient['province_id'] ?? ''); ?>" data-selected-name="<?php echo htmlspecialchars($patient['province_name'] ?? ''); ?>" <?php echo $isOldEditForm ? 'disabled' : ''; ?>>
                                    <option value="">Select Province</option>
                                </select>
                                <input type="hidden" id="province-name" name="province_name" value="<?php echo htmlspecialchars($patient['province_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="municipality">City/Municipality *</label>
                                <select id="municipality" name="municipality_id" required data-selected="<?php echo htmlspecialchars($patient['municipality_id'] ?? ''); ?>" data-selected-name="<?php echo htmlspecialchars($patient['municipality_name'] ?? ''); ?>" <?php echo $isOldEditForm ? 'disabled' : ''; ?>>
                                    <option value="">Select City/Municipality</option>
                                </select>
                                <input type="hidden" id="municipality-name" name="municipality_name" value="<?php echo htmlspecialchars($patient['municipality_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="barangay">Barangay *</label>
                                <select id="barangay" name="barangay_id" required data-selected="<?php echo htmlspecialchars($patient['barangay_id'] ?? ''); ?>" data-selected-name="<?php echo htmlspecialchars($patient['barangay_name'] ?? ''); ?>" <?php echo $isOldEditForm ? 'disabled' : ''; ?>>
                                    <option value="">Select Barangay</option>
                                </select>
                                <input type="hidden" id="barangay-name" name="barangay_name" value="<?php echo htmlspecialchars($patient['barangay_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="gender">Sex *</label>
                                <select id="gender" name="gender" required <?php echo $isOldEditForm ? 'disabled' : ''; ?>>
                                    <option value="">Select Sex</option>
                                    <option value="Male" <?php echo ($patient['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($patient['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($patient['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="civil-status">Civil Status</label>
                                <select id="civil-status" name="civil_status" <?php echo $isOldEditForm ? 'disabled' : ''; ?>>
                                    <option value="">Select Civil Status</option>
                                    <?php foreach ($civilStatusOptions as $civilStatus): ?>
                                        <option value="<?php echo htmlspecialchars($civilStatus); ?>" <?php echo ($patient['civil_status'] ?? '') === $civilStatus ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($civilStatus); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="ward-department">Department *</label>
                                <select id="ward-department" name="ward_department" required <?php echo $isOldEditForm ? 'disabled' : ''; ?>>
                                    <?php foreach (wardDepartmentOptions() as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($patient['ward_department'] ?? 'MEDICAL') === $value ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="religion">Religion *</label>
                                <input type="text" id="religion" name="religion" <?php echo $isOldEditForm ? 'readonly' : 'required'; ?> value="<?php echo htmlspecialchars($patient['religion'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Contact Number *</label>
                                <input type="text" id="phone" name="phone" required value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>" <?php echo $isOldEditForm ? 'readonly' : ''; ?>>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="triage-date">Date of Arrival *</label>
                                <input type="date" id="triage-date" name="triage_date" required value="<?php echo htmlspecialchars($isOldEditForm ? $currentDate : ($patient['triage_date'] ?? $currentDate)); ?>">
                            </div>
                            <div class="form-group">
                                <label for="triage-time">Time of Arrival *</label>
                                <input type="time" id="triage-time" name="triage_time" required value="<?php echo htmlspecialchars($isOldEditForm ? $currentTime : substr($patient['triage_time'] ?? $currentTime, 0, 5)); ?>">
                            </div>
                        </div>

                        <?php if ($action == 'add' || $action == 'old_edit'): ?>
                            <div class="form-section-subtitle">
                                <h3>Triage Assessment</h3>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="triage-level">Triage Level *</label>
                                    <select id="triage-level" name="triage_level" required>
                                        <option value="">Select triage level</option>
                                        <?php foreach ($triageLevelOptions as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($_POST['triage_level'] ?? '') === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="chief-complaint">Chief Complaint *</label>
                                    <textarea id="chief-complaint" name="chief_complaint" required><?php echo htmlspecialchars($_POST['chief_complaint'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="assessment">Assessment</label>
                                    <textarea id="assessment" name="assessment"><?php echo htmlspecialchars($_POST['assessment'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="medicolegal">Medicolegal</label>
                                    <select id="medicolegal" name="medicolegal">
                                        <option value="0" <?php echo ($_POST['medicolegal'] ?? '0') === '0' ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?php echo ($_POST['medicolegal'] ?? '0') === '1' ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="ip">IP</label>
                                    <select id="ip" name="ip">
                                        <option value="0" <?php echo ($_POST['ip'] ?? '0') === '0' ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?php echo ($_POST['ip'] ?? '0') === '1' ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="referred-by">Referred By</label>
                                    <input type="text" id="referred-by" name="referred_by" maxlength="150" value="<?php echo htmlspecialchars($_POST['referred_by'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="coordinated">Coordinated</label>
                                    <select id="coordinated" name="coordinated">
                                        <option value="0" <?php echo ($_POST['coordinated'] ?? '0') === '0' ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?php echo ($_POST['coordinated'] ?? '0') === '1' ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="temperature">Temperature (C) *</label>
                                    <input type="number" id="temperature" name="temperature" step="0.1" required placeholder="e.g., 37.5" value="<?php echo htmlspecialchars($_POST['temperature'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="blood-pressure">Blood Pressure *</label>
                                    <input type="text" id="blood-pressure" name="blood_pressure" required placeholder="e.g., 120/80" value="<?php echo htmlspecialchars($_POST['blood_pressure'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="heart-rate">Heart Rate (bpm) *</label>
                                    <input type="number" id="heart-rate" name="heart_rate" required placeholder="e.g., 72" value="<?php echo htmlspecialchars($_POST['heart_rate'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="respiratory-rate">Respiratory Rate *</label>
                                    <input type="number" id="respiratory-rate" name="respiratory_rate" required placeholder="e.g., 16" value="<?php echo htmlspecialchars($_POST['respiratory_rate'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="vital-pr">PR</label>
                                    <input type="text" id="vital-pr" name="vital_pr" maxlength="20" value="<?php echo htmlspecialchars($_POST['vital_pr'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="vital-height">Height</label>
                                    <input type="text" id="vital-height" name="vital_height" maxlength="20" value="<?php echo htmlspecialchars($_POST['vital_height'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="vital-weight">Weight</label>
                                    <input type="text" id="vital-weight" name="vital_weight" maxlength="20" value="<?php echo htmlspecialchars($_POST['vital_weight'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="vital-spo2">SPO2</label>
                                    <input type="text" id="vital-spo2" name="vital_spo2" maxlength="20" value="<?php echo htmlspecialchars($_POST['vital_spo2'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="vital-cbg">CBG</label>
                                    <input type="text" id="vital-cbg" name="vital_cbg" maxlength="20" value="<?php echo htmlspecialchars($_POST['vital_cbg'] ?? ''); ?>">
                                </div>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary"><?php echo $action == 'add' ? 'Add Patient - Triage' : ($isOldEditForm ? 'Create Encounter' : 'Update Patient'); ?></button>
                        <a href="<?php echo $action == 'old_edit' ? 'patients.php?action=old' : 'patients.php'; ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
                    </form>
                </div>
            <?php elseif ($action == 'old'): ?>
                <div class="patients-section">
                    <h2>Search Old Patient</h2>
                    <form method="GET" class="patient-search-form">
                        <input type="hidden" name="action" value="old">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="old-patient-search">First Name or Last Name</label>
                                <input type="text" id="old-patient-search" name="search" value="<?php echo htmlspecialchars($oldPatientSearch); ?>" placeholder="Type first name or last name" autofocus>
                            </div>
                            <div class="form-group patient-search-actions">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <a href="patients.php?action=choose" class="btn btn-secondary">Back</a>
                            </div>
                        </div>
                    </form>

                    <?php if ($oldPatientSearch !== ''): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Encounter ID</th>
                                        <th>Name</th>
                                        <th>Birthday</th>
                                        <th>Age</th>
                                        <th>Sex</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Last Visit</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($oldPatientResults)): ?>
                                        <tr><td colspan="10" class="text-center">No matching patient found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($oldPatientResults as $oldPatient): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($oldPatient['permanent_patient_id'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($oldPatient['patient_code']); ?></td>
                                                <td><?php echo htmlspecialchars(patientFullName($oldPatient)); ?></td>
                                                <td><?php echo htmlspecialchars(formatPatientDate($oldPatient['date_of_birth'] ?? null)); ?></td>
                                                <td><?php echo htmlspecialchars($oldPatient['age'] ?? calculateAge($oldPatient['date_of_birth'])); ?></td>
                                                <td><?php echo htmlspecialchars($oldPatient['gender']); ?></td>
                                                <td><?php echo htmlspecialchars($oldPatient['phone'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars(statusLabel($oldPatient['status'] ?? '')); ?></td>
                                                <td><?php echo !empty($oldPatient['admission_date']) ? htmlspecialchars(date('M d, Y', strtotime($oldPatient['admission_date']))) : 'N/A'; ?></td>
                                                <td>
                                                    <a href="patients.php?action=old_edit&id=<?php echo (int) $oldPatient['patient_id']; ?>" class="btn btn-sm btn-primary">Create Encounter</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="patients-section">
                    <h2>Emergency Room - Active Patients</h2>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Patient ID</th>
                                    <th>Encounter ID</th>
                                    <th>Queue No.</th>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Sex</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>Triage Level</th>
                                    <th>Status</th>
                                    <th>Disposition</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($patients)): ?>
                                    <tr><td colspan="12" class="text-center">No active patients found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($patients as $p): ?>
                                        <?php $hasTriageAssessment = !empty($p['triage_id']); ?>
                                        <tr class="patient-row triage-<?php echo htmlspecialchars($p['triage_level'] ?? 'none'); ?>">
                                            <td><?php echo htmlspecialchars($p['permanent_patient_id'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($p['patient_code']); ?></td>
                                            <td><strong><?php echo htmlspecialchars(formatQueueNumber($p['queue_number'] ?? null)); ?></strong></td>
                                            <td><?php echo htmlspecialchars(patientFullName($p)); ?></td>
                                            <td><?php echo htmlspecialchars($p['age'] ?? calculateAge($p['date_of_birth'])); ?></td>
                                            <td><?php echo htmlspecialchars($p['gender']); ?></td>
                                            <td><?php echo htmlspecialchars($p['phone'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(trim(($p['house_no_street'] ?? '') . ', ' . ($p['display_barangay_name'] ?? '') . ', ' . ($p['display_municipality_name'] ?? '') . ', ' . ($p['display_province_name'] ?? ''), ', ')); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo htmlspecialchars($p['triage_level'] ?? 'secondary'); ?>">
                                                    <?php echo htmlspecialchars(triageLevelLabel($p['triage_level'] ?? '')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <select name="status" form="status-form-<?php echo $p['patient_id']; ?>" data-original-status="<?php echo htmlspecialchars($p['status']); ?>" <?php echo (!$hasTriageAssessment || !$canUpdatePatientStatus) ? 'disabled' : ''; ?>>
                                                    <?php foreach (forwardPatientStatusOptions($statusOptions, $p['status']) as $value => $label): ?>
                                                        <option value="<?php echo $value; ?>" <?php echo $p['status'] == $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if (!$canUpdatePatientStatus): ?>
                                                    <div class="compact-meta">Status update not allowed</div>
                                                <?php elseif (!$hasTriageAssessment): ?>
                                                    <div class="compact-meta">Save triage assessment first</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary disposition-btn" data-form-id="status-form-<?php echo $p['patient_id']; ?>" <?php echo ($p['status'] !== 'ready_for_disposition' || !$canSetDisposition) ? 'disabled' : ''; ?>>Set Disposition</button>
                                                <?php if (!$canSetDisposition): ?>
                                                    <div class="compact-meta">Doctor sets disposition</div>
                                                <?php elseif ($p['status'] !== 'ready_for_disposition'): ?>
                                                    <div class="compact-meta">Available when ready for disposition</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" id="status-form-<?php echo $p['patient_id']; ?>" class="inline-status-form" style="display: inline;">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="patient_id" value="<?php echo $p['patient_id']; ?>">
                                                    <input type="hidden" name="disposition" class="disposition-input">
                                                    <input type="hidden" name="final_diagnosis" class="final-diagnosis-input">
                                                    <input type="hidden" name="specific_diagnosis" class="specific-diagnosis-input">
                                                    <button type="submit" class="btn btn-sm btn-primary status-save-btn" disabled>Save</button>
                                                </form>
                                                <a href="patients.php?action=edit&id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                                <a href="patient_detail.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm" style="background-color: #0B3C5D; color: white;">View</a>
                                                <a href="er_record.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-secondary">ER Record</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="../public/js/sidebar.js"></script>
    <script src="../public/js/dashboard.js"></script>
    <script src="../public/js/patient_form.js"></script>
    <datalist id="diagnosis-options">
        <?php foreach (diagnosisOptions() as $diagnosis): ?>
            <option value="<?php echo htmlspecialchars($diagnosis); ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <div id="patient-type-modal" class="choice-modal <?php echo $action === 'choose' ? 'open' : ''; ?>" aria-hidden="<?php echo $action === 'choose' ? 'false' : 'true'; ?>">
        <div class="choice-modal-card">
            <h3>Add Patient - Triage</h3>
            <p class="compact-meta">Choose if this is a new patient record or an old patient returning to the emergency room.</p>
            <div class="patient-type-actions">
                <a href="patients.php?action=add" class="btn btn-primary">New Patient</a>
                <a href="patients.php?action=old" class="btn btn-info">Old Patient</a>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 14px;">
                <a href="patients.php" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </div>
    <div id="duplicate-patient-modal" class="choice-modal <?php echo !empty($duplicateOldPatients) ? 'open' : ''; ?>" aria-hidden="<?php echo !empty($duplicateOldPatients) ? 'false' : 'true'; ?>">
        <div class="choice-modal-card duplicate-patient-card">
            <h3>Existing Patient Found</h3>
            <p class="compact-meta">A patient with the same first name, last name, and birthday already exists. Create an encounter from the old patient record instead of creating a new patient.</p>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Encounter ID</th>
                            <th>Name</th>
                            <th>Birthday</th>
                            <th>Last Visit</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duplicateOldPatients as $duplicatePatient): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($duplicatePatient['permanent_patient_id'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($duplicatePatient['patient_code']); ?></td>
                                <td><?php echo htmlspecialchars(patientFullName($duplicatePatient)); ?></td>
                                <td><?php echo htmlspecialchars(formatPatientDate($duplicatePatient['date_of_birth'] ?? null)); ?></td>
                                <td><?php echo !empty($duplicatePatient['admission_date']) ? htmlspecialchars(date('M d, Y', strtotime($duplicatePatient['admission_date']))) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars(statusLabel($duplicatePatient['status'] ?? '')); ?></td>
                                <td>
                                    <?php if (in_array($duplicatePatient['status'] ?? '', inactivePatientStatuses(), true)): ?>
                                        <form method="POST" action="patients.php?action=old_edit&id=<?php echo (int) $duplicatePatient['patient_id']; ?>">
                                            <input type="hidden" name="save_patient" value="1">
                                            <input type="hidden" name="triage_date" value="<?php echo htmlspecialchars($pendingNewPatientPost['triage_date'] ?? $currentDate); ?>">
                                            <input type="hidden" name="triage_time" value="<?php echo htmlspecialchars($pendingNewPatientPost['triage_time'] ?? $currentTime); ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">Create Old Patient Encounter</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="patient_detail.php?id=<?php echo (int) $duplicatePatient['patient_id']; ?>" class="btn btn-sm btn-info">View Active Record</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="GET" action="patients.php" class="duplicate-continue-form">
                <input type="hidden" name="action" value="old">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($pendingNewPatientPost['last_name'] ?? ''); ?>">
                <button type="submit" class="btn btn-primary">Open Old Patient Menu</button>
                <a href="patients.php?action=add" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
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
        html,
        body.patients-page {
            min-height: 100%;
            overflow-y: auto;
        }

        body.patients-page .dashboard-container {
            min-height: calc(100vh - 70px);
            align-items: stretch;
        }

        body.patients-page .main-content {
            height: auto;
            min-height: calc(100vh - 70px);
            overflow-y: visible;
            padding-bottom: 96px;
        }

        body.patients-page .form-section {
            margin-bottom: 72px;
        }

        .patient-search-actions {
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }
        .choice-modal,
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
        .choice-modal.open,
        .discharge-modal.open {
            display: flex;
        }
        .choice-modal-card,
        .discharge-modal-card {
            width: min(520px, 100%);
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
            padding: 22px;
        }
        .patient-type-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 18px;
        }
        .discharge-modal-card textarea {
            width: 100%;
            resize: vertical;
        }
        .duplicate-patient-card {
            width: min(860px, 100%);
        }
        .duplicate-patient-card form {
            margin: 0;
        }
        .duplicate-continue-form {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 14px;
        }
        @media (max-width: 640px) {
            .patient-type-actions,
            .patient-search-form .form-row {
                grid-template-columns: 1fr;
            }
            .patient-search-actions {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>
    <script>
        const dischargeModal = document.getElementById('discharge-modal');
        const modalDisposition = document.getElementById('modal-disposition');
        const modalDiagnosis = document.getElementById('modal-final-diagnosis');
        const modalSpecificDiagnosis = document.getElementById('modal-specific-diagnosis');
        const modalTitle = document.getElementById('diagnosis-modal-title');
        const modalHelp = document.getElementById('diagnosis-modal-help');
        const cancelModal = document.getElementById('cancel-discharge-modal');
        const confirmModal = document.getElementById('confirm-discharge-modal');
        let pendingDischargeForm = null;

        function showConfirmPopup(message, onConfirm) {
            const popup = document.createElement('div');
            popup.className = 'choice-modal open';
            popup.setAttribute('aria-hidden', 'false');
            popup.innerHTML = `
                <div class="choice-modal-card" role="dialog" aria-modal="true">
                    <h3>Confirm Status Change</h3>
                    <p class="compact-meta">${message}</p>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px;">
                        <button type="button" class="btn btn-secondary" data-confirm-cancel>Cancel</button>
                        <button type="button" class="btn btn-primary" data-confirm-ok>Confirm</button>
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
            pendingDischargeForm = null;
            modalDisposition.value = '';
            modalDiagnosis.value = '';
            modalSpecificDiagnosis.value = '';
        };

        cancelModal.addEventListener('click', closeDischargeModal);
        dischargeModal.addEventListener('click', (event) => {
            if (event.target === dischargeModal) {
                closeDischargeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (dischargeModal.classList.contains('open')) {
                    closeDischargeModal();
                }

                document.querySelectorAll('.choice-modal.open').forEach((modal) => {
                    modal.classList.remove('open');
                    modal.setAttribute('aria-hidden', 'true');
                });
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
            if (pendingDischargeForm) {
                pendingDischargeForm.querySelector('.disposition-input').value = modalDisposition.value;
                pendingDischargeForm.querySelector('.final-diagnosis-input').value = modalDiagnosis.value.trim();
                pendingDischargeForm.querySelector('.specific-diagnosis-input').value = modalSpecificDiagnosis.value.trim();
                const form = pendingDischargeForm;
                showConfirmPopup('Are you sure you want to change this patient status?', () => {
                    closeDischargeModal();
                    form.submit();
                });
            }
        });

        document.querySelectorAll('.inline-status-form').forEach((form) => {
            const select = document.querySelector(`select[name="status"][form="${form.id}"]`);
            const button = form.querySelector('.status-save-btn');

            const updateSaveState = () => {
                const statusChanged = select && select.value !== select.dataset.originalStatus;
                button.disabled = !statusChanged;
            };

            if (select) {
                select.addEventListener('change', updateSaveState);
            }

            form.addEventListener('submit', (event) => {
                const dispositionValue = form.querySelector('.disposition-input').value;
                if (dispositionValue && !form.querySelector('.final-diagnosis-input').value.trim()) {
                    event.preventDefault();
                    pendingDischargeForm = form;
                    modalDisposition.value = dispositionValue;
                    modalTitle.textContent = 'Patient Disposition';
                    modalHelp.textContent = 'Choose the disposition and diagnosis before updating the patient status.';
                    dischargeModal.classList.add('open');
                    dischargeModal.setAttribute('aria-hidden', 'false');
                    modalDiagnosis.focus();
                    return;
                }

                event.preventDefault();
                showConfirmPopup('Are you sure you want to change this patient status?', () => form.submit());
            });
        });

        document.querySelectorAll('.disposition-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const form = document.getElementById(button.dataset.formId);
                if (!form) {
                    return;
                }
                pendingDischargeForm = form;
                modalTitle.textContent = 'Patient Disposition';
                modalHelp.textContent = 'Choose the disposition and diagnosis before updating the patient status.';
                dischargeModal.classList.add('open');
                dischargeModal.setAttribute('aria-hidden', 'false');
                modalDisposition.focus();
            });
        });
    </script>
</body>
</html>
