<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/patient_helpers.php';

requireLogin();
ensureStatusTrackingSchema($conn);
ensurePatientWorkflowSchema($conn);

$isMonthlyReport = basename($_SERVER['PHP_SELF']) === 'monthly_report.php';
$reportMonth = $_GET['report_month'] ?? date('Y-m');
$reportArea = $_GET['area'] ?? 'emergency_room';
$reportArea = in_array($reportArea, ['emergency_room', 'ward'], true) ? $reportArea : 'emergency_room';
if (isErLimitedUser() && $reportArea !== 'emergency_room') {
    $target = $isMonthlyReport ? 'monthly_report.php?area=emergency_room' : 'reports.php?area=emergency_room';
    header('Location: ' . $target);
    exit();
}
if (isWardLimitedUser() && $reportArea !== 'ward') {
    $target = $isMonthlyReport ? 'monthly_report.php?area=ward' : 'reports.php?area=ward';
    header('Location: ' . $target);
    exit();
}
$reportAreaLabel = $reportArea === 'ward' ? 'Ward' : 'Emergency Room';
$reportAreaQuery = $reportArea === 'ward' ? 'ward' : 'emergency_room';
$reportAreaCondition = $reportArea === 'ward'
    ? "AND (p.status = 'admit_inpatient' OR EXISTS (SELECT 1 FROM beds area_b WHERE area_b.bed_id = p.current_bed AND area_b.room_number LIKE 'WARD-%'))"
    : "AND p.status <> 'admit_inpatient' AND NOT EXISTS (SELECT 1 FROM beds area_b WHERE area_b.bed_id = p.current_bed AND area_b.room_number LIKE 'WARD-%')";

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
$tatKeys = ['tat_lt_2', 'tat_2_4', 'tat_4_6', 'tat_gt_6'];
$tatTriageRows = [
    'emergent' => array_fill_keys($tatKeys, 0),
    'urgent' => array_fill_keys($tatKeys, 0),
    'non_urgent' => array_fill_keys($tatKeys, 0)
];
$tatTriageLabels = [
    'emergent' => 'EMERGENT',
    'urgent' => 'URGENT',
    'non_urgent' => 'NON-URGENT'
];
$tatTriageMap = [
    'red' => 'emergent',
    'emergency' => 'emergent',
    'yellow' => 'urgent',
    'urgent' => 'urgent',
    'green' => 'non_urgent',
    'non-urgent' => 'non_urgent'
];

$turnaroundStmt = $conn->prepare("
    SELECT
        p.status,
        t.triage_level,
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
    LEFT JOIN triage t ON t.patient_id = p.patient_id
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) BETWEEN ? AND ?
        $reportAreaCondition
");
$turnaroundStmt->bind_param("ss", $startDate, $endDate);
$turnaroundStmt->execute();
$turnaroundResults = $turnaroundStmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($turnaroundResults as $item) {
    $triageKey = $tatTriageMap[$item['triage_level'] ?? ''] ?? null;
    if (!$triageKey || !isset($tatTriageRows[$triageKey])) {
        continue;
    }

    $minutes = (int) $item['minutes'];
    if ($minutes < 120) {
        $tatTriageRows[$triageKey]['tat_lt_2']++;
    } elseif ($minutes < 240) {
        $tatTriageRows[$triageKey]['tat_2_4']++;
    } elseif ($minutes < 360) {
        $tatTriageRows[$triageKey]['tat_4_6']++;
    } else {
        $tatTriageRows[$triageKey]['tat_gt_6']++;
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
        $reportAreaCondition
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
    WHERE p.status IN ('discharge', 'for_admission', 'admit_inpatient', 'trans_in', 'trans_out', 'referred', 'ama', 'hama', 'thoc', 'absconded', 'dama', 'doa', 'death', 'expired', 'er_death')
        AND DATE(COALESCE(p.updated_at, p.arrival_time, p.admission_date, p.created_at)) BETWEEN ? AND ?
        $reportAreaCondition
    GROUP BY department, p.status
");
$dispositionStmt->bind_param("ss", $startDate, $endDate);
$dispositionStmt->execute();
$dispositionResults = $dispositionStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$dispositionMap = [
    'discharge' => 'discharged',
    'discharged' => 'discharged',
    'admit_inpatient' => 'admission',
    'trans_in' => 'admission',
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

$triageKeys = ['emergent', 'urgent', 'non_urgent'];
$dispositionKeys = ['discharged', 'admission', 'thoc', 'absconded', 'dama', 'doa', 'er_death'];
$tatTriageTotals = array_fill_keys($tatKeys, 0);
foreach ($tatTriageRows as $tatTriageRow) {
    foreach ($tatKeys as $key) {
        $tatTriageTotals[$key] += $tatTriageRow[$key];
    }
}
$tatTotal = array_sum($tatTriageTotals);
$triageTotal = array_sum(array_intersect_key($totals, array_flip($triageKeys)));
$dispositionTotal = array_sum(array_intersect_key($totals, array_flip($dispositionKeys)));

$serviceAreaRows = [
    'Emergency Room' => [
        'patients' => 0,
        'active_or_admitted' => 0,
        'admissions' => 0,
        'discharges' => 0,
        'available_beds' => 0,
        'occupied_beds' => 0
    ],
    'Ward' => [
        'patients' => 0,
        'active_or_admitted' => 0,
        'admissions' => 0,
        'discharges' => 0,
        'available_beds' => 0,
        'occupied_beds' => 0
    ]
];

$serviceAreaPatientStmt = $conn->prepare("
    SELECT
        CASE
            WHEN p.status = 'admit_inpatient' OR b.room_number LIKE 'WARD-%' THEN 'Ward'
            ELSE 'Emergency Room'
        END AS service_area,
        COUNT(*) AS patients,
        SUM(CASE WHEN p.status IN ('triage', 'waiting_for_consultation', 'consultation', 'treatment', 'waiting_results', 'ready_for_disposition', 'for_admission') THEN 1 ELSE 0 END) AS active_er,
        SUM(CASE WHEN p.status = 'admit_inpatient' THEN 1 ELSE 0 END) AS ward_admitted,
        SUM(CASE WHEN p.status IN ('for_admission', 'admit_inpatient') THEN 1 ELSE 0 END) AS admissions,
        SUM(CASE WHEN p.status IN ('discharge', 'referred', 'ama', 'thoc', 'absconded', 'dama', 'doa', 'expired', 'er_death') THEN 1 ELSE 0 END) AS discharges
    FROM patients p
    LEFT JOIN beds b ON p.current_bed = b.bed_id
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) BETWEEN ? AND ?
    GROUP BY service_area
");
$serviceAreaPatientStmt->bind_param("ss", $startDate, $endDate);
$serviceAreaPatientStmt->execute();
$serviceAreaPatientResults = $serviceAreaPatientStmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($serviceAreaPatientResults as $item) {
    $area = $item['service_area'];
    if (!isset($serviceAreaRows[$area])) {
        continue;
    }
    $serviceAreaRows[$area]['patients'] = (int) $item['patients'];
    $serviceAreaRows[$area]['active_or_admitted'] = $area === 'Ward' ? (int) $item['ward_admitted'] : (int) $item['active_er'];
    $serviceAreaRows[$area]['admissions'] = (int) $item['admissions'];
    $serviceAreaRows[$area]['discharges'] = (int) $item['discharges'];
}

$serviceAreaBedRows = $conn->query("
    SELECT
        CASE WHEN room_number LIKE 'WARD-%' THEN 'Ward' ELSE 'Emergency Room' END AS service_area,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_beds,
        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) AS occupied_beds
    FROM beds
    WHERE room_number LIKE 'ER-%' OR room_number LIKE 'WARD-%'
    GROUP BY service_area
")->fetch_all(MYSQLI_ASSOC);
foreach ($serviceAreaBedRows as $item) {
    $area = $item['service_area'];
    if (!isset($serviceAreaRows[$area])) {
        continue;
    }
    $serviceAreaRows[$area]['available_beds'] = (int) $item['available_beds'];
    $serviceAreaRows[$area]['occupied_beds'] = (int) $item['occupied_beds'];
}

$serviceAreaTatRows = [
    'Emergency Room' => array_fill_keys($tatKeys, 0),
    'Ward' => array_fill_keys($tatKeys, 0)
];
$serviceAreaTatStmt = $conn->prepare("
    SELECT
        CASE
            WHEN p.status = 'admit_inpatient' OR b.room_number LIKE 'WARD-%' THEN 'Ward'
            ELSE 'Emergency Room'
        END AS service_area,
        GREATEST(TIMESTAMPDIFF(
            MINUTE,
            COALESCE(TIMESTAMP(p.triage_date, p.triage_time), p.arrival_time),
            COALESCE((SELECT COALESCE(pt.status_ended_at, pt.created_at) FROM patient_tracking pt WHERE pt.patient_id = p.patient_id AND pt.action = 'Status Updated' ORDER BY COALESCE(pt.status_ended_at, pt.created_at) DESC LIMIT 1), p.updated_at, NOW())
        ), 0) AS minutes
    FROM patients p
    LEFT JOIN beds b ON p.current_bed = b.bed_id
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) BETWEEN ? AND ?
");
$serviceAreaTatStmt->bind_param("ss", $startDate, $endDate);
$serviceAreaTatStmt->execute();
$serviceAreaTatResults = $serviceAreaTatStmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($serviceAreaTatResults as $item) {
    $area = $item['service_area'];
    if (!isset($serviceAreaTatRows[$area])) {
        continue;
    }
    $minutes = (int) $item['minutes'];
    if ($minutes < 120) {
        $serviceAreaTatRows[$area]['tat_lt_2']++;
    } elseif ($minutes < 240) {
        $serviceAreaTatRows[$area]['tat_2_4']++;
    } elseif ($minutes < 360) {
        $serviceAreaTatRows[$area]['tat_4_6']++;
    } else {
        $serviceAreaTatRows[$area]['tat_gt_6']++;
    }
}

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
        $reportAreaCondition
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

$topCasesStmt = $conn->prepare("
    SELECT
        p.final_diagnosis AS case_name,
        COUNT(*) AS total
    FROM patients p
    WHERE DATE(COALESCE(p.arrival_time, p.admission_date, p.created_at)) BETWEEN ? AND ?
        AND p.final_diagnosis IS NOT NULL
        AND p.final_diagnosis <> ''
        $reportAreaCondition
    GROUP BY case_name
    ORDER BY total DESC, case_name ASC
    LIMIT 10
");
$topCasesStmt->bind_param("ss", $startDate, $endDate);
$topCasesStmt->execute();
$topCases = $topCasesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$totalCases = array_sum(array_column($topCases, 'total'));

function reportPercent($value, $total) {
    return $total > 0 ? number_format(($value / $total) * 100, 2) . '%' : '0.00%';
}

$isPdfReport = isset($_GET['pdf_report']) || isset($_GET['print_pdf']);
$shouldAutoPrintPdf = isset($_GET['print_pdf']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Nursing Information System</title>
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
            border-left: 4px solid #EAF6FF;
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
        .top-cases-table {
            min-width: 0 !important;
            table-layout: fixed;
        }
        .top-cases-table th,
        .top-cases-table td {
            white-space: normal;
        }
        .top-cases-table col.rank-col {
            width: 12%;
        }
        .top-cases-table col.case-col {
            width: 46%;
        }
        .top-cases-table col.total-col {
            width: 20%;
        }
        .top-cases-table col.percent-col {
            width: 22%;
        }
        .report-panel .top-cases-table thead tr:first-child th:first-child,
        .report-panel .top-cases-table tbody td:first-child {
            width: 12% !important;
        }
        .top-cases-table th:nth-child(1),
        .top-cases-table td:nth-child(1) {
            text-align: center;
        }
        .top-cases-table th:nth-child(2),
        .top-cases-table td:nth-child(2) {
            text-align: left;
            padding-left: 28px;
            word-break: normal;
            overflow-wrap: anywhere;
        }
        .top-cases-table th:nth-child(3),
        .top-cases-table td:nth-child(3),
        .top-cases-table th:nth-child(4),
        .top-cases-table td:nth-child(4) {
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
        .metric-success { color: #0B3C5D; font-weight: 700; }
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
        .report-actions {
            display: flex;
            gap: 8px;
            align-items: end;
            flex-wrap: wrap;
        }
        .pdf-only {
            display: none;
        }
        .report-letterhead {
            border-bottom: 3px solid #0B3C5D;
            margin-bottom: 22px;
            padding-bottom: 16px;
        }
        .report-letterhead-top {
            display: grid;
            grid-template-columns: 80px 1fr 160px;
            gap: 18px;
            align-items: center;
        }
        .report-letterhead img {
            max-height: 74px;
            max-width: 100%;
            object-fit: contain;
        }
        .letterhead-branding {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            align-items: center;
        }
        .letterhead-title {
            text-align: center;
            color: #0B3C5D;
        }
        .letterhead-title h1 {
            margin: 0;
            font-size: 22px;
            line-height: 1.2;
            color: #0B3C5D;
        }
        .letterhead-title p {
            margin: 3px 0;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
        }
        .report-title-block {
            background: #eef4f8;
            border: 1px solid #d4e2ea;
            border-radius: 6px;
            margin: 16px 0 0;
            padding: 12px 14px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }
        .report-title-block h2 {
            margin: 0;
            color: #0B3C5D;
        }
        .report-title-block span {
            color: #475569;
            font-weight: 700;
        }
        body.report-pdf-mode {
            background: #f3f6f8;
        }
        .report-pdf-mode .dashboard-container {
            display: block;
            max-width: 1120px;
            margin: 24px auto;
            padding: 0 18px;
        }
        .report-pdf-mode .main-content {
            margin: 0;
            width: 100%;
        }
        .report-pdf-mode .patients-section {
            background: #fff;
            border: 1px solid #dbe7f0;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(30, 58, 95, 0.08);
        }
        .report-pdf-mode .no-print {
            display: none !important;
        }
        .pdf-preview-actions {
            display: none;
        }
        .report-pdf-mode .pdf-preview-actions {
            display: flex !important;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 16px;
        }
        .report-pdf-mode,
        .report-pdf-mode * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .report-pdf-mode .main-content > *,
        .report-pdf-mode .report-panel {
            animation: none !important;
            opacity: 1 !important;
            transform: none !important;
        }
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            body,
            body.report-pdf-mode {
                background: #fff;
            }
            .no-print,
            .report-pdf-mode .pdf-preview-actions.no-print,
            .navbar,
            .sidebar,
            .report-filter {
                display: none !important;
            }
            .pdf-only {
                display: block;
            }
            .dashboard-container,
            .report-pdf-mode .dashboard-container {
                display: block;
                margin: 0;
                padding: 0;
                max-width: none;
            }
            .main-content,
            .report-pdf-mode .main-content {
                margin: 0;
                width: 100%;
            }
            .patients-section,
            .report-pdf-mode .patients-section {
                box-shadow: none;
                border: 0;
                padding: 0;
            }
            .report-panel {
                break-inside: avoid;
                page-break-inside: avoid;
                border-color: #b8cbd7;
            }
            .report-panel table {
                font-size: 8.5px;
                min-width: 0;
                table-layout: fixed;
            }
            .report-panel th,
            .report-panel td {
                padding: 5px 4px;
                white-space: normal;
                word-break: break-word;
            }
            .report-panel thead tr:first-child th:first-child,
            .report-panel tbody td:first-child,
            .report-panel tfoot td:first-child {
                width: 22%;
            }
            .top-cases-table th:nth-child(1),
            .top-cases-table td:nth-child(1) {
                width: 10% !important;
            }
            .top-cases-table col.rank-col {
                width: 10%;
            }
            .top-cases-table col.case-col {
                width: 46%;
            }
            .top-cases-table col.total-col {
                width: 20%;
            }
            .top-cases-table col.percent-col {
                width: 24%;
            }
            .top-cases-table th:nth-child(2),
            .top-cases-table td:nth-child(2) {
                padding-left: 14px;
            }
            .top-cases-table th:nth-child(3),
            .top-cases-table td:nth-child(3),
            .top-cases-table th:nth-child(4),
            .top-cases-table td:nth-child(4) {
                width: auto;
            }
            .top-cases-table th:nth-child(2),
            .top-cases-table td:nth-child(2) {
                width: auto;
            }
            .report-pdf-mode .main-content > *,
            .report-pdf-mode .report-panel {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
        }
    </style>
<link rel="stylesheet" href="../public/css/module-theme.css?v=18">
<script defer src="../public/js/nursync-ui.js?v=4"></script></head>
<body class="module-ui <?php echo $isPdfReport ? 'report-pdf-mode' : ''; ?>">
    <?php if (!$isPdfReport): ?>
    <nav class="navbar">
        <div class="navbar-brand"><div class="navbar-title-stack"><h1>Joni Villanueva General Hospital</h1><span class="navbar-system-subtitle">Nursing Information System</span></div></div>
        <div class="navbar-menu">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
            <a href="../api/logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>
    <?php endif; ?>

    <div class="dashboard-container">
        <?php if (!$isPdfReport): ?>
            <?php include '../includes/sidebar.php'; ?>
        <?php endif; ?>

        <main class="main-content">
            <section class="patients-section">
                <?php if ($isPdfReport): ?>
                    <div class="report-letterhead">
                        <div class="report-letterhead-top">
                            <img src="../public/images/branding/jvgh_seal.png" alt="JVGH Seal">
                            <div class="letterhead-title">
                                <p>Republic of the Philippines</p>
                                <p>Department of Health</p>
                                <h1>Joni Villanueva General Hospital</h1>
                                <p>Nursing Information System</p>
                            </div>
                            <div class="letterhead-branding">
                                <img src="../public/images/branding/doh_logo.png" alt="DOH Logo">
                                <img src="../public/images/branding/bagong_pilipinas_logo.png" alt="Bagong Pilipinas Logo">
                            </div>
                        </div>
                        <div class="report-title-block">
                            <h2><?php echo htmlspecialchars($reportAreaLabel . ' ' . ($isMonthlyReport ? 'Monthly Report' : 'Daily Report')); ?></h2>
                            <span><?php echo htmlspecialchars($isMonthlyReport ? $reportMonth : ($startDate . ' to ' . $endDate)); ?></span>
                        </div>
                    </div>
                    <div class="pdf-preview-actions no-print">
                        <a href="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF']) . '?' . http_build_query(array_diff_key($_GET, ['pdf_report' => true, 'print_pdf' => true]))); ?>" class="btn btn-secondary">Back</a>
                    </div>
                <?php endif; ?>
                <div class="<?php echo $isPdfReport ? 'no-print' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <h2><?php echo htmlspecialchars($reportAreaLabel . ' ' . ($isMonthlyReport ? 'Monthly Report' : 'Daily Report')); ?></h2>
                    <form method="GET" class="report-filter">
                        <input type="hidden" name="area" value="<?php echo htmlspecialchars($reportAreaQuery); ?>">
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
                        <div class="report-actions">
                            <button type="submit" name="print_pdf" value="1" class="btn btn-info">Download PDF Report</button>
                        </div>
                    </form>
                </div>

                <div class="report-panels">
                    <?php if (false): ?>
                    <?php foreach (['Emergency Room', 'Ward'] as $serviceArea): ?>
                        <?php $serviceRow = $serviceAreaRows[$serviceArea]; ?>
                        <div class="report-panel">
                            <div class="report-panel-header">
                                <h3><?php echo htmlspecialchars($serviceArea); ?> Report</h3>
                                <span><?php echo htmlspecialchars($startDate . ' to ' . $endDate); ?></span>
                            </div>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Area</th>
                                            <th class="number-header">Patients</th>
                                            <th class="number-header"><?php echo $serviceArea === 'Ward' ? 'Admitted' : 'Active'; ?></th>
                                            <th class="number-header">Admissions</th>
                                            <th class="number-header">Discharges</th>
                                            <th class="number-header">Available Beds</th>
                                            <th class="number-header">Occupied Beds</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo htmlspecialchars($serviceArea); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($serviceRow['patients']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($serviceRow['active_or_admitted']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($serviceRow['admissions']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($serviceRow['discharges']); ?></td>
                                            <td class="number-cell metric-success"><?php echo htmlspecialchars($serviceRow['available_beds']); ?></td>
                                            <td class="number-cell metric-warning"><?php echo htmlspecialchars($serviceRow['occupied_beds']); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <?php $serviceTatRow = $serviceAreaTatRows[$serviceArea]; ?>
                        <div class="report-panel">
                            <div class="report-panel-header">
                                <h3><?php echo htmlspecialchars($serviceArea); ?> Turn Around Time</h3>
                                <span><?php echo htmlspecialchars($startDate . ' to ' . $endDate); ?></span>
                            </div>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Area</th>
                                            <th class="number-header">&lt; 2 Hours</th>
                                            <th class="number-header">2 to &lt; 4 Hours</th>
                                            <th class="number-header">4 to &lt; 6 Hours</th>
                                            <th class="number-header">&gt; 6 Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo htmlspecialchars($serviceArea); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($serviceTatRow['tat_lt_2']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($serviceTatRow['tat_2_4']); ?></td>
                                            <td class="number-cell"><?php echo htmlspecialchars($serviceTatRow['tat_4_6']); ?></td>
                                            <td class="number-cell <?php echo $serviceTatRow['tat_gt_6'] > 0 ? 'metric-danger' : ''; ?>"><?php echo htmlspecialchars($serviceTatRow['tat_gt_6']); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="report-panel">
                        <div class="report-panel-header">
                            <h3>Turn Around Time</h3>
                            <span><?php echo htmlspecialchars($startDate . ' to ' . $endDate); ?></span>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Triage Scale</th>
                                        <th class="number-header">&lt; 2 Hours</th>
                                        <th class="number-header">2 to &lt; 4 Hours</th>
                                        <th class="number-header">4 to &lt; 6 Hours</th>
                                        <th class="number-header">&gt; 6 Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tatTriageRows as $triageKey => $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tatTriageLabels[$triageKey]); ?></td>
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
                                            <td class="number-cell"><?php echo htmlspecialchars($tatTriageTotals[$key]); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <td>Grand Total</td>
                                        <td class="number-cell" colspan="4"><?php echo htmlspecialchars($tatTotal); ?></td>
                                    </tr>
                                    <tr>
                                        <td>%</td>
                                        <?php foreach ($tatKeys as $key): ?>
                                            <td class="number-cell"><?php echo htmlspecialchars(reportPercent($tatTriageTotals[$key], $tatTotal)); ?></td>
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
                            <h3>Top 10 Cases</h3>
                            <span><?php echo htmlspecialchars($totalCases); ?> total</span>
                        </div>
                        <div class="table-responsive">
                            <table class="top-cases-table">
                                <colgroup>
                                    <col class="rank-col">
                                    <col class="case-col">
                                    <col class="total-col">
                                    <col class="percent-col">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Case</th>
                                        <th class="number-header">Total</th>
                                        <th class="number-header">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($topCases)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No cases found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($topCases as $index => $case): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($index + 1); ?></td>
                                                <td><?php echo htmlspecialchars($case['case_name']); ?></td>
                                                <td class="number-cell"><?php echo htmlspecialchars($case['total']); ?></td>
                                                <td class="number-cell"><?php echo htmlspecialchars(reportPercent($case['total'], $totalCases)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
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
    <?php if ($shouldAutoPrintPdf): ?>
        <script>
            window.addEventListener('load', function () {
                const images = Array.from(document.images);
                const waitForImages = images.map(function (image) {
                    if (image.complete) {
                        return Promise.resolve();
                    }
                    return new Promise(function (resolve) {
                        image.addEventListener('load', resolve, { once: true });
                        image.addEventListener('error', resolve, { once: true });
                    });
                });

                Promise.all(waitForImages).then(function () {
                    setTimeout(function () {
                        window.print();
                    }, 800);
                });
            });
        </script>
    <?php endif; ?>
    <script src="../public/js/sidebar.js"></script>
</body>
</html>
