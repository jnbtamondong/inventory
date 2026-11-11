<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/patient_helpers.php';
require_once __DIR__ . '/includes/dashboard_overview.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$asOfDate = date('F j, Y');
$nursingOverview = nursingOverviewData($conn, date('Y-m-d'), date('Y-m-d'));
$overviewWardAvailable = $nursingOverview['beds']['Medical / Surgery Ward']['available'] !== null && $nursingOverview['beds']['OB / Pedia Ward']['available'] !== null
    ? $nursingOverview['beds']['Medical / Surgery Ward']['available'] + $nursingOverview['beds']['OB / Pedia Ward']['available'] : 'Not reported';
$navItems = [
    'index.php' => 'Home',
    'dashboard_preview.php' => 'Dashboard',
    'index.php#about' => 'Mission & Vision',

    'index.php#services' => 'Modules',

    'index.php#team' => 'Leadership',
    'index.php#contact' => 'Contact'
];

function navActive($page, $currentPage) {
    return basename(parse_url($page, PHP_URL_PATH)) === $currentPage ? ' active' : '';
}

function dashboardScalar($conn, $sql, $default = 0) {
    $result = $conn->query($sql);
    if (!$result) {
        return $default;
    }

    $row = $result->fetch_assoc();
    if (!$row) {
        return $default;
    }

    return $row['total'] ?? $row['value'] ?? $default;
}

function dashboardAvgMinutes($conn, $areaCondition) {
    $sql = "
        SELECT AVG(GREATEST(TIMESTAMPDIFF(
            MINUTE,
            COALESCE(TIMESTAMP(p.triage_date, p.triage_time), p.arrival_time, p.admission_date, p.created_at),
            COALESCE((
                SELECT COALESCE(pt.status_ended_at, pt.created_at)
                FROM patient_tracking pt
                WHERE pt.patient_id = p.patient_id
                    AND pt.action = 'Status Updated'
                    AND pt.to_status IN ('discharge', 'discharged', 'for_admission', 'admit_inpatient', 'admission', 'referred', 'refer_other_facility', 'thoc', 'trans_out', 'absconded', 'ama', 'hama', 'dama', 'doa', 'death', 'expired', 'er_death')
                ORDER BY COALESCE(pt.status_ended_at, pt.created_at) DESC
                LIMIT 1
            ), p.updated_at, NOW())
        ), 0)) AS value
        FROM patients p
        LEFT JOIN beds area_b ON area_b.bed_id = p.current_bed
        WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) = CURDATE()
            $areaCondition
    ";

    return (float) dashboardScalar($conn, $sql, 0);
}

$activeStatusList = "'" . implode("','", array_map([$conn, 'real_escape_string'], activePatientStatuses())) . "'";
$inactiveStatusList = "'" . implode("','", array_map([$conn, 'real_escape_string'], inactivePatientStatuses())) . "'";

$erAreaCondition = "AND p.status <> 'admit_inpatient' AND NOT (area_b.room_number LIKE 'WARD-%')";
$wardAreaCondition = "AND (p.status = 'admit_inpatient' OR area_b.room_number LIKE 'WARD-%')";

$erCensus = (int) dashboardScalar($conn, "
    SELECT COUNT(*) AS total
    FROM patients p
    LEFT JOIN beds area_b ON area_b.bed_id = p.current_bed
    WHERE p.status IN ($activeStatusList)
        $erAreaCondition
");
$erPatientsToday = (int) dashboardScalar($conn, "
    SELECT COUNT(*) AS total
    FROM patients p
    LEFT JOIN beds area_b ON area_b.bed_id = p.current_bed
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) = CURDATE()
        $erAreaCondition
");
$erAvailableBeds = (int) dashboardScalar($conn, "SELECT COUNT(*) AS total FROM beds WHERE room_number LIKE 'ER-%' AND status = 'available'");
$erOccupiedBeds = (int) dashboardScalar($conn, "SELECT COUNT(*) AS total FROM beds WHERE room_number LIKE 'ER-%' AND status = 'occupied'");
$erDischargesToday = (int) dashboardScalar($conn, "
    SELECT COUNT(*) AS total
    FROM patients p
    LEFT JOIN beds area_b ON area_b.bed_id = p.current_bed
    WHERE p.status IN ($inactiveStatusList)
        AND DATE(p.updated_at) = CURDATE()
        $erAreaCondition
");
$erTurnaround = formatWaitTime(round(dashboardAvgMinutes($conn, $erAreaCondition)));

$wardCensus = (int) dashboardScalar($conn, "SELECT COUNT(*) AS total FROM patients WHERE status = 'admit_inpatient'");
$wardAssignedBeds = (int) dashboardScalar($conn, "SELECT COUNT(*) AS total FROM patients WHERE status = 'admit_inpatient' AND current_bed IS NOT NULL");
$wardAvailableBeds = (int) dashboardScalar($conn, "SELECT COUNT(*) AS total FROM beds WHERE room_number LIKE 'WARD-%' AND status = 'available'");
$wardOccupiedBeds = (int) dashboardScalar($conn, "SELECT COUNT(*) AS total FROM beds WHERE room_number LIKE 'WARD-%' AND status = 'occupied'");
$wardDischargesToday = (int) dashboardScalar($conn, "
    SELECT COUNT(*) AS total
    FROM patients p
    LEFT JOIN beds area_b ON area_b.bed_id = p.current_bed
    WHERE p.status IN ('discharge', 'expired', 'er_death')
        AND DATE(p.updated_at) = CURDATE()
        $wardAreaCondition
");
$wardTurnaround = formatWaitTime(round(dashboardAvgMinutes($conn, $wardAreaCondition)));

$erKpis = [
    ['label' => 'ER Census', 'value' => $erCensus, 'note' => 'Current emergency room census'],
    ['label' => 'Available ER Beds', 'value' => $erAvailableBeds, 'note' => 'Ready for assignment'],
    ['label' => 'Occupied ER Beds', 'value' => $erOccupiedBeds, 'note' => 'Currently in use'],
    ['label' => 'ER Turnaround Time', 'value' => $erTurnaround, 'note' => 'Average completion time']
];

$wardKpis = [
    ['label' => 'Ward Census', 'value' => $wardCensus, 'note' => 'Admitted ward patients'],
    ['label' => 'Available Ward Beds', 'value' => $wardAvailableBeds, 'note' => 'Open ward capacity'],
    ['label' => 'Assigned Ward Beds', 'value' => $wardAssignedBeds, 'note' => 'Patients with beds'],
    ['label' => 'Ward Turnaround Time', 'value' => $wardTurnaround, 'note' => 'Average ward processing time']
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nursing Dashboard | NURSYNC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/css/nursync-home.css?v=7">
    <link rel="stylesheet" href="public/css/nursync-overview.css?v=3">
    <link rel="stylesheet" href="public/vendor/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="public/css/workspace-ui.css?v=2">
</head>
<body class="story-site dashboard-story">
    <nav class="navbar navbar-expand-lg sticky-top site-navbar">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php" aria-label="NURSYNC Homepage">
                <img class="brand-logo home-brand-seal" src="public/images/branding/jvgh_seal.png" alt="JVGH seal">
                <span class="brand-text">
                    <strong>NURSYNC</strong>
                    <span>JVGH Nursing Management System</span>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#jvghNavbar" aria-controls="jvghNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="jvghNavbar">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <?php foreach ($navItems as $page => $label): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo navActive($page, $currentPage); ?>" href="<?php echo htmlspecialchars($page); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="login-pill ms-lg-3" href="pages/login.php">Login</a>
            </div>
        </div>
    </nav>

    <main>
        <section class="dashboard-hero">
            <?php include __DIR__ . '/includes/public_background.php'; ?>
            <div class="container">
                <span class="home-section-label">JVGH NURSING SERVICE</span>
                <h1>Nursing Operations Dashboard</h1>
                <p>Bed availability, occupied beds, and census across the JVGH nursing departments.</p>
                <div class="report-date-pill">Report Basis: <?php echo htmlspecialchars($asOfDate); ?></div>
            </div>
        </section>

        <div class="container dashboard-overview-wrap">
            <?php include __DIR__ . '/includes/dashboard_overview_cards.php'; ?>
        </div>

        <section class="section-block">
            <?php include __DIR__ . '/includes/public_background.php'; ?>
            <div class="container">
                <div class="section-heading">
                    <h2>ER KPI</h2>
                    <span>Emergency Room performance indicators</span>
                </div>
                <div class="row g-4">
                    <?php foreach ($erKpis as $kpi): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="kpi-card">
                                <div class="label"><?php echo htmlspecialchars($kpi['label']); ?></div>
                                <div class="value"><?php echo htmlspecialchars($kpi['value']); ?></div>
                                <div class="note"><?php echo htmlspecialchars($kpi['note']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section-block pt-0">
            <?php include __DIR__ . '/includes/public_background.php'; ?>
            <div class="container">
                <div class="section-heading">
                    <h2>Ward KPI</h2>
                    <span>Ward census and capacity indicators</span>
                </div>
                <div class="row g-4">
                    <?php foreach ($wardKpis as $kpi): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="kpi-card">
                                <div class="label"><?php echo htmlspecialchars($kpi['label']); ?></div>
                                <div class="value"><?php echo htmlspecialchars($kpi['label'] === 'Available Ward Beds' ? $overviewWardAvailable : $kpi['value']); ?></div>
                                <div class="note"><?php echo htmlspecialchars($kpi['note']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section-block pt-0">
            <?php include __DIR__ . '/includes/public_background.php'; ?>
            <div class="container">
                <div class="section-heading">
                    <h2>Reports</h2>
                    <span>ER and Ward report preview</span>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="report-card">
                            <h3>Emergency Room Report</h3>
                            <span class="report-date">Basis Date: <?php echo htmlspecialchars($asOfDate); ?></span>
                            <table class="report-table">
                                <tr><th>Metric</th><th>Preview</th></tr>
                                <tr><td>Patients Today</td><td><?php echo htmlspecialchars($erPatientsToday); ?></td></tr>
                                <tr><td>ER Census</td><td><?php echo htmlspecialchars($erCensus); ?></td></tr>
                                <tr><td>Available Beds</td><td><?php echo htmlspecialchars($erAvailableBeds); ?></td></tr>
                                <tr><td>Discharges Today</td><td><?php echo htmlspecialchars($erDischargesToday); ?></td></tr>
                                <tr><td>Turnaround Time</td><td><?php echo htmlspecialchars($erTurnaround); ?> average</td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="report-card">
                            <h3>Ward Report</h3>
                            <span class="report-date">Basis Date: <?php echo htmlspecialchars($asOfDate); ?></span>
                            <table class="report-table">
                                <tr><th>Metric</th><th>Preview</th></tr>
                                <tr><td>Ward Census</td><td><?php echo htmlspecialchars($wardCensus); ?></td></tr>
                                <tr><td>Assigned Beds</td><td><?php echo htmlspecialchars($wardAssignedBeds); ?></td></tr>
                                <tr><td>Available Beds (2nd / 3rd Floor)</td><td><?php echo htmlspecialchars($overviewWardAvailable); ?></td></tr>
                                <tr><td>Discharges Today</td><td><?php echo htmlspecialchars($wardDischargesToday); ?></td></tr>
                                <tr><td>Turnaround Time</td><td><?php echo htmlspecialchars($wardTurnaround); ?> average</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <p class="preview-note">Census coverage: today. Bed counts show current availability.</p>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between gap-3">
            <strong>NURSYNC</strong>
            <span>JVGH Nursing Service</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/nursync-home.js?v=1"></script>
    <script src="public/js/workspace-background.js?v=1"></script>
</body>
</html>
