<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/clinical_modules.php';

requireLogin();

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$module = clinicalModuleByPage($currentPage);
if (!$module) {
    header('Location: dashboard.php');
    exit();
}

requirePermission($module['permission'], 'view', $module['department']);
ensureClinicalModuleSchema($conn);

$error = '';
$success = '';
consumeFlashMessages($error, $success);
$canEditModule = hasPermission($module['permission'], 'edit', $module['department']);
$patients = clinicalPatientOptions($conn);
$todayStart = date('Y-m-d') . ' 00:00:00';
$todayEnd = date('Y-m-d') . ' 23:59:59';

function selectedPatientEncounter(array $patients, int $patientId): string {
    foreach ($patients as $patient) {
        if ((int) $patient['patient_id'] === $patientId) {
            return (string) ($patient['patient_code'] ?? '');
        }
    }
    return '';
}

function moduleMovementPatientOptions(mysqli $conn, array $module): array {
    if ($module['key'] === 'micu') {
        return clinicalRows($conn, "
            SELECT p.patient_id, p.patient_code, p.permanent_patient_id, p.first_name, p.middle_name, p.last_name, p.age, p.gender, p.status, p.ward_department
            FROM micu_records r
            INNER JOIN patients p ON p.patient_id = r.patient_id
            WHERE r.status='ACTIVE'
            ORDER BY r.admission_date_time DESC, p.last_name, p.first_name
        ");
    }
    if ($module['key'] === 'nicu') {
        return clinicalRows($conn, "
            SELECT p.patient_id, p.patient_code, p.permanent_patient_id, p.first_name, p.middle_name, p.last_name, p.age, p.gender, p.status, p.ward_department
            FROM nicu_records r
            INNER JOIN patients p ON p.patient_id = r.patient_id
            WHERE r.status='ACTIVE'
            ORDER BY r.admission_date_time DESC, p.last_name, p.first_name
        ");
    }
    if ($module['key'] === 'or') {
        return clinicalRows($conn, "
            SELECT p.patient_id, p.patient_code, p.permanent_patient_id, p.first_name, p.middle_name, p.last_name, p.age, p.gender, p.status, p.ward_department
            FROM or_schedule o
            INNER JOIN patients p ON p.patient_id = o.patient_id
            WHERE o.status IN ('PENDING','ACCEPTED','IN OR','RECOVERY')
            ORDER BY o.schedule_date_time DESC, p.last_name, p.first_name
        ");
    }
    if ($module['key'] === 'ldr') {
        return clinicalRows($conn, "
            SELECT p.patient_id, p.patient_code, p.permanent_patient_id, p.first_name, p.middle_name, p.last_name, p.age, p.gender, p.status, p.ward_department
            FROM delivery_room_records r
            INNER JOIN patients p ON p.patient_id = r.patient_id
            ORDER BY COALESCE(r.date_time_of_delivery, r.created_at) DESC, p.last_name, p.first_name
            LIMIT 250
        ");
    }
    return clinicalPatientOptions($conn);
}

function moduleMovementTypes(array $module): array {
    $prefixes = match ($module['key']) {
        'micu' => ['MICU TO'],
        'nicu' => ['NICU TO'],
        'or' => ['OR TO'],
        'ldr' => ['LR/DR TO'],
        default => [],
    };

    if (!$prefixes) {
        return clinicalMovementTypes();
    }

    return array_values(array_filter(clinicalMovementTypes(), function ($type) use ($prefixes) {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($type, $prefix)) {
                return true;
            }
        }
        return false;
    }));
}

function criticalDischargeStatus(string $disposition): string {
    return match ($disposition) {
        'HAMA' => 'hama',
        'ABSCONDED' => 'absconded',
        'EXPIRED' => 'expired',
        default => 'discharge',
    };
}

$movementPatients = moduleMovementPatientOptions($conn, $module);
$movementTypes = moduleMovementTypes($module);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEditModule) {
        $error = 'You do not have permission to update this module.';
    } elseif (isset($_POST['create_movement'])) {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $from = $module['department'];
        $to = sanitizeText($_POST['to_department'] ?? '', 60);
        $movementType = sanitizeText($_POST['movement_type'] ?? '', 80);
        $reason = sanitizeText($_POST['reason'] ?? '', 1000);
        $remarks = sanitizeText($_POST['remarks'] ?? '', 2000);
        $encounterId = selectedPatientEncounter($movementPatients, $patientId);
        $allowedMovementPatientIds = array_map(fn($patient) => (int) $patient['patient_id'], $movementPatients);
        if ($patientId <= 0 || !in_array($patientId, $allowedMovementPatientIds, true) || $to === '' || !in_array($movementType, $movementTypes, true)) {
            $error = 'Please select a patient, destination, and valid movement type.';
        } elseif (clinicalCreateMovementSafe($conn, $patientId, $encounterId, $from, $to, $movementType, $reason, $remarks)) {
            $success = 'Patient movement request recorded.';
        } else {
            $error = 'Unable to record patient movement.';
        }
    } elseif (isset($_POST['discharge_critical_patient']) && in_array($module['key'], ['micu','nicu'], true)) {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        $disposition = sanitizeText($_POST['disposition'] ?? '', 80);
        $dischargeAt = sanitizeText($_POST['discharge_date_time'] ?? date('Y-m-d\TH:i'), 30);
        $remarks = sanitizeText($_POST['remarks'] ?? '', 2000);
        $allowedDispositions = ['DISCHARGED', 'HAMA', 'ABSCONDED', 'EXPIRED'];
        $table = $module['table'];
        $department = $module['department'];
        $unitName = $module['short'];

        $recordRowsForUpdate = clinicalRows($conn, "
            SELECT r.id, r.patient_id, r.encounter_id, r.bed_no, r.diagnosis, p.status AS patient_status
            FROM {$table} r
            INNER JOIN patients p ON p.patient_id = r.patient_id
            WHERE r.id=? AND r.status='ACTIVE'
            LIMIT 1
        ", [$recordId], 'i');
        $record = $recordRowsForUpdate[0] ?? null;

        if (!$record) {
            $error = "Please select an active {$unitName} patient.";
        } elseif (!in_array($disposition, $allowedDispositions, true) || $dischargeAt === '') {
            $error = 'Please select a valid discharge disposition and date/time.';
        } else {
            $patientId = (int) $record['patient_id'];
            $encounterId = (string) ($record['encounter_id'] ?? '');
            $patientStatus = criticalDischargeStatus($disposition);
            $movementType = $disposition === 'DISCHARGED' ? 'DISCHARGE' : $disposition;
            $notes = trim($remarks);
            $recordRemarks = trim('Disposition: ' . $disposition . ($notes !== '' ? "\n" . $notes : ''));
            $userId = $_SESSION['user_id'] ?? null;

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE {$table} SET status='DISCHARGED', disposition=?, discharge_date_time=?, remarks=? WHERE id=? AND status='ACTIVE'");
                $stmt->bind_param("sssi", $disposition, $dischargeAt, $recordRemarks, $recordId);
                $stmt->execute();

                if ($stmt->affected_rows < 1) {
                    throw new RuntimeException('Unable to update the active critical care record.');
                }

                $stmt = $conn->prepare("UPDATE patients SET status=?, updated_at=NOW() WHERE patient_id=?");
                $stmt->bind_param("si", $patientStatus, $patientId);
                $stmt->execute();

                if (!clinicalCreateMovementSafe($conn, $patientId, $encounterId, $department, $patientStatus, $movementType, $disposition, $notes, 'COMPLETED')) {
                    throw new RuntimeException('Unable to record the discharge movement.');
                }

                $trackStmt = $conn->prepare("INSERT INTO patient_tracking (patient_id, action, description, from_status, to_status, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                $action = $unitName . ' Discharge';
                $description = $unitName . ' patient marked as ' . $disposition . ($notes !== '' ? ' - ' . $notes : '');
                $trackStmt->bind_param("issssi", $patientId, $action, $description, $department, $patientStatus, $userId);
                $trackStmt->execute();

                clinicalAudit($conn, $action, $unitName, $patientId, $encounterId, null, [
                    'record_id' => $recordId,
                    'disposition' => $disposition,
                    'discharge_date_time' => $dischargeAt,
                    'remarks' => $remarks,
                ]);
                $conn->commit();
                $success = $unitName . ' patient discharge saved.';
            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    } elseif (isset($_POST['save_or_schedule'])) {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $encounterId = selectedPatientEncounter($patients, $patientId);
        $source = sanitizeText($_POST['source_department'] ?? 'WARD', 60);
        $procedure = sanitizeText($_POST['procedure_name'] ?? '', 200);
        $surgeon = sanitizeText($_POST['surgeon'] ?? '', 150);
        $anesthesiologist = sanitizeText($_POST['anesthesiologist'] ?? '', 150);
        $scheduleDate = sanitizeText($_POST['schedule_date_time'] ?? '', 30);
        $urgency = sanitizeText($_POST['urgency_type'] ?? 'ELECTIVE', 40);
        $postDestination = sanitizeText($_POST['post_op_destination'] ?? 'WARD', 60);
        $remarks = sanitizeText($_POST['remarks'] ?? '', 2000);
        if ($patientId <= 0 || $procedure === '' || $scheduleDate === '') {
            $error = 'Please select a patient, procedure, and schedule date/time.';
        } else {
            $createdBy = $_SESSION['user_id'] ?? null;
            $stmt = $conn->prepare("INSERT INTO or_schedule (patient_id, encounter_id, source_department, `procedure`, procedure_name, surgeon, anesthesiologist, schedule_date_time, urgency_type, post_op_destination, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssssssi", $patientId, $encounterId, $source, $procedure, $procedure, $surgeon, $anesthesiologist, $scheduleDate, $urgency, $postDestination, $remarks, $createdBy);
            if ($stmt->execute()) {
                $movementType = $source === 'ER' ? 'ER TO OR' : ($source === 'OPD' ? 'OPD TO OR' : 'WARD TO OR');
                clinicalCreateMovementSafe($conn, $patientId, $encounterId, $source, 'OR', $movementType, $procedure, $remarks);
                clinicalAudit($conn, 'OR Schedule Created', 'Operating Room', $patientId, $encounterId, null, $_POST);
                $success = 'OR schedule saved.';
            } else {
                $error = 'Unable to save OR schedule.';
            }
        }
    } elseif (isset($_POST['save_delivery_record'])) {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $babyPatientId = (int) ($_POST['baby_patient_id'] ?? 0);
        $encounterId = selectedPatientEncounter($patients, $patientId);
        $gravida = sanitizeText($_POST['gravida'] ?? '', 20);
        $para = sanitizeText($_POST['para'] ?? '', 20);
        $aog = sanitizeText($_POST['age_of_gestation'] ?? '', 40);
        $deliveryType = sanitizeText($_POST['delivery_type'] ?? '', 80);
        $deliveryAt = sanitizeText($_POST['date_time_of_delivery'] ?? '', 30);
        $motherDisposition = sanitizeText($_POST['mother_disposition'] ?? '', 80);
        $babyDisposition = sanitizeText($_POST['baby_disposition'] ?? '', 80);
        $referredToNicu = $babyDisposition === 'TRANSFER TO NICU' ? 1 : 0;
        $remarks = sanitizeText($_POST['remarks'] ?? '', 2000);
        if ($patientId <= 0 || $deliveryAt === '') {
            $error = 'Please select the mother patient and delivery date/time.';
        } else {
            $createdBy = $_SESSION['user_id'] ?? null;
            $babyValue = $babyPatientId > 0 ? $babyPatientId : null;
            $stmt = $conn->prepare("INSERT INTO delivery_room_records (patient_id, encounter_id, gravida, para, age_of_gestation, delivery_type, date_time_of_delivery, mother_disposition, baby_disposition, baby_patient_id, referred_to_nicu, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssssiisi", $patientId, $encounterId, $gravida, $para, $aog, $deliveryType, $deliveryAt, $motherDisposition, $babyDisposition, $babyValue, $referredToNicu, $remarks, $createdBy);
            if ($stmt->execute()) {
                if ($motherDisposition === 'TRANSFER TO WARD') {
                    clinicalCreateMovementSafe($conn, $patientId, $encounterId, 'LR_DR', 'WARD', 'LR/DR TO WARD', 'Mother disposition', $remarks);
                }
                if ($referredToNicu && $babyValue) {
                    clinicalCreateMovementSafe($conn, $babyValue, selectedPatientEncounter($patients, $babyValue), 'LR_DR', 'NICU', 'LR/DR TO NICU', 'Baby disposition', $remarks);
                }
                clinicalAudit($conn, 'Delivery Record Created', 'LR/DR', $patientId, $encounterId, null, $_POST);
                $success = 'Delivery record saved.';
            } else {
                $error = 'Unable to save delivery record.';
            }
        }
    } elseif (isset($_POST['save_critical_record'])) {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $encounterId = selectedPatientEncounter($patients, $patientId);
        $source = sanitizeText($_POST['source_department'] ?? '', 60);
        $diagnosis = sanitizeText($_POST['diagnosis'] ?? '', 200);
        $bedNo = sanitizeText($_POST['bed_no'] ?? '', 40);
        $admissionAt = sanitizeText($_POST['admission_date_time'] ?? date('Y-m-d\TH:i'), 30);
        $remarks = sanitizeText($_POST['remarks'] ?? '', 2000);
        $createdBy = $_SESSION['user_id'] ?? null;
        if ($patientId <= 0 || $admissionAt === '') {
            $error = 'Please select a patient and admission date/time.';
        } elseif ($module['key'] === 'micu') {
            $stmt = $conn->prepare("INSERT INTO micu_records (patient_id, encounter_id, admission_source, diagnosis, bed_no, admission_date_time, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssi", $patientId, $encounterId, $source, $diagnosis, $bedNo, $admissionAt, $remarks, $createdBy);
            $success = $stmt->execute() ? 'MICU record saved.' : '';
            if ($success) {
                clinicalAudit($conn, 'MICU Record Created', 'MICU', $patientId, $encounterId, null, $_POST);
            } else {
                $error = 'Unable to save MICU record.';
            }
        } elseif ($module['key'] === 'nicu') {
            $babyType = sanitizeText($_POST['baby_type'] ?? '', 60);
            $inbornOutborn = sanitizeText($_POST['inborn_or_outborn'] ?? 'INBORN', 20);
            $motherPatientId = (int) ($_POST['mother_patient_id'] ?? 0);
            $motherValue = $motherPatientId > 0 ? $motherPatientId : null;
            $stmt = $conn->prepare("INSERT INTO nicu_records (patient_id, encounter_id, baby_type, inborn_or_outborn, mother_patient_id, source_department, diagnosis, bed_no, admission_date_time, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssisssssi", $patientId, $encounterId, $babyType, $inbornOutborn, $motherValue, $source, $diagnosis, $bedNo, $admissionAt, $remarks, $createdBy);
            $success = $stmt->execute() ? 'NICU record saved.' : '';
            if ($success) {
                clinicalAudit($conn, 'NICU Record Created', 'NICU', $patientId, $encounterId, null, $_POST);
            } else {
                $error = 'Unable to save NICU record.';
            }
        }
    }
    if ($error) { setFlashMessage('error', $error); }
    if ($success) { setFlashMessage('success', $success); }
    redirectAfterPost($currentPage);
}

$pageTitle = $module['page_title'];
$movementRows = clinicalRows($conn, "
    SELECT pm.*, p.permanent_patient_id, p.patient_code,
        CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name,
        requester.username AS requested_by_name,
        accepter.username AS accepted_by_name
    FROM patient_movements pm
    INNER JOIN patients p ON p.patient_id = pm.patient_id
    LEFT JOIN users requester ON requester.user_id = pm.requested_by
    LEFT JOIN users accepter ON accepter.user_id = pm.accepted_by
    WHERE COALESCE(pm.source_department, pm.from_department)=? OR COALESCE(pm.destination_department, pm.to_department)=?
    ORDER BY COALESCE(pm.movement_date_time, pm.created_at) DESC
    LIMIT 40
", [$module['department'], $module['department']], 'ss');

$dashboardStats = [
    'movements_pending' => clinicalScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (destination_department=? OR to_department=?) AND status='PENDING'", [$module['department'], $module['department']], 'ss'),
    'in_transit' => clinicalScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (destination_department=? OR to_department=?) AND status='IN TRANSIT'", [$module['department'], $module['department']], 'ss'),
    'completed_today' => clinicalScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (source_department=? OR from_department=? OR destination_department=? OR to_department=?) AND status='COMPLETED' AND DATE(COALESCE(movement_date_time, created_at))=CURDATE()", [$module['department'], $module['department'], $module['department'], $module['department']], 'ssss'),
];
if ($module['key'] === 'or') {
    $dashboardStats['active_records'] = clinicalScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE status IN ('PENDING','ACCEPTED','IN OR','RECOVERY')");
} elseif ($module['key'] === 'ldr') {
    $dashboardStats['active_records'] = clinicalScalar($conn, "SELECT COUNT(*) FROM delivery_room_records WHERE DATE(COALESCE(date_time_of_delivery, created_at))=CURDATE()");
} else {
    $dashboardStats['active_records'] = clinicalScalar($conn, "SELECT COUNT(*) FROM {$module['table']} WHERE status='ACTIVE'");
}

$recordRows = [];
$criticalDischargeRows = [];
if ($module['key'] === 'or') {
    $recordRows = clinicalRows($conn, "SELECT o.*, p.permanent_patient_id, p.patient_code, CONCAT(p.last_name, ', ', p.first_name) AS patient_name FROM or_schedule o INNER JOIN patients p ON p.patient_id=o.patient_id ORDER BY o.schedule_date_time DESC LIMIT 40");
} elseif ($module['key'] === 'ldr') {
    $recordRows = clinicalRows($conn, "SELECT r.*, p.permanent_patient_id, p.patient_code, CONCAT(p.last_name, ', ', p.first_name) AS patient_name FROM delivery_room_records r INNER JOIN patients p ON p.patient_id=r.patient_id ORDER BY COALESCE(r.date_time_of_delivery, r.created_at) DESC LIMIT 40");
} elseif ($module['key'] === 'micu') {
    $recordRows = clinicalRows($conn, "SELECT r.*, p.permanent_patient_id, p.patient_code, CONCAT(p.last_name, ', ', p.first_name) AS patient_name FROM micu_records r INNER JOIN patients p ON p.patient_id=r.patient_id ORDER BY r.admission_date_time DESC LIMIT 40");
    $criticalDischargeRows = clinicalRows($conn, "SELECT r.*, p.permanent_patient_id, p.patient_code, p.age, p.gender, CONCAT(p.last_name, ', ', p.first_name) AS patient_name FROM micu_records r INNER JOIN patients p ON p.patient_id=r.patient_id WHERE r.status='ACTIVE' ORDER BY r.admission_date_time DESC, p.last_name, p.first_name");
} elseif ($module['key'] === 'nicu') {
    $recordRows = clinicalRows($conn, "SELECT r.*, p.permanent_patient_id, p.patient_code, CONCAT(p.last_name, ', ', p.first_name) AS patient_name FROM nicu_records r INNER JOIN patients p ON p.patient_id=r.patient_id ORDER BY r.admission_date_time DESC LIMIT 40");
    $criticalDischargeRows = clinicalRows($conn, "SELECT r.*, p.permanent_patient_id, p.patient_code, p.age, p.gender, CONCAT(p.last_name, ', ', p.first_name) AS patient_name FROM nicu_records r INNER JOIN patients p ON p.patient_id=r.patient_id WHERE r.status='ACTIVE' ORDER BY r.admission_date_time DESC, p.last_name, p.first_name");
}

$bedSummaries = clinicalAllBedSummaries($conn);
$currentBedSummary = null;
foreach ($bedSummaries as $bedSummary) {
    if (($module['key'] === 'micu' && $bedSummary['area_name'] === 'ICU / MICU') || ($module['key'] === 'nicu' && $bedSummary['area_name'] === 'NICU')) {
        $currentBedSummary = $bedSummary;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .module-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .module-grid { display: grid; grid-template-columns: 1.15fr .85fr; gap: 18px; align-items: start; }
        .module-panel { background:#fff; border:1px solid #E1E7EF; border-radius:8px; padding:18px; box-shadow:0 8px 22px rgba(30,58,95,.06); margin-bottom:18px; }
        .module-panel h3 { margin:0 0 12px; color:#0B3C5D; padding-bottom:9px; border-bottom:2px solid #E1E7EF; }
        .module-form { display:grid; gap:12px; }
        .module-form .form-row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .badge-status { display:inline-flex; border-radius:999px; padding:4px 9px; font-size:11px; font-weight:800; background:#F4F7FA; color:#0B3C5D; }
        .critical-discharge-table td { vertical-align: middle; }
        .critical-discharge-meta { color:#5D6B7C; font-size:12px; display:block; margin-top:2px; }
        .critical-discharge-modal { position:fixed; inset:0; background:rgba(15,23,42,.42); display:none; align-items:center; justify-content:center; z-index:1000; padding:18px; }
        .critical-discharge-modal.open { display:flex; }
        .critical-discharge-card { width:min(560px, 100%); background:#fff; border-radius:8px; box-shadow:0 24px 70px rgba(15,23,42,.26); padding:22px; }
        .critical-discharge-card h3 { margin:0 0 6px; color:#0B3C5D; }
        .critical-discharge-card p { margin:0 0 16px; color:#5D6B7C; }
        .critical-discharge-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:6px; }
        .bed-status-AVAILABLE { color:#0B3C5D; }
        .bed-status-FULL { color:#9A5B10; }
        .bed-status-OVER { color:#B42318; }
        @media (max-width: 980px) { .module-grid, .module-form .form-row { grid-template-columns:1fr; } }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
<nav class="navbar"><div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div><div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?></span></div></nav>
<div class="dashboard-container">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
    <section class="page-header">
        <div class="jvgh-brand dashboard-heading">
            <div><h2><?php echo h($pageTitle); ?></h2><p class="compact-meta"><?php echo h($module['label']); ?> workflow connected to the Patient Movement Tracker.</p></div>
            <div class="module-actions">
                <a class="btn btn-info" href="movement_history.php?department=<?php echo h($module['department']); ?>">View Movement History</a>
                <?php if ($module['key'] === 'or'): ?><a class="btn btn-primary" href="or_schedule.php">New OR Schedule</a><?php endif; ?>
                <?php if ($module['key'] === 'ldr'): ?><a class="btn btn-primary" href="ldr_delivery_record.php">New Delivery Record</a><?php endif; ?>
            </div>
        </div>
    </section>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>

    <section class="stats-grid">
        <div class="stat-card"><div class="stat-info"><span class="stat-label"><?php echo h($module['short']); ?> Census / Records</span><span class="stat-value"><?php echo h($dashboardStats['active_records']); ?></span></div></div>
        <div class="stat-card waiting"><div class="stat-info"><span class="stat-label">Pending Transfer Requests</span><span class="stat-value"><?php echo h($dashboardStats['movements_pending']); ?></span></div></div>
        <div class="stat-card"><div class="stat-info"><span class="stat-label">Patients in Transit</span><span class="stat-value"><?php echo h($dashboardStats['in_transit']); ?></span></div></div>
        <div class="stat-card available"><div class="stat-info"><span class="stat-label">Completed Today</span><span class="stat-value"><?php echo h($dashboardStats['completed_today']); ?></span></div></div>
        <?php if ($currentBedSummary && str_contains($currentPage, 'bed_tracker')): ?>
            <div class="stat-card available"><div class="stat-info"><span class="stat-label">Available Beds</span><span class="stat-value"><?php echo h(max(0, (int) $currentBedSummary['vacant'])); ?></span></div></div>
            <div class="stat-card"><div class="stat-info"><span class="stat-label">Capacity Beds</span><span class="stat-value"><?php echo h($currentBedSummary['total_bed_capacity']); ?></span></div></div>
        <?php endif; ?>
    </section>

    <section class="module-grid">
        <div>
            <?php if ($module['key'] === 'or' && in_array($currentPage, ['or_schedule.php','or_for_patients.php','or_preop_checklist.php'], true)): ?>
                <div class="module-panel"><h3>Schedule Patient for OR</h3><form method="POST" class="module-form">
                    <input type="hidden" name="save_or_schedule" value="1">
                    <div class="form-row"><div class="form-group"><label>Patient</label><select name="patient_id" required><option value="">Select patient</option><?php foreach ($patients as $patient): ?><option value="<?php echo (int) $patient['patient_id']; ?>"><?php echo h(clinicalPatientLabel($patient)); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Source Department</label><select name="source_department"><option>ER</option><option>WARD</option><option>OPD</option><option>LR_DR</option><option>MICU</option></select></div></div>
                    <div class="form-row"><div class="form-group"><label>Procedure</label><input name="procedure_name" required></div><div class="form-group"><label>Schedule Date/Time</label><input type="datetime-local" name="schedule_date_time" required></div></div>
                    <div class="form-row"><div class="form-group"><label>Surgeon</label><input name="surgeon"></div><div class="form-group"><label>Anesthesiologist</label><input name="anesthesiologist"></div></div>
                    <div class="form-row"><div class="form-group"><label>Urgency</label><select name="urgency_type"><option>ELECTIVE</option><option>EMERGENCY</option><option>URGENT</option></select></div><div class="form-group"><label>Post-Op Destination</label><select name="post_op_destination"><option>WARD</option><option>MICU</option><option>OR</option></select></div></div>
                    <div class="form-group"><label>Remarks</label><textarea name="remarks"></textarea></div><button class="btn btn-primary">Save OR Schedule</button>
                </form></div>
            <?php elseif ($module['key'] === 'ldr' && in_array($currentPage, ['ldr_delivery_record.php','ldr_labor_monitoring.php','ldr_mother_disposition.php','ldr_baby_disposition.php'], true)): ?>
                <div class="module-panel"><h3>Delivery Record</h3><form method="POST" class="module-form">
                    <input type="hidden" name="save_delivery_record" value="1">
                    <div class="form-row"><div class="form-group"><label>Mother Patient</label><select name="patient_id" required><option value="">Select mother</option><?php foreach ($patients as $patient): ?><option value="<?php echo (int) $patient['patient_id']; ?>"><?php echo h(clinicalPatientLabel($patient)); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Baby Patient</label><select name="baby_patient_id"><option value="">Link baby record</option><?php foreach ($patients as $patient): ?><option value="<?php echo (int) $patient['patient_id']; ?>"><?php echo h(clinicalPatientLabel($patient)); ?></option><?php endforeach; ?></select></div></div>
                    <div class="form-row"><div class="form-group"><label>Gravida</label><input name="gravida"></div><div class="form-group"><label>Para</label><input name="para"></div></div>
                    <div class="form-row"><div class="form-group"><label>Age of Gestation</label><input name="age_of_gestation"></div><div class="form-group"><label>Delivery Type</label><input name="delivery_type"></div></div>
                    <div class="form-row"><div class="form-group"><label>Date/Time of Delivery</label><input type="datetime-local" name="date_time_of_delivery" required></div><div class="form-group"><label>Mother Disposition</label><select name="mother_disposition"><option>REMAIN IN LR/DR</option><option>TRANSFER TO WARD</option><option>TRANSFER TO OTHER FACILITY</option></select></div></div>
                    <div class="form-group"><label>Baby Disposition</label><select name="baby_disposition"><option>ROOM-IN</option><option>TRANSFER TO NICU</option><option>TRANSFER TO WARD</option><option>REFERRED TO OTHER FACILITY</option></select></div>
                    <div class="form-group"><label>Remarks</label><textarea name="remarks"></textarea></div><button class="btn btn-primary">Save Delivery Record</button>
                </form></div>
            <?php elseif (in_array($module['key'], ['micu','nicu'], true) && (str_contains($currentPage, 'patients') || str_contains($currentPage, 'bed') || str_contains($currentPage, 'monitoring'))): ?>
                <div class="module-panel"><h3><?php echo h($module['short']); ?> Admission / Bed Assignment</h3><form method="POST" class="module-form">
                    <input type="hidden" name="save_critical_record" value="1">
                    <div class="form-row"><div class="form-group"><label>Patient</label><select name="patient_id" required><option value="">Select patient</option><?php foreach ($patients as $patient): ?><option value="<?php echo (int) $patient['patient_id']; ?>"><?php echo h(clinicalPatientLabel($patient)); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Source Department</label><select name="source_department"><option>ER</option><option>WARD</option><option>LR_DR</option><option>OPD</option><option>OR</option></select></div></div>
                    <?php if ($module['key'] === 'nicu'): ?><div class="form-row"><div class="form-group"><label>Baby Type</label><input name="baby_type"></div><div class="form-group"><label>Inborn / Outborn</label><select name="inborn_or_outborn"><option>INBORN</option><option>OUTBORN</option></select></div></div><div class="form-group"><label>Mother Patient</label><select name="mother_patient_id"><option value="">Optional mother link</option><?php foreach ($patients as $patient): ?><option value="<?php echo (int) $patient['patient_id']; ?>"><?php echo h(clinicalPatientLabel($patient)); ?></option><?php endforeach; ?></select></div><?php endif; ?>
                    <div class="form-row"><div class="form-group"><label>Diagnosis</label><input name="diagnosis"></div><div class="form-group"><label>Bed No.</label><input name="bed_no"></div></div>
                    <div class="form-group"><label>Admission Date/Time</label><input type="datetime-local" name="admission_date_time" value="<?php echo h(date('Y-m-d\TH:i')); ?>"></div>
                    <div class="form-group"><label>Remarks</label><textarea name="remarks"></textarea></div><button class="btn btn-primary">Save <?php echo h($module['short']); ?> Record</button>
                </form></div>
            <?php elseif (in_array($module['key'], ['micu','nicu'], true) && str_contains($currentPage, 'discharge')): ?>
                <div class="module-panel"><h3><?php echo h($module['short']); ?> Discharge</h3>
                    <div class="table-responsive"><table class="table table-striped critical-discharge-table"><thead><tr><th>Patient</th><th>Age / Sex</th><th>Bed</th><th>Admission</th><th>Diagnosis</th><th>Action</th></tr></thead><tbody>
                        <?php foreach ($criticalDischargeRows as $row): ?><tr>
                            <td><strong><?php echo h($row['patient_name']); ?></strong><span class="critical-discharge-meta"><?php echo h($row['permanent_patient_id'] ?: $row['patient_code']); ?></span></td>
                            <td><?php echo h(($row['age'] ?? '') . ' / ' . ($row['gender'] ?? '')); ?></td>
                            <td><?php echo h($row['bed_no'] ?: 'N/A'); ?></td>
                            <td><?php echo h(date('M d, Y h:i A', strtotime($row['admission_date_time']))); ?></td>
                            <td><?php echo h($row['diagnosis'] ?: 'N/A'); ?></td>
                            <td><button type="button" class="btn btn-sm btn-danger critical-discharge-open" data-record-id="<?php echo (int) $row['id']; ?>" data-patient-name="<?php echo h($row['patient_name']); ?>" data-bed-no="<?php echo h($row['bed_no'] ?: 'N/A'); ?>">Discharge</button></td>
                        </tr><?php endforeach; ?>
                        <?php if (empty($criticalDischargeRows)): ?><tr><td colspan="6" class="text-center">No active <?php echo h($module['short']); ?> patients for discharge.</td></tr><?php endif; ?>
                    </tbody></table></div>
                </div>
            <?php endif; ?>

            <div class="module-panel"><h3><?php echo h($module['short']); ?> Records</h3><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Patient</th><th>Record</th><th>Status</th><th>Date/Time</th><th>Remarks</th></tr></thead><tbody>
                <?php foreach ($recordRows as $row): ?><tr><td><?php echo h($row['patient_name']); ?><br><small><?php echo h($row['permanent_patient_id'] ?: $row['patient_code']); ?></small></td><td><?php echo h($row['procedure_name'] ?? $row['delivery_type'] ?? $row['diagnosis'] ?? 'Record'); ?></td><td><span class="badge-status"><?php echo h($row['status'] ?? $row['mother_disposition'] ?? 'ACTIVE'); ?></span></td><td><?php echo h(date('M d, Y h:i A', strtotime($row['schedule_date_time'] ?? $row['date_time_of_delivery'] ?? $row['admission_date_time'] ?? $row['created_at']))); ?></td><td><?php echo h($row['remarks'] ?? ''); ?></td></tr><?php endforeach; ?>
                <?php if (empty($recordRows)): ?><tr><td colspan="5" class="text-center">No records found.</td></tr><?php endif; ?>
            </tbody></table></div></div>
        </div>
        <aside>
            <div class="module-panel" id="trans-out-patient"><h3>Trans In / Trans Out Patient</h3><form method="POST" class="module-form">
                <input type="hidden" name="create_movement" value="1">
                <div class="form-group"><label>Patient in <?php echo h($module['short']); ?></label><select name="patient_id" required><option value="">Select active patient</option><?php foreach ($movementPatients as $patient): ?><option value="<?php echo (int) $patient['patient_id']; ?>"><?php echo h(clinicalPatientLabel($patient)); ?></option><?php endforeach; ?></select></div>
                <div class="form-row"><div class="form-group"><label>From</label><input value="<?php echo h($module['short']); ?>" readonly></div><div class="form-group"><label>To</label><select name="to_department" required><option value="">Select</option><option>WARD</option><option>MICU</option><option>OR</option><option>NICU</option><option>ER</option><option>OTHER FACILITY</option></select></div></div>
                <div class="form-group"><label>Movement Type</label><select name="movement_type" required><?php foreach ($movementTypes as $type): ?><option value="<?php echo h($type); ?>"><?php echo h($type); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Reason</label><input name="reason"></div><div class="form-group"><label>Remarks</label><textarea name="remarks"></textarea></div><button class="btn btn-primary">Record Movement</button>
            </form></div>
            <?php if (in_array($module['key'], ['micu','nicu'], true) || str_contains($currentPage, 'bed_tracker')): ?><div class="module-panel"><h3>Bed Capacity</h3><?php foreach ($bedSummaries as $bed): if (($module['key']==='micu' && $bed['area_name'] !== 'ICU / MICU') || ($module['key']==='nicu' && $bed['area_name'] !== 'NICU')) { continue; } ?><div class="metric-line"><strong><?php echo h($bed['area_name']); ?></strong><span><?php echo h($bed['vacant_label']); ?></span></div><div class="metric-line"><span>Occupied</span><strong><?php echo h($bed['occupied']); ?></strong></div><div class="metric-line"><span>Status</span><strong class="bed-status-<?php echo h(strtok($bed['status'], ' ')); ?>"><?php echo h($bed['status']); ?></strong></div><?php endforeach; ?></div><?php endif; ?>
        </aside>
    </section>

    <section class="module-panel"><h3>Patient Movement Tracker</h3><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Patient</th><th>Current</th><th>Previous</th><th>Destination</th><th>Movement Type</th><th>Status</th><th>Date/Time</th><th>Requested By</th><th>Accepted By</th><th>Remarks</th></tr></thead><tbody>
    <?php foreach ($movementRows as $row): ?><tr><td><?php echo h($row['patient_name']); ?><br><small><?php echo h($row['permanent_patient_id'] ?: $row['patient_code']); ?></small></td><td><?php echo h($row['current_department'] ?: $row['from_department']); ?></td><td><?php echo h($row['previous_department'] ?: $row['from_department']); ?></td><td><?php echo h($row['destination_department'] ?: $row['to_department']); ?></td><td><?php echo h($row['movement_type']); ?></td><td><span class="badge-status"><?php echo h($row['status']); ?></span></td><td><?php echo h(date('M d, Y h:i A', strtotime($row['movement_date_time'] ?? $row['created_at']))); ?></td><td><?php echo h($row['requested_by_name']); ?></td><td><?php echo h($row['accepted_by_name'] ?: 'N/A'); ?></td><td><?php echo nl2br(h($row['remarks'])); ?></td></tr><?php endforeach; ?>
    <?php if (empty($movementRows)): ?><tr><td colspan="10" class="text-center">No movement records for this module.</td></tr><?php endif; ?>
    </tbody></table></div></section>
    <?php if (in_array($module['key'], ['micu','nicu'], true) && str_contains($currentPage, 'discharge')): ?>
        <div class="critical-discharge-modal" id="critical-discharge-modal" aria-hidden="true">
            <div class="critical-discharge-card" role="dialog" aria-modal="true">
                <h3>Confirm <?php echo h($module['short']); ?> Discharge</h3>
                <p id="critical-discharge-patient">Select a patient disposition to complete the discharge.</p>
                <form method="POST" class="module-form" id="critical-discharge-form">
                    <input type="hidden" name="discharge_critical_patient" value="1">
                    <input type="hidden" name="record_id" id="critical-discharge-record-id">
                    <div class="form-row">
                        <div class="form-group"><label>Disposition</label><select name="disposition" required><option value="DISCHARGED">DISCHARGED</option><option value="HAMA">HAMA</option><option value="ABSCONDED">ABSCONDED</option><option value="EXPIRED">EXPIRED</option></select></div>
                        <div class="form-group"><label>Date/Time</label><input type="datetime-local" name="discharge_date_time" value="<?php echo h(date('Y-m-d\TH:i')); ?>" required></div>
                    </div>
                    <div class="form-group"><label>Remarks</label><textarea name="remarks" placeholder="Optional clinical notes"></textarea></div>
                    <div class="critical-discharge-actions">
                        <button type="button" class="btn btn-secondary" id="critical-discharge-cancel">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Discharge</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main></div><script src="../public/js/sidebar.js"></script>
<?php if (in_array($module['key'], ['micu','nicu'], true) && str_contains($currentPage, 'discharge')): ?>
<script>
    (() => {
        const modal = document.getElementById('critical-discharge-modal');
        const recordInput = document.getElementById('critical-discharge-record-id');
        const patientText = document.getElementById('critical-discharge-patient');
        const cancelButton = document.getElementById('critical-discharge-cancel');
        const closeModal = () => {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            recordInput.value = '';
        };
        document.querySelectorAll('.critical-discharge-open').forEach((button) => {
            button.addEventListener('click', () => {
                recordInput.value = button.dataset.recordId || '';
                patientText.textContent = `${button.dataset.patientName || 'Patient'} | Bed ${button.dataset.bedNo || 'N/A'}`;
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
            });
        });
        cancelButton.addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
    })();
</script>
<?php endif; ?>
</body></html>
