<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/patient-registration.php';
require_once '../includes/clinical_modules.php';

requireLogin();
require_once '../includes/nis-tracker.php';
$patient_id = $_GET['id'] ?? null;
if ($patient_id && !nisTrackerCanOpenChart($conn,(int)$patient_id)) {
    http_response_code(403);
    exit('Clinical record access is not permitted for this department. Use Patient Tracker for authorized movement information.');
}
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
ensureErRecordTriageSchema($conn);
ensureClinicalModuleSchema($conn);
$currentWaitSql = patientCurrentWaitMinutesSql('p');

$patient_id = $_GET['id'] ?? null;

if (!$patient_id) {
    header('Location: patients.php');
    exit();
}

// Get patient details with all related info
$patient_query = "SELECT 
    p.*,
    CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) as patient_name,
    b.bed_number,
    b.room_number,
    COALESCE(p.province_name, pr.province_name) as display_province_name,
    COALESCE(p.municipality_name, m.municipality_name) as display_municipality_name,
    COALESCE(p.barangay_name, br.barangay_name) as display_barangay_name,
    u.first_name as doctor_name,
    u2.first_name as nurse_name,
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
    $currentWaitSql as wait_time
FROM patients p
LEFT JOIN beds b ON p.current_bed = b.bed_id
LEFT JOIN provinces pr ON p.province_id = pr.province_id
LEFT JOIN municipalities m ON p.municipality_id = m.municipality_id
LEFT JOIN barangays br ON p.barangay_id = br.barangay_id
LEFT JOIN users u ON p.current_doctor = u.user_id
LEFT JOIN users u2 ON p.assigned_nurse = u2.user_id
LEFT JOIN triage t ON p.patient_id = t.patient_id
WHERE p.patient_id = ?";

$stmt = $conn->prepare($patient_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    header('Location: patients.php');
    exit();
}

// Opening a chart must not infer or overwrite identity details from names.

// Get patient tracking history
$tracking_query = "
    SELECT
        pt.*,
        CONCAT(u.first_name, ' ', u.last_name) as updated_by_name,
        u.role as updated_by_role
    FROM patient_tracking pt
    LEFT JOIN users u ON pt.user_id = u.user_id
    WHERE pt.patient_id = ?
    ORDER BY pt.created_at DESC
    LIMIT 20
";
$stmt = $conn->prepare($tracking_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$tracking_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$movement_timeline_query = "
    SELECT
        pm.*,
        requester.username AS requested_by_name,
        accepter.username AS accepted_by_name
    FROM patient_movements pm
    LEFT JOIN users requester ON requester.user_id = pm.requested_by
    LEFT JOIN users accepter ON accepter.user_id = pm.accepted_by
    WHERE pm.patient_id = ?
    ORDER BY COALESCE(pm.movement_date_time, pm.created_at) ASC
";
$stmt = $conn->prepare($movement_timeline_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$movement_timeline = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($movement_timeline as $movement) {
    $fromDepartment = $movement['from_department'] ?: ($movement['source_department'] ?: 'N/A');
    $toDepartment = $movement['to_department'] ?: ($movement['destination_department'] ?: 'N/A');
    $descriptionParts = [];
    if (!empty($movement['reason'])) {
        $descriptionParts[] = 'Reason: ' . $movement['reason'];
    }
    if (!empty($movement['remarks'])) {
        $descriptionParts[] = 'Remarks: ' . $movement['remarks'];
    }
    $description = $descriptionParts ? implode("\n", $descriptionParts) : 'Patient movement recorded.';
    $tracking_history[] = [
        'action' => $movement['movement_type'] ?: 'Patient Movement',
        'description' => $description,
        'from_status' => $fromDepartment,
        'to_status' => $toDepartment,
        'wait_time_minutes' => 0,
        'user_id' => $movement['requested_by'] ?? null,
        'updated_by_name' => $movement['requested_by_name'] ?? '',
        'updated_by_role' => '',
        'created_at' => $movement['movement_date_time'] ?: $movement['created_at'],
        'tracking_type' => 'movement',
        'movement_status' => $movement['status'] ?? '',
        'accepted_by_name' => $movement['accepted_by_name'] ?? '',
    ];
}

usort($tracking_history, function ($a, $b) {
    return strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01');
});

// Total waiting time from triage/arrival until discharge. If not discharged yet, show current elapsed time.
$total_wait_query = "
    SELECT
        COALESCE(TIMESTAMP(p.triage_date, p.triage_time), p.arrival_time) as triage_started_at,
        COALESCE((
            SELECT COALESCE(pt.status_ended_at, pt.created_at)
            FROM patient_tracking pt
            WHERE pt.patient_id = p.patient_id
                AND pt.action = 'Status Updated'
                AND pt.to_status IN ('discharge', 'discharged')
            ORDER BY COALESCE(pt.status_ended_at, pt.created_at) ASC
            LIMIT 1
        ), CASE WHEN p.status IN ('discharge', 'discharged') THEN p.updated_at ELSE NULL END) as discharged_at,
        GREATEST(TIMESTAMPDIFF(
            MINUTE,
            COALESCE(TIMESTAMP(p.triage_date, p.triage_time), p.arrival_time),
            COALESCE((
                SELECT COALESCE(pt.status_ended_at, pt.created_at)
                FROM patient_tracking pt
                WHERE pt.patient_id = p.patient_id
                    AND pt.action = 'Status Updated'
                    AND pt.to_status IN ('discharge', 'discharged')
                ORDER BY COALESCE(pt.status_ended_at, pt.created_at) ASC
                LIMIT 1
            ), CASE WHEN p.status IN ('discharge', 'discharged') THEN p.updated_at ELSE NULL END, NOW())
        ), 0) as total_wait_minutes
    FROM patients p
    WHERE p.patient_id = ?
";
$stmt = $conn->prepare($total_wait_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$total_wait = $stmt->get_result()->fetch_assoc();

// Total time from confirmed admission/bed assignment until discharge.
$admission_duration_query = "
    SELECT
        admission_started_at,
        admission_discharged_at,
        CASE
            WHEN admission_started_at IS NULL THEN NULL
            ELSE GREATEST(TIMESTAMPDIFF(MINUTE, admission_started_at, COALESCE(admission_discharged_at, NOW())), 0)
        END as admission_total_minutes
    FROM (
        SELECT
            COALESCE((
                SELECT COALESCE(pt.status_ended_at, pt.created_at)
                FROM patient_tracking pt
                WHERE pt.patient_id = p.patient_id
                    AND pt.action = 'Status Updated'
                    AND pt.to_status = 'admit_inpatient'
                ORDER BY COALESCE(pt.status_ended_at, pt.created_at) ASC
                LIMIT 1
            ), (
                SELECT pt.created_at
                FROM patient_tracking pt
                WHERE pt.patient_id = p.patient_id
                    AND pt.action IN ('Bed Assigned', 'Bed Transferred')
                ORDER BY pt.created_at ASC
                LIMIT 1
            )) as admission_started_at,
            COALESCE((
                SELECT COALESCE(pt.status_ended_at, pt.created_at)
                FROM patient_tracking pt
                WHERE pt.patient_id = p.patient_id
                    AND pt.action = 'Status Updated'
                    AND pt.to_status IN ('discharge', 'discharged')
                ORDER BY COALESCE(pt.status_ended_at, pt.created_at) DESC
                LIMIT 1
            ), CASE WHEN p.status IN ('discharge', 'discharged') THEN p.updated_at ELSE NULL END) as admission_discharged_at
        FROM patients p
        WHERE p.patient_id = ?
    ) admission_span
";
$stmt = $conn->prepare($admission_duration_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$admission_duration = $stmt->get_result()->fetch_assoc();

$consultation_history_query = "
    SELECT
        COALESCE(status_ended_at, created_at) as consultation_at,
        description
    FROM patient_tracking
    WHERE patient_id = ?
        AND action = 'Status Updated'
        AND to_status = 'consultation'
    ORDER BY COALESCE(status_ended_at, created_at) DESC
";
$stmt = $conn->prepare($consultation_history_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$consultation_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Hospital Number links identities; names alone never link clinical histories.
[$historyScope,$historyValues]=registrationHistoryScope($patient);
$visit_history_query = "
    SELECT
        p.patient_id,
        p.patient_code,
        p.admission_date,
        p.triage_date,
        p.triage_time,
        p.arrival_time,
        p.status,
        p.medical_history,
        p.final_diagnosis,
        p.specific_diagnosis,
        t.chief_complaint,
        COALESCE((
            SELECT COALESCE(pt.status_ended_at, pt.created_at)
            FROM patient_tracking pt
            WHERE pt.patient_id = p.patient_id
                AND pt.action = 'Status Updated'
                AND pt.to_status IN ('discharge', 'discharged', 'trans_in', 'trans_out', 'referred', 'ama', 'hama', 'thoc', 'absconded', 'dama', 'doa', 'death', 'expired', 'er_death')
            ORDER BY COALESCE(pt.status_ended_at, pt.created_at) DESC
            LIMIT 1
        ), CASE WHEN p.status IN ('discharge', 'trans_in', 'trans_out', 'referred', 'ama', 'hama', 'thoc', 'absconded', 'dama', 'doa', 'death', 'expired', 'er_death') THEN p.updated_at ELSE NULL END) as ended_at
    FROM patients p
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    WHERE $historyScope
    ORDER BY COALESCE(p.arrival_time, p.admission_date, p.created_at) DESC
";
$stmt = $conn->prepare($visit_history_query);
$stmt->bind_param(str_repeat('s',count($historyValues)),...$historyValues);
$stmt->execute();
$visit_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$previous_visits = array_values(array_filter($visit_history, function ($visit) use ($patient_id) {
    return (int) $visit['patient_id'] !== (int) $patient_id;
}));

$isOldPatient = !empty($patient['created_at'])
    && !empty($patient['admission_date'])
    && (strtotime($patient['created_at']) < strtotime($patient['admission_date']) || !empty($previous_visits));

$vitals = [];
if (!empty($patient['vital_signs'])) {
    $decodedVitals = json_decode($patient['vital_signs'], true);
    if (is_array($decodedVitals)) {
        $vitals = $decodedVitals;
    }
}
$erVitals = [
    'Temperature (C)' => $vitals['temperature'] ?? '',
    'Blood Pressure' => $vitals['blood_pressure'] ?? '',
    'Heart Rate' => $vitals['heart_rate'] ?? '',
    'Respiratory Rate' => $vitals['respiratory_rate'] ?? '',
    'PR' => $patient['vital_pr'] ?? ($vitals['pr'] ?? ''),
    'Height' => $patient['vital_height'] ?? ($vitals['height'] ?? ''),
    'Weight' => $patient['vital_weight'] ?? ($vitals['weight'] ?? ''),
    'SPO2' => $patient['vital_spo2'] ?? ($vitals['spo2'] ?? ''),
    'CBG' => $patient['vital_cbg'] ?? ($vitals['cbg'] ?? '')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .patient-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        body.patient-detail-page {
            min-height: 100vh;
            overflow-y: auto !important;
        }

        .patient-detail-page .dashboard-container {
            min-height: auto;
            align-items: start;
        }

        .patient-detail-page .main-content {
            min-height: calc(100vh - 70px);
            height: auto;
            max-height: none;
            overflow: visible !important;
            padding-bottom: 96px;
        }

        .tracking-history-list {
            display: grid;
            gap: 10px;
            max-height: none;
            overflow: visible;
        }
        
        .detail-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        
        .detail-card h3 {
            margin-bottom: 15px;
            color: #0B3C5D;
            border-bottom: 2px solid #EAF6FF;
            padding-bottom: 10px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        
        .detail-value {
            color: #333;
        }
        
        .vital-signs {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .tracking-item {
            padding: 12px;
            background: #f5f5f5;
            border-left: 4px solid #EAF6FF;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        
        .tracking-time {
            font-size: 12px;
            color: #999;
        }

        .medical-history-list {
            width: 100%;
            margin-top: 6px;
        }

        .medical-history-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .medical-history-item:last-child {
            border-bottom: none;
        }

        .medical-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(44, 62, 80, 0.55);
            padding: 20px;
            z-index: 999;
        }

        .medical-modal.open {
            display: flex;
        }

        .medical-modal-card {
            width: min(980px, 100%);
            max-height: 88vh;
            overflow-y: auto;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
            padding: 22px;
        }

        .medical-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .visit-history-list {
            display: grid;
            gap: 12px;
        }

        .visit-history-item {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 14px;
            background: #f8fafc;
        }

        .visit-history-meta {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
            margin-top: 4px;
        }

        .medical-summary-card .detail-row {
            display: none;
        }
        
        @media (max-width: 768px) {
            .patient-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui patient-detail-page">
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
            <div style="margin-bottom: 20px;">
                <button type="button" class="btn btn-secondary" onclick="history.back()">← Back</button>
            </div>

            <h2><?php echo htmlspecialchars($patient['patient_name']); ?></h2>

            <!-- Patient Details -->
            <div class="patient-details">
                <!-- Basic Info -->
                <div class="detail-card">
                    <h3>📋 Basic Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Patient ID:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['permanent_patient_id'] ?? ''); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Encounter ID:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['patient_code']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Queue No.:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(formatQueueNumber($patient['queue_number'] ?? null)); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date of Birth:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(formatPatientDate($patient['date_of_birth'] ?? null)); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Gender:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['gender']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Civil Status:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['civil_status'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Religion:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['religion'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Address:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(trim(($patient['house_no_street'] ?? '') . ', ' . ($patient['display_barangay_name'] ?? '') . ', ' . ($patient['display_municipality_name'] ?? '') . ', ' . ($patient['display_province_name'] ?? ''), ', ')); ?></span>
                    </div>
                </div>

                <!-- Current Status -->
                <div class="detail-card">
                    <h3>📍 Current Status</h3>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="badge badge-<?php echo htmlspecialchars($patient['status']); ?>">
                                <?php echo htmlspecialchars(statusLabel($patient['status'])); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Triage Level:</span>
                        <span class="detail-value">
                            <span class="badge badge-<?php echo htmlspecialchars($patient['triage_level'] ?? 'secondary'); ?>">
                                <?php echo htmlspecialchars(triageLevelLabel($patient['triage_level'] ?? '')); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Wait Time:</span>
                            <span class="detail-value"><?php echo htmlspecialchars(formatWaitTime($patient['wait_time'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total Triage to Discharge Time:</span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars(formatWaitTime($total_wait['total_wait_minutes'] ?? 0)); ?>
                            <?php echo empty($total_wait['discharged_at']) ? ' (ongoing)' : ''; ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Arrival Date/Time:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(($patient['triage_date'] ?? 'N/A') . ' ' . substr($patient['triage_time'] ?? '', 0, 5)); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Assigned Bed:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['bed_number'] ?? 'Unassigned'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Assigned Doctor:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['doctor_name'] ?? 'Unassigned'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Assigned Nurse:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['nurse_name'] ?? 'Unassigned'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">ER Record:</span>
                        <span class="detail-value"><?php if(hasPermission('patients','print','ER')):?><a href="er_record.php?id=<?php echo (int) $patient['patient_id']; ?>" class="btn btn-sm btn-info">View / Print ER Record</a> <a href="er_record.php?id=<?=(int)$patient['patient_id']?>&amp;download=pdf" class="btn btn-sm btn-secondary">Download PDF</a><?php else:?>Restricted to authorized ER record access<?php endif;?></span>
                    </div>
                </div>

                <!-- Medical Info -->
                <div class="detail-card medical-summary-card">
                    <h3>⚕️ Medical Information</h3>
                    <p class="tracking-time">Medical details and all patient visits are available in the history popup.</p>
                    <button type="button" class="btn btn-primary" id="open-medical-modal">View Medical / Visit History</button>
                    <div class="detail-row">
                        <span class="detail-label">Chief Complaint:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['chief_complaint'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Final Diagnosis:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['final_diagnosis'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Specific Diagnosis:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['specific_diagnosis'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Medical History:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['medical_history'] ?? 'No history recorded'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Last Visit:</span>
                        <span class="detail-value medical-history-list">
                            <?php if (empty($previous_visits)): ?>
                                No previous visit recorded
                            <?php else: ?>
                                <?php foreach ($previous_visits as $visitIndex => $visit): ?>
                                    <div class="medical-history-item">
                                        <strong><?php echo $visitIndex === 0 ? 'Last Visit' : 'Previous Visit'; ?>:</strong>
                                        <?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($visit['arrival_time'] ?? $visit['admission_date']))); ?>
                                        <br>
                                        <span class="tracking-time">
                                            Encounter ID: <?php echo htmlspecialchars($visit['patient_code']); ?>
                                            | Status: <?php echo htmlspecialchars(statusLabel($visit['status'] ?? '')); ?>
                                            <?php if (!empty($visit['ended_at'])): ?>
                                                | Ended: <?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($visit['ended_at']))); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php if (!empty($visit['final_diagnosis'])): ?>
                                            <br>
                                            <span class="tracking-time">Diagnosis: <?php echo htmlspecialchars($visit['final_diagnosis']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($visit['specific_diagnosis'])): ?>
                                            <br>
                                            <span class="tracking-time">Specific: <?php echo htmlspecialchars($visit['specific_diagnosis']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($isOldPatient || !empty($consultation_history)): ?>
                        <div class="detail-row">
                            <span class="detail-label">Consultation History:</span>
                            <span class="detail-value medical-history-list">
                                <?php if (empty($consultation_history)): ?>
                                    No previous consultation date recorded
                                <?php else: ?>
                                    <?php foreach ($consultation_history as $consultation): ?>
                                        <div class="medical-history-item">
                                            <strong>Date Consulted:</strong>
                                            <?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($consultation['consultation_at']))); ?>
                                            <?php if (!empty($consultation['description'])): ?>
                                                <br>
                                                <span class="tracking-time"><?php echo htmlspecialchars($consultation['description']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ER Record Details -->
                <div class="detail-card">
                    <h3>ER Record Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Medicolegal:</span>
                        <span class="detail-value"><?php echo !empty($patient['medicolegal']) ? 'Yes' : 'No'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">IP:</span>
                        <span class="detail-value"><?php echo !empty($patient['ip']) ? 'Yes' : 'No'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Referred By:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patient['referred_by'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Coordinated:</span>
                        <span class="detail-value"><?php echo !empty($patient['coordinated']) ? 'Yes' : 'No'; ?></span>
                    </div>
                </div>

                <!-- Vital Signs -->
                <?php if (!empty(array_filter($erVitals))): ?>
                    <div class="detail-card">
                        <h3>❤️ Vital Signs</h3>
                        <div class="vital-signs">
                            <?php foreach ($erVitals as $key => $value): ?>
                                <?php if ($value === '' || $value === null) { continue; } ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?php echo htmlspecialchars($key); ?>:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tracking History -->
            <section class="patients-section">
                <h3>📊 Tracking History</h3>
                <?php if (empty($tracking_history)): ?>
                    <p>No tracking history available</p>
                <?php else: ?>
                    <div class="tracking-history-list">
                        <?php foreach ($tracking_history as $event): ?>
                            <?php
                            $actorLabel = 'Updated by';
                            if (in_array($event['action'] ?? '', ['Bed Assigned', 'Bed Transferred'], true)) {
                                $actorLabel = ($event['action'] ?? '') === 'Bed Assigned' ? 'Admitted / bed assigned by' : 'Bed transferred by';
                            } elseif (($event['tracking_type'] ?? '') === 'movement') {
                                $actorLabel = 'Requested by';
                            } elseif (($event['action'] ?? '') === 'Status Updated' && in_array($event['to_status'] ?? '', ['discharge', 'discharged'], true)) {
                                $actorLabel = 'Discharged by';
                            } elseif (($event['action'] ?? '') === 'Status Updated' && ($event['to_status'] ?? '') === 'for_admission') {
                                $actorLabel = 'Marked for admission by';
                            } elseif (($event['action'] ?? '') === 'Status Updated' && ($event['to_status'] ?? '') === 'admit_inpatient') {
                                $actorLabel = 'Admitted by';
                            }
                            ?>
                            <div class="tracking-item">
                                <strong><?php echo htmlspecialchars($event['action']); ?></strong>
                                <p><?php echo nl2br(htmlspecialchars($event['description'] ?? '')); ?></p>
                                <?php if (($event['tracking_type'] ?? '') === 'movement'): ?>
                                    <p>
                                        <strong>Route:</strong>
                                        <?php echo htmlspecialchars($event['from_status'] ?? 'N/A'); ?>
                                        to
                                        <?php echo htmlspecialchars($event['to_status'] ?? 'N/A'); ?>
                                        |
                                        <strong>Status:</strong> <?php echo htmlspecialchars($event['movement_status'] ?? 'N/A'); ?>
                                    </p>
                                    <p>
                                        <strong>Accepted by:</strong>
                                        <?php echo htmlspecialchars($event['accepted_by_name'] ?: 'N/A'); ?>
                                    </p>
                                <?php elseif (($event['action'] ?? '') === 'Status Updated'): ?>
                                    <p>
                                        <strong>Process:</strong>
                                        <?php echo htmlspecialchars(statusLabel($event['from_status'] ?? '')); ?>
                                        to
                                        <?php echo htmlspecialchars(statusLabel($event['to_status'] ?? '')); ?>
                                        |
                                        <strong>Stayed:</strong>
                                        <?php echo htmlspecialchars(formatWaitTime($event['wait_time_minutes'] ?? 0)); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($event['user_id'])): ?>
                                    <p>
                                        <strong><?php echo htmlspecialchars($actorLabel); ?>:</strong>
                                        <?php echo htmlspecialchars($event['updated_by_name'] ?: 'Unknown user'); ?>
                                        <?php if (!empty($event['updated_by_role'])): ?>
                                            (<?php echo htmlspecialchars(roleLabel($event['updated_by_role'])); ?>)
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <span class="tracking-time"><?php echo date('M d, Y H:i:s', strtotime($event['created_at'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <div id="medical-modal" class="medical-modal" aria-hidden="true">
        <div class="medical-modal-card">
            <div class="medical-modal-header">
                <div>
                    <h3>Medical / Visit History</h3>
                    <p class="tracking-time">
                        Patient ID: <?php echo htmlspecialchars($patient['permanent_patient_id'] ?? ''); ?>
                        | <?php echo htmlspecialchars($patient['patient_name']); ?>
                    </p>
                </div>
                <button type="button" class="btn btn-secondary" id="close-medical-modal">Close</button>
            </div>

            <div class="visit-history-list">
                <?php if (empty($visit_history)): ?>
                    <p>No visit history available.</p>
                <?php else: ?>
                    <?php foreach ($visit_history as $visit): ?>
                        <?php $isCurrentVisit = (int) $visit['patient_id'] === (int) $patient_id; ?>
                        <div class="visit-history-item">
                            <strong>
                                <?php echo $isCurrentVisit ? 'Current Visit' : 'Visit'; ?>:
                                <?php echo htmlspecialchars($visit['patient_code']); ?>
                            </strong>
                            <div class="visit-history-meta">
                                Arrival:
                                <?php echo !empty($visit['arrival_time']) ? htmlspecialchars(date('M d, Y H:i:s', strtotime($visit['arrival_time']))) : 'N/A'; ?>
                                | Status: <?php echo htmlspecialchars(statusLabel($visit['status'] ?? '')); ?>
                                <?php if (!empty($visit['ended_at'])): ?>
                                    | Ended: <?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($visit['ended_at']))); ?>
                                <?php endif; ?>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Chief Complaint:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($visit['chief_complaint'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Final Diagnosis:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($visit['final_diagnosis'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Specific Diagnosis:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($visit['specific_diagnosis'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="../public/js/sidebar.js"></script>
    <script>
        const medicalModal = document.getElementById('medical-modal');
        const openMedicalModal = document.getElementById('open-medical-modal');
        const closeMedicalModal = document.getElementById('close-medical-modal');

        const closeMedicalHistoryModal = () => {
            medicalModal.classList.remove('open');
            medicalModal.setAttribute('aria-hidden', 'true');
        };

        openMedicalModal?.addEventListener('click', () => {
            medicalModal.classList.add('open');
            medicalModal.setAttribute('aria-hidden', 'false');
        });

        closeMedicalModal?.addEventListener('click', closeMedicalHistoryModal);

        medicalModal?.addEventListener('click', (event) => {
            if (event.target === medicalModal) {
                closeMedicalHistoryModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && medicalModal.classList.contains('open')) {
                closeMedicalHistoryModal();
            }
        });
    </script>
</body>
</html>
