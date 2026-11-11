<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
requireRole(doctorRoles());
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);

$isMonthlyReport = basename($_SERVER['PHP_SELF']) === 'monthly_report.php';
$reportMonth = $_GET['report_month'] ?? date('Y-m');

if ($isMonthlyReport) {
    $monthCheck = DateTime::createFromFormat('Y-m', $reportMonth);
    if (!$monthCheck || $monthCheck->format('Y-m') !== $reportMonth) {
        $reportMonth = date('Y-m');
        $monthCheck = new DateTime($reportMonth . '-01');
    }
    $startDate = $monthCheck->format('Y-m-01');
    $endDate = $monthCheck->format('Y-m-t');
} else {
    $startDate = $_GET['start_date'] ?? date('Y-m-d');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
}
$startCheck = DateTime::createFromFormat('Y-m-d', $startDate);
$endCheck = DateTime::createFromFormat('Y-m-d', $endDate);
if (!$startCheck || $startCheck->format('Y-m-d') !== $startDate) {
    $startDate = date('Y-m-d');
}
if (!$endCheck || $endCheck->format('Y-m-d') !== $endDate) {
    $endDate = $startDate;
}
if ($startDate > $endDate) {
    $endDate = $startDate;
}

$conn->query("UPDATE patients SET ward_department = 'NON-TRAUMA SURGERY' WHERE ward_department = 'SURGERY'");

$departments = wardDepartmentOptions();
$metrics = [
    'tat_lt_2' => '< 2 HOURS.',
    'tat_2_4' => '> 2 TO < 4HOURS',
    'tat_4_6' => '> 4 HOURS TO < 6HOURS',
    'tat_gt_6' => '> 6HOURS',
    'emergent' => 'EMERGENT',
    'urgent' => 'URGENT',
    'non_urgent' => 'NON-URGENT',
    'discharged' => 'DISCHARGED',
    'admission' => 'ADMISSION',
    'thoc' => 'THOC',
    'absconded' => 'ABSCONDED',
    'dama' => 'DAMA',
    'doa' => 'DOA',
    'er_death' => 'ER DEATH'
];

$rows = [];
foreach ($departments as $department => $label) {
    $rows[$department] = array_fill_keys(array_keys($metrics), 0);
}

$departmentSql = "COALESCE(NULLIF(p.ward_department, ''), 'MEDICAL')";

$turnaroundStmt = $conn->prepare("
    SELECT
        $departmentSql as department,
        GREATEST(TIMESTAMPDIFF(
            MINUTE,
            COALESCE(TIMESTAMP(p.triage_date, p.triage_time), p.arrival_time),
            COALESCE((
                SELECT COALESCE(pt.status_ended_at, pt.created_at)
                FROM patient_tracking pt
                WHERE pt.patient_id = p.patient_id
                    AND pt.action = 'Status Updated'
                    AND pt.to_status IN ('discharge', 'discharged', 'for_admission', 'admit_inpatient', 'admission', 'referred', 'refer_other_facility', 'thoc', 'trans_out', 'absconded', 'ama', 'hama', 'dama', 'doa', 'death', 'expired', 'er_death')
                ORDER BY COALESCE(pt.status_ended_at, pt.created_at) DESC
                LIMIT 1
            ), p.updated_at, NOW())
        ), 0) as minutes
    FROM patients p
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) BETWEEN ? AND ?
");
$turnaroundStmt->bind_param("ss", $startDate, $endDate);
$turnaroundStmt->execute();
$turnaroundResults = $turnaroundStmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($turnaroundResults as $item) {
    $department = $item['department'];
    if (!isset($rows[$department])) {
        continue;
    }

    $minutes = (int) $item['minutes'];
    if ($minutes < 120) {
        $rows[$department]['tat_lt_2']++;
    } elseif ($minutes < 240) {
        $rows[$department]['tat_2_4']++;
    } elseif ($minutes < 360) {
        $rows[$department]['tat_4_6']++;
    } else {
        $rows[$department]['tat_gt_6']++;
    }
}

$triageStmt = $conn->prepare("
    SELECT
        $departmentSql as department,
        t.triage_level,
        COUNT(*) as total
    FROM patients p
    INNER JOIN triage t ON t.patient_id = p.patient_id
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) BETWEEN ? AND ?
    GROUP BY department, t.triage_level
");
$triageStmt->bind_param("ss", $startDate, $endDate);
$triageStmt->execute();
$triageResults = $triageStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$triageMap = [
    'red' => 'emergent',
    'emergency' => 'emergent',
    'yellow' => 'urgent',
    'urgent' => 'urgent',
    'green' => 'non_urgent',
    'non-urgent' => 'non_urgent'
];

foreach ($triageResults as $item) {
    $department = $item['department'];
    $metric = $triageMap[$item['triage_level']] ?? null;
    if ($metric && isset($rows[$department])) {
        $rows[$department][$metric] += (int) $item['total'];
    }
}

$dispositionStmt = $conn->prepare("
    SELECT
        $departmentSql as department,
        p.status as to_status,
        COUNT(*) as total
    FROM patients p
    WHERE p.status IN ('discharge', 'for_admission', 'admit_inpatient', 'referred', 'ama', 'thoc', 'absconded', 'dama', 'doa', 'expired', 'er_death')
        AND DATE(COALESCE(p.updated_at, p.arrival_time, p.admission_date, p.created_at)) BETWEEN ? AND ?
    GROUP BY department, p.status
");
$dispositionStmt->bind_param("ss", $startDate, $endDate);
$dispositionStmt->execute();
$dispositionResults = $dispositionStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$dispositionMap = [
    'discharge' => 'discharged',
    'discharged' => 'discharged',
    'admit_inpatient' => 'admission',
    'for_admission' => 'admission',
    'admission' => 'admission',
    'referred' => 'thoc',
    'thoc' => 'thoc',
    'refer_other_facility' => 'thoc',
    'trans_out' => 'thoc',
    'absconded' => 'absconded',
    'hama' => 'dama',
    'ama' => 'dama',
    'dama' => 'dama',
    'doa' => 'doa',
    'black' => 'doa',
    'death' => 'er_death',
    'expired' => 'er_death',
    'er_death' => 'er_death'
];

foreach ($dispositionResults as $item) {
    $department = $item['department'];
    $metric = $dispositionMap[$item['to_status']] ?? null;
    if ($metric && isset($rows[$department])) {
        $rows[$department][$metric] += (int) $item['total'];
    }
}

$totals = array_fill_keys(array_keys($metrics), 0);
foreach ($rows as $row) {
    foreach ($metrics as $key => $label) {
        $totals[$key] += $row[$key];
    }
}

$tatKeys = ['tat_lt_2', 'tat_2_4', 'tat_4_6', 'tat_gt_6'];
$triageKeys = ['emergent', 'urgent', 'non_urgent'];
$dispositionKeys = ['discharged', 'admission', 'thoc', 'absconded', 'dama', 'doa', 'er_death'];
$tatTotal = array_sum(array_intersect_key($totals, array_flip($tatKeys)));
$triageTotal = array_sum(array_intersect_key($totals, array_flip($triageKeys)));
$dispositionTotal = array_sum(array_intersect_key($totals, array_flip($dispositionKeys)));

$bulacanMunicipalities = [
    'ANGAT',
    'BALAGTAS',
    'BALIUAG',
    'BOCAUE',
    'BULACAN',
    'BUSTOS',
    'CALUMPIT',
    'DONA REMEDIOS TRINIDAD',
    'GUIGUINTO',
    'HAGONOY',
    'MARILAO',
    'NORZAGARAY',
    'OBANDO',
    'PANDI',
    'PAOMBONG',
    'PLARIDEL',
    'PULILAN',
    'SAN ILDEFONSO',
    'SAN MIGUEL',
    'SAN RAFAEL',
    'SANTA MARIA',
    'MALOLOS',
    'MEYCAUAYAN',
    'SAN JOSE DEL MONTE'
];
$blankMunicipalityRow = [
    'municipality' => '',
    'male_count' => 0,
    'female_count' => 0,
    'sex_total' => 0,
    'under_18_count' => 0,
    'over_18_count' => 0,
    'age_total' => 0
];
$municipalityRows = [];
$outsideBulacanRows = [];
$municipalityStmt = $conn->prepare("
    SELECT
        COALESCE(NULLIF(p.province_name, ''), pr.province_name, 'Unspecified') as province,
        COALESCE(NULLIF(p.municipality_name, ''), m.municipality_name, 'Unspecified') as municipality,
        COALESCE(NULLIF(p.barangay_name, ''), b.barangay_name, '') as barangay,
        SUM(CASE WHEN p.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
        SUM(CASE WHEN p.gender = 'Female' THEN 1 ELSE 0 END) as female_count,
        COUNT(*) as sex_total,
        SUM(CASE WHEN COALESCE(p.age, TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE())) < 18 THEN 1 ELSE 0 END) as under_18_count,
        SUM(CASE WHEN COALESCE(p.age, TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE())) >= 18 THEN 1 ELSE 0 END) as over_18_count,
        COUNT(*) as age_total
    FROM patients p
    LEFT JOIN provinces pr ON p.province_id = pr.province_id
    LEFT JOIN municipalities m ON p.municipality_id = m.municipality_id
    LEFT JOIN barangays b ON p.barangay_id = b.barangay_id
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) BETWEEN ? AND ?
    GROUP BY province, municipality, barangay
    ORDER BY province ASC, municipality ASC, barangay ASC
");
$municipalityStmt->bind_param("ss", $startDate, $endDate);
$municipalityStmt->execute();
$municipalityResults = $municipalityStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$municipalityResultsByName = [];
foreach ($municipalityResults as $municipalityResult) {
    $normalizedProvince = strtoupper(trim($municipalityResult['province']));
    $normalizedName = strtoupper(trim($municipalityResult['municipality']));
    $normalizedBarangay = strtoupper(trim($municipalityResult['barangay'] ?? ''));
    if ($normalizedName === 'DOÑA REMEDIOS TRINIDAD') {
        $normalizedName = 'DONA REMEDIOS TRINIDAD';
    } elseif ($normalizedName === 'STA. MARIA') {
        $normalizedName = 'SANTA MARIA';
    } elseif ($normalizedName === 'CITY OF SAN JOSE DEL MONTE') {
        $normalizedName = 'SAN JOSE DEL MONTE';
    } elseif ($normalizedName === 'CITY OF MALOLOS') {
        $normalizedName = 'MALOLOS';
    } elseif ($normalizedName === 'CITY OF MEYCAUAYAN') {
        $normalizedName = 'MEYCAUAYAN';
    }

    $isBulacanProvince = strpos($normalizedProvince, 'BULACAN') !== false && $normalizedProvince !== 'OUTSIDE BULACAN';
    $hasUnspecifiedProvince = in_array($normalizedProvince, ['', 'UNSPECIFIED', 'N/A'], true);
    $isBulacanMunicipality = in_array($normalizedName, $bulacanMunicipalities, true);

    if (($isBulacanProvince || $hasUnspecifiedProvince) && $isBulacanMunicipality) {
        if (!isset($municipalityResultsByName[$normalizedName])) {
            $municipalityResultsByName[$normalizedName] = $blankMunicipalityRow;
        }
        foreach (['male_count', 'female_count', 'sex_total', 'under_18_count', 'over_18_count', 'age_total'] as $key) {
            $municipalityResultsByName[$normalizedName][$key] += (int) ($municipalityResult[$key] ?? 0);
        }
    } else {
        $outsideParts = array_filter([
            $normalizedBarangay,
            $normalizedName !== '' ? $normalizedName : 'UNSPECIFIED',
            $normalizedProvince !== '' ? $normalizedProvince : 'UNSPECIFIED'
        ], function ($part) {
            return !in_array($part, ['', 'UNSPECIFIED', 'N/A'], true);
        });
        $outsideLabel = !empty($outsideParts) ? implode(', ', $outsideParts) : 'UNSPECIFIED';
        $outsideKey = $outsideLabel;
        if (!isset($outsideBulacanRows[$outsideKey])) {
            $outsideBulacanRows[$outsideKey] = $blankMunicipalityRow;
            $outsideBulacanRows[$outsideKey]['municipality'] = $outsideLabel;
        }
        foreach (['male_count', 'female_count', 'sex_total', 'under_18_count', 'over_18_count', 'age_total'] as $key) {
            $outsideBulacanRows[$outsideKey][$key] += (int) ($municipalityResult[$key] ?? 0);
        }
    }
}

foreach ($bulacanMunicipalities as $municipalityName) {
    $row = $municipalityResultsByName[$municipalityName] ?? $blankMunicipalityRow;
    $row['municipality'] = $municipalityName;
    if ((int) ($row['sex_total'] ?? 0) > 0) {
        $municipalityRows[] = $row;
    }
}

$outsideBulacanRows = array_values($outsideBulacanRows);
usort($outsideBulacanRows, function ($a, $b) {
    return strcmp($a['municipality'], $b['municipality']);
});

$municipalityTotals = [
    'male_count' => 0,
    'female_count' => 0,
    'sex_total' => 0,
    'under_18_count' => 0,
    'over_18_count' => 0,
    'age_total' => 0
];
foreach ($municipalityRows as $municipalityRow) {
    foreach ($municipalityTotals as $key => $value) {
        $municipalityTotals[$key] += (int) ($municipalityRow[$key] ?? 0);
    }
}
foreach ($outsideBulacanRows as $municipalityRow) {
    foreach ($municipalityTotals as $key => $value) {
        $municipalityTotals[$key] += (int) ($municipalityRow[$key] ?? 0);
    }
}

function reportPercent($value, $total) {
    return $total > 0 ? number_format(($value / $total) * 100, 2) . '%' : '0.00%';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Nursing Service System</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/dashboard.css">
    <style>
        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .report-stat {
            background: #f8fbfd;
            border: 1px solid #dbe7f0;
            border-left: 4px solid #2196F3;
            border-radius: 6px;
            padding: 14px;
        }
        .report-stat strong {
            display: block;
            color: #2c3e50;
            font-size: 24px;
            line-height: 1;
            margin-bottom: 6px;
        }
        .report-stat span {
            color: #607d8b;
            font-size: 13px;
            font-weight: 600;
        }
        .report-panels {
            display: grid;
            gap: 20px;
        }
        .report-panel {
            border: 1px solid #dbe7f0;
            border-radius: 6px;
            background: #fff;
            overflow: hidden;
        }
        .report-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 16px 18px;
            background: #2c3e50;
            color: #fff;
        }
        .report-panel-header h3 {
            margin: 0;
            color: #fff;
            font-size: 18px;
        }
        .report-panel-header span {
            color: #dce9f3;
            font-size: 13px;
        }
        .report-panel table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
            table-layout: fixed;
        }
        .report-panel th,
        .report-panel td {
            padding: 12px 14px;
            border-bottom: 1px solid #edf2f6;
            text-align: right;
            white-space: nowrap;
            vertical-align: middle;
        }
        .report-panel th {
            background: #f5f8fb;
            color: #2c3e50;
            font-size: 12px;
            text-transform: uppercase;
        }
        .report-panel th:first-child,
        .report-panel td:first-child {
            text-align: left;
            font-weight: 700;
        }
        .report-panel thead tr:first-child th:first-child,
        .report-panel tbody td:first-child,
        .report-panel tfoot td:first-child {
            width: 28%;
        }
        .report-panel th[colspan] {
            text-align: center;
        }
        .report-panel th[rowspan] {
            vertical-align: middle;
        }
        .report-panel th.number-header {
            text-align: center;
            width: auto;
        }
        .report-panel .number-cell,
        .report-panel .number-header {
            text-align: center;
        }
        .report-panel tbody tr:hover {
            background: #f8fbfd;
        }
        .report-panel tfoot td {
            background: #f5f8fb;
            font-weight: 700;
            color: #2c3e50;
        }
        .municipality-divider td {
            background: #e3f2fd;
            color: #2c3e50;
            font-weight: 700;
            text-transform: uppercase;
        }
        .metric-danger { color: #f44336; font-weight: 700; }
        .metric-warning { color: #ff9800; font-weight: 700; }
        .metric-success { color: #2e7d32; font-weight: 700; }
        .report-filter {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 10px;
        }
        .report-filter label {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
            font-size: 12px;
        }
        .report-filter input {
            padding: 9px 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand"><h1>Nursing Service System</h1></div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <section class="patients-section">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <h2><?php echo $isMonthlyReport ? 'Monthly Report' : 'Daily Report'; ?></h2>
                    <form method="GET" class="report-filter">
                        <?php if ($isMonthlyReport): ?>
                            <div>
                                <label for="report-month">Month</label>
                                <input type="month" id="report-month" name="report_month" value="<?php echo htmlspecialchars($reportMonth); ?>">
                            </div>
                        <?php else: ?>
                            <div>
                                <label for="start-date">Start Date</label>
                                <input type="date" id="start-date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                            </div>
                            <div>
                                <label for="end-date">End Date</label>
                                <input type="date" id="end-date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">View</button>
                    </form>
                </div>

                <div class="report-panels">
                    <div class="report-panel">
                        <div class="report-panel-header">
                            <h3>Turn Around Time</h3>
                            <span><?php echo htmlspecialchars($startDate . ' to ' . $endDate); ?></span>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th class="number-header">&lt; 2 Hours</th>
                                        <th class="number-header">2 to &lt; 4 Hours</th>
                                        <th class="number-header">4 to &lt; 6 Hours</th>
                                        <th class="number-header">&gt; 6 Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $department => $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($department); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($row['tat_lt_2']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($row['tat_2_4']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($row['tat_4_6']); ?></td>
                                            <td class="number-cell <?php echo $row['tat_gt_6'] > 0 ? 'metric-danger' : ''; ?>"><?php echo htmlspecialchars($row['tat_gt_6']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>Total</td>
                                        <?php foreach ($tatKeys as $key): ?>
                                            <td class="number-cell"><?php echo htmlspecialchars($totals[$key]); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <td>Grand Total</td>
                                        <td class="number-cell" colspan="4"><?php echo htmlspecialchars($tatTotal); ?></td>
                                    </tr>
                                    <tr>
                                        <td>%</td>
                                        <?php foreach ($tatKeys as $key): ?>
                                            <td class="number-cell"><?php echo htmlspecialchars(reportPercent($totals[$key], $tatTotal)); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="report-panel">
                        <div class="report-panel-header">
                            <h3>Triage Scale</h3>
                            <span><?php echo htmlspecialchars($triageTotal); ?> total</span>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th class="number-header">Emergent</th>
                                        <th class="number-header">Urgent</th>
                                        <th class="number-header">Non-Urgent</th>
                                        <th class="number-header">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $department => $row): ?>
                                        <?php $triageRowTotal = $row['emergent'] + $row['urgent'] + $row['non_urgent']; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($department); ?></td>
                                            <td class="number-cell metric-danger"><?php echo htmlspecialchars($row['emergent']); ?></td>
                                            <td class="number-cell metric-warning"><?php echo htmlspecialchars($row['urgent']); ?></td>
                                            <td class="number-cell metric-success"><?php echo htmlspecialchars($row['non_urgent']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($triageRowTotal); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>Total</td>
                                        <?php foreach ($triageKeys as $key): ?>
                                            <td class="number-cell"><?php echo htmlspecialchars($totals[$key]); ?></td>
                                        <?php endforeach; ?>
                                        <td class="number-cell"><?php echo htmlspecialchars($triageTotal); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="report-panel">
                        <div class="report-panel-header">
                            <h3>Disposition</h3>
                            <span><?php echo htmlspecialchars($dispositionTotal); ?> total</span>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th class="number-header">Discharged</th>
                                        <th class="number-header">Admission</th>
                                        <th class="number-header">THOC</th>
                                        <th class="number-header">Absconded</th>
                                        <th class="number-header">DAMA</th>
                                        <th class="number-header">DOA</th>
                                        <th class="number-header">ER Death</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $department => $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($department); ?></td>
                                            <?php foreach ($dispositionKeys as $key): ?>
                                                <td class="number-cell"><?php echo htmlspecialchars($row[$key]); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>Total</td>
                                        <?php foreach ($dispositionKeys as $key): ?>
                                            <td class="number-cell"><?php echo htmlspecialchars($totals[$key]); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="report-panel">
                        <div class="report-panel-header">
                            <h3>Patient per Municipality</h3>
                            <span><?php echo htmlspecialchars($municipalityTotals['sex_total']); ?> total</span>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th rowspan="2">Municipality</th>
                                        <th colspan="3">Sex</th>
                                        <th colspan="3">Age</th>
                                    </tr>
                                    <tr>
                                        <th class="number-header">Male</th>
                                        <th class="number-header">Female</th>
                                        <th class="number-header">Total</th>
                                        <th class="number-header">&lt; 18</th>
                                        <th class="number-header">&gt; 18</th>
                                        <th class="number-header">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($municipalityRows as $municipalityRow): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($municipalityRow['municipality']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['male_count']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['female_count']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['sex_total']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['under_18_count']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['over_18_count']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['age_total']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="municipality-divider">
                                        <td>Outside Bulacan</td>
                                        <td colspan="6"></td>
                                    </tr>
                                    <?php if (empty($outsideBulacanRows)): ?>
                                        <tr>
                                            <td>No outside Bulacan patients</td>
                                            <td class="number-cell">0</td>
                                            <td class="number-cell">0</td>
                                            <td class="number-cell">0</td>
                                            <td class="number-cell">0</td>
                                            <td class="number-cell">0</td>
                                            <td class="number-cell">0</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($outsideBulacanRows as $municipalityRow): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($municipalityRow['municipality']); ?></td>
                                                <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['male_count']); ?></td>
                                                <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['female_count']); ?></td>
                                                <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['sex_total']); ?></td>
                                                <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['under_18_count']); ?></td>
                                                <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['over_18_count']); ?></td>
                                                <td class="number-cell"><?php echo htmlspecialchars($municipalityRow['age_total']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>Total</td>
                                        <td class="number-cell"><?php echo htmlspecialchars($municipalityTotals['male_count']); ?></td>
                                        <td class="number-cell"><?php echo htmlspecialchars($municipalityTotals['female_count']); ?></td>
                                        <td class="number-cell"><?php echo htmlspecialchars($municipalityTotals['sex_total']); ?></td>
                                        <td class="number-cell"><?php echo htmlspecialchars($municipalityTotals['under_18_count']); ?></td>
                                        <td class="number-cell"><?php echo htmlspecialchars($municipalityTotals['over_18_count']); ?></td>
                                        <td class="number-cell"><?php echo htmlspecialchars($municipalityTotals['age_total']); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
