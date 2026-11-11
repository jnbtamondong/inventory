<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/opd_helpers.php';

requireLogin();
requireRole(array_merge(nurseRoles(), doctorRoles(), admissionRoles()));
if (hasRole(nurseRoles()) && hasDepartment(['WARD_2F', 'WARD_3F', 'ICU', 'NICU']) && !hasRole('admin')) {
    header('Location: ../pages/patient_queue.php');
    exit();
}
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
ensureEmergencyRoomBeds($conn);
ensureOpdSchema($conn);

$today = date('Y-m-d');
$activeStatuses = activePatientStatuses();
$activeStatusList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $activeStatuses)) . "'";
$inactiveStatuses = ['discharge', 'referred', 'ama', 'thoc', 'absconded', 'dama', 'doa', 'expired', 'er_death'];
$inactiveStatusList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $inactiveStatuses)) . "'";
$tatKeys = ['tat_lt_2', 'tat_2_4', 'tat_4_6', 'tat_gt_6'];
$tatLabels = [
    'tat_lt_2' => '< 2 Hours',
    'tat_2_4' => '> 2 to < 4 Hours',
    'tat_4_6' => '> 4 to < 6 Hours',
    'tat_gt_6' => '> 6 Hours'
];
$triageLabels = [
    'emergent' => 'Emergent',
    'urgent' => 'Urgent',
    'non_urgent' => 'Non-Urgent'
];
$triageMap = [
    'red' => 'emergent',
    'emergency' => 'emergent',
    'yellow' => 'urgent',
    'urgent' => 'urgent',
    'green' => 'non_urgent',
    'non-urgent' => 'non_urgent'
];

function emptyTatRow($tatKeys) {
    return array_fill_keys($tatKeys, 0);
}

function tatBucketKey($minutes) {
    if ($minutes < 120) {
        return 'tat_lt_2';
    }
    if ($minutes < 240) {
        return 'tat_2_4';
    }
    if ($minutes < 360) {
        return 'tat_4_6';
    }
    return 'tat_gt_6';
}

function erDashboardPercent($value, $total) {
    return $total > 0 ? number_format(($value / $total) * 100, 1) . '%' : '0.0%';
}

$opdErReferrals = $conn->query("
    SELECT pm.*, p.permanent_patient_id, CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name
    FROM patient_movements pm
    INNER JOIN patients p ON p.patient_id = pm.patient_id
    WHERE pm.source_department='OPD'
        AND pm.destination_department='ER'
        AND pm.status IN ('PENDING','ACCEPTED','IN TRANSIT','ARRIVED')
    ORDER BY pm.created_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$todayPatientRows = $conn->query("
    SELECT
        p.patient_id,
        p.patient_code,
        p.permanent_patient_id,
        p.first_name,
        p.last_name,
        p.middle_name,
        p.age,
        p.gender,
        p.status,
        p.arrival_time,
        p.admission_date,
        p.created_at,
        p.updated_at,
        t.triage_level,
        t.chief_complaint,
        COALESCE(
            (
                SELECT COALESCE(pt.status_ended_at, pt.created_at)
                FROM patient_tracking pt
                WHERE pt.patient_id = p.patient_id
                    AND pt.to_status IN ('discharge', 'discharged', 'for_admission', 'admit_inpatient', 'admission', 'referred', 'refer_other_facility', 'thoc', 'trans_out', 'absconded', 'ama', 'hama', 'dama', 'doa', 'death', 'expired', 'er_death')
                ORDER BY COALESCE(pt.status_ended_at, pt.created_at) DESC
                LIMIT 1
            ),
            CASE WHEN p.status IN ($activeStatusList) THEN NOW() ELSE p.updated_at END,
            NOW()
        ) AS endpoint_at,
        (
            SELECT MAX(COALESCE(pt.status_ended_at, pt.created_at))
            FROM patient_tracking pt
            WHERE pt.patient_id = p.patient_id
                AND pt.to_status IN ($inactiveStatusList)
        ) AS discharged_at,
        EXISTS (
            SELECT 1
            FROM patient_tracking ward_pt
            WHERE ward_pt.patient_id = p.patient_id
                AND ward_pt.to_status = 'admit_inpatient'
        ) AS has_ward_admission
    FROM patients p
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) = CURDATE()
        AND p.status <> 'admit_inpatient'
")->fetch_all(MYSQLI_ASSOC);

$dailyCensus = [
    'total' => 0,
    'active' => 0,
    'for_admission' => 0,
    'discharged' => 0,
    'referred' => 0,
    'other_disposition' => 0
];

$tatByTriage = [
    'emergent' => emptyTatRow($tatKeys),
    'urgent' => emptyTatRow($tatKeys),
    'non_urgent' => emptyTatRow($tatKeys)
];
$dailyTat = emptyTatRow($tatKeys);
$dischargeTat = emptyTatRow($tatKeys);
$pendingPatients = [];

foreach ($todayPatientRows as $row) {
    if ((int) $row['has_ward_admission'] === 1 && $row['status'] === 'discharge') {
        continue;
    }

    $dailyCensus['total']++;
    if (in_array($row['status'], $activeStatuses, true)) {
        $dailyCensus['active']++;
        $pendingPatients[] = $row;
    } elseif (in_array($row['status'], ['for_admission', 'admission'], true)) {
        $dailyCensus['for_admission']++;
    } elseif (in_array($row['status'], ['discharge', 'discharged'], true)) {
        $dailyCensus['discharged']++;
    } elseif (in_array($row['status'], ['referred', 'thoc'], true)) {
        $dailyCensus['referred']++;
    } elseif (in_array($row['status'], $inactiveStatuses, true)) {
        $dailyCensus['other_disposition']++;
    }

    $startAt = $row['arrival_time'] ?: ($row['admission_date'] ?: $row['created_at']);
    $endAt = $row['endpoint_at'] ?: date('Y-m-d H:i:s');
    $minutes = max(0, (int) floor((strtotime($endAt) - strtotime($startAt)) / 60));
    $bucket = tatBucketKey($minutes);
    $dailyTat[$bucket]++;

    $triageKey = $triageMap[$row['triage_level'] ?? ''] ?? null;
    if ($triageKey) {
        $tatByTriage[$triageKey][$bucket]++;
    }

    if (!empty($row['discharged_at']) && date('Y-m-d', strtotime($row['discharged_at'])) === $today) {
        $dischargeTat[$bucket]++;
    }
}

$tatByTriageTotals = emptyTatRow($tatKeys);
foreach ($tatByTriage as $row) {
    foreach ($tatKeys as $key) {
        $tatByTriageTotals[$key] += $row[$key];
    }
}

$dailyTatTotal = array_sum($dailyTat);
$dischargeTatTotal = array_sum($dischargeTat);
$pendingCount = count($pendingPatients);
$opdReferralCount = count($opdErReferrals);
$longWaitCount = $dailyTat['tat_gt_6'];
$completedDispositionCount = $dailyCensus['discharged'] + $dailyCensus['referred'] + $dailyCensus['other_disposition'];

usort($pendingPatients, function ($a, $b) {
    return strtotime($a['arrival_time'] ?: $a['created_at']) <=> strtotime($b['arrival_time'] ?: $b['created_at']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Room Dashboard - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        body {
            background: #f4f7fb;
        }

        .er-dashboard {
            display: grid;
            gap: 20px;
        }

        .er-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 18px;
            align-items: center;
            background: linear-gradient(135deg, #0B3C5D 0%, #0B3C5D 100%);
            color: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 16px 36px rgba(7, 55, 99, 0.22);
        }

        .er-hero h2 {
            margin: 0;
            color: #fff;
            font-size: 28px;
            line-height: 1.2;
        }

        .er-hero p {
            margin: 8px 0 0;
            color: rgba(255, 255, 255, 0.82);
            max-width: 760px;
        }

        .er-hero-date {
            display: grid;
            gap: 4px;
            min-width: 180px;
            padding: 14px 16px;
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            text-align: right;
        }

        .er-hero-date span {
            color: rgba(255, 255, 255, 0.72);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .er-hero-date strong {
            font-size: 18px;
        }

        .er-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .er-kpi-card {
            position: relative;
            overflow: hidden;
            min-height: 116px;
            background: #fff;
            border: 1px solid #dbe5f0;
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
        }

        .er-kpi-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: #0B3C5D;
        }

        .er-kpi-card.is-warning::before {
            background: #d97706;
        }

        .er-kpi-card.is-danger::before {
            background: #dc2626;
        }

        .er-kpi-card.is-success::before {
            background: #0B3C5D;
        }

        .er-kpi-card span {
            display: block;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .er-kpi-card strong {
            display: block;
            margin-top: 8px;
            color: #0f172a;
            font-size: 32px;
            line-height: 1;
        }

        .er-kpi-card small {
            display: block;
            margin-top: 10px;
            color: #64748b;
            line-height: 1.4;
        }

        .report-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .report-dashboard-grid .report-panel.is-wide {
            grid-column: 1 / -1;
        }

        .report-panel {
            background: #fff;
            border: 1px solid #dbe5f0;
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
        }

        .report-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
        }

        .report-panel-header h3 {
            margin: 0;
            color: #0B3C5D;
            font-size: 16px;
        }

        .report-panel-header span {
            color: #6b7280;
            font-size: 13px;
        }

        .number-cell,
        .number-header {
            text-align: center;
        }

        .metric-danger {
            color: #dc2626;
            font-weight: 700;
        }

        .metric-warning {
            color: #d97706;
            font-weight: 700;
        }

        .metric-success {
            color: #0B3C5D;
            font-weight: 700;
        }

        .census-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .census-summary-item {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            background: #f8fafc;
        }

        .census-summary-item span {
            display: block;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .census-summary-item strong {
            display: block;
            color: #111827;
            font-size: 26px;
            margin-top: 4px;
        }

        .er-action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
        }

        .er-action-grid .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 42px;
            border-radius: 8px;
            text-align: center;
        }

        .er-table {
            margin-bottom: 0;
        }

        .er-table th {
            white-space: nowrap;
        }

        .er-empty-state {
            color: #64748b;
            padding: 18px;
        }

        .er-count-badge {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eef6ff;
            color: #0B3C5D;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .er-header-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        @media (max-width: 1180px) {
            .er-kpi-grid,
            .report-dashboard-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .er-hero {
                grid-template-columns: 1fr;
            }

            .er-hero-date {
                text-align: left;
            }

            .er-kpi-grid,
            .report-dashboard-grid,
            .census-summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
    <nav class="navbar">
        <div class="navbar-brand">
            <div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div>
        </div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?> (<?php echo htmlspecialchars(roleLabel($_SESSION['role'])); ?>)</span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="er-dashboard">
                <section class="er-hero">
                    <div>
                        <h2>Emergency Room Dashboard</h2>
                        <?php if(hasPermission('patients','create','ER')): ?><a class="btn btn-primary" href="patient_registration.php?module=er">+ Register Patient</a><?php endif; ?>
                        <p>Daily ER census, turnaround time, pending patients, transfers, and OPD referrals for active nursing operations.</p>
                    </div>
                    <div class="er-hero-date">
                        <span>Monitoring Date</span>
                        <strong><?php echo htmlspecialchars(date('F d, Y')); ?></strong>
                    </div>
                </section>

                <section class="er-kpi-grid" aria-label="Emergency room key performance indicators">
                    <div class="er-kpi-card">
                        <span>Total ER Patients</span>
                        <strong><?php echo htmlspecialchars($dailyCensus['total']); ?></strong>
                        <small>Registered in the ER today</small>
                    </div>
                    <div class="er-kpi-card is-warning">
                        <span>Pending Workflow</span>
                        <strong><?php echo htmlspecialchars($pendingCount); ?></strong>
                        <small>Active patients still in ER care</small>
                    </div>
                    <div class="er-kpi-card is-danger">
                        <span>Over 6 Hours</span>
                        <strong><?php echo htmlspecialchars($longWaitCount); ?></strong>
                        <small>Cases in the longest TAT bucket</small>
                    </div>
                    <div class="er-kpi-card is-success">
                        <span>Completed Disposition</span>
                        <strong><?php echo htmlspecialchars($completedDispositionCount); ?></strong>
                        <small>Discharged, referred, or other final status</small>
                    </div>
                </section>

                <div class="report-dashboard-grid">
                    <section class="report-panel is-wide">
                    <div class="report-panel-header">
                        <div>
                            <h3>Daily Census - Emergency Room</h3>
                            <span>Patients registered in the emergency room today</span>
                        </div>
                    </div>
                    <div class="census-summary-grid">
                        <div class="census-summary-item"><span>Total ER Patients</span><strong><?php echo htmlspecialchars($dailyCensus['total']); ?></strong></div>
                        <div class="census-summary-item"><span>Pending ER Patients</span><strong><?php echo htmlspecialchars($dailyCensus['active']); ?></strong></div>
                        <div class="census-summary-item"><span>For Admission</span><strong><?php echo htmlspecialchars($dailyCensus['for_admission']); ?></strong></div>
                        <div class="census-summary-item"><span>Discharged</span><strong><?php echo htmlspecialchars($dailyCensus['discharged']); ?></strong></div>
                        <div class="census-summary-item"><span>Referred/THOC</span><strong><?php echo htmlspecialchars($dailyCensus['referred']); ?></strong></div>
                        <div class="census-summary-item"><span>Other Disposition</span><strong><?php echo htmlspecialchars($dailyCensus['other_disposition']); ?></strong></div>
                    </div>
                    </section>

                <section class="report-panel">
                    <div class="report-panel-header">
                        <div>
                            <h3>ER Transfer Actions</h3>
                            <span>Connect ER patients to Ward, MICU, OR, NICU, and movement history.</span>
                        </div>
                    </div>
                    <div class="er-action-grid">
                        <a class="btn btn-primary" href="movement_history.php?department=ER">Admit to Ward</a>
                        <a class="btn btn-info" href="movement_history.php?department=ER">Transfer to MICU</a>
                        <a class="btn btn-secondary" href="or_schedule.php">Schedule for OR</a>
                        <a class="btn btn-info" href="movement_history.php?department=ER">Transfer to NICU</a>
                        <a class="btn btn-secondary" href="movement_history.php?department=ER">View Movement History</a>
                    </div>
                </section>

                <section class="report-panel">
                    <div class="report-panel-header">
                        <div>
                            <h3>Turnaround Time by Triage</h3>
                            <span>Grouped by emergent, urgent, and non-urgent triage priority</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped er-table">
                            <thead>
                                <tr>
                                    <th>Priority</th>
                                    <?php foreach ($tatLabels as $label): ?>
                                        <th class="number-header"><?php echo htmlspecialchars($label); ?></th>
                                    <?php endforeach; ?>
                                    <th class="number-header">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tatByTriage as $triageKey => $row): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($triageLabels[$triageKey]); ?></strong></td>
                                        <?php foreach ($tatKeys as $key): ?>
                                            <td class="number-cell <?php echo $key === 'tat_gt_6' && $row[$key] > 0 ? 'metric-danger' : ''; ?>"><?php echo htmlspecialchars($row[$key]); ?></td>
                                        <?php endforeach; ?>
                                        <td class="number-cell"><strong><?php echo htmlspecialchars(array_sum($row)); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td><strong>Total</strong></td>
                                    <?php foreach ($tatKeys as $key): ?>
                                        <td class="number-cell"><strong><?php echo htmlspecialchars($tatByTriageTotals[$key]); ?></strong></td>
                                    <?php endforeach; ?>
                                    <td class="number-cell"><strong><?php echo htmlspecialchars(array_sum($tatByTriageTotals)); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="report-panel">
                    <div class="report-panel-header">
                        <div>
                            <h3>Overall Turnaround Time</h3>
                            <span>All ER patients today by turnaround bucket</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped er-table">
                            <thead>
                                <tr>
                                    <?php foreach ($tatLabels as $label): ?>
                                        <th class="number-header"><?php echo htmlspecialchars($label); ?></th>
                                    <?php endforeach; ?>
                                    <th class="number-header">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <?php foreach ($tatKeys as $key): ?>
                                        <td class="number-cell <?php echo $key === 'tat_gt_6' && $dailyTat[$key] > 0 ? 'metric-danger' : ''; ?>"><?php echo htmlspecialchars($dailyTat[$key]); ?></td>
                                    <?php endforeach; ?>
                                    <td class="number-cell"><strong><?php echo htmlspecialchars($dailyTatTotal); ?></strong></td>
                                </tr>
                                <tr>
                                    <?php foreach ($tatKeys as $key): ?>
                                        <td class="number-cell"><?php echo htmlspecialchars(erDashboardPercent($dailyTat[$key], $dailyTatTotal)); ?></td>
                                    <?php endforeach; ?>
                                    <td class="number-cell">100.0%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="report-panel">
                    <div class="report-panel-header">
                        <div>
                            <h3>Daily Turnaround Time - Discharge</h3>
                            <span>Patients discharged from the emergency room today</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped er-table">
                            <thead>
                                <tr>
                                    <?php foreach ($tatLabels as $label): ?>
                                        <th class="number-header"><?php echo htmlspecialchars($label); ?></th>
                                    <?php endforeach; ?>
                                    <th class="number-header">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <?php foreach ($tatKeys as $key): ?>
                                        <td class="number-cell <?php echo $key === 'tat_gt_6' && $dischargeTat[$key] > 0 ? 'metric-danger' : ''; ?>"><?php echo htmlspecialchars($dischargeTat[$key]); ?></td>
                                    <?php endforeach; ?>
                                    <td class="number-cell"><strong><?php echo htmlspecialchars($dischargeTatTotal); ?></strong></td>
                                </tr>
                                <tr>
                                    <?php foreach ($tatKeys as $key): ?>
                                        <td class="number-cell"><?php echo htmlspecialchars(erDashboardPercent($dischargeTat[$key], $dischargeTatTotal)); ?></td>
                                    <?php endforeach; ?>
                                    <td class="number-cell">100.0%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="report-panel is-wide">
                    <div class="report-panel-header">
                        <div>
                            <h3>Daily Pending Emergency Room Patients</h3>
                            <span>Active ER patients still in workflow</span>
                        </div>
                        <span class="er-count-badge"><?php echo htmlspecialchars($pendingCount); ?> pending</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped er-table">
                            <thead>
                                <tr>
                                    <th>Encounter ID</th>
                                    <th>Patient</th>
                                    <th>Triage</th>
                                    <th>Status</th>
                                    <th>Complaint</th>
                                    <th>Arrival</th>
                                    <th>Current TAT</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingPatients)): ?>
                                    <tr><td colspan="8" class="text-center er-empty-state">No pending emergency room patients today</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pendingPatients as $patient): ?>
                                        <?php
                                            $pendingStartAt = $patient['arrival_time'] ?: ($patient['admission_date'] ?: $patient['created_at']);
                                            $pendingMinutes = max(0, (int) floor((time() - strtotime($pendingStartAt)) / 60));
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($patient['patient_code']); ?></td>
                                            <td><?php echo htmlspecialchars(patientFullName($patient)); ?></td>
                                            <td><?php echo htmlspecialchars(triageLevelLabel($patient['triage_level'] ?? '')); ?></td>
                                            <td><span class="status-pill status-<?php echo htmlspecialchars($patient['status']); ?>"><?php echo htmlspecialchars(statusLabel($patient['status'])); ?></span></td>
                                            <td><?php echo htmlspecialchars($patient['chief_complaint'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($pendingStartAt); ?></td>
                                            <td class="<?php echo $pendingMinutes >= 360 ? 'metric-danger' : ''; ?>"><?php echo htmlspecialchars(formatWaitTime($pendingMinutes)); ?></td>
                                            <td><a href="patient_detail.php?id=<?php echo (int) $patient['patient_id']; ?>" class="btn btn-sm btn-info">View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="report-panel is-wide">
                    <div class="report-panel-header">
                        <div>
                            <h3>OPD Referred Patients</h3>
                            <span>Patients sent from OPD for ER receiving</span>
                        </div>
                        <div class="er-header-actions">
                            <span class="er-count-badge"><?php echo htmlspecialchars($opdReferralCount); ?> referrals</span>
                            <a class="btn btn-sm btn-info" href="movement_history.php?destination=ER">Receive from OPD</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped er-table">
                            <thead><tr><th>Patient</th><th>Movement</th><th>Status</th><th>Requested</th><th>Remarks</th></tr></thead>
                            <tbody>
                                <?php foreach ($opdErReferrals as $movement): ?>
                                    <tr><td><?php echo htmlspecialchars($movement['patient_name']); ?><br><small><?php echo htmlspecialchars($movement['permanent_patient_id']); ?></small></td><td><?php echo htmlspecialchars($movement['movement_type']); ?></td><td><?php echo htmlspecialchars($movement['status']); ?></td><td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($movement['created_at']))); ?></td><td><?php echo htmlspecialchars($movement['remarks'] ?? ''); ?></td></tr>
                                <?php endforeach; ?>
                                <?php if (empty($opdErReferrals)): ?><tr><td colspan="5" class="text-center er-empty-state">No OPD referrals pending for ER.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                </div>
            </div>
        </main>
    </div>

    <script src="../public/js/sidebar.js"></script>
</body>
</html>
