<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requirePermission('nursing', 'view', ['WARD_2F', 'WARD_3F', 'ICU', 'NICU']);

ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
ensureWardBeds($conn);

function wardStatValue(mysqli $conn, string $sql, array $params = [], string $types = ''): int {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int) reset($row) : 0;
}

function wardStatRows(mysqli $conn, string $sql, array $params = [], string $types = ''): array {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function wardPercent(int $value, int $total): float {
    return $total > 0 ? round(($value / $total) * 100, 2) : 0.0;
}

function wardCapacityStatus(int $occupied, int $total): string {
    if ($total <= 0) {
        return 'NO BEDS CONFIGURED';
    }
    if ($occupied > $total) {
        return 'OVER CAPACITY';
    }
    if ($occupied === $total) {
        return 'FULL';
    }
    return 'AVAILABLE';
}

$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;
if (!validDateString($selectedDate)) {
    $selectedDate = $today;
}

$wardStatusList = "'admit_inpatient'";
$dischargeStatusList = "'discharge','discharged'";
$transferInStatusList = "'trans_in','admit_inpatient'";
$transferOutStatusList = "'trans_out','referred','thoc'";
$hamaStatusList = "'hama','ama','dama'";
$deathStatusList = "'death','expired','er_death','doa'";

$currentCensus = wardStatValue($conn, "SELECT COUNT(*) FROM patients WHERE status IN ($wardStatusList)");
$assignedBeds = wardStatValue($conn, "
    SELECT COUNT(*)
    FROM patients p
    INNER JOIN beds b ON b.bed_id = p.current_bed
    WHERE p.status IN ($wardStatusList)
        AND (b.room_number LIKE 'WARD-%' OR b.bed_type IN ('General','ICU'))
");
$awaitingBeds = wardStatValue($conn, "SELECT COUNT(*) FROM patients WHERE status = 'for_admission'");
$admissionsToday = wardStatValue($conn, "
    SELECT COUNT(DISTINCT patient_id)
    FROM patient_tracking
    WHERE to_status IN ($transferInStatusList) AND DATE(created_at) = ?
", [$selectedDate], 's');
$dischargesToday = wardStatValue($conn, "
    SELECT COUNT(*)
    FROM patients
    WHERE status IN ($dischargeStatusList) AND DATE(updated_at) = ?
", [$selectedDate], 's');
$transferOutToday = wardStatValue($conn, "
    SELECT COUNT(*)
    FROM patients
    WHERE status IN ($transferOutStatusList) AND DATE(updated_at) = ?
", [$selectedDate], 's');
$hamaToday = wardStatValue($conn, "
    SELECT COUNT(*)
    FROM patients
    WHERE status IN ($hamaStatusList) AND DATE(updated_at) = ?
", [$selectedDate], 's');
$deathsToday = wardStatValue($conn, "
    SELECT COUNT(*)
    FROM patients
    WHERE status IN ($deathStatusList) AND DATE(updated_at) = ?
", [$selectedDate], 's');

$under6 = wardStatValue($conn, "
    SELECT COUNT(*)
    FROM patient_tracking
    WHERE to_status IN ($dischargeStatusList)
        AND wait_time_minutes > 0
        AND wait_time_minutes < 360
        AND DATE(created_at) = ?
", [$selectedDate], 's');
$over6 = wardStatValue($conn, "
    SELECT COUNT(*)
    FROM patient_tracking
    WHERE to_status IN ($dischargeStatusList)
        AND wait_time_minutes >= 360
        AND DATE(created_at) = ?
", [$selectedDate], 's');
$tatTotal = $under6 + $over6;
$dischargeAccomplishment = wardPercent($under6, max(1, $tatTotal));

$departmentRows = wardStatRows($conn, "
    SELECT
        COALESCE(NULLIF(ward_department, ''), 'MEDICAL') AS department,
        COUNT(*) AS census,
        SUM(CASE WHEN current_bed IS NOT NULL THEN 1 ELSE 0 END) AS assigned_beds
    FROM patients
    WHERE status IN ($wardStatusList)
    GROUP BY COALESCE(NULLIF(ward_department, ''), 'MEDICAL')
    ORDER BY census DESC, department
");

$movementRows = wardStatRows($conn, "
    SELECT
        COALESCE(NULLIF(p.ward_department, ''), 'MEDICAL') AS department,
        SUM(CASE WHEN pt.to_status IN ($transferInStatusList) THEN 1 ELSE 0 END) AS admissions,
        SUM(CASE WHEN pt.to_status IN ($dischargeStatusList) THEN 1 ELSE 0 END) AS discharges,
        SUM(CASE WHEN pt.to_status IN ($transferOutStatusList) THEN 1 ELSE 0 END) AS transfers_out,
        SUM(CASE WHEN pt.to_status IN ($hamaStatusList) THEN 1 ELSE 0 END) AS hama,
        SUM(CASE WHEN pt.to_status IN ($deathStatusList) THEN 1 ELSE 0 END) AS deaths
    FROM patient_tracking pt
    INNER JOIN patients p ON p.patient_id = pt.patient_id
    WHERE DATE(pt.created_at) = ?
        AND pt.to_status IN ($transferInStatusList, $dischargeStatusList, $transferOutStatusList, $hamaStatusList, $deathStatusList)
    GROUP BY COALESCE(NULLIF(p.ward_department, ''), 'MEDICAL')
    ORDER BY department
", [$selectedDate], 's');

$bedRows = [];
foreach ([
    '2ND FLOOR WARD' => '2nd Floor Ward',
    '3RD FLOOR WARD' => '3rd Floor Ward',
    'ICU / MICU' => 'ICU',
    'NICU' => 'NICU',
] as $areaName => $wardArea) {
    $summary = clinicalBedCapacitySummary($conn, $areaName);
    $bedRows[] = [
        'ward_area' => $wardArea,
        'total_beds' => $summary['total_bed_capacity'],
        'occupied_beds' => $summary['occupied'],
        'vacant_beds' => $summary['vacant'],
        'maintenance_beds' => 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Statistics Dashboard - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .stats-panel { background:#fff; border:1px solid #E1E7EF; border-radius:14px; box-shadow:0 8px 22px rgba(30,58,95,.06); padding:18px; margin-bottom:18px; }
        .stats-panel h3 { color:#0B3C5D; margin:0 0 12px; padding-bottom:9px; border-bottom:2px solid #E1E7EF; }
        .compact-table { width:100%; min-width:720px; border-collapse:collapse; }
        .compact-table th,.compact-table td { padding:10px; border-bottom:1px solid #E1E7EF; text-align:left; font-size:13px; }
        .compact-table th { background:#F4F7FA; color:#0B3C5D; }
        .metric-line { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid #EDF2F7; }
        .metric-line:last-child { border-bottom:0; }
        .progress-track { height:10px; background:#E8EEF6; border-radius:999px; overflow:hidden; margin-top:10px; }
        .progress-fill { height:100%; background:#0B3C5D; }
        .status-pill { display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; background:#EAF6FF; color:#0B3C5D; }
        .status-pill.full { background:#FFF4E5; color:#8A4B00; }
        .status-pill.over { background:#FDECEC; color:#9B1C1C; }
        @media print { .sidebar,.navbar,.no-print{display:none!important}.dashboard-container{display:block}.main-content{padding:0} }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui">
    <nav class="navbar">
        <div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div>
        <div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?> (<?php echo h(roleLabel($_SESSION['role'])); ?>)</span></div>
    </nav>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <main class="main-content">
            <section class="page-header">
                <h2>Ward Statistics Dashboard</h2>
                <p class="compact-meta">Aggregate ward statistics only. No patient names are shown.</p>
                <form method="GET" class="report-filter no-print">
                    <div class="form-group"><label>Date</label><input type="date" name="date" value="<?php echo h($selectedDate); ?>"></div>
                    <button class="btn btn-primary">Apply</button>
                    <button class="btn btn-secondary" type="button" onclick="window.print()">Print</button>
                </form>
            </section>

            <section class="stats-grid">
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Current Census</span><span class="stat-value"><?php echo h($currentCensus); ?></span></div></div>
                <div class="stat-card available"><div class="stat-info"><span class="stat-label">Assigned Beds</span><span class="stat-value"><?php echo h($assignedBeds); ?></span></div></div>
                <div class="stat-card waiting"><div class="stat-info"><span class="stat-label">Awaiting Bed</span><span class="stat-value"><?php echo h($awaitingBeds); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Admissions</span><span class="stat-value"><?php echo h($admissionsToday); ?></span></div></div>
                <div class="stat-card available"><div class="stat-info"><span class="stat-label">Discharges</span><span class="stat-value"><?php echo h($dischargesToday); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Transfers Out</span><span class="stat-value"><?php echo h($transferOutToday); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">HAMA</span><span class="stat-value"><?php echo h($hamaToday); ?></span></div></div>
                <div class="stat-card emergency"><div class="stat-info"><span class="stat-label">Deaths</span><span class="stat-value"><?php echo h($deathsToday); ?></span></div></div>
            </section>

            <section class="stats-panel">
                <h3>Bed Capacity by Ward Area</h3>
                <div class="table-responsive">
                    <table class="compact-table">
                        <thead><tr><th>Ward Area</th><th>Vacant Beds</th><th>Occupied</th><th>Maintenance</th><th>Occupancy</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($bedRows as $row): ?>
                                <?php
                                    $total = (int) $row['total_beds'];
                                    $occupied = (int) $row['occupied_beds'];
                                    $status = wardCapacityStatus($occupied, $total);
                                    $statusClass = $status === 'OVER CAPACITY' ? 'over' : ($status === 'FULL' ? 'full' : '');
                                ?>
                                <tr>
                                    <td><?php echo h(nisDepartmentLabel($row['ward_area'])); ?></td>
                                    <td><strong>Vacant Beds: <?php echo h($row['vacant_beds']); ?> / <?php echo h($total); ?> Beds</strong></td>
                                    <td><?php echo h($occupied); ?></td>
                                    <td><?php echo h($row['maintenance_beds']); ?></td>
                                    <td><?php echo h(number_format(wardPercent($occupied, $total), 2)); ?>%</td>
                                    <td><span class="status-pill <?php echo h($statusClass); ?>"><?php echo h($status); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($bedRows)): ?><tr><td colspan="6">No ward beds configured.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="stats-panel">
                <h3>Ward Discharge Turnaround KPI</h3>
                <div class="metric-line"><strong>Discharged &lt; 6 Hours</strong><span><?php echo h($under6); ?></span></div>
                <div class="metric-line"><strong>Discharged &gt;= 6 Hours</strong><span><?php echo h($over6); ?></span></div>
                <div class="metric-line"><strong>Accomplishment</strong><span><?php echo h(number_format($dischargeAccomplishment, 2)); ?>%</span></div>
                <div class="progress-track"><div class="progress-fill" style="width: <?php echo min(100, $dischargeAccomplishment); ?>%"></div></div>
            </section>

            <section class="stats-panel">
                <h3>Current Census by Department</h3>
                <div class="table-responsive">
                    <table class="compact-table">
                        <thead><tr><th>Department</th><th>Census</th><th>Assigned Beds</th><th>Awaiting Bed</th></tr></thead>
                        <tbody>
                            <?php foreach ($departmentRows as $row): ?>
                                <?php $departmentAwaiting = max(0, (int) $row['census'] - (int) $row['assigned_beds']); ?>
                                <tr>
                                    <td><?php echo h($row['department']); ?></td>
                                    <td><?php echo h($row['census']); ?></td>
                                    <td><?php echo h($row['assigned_beds']); ?></td>
                                    <td><?php echo h($departmentAwaiting); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($departmentRows)): ?><tr><td colspan="4">No admitted ward census.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="stats-panel">
                <h3>Ward Movements</h3>
                <div class="table-responsive">
                    <table class="compact-table">
                        <thead><tr><th>Department</th><th>Admissions</th><th>Discharges</th><th>Transfers Out</th><th>HAMA</th><th>Deaths</th></tr></thead>
                        <tbody>
                            <?php foreach ($movementRows as $row): ?>
                                <tr>
                                    <td><?php echo h($row['department']); ?></td>
                                    <td><?php echo h($row['admissions']); ?></td>
                                    <td><?php echo h($row['discharges']); ?></td>
                                    <td><?php echo h($row['transfers_out']); ?></td>
                                    <td><?php echo h($row['hama']); ?></td>
                                    <td><?php echo h($row['deaths']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($movementRows)): ?><tr><td colspan="6">No ward movements recorded for this date.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
