<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(array_merge(nurseRoles(), doctorRoles()));
$trackingSchemaResult = ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
ensureEmergencyRoomBeds($conn);
$activeStatuses = "'" . implode("','", activePatientStatuses()) . "'";
$patients = [];
$beds = [];
$statusOptions = patientStatusOptions();
$currentWaitSql = patientCurrentWaitMinutesSql('p');

// Get dashboard stats
$longest_wait_patient = $conn->query("
    SELECT
        p.patient_id,
        p.patient_code,
        CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) as patient_name,
        $currentWaitSql as wait_time
    FROM patients p
    WHERE p.status IN ($activeStatuses)
    ORDER BY wait_time DESC
    LIMIT 1
")->fetch_assoc();

$triage_level_counts = [
    'red' => 0,
    'yellow' => 0,
    'green' => 0,
    'black' => 0,
    'none' => 0
];
$triage_count_rows = $conn->query("
    SELECT COALESCE(t.triage_level, 'none') as triage_level, COUNT(*) as count
    FROM patients p
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    WHERE p.status IN ($activeStatuses)
    GROUP BY COALESCE(t.triage_level, 'none')
")->fetch_all(MYSQLI_ASSOC);
foreach ($triage_count_rows as $row) {
    $triage_level_counts[$row['triage_level']] = (int) $row['count'];
}

$stats = [
    'total_er_patients_today' => $conn->query("SELECT COUNT(*) as count FROM patients WHERE DATE(COALESCE(arrival_time, admission_date, created_at)) = CURDATE()")->fetch_assoc()['count'],
    'active_patients' => $conn->query("SELECT COUNT(*) as count FROM patients WHERE status IN ($activeStatuses)")->fetch_assoc()['count'],
    'waiting_for_consultation' => $conn->query("SELECT COUNT(*) as count FROM patients WHERE status = 'waiting_for_consultation'")->fetch_assoc()['count'],
    'patients_by_triage_level' => $triage_level_counts,
    'admissions_from_er' => $conn->query("SELECT COUNT(*) as count FROM patients WHERE status IN ('for_admission', 'admit_inpatient') AND DATE(updated_at) = CURDATE()")->fetch_assoc()['count'],
    'discharges_today' => $conn->query("SELECT COUNT(*) as count FROM patients WHERE status IN ('discharge', 'referred', 'ama', 'thoc', 'absconded', 'dama', 'doa', 'expired', 'er_death') AND DATE(updated_at) = CURDATE()")->fetch_assoc()['count'],
    'longest_waiting_patient' => $longest_wait_patient ?: null
];

// Calculate KPIs
$kpis = [];

// Average Wait Time
$avg_wait = $conn->query("SELECT AVG($currentWaitSql) as avg_wait FROM patients p WHERE p.status IN ($activeStatuses)")->fetch_assoc()['avg_wait'];
$kpis['avg_wait_time'] = round($avg_wait ?? 0);
$kpis['avg_wait_time_label'] = formatWaitTime($kpis['avg_wait_time']);

// Average time spent before each status change
$status_change_stats = $conn->query("
    SELECT
        AVG(wait_time_minutes) as avg_status_change_time,
        COUNT(*) as status_change_count
    FROM patient_tracking
    WHERE action = 'Status Updated'
")->fetch_assoc();
$kpis['avg_status_change_time'] = round($status_change_stats['avg_status_change_time'] ?? 0);
$kpis['avg_status_change_time_label'] = formatWaitTime($kpis['avg_status_change_time']);
$kpis['status_change_count'] = (int) ($status_change_stats['status_change_count'] ?? 0);

$status_time_by_process = [];
foreach ($statusOptions as $statusValue => $statusName) {
    $status_time_by_process[$statusValue] = [
        'status' => $statusValue,
        'label' => $statusName,
        'avg_minutes' => null,
        'completed_count' => 0
    ];
}

if ($trackingSchemaResult === true) {
    $status_time_rows = $conn->query("
        SELECT
            from_status as status,
            AVG(wait_time_minutes) as avg_minutes,
            COUNT(*) as completed_count
        FROM patient_tracking
        WHERE action = 'Status Updated'
            AND from_status IS NOT NULL
        GROUP BY from_status
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($status_time_rows as $row) {
        if (isset($status_time_by_process[$row['status']])) {
            $status_time_by_process[$row['status']]['avg_minutes'] = round($row['avg_minutes'] ?? 0);
            $status_time_by_process[$row['status']]['completed_count'] = (int) $row['completed_count'];
        }
    }
}

$treatmentAverage = $status_time_by_process['treatment']['avg_minutes'];
$kpis['avg_treatment_time_label'] = $treatmentAverage === null ? 'No data' : formatWaitTime($treatmentAverage);
$kpis['treatment_count'] = $status_time_by_process['treatment']['completed_count'];

// Bed Occupancy Rate
$total_beds = $conn->query("SELECT COUNT(*) as count FROM beds")->fetch_assoc()['count'];
$occupied_beds = $conn->query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch_assoc()['count'];
$kpis['bed_occupancy_rate'] = $total_beds > 0 ? round(($occupied_beds / $total_beds) * 100) : 0;

// Average Wait by Triage
$wait_by_triage = $conn->query("
    SELECT 
        t.triage_level,
        AVG($currentWaitSql) as avg_wait,
        COUNT(p.patient_id) as patient_count
    FROM patients p
    LEFT JOIN triage t ON p.patient_id = t.patient_id
    WHERE p.status IN ($activeStatuses)
    GROUP BY t.triage_level
")->fetch_all(MYSQLI_ASSOC);

// Patients by Status
$patients_by_status = $conn->query("
    SELECT status, COUNT(*) as count FROM patients GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

// Beds by Type
$beds_by_type = $conn->query("
    SELECT bed_type, 
           COUNT(*) as total,
           SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
           SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
           SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
    FROM beds 
    GROUP BY bed_type
")->fetch_all(MYSQLI_ASSOC);

// Top Complaints
$top_complaints = $conn->query("
    SELECT chief_complaint, COUNT(*) as count 
    FROM triage 
    GROUP BY chief_complaint 
    ORDER BY count DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$hourly_arrivals = array_fill(0, 24, 0);
$hourly_arrival_rows = $conn->query("
    SELECT HOUR(COALESCE(arrival_time, admission_date, created_at)) as arrival_hour, COUNT(*) as total
    FROM patients
    WHERE DATE(COALESCE(arrival_time, admission_date, created_at)) = CURDATE()
    GROUP BY HOUR(COALESCE(arrival_time, admission_date, created_at))
")->fetch_all(MYSQLI_ASSOC);
foreach ($hourly_arrival_rows as $row) {
    $hourly_arrivals[(int) $row['arrival_hour']] = (int) $row['total'];
}

$chartWidth = 620;
$chartHeight = 190;
$chartPadding = 26;
$maxHourlyArrival = max(max($hourly_arrivals), 1);
$linePoints = [];
foreach ($hourly_arrivals as $hour => $count) {
    $x = $chartPadding + ($hour * (($chartWidth - ($chartPadding * 2)) / 23));
    $y = ($chartHeight - $chartPadding) - (($count / $maxHourlyArrival) * ($chartHeight - ($chartPadding * 2)));
    $linePoints[] = round($x, 1) . ',' . round($y, 1);
}
$linePointsString = implode(' ', $linePoints);

$urgentTriageCount = (int) $triage_level_counts['red'] + (int) $triage_level_counts['yellow'];
$nonUrgentTriageCount = (int) $triage_level_counts['green'];
$triagePieTotal = max($urgentTriageCount + $nonUrgentTriageCount, 1);
$urgentPercent = round(($urgentTriageCount / $triagePieTotal) * 100);

$admissionDischargeMax = max((int) $stats['admissions_from_er'], (int) $stats['discharges_today'], 1);
$admissionBarPercent = round(((int) $stats['admissions_from_er'] / $admissionDischargeMax) * 100);
$dischargeBarPercent = round(((int) $stats['discharges_today'] / $admissionDischargeMax) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Room Dashboard - Nursing Service System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-brand">
            <h1>🏥 Nursing Service System</h1>
        </div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?> (<?php echo htmlspecialchars(roleLabel($_SESSION['role'])); ?>)</span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content" data-active-view="dashboard">
            <div class="page-header dashboard-only-section">
                <h2>Emergency Room Dashboard</h2>
            </div>

            <!-- Stats Cards -->
            <section class="stats-grid dashboard-only-section">
                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">Total ER Patients Today</span>
                        <span class="stat-value"><?php echo htmlspecialchars($stats['total_er_patients_today']); ?></span>
                    </div>
                </div>
                <div class="stat-card waiting">
                    <div class="stat-info">
                        <span class="stat-label">Active Patients</span>
                        <span class="stat-value"><?php echo htmlspecialchars($stats['active_patients']); ?></span>
                    </div>
                </div>
                <div class="stat-card emergency">
                    <div class="stat-info">
                        <span class="stat-label">Waiting for Consultation</span>
                        <span class="stat-value"><?php echo htmlspecialchars($stats['waiting_for_consultation']); ?></span>
                    </div>
                </div>
                <div class="stat-card available">
                    <div class="stat-info">
                        <span class="stat-label">Average Waiting Time</span>
                        <span class="stat-value"><?php echo htmlspecialchars($kpis['avg_wait_time_label']); ?></span>
                    </div>
                </div>
                <div class="stat-card waiting">
                    <div class="stat-info">
                        <span class="stat-label">Longest Waiting Patient</span>
                        <span class="stat-value"><?php echo htmlspecialchars(isset($stats['longest_waiting_patient']['wait_time']) ? formatWaitTime($stats['longest_waiting_patient']['wait_time']) : 'No data'); ?></span>
                        <span class="stat-subtext"><?php echo htmlspecialchars($stats['longest_waiting_patient']['patient_name'] ?? 'No active patient'); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <span class="stat-label">Patients by Triage Level</span>
                        <span class="stat-value stat-value-small">R <?php echo htmlspecialchars($stats['patients_by_triage_level']['red']); ?> / Y <?php echo htmlspecialchars($stats['patients_by_triage_level']['yellow']); ?> / G <?php echo htmlspecialchars($stats['patients_by_triage_level']['green']); ?> / B <?php echo htmlspecialchars($stats['patients_by_triage_level']['black']); ?></span>
                    </div>
                </div>
                <div class="stat-card available">
                    <div class="stat-info">
                        <span class="stat-label">Admissions from ER</span>
                        <span class="stat-value"><?php echo htmlspecialchars($stats['admissions_from_er']); ?></span>
                    </div>
                </div>
                <div class="stat-card emergency">
                    <div class="stat-info">
                        <span class="stat-label">Discharges Today</span>
                        <span class="stat-value"><?php echo htmlspecialchars($stats['discharges_today']); ?></span>
                    </div>
                </div>
            </section>

            <section class="chart-section dashboard-only-section">
                <h2>Best Charts</h2>
                <div class="chart-grid">
                    <div class="chart-card chart-card-wide">
                        <div class="chart-header">
                            <h3>Patient Flow Trend</h3>
                            <span>Hourly ER arrivals today</span>
                        </div>
                        <svg class="line-chart" viewBox="0 0 <?php echo $chartWidth; ?> <?php echo $chartHeight; ?>" role="img" aria-label="Hourly ER arrivals line chart">
                            <line x1="<?php echo $chartPadding; ?>" y1="<?php echo $chartHeight - $chartPadding; ?>" x2="<?php echo $chartWidth - $chartPadding; ?>" y2="<?php echo $chartHeight - $chartPadding; ?>" class="chart-axis"></line>
                            <line x1="<?php echo $chartPadding; ?>" y1="<?php echo $chartPadding; ?>" x2="<?php echo $chartPadding; ?>" y2="<?php echo $chartHeight - $chartPadding; ?>" class="chart-axis"></line>
                            <polyline points="<?php echo htmlspecialchars($linePointsString); ?>" class="line-chart-path"></polyline>
                            <?php foreach ($linePoints as $point): ?>
                                <?php [$pointX, $pointY] = explode(',', $point); ?>
                                <circle cx="<?php echo htmlspecialchars($pointX); ?>" cy="<?php echo htmlspecialchars($pointY); ?>" r="3" class="line-chart-dot"></circle>
                            <?php endforeach; ?>
                            <?php foreach ([0, 6, 12, 18, 23] as $hourLabel): ?>
                                <?php $labelX = $chartPadding + ($hourLabel * (($chartWidth - ($chartPadding * 2)) / 23)); ?>
                                <text x="<?php echo round($labelX, 1); ?>" y="<?php echo $chartHeight - 6; ?>" class="chart-label" text-anchor="middle"><?php echo str_pad((string) $hourLabel, 2, '0', STR_PAD_LEFT); ?>:00</text>
                            <?php endforeach; ?>
                        </svg>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Triage Distribution</h3>
                            <span>Urgent vs non-urgent</span>
                        </div>
                        <div class="pie-chart" style="--urgent: <?php echo $urgentPercent; ?>%;"></div>
                        <div class="chart-legend">
                            <span><i class="legend-dot urgent"></i>Urgent: <?php echo htmlspecialchars($urgentTriageCount); ?></span>
                            <span><i class="legend-dot nonurgent"></i>Non-Urgent: <?php echo htmlspecialchars($nonUrgentTriageCount); ?></span>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Admission Rate</h3>
                            <span>Admitted vs discharged today</span>
                        </div>
                        <div class="bar-chart">
                            <div class="bar-row">
                                <span>Admitted</span>
                                <div class="bar-track"><div class="bar-fill admitted" style="width: <?php echo $admissionBarPercent; ?>%;"></div></div>
                                <strong><?php echo htmlspecialchars($stats['admissions_from_er']); ?></strong>
                            </div>
                            <div class="bar-row">
                                <span>Discharged</span>
                                <div class="bar-track"><div class="bar-fill discharged" style="width: <?php echo $dischargeBarPercent; ?>%;"></div></div>
                                <strong><?php echo htmlspecialchars($stats['discharges_today']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Patient Tracking Table -->
            <section class="patients-section dashboard-panel" id="live-patient-tracking">
                <h2>📋 Patient Live Tracker</h2>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Triage Level</th>
                                <th>Wait Time</th>
                                <th>Complaint</th>
                                <th>Status</th>
                                <th>Bed</th>
                                <th>Assigned Nurse</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No active patients</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <tr class="patient-row triage-<?php echo htmlspecialchars($patient['triage_level'] ?? 'none'); ?> <?php echo (int) ($patient['wait_time'] ?? 0) >= 30 ? 'wait-alert-row' : ''; ?>">
                                        <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo htmlspecialchars($patient['triage_level'] ?? 'secondary'); ?>">
                                                <?php echo htmlspecialchars(triageLevelLabel($patient['triage_level'] ?? '')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="wait-time <?php echo (int) ($patient['wait_time'] ?? 0) >= 30 ? 'wait-long' : ''; ?>">
                                                <?php echo htmlspecialchars(formatWaitTime($patient['wait_time'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['chief_complaint'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(statusLabel($patient['status'])); ?></td>
                                        <td><?php echo htmlspecialchars($patient['bed_number'] ?? 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars($patient['nurse_name'] ?? 'Unassigned'); ?></td>
                                        <td>
                                            <a href="patient_detail.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-info">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Bed Management Section -->
            <section class="beds-section dashboard-panel" id="bed-tracker">
                <h2>Bed Tracker</h2>
                <div class="beds-grid">
                    <?php foreach ($beds as $bed): ?>
                        <div class="bed-card bed-<?php echo htmlspecialchars($bed['status']); ?>">
                            <div class="bed-number"><?php echo htmlspecialchars($bed['bed_number']); ?></div>
                            <div class="bed-type"><?php echo htmlspecialchars($bed['bed_type']); ?></div>
                            <div class="bed-room">Room <?php echo htmlspecialchars($bed['room_number']); ?></div>
                            <div class="bed-status"><?php echo ucfirst(htmlspecialchars($bed['status'])); ?></div>
                            <?php if ($bed['status'] != 'available'): ?>
                                <div class="bed-patient"><?php echo htmlspecialchars($bed['patient_name']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- KPIs Section -->
            <section class="kpi-section dashboard-only-section">
                <h2>📊 Key Performance Indicators (KPIs)</h2>
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-label">Average Wait Time</div>
                        <div class="kpi-value"><?php echo htmlspecialchars($kpis['avg_wait_time_label']); ?></div>
                        <div class="kpi-trend">Current average</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Bed Occupancy Rate</div>
                        <div class="kpi-value"><?php echo $kpis['bed_occupancy_rate']; ?>%</div>
                        <div class="kpi-trend"><?php echo $occupied_beds; ?> of <?php echo $total_beds; ?> beds</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Avg Treatment</div>
                        <div class="kpi-value"><?php echo htmlspecialchars($kpis['avg_treatment_time_label']); ?></div>
                        <div class="kpi-trend"><?php echo $kpis['treatment_count']; ?> completed stays</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Avg Process Time</div>
                        <div class="kpi-value"><?php echo htmlspecialchars($kpis['avg_status_change_time_label']); ?></div>
                        <div class="kpi-trend"><?php echo $kpis['status_change_count']; ?> status updates</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Total Patients Active</div>
                        <div class="kpi-value"><?php echo $stats['active_patients']; ?></div>
                        <div class="kpi-trend">In system</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">RED Priority Cases</div>
                        <div class="kpi-value"><?php echo $stats['patients_by_triage_level']['red']; ?></div>
                        <div class="kpi-trend">Immediate priority</div>
                    </div>
                </div>
            </section>

            <!-- Census Section -->
            <section class="census-section dashboard-only-section">
                <div class="census-container">
                    <!-- Bed Status by Type -->
                    <div class="census-card">
                        <h3>🛏️ Census - Bed Status by Type</h3>
                        <table class="table" style="font-size: 14px;">
                            <thead>
                                <tr>
                                    <th>Bed Type</th>
                                    <th>Total</th>
                                    <th>Available</th>
                                    <th>Occupied</th>
                                    <th>Maintenance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($beds_by_type as $bed_type): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($bed_type['bed_type']); ?></strong></td>
                                        <td><?php echo $bed_type['total']; ?></td>
                                        <td><span style="color: #4CAF50; font-weight: bold;"><?php echo $bed_type['available']; ?></span></td>
                                        <td><span style="color: #ff9800; font-weight: bold;"><?php echo $bed_type['occupied']; ?></span></td>
                                        <td><span style="color: #999; font-weight: bold;"><?php echo $bed_type['maintenance']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Patient Status Distribution -->
                    <div class="census-card">
                        <h3>📋 Patient Status Distribution</h3>
                        <div class="census-chart">
                            <?php foreach ($patients_by_status as $status): ?>
                                <div class="census-item">
                                    <div class="census-label"><?php echo htmlspecialchars(statusLabel($status['status'])); ?></div>
                                    <div class="census-bar-container">
                                        <div class="census-bar" style="width: <?php echo ($status['count'] / max(array_column($patients_by_status, 'count'), 1)) * 100; ?>%">
                                            <span class="census-value"><?php echo $status['count']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Wait Time by Triage Level -->
                <div class="census-card">
                    <h3>⏱️ Average Wait Time by Triage Level</h3>
                    <table class="table" style="font-size: 14px;">
                        <thead>
                            <tr>
                                <th>Triage Level</th>
                                <th>Avg Wait (min)</th>
                                <th>Patient Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wait_by_triage as $item): ?>
                                <tr class="triage-<?php echo htmlspecialchars($item['triage_level'] ?? 'none'); ?>">
                                    <td>
                                        <span class="badge badge-<?php echo htmlspecialchars($item['triage_level'] ?? 'secondary'); ?>">
                                            <?php echo htmlspecialchars(triageLevelLabel($item['triage_level'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(formatWaitTime(round($item['avg_wait'] ?? 0))); ?></td>
                                    <td><?php echo $item['patient_count']; ?> patients</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="census-card">
                    <h3>Average Time Per Process</h3>
                    <?php if ($trackingSchemaResult !== true): ?>
                        <p><?php echo htmlspecialchars($trackingSchemaResult); ?></p>
                    <?php else: ?>
                        <table class="table" style="font-size: 14px;">
                            <thead>
                                <tr>
                                    <th>Process</th>
                                    <th>Average Time</th>
                                    <th>Completed Stays</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($status_time_by_process as $process): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($process['label']); ?></td>
                                        <td><?php echo $process['avg_minutes'] === null ? 'No data' : htmlspecialchars(formatWaitTime($process['avg_minutes'])); ?></td>
                                        <td><?php echo $process['completed_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Top Chief Complaints -->
                <div class="census-card">
                    <h3>🏥 Top Chief Complaints</h3>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($top_complaints as $index => $complaint): ?>
                            <li style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                                <span><?php echo ($index + 1); ?>. <?php echo htmlspecialchars($complaint['chief_complaint']); ?></span>
                                <span style="background: #f0f0f0; padding: 4px 12px; border-radius: 20px; font-weight: 600;"><?php echo $complaint['count']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>

    <script src="../public/js/sidebar.js"></script>
    <script src="../public/js/dashboard.js"></script>
</body>
</html>

