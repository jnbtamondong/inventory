<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/clinical_modules.php';

requireLogin();
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
ensureClinicalModuleSchema($conn);

$error = '';
$success = '';
consumeFlashMessages($error, $success);

function transOutDepartments(): array {
    return [
        'WARD' => [
            'label' => 'Ward',
            'short' => 'WARD',
            'permission' => ['patient_movements', 'edit'],
            'destinations' => ['OR', 'MICU', 'OTHER FACILITY'],
        ],
        'LR_DR' => [
            'label' => 'LR/DR',
            'short' => 'LR/DR',
            'permission' => ['ldr', 'view', 'LR_DR'],
            'destinations' => ['WARD', 'NICU'],
        ],
        'NICU' => [
            'label' => 'NICU',
            'short' => 'NICU',
            'permission' => ['nicu', 'view', 'NICU'],
            'destinations' => ['WARD'],
        ],
        'ICU' => [
            'label' => 'ICU / MICU',
            'short' => 'MICU',
            'permission' => ['micu', 'view', 'ICU'],
            'destinations' => ['WARD'],
        ],
        'OR' => [
            'label' => 'Operating Room',
            'short' => 'OR',
            'permission' => ['operating_room', 'view', 'OR'],
            'destinations' => ['WARD', 'MICU'],
        ],
    ];
}

function transOutMovementType(string $from, string $to): string {
    if ($to === 'OTHER FACILITY') {
        return 'TRANSFER TO OTHER FACILITY';
    }
    $fromLabel = $from === 'ICU' ? 'MICU' : $from;
    return $fromLabel . ' TO ' . $to;
}

function transOutDepartmentAliases(string $department): array {
    return match ($department) {
        'ICU' => ['ICU', 'MICU'],
        'LR_DR' => ['LR_DR', 'LR/DR'],
        default => [$department],
    };
}

function transOutPatientLabel(array $patient): string {
    $name = trim(($patient['last_name'] ?? '') . ', ' . ($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? ''));
    $id = $patient['permanent_patient_id'] ?: ($patient['patient_code'] ?? '');
    $bed = $patient['bed_no'] ?? ($patient['bed_number'] ?? '');
    return trim($name . ' - ' . $id . ($bed ? ' (' . $bed . ')' : ''));
}

function transOutPatientAge(array $patient): ?int {
    if (($patient['age'] ?? '') === '' || $patient['age'] === null) {
        return null;
    }
    return (int) $patient['age'];
}

function transOutCanTransferInfantToNicu(string $department, array $patient): bool {
    $age = transOutPatientAge($patient);
    return $department === 'WARD' && $age !== null && $age >= 0 && $age <= 1;
}

function transOutWaitingTime(?string $dateTime): string {
    if (!$dateTime) {
        return 'N/A';
    }
    $start = strtotime($dateTime);
    if (!$start) {
        return 'N/A';
    }
    $minutes = max(0, (int) floor((time() - $start) / 60));
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;
    if ($hours > 0) {
        return $hours . ' hr' . ($hours === 1 ? '' : 's') . ' ' . $remainingMinutes . ' min';
    }
    return $remainingMinutes . ' min';
}

function transOutCriticalCapacity(mysqli $conn, string $areaName): int {
    $capacity = clinicalScalar($conn, "SELECT total_bed_capacity FROM bed_capacity_settings WHERE area_name=? AND status='ACTIVE'", [$areaName], 's');
    if ($capacity <= 0) {
        $capacity = clinicalDefaultBedCapacities()[$areaName] ?? 5;
    }
    return max(1, $capacity);
}

function transOutCriticalBedSlots(string $prefix, int $capacity): array {
    $slots = [];
    for ($i = 1; $i <= $capacity; $i++) {
        $slots[] = $prefix . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    }
    return $slots;
}

function transOutAvailableBeds(mysqli $conn, string $department): array {
    if ($department === 'WARD') {
        ensureWardBeds($conn);
        return clinicalRows($conn, "
            SELECT bed_id, bed_number, room_number, bed_type
            FROM beds
            WHERE bed_type='General'
                AND status='available'
                AND patient_id IS NULL
            ORDER BY room_number, bed_number
        ");
    }

    if ($department === 'ICU') {
        $slots = transOutCriticalBedSlots('MICU', transOutCriticalCapacity($conn, 'ICU / MICU'));
        $occupiedRows = clinicalRows($conn, "SELECT bed_no FROM micu_records WHERE status='ACTIVE' AND bed_no IS NOT NULL AND bed_no<>''");
        $occupied = array_column($occupiedRows, 'bed_no');
        return array_map(fn($slot) => ['bed_id' => $slot, 'bed_number' => $slot, 'room_number' => 'MICU', 'bed_type' => 'Critical Care'], array_values(array_diff($slots, $occupied)));
    }

    if ($department === 'NICU') {
        $slots = transOutCriticalBedSlots('NICU', transOutCriticalCapacity($conn, 'NICU'));
        $occupiedRows = clinicalRows($conn, "SELECT bed_no FROM nicu_records WHERE status='ACTIVE' AND bed_no IS NOT NULL AND bed_no<>''");
        $occupied = array_column($occupiedRows, 'bed_no');
        return array_map(fn($slot) => ['bed_id' => $slot, 'bed_number' => $slot, 'room_number' => 'NICU', 'bed_type' => 'Neonatal'], array_values(array_diff($slots, $occupied)));
    }

    if ($department === 'OR') {
        return [['bed_id' => 'OR-HOLD', 'bed_number' => 'OR-HOLD', 'room_number' => 'OR', 'bed_type' => 'Operating Room Holding']];
    }

    if ($department === 'LR_DR') {
        return [['bed_id' => 'LRDR-HOLD', 'bed_number' => 'LRDR-HOLD', 'room_number' => 'LR/DR', 'bed_type' => 'Labor / Delivery']];
    }

    return [];
}

function transOutReleaseFormerDepartment(mysqli $conn, int $patientId, string $department, string $to, string $movementType): void {
    $userId = $_SESSION['user_id'] ?? null;
    $now = date('Y-m-d H:i:s');
    $description = 'Patient transferred out from ' . moduleNameFromDepartment($department) . ' to ' . $to;

    if ($department === 'WARD') {
        $patientRows = clinicalRows($conn, "SELECT current_bed FROM patients WHERE patient_id=? LIMIT 1", [$patientId], 'i');
        $currentBed = (int) ($patientRows[0]['current_bed'] ?? 0);
        if ($currentBed > 0) {
            $releaseStmt = $conn->prepare("UPDATE beds SET status='available', patient_id=NULL WHERE bed_id=?");
            $releaseStmt->bind_param("i", $currentBed);
            $releaseStmt->execute();
        }
        $stmt = $conn->prepare("UPDATE patients SET current_bed=NULL, status='trans_out' WHERE patient_id=?");
        $stmt->bind_param("i", $patientId);
        $stmt->execute();
    } elseif ($department === 'ICU') {
        $stmt = $conn->prepare("UPDATE micu_records SET status='TRANSFERRED', disposition='TRANSFER OUT', transfer_out_department=?, discharge_date_time=? WHERE patient_id=? AND status='ACTIVE'");
        $stmt->bind_param("ssi", $to, $now, $patientId);
        $stmt->execute();
        $patientStmt = $conn->prepare("UPDATE patients SET status='trans_out' WHERE patient_id=?");
        $patientStmt->bind_param("i", $patientId);
        $patientStmt->execute();
    } elseif ($department === 'NICU') {
        $stmt = $conn->prepare("UPDATE nicu_records SET status='TRANSFERRED', disposition='TRANSFER OUT', transfer_out_department=?, discharge_date_time=? WHERE patient_id=? AND status='ACTIVE'");
        $stmt->bind_param("ssi", $to, $now, $patientId);
        $stmt->execute();
        $patientStmt = $conn->prepare("UPDATE patients SET status='trans_out' WHERE patient_id=?");
        $patientStmt->bind_param("i", $patientId);
        $patientStmt->execute();
    } elseif ($department === 'OR') {
        $stmt = $conn->prepare("UPDATE or_schedule SET status='TRANSFERRED', post_op_destination=? WHERE patient_id=? AND status IN ('PENDING','ACCEPTED','IN OR','RECOVERY')");
        $stmt->bind_param("si", $to, $patientId);
        $stmt->execute();
    } elseif ($department === 'LR_DR') {
        $stmt = $conn->prepare("UPDATE delivery_room_records SET mother_disposition='TRANSFER OUT', remarks=CONCAT(COALESCE(remarks,''), IF(COALESCE(remarks,'')='', '', '\n'), ?) WHERE patient_id=? AND COALESCE(mother_disposition, '') NOT IN ('TRANSFER OUT', 'TRANSFERRED')");
        $stmt->bind_param("si", $description, $patientId);
        $stmt->execute();
    }

    $trackStmt = $conn->prepare("INSERT INTO patient_tracking (patient_id, action, description, from_status, to_status, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    $trackStmt->bind_param("issssi", $patientId, $movementType, $description, $department, $to, $userId);
    $trackStmt->execute();
}

function transOutPatients(mysqli $conn, string $department): array {
    if ($department === 'WARD') {
        return clinicalRows($conn, "
            SELECT p.patient_id, p.patient_code, p.permanent_patient_id, p.first_name, p.middle_name, p.last_name, p.age, p.gender, p.status, p.ward_department, b.bed_number
            FROM patients p
            LEFT JOIN beds b ON p.current_bed = b.bed_id
            WHERE p.status='admit_inpatient'
                AND (
                    b.bed_type = 'General'
                    OR EXISTS (
                        SELECT 1
                        FROM patient_tracking ward_pt
                        WHERE ward_pt.patient_id = p.patient_id
                            AND ward_pt.action = 'Ward Admission Seeded'
                    )
                )
            ORDER BY p.ward_department, p.last_name, p.first_name
        ");
    }

    if ($department === 'LR_DR') {
        return clinicalRows($conn, "
            SELECT p.patient_id, p.patient_code, p.permanent_patient_id, p.first_name, p.middle_name, p.last_name, p.age, p.gender, p.status, p.ward_department,
                CONCAT('LDR-', r.id) AS bed_no
            FROM delivery_room_records r
            INNER JOIN patients p ON p.patient_id = r.patient_id
            WHERE COALESCE(r.mother_disposition, '') NOT IN ('TRANSFER OUT', 'TRANSFERRED')
            ORDER BY COALESCE(r.date_time_of_delivery, r.created_at) DESC, p.last_name, p.first_name
            LIMIT 250
        ");
    }

    if ($department === 'NICU') {
        return clinicalRows($conn, "
            SELECT p.patient_id, p.patient_code, p.permanent_patient_id, p.first_name, p.middle_name, p.last_name, p.age, p.gender, p.status, p.ward_department, r.bed_no
            FROM nicu_records r
            INNER JOIN patients p ON p.patient_id = r.patient_id
            WHERE r.status='ACTIVE'
            ORDER BY r.bed_no, r.admission_date_time DESC, p.last_name, p.first_name
        ");
    }

    if ($department === 'ICU') {
        return clinicalRows($conn, "
            SELECT p.patient_id, p.patient_code, p.permanent_patient_id, p.first_name, p.middle_name, p.last_name, p.age, p.gender, p.status, p.ward_department, r.bed_no
            FROM micu_records r
            INNER JOIN patients p ON p.patient_id = r.patient_id
            WHERE r.status='ACTIVE'
            ORDER BY r.bed_no, r.admission_date_time DESC, p.last_name, p.first_name
        ");
    }

    if ($department === 'OR') {
        return clinicalRows($conn, "
            SELECT p.patient_id, p.patient_code, p.permanent_patient_id, p.first_name, p.middle_name, p.last_name, p.age, p.gender, p.status, p.ward_department,
                o.procedure_name AS bed_no
            FROM or_schedule o
            INNER JOIN patients p ON p.patient_id = o.patient_id
            WHERE o.status IN ('PENDING','ACCEPTED','IN OR','RECOVERY')
            ORDER BY o.schedule_date_time DESC, p.last_name, p.first_name
        ");
    }

    return [];
}

$departments = transOutDepartments();
$selectedDepartment = canonicalDepartment($_GET['department'] ?? ($_POST['department'] ?? ($_GET['module'] ?? 'WARD')));
if ($selectedDepartment === 'MICU') {
    $selectedDepartment = 'ICU';
}
if (!isset($departments[$selectedDepartment])) {
    $selectedDepartment = 'WARD';
}

$departmentConfig = $departments[$selectedDepartment];
[$permissionModule, $permissionAction, $permissionDepartment] = array_pad($departmentConfig['permission'], 3, null);
if ($permissionModule && !hasPermission($permissionModule, $permissionAction ?: 'view', $permissionDepartment)) {
    logSecurityEvent($conn, 'Restricted Access Attempt', 'Trans out blocked: ' . $selectedDepartment);
    header('Location: system_menu.php');
    exit();
}
$canSubmitTransOut = hasPermission('patient_movements', 'edit')
    || ($permissionModule && hasPermission($permissionModule, 'edit', $permissionDepartment));
$canManageTransIn = $canSubmitTransOut;

$patients = transOutPatients($conn, $selectedDepartment);
$departmentAliases = transOutDepartmentAliases($selectedDepartment);
$departmentPlaceholders = implode(',', array_fill(0, count($departmentAliases), '?'));
$departmentAliasParams = array_merge($departmentAliases, $departmentAliases);
$departmentAliasTypes = str_repeat('s', count($departmentAliasParams));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['queue_action']) || isset($_POST['receive_transfer']))) {
    requireCsrfToken();
    try {
        if (isset($_POST['receive_transfer'])) {
            throw new DomainException('Continue in Transfers & handover to record transport, verify the receiving unit, and confirm arrival.');
        }
        $action = (string)($_POST['queue_action'] ?? '');
        if (!in_array($action, ['accept','decline'], true)) throw new DomainException('Invalid transfer action.');
        nisTransitionMovement($conn, (int)($_POST['movement_id'] ?? 0), $action === 'accept' ? 'ACCEPTED' : 'REJECTED', sanitizeText($_POST['decision_note'] ?? '', 500));
        setFlashMessage('success', 'Receiving-unit decision recorded.');
    } catch (DomainException $e) { setFlashMessage('error', $e->getMessage()); }
    catch (Throwable $e) { setFlashMessage('error', 'Unable to update transfer. Refresh and retry.'); }
    redirectAfterPost('movement_history.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_trans_out'])) {
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $to = sanitizeText($_POST['to_department'] ?? '', 60);
    $movementType = transOutMovementType($selectedDepartment, $to);
    $reason = sanitizeText($_POST['reason'] ?? '', 1000);
    $remarks = sanitizeText($_POST['remarks'] ?? '', 2000);
    $allowedPatientIds = array_map(fn($patient) => (int) $patient['patient_id'], $patients);
    $selectedPatient = null;
    foreach ($patients as $patient) {
        if ((int) $patient['patient_id'] === $patientId) {
            $selectedPatient = $patient;
            break;
        }
    }
    $allowedDestinations = $departmentConfig['destinations'];
    if ($selectedPatient && transOutCanTransferInfantToNicu($selectedDepartment, $selectedPatient)) {
        $allowedDestinations[] = 'NICU';
    }
    $allowedDestinations = array_values(array_unique($allowedDestinations));

    if (!$canSubmitTransOut) {
        $error = 'You do not have permission to submit trans out requests.';
    } elseif ($patientId <= 0 || !in_array($patientId, $allowedPatientIds, true)) {
        $error = 'Please select an active patient from ' . $departmentConfig['label'] . '.';
    } elseif (!in_array($to, $allowedDestinations, true) || !in_array($movementType, clinicalMovementTypes(), true)) {
        $error = 'Please select a valid destination.';
    } else {
        $encounterId = '';
        $encounterId = (string) ($selectedPatient['patient_code'] ?? '');

        $conn->begin_transaction();
        try {
            if (!clinicalCreateMovementSafe($conn, $patientId, $encounterId, $selectedDepartment, $to, $movementType, $reason, $remarks)) {
                throw new RuntimeException('Unable to record trans out request.');
            }
            if (!in_array(nisDepartment($to), ['ER','OPD','WARD','WARD_2F','WARD_3F','OR','LR_DR','ICU','NICU'], true)) {
                transOutReleaseFormerDepartment($conn, $patientId, $selectedDepartment, $to, $movementType);
            }
            $conn->commit();
            $success = 'Trans out request recorded. Internal transfers retain the sending location until receiving acknowledgment.';
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = $exception->getMessage();
        }
    }

    if ($error) { setFlashMessage('error', $error); }
    if ($success) { setFlashMessage('success', $success); }
    redirectAfterPost('trans_out_patient.php?department=' . rawurlencode($selectedDepartment));
}

$recentRows = clinicalRows($conn, "
    SELECT pm.*, p.permanent_patient_id, p.patient_code,
        CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name,
        requester.username AS requested_by_name
    FROM patient_movements pm
    INNER JOIN patients p ON p.patient_id = pm.patient_id
    LEFT JOIN users requester ON requester.user_id = pm.requested_by
    WHERE pm.source_department IN ($departmentPlaceholders) OR pm.from_department IN ($departmentPlaceholders)
    ORDER BY COALESCE(pm.movement_date_time, pm.created_at) DESC
    LIMIT 12
", $departmentAliasParams, $departmentAliasTypes);

$incomingRows = clinicalRows($conn, "
    SELECT pm.*, p.permanent_patient_id, p.patient_code, p.queue_number, p.arrival_time, p.admission_date, p.created_at AS patient_created_at,
        p.age, p.gender, p.status AS patient_status, p.ward_department, p.final_diagnosis, p.specific_diagnosis,
        t.triage_level, t.chief_complaint,
        CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name,
        requester.username AS requested_by_name
    FROM patient_movements pm
    INNER JOIN patients p ON p.patient_id = pm.patient_id
    LEFT JOIN triage t ON t.patient_id = p.patient_id
    LEFT JOIN users requester ON requester.user_id = pm.requested_by
    WHERE (pm.destination_department IN ($departmentPlaceholders) OR pm.to_department IN ($departmentPlaceholders))
        AND pm.status IN ('PENDING', 'ACCEPTED', 'IN TRANSIT', 'ARRIVED')
    ORDER BY FIELD(pm.status, 'PENDING', 'ACCEPTED', 'IN TRANSIT', 'ARRIVED'), COALESCE(pm.movement_date_time, pm.created_at) DESC
    LIMIT 12
", $departmentAliasParams, $departmentAliasTypes);

$availableBeds = transOutAvailableBeds($conn, $selectedDepartment);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trans In / Trans Out Patient - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .transout-page { max-width: 1440px; }
        .transout-hero {
            background: #fff;
            border: 1px solid #dfe8f2;
            border-radius: 8px;
            box-shadow: 0 10px 26px rgba(30,58,95,.07);
            padding: 20px;
            margin-bottom: 16px;
        }
        .transout-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            flex-wrap: wrap;
        }
        .transout-title-row h2 { margin: 0 0 6px; color: #0B3C5D; letter-spacing: 0; }
        .transout-unit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #cfe0f1;
            border-radius: 999px;
            padding: 8px 12px;
            background: #f4f8fc;
            color: #0B3C5D;
            font-weight: 800;
            white-space: nowrap;
        }
        .transout-grid {
            display: grid;
            grid-template-columns: minmax(420px, 0.9fr) minmax(560px, 1.1fr);
            gap: 18px;
            align-items: start;
        }
        .transout-side { display: grid; gap: 18px; }
        .transout-panel {
            background: #fff;
            border: 1px solid #dfe8f2;
            border-radius: 8px;
            box-shadow: 0 10px 26px rgba(30,58,95,.06);
            padding: 20px;
        }
        .transout-panel h3 { margin: 0 0 4px; color: #0B3C5D; letter-spacing: 0; }
        .transout-panel-subtitle { color: #64748b; font-size: 13px; margin: 0 0 16px; }
        .transout-form { display: grid; gap: 18px; }
        .form-section {
            border: 1px solid #e6edf5;
            border-radius: 8px;
            padding: 16px;
            background: #fbfdff;
        }
        .form-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            color: #0B3C5D;
            font-weight: 900;
        }
        .step-badge {
            width: 28px;
            height: 28px;
            display: inline-grid;
            place-items: center;
            border-radius: 999px;
            background: #0B3C5D;
            color: #fff;
            font-size: 13px;
        }
        .form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .transout-form label { font-weight: 800; color: #0B3C5D; }
        .transout-form select,
        .transout-form input,
        .transout-form textarea {
            width: 100%;
            min-height: 42px;
            border: 1px solid #cfdbe8;
            border-radius: 8px;
            background: #fff;
            color: #0B3C5D;
        }
        .transout-form input[readonly] {
            background: #eef4f9;
            color: #0B3C5D;
            font-weight: 800;
        }
        .transout-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .recent-list { display: grid; gap: 10px; }
        .recent-item {
            border: 1px solid #e6edf5;
            border-radius: 8px;
            padding: 12px;
            background: #fbfdff;
        }
        .recent-item.queue-item {
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            border-color: #dbe7f3;
        }
        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }
        .recent-item strong { color: #0B3C5D; }
        .recent-meta { margin-top: 6px; display: flex; gap: 8px; flex-wrap: wrap; color: #64748b; font-size: 12px; }
        .queue-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e6edf5;
            color: #0B3C5D;
            font-size: 12px;
        }
        .queue-details span { display: grid; gap: 2px; }
        .queue-details small {
            color: #64748b;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .queue-note {
            margin-top: 10px;
            padding: 9px 10px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #e6edf5;
            color: #0B3C5D;
            font-size: 12px;
            line-height: 1.45;
        }
        .queue-actions {
            display: grid;
            gap: 8px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e6edf5;
        }
        .queue-actions label {
            color: #0B3C5D;
            font-size: 12px;
            font-weight: 900;
        }
        .queue-actions select,
        .queue-actions textarea {
            width: 100%;
            border: 1px solid #cfdbe8;
            border-radius: 8px;
            background: #fff;
        }
        .queue-actions select { min-height: 40px; }
        .queue-actions textarea {
            min-height: 58px;
            resize: vertical;
        }
        .queue-action-buttons { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        .queue-receive-box {
            margin-top: 10px;
            border: 1px solid #cfe0f1;
            background: #f4f8fc;
            border-radius: 8px;
            padding: 12px;
        }
        .queue-receive-title {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            color: #0B3C5D;
            font-weight: 900;
        }
        .queue-empty-beds {
            color: #9A5B10;
            background: #fff7e6;
            border: 1px solid #f1d49a;
            border-radius: 8px;
            padding: 10px;
            font-size: 12px;
            font-weight: 800;
        }
        .queue-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 3px 8px;
            background: #eef5ff;
            color: #0B3C5D;
            font-size: 11px;
            font-weight: 900;
        }
        .transfer-queue-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
            font-size: 12px;
        }
        .transfer-queue-table th {
            text-align: left;
            padding: 10px 12px;
            color: #0B3C5D;
            background: #fff;
            border-bottom: 1px solid #dfe8f2;
            font-size: 11px;
        }
        .transfer-queue-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0dcdc;
            vertical-align: middle;
            color: #0B3C5D;
        }
        .transfer-queue-table tbody tr { background: #f7e2e2; }
        .transfer-queue-table tbody tr:nth-child(even) { background: #fdeaea; }
        .transfer-queue-table tbody tr.triage-green { border-left: 3px solid #328CC1; }
        .transfer-queue-table tbody tr.triage-yellow,
        .transfer-queue-table tbody tr.triage-urgent { border-left: 3px solid #f59e0b; }
        .transfer-queue-table tbody tr.triage-red,
        .transfer-queue-table tbody tr.triage-emergency { border-left: 3px solid #ef4444; }
        .transfer-queue-table tbody tr.triage-black { border-left: 3px solid #111827; }
        .transfer-queue-name { display: grid; gap: 2px; }
        .transfer-queue-name small { color: #64748b; }
        .transfer-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            min-width: 230px;
        }
        .transfer-actions .btn { white-space: nowrap; }
        .transfer-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, .48);
        }
        .transfer-modal-backdrop.open { display: flex; }
        .transfer-modal {
            width: min(560px, 100%);
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .28);
            border: 1px solid #dbe7f3;
        }
        .transfer-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 20px;
            border-bottom: 1px solid #e6edf5;
        }
        .transfer-modal-header h3 { margin: 0; color: #0B3C5D; }
        .transfer-modal-header p { margin: 4px 0 0; color: #64748b; font-size: 13px; }
        .transfer-modal-close {
            border: 0;
            background: #eef4f9;
            color: #0B3C5D;
            border-radius: 999px;
            width: 34px;
            height: 34px;
            font-weight: 900;
            cursor: pointer;
        }
        .transfer-modal-form {
            display: grid;
            gap: 14px;
            padding: 20px;
        }
        .transfer-modal-form label {
            color: #0B3C5D;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .transfer-modal-form select,
        .transfer-modal-form textarea {
            width: 100%;
            border: 1px solid #cfdbe8;
            border-radius: 8px;
            background: #fff;
            color: #0B3C5D;
        }
        .transfer-modal-form select { min-height: 42px; }
        .transfer-modal-form textarea {
            min-height: 94px;
            resize: vertical;
        }
        .transfer-modal-summary {
            display: grid;
            gap: 4px;
            padding: 12px;
            border-radius: 8px;
            background: #f4f8fc;
            border: 1px solid #dbe7f3;
            color: #0B3C5D;
            font-size: 13px;
        }
        .transfer-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
            padding-top: 4px;
        }
        .queue-note-row {
            color: #64748b;
            font-size: 12px;
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .badge-triage {
            display: inline-flex;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 10px;
            font-weight: 900;
            background: #e5e7eb;
            color: #374151;
        }
        .badge-triage.yellow,
        .badge-triage.urgent { background: #fde047; color: #713f12; }
        .badge-triage.green,
        .badge-triage.non-urgent { background: #328CC1; color: #fff; }
        .badge-triage.red,
        .badge-triage.emergency { background: #ef4444; color: #fff; }
        .badge-triage.black { background: #111827; color: #fff; }
        .wait-time { font-weight: 900; }
        @media (max-width: 1180px) { .transout-grid, .form-row, .queue-details { grid-template-columns: 1fr; } }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
<nav class="navbar">
    <div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div>
    <div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?></span></div>
</nav>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    <main class="main-content transout-page">
        <section class="transout-hero">
            <div class="transout-title-row">
                <div>
                    <h2>Trans In / Trans Out Patient</h2>
                    <p class="compact-meta">Create outgoing transfer requests and monitor incoming patients queued to this department.</p>
                </div>
                <span class="transout-unit"><?php echo h($departmentConfig['label']); ?></span>
            </div>
        </section>

        <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>

        <section class="transout-grid">
            <div class="transout-panel">
                <h3><?php echo h($departmentConfig['label']); ?> Transfer Request</h3>
                <p class="transout-panel-subtitle">Select the patient, choose the destination, then record the request for receiving-unit action.</p>
                <form method="POST" class="transout-form">
                    <input type="hidden" name="create_trans_out" value="1">
                    <input type="hidden" name="department" value="<?php echo h($selectedDepartment); ?>">
                    <div class="form-section">
                        <div class="form-section-title"><span class="step-badge">1</span> Patient</div>
                        <div class="form-group">
                            <label>Patient for Transfer</label>
                            <select name="patient_id" id="transfer-patient" required>
                                <option value="">Select active patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo (int) $patient['patient_id']; ?>" data-age="<?php echo h((string) ($patient['age'] ?? '')); ?>">
                                        <?php echo h(transOutPatientLabel($patient)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><span class="step-badge">2</span> Transfer Route</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>From</label>
                                <input value="<?php echo h($departmentConfig['short']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>To</label>
                                <select name="to_department" id="to-department" required>
                                    <option value="">Select destination</option>
                                    <?php foreach ($departmentConfig['destinations'] as $destination): ?>
                                        <option value="<?php echo h($destination); ?>"><?php echo h($destination); ?></option>
                                    <?php endforeach; ?>
                                    <?php if ($selectedDepartment === 'WARD' && !in_array('NICU', $departmentConfig['destinations'], true)): ?>
                                        <option value="NICU" data-infant-only="1" hidden disabled>NICU</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><span class="step-badge">3</span> Clinical Notes</div>
                        <div class="form-group"><label>Reason</label><input name="reason" placeholder="Reason for transfer"></div>
                        <div class="form-group"><label>Remarks</label><textarea name="remarks" rows="4" placeholder="Receiving instructions, precautions, or endorsement notes"></textarea></div>
                    </div>

                    <div class="transout-actions">
                        <a class="btn btn-secondary" href="movement_history.php?department=<?php echo h(rawurlencode($selectedDepartment)); ?>">Movement History</a>
                        <button class="btn btn-primary" type="submit">Record Trans Out</button>
                    </div>
                    <?php if (!$canSubmitTransOut): ?><p class="compact-meta">You can view this form, but your account cannot submit trans out requests.</p><?php endif; ?>
                </form>
            </div>

            <aside class="transout-side">
                <div class="transout-panel">
                    <h3>Trans In Queue</h3>
                    <p class="transout-panel-subtitle">Patients currently transferring to <?php echo h($departmentConfig['short']); ?>.</p>
                    <div class="table-responsive">
                        <table class="transfer-queue-table">
                            <thead>
                                <tr>
                                    <th>Patient Name</th>
                                    <th>Age</th>
                                    <th>Sex</th>
                                    <th>Admission</th>
                                    <th>Diagnosis</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incomingRows as $row): ?>
                                    <?php
                                        $triageClass = strtolower((string) ($row['triage_level'] ?: 'none'));
                                        $admissionDate = !empty($row['admission_date']) ? date('M d, Y', strtotime($row['admission_date'])) : 'N/A';
                                        $diagnosis = $row['specific_diagnosis'] ?: ($row['final_diagnosis'] ?: ($row['chief_complaint'] ?: 'N/A'));
                                    ?>
                                    <tr class="triage-<?php echo h($triageClass); ?>">
                                        <td>
                                            <span class="transfer-queue-name">
                                                <strong><?php echo h($row['patient_name']); ?></strong>
                                                <small><?php echo h($row['permanent_patient_id'] ?: $row['patient_code']); ?></small>
                                            </span>
                                        </td>
                                        <td><?php echo h(($row['age'] === null || $row['age'] === '') ? 'N/A' : (string) $row['age']); ?></td>
                                        <td><?php echo h($row['gender'] ?: 'N/A'); ?></td>
                                        <td><?php echo h($admissionDate); ?></td>
                                        <td><?php echo h($diagnosis); ?></td>
                                        <td>
                                            <div class="transfer-actions">
                                                <span class="queue-status"><?php echo h($row['status'] === 'ACCEPTED' ? 'Accepted' : ucwords(strtolower($row['status']))); ?></span>
                                                <a class="btn btn-sm btn-info" href="patient_detail.php?id=<?php echo (int) $row['patient_id']; ?>">View</a>
                                                <?php if ($row['status'] === 'PENDING' && $canManageTransIn): ?>
                                                    <button class="btn btn-success btn-sm transfer-open-modal" type="button" data-mode="accept" data-movement-id="<?php echo (int) $row['id']; ?>" data-patient="<?php echo h($row['patient_name']); ?>" data-route="<?php echo h(($row['source_department'] ?: $row['from_department']) . ' to ' . ($row['destination_department'] ?: $row['to_department'])); ?>">Accept</button>
                                                    <button class="btn btn-danger btn-sm transfer-open-modal" type="button" data-mode="decline" data-movement-id="<?php echo (int) $row['id']; ?>" data-patient="<?php echo h($row['patient_name']); ?>" data-route="<?php echo h(($row['source_department'] ?: $row['from_department']) . ' to ' . ($row['destination_department'] ?: $row['to_department'])); ?>">Decline</button>
                                                <?php elseif ($row['status'] === 'ACCEPTED' && $canManageTransIn): ?>
                                                    <?php if (!empty($availableBeds)): ?>
                                                        <button class="btn btn-primary btn-sm transfer-open-modal" type="button" data-mode="receive" data-movement-id="<?php echo (int) $row['id']; ?>" data-patient="<?php echo h($row['patient_name']); ?>" data-route="<?php echo h(($row['source_department'] ?: $row['from_department']) . ' to ' . ($row['destination_department'] ?: $row['to_department'])); ?>">Assign Bed / Receive</button>
                                                    <?php else: ?>
                                                        <span class="compact-meta">No bed available</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($incomingRows)): ?><tr><td colspan="6" class="text-center">No patients queued for transfer in.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="transout-panel">
                    <h3>Recent <?php echo h($departmentConfig['short']); ?> Trans Out</h3>
                    <p class="transout-panel-subtitle">Latest outgoing movement records from this department.</p>
                    <div class="recent-list">
                        <?php foreach ($recentRows as $row): ?>
                            <div class="recent-item">
                                <strong><?php echo h($row['patient_name']); ?></strong>
                                <div class="recent-meta">
                                    <span><?php echo h($row['permanent_patient_id'] ?: $row['patient_code']); ?></span>
                                    <span>To <?php echo h($row['destination_department'] ?: $row['to_department']); ?></span>
                                    <span><?php echo h($row['status']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($recentRows)): ?><div class="empty-state">No recent trans out records.</div><?php endif; ?>
                    </div>
                </div>
            </aside>
        </section>
    </main>
</div>
<div class="transfer-modal-backdrop" id="transfer-modal" aria-hidden="true">
    <div class="transfer-modal" role="dialog" aria-modal="true" aria-labelledby="transfer-modal-title">
        <div class="transfer-modal-header">
            <div>
                <h3 id="transfer-modal-title">Receive Patient</h3>
                <p id="transfer-modal-subtitle">Review the transfer action before submitting.</p>
            </div>
            <button class="transfer-modal-close" type="button" data-close-transfer-modal aria-label="Close transfer form">X</button>
        </div>
        <form method="POST" class="transfer-modal-form" id="transfer-modal-form">
            <input type="hidden" name="movement_id" id="modal-movement-id">
            <input type="hidden" name="queue_action" id="modal-queue-action" disabled>
            <input type="hidden" name="receive_transfer" id="modal-receive-transfer" value="1" disabled>
            <div class="transfer-modal-summary">
                <strong id="modal-patient-name">Patient</strong>
                <span id="modal-route">Transfer route</span>
            </div>
            <div id="modal-bed-group">
                <label for="modal-bed-value">Available Bed / Area</label>
                <select name="bed_value" id="modal-bed-value" disabled>
                    <option value="">Choose available bed</option>
                    <?php foreach ($availableBeds as $bed): ?>
                        <option value="<?php echo h((string) $bed['bed_id']); ?>">
                            <?php echo h($bed['bed_number'] . ' - ' . $bed['room_number'] . ' (' . $bed['bed_type'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="modal-note-group">
                <label for="modal-note">Note</label>
                <textarea id="modal-note" placeholder="Optional note"></textarea>
            </div>
            <div class="transfer-modal-actions">
                <button class="btn btn-secondary" type="button" data-close-transfer-modal>Cancel</button>
                <button class="btn btn-primary" type="submit" id="modal-submit-button">Submit</button>
            </div>
        </form>
    </div>
</div>
<script src="../public/js/sidebar.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const patientSelect = document.getElementById('transfer-patient');
    const destinationSelect = document.getElementById('to-department');
    const infantNicuOption = destinationSelect ? destinationSelect.querySelector('option[data-infant-only="1"]') : null;

    function refreshInfantDestination() {
        if (!patientSelect || !infantNicuOption) return;
        const selectedOption = patientSelect.options[patientSelect.selectedIndex];
        const age = Number.parseInt(selectedOption ? selectedOption.dataset.age : '', 10);
        const canUseNicu = Number.isInteger(age) && age >= 0 && age <= 1;
        infantNicuOption.hidden = !canUseNicu;
        infantNicuOption.disabled = !canUseNicu;
        if (!canUseNicu && destinationSelect.value === 'NICU') {
            destinationSelect.value = '';
        }
    }

    patientSelect?.addEventListener('change', refreshInfantDestination);
    refreshInfantDestination();

    const modal = document.getElementById('transfer-modal');
    const modalForm = document.getElementById('transfer-modal-form');
    const modalTitle = document.getElementById('transfer-modal-title');
    const modalSubtitle = document.getElementById('transfer-modal-subtitle');
    const modalMovementId = document.getElementById('modal-movement-id');
    const modalQueueAction = document.getElementById('modal-queue-action');
    const modalReceiveTransfer = document.getElementById('modal-receive-transfer');
    const modalPatientName = document.getElementById('modal-patient-name');
    const modalRoute = document.getElementById('modal-route');
    const modalBedGroup = document.getElementById('modal-bed-group');
    const modalBedValue = document.getElementById('modal-bed-value');
    const modalNote = document.getElementById('modal-note');
    const modalSubmitButton = document.getElementById('modal-submit-button');

    function openTransferModal(button) {
        const mode = button.dataset.mode;
        const isReceive = mode === 'receive';
        if (isReceive) { window.location.href = 'movement_history.php'; return; }
        const isDecline = mode === 'decline';
        modalMovementId.value = button.dataset.movementId || '';
        modalPatientName.textContent = button.dataset.patient || 'Patient';
        modalRoute.textContent = button.dataset.route || 'Transfer route';
        modalQueueAction.disabled = isReceive;
        modalReceiveTransfer.disabled = !isReceive;
        modalBedValue.disabled = !isReceive;
        modalBedValue.required = isReceive;
        modalBedGroup.style.display = isReceive ? 'grid' : 'none';
        modalNote.name = isReceive ? 'receive_note' : 'decision_note';
        modalNote.value = '';
        modalNote.placeholder = isReceive ? 'Receiving note or endorsement' : 'Optional decision note';
        modalQueueAction.value = isReceive ? '' : mode;
        modalTitle.textContent = isReceive ? 'Assign Bed / Receive Patient' : (isDecline ? 'Decline Transfer' : 'Accept Transfer');
        modalSubtitle.textContent = isReceive
            ? 'Choose an available bed or receiving area before completing the transfer.'
            : (isDecline ? 'Declining will return the patient to the transferring department.' : 'Accept this patient into the receiving queue.');
        modalSubmitButton.textContent = isReceive ? 'Assign Bed / Receive' : (isDecline ? 'Decline Patient' : 'Accept Patient');
        modalSubmitButton.className = isDecline ? 'btn btn-danger' : (isReceive ? 'btn btn-primary' : 'btn btn-success');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => (isReceive ? modalBedValue : modalNote).focus(), 0);
    }

    function closeTransferModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        modalForm.reset();
    }

    document.querySelectorAll('.transfer-open-modal').forEach((button) => {
        button.addEventListener('click', () => openTransferModal(button));
    });
    document.querySelectorAll('[data-close-transfer-modal]').forEach((button) => {
        button.addEventListener('click', closeTransferModal);
    });
    modal?.addEventListener('click', function (event) {
        if (event.target === modal) closeTransferModal();
    });
    modalForm?.addEventListener('submit', function (event) {
        if (!modalReceiveTransfer.disabled) {
            if (!confirm('Receive this patient and assign the selected bed/area?')) {
                event.preventDefault();
            }
            return;
        }
        if (modalQueueAction.value === 'decline' && !confirm('Decline this transfer and return the patient to the transferring department?')) {
            event.preventDefault();
        }
    });
});
</script>
</body>
</html>
