<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';
require_once '../includes/module_navigation.php';
require_once '../includes/nursing_reports.php';
require_once '../includes/dashboard_overview.php';

requireLogin();

$canSeeSystemAlerts = isSuperAdmin() || hasRole(['CSSR_HEAD', 'CSSR_STAFF']);
$canViewNursing = hasPermission('nursing', 'view') || hasPermission('patients', 'view');
$canViewCssrOnly = !$canViewNursing && (hasPermission('ris_requests', 'view') || hasPermission('cssr_dashboard', 'view'));
if (!$canViewNursing && !$canViewCssrOnly) {
    logSecurityEvent($conn, 'Restricted Access Attempt', 'Dashboard blocked');
    header('Location: system_menu.php');
    exit();
}

ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
ensureClinicalModuleSchema($conn);
nursingReportEnsureSchema($conn);

function dashboardScalar(mysqli $conn, string $sql, array $params = [], string $types = ''): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row ? reset($row) : 0);
}

function dashboardRows(mysqli $conn, string $sql, array $params = [], string $types = ''): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function dashboardSqlList(mysqli $conn, array $values): string {
    return "'" . implode("','", array_map([$conn, 'real_escape_string'], $values)) . "'";
}

function dashboardPercent(int $part, int $total): string {
    return $total > 0 ? number_format(($part / $total) * 100, 2) : '0.00';
}

function dashboardBadgeClass(string $status): string {
    $status = strtoupper($status);
    if (in_array($status, ['AVAILABLE', 'COMPLETED', 'ACCEPTED', 'ACTIVE'], true)) {
        return 'success';
    }
    if (in_array($status, ['FULL', 'PENDING', 'IN TRANSIT'], true)) {
        return 'warning';
    }
    if (in_array($status, ['OVER CAPACITY', 'CANCELLED', 'REJECTED', 'EXPIRED'], true)) {
        return 'danger';
    }
    return 'info';
}

function cssrDashboardSummary(): array {
    try {
        $cssrSettings=nursingDatabaseSettings('cssr_inventory');
        $cssr = new mysqli($cssrSettings['host'],$cssrSettings['user'],$cssrSettings['password'],$cssrSettings['database'],$cssrSettings['port']);
        $cssr->set_charset('utf8mb4');
        return [
            'pending' => dashboardScalar($cssr, "SELECT COUNT(*) FROM item_requests WHERE status='Pending'"),
            'approved' => dashboardScalar($cssr, "SELECT COUNT(*) FROM item_requests WHERE status='Approved'"),
            'released_today' => dashboardScalar($cssr, "SELECT COUNT(*) FROM item_requests WHERE status='Released' AND DATE(COALESCE(released_at, created_at))=CURDATE()"),
            'low_stock' => dashboardScalar($cssr, "SELECT COUNT(*) FROM items WHERE status='Active' AND current_stock <= minimum_stock"),
        ];
    } catch (Throwable $e) {
        return ['pending' => 0, 'approved' => 0, 'released_today' => 0, 'low_stock' => 0];
    }
}

function dashboardBedAreaSummary(mysqli $conn, string $areaName, string $whereSql, array $params = [], string $types = ''): array {
    $row = dashboardRows($conn, "
        SELECT
            COUNT(*) AS total_bed_capacity,
            SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) AS available,
            SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) AS occupied,
            SUM(CASE WHEN status='maintenance' THEN 1 ELSE 0 END) AS maintenance
        FROM beds
        WHERE $whereSql
    ", $params, $types)[0] ?? [];

    $capacity = (int) ($row['total_bed_capacity'] ?? 0);
    $configuredCapacity = dashboardScalar($conn, "SELECT total_bed_capacity FROM bed_capacity_settings WHERE area_name=? AND status='ACTIVE'", [$areaName], 's');
    if ($configuredCapacity > 0) {
        $capacity = $configuredCapacity;
    }
    $occupied = (int) ($row['occupied'] ?? 0);
    $maintenance = (int) ($row['maintenance'] ?? 0);
    $available = max(0, $capacity - $occupied - $maintenance);
    $status = $available > 0 ? 'AVAILABLE' : ($capacity > 0 && $maintenance >= $capacity ? 'MAINTENANCE' : 'FULL');

    return [
        'area_name' => $areaName,
        'total_bed_capacity' => $capacity,
        'occupied' => $occupied,
        'vacant' => $available,
        'maintenance' => $maintenance,
        'status' => $status,
        'occupancy_percentage' => $capacity > 0 ? round(($occupied / $capacity) * 100, 2) : 0,
        'vacant_label' => 'Available Beds: ' . $available . ' / ' . $capacity . ' Beds',
    ];
}

function dashboardSqlValueList(mysqli $conn, array $values): string {
    return implode(',', array_map(fn($value) => "'" . $conn->real_escape_string($value) . "'", $values));
}

function dashboardLiveBedSummaries(mysqli $conn): array {
    $obPediaRoomCondition = 'room_number IN (' . dashboardSqlValueList($conn, array_values(array_unique(obPediaWardRoomNumbers()))) . ')';
    $rows = [
        dashboardBedAreaSummary($conn, 'ER', "room_number LIKE 'ER-%'"),
        dashboardBedAreaSummary($conn, '2ND FLOOR WARD', $obPediaRoomCondition),
        dashboardBedAreaSummary($conn, '3RD FLOOR WARD', "room_number LIKE 'WARD-03%' OR room_number LIKE 'WARD-04%'"),
    ];

    foreach (['NICU', 'ICU / MICU'] as $areaName) {
        $capacity = dashboardScalar($conn, "SELECT total_bed_capacity FROM bed_capacity_settings WHERE area_name=? AND status='ACTIVE'", [$areaName], 's');
        if ($capacity <= 0) {
            $capacity = clinicalDefaultBedCapacities()[$areaName] ?? 0;
        }
        $occupied = $areaName === 'NICU'
            ? dashboardScalar($conn, "SELECT COUNT(*) FROM nicu_records WHERE status='ACTIVE'")
            : dashboardScalar($conn, "SELECT COUNT(*) FROM micu_records WHERE status='ACTIVE'");
        $available = $capacity - $occupied;
        $status = $available > 0 ? 'AVAILABLE' : ($available === 0 ? 'FULL' : 'OVER CAPACITY');
        $rows[] = [
            'area_name' => $areaName,
            'total_bed_capacity' => $capacity,
            'occupied' => $occupied,
            'vacant' => $available,
            'maintenance' => 0,
            'status' => $status,
            'occupancy_percentage' => $capacity > 0 ? round(($occupied / $capacity) * 100, 2) : 0,
            'vacant_label' => 'Available Beds: ' . $available . ' / ' . $capacity . ' Beds',
        ];
    }

    return $rows;
}

$today = date('Y-m-d');
$startDate = $_GET['start_date'] ?? ($_GET['date'] ?? $today);
$endDate = $_GET['end_date'] ?? ($_GET['date'] ?? $today);
if (!validDateString($startDate) || !validDateString($endDate)) {
    $startDate = $today;
    $endDate = $today;
}
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$selectedDepartment = sanitizeText($_GET['department'] ?? 'ALL', 40);
$transferStatus = sanitizeText($_GET['transfer_status'] ?? 'PENDING', 40);
$validTransferStatuses = ['PENDING', 'ACCEPTED', 'REJECTED', 'IN TRANSIT', 'ARRIVED', 'COMPLETED', 'CANCELLED'];
if (!in_array($transferStatus, $validTransferStatuses, true)) {
    $transferStatus = 'PENDING';
}

$currentRole = $_SESSION['role'] ?? '';
$currentDepartment = canonicalDepartment($_SESSION['department'] ?? '');
$canViewAllDepartments = hasRole(['SUPER_ADMIN', 'ADMIN', 'CHIEF_NURSE', 'SUPERVISOR_NURSE']);
if (!$canViewAllDepartments && $selectedDepartment === 'ALL') {
    $selectedDepartment = $currentDepartment ?: 'ALL';
}
$canShowPatientNames = hasRole(['SUPER_ADMIN', 'CHIEF_NURSE', 'SUPERVISOR_NURSE', 'HEAD_NURSE']);

$coverageStart = $startDate . ' 00:00:00';
$coverageEnd = $endDate . ' 23:59:59';
$dateParams = [$startDate, $endDate];
$dateTypes = 'ss';
$activeStatuses = activePatientStatuses();
$dashboardCensusStatuses = array_values(array_unique(array_merge($activeStatuses, ['for_admission', 'admit_inpatient', 'admission'])));
$activeStatusList = dashboardSqlList($conn, $activeStatuses);
$dashboardCensusStatusList = dashboardSqlList($conn, $dashboardCensusStatuses);
$dischargeStatuses = ['discharge', 'discharged', 'trans_out', 'referred', 'ama', 'hama', 'thoc', 'absconded', 'dama', 'doa', 'death', 'er_death', 'expired'];
$dischargeStatusList = dashboardSqlList($conn, $dischargeStatuses);
$inactiveStatusList = dashboardSqlList($conn, inactivePatientStatuses());

$cssrSummary = cssrDashboardSummary();

if ($canViewCssrOnly): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSSR Request Summary - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script>
<link rel="stylesheet" href="../public/css/nursync-overview.css?v=3"></head>
<body class="module-ui">
    <nav class="navbar">
        <div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div>
        <div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($_SESSION['first_name'] ?? $_SESSION['username']); ?> (<?php echo h(roleLabel($currentRole)); ?>)</span></div>
    </nav>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <main class="main-content">
            <section class="page-header"><h2>CSSR Request Summary</h2><p class="compact-meta">Patient clinical dashboard data is hidden for CSSR roles.</p></section>
            <section class="stats-grid">
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Pending RIS Requests</span><span class="stat-value"><?php echo h($cssrSummary['pending']); ?></span></div></div>
                <div class="stat-card available"><div class="stat-info"><span class="stat-label">Approved Requests</span><span class="stat-value"><?php echo h($cssrSummary['approved']); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Released Today</span><span class="stat-value"><?php echo h($cssrSummary['released_today']); ?></span></div></div>
                <div class="stat-card waiting"><div class="stat-info"><span class="stat-label">Low Stock Items</span><span class="stat-value"><?php echo h($cssrSummary['low_stock']); ?></span></div></div>
            </section>
            <a href="/Nursing_System/CSSR_INVENTORY/" class="btn btn-primary">Open CSSR Inventory</a>
        </main>
    </div>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
<?php exit; endif;

$bedRows = dashboardLiveBedSummaries($conn);
$bedByArea = [];
foreach ($bedRows as $row) {
    $bedByArea[$row['area_name']] = $row;
}
$availableBeds = array_sum(array_map(fn($row) => max(0, (int) $row['vacant']), $bedRows));
$occupiedBeds = array_sum(array_map(fn($row) => (int) $row['occupied'], $bedRows));

$summary = [
    'current_census' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ($dashboardCensusStatusList)"),
    'admissions' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE DATE(COALESCE(admission_date, arrival_time, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'discharges' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ($dischargeStatusList) AND DATE(updated_at) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'transfer_in' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE DATE(COALESCE(movement_date_time, created_at)) BETWEEN ? AND ? AND (destination_department IS NOT NULL OR to_department IS NOT NULL)", $dateParams, $dateTypes),
    'transfer_out' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE DATE(COALESCE(movement_date_time, created_at)) BETWEEN ? AND ? AND (source_department IS NOT NULL OR from_department IS NOT NULL)", $dateParams, $dateTypes),
    'deaths' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('death','er_death','expired','doa') AND DATE(updated_at) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'hama' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('hama','ama','dama') AND DATE(updated_at) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'er_consultations' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE DATE(COALESCE(arrival_time, triage_date, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'pending_transfer' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE status='PENDING'"),
    'in_transit' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE status='IN TRANSIT'"),
    'available_beds' => $availableBeds,
    'occupied_beds' => $occupiedBeds,
];

$topCards = [
    ['Total Current Census', $summary['current_census'], 'Active ER and admitted patients', 'fa-hospital-user', 'blue'],
    ['Total Admissions', $summary['admissions'], 'Admissions in selected coverage', 'fa-user-plus', 'blue'],
    ['Total Discharges', $summary['discharges'], 'Dispositions and discharges', 'fa-person-walking-arrow-right', 'green'],
    ['Transfer In', $summary['transfer_in'], 'Movement records in coverage', 'fa-right-to-bracket', 'blue'],
    ['Transfer Out', $summary['transfer_out'], 'Outgoing movements in coverage', 'fa-right-from-bracket', 'blue'],
    ['Deaths', $summary['deaths'], 'DOA, death, expired, ER death', 'fa-triangle-exclamation', 'red'],
    ['HAMA', $summary['hama'], 'HAMA, AMA, DAMA cases', 'fa-file-signature', 'orange'],
    ['ER Consultations', $summary['er_consultations'], 'ER registrations in coverage', 'fa-truck-medical', 'blue'],
    ['Pending Transfer Requests', $summary['pending_transfer'], 'Awaiting receiving unit action', 'fa-clock', 'orange'],
    ['Patients in Transit', $summary['in_transit'], 'Accepted and moving patients', 'fa-route', 'blue'],
    ['Available Beds', $summary['available_beds'], 'Available beds from bed trackers', 'fa-bed', 'green'],
    ['Occupied Beds', $summary['occupied_beds'], 'Active occupied beds', 'fa-bed-pulse', 'blue'],
];

$departmentConfigs = [
    'PEDIA' => ["p.ward_department='PEDIA'", 0],
    'OB' => ["p.ward_department='OB'", 0],
    'MEDICAL' => ["p.ward_department='MEDICAL'", 0],
    'SURGERY' => ["p.ward_department IN ('SURGERY','NON-TRAUMA SURGERY','TRAUMA SURGERY')", 0],
    'NEWBORN' => ["p.ward_department='NEWBORN'", 0],
    'MICU' => [null, (int) ($bedByArea['ICU / MICU']['total_bed_capacity'] ?? 5)],
    'NICU' => [null, (int) ($bedByArea['NICU']['total_bed_capacity'] ?? 5)],
    'OR COMPLEX' => [null, null],
    'OPD' => [null, null],
    'LR/DR' => [null, null],
    'ER' => [null, null],
];
$departmentRows = [];
foreach ($departmentConfigs as $department => [$condition, $capacity]) {
    if ($department === 'MICU') {
        $census = dashboardScalar($conn, "SELECT COUNT(*) FROM micu_records WHERE status='ACTIVE'");
        $admission = dashboardScalar($conn, "SELECT COUNT(*) FROM micu_records WHERE DATE(admission_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes);
        $discharged = dashboardScalar($conn, "SELECT COUNT(*) FROM micu_records WHERE status<>'ACTIVE' AND DATE(updated_at) BETWEEN ? AND ?", $dateParams, $dateTypes);
    } elseif ($department === 'NICU') {
        $census = dashboardScalar($conn, "SELECT COUNT(*) FROM nicu_records WHERE status='ACTIVE'");
        $admission = dashboardScalar($conn, "SELECT COUNT(*) FROM nicu_records WHERE DATE(admission_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes);
        $discharged = dashboardScalar($conn, "SELECT COUNT(*) FROM nicu_records WHERE status<>'ACTIVE' AND DATE(updated_at) BETWEEN ? AND ?", $dateParams, $dateTypes);
    } elseif ($department === 'OR COMPLEX') {
        $census = dashboardScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE status IN ('PENDING','ACCEPTED','IN OR','RECOVERY')");
        $admission = dashboardScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE DATE(schedule_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes);
        $discharged = dashboardScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE status IN ('COMPLETED','DONE') AND DATE(updated_at) BETWEEN ? AND ?", $dateParams, $dateTypes);
    } elseif ($department === 'OPD') {
        $census = dashboardScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE status='ACTIVE' AND DATE(consultation_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes);
        $admission = dashboardScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE DATE(consultation_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes);
        $discharged = dashboardScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='COMPLETED CONSULTATION' AND DATE(consultation_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes);
    } elseif ($department === 'LR/DR') {
        $census = dashboardScalar($conn, "SELECT COUNT(*) FROM delivery_room_records WHERE DATE(COALESCE(date_time_of_delivery, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes);
        $admission = $census;
        $discharged = dashboardScalar($conn, "SELECT COUNT(*) FROM delivery_room_records WHERE mother_disposition LIKE '%TRANSFER%' AND DATE(COALESCE(date_time_of_delivery, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes);
    } elseif ($department === 'ER') {
        $census = dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ($activeStatusList)");
        $admission = $summary['er_consultations'];
        $discharged = dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ($dischargeStatusList) AND current_bed IN (SELECT bed_id FROM beds WHERE room_number LIKE 'ER-%') AND DATE(updated_at) BETWEEN ? AND ?", $dateParams, $dateTypes);
    } else {
        $capacity = $capacity ?: null;
        $census = dashboardScalar($conn, "SELECT COUNT(*) FROM patients p WHERE p.status='admit_inpatient' AND $condition");
        $admission = dashboardScalar($conn, "SELECT COUNT(*) FROM patients p WHERE $condition AND DATE(COALESCE(p.admission_date, p.arrival_time, p.created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes);
        $discharged = dashboardScalar($conn, "SELECT COUNT(*) FROM patients p WHERE $condition AND p.status IN ($dischargeStatusList) AND DATE(p.updated_at) BETWEEN ? AND ?", $dateParams, $dateTypes);
    }
    $transferIn = dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (destination_department=? OR to_department=?) AND DATE(COALESCE(movement_date_time, created_at)) BETWEEN ? AND ?", [$department, $department, $startDate, $endDate], 'ssss');
    $transferOut = dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (source_department=? OR from_department=?) AND DATE(COALESCE(movement_date_time, created_at)) BETWEEN ? AND ?", [$department, $department, $startDate, $endDate], 'ssss');
    $status = $capacity && $census > $capacity ? 'OVER CAPACITY' : ($capacity && $census === $capacity ? 'FULL' : 'AVAILABLE');
    $departmentRows[] = [
        'department' => $department,
        'capacity' => $capacity,
        'census' => $census,
        'admission' => $admission,
        'discharged' => $discharged,
        'transfer_in' => $transferIn,
        'transfer_out' => $transferOut,
        'hama' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'ER')=? AND status IN ('hama','ama','dama') AND DATE(updated_at) BETWEEN ? AND ?", [$department, $startDate, $endDate], 'sss'),
        'death' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE COALESCE(ward_department,'ER')=? AND status IN ('death','er_death','expired','doa') AND DATE(updated_at) BETWEEN ? AND ?", [$department, $startDate, $endDate], 'sss'),
        'status' => $status,
    ];
}

$transferStats = [
    'Pending Transfer Requests' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE status='PENDING'"),
    'Accepted Transfers' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE status='ACCEPTED'"),
    'Rejected Transfers' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE status IN ('REJECTED','CANCELLED')"),
    'Patients In Transit' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE status='IN TRANSIT'"),
    'Awaiting Bed Assignment' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('for_admission','admit_inpatient') AND current_bed IS NULL"),
    'Completed Transfers Today' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE status='COMPLETED' AND DATE(COALESCE(movement_date_time, created_at))=CURDATE()"),
];

$transferRows = dashboardRows($conn, "
    SELECT pm.*, p.permanent_patient_id, p.patient_code,
        CONCAT(p.last_name, ', ', p.first_name, IFNULL(CONCAT(' ', p.middle_name), '')) AS patient_name,
        requester.username AS requested_by_name
    FROM patient_movements pm
    INNER JOIN patients p ON p.patient_id=pm.patient_id
    LEFT JOIN users requester ON requester.user_id=pm.requested_by
    WHERE pm.status=?
    ORDER BY COALESCE(pm.movement_date_time, pm.created_at) DESC
    LIMIT 10
", [$transferStatus], 's');

$erActive = "('triage','waiting_for_consultation','consultation','treatment','waiting_results','ready_for_disposition','for_admission')";
$erCards = [
    'Total ER Consultations' => $summary['er_consultations'],
    'ER Admissions' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('for_admission','admit_inpatient') AND DATE(COALESCE(arrival_time, admission_date, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'ER Discharged' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('discharge','discharged') AND DATE(updated_at) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'ER Pending' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN $erActive"),
    'ER On Observation' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('treatment','waiting_results')"),
    'Turnaround Time < 4 Hours' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE DATE(COALESCE(arrival_time, created_at)) BETWEEN ? AND ? AND TIMESTAMPDIFF(HOUR, COALESCE(arrival_time, created_at), COALESCE(updated_at, NOW())) < 4", $dateParams, $dateTypes),
    'Turnaround Time > 4 Hours' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE DATE(COALESCE(arrival_time, created_at)) BETWEEN ? AND ? AND TIMESTAMPDIFF(HOUR, COALESCE(arrival_time, created_at), COALESCE(updated_at, NOW())) >= 4", $dateParams, $dateTypes),
];

$erBreakdowns = [
    'Medical' => "p.ward_department='MEDICAL'",
    'Pedia' => "p.ward_department='PEDIA'",
    'Newborn' => "p.ward_department='NEWBORN'",
    'OB' => "p.ward_department='OB'",
    'Surgery' => "p.ward_department IN ('NON-TRAUMA SURGERY','TRAUMA SURGERY')",
    'Trauma' => "p.ward_department='TRAUMA SURGERY'",
    'Non-Trauma' => "p.ward_department='NON-TRAUMA SURGERY'",
];
$erBreakdownRows = [];
foreach ($erBreakdowns as $label => $condition) {
    $base = "FROM patients p LEFT JOIN triage t ON t.patient_id=p.patient_id WHERE $condition AND DATE(COALESCE(p.arrival_time, p.created_at)) BETWEEN ? AND ?";
    $erBreakdownRows[] = [
        'label' => $label,
        'consult' => dashboardScalar($conn, "SELECT COUNT(*) $base", $dateParams, $dateTypes),
        'admission' => dashboardScalar($conn, "SELECT COUNT(*) $base AND p.status IN ('for_admission','admit_inpatient')", $dateParams, $dateTypes),
        'discharged' => dashboardScalar($conn, "SELECT COUNT(*) $base AND p.status IN ('discharge','discharged')", $dateParams, $dateTypes),
        'transfer' => dashboardScalar($conn, "SELECT COUNT(*) $base AND p.status IN ('referred','trans_out','thoc')", $dateParams, $dateTypes),
        'hama' => dashboardScalar($conn, "SELECT COUNT(*) $base AND p.status IN ('hama','ama','dama')", $dateParams, $dateTypes),
        'observation' => dashboardScalar($conn, "SELECT COUNT(*) $base AND p.status IN ('treatment','waiting_results')", $dateParams, $dateTypes),
        'lt4' => dashboardScalar($conn, "SELECT COUNT(*) $base AND TIMESTAMPDIFF(HOUR, COALESCE(p.arrival_time, p.created_at), COALESCE(p.updated_at, NOW())) < 4", $dateParams, $dateTypes),
        'gt4' => dashboardScalar($conn, "SELECT COUNT(*) $base AND TIMESTAMPDIFF(HOUR, COALESCE(p.arrival_time, p.created_at), COALESCE(p.updated_at, NOW())) >= 4", $dateParams, $dateTypes),
        'pending' => dashboardScalar($conn, "SELECT COUNT(*) $base AND p.status IN $erActive", $dateParams, $dateTypes),
    ];
}

$opdCards = [
    'Total OPD Consultations' => dashboardScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE DATE(consultation_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'Completed Consultations' => dashboardScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='COMPLETED CONSULTATION' AND DATE(consultation_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'Referred to ER' => dashboardScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='REFERRED TO ER' AND DATE(consultation_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'For Admission' => dashboardScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='FOR ADMISSION TO WARD' AND DATE(consultation_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'For OR Schedule' => dashboardScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='FOR OR SCHEDULE' AND DATE(consultation_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'Follow-Up Patients' => dashboardScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='FOR FOLLOW-UP' AND DATE(consultation_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'Referred to Other Facility' => dashboardScalar($conn, "SELECT COUNT(*) FROM opd_records WHERE disposition='REFERRED TO OTHER FACILITY' AND DATE(consultation_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
];

$ldrCards = [
    'LR/DR Census' => dashboardScalar($conn, "SELECT COUNT(*) FROM delivery_room_records WHERE DATE(COALESCE(date_time_of_delivery, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'Mothers for Ward Transfer' => dashboardScalar($conn, "SELECT COUNT(*) FROM delivery_room_records WHERE mother_disposition='TRANSFER TO WARD' AND DATE(COALESCE(date_time_of_delivery, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'Babies for NICU Transfer' => dashboardScalar($conn, "SELECT COUNT(*) FROM delivery_room_records WHERE baby_disposition='TRANSFER TO NICU' AND DATE(COALESCE(date_time_of_delivery, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'Delivery Records Today' => dashboardScalar($conn, "SELECT COUNT(*) FROM delivery_room_records WHERE DATE(COALESCE(date_time_of_delivery, created_at))=CURDATE()"),
    'Pending Mother Transfer' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (source_department='LR_DR' OR from_department='LR_DR') AND (destination_department='WARD' OR to_department='WARD') AND status='PENDING'"),
    'Pending Baby Transfer' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (source_department='LR_DR' OR from_department='LR_DR') AND (destination_department='NICU' OR to_department='NICU') AND status='PENDING'"),
];

$orCards = [
    'OR Scheduled Cases' => dashboardScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE DATE(schedule_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'OR Pending Cases' => dashboardScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE status IN ('PENDING','ACCEPTED') AND DATE(schedule_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'OR Completed Cases' => dashboardScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE status IN ('COMPLETED','DONE') AND DATE(COALESCE(updated_at, schedule_date_time)) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'Emergency Cases' => dashboardScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE urgency_type='EMERGENCY' AND DATE(schedule_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'Elective Cases' => dashboardScalar($conn, "SELECT COUNT(*) FROM or_schedule WHERE urgency_type='ELECTIVE' AND DATE(schedule_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'Post-Op Transfers' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (source_department='OR' OR from_department='OR') AND DATE(COALESCE(movement_date_time, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'OR to MICU Transfers' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (source_department='OR' OR from_department='OR') AND (destination_department='MICU' OR destination_department='ICU' OR to_department='MICU' OR to_department='ICU') AND DATE(COALESCE(movement_date_time, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'OR to Ward Transfers' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (source_department='OR' OR from_department='OR') AND (destination_department='WARD' OR to_department='WARD') AND DATE(COALESCE(movement_date_time, created_at)) BETWEEN ? AND ?", $dateParams, $dateTypes),
];

$micuCards = [
    'MICU Census' => dashboardScalar($conn, "SELECT COUNT(*) FROM micu_records WHERE status='ACTIVE'"),
    'MICU Admissions' => dashboardScalar($conn, "SELECT COUNT(*) FROM micu_records WHERE DATE(admission_date_time) BETWEEN ? AND ?", $dateParams, $dateTypes),
    'MICU Transfer-In Requests' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (destination_department IN ('MICU','ICU') OR to_department IN ('MICU','ICU')) AND status='PENDING'"),
    'MICU Transfer-Out Requests' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (source_department IN ('MICU','ICU') OR from_department IN ('MICU','ICU')) AND status='PENDING'"),
    'MICU Vacant Beds' => ($bedByArea['ICU / MICU']['vacant'] ?? 0) . ' / ' . ($bedByArea['ICU / MICU']['total_bed_capacity'] ?? 5) . ' Beds',
    'MICU Status' => $bedByArea['ICU / MICU']['status'] ?? 'AVAILABLE',
];
$nicuCards = [
    'NICU Census' => dashboardScalar($conn, "SELECT COUNT(*) FROM nicu_records WHERE status='ACTIVE'"),
    'Inborn Patients' => dashboardScalar($conn, "SELECT COUNT(*) FROM nicu_records WHERE inborn_or_outborn='INBORN' AND status='ACTIVE'"),
    'Outborn Patients' => dashboardScalar($conn, "SELECT COUNT(*) FROM nicu_records WHERE inborn_or_outborn='OUTBORN' AND status='ACTIVE'"),
    'NICU Transfer-In Requests' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (destination_department='NICU' OR to_department='NICU') AND status='PENDING'"),
    'NICU Transfer-Out Requests' => dashboardScalar($conn, "SELECT COUNT(*) FROM patient_movements WHERE (source_department='NICU' OR from_department='NICU') AND status='PENDING'"),
    'NICU Vacant Beds' => ($bedByArea['NICU']['vacant'] ?? 0) . ' / ' . ($bedByArea['NICU']['total_bed_capacity'] ?? 5) . ' Beds',
    'NICU Status' => $bedByArea['NICU']['status'] ?? 'AVAILABLE',
];

$wardTatRows = [];
$obPediaBedCondition = 'b.room_number IN (' . dashboardSqlValueList($conn, array_values(array_unique(obPediaWardRoomNumbers()))) . ')';
foreach ([
    '2nd Floor Ward' => [
        'bed_condition' => $obPediaBedCondition,
        'ward_codes' => ['PEDIA', 'OB', 'NEWBORN'],
    ],
    '3rd Floor Ward' => [
        'bed_condition' => "b.room_number LIKE 'WARD-03%' OR b.room_number LIKE 'WARD-04%'",
        'ward_codes' => ['MEDICAL', 'NON-TRAUMA SURGERY', 'TRAUMA SURGERY'],
    ],
] as $ward => $wardConfig) {
    $bedCondition = $wardConfig['bed_condition'];
    $wardCodes = $wardConfig['ward_codes'];
    $wardPlaceholders = implode(',', array_fill(0, count($wardCodes), '?'));
    $wardDateParams = array_merge($wardCodes, [$startDate, $endDate]);
    $wardDateTypes = str_repeat('s', count($wardCodes)) . 'ss';
    $totalDischarges = dashboardScalar($conn, "SELECT COUNT(*) FROM ward_discharge_tat_records WHERE ward IN ($wardPlaceholders) AND DATE(disposition_at) BETWEEN ? AND ?", $wardDateParams, $wardDateTypes);
    $lt6 = dashboardScalar($conn, "SELECT COUNT(*) FROM ward_discharge_tat_records WHERE ward IN ($wardPlaceholders) AND actual_discharge_tat_minutes < 360 AND DATE(disposition_at) BETWEEN ? AND ?", $wardDateParams, $wardDateTypes);
    $gt6 = max(0, $totalDischarges - $lt6);
    $pendingDischarge = dashboardScalar($conn, "SELECT COUNT(*) FROM patients p LEFT JOIN beds b ON b.bed_id=p.current_bed WHERE ($bedCondition) AND p.status='admit_inpatient'");
    $pendingBilling = dashboardScalar($conn, "SELECT COUNT(*) FROM ward_discharge_tat_records WHERE ward IN ($wardPlaceholders) AND signed_soa_received_at IS NULL AND DATE(disposition_at) BETWEEN ? AND ?", $wardDateParams, $wardDateTypes);
    $wardTatRows[] = [
        'ward' => $ward,
        'lt6' => $lt6,
        'gt6' => $gt6,
        'target' => '100%',
        'accomplishment' => dashboardPercent($lt6, max(1, $totalDischarges)) . '%',
        'pending_discharge' => $pendingDischarge,
        'pending_billing' => $pendingBilling,
        'remarks' => $gt6 > 0 ? 'Needs follow-up' : 'On target',
    ];
}

$reportableCases = [
    ['category' => 'Category I Immediately Notifiable Cases', 'count' => dashboardScalar($conn, "SELECT COUNT(*) FROM triage WHERE (medicolegal=1 OR ip=1) AND DATE(COALESCE(updated_at, triage_time)) BETWEEN ? AND ?", $dateParams, $dateTypes)],
    ['category' => 'Category II Weekly Notifiable Cases', 'count' => dashboardScalar($conn, "SELECT COUNT(*) FROM patients WHERE final_diagnosis IS NOT NULL AND final_diagnosis<>'' AND DATE(updated_at) BETWEEN ? AND ?", $dateParams, $dateTypes)],
];
$activeReportableCases = array_values(array_filter($reportableCases, fn($case) => (int) $case['count'] > 0));

$alertItems = [];
foreach ($bedRows as $bed) {
    if (in_array($bed['status'], ['FULL', 'OVER CAPACITY'], true)) {
        $alertItems[] = [$bed['area_name'], $bed['status'], $bed['vacant_label']];
    }
}
if ($summary['pending_transfer'] > 0) { $alertItems[] = ['Pending Transfer Acceptance', 'PENDING', $summary['pending_transfer'] . ' request(s)']; }
if ($transferStats['Awaiting Bed Assignment'] > 0) { $alertItems[] = ['Awaiting Bed Assignment', 'PENDING', $transferStats['Awaiting Bed Assignment'] . ' patient(s)']; }
if ($orCards['OR Pending Cases'] > 0) { $alertItems[] = ['Pending OR Cases', 'PENDING', $orCards['OR Pending Cases'] . ' case(s)']; }
if ($erCards['Turnaround Time > 4 Hours'] > 0) { $alertItems[] = ['ER Patients > 4 Hours', 'WARNING', $erCards['Turnaround Time > 4 Hours'] . ' patient(s)']; }
foreach ($activeReportableCases as $case) {
    $alertItems[] = [$case['category'], 'REPORTABLE', $case['count'] . ' case(s)'];
}

$displayName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = $_SESSION['username'] ?? 'Nurse';
}
$roleText = roleLabel($currentRole);
$departmentText = $currentDepartment ?: 'All assigned units';
$dateLabel = $startDate === $endDate ? date('F d, Y', strtotime($startDate)) : date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nursing Dashboard - Nursing Information System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        :root {
            --nd-bg: #ECF4F7;
            --nd-panel: #ffffff;
            --nd-line: #D5E6EC;
            --nd-text: #163747;
            --nd-muted: #58717E;
            --nd-blue: #1677A3;
            --nd-navy: #082F49;
            --nd-green: #2CB7A5;
            --nd-gold: #E8B557;
            --nd-red: #D92727;
        }
        body { background: var(--nd-bg); color: var(--nd-text); }
        .dashboard-container { background: var(--nd-bg); }
        .main-content.nursing-dashboard { padding: 22px; background: var(--nd-bg); }
        .nd-header, .nd-panel, .nd-card {
            background: var(--nd-panel);
            border: 1px solid var(--nd-line);
            border-radius: 8px;
            box-shadow: 0 10px 26px rgba(18, 52, 81, 0.07);
        }
        .nd-header { padding: 20px; margin-bottom: 16px; }
        .nd-title-row { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
        .nd-title-row h2 { margin: 0 0 6px; color: var(--nd-navy); font-size: 26px; letter-spacing: 0; }
        .nd-meta { display: flex; flex-wrap: wrap; gap: 8px; color: var(--nd-muted); font-size: 13px; }
        .nd-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: #f1f6fb; border: 1px solid #e0ebf5; font-weight: 700; }
        .nd-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .nd-filter-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding: 88px 20px 24px;
            background: rgba(18, 52, 81, 0.48);
            z-index: 2000;
            overflow-y: auto;
        }
        .nd-filter-modal.open { display: flex; }
        .nd-filter-card {
            width: min(720px, 100%);
            max-height: calc(100vh - 112px);
            overflow-y: auto;
            background: #fff;
            border: 1px solid var(--nd-line);
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(18, 52, 81, 0.24);
            padding: 18px;
            margin: 0 auto;
        }
        .nd-filter-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; }
        .nd-filter-header h3 { margin: 0; color: var(--nd-navy); font-size: 18px; }
        .nd-filter-close {
            width: 36px;
            height: 36px;
            border: 1px solid var(--nd-line);
            border-radius: 8px;
            background: #f7fbff;
            color: var(--nd-navy);
            cursor: pointer;
        }
        .nd-filters { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; align-items: end; }
        .nd-filters .form-group { margin: 0; }
        .nd-filter-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }
        .nd-section { margin-top: 16px; }
        .nd-section-title { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 10px; }
        .nd-section-title h3 { margin: 0; color: var(--nd-navy); font-size: 18px; }
        .nd-section-title span { color: var(--nd-muted); font-size: 12px; }
        .nd-grid { display: grid; gap: 12px; }
        .nd-summary-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .nd-card { padding: 14px; border-left: 4px solid var(--nd-blue); min-height: 118px; }
        .nd-card.green { border-left-color: var(--nd-green); }
        .nd-card.orange { border-left-color: var(--nd-gold); }
        .nd-card.red { border-left-color: var(--nd-red); }
        .nd-card-top { display: flex; justify-content: space-between; align-items: center; gap: 10px; color: var(--nd-muted); }
        .nd-icon { width: 34px; height: 34px; display: inline-grid; place-items: center; border-radius: 8px; background: #F4F7FA; color: var(--nd-blue); }
        .nd-value { display: block; margin-top: 12px; font-size: 28px; font-weight: 900; color: var(--nd-navy); line-height: 1; }
        .nd-label { margin-top: 8px; font-size: 13px; font-weight: 900; color: #0B3C5D; }
        .nd-desc { margin-top: 4px; font-size: 12px; color: var(--nd-muted); }
        .nd-two-col { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(320px, .8fr); gap: 16px; }
        .nd-panel { padding: 16px; min-width: 0; }
        .bed-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .bed-meter { height: 8px; background: #e8eef5; border-radius: 999px; overflow: hidden; margin: 12px 0; }
        .bed-meter span { display: block; height: 100%; background: var(--nd-blue); border-radius: inherit; }
        .metric-list { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .mini-metric { border: 1px solid #e4edf5; background: #fbfdff; border-radius: 8px; padding: 12px; }
        .mini-metric span { display: block; color: var(--nd-muted); font-size: 12px; font-weight: 800; }
        .mini-metric strong { display: block; margin-top: 6px; color: var(--nd-navy); font-size: 22px; }
        .status-badge { display: inline-flex; align-items: center; width: fit-content; padding: 4px 9px; border-radius: 999px; font-size: 11px; font-weight: 900; }
        .status-success { background: #EAF6FF; color: #0B3C5D; }
        .status-warning { background: #fff7e3; color: #8a5a00; }
        .status-danger { background: #fdeceb; color: #9f1d14; }
        .status-info { background: #F4F7FA; color: #0B3C5D; }
        .table-responsive { overflow-x: auto; }
        .nd-table { width: 100%; border-collapse: collapse; min-width: 760px; }
        .nd-table th, .nd-table td { border-bottom: 1px solid #e7eef6; padding: 10px; text-align: left; font-size: 13px; vertical-align: top; }
        .nd-table th { color: #435c73; font-size: 11px; text-transform: uppercase; letter-spacing: .02em; background: #f7fbff; }
        .nd-table td strong { color: var(--nd-navy); }
        .chart-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .bar-chart { display: grid; gap: 9px; }
        .bar-row { display: grid; grid-template-columns: 96px minmax(0, 1fr) 40px; gap: 8px; align-items: center; font-size: 12px; }
        .bar-track { height: 10px; background: #e8eef5; border-radius: 999px; overflow: hidden; }
        .bar-fill { height: 100%; background: var(--nd-blue); border-radius: inherit; }
        .donut { width: 132px; height: 132px; border-radius: 50%; margin: 8px auto; background: conic-gradient(var(--nd-blue) 0 var(--done), var(--nd-gold) var(--done) var(--pending), #e7eef6 var(--pending) 100%); box-shadow: inset 0 0 0 28px #fff; }
        .alert-list { display: grid; gap: 10px; }
        .alert-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; padding: 11px; border: 1px solid #e7eef6; border-radius: 8px; background: #fbfdff; }
        .empty-state { padding: 16px; border: 1px dashed #cddbea; border-radius: 8px; text-align: center; color: var(--nd-muted); background: #fbfdff; }
        .reportable-list { display: grid; gap: 10px; }
        @media print { .navbar, .sidebar, .nd-actions, .nd-filter-modal { display: none !important; } .dashboard-container { display: block; } .main-content.nursing-dashboard { padding: 0; } }
        @media (max-width: 1280px) { .nd-summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .bed-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
        @media (max-width: 980px) { .nd-two-col, .chart-grid { grid-template-columns: 1fr; } .nd-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); } .metric-list { grid-template-columns: repeat(2, minmax(0,1fr)); } }
        @media (max-width: 640px) { .main-content.nursing-dashboard { padding: 14px; } .nd-summary-grid, .bed-grid, .metric-list, .nd-filters { grid-template-columns: 1fr; } .nd-title-row { display: block; } .nd-actions { margin-top: 12px; } .nd-filter-modal { padding: 78px 12px 16px; } .nd-filter-card { max-height: calc(100vh - 94px); } .nd-filter-actions { display: grid; } }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script>
<link rel="stylesheet" href="../public/css/nursync-overview.css?v=3"></head>
<body class="module-ui">
    <nav class="navbar">
        <div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div>
        <div class="navbar-menu"><span class="user-info">Welcome, <?php echo h($displayName); ?> (<?php echo h($roleText); ?>)</span><a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a></div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <main class="main-content nursing-dashboard" id="dashboard-export-area">
            <section class="nd-header">
                <div class="nd-title-row">
                    <div>
                        <h2>Nursing Dashboard</h2>
                        <div class="nd-meta">
                            <span class="nd-chip"><i class="fa-solid fa-calendar-days"></i><?php echo h($dateLabel); ?></span>
                            <span class="nd-chip"><i class="fa-solid fa-user-nurse"></i><?php echo h($displayName); ?></span>
                            <span class="nd-chip"><?php echo h($roleText); ?></span>
                            <span class="nd-chip"><?php echo h($departmentText); ?></span>
                        </div>
                    </div>
                    <div class="nd-actions">
                        <a class="btn btn-secondary" href="dashboard.php"><i class="fa-solid fa-rotate"></i> Refresh</a>
                        <button class="btn btn-secondary" type="button" data-open-dashboard-filters><i class="fa-solid fa-filter"></i> Filters</button>
                        <button class="btn btn-primary" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Export</button>
                    </div>
                </div>
            </section>

            <section class="nd-section">
                <div class="nd-section-title"><h3>Daily Operations Summary</h3><span>Essential nursing indicators</span></div>
                <div class="nd-grid nd-summary-grid">
                    <?php foreach ($topCards as $card): ?>
                        <article class="nd-card <?php echo h($card[4]); ?>">
                            <div class="nd-card-top"><span class="nd-icon"><i class="fa-solid <?php echo h($card[3]); ?>"></i></span></div>
                            <span class="nd-value"><?php echo h($card[1]); ?></span>
                            <div class="nd-label"><?php echo h($card[0]); ?></div>
                            <div class="nd-desc"><?php echo h($card[2]); ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="nd-section">
                <div class="nd-section-title"><h3>Bed Availability</h3><span>Fixed capacity, calculated occupancy</span></div>
                <div class="bed-grid">
                    <?php foreach ($bedRows as $bed): ?>
                        <?php $pct = (float) $bed['occupancy_percentage']; ?>
                        <article class="nd-card">
                            <div class="nd-card-top"><strong><?php echo h(nisDepartmentLabel($bed['area_name'])); ?></strong><span class="status-badge status-<?php echo h(dashboardBadgeClass($bed['status'])); ?>"><?php echo h($bed['status']); ?></span></div>
                            <div class="bed-meter"><span style="width: <?php echo h(min(100, $pct)); ?>%;"></span></div>
                            <div class="metric-list" style="grid-template-columns: repeat(2,minmax(0,1fr));">
                                <div class="mini-metric"><span>Total Beds</span><strong><?php echo h($bed['total_bed_capacity']); ?></strong></div>
                                <div class="mini-metric"><span>Occupied</span><strong><?php echo h($bed['occupied']); ?></strong></div>
                                <div class="mini-metric"><span>Available Beds</span><strong><?php echo h($bed['vacant']); ?> / <?php echo h($bed['total_bed_capacity']); ?></strong></div>
                                <div class="mini-metric"><span>Occupancy</span><strong><?php echo h(number_format($pct, 2)); ?>%</strong></div>
                            </div>
                            <div class="nd-desc" style="margin-top:10px;"><?php echo h($bed['vacant_label']); ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="nd-section <?= $canSeeSystemAlerts ? 'nd-two-col' : '' ?>">
                <div class="nd-panel">
                    <div class="nd-section-title"><h3>Patient Census by Department</h3><span>Totals only on main dashboard</span></div>
                    <div class="table-responsive">
                        <table class="nd-table export-table">
                            <thead><tr><th>Department</th><th>Capacity</th><th>Census</th><th>Admission</th><th>Discharged</th><th>Transfer In</th><th>Transfer Out</th><th>HAMA</th><th>Death</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($departmentRows as $row): ?>
                                    <tr>
                                        <td><strong><?php echo h($row['department']); ?></strong></td>
                                        <td><?php echo h($row['capacity'] ?? 'N/A'); ?></td>
                                        <td><?php echo h($row['census']); ?></td>
                                        <td><?php echo h($row['admission']); ?></td>
                                        <td><?php echo h($row['discharged']); ?></td>
                                        <td><?php echo h($row['transfer_in']); ?></td>
                                        <td><?php echo h($row['transfer_out']); ?></td>
                                        <td><?php echo h($row['hama']); ?></td>
                                        <td><?php echo h($row['death']); ?></td>
                                        <td><span class="status-badge status-<?php echo h(dashboardBadgeClass($row['status'])); ?>"><?php echo h($row['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($canSeeSystemAlerts): ?><aside class="nd-panel" id="alerts">
                    <div class="nd-section-title"><h3>Alerts and Tasks</h3><span>Action-focused indicators</span></div>
                    <div class="alert-list">
                        <?php foreach ($alertItems as $alert): ?>
                            <div class="alert-item"><div><strong><?php echo h($alert[0]); ?></strong><div class="nd-desc"><?php echo h($alert[2]); ?></div></div><span class="status-badge status-<?php echo h(dashboardBadgeClass($alert[1])); ?>"><?php echo h($alert[1]); ?></span></div>
                        <?php endforeach; ?>
                        <?php if (empty($alertItems)): ?><div class="empty-state">No active dashboard alerts.</div><?php endif; ?>
                    </div>
                </aside><?php endif; ?>
            </section>

            <section class="nd-section nd-panel">
                <div class="nd-section-title"><h3>Patient Transfer Monitoring</h3><span>Receiving department must accept and assign a bed in the transfer workflow</span></div>
                <div class="metric-list">
                    <?php foreach ($transferStats as $label => $value): ?><div class="mini-metric"><span><?php echo h($label); ?></span><strong><?php echo h($value); ?></strong></div><?php endforeach; ?>
                </div>
                <div class="table-responsive" style="margin-top:12px;">
                    <table class="nd-table export-table">
                        <thead><tr><th>Transfer No.</th><th>Patient / Hospital No.</th><th>From</th><th>To</th><th>Reason</th><th>Requested By</th><th>Requested Time</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($transferRows as $row): ?>
                                <tr>
                                    <td><?php echo h('TR-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT)); ?></td>
                                    <td><strong><?php echo h($canShowPatientNames ? $row['patient_name'] : 'Restricted'); ?></strong><br><span class="nd-desc"><?php echo h($row['permanent_patient_id'] ?: $row['patient_code']); ?></span></td>
                                    <td><?php echo h($row['from_department'] ?: $row['source_department']); ?></td>
                                    <td><?php echo h($row['to_department'] ?: $row['destination_department']); ?></td>
                                    <td><?php echo h($row['reason'] ?: ($row['remarks'] ?: 'N/A')); ?></td>
                                    <td><?php echo h($row['requested_by_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo h(date('M d, Y h:i A', strtotime($row['movement_date_time'] ?? $row['created_at']))); ?></td>
                                    <td><span class="status-badge status-<?php echo h(dashboardBadgeClass($row['status'])); ?>"><?php echo h($row['status']); ?></span></td>
                                    <td><a class="btn btn-sm btn-secondary" href="movement_history.php?status=<?php echo h($row['status']); ?>">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transferRows)): ?><tr><td colspan="9" class="empty-state">No pending transfer requests.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="nd-section nd-two-col">
                <div class="nd-panel">
                    <div class="nd-section-title"><h3>ER Statistics</h3><span>Consultation and turnaround monitoring</span></div>
                    <div class="metric-list"><?php foreach ($erCards as $label => $value): ?><div class="mini-metric"><span><?php echo h($label); ?></span><strong><?php echo h($value); ?></strong></div><?php endforeach; ?></div>
                    <div class="table-responsive" style="margin-top:12px;">
                        <table class="nd-table export-table">
                            <thead><tr><th>Area</th><th>ER Consult</th><th>Admission</th><th>Discharged</th><th>Transfer</th><th>HAMA</th><th>Observation</th><th>&lt; 4 Hours</th><th>&gt; 4 Hours</th><th>Pending</th></tr></thead>
                            <tbody><?php foreach ($erBreakdownRows as $row): ?><tr><td><strong><?php echo h($row['label']); ?></strong></td><td><?php echo h($row['consult']); ?></td><td><?php echo h($row['admission']); ?></td><td><?php echo h($row['discharged']); ?></td><td><?php echo h($row['transfer']); ?></td><td><?php echo h($row['hama']); ?></td><td><?php echo h($row['observation']); ?></td><td><?php echo h($row['lt4']); ?></td><td><?php echo h($row['gt4']); ?></td><td><?php echo h($row['pending']); ?></td></tr><?php endforeach; ?></tbody>
                        </table>
                    </div>
                </div>
                <div class="nd-panel">
                    <div class="nd-section-title"><h3>Charts</h3><span>Compact visual summary</span></div>
                    <div class="chart-grid">
                        <div>
                            <strong>Census by Department</strong>
                            <div class="bar-chart"><?php $maxCensus = max(1, max(array_column($departmentRows, 'census'))); foreach ($departmentRows as $row): ?><div class="bar-row"><span><?php echo h($row['department']); ?></span><div class="bar-track"><div class="bar-fill" style="width: <?php echo h(($row['census'] / $maxCensus) * 100); ?>%;"></div></div><strong><?php echo h($row['census']); ?></strong></div><?php endforeach; ?></div>
                        </div>
                        <div>
                            <strong>Transfer Status</strong>
                            <?php $transferTotal = max(1, array_sum($transferStats)); $donePct = ($transferStats['Completed Transfers Today'] / $transferTotal) * 100; $pendingPct = (($transferStats['Completed Transfers Today'] + $transferStats['Pending Transfer Requests']) / $transferTotal) * 100; ?>
                            <div class="donut" style="--done: <?php echo h($donePct); ?>%; --pending: <?php echo h($pendingPct); ?>%;"></div>
                            <div class="nd-desc" style="text-align:center;">Completed today / pending / other active transfers</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="nd-section nd-panel">
                <div class="nd-section-title"><h3>OPD, LR/DR, OR, MICU, NICU</h3><span>Module-level operating indicators</span></div>
                <div class="metric-list">
                    <?php foreach (array_merge($opdCards, $ldrCards, $orCards, $micuCards, $nicuCards) as $label => $value): ?>
                        <div class="mini-metric"><span><?php echo h($label); ?></span><strong><?php echo h($value); ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="nd-section nd-two-col">
                <div class="nd-panel">
                    <div class="nd-section-title"><h3>Ward Discharge TAT</h3><span>2nd and 3rd floor ward discharge monitoring</span></div>
                    <div class="table-responsive">
                        <table class="nd-table export-table">
                            <thead><tr><th>Ward</th><th>&lt; 6 Hours</th><th>&gt; 6 Hours</th><th>Target</th><th>Accomplishment</th><th>Pending Discharge</th><th>Pending Billing</th><th>Remarks</th></tr></thead>
                            <tbody><?php foreach ($wardTatRows as $row): ?><tr><td><strong><?php echo h(nisDepartmentLabel($row['ward'])); ?></strong></td><td><?php echo h($row['lt6']); ?></td><td><?php echo h($row['gt6']); ?></td><td><?php echo h($row['target']); ?></td><td><?php echo h($row['accomplishment']); ?></td><td><?php echo h($row['pending_discharge']); ?></td><td><?php echo h($row['pending_billing']); ?></td><td><?php echo h($row['remarks']); ?></td></tr><?php endforeach; ?></tbody>
                        </table>
                    </div>
                </div>
                <div class="nd-panel">
                    <div class="nd-section-title"><h3>Reportable Cases</h3><span>Counts greater than zero are highlighted</span></div>
                    <div class="reportable-list">
                        <?php foreach ($activeReportableCases as $case): ?>
                            <div class="alert-item"><div><strong><?php echo h($case['category']); ?></strong><div class="nd-desc">Requires report monitoring</div></div><span class="status-badge status-danger"><?php echo h($case['count']); ?></span></div>
                        <?php endforeach; ?>
                        <?php if (empty($activeReportableCases)): ?><div class="empty-state">No reportable cases recorded.</div><?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <div class="nd-filter-modal" id="dashboard-filter-modal" aria-hidden="true">
        <div class="nd-filter-card" role="dialog" aria-modal="true" aria-labelledby="dashboard-filter-title">
            <div class="nd-filter-header">
                <h3 id="dashboard-filter-title">Dashboard Filters</h3>
                <button class="nd-filter-close" type="button" data-close-dashboard-filters aria-label="Close filters"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form class="nd-filters" method="GET">
                <div class="form-group"><label>Date</label><input type="date" name="date" value="<?php echo h($_GET['date'] ?? $startDate); ?>"></div>
                <div class="form-group"><label>Start Date</label><input type="date" name="start_date" value="<?php echo h($startDate); ?>"></div>
                <div class="form-group"><label>End Date</label><input type="date" name="end_date" value="<?php echo h($endDate); ?>"></div>
                <div class="form-group"><label>Department</label><select name="department"><option value="ALL">All Departments</option><?php foreach (departmentOptions() as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo $selectedDepartment === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select></div>
                <div class="nd-filter-actions">
                    <button class="btn btn-secondary" type="button" data-close-dashboard-filters>Cancel</button>
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
    <script src="../public/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('dashboard-filter-modal');
            if (!modal) return;
            var openButtons = document.querySelectorAll('[data-open-dashboard-filters]');
            var closeButtons = document.querySelectorAll('[data-close-dashboard-filters]');

            function setFilterModal(open) {
                modal.classList.toggle('open', open);
                modal.setAttribute('aria-hidden', open ? 'false' : 'true');
                document.body.classList.toggle('modal-open', open);
            }

            openButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    setFilterModal(true);
                    var firstInput = modal.querySelector('input, select, button');
                    if (firstInput) firstInput.focus();
                });
            });

            closeButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    setFilterModal(false);
                });
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    setFilterModal(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('open')) {
                    setFilterModal(false);
                }
            });
        });
    </script>
</body>
</html>
