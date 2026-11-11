<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requirePermission('nursing', 'view', 'ER');

ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);
ensureEmergencyRoomBeds($conn);

function erStatValue(mysqli $conn, string $sql, array $params = [], string $types = '') {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int) reset($row) : 0;
}

function erStatRows(mysqli $conn, string $sql, array $params = [], string $types = ''): array {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function erPercent(int $value, int $total): float {
    return $total > 0 ? round(($value / $total) * 100, 2) : 0.0;
}

$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;
if (!validDateString($selectedDate)) {
    $selectedDate = $today;
}

$activeStatuses = activePatientStatuses();
$activeStatusList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $activeStatuses)) . "'";
$dispositionStatuses = ['discharge', 'discharged', 'for_admission', 'admit_inpatient', 'referred', 'thoc', 'trans_out', 'absconded', 'ama', 'hama', 'dama', 'doa', 'death', 'expired', 'er_death'];
$dispositionStatusList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $dispositionStatuses)) . "'";

$totalConsults = erStatValue($conn, "SELECT COUNT(*) FROM patients WHERE DATE(COALESCE(arrival_time, admission_date, created_at)) = ?", [$selectedDate], 's');
$pending = erStatValue($conn, "SELECT COUNT(*) FROM patients WHERE status IN ($activeStatusList)");
$admissions = erStatValue($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('for_admission','admit_inpatient') AND DATE(COALESCE(arrival_time, admission_date, created_at)) = ?", [$selectedDate], 's');
$discharged = erStatValue($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('discharge','discharged') AND DATE(updated_at) = ?", [$selectedDate], 's');
$transfers = erStatValue($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('referred','trans_out','thoc') AND DATE(updated_at) = ?", [$selectedDate], 's');
$deaths = erStatValue($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('death','er_death','expired','doa') AND DATE(updated_at) = ?", [$selectedDate], 's');
$hama = erStatValue($conn, "SELECT COUNT(*) FROM patients WHERE status IN ('hama','ama','dama') AND DATE(updated_at) = ?", [$selectedDate], 's');
$absconded = erStatValue($conn, "SELECT COUNT(*) FROM patients WHERE status='absconded' AND DATE(updated_at) = ?", [$selectedDate], 's');
$under4 = erStatValue($conn, "SELECT COUNT(*) FROM patient_tracking WHERE to_status IN ($dispositionStatusList) AND wait_time_minutes > 0 AND wait_time_minutes < 240 AND DATE(created_at) = ?", [$selectedDate], 's');
$over4 = erStatValue($conn, "SELECT COUNT(*) FROM patient_tracking WHERE to_status IN ($dispositionStatusList) AND wait_time_minutes >= 240 AND DATE(created_at) = ?", [$selectedDate], 's');
$tatTotal = $under4 + $over4;
$accomplishment = erPercent($under4, max(1, $tatTotal));

$breakdown = erStatRows($conn, "
    SELECT
        COALESCE(NULLIF(ward_department, ''), 'MEDICAL') AS department,
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('for_admission','admit_inpatient') THEN 1 ELSE 0 END) AS admissions,
        SUM(CASE WHEN status IN ('discharge','discharged') THEN 1 ELSE 0 END) AS discharged,
        SUM(CASE WHEN status IN ($activeStatusList) THEN 1 ELSE 0 END) AS pending
    FROM patients
    WHERE DATE(COALESCE(arrival_time, admission_date, created_at)) = ?
    GROUP BY COALESCE(NULLIF(ward_department, ''), 'MEDICAL')
    ORDER BY department
", [$selectedDate], 's');

$triage = erStatRows($conn, "
    SELECT
        CASE
            WHEN t.triage_level IN ('red','emergency') THEN 'Emergent'
            WHEN t.triage_level IN ('yellow','urgent') THEN 'Urgent'
            ELSE 'Non-Urgent'
        END AS category,
        SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, p.arrival_time, COALESCE(p.updated_at, NOW())) < 240 THEN 1 ELSE 0 END) AS under4,
        SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, p.arrival_time, COALESCE(p.updated_at, NOW())) >= 240 THEN 1 ELSE 0 END) AS over4,
        COUNT(*) AS total
    FROM patients p
    LEFT JOIN triage t ON t.patient_id = p.patient_id
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) = ?
    GROUP BY category
    ORDER BY category
", [$selectedDate], 's');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ER Statistics Dashboard - Nursing Information System</title>
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
        .progress-track { height:10px; background:#E8EEF6; border-radius:999px; overflow:hidden; }
        .progress-fill { height:100%; background:#0B3C5D; }
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
                <h2>ER Statistics Dashboard</h2>
                <p class="compact-meta">Aggregate emergency room statistics only. No patient names are shown.</p>
                <form method="GET" class="report-filter no-print">
                    <div class="form-group"><label>Date</label><input type="date" name="date" value="<?php echo h($selectedDate); ?>"></div>
                    <button class="btn btn-primary">Apply</button>
                    <button class="btn btn-secondary" type="button" onclick="window.print()">Print</button>
                </form>
            </section>
            <section class="stats-grid">
                <div class="stat-card emergency"><div class="stat-info"><span class="stat-label">Total ER Consult</span><span class="stat-value"><?php echo h($totalConsults); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Admissions</span><span class="stat-value"><?php echo h($admissions); ?></span></div></div>
                <div class="stat-card available"><div class="stat-info"><span class="stat-label">Discharged</span><span class="stat-value"><?php echo h($discharged); ?></span></div></div>
                <div class="stat-card waiting"><div class="stat-info"><span class="stat-label">Pending</span><span class="stat-value"><?php echo h($pending); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Transfers</span><span class="stat-value"><?php echo h($transfers); ?></span></div></div>
                <div class="stat-card emergency"><div class="stat-info"><span class="stat-label">Deaths / DOA</span><span class="stat-value"><?php echo h($deaths); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">HAMA</span><span class="stat-value"><?php echo h($hama); ?></span></div></div>
                <div class="stat-card"><div class="stat-info"><span class="stat-label">Absconded</span><span class="stat-value"><?php echo h($absconded); ?></span></div></div>
            </section>
            <section class="stats-panel">
                <h3>ER Turnaround KPI</h3>
                <div class="metric-line"><strong>Disposition &lt; 4 Hours</strong><span><?php echo h($under4); ?></span></div>
                <div class="metric-line"><strong>Disposition &gt;= 4 Hours</strong><span><?php echo h($over4); ?></span></div>
                <div class="metric-line"><strong>Accomplishment</strong><span><?php echo h(number_format($accomplishment, 2)); ?>%</span></div>
                <div class="progress-track"><div class="progress-fill" style="width: <?php echo min(100, $accomplishment); ?>%"></div></div>
            </section>
            <section class="stats-panel">
                <h3>ER Breakdown</h3>
                <div class="table-responsive"><table class="compact-table"><thead><tr><th>Category</th><th>Total</th><th>Admissions</th><th>Discharged</th><th>Pending</th></tr></thead><tbody><?php foreach ($breakdown as $row): ?><tr><td><?php echo h($row['department']); ?></td><td><?php echo h($row['total']); ?></td><td><?php echo h($row['admissions']); ?></td><td><?php echo h($row['discharged']); ?></td><td><?php echo h($row['pending']); ?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>
            <section class="stats-panel">
                <h3>Triage Category</h3>
                <div class="table-responsive"><table class="compact-table"><thead><tr><th>Category</th><th>&lt; 4 Hours</th><th>&gt;= 4 Hours</th><th>Total</th></tr></thead><tbody><?php foreach ($triage as $row): ?><tr><td><?php echo h($row['category']); ?></td><td><?php echo h($row['under4']); ?></td><td><?php echo h($row['over4']); ?></td><td><?php echo h($row['total']); ?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>
        </main>
    </div>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
